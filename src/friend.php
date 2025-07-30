<?php
session_start();
require 'includes/db.php';

if (!isset($_SESSION['user'])) {
  header("Location: index.php");
  exit;
}

$user_id = $_SESSION['user']['id'];
$message = '';

// フレンド追加処理
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['friend_email'])) {
  $email = trim($_POST['friend_email']);

  // 自分以外のユーザーを検索
  $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
  $stmt->execute([$email, $user_id]);
  $friend = $stmt->fetch();

  if ($friend) {
    // 重複チェック
    $check = $pdo->prepare("SELECT * FROM friends WHERE user_id = ? AND friend_id = ?");
    $check->execute([$user_id, $friend['id']]);

    if (!$check->fetch()) {
      $insert = $pdo->prepare("INSERT INTO friends (user_id, friend_id) VALUES (?, ?)");
      $insert->execute([$user_id, $friend['id']]);
      $message = "✅ フレンドを追加しました！";
    } else {
      $message = "⚠️ すでに追加済みです。";
    }
  } else {
    $message = "❌ 該当するユーザーが見つかりません。";
  }
}

// フレンド一覧取得
$stmt = $pdo->prepare("
  SELECT u.id, u.nickname, u.email
  FROM friends f
  JOIN users u ON f.friend_id = u.id
  WHERE f.user_id = ?
");
$stmt->execute([$user_id]);
$friends = $stmt->fetchAll();

$current_page = 'friend'; 

?>

<link rel="stylesheet" href="styles/style.css">

<div class="container">
<?php include 'includes/navbar.php'; ?>
  <main class="content">
  
    <?php if ($message): ?>
      <p><?= htmlspecialchars($message) ?></p>
    <?php endif; ?>
  
    <h2>フレンドを追加</h2>
    <form method="POST">
      <input type="email" name="friend_email" placeholder="相手のメールアドレス" required>
      <button type="submit">追加</button>
    </form>
  
    <h2>フレンド一覧</h2>
    <ul>
      <?php foreach ($friends as $f): ?>
        <li>
          <?= htmlspecialchars($f['nickname'] ?? '未設定') ?>（<?= htmlspecialchars($f['email']) ?>）
        </li>
      <?php endforeach; ?>
      <?php if (empty($friends)): ?>
        <li>現在フレンドはいません。</li>
      <?php endif; ?>
    </ul>
  </main>
</div>
