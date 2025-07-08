<?php
session_start();
if (!isset($_SESSION["user"])) {
    header("Location: login.php");
    exit;
}
?>

<h2>ようこそ、<?= htmlspecialchars($_SESSION["user"]["username"]) ?> さん！</h2>
<ul>
  <li><a href="avatar.php">アバターを設定する</a></li>
  <li><a href="logout.php">ログアウト</a></li>
</ul>
