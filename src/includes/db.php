<?php
// Guard: prevent re-execution if already connected (e.g. when included via session.php)
if (isset($pdo)) return;

$host    = getenv('DB_HOST')     ?: 'db';
$dbname  = getenv('DB_NAME')     ?: 'postgres';
$user    = getenv('DB_USER')     ?: 'postgres';
$pass    = getenv('DB_PASSWORD') ?: 'password';
$port    = getenv('DB_PORT')     ?: '5432';
$sslmode = getenv('DB_SSLMODE')  ?: 'require';

try {
    $pdo = new PDO("pgsql:host=$host;port=$port;dbname=$dbname;sslmode=$sslmode", $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    // エミュレートPreparedStatement: prepare()をPHP側で処理しサーバーに送らない
    // → トランザクション内でprepare失敗によるABORTEDを防ぐ
    $pdo->setAttribute(PDO::ATTR_EMULATE_PREPARES, true);
} catch (PDOException $e) {
    die("DB接続エラー: " . $e->getMessage());
}
