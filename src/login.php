<?php
session_start();
require 'includes/db.php';

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $email = $_POST["email"];
    $password = $_POST["password"];

    $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ?");
    $stmt->execute([$email]);
    $user = $stmt->fetch();

    if ($user && password_verify($password, $user["password"])) {
        $_SESSION["user"] = $user;
        header("Location: dashboard.php");
        exit;
    } else {
        echo "ログイン失敗";
    }
}
?>

<form method="POST">
  <h2>ログイン</h2>
  <input type="email" name="email" placeholder="メールアドレス" required>
  <input type="password" name="password" placeholder="パスワード" required>
  <button type="submit">ログイン</button>
</form>
