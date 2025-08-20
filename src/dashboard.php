<?php
session_start();
require 'includes/db.php';
require 'includes/functions.php';


if (!isset($_SESSION['user'])) {
  header("Location: index.php");
  exit;
}


$user = $_SESSION['user'];
$user_id = $user['id'];
$status = getAvatarStatus($pdo, $user_id);
// 現在の装備（=アバターパーツ）を取得
$selectedParts = fetchSelectedParts($pdo, $user_id);
echo renderAvatarLayers($selectedParts);

// アバター情報
$stmt = $pdo->prepare("SELECT * FROM avatars WHERE user_id = ?");
// この1行で統合された状態が入る
$status = getAvatarStatusWithEquip($pdo, $user_id);
$stmt->execute([$user_id]);
$avatar = $stmt->fetch();

// 現在の装備を取得
$stmt = $pdo->prepare("
  SELECT uae.slot, e.image_path
  FROM user_avatar_equipments uae
  JOIN equipments e ON uae.equipment_id = e.id
  WHERE uae.user_id = ?
");
$stmt->execute([$user_id]);
$equipped = [];
foreach ($stmt->fetchAll() as $row) {
  $equipped[$row['slot']] = [
    'image_path' => $row['image_path']
  ];
}


// 素材所持数
$stmt = $pdo->prepare("
  SELECT m.name, um.quantity
  FROM user_materials um
  JOIN materials m ON um.material_id = m.id
  WHERE um.user_id = ?
");
$stmt->execute([$user_id]);
$materials = $stmt->fetchAll();

// 今日の宿題記録
$today = date("Y-m-d");
$stmt = $pdo->prepare("
  SELECT * FROM study_logs
  WHERE user_id = ? AND DATE(started_at) = ?
  ORDER BY started_at DESC
");
$stmt->execute([$user_id, $today]);
$logs = $stmt->fetchAll();

$current_page = 'home'; 

?>

<!DOCTYPE html>
<html lang="ja">
<head>
  <meta charset="UTF-8">
  <title>バトスタ ホーム</title>
  <link rel="stylesheet" href="styles/style.css">
  <link rel="stylesheet" href="styles/dashboard.css">
</head>
<body>

<div class="container">
 <?php include 'includes/navbar.php'; ?>
 <main class="content">
   <section>
   <p>レベル：<?= $status['level'] ?> / 経験値：<?= $status['exp'] ?></p>
   <?php
      function requiredExp($level) {
        return 100 + ($level - 1) * 20;
      }
      $progress_percent = round(($status['exp'] / requiredExp($status['level'])) * 100);
      ?>
      <div class="progress-bar">
        <div class="progress" style="width: <?= $progress_percent ?>%;"></div>
      </div>
    </section>
    <section>
      <h2>現在のステータス</h2>
      <p>攻撃力：<?= $status['attack'] ?> / 防御力：<?= $status['defense'] ?></p>    </section>
    <section class="avatar_section">
      <h2>あなたのアバター</h2>
      <?= renderAvatarLayers($selectedParts) ?>
    </section>
  </div>
  
  
<!-- このあたりを変更 -->
  <div class="dashboard-columns">
    <section class="hw">
      <h3>今日の宿題履歴（<?= count($logs) ?>件）</h3>
      <ul>
        <?php foreach ($logs as $log): ?>
          <li><?= $log['subject'] ?> / <?= $log['type'] ?>（<?= $log['duration_minutes'] ?>分）</li>
        <?php endforeach; ?>
        <?php if (count($logs) === 0): ?>
          <li>まだ今日の宿題はありません</li>
        <?php endif; ?>
      </ul>
    </section>

    <section class="mt">
      <h3>所持素材</h3>
      <ul>
        <?php foreach ($materials as $m): ?>
          <li><?= $m['name'] ?> × <?= $m['quantity'] ?></li>
        <?php endforeach; ?>
        <?php if (count($materials) === 0): ?>
          <li>素材はまだありません</li>
        <?php endif; ?>
      </ul>
    </section>
  </div>
</div>
</main>
    
  </body>
  </html>
