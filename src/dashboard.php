<?php
session_start();
require 'includes/db.php';
require 'includes/functions.php';


if (!isset($_SESSION['user'])) {
  header("Location: login.php");
  exit;
}

$user = $_SESSION['user'];
$user_id = $user['id'];

// アバター情報
$stmt = $pdo->prepare("SELECT * FROM avatars WHERE user_id = ?");
$stats = calculateUserStats($pdo, $user_id);
$stmt->execute([$user_id]);
$avatar = $stmt->fetch();

// 現在の装備を取得
$stmt = $pdo->prepare("
  SELECT e.slot, e.name
  FROM user_avatar_equipments uae
  JOIN equipments e ON uae.equipment_id = e.id
  WHERE uae.user_id = ?
");
$stmt->execute([$user_id]);
$equipped = [];
foreach ($stmt->fetchAll() as $row) {
  $equipped[$row['slot']] = $row['name'];
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
  <style>
    body {
      margin: 0;
      display: flex;
      background: #5D73A9;
    }
    .container {
      display: flex;
      min-height: 100vh;
    }
    .content {
      padding: 2rem;
    }
    main {
      flex: 1;
      padding: 2rem;
    }
    section {
      background: #484E88;
      margin-bottom: 2rem;
      padding: 1.5rem;
      border-radius: 20px;
      color: white;
    }

    .hw{
      margin-top: 2rem;
      margin-right: 1.5rem;
      width: 400px;
      height: 690px;
    }
    .mt{
      margin-top: 2rem;
      width: 400px;
      height: 690px;


    }
    .progress-bar {
      background: #eee;
      height: 20px;
      border-radius: 10px;
      overflow: hidden;
    }
    .progress {
      height: 100%;
      background: linear-gradient(to right, orange, gold);
    }
    .avatar-container {
    position: relative;
    width: 200px;
    height: 200px;
    margin: auto;
    }
    .avatar-layer {
      position: absolute;
      top: 0;
      left: 0;
      width: 100%;
}
  </style>
</head>
<body>

<div class="container">
 <?php include 'includes/navbar.php'; ?>
 <main class="content">
   <section>
     <p>レベル：<?= $avatar['level'] ?? 1 ?> / 経験値：<?= $avatar['exp'] ?? 0 ?> / 性別：<?= $avatar['gender'] ?? '不明' ?></p>
     <div class="progress-bar">
       <div class="progress" style="width: <?= ($avatar['exp'] % 100) ?>%;"></div>
      </div>
    </section>
    <section>
      <h2>現在のステータス</h2>
      <p>攻撃力：<?= $stats['attack'] ?> / 防御力：<?= $stats['defense'] ?></p>
    </section>
    <section>
      <h2>あなたのアバター</h2>
      <?= renderAvatarLayers($equipped) ?>
    </section>
  </div>
  
  
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
    </main>
  </div>
    
  </body>
  </html>
