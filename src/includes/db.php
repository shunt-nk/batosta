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
} catch (PDOException $e) {
    die("DB接続エラー: " . $e->getMessage());
}
