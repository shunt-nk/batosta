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

    header("Location: index.php");
    exit;
}
?>

<link rel="stylesheet" href="styles/index.css">
<script src="js/index.js"></script>
<body>
  <div class="form_container"> 
    <img src="assets/icons/logo.png" alt="ロゴ">
    <form method="POST">
      <h2>新規登録</h2>
      <input class="user_text" type="text" name="username" placeholder="ユーザーネーム" required>
      <input class="user_text" type="email" name="email" placeholder="メールアドレス" required>
      <input class="user_text" type="password" name="password" id="password" placeholder="パスワード" required>
      <label><input type="checkbox" class="custom-checkbox" onclick="togglePassword()"> パスワードを表示</label>
    
      <button type="submit">登録</button>
      <a href="index.php" class="new">ログイン</a>
    </form>
  </div>
</body>
