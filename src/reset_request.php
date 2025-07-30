<?php
session_start();
require 'includes/db.php';

$message = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $email = $_POST["email"];

    $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ?");
    $stmt->execute([$email]);
    $user = $stmt->fetch();

    if ($user) {
        $_SESSION["reset_user_id"] = $user["id"];
        header("Location: reset_password.php");
        exit;
    } else {
        $message = "このメールアドレスは登録されていません。";
    }
}
?>

<link rel="stylesheet" href="styles/index.css">
<body>
  <div class="form_container">
    <img src="assets/icons/logo.png" alt="ロゴ">
    <form method="POST">
      <h2>パスワード再設定</h2>
      <p><?= $message ?></p>
      <input class="user_text" type="email" name="email" placeholder="登録済みのメールアドレス" required>
      <button type="submit">次へ</button>
      <a href="index.php" class="new" id="back">ログインに戻る</a>
    </form>
  </div>
</body>
