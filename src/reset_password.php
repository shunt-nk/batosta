<?php
session_start();
require 'includes/db.php';

if (!isset($_SESSION["reset_user_id"])) {
  header("Location: reset_request.php");
  exit;
}

$user_id = $_SESSION["reset_user_id"];
$message = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
  $new_password = password_hash($_POST["password"], PASSWORD_DEFAULT);

  $stmt = $pdo->prepare("UPDATE users SET password = ? WHERE id = ?");
  $stmt->execute([$new_password, $user_id]);

  unset($_SESSION["reset_user_id"]);
  $message = "パスワードが更新されました。ログインしてください。";
}
?>

<link rel="stylesheet" href="styles/index.css">
<body>
  <div class="form_container">
    <img src="assets/icons/logo.png" alt="ロゴ">
    <form method="POST">
      <h2>新しいパスワードを設定</h2>
      <p><?= $message ?></p>
      <input class="user_text" type="password" name="password" placeholder="新しいパスワード" required>
      <button type="submit">再設定する</button>
      <a href="index.php" class="new" id="back">ログインに戻る</a>
    </form>
  </div>
</body>
