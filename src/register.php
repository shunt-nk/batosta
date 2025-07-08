<?php
require 'includes/db.php';

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $username = $_POST["username"];
    $email = $_POST["email"];
    $password = password_hash($_POST["password"], PASSWORD_DEFAULT);

    // メールアドレス重複チェック
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE email = ?");
    $stmt->execute([$email]);
    $count = $stmt->fetchColumn();

    if ($count > 0) {
        echo "⚠️ すでにそのメールアドレスは登録されています。<a href='login.php'>ログイン</a>";
        exit;
    }

    // 登録処理
    $stmt = $pdo->prepare("INSERT INTO users (username, email, password) VALUES (?, ?, ?)");
    $stmt->execute([$username, $email, $password]);

    header("Location: login.php");
    exit;
}
?>

<form method="POST">
  <h2>ユーザー登録</h2>
  <input type="text" name="username" placeholder="ユーザーネーム" required>
  <input type="email" name="email" placeholder="メールアドレス" required>
  <input type="password" name="password" placeholder="パスワード" required>
  <button type="submit">登録</button>
</form>
