<?php
// src/includes/session.php
// DB-based session handler — works in Docker locally and on Vercel serverless.
// Include this file instead of calling session_start() directly.
declare(strict_types=1);

if (!isset($pdo)) {
    require_once __DIR__ . '/db.php';
}

class DbSessionHandler implements SessionHandlerInterface
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public function open(string $path, string $name): bool
    {
        try {
            $this->pdo->exec(
                'CREATE TABLE IF NOT EXISTS user_sessions (
                    id            VARCHAR(128) NOT NULL PRIMARY KEY,
                    data          TEXT         DEFAULT NULL,
                    last_activity INTEGER      NOT NULL
                )'
            );
            $this->pdo->exec(
                'CREATE INDEX IF NOT EXISTS idx_user_sessions_activity ON user_sessions (last_activity)'
            );
        } catch (\Throwable $e) {
            // テーブル作成失敗は write() 側で改めて検出する
        }
        return true;
    }

    public function close(): bool
    {
        return true;
    }

    public function read(string $id): string|false
    {
        try {
            $lifetime = (int)ini_get('session.gc_maxlifetime') ?: 1440;
            $st = $this->pdo->prepare(
                'SELECT data FROM user_sessions WHERE id = ? AND last_activity > ?'
            );
            $st->execute([$id, time() - $lifetime]);
            $row = $st->fetchColumn();
            return $row !== false ? (string)$row : '';
        } catch (\Throwable $e) {
            return '';
        }
    }

    public function write(string $id, string $data): bool
    {
        try {
            $st = $this->pdo->prepare(
                'INSERT INTO user_sessions (id, data, last_activity) VALUES (?, ?, ?)
                 ON CONFLICT (id) DO UPDATE SET data = EXCLUDED.data, last_activity = EXCLUDED.last_activity'
            );
            $st->execute([$id, $data, time()]);
            return true;
        } catch (\Throwable $e) {
            return false;
        }
    }

    public function destroy(string $id): bool
    {
        try {
            $this->pdo->prepare('DELETE FROM user_sessions WHERE id = ?')->execute([$id]);
            return true;
        } catch (\Throwable $e) {
            return false;
        }
    }

    public function gc(int $max_lifetime): int|false
    {
        try {
            $st = $this->pdo->prepare('DELETE FROM user_sessions WHERE last_activity < ?');
            $st->execute([time() - $max_lifetime]);
            return $st->rowCount();
        } catch (\Throwable $e) {
            return 0;
        }
    }
}

$handler = new DbSessionHandler($pdo);
session_set_save_handler($handler, true);

ini_set('session.cookie_httponly', '1');
ini_set('session.use_strict_mode', '1');
ini_set('session.cookie_samesite', 'Lax');

// Vercel (HTTPS) ではセキュアフラグを有効化
$_isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
    || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
if ($_isHttps) {
    ini_set('session.cookie_secure', '1');
}
unset($_isHttps);

session_start();
