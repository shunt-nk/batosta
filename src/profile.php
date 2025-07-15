<?php
session_start();
require 'includes/db.php';

if (!isset($_SESSION['user'])) {
  header("Location: login.php");
  exit;
}

$user = $_SESSION['user'];
$user_id = $user['id'];

// 勉強時間の合計を取得
$stmt = $pdo->prepare("SELECT SUM(duration_minutes) AS total FROM study_logs WHERE user_id = ?");
$stmt->execute([$user_id]);
$total = $stmt->fetchColumn() ?? 0;

// 経験値 → レベル（例：30分でLv1、90分でLv2）
$level = floor($total / 60) + 1;

// 現在の装備を取得（アバター表示用）
$stmt = $pdo->prepare("
  SELECT e.slot, e.image_path
  FROM user_avatar_equipments uae
  JOIN equipments e ON uae.equipment_id = e.id
  WHERE uae.user_id = ?
");
$stmt->execute([$user_id]);
$equipments = $stmt->fetchAll(PDO::FETCH_KEY_PAIR); // ['head' => 'head.png', ...]
?>

<style>
    body {
      margin: 0;
      display: flex;
      background: #5D73A9;
      color: #fff;
    }
    .container {
      display: flex;
      min-height: 100vh;
    }
    .content {
      padding: 2rem;
    }

</style>
<div class="container">
<?php include 'includes/navbar.php'; ?>
  <main class="content">
    <h1>プロフィール</h1>
  
    <p><strong>ニックネーム：</strong> <?= htmlspecialchars($user['nickname'] ?? '未設定') ?></p>
    <p><strong>メール：</strong> <?= htmlspecialchars($user['email']) ?></p>
    <p><strong>総勉強時間：</strong> <?= $total ?> 分</p>
    <p><strong>レベル：</strong> Lv<?= $level ?></p>
  
    <h2>現在のアバター</h2>
    <div class="avatar-container" style="position:relative; width:200px; height:200px;">
      <img src="images/avatar/base.png" class="avatar-layer" style="position:absolute; top:0; left:0; width:100%;">
      <?php foreach (['head', 'body', 'weapon', 'shield', 'feet'] as $slot): ?>
        <?php if (isset($equipments[$slot])): ?>
          <img src="images/avatar/<?= htmlspecialchars($equipments[$slot]) ?>" class="avatar-layer" style="position:absolute; top:0; left:0; width:100%;">
        <?php endif; ?>
      <?php endforeach; ?>
    </div>
  </main>
</div>
