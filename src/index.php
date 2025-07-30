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

<link rel="stylesheet" href="styles/index.css">
<script src="js/index.js"></script>
<body>
  <div class="form_container">
    <img src="assets/icons/logo.png" alt="ロゴ">
    <form method="POST">
      <h2>ログイン</h2>
      <input class="user_text" type="email" name="email" placeholder="メールアドレス" required>
      <input class="user_text" type="password" name="password" id="password" placeholder="パスワード" required>

      <label><input type="checkbox" class="custom-checkbox" onclick="togglePassword()"> パスワードを表示</label>
      <button type="submit">ログイン</button>
      <a href="reset_request.php" class="reset">パスワードを忘れた方は<span>こちら</span></a>
      <a href="register.php" class="new">新規登録はこちら</a>
    </form>
  </div>
</body>
