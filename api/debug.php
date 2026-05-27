<?php
// デバッグ確認用（確認後は削除すること）
echo "PHP: " . phpversion() . "\n";
echo "DB_HOST: " . (getenv('DB_HOST') ?: '未設定') . "\n";
echo "DB_NAME: " . (getenv('DB_NAME') ?: '未設定') . "\n";
echo "DB_USER: " . (getenv('DB_USER') ?: '未設定') . "\n";
echo "DB_PORT: " . (getenv('DB_PORT') ?: '未設定') . "\n";
echo "pdo_pgsql: " . (extension_loaded('pdo_pgsql') ? '✅ 有効' : '❌ 無効') . "\n";

try {
    $host = getenv('DB_HOST');
    $port = getenv('DB_PORT') ?: '5432';
    $name = getenv('DB_NAME');
    $user = getenv('DB_USER');
    $pass = getenv('DB_PASSWORD');
    $pdo = new PDO("pgsql:host=$host;port=$port;dbname=$name;sslmode=require", $user, $pass);
    echo "DB接続: ✅ 成功\n";
} catch (Exception $e) {
    echo "DB接続: ❌ 失敗 → " . $e->getMessage() . "\n";
}
