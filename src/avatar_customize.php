<?php
session_start();
require 'includes/db.php';

function renderAvatarLayers($current) {
  $html = '<div class="avatar-container" style="position: relative; width: 200px; height: 200px;">';
  $html .= '<img src="images/avatar/base.png" class="avatar-layer" style="position:absolute; top:0; left:0; width:100%;">';

  foreach (['head', 'body', 'weapon', 'shield', 'feet'] as $slot) {
    if (isset($current[$slot]['image_path'])) {
      $image_path = htmlspecialchars($current[$slot]['image_path']);
      $html .= '<img src="images/avatar/' . $image_path . '" class="avatar-layer" style="position:absolute; top:0; left:0; width:100%;">';
    }
  }

  $html .= '</div>';
  return $html;
}

if (!isset($_SESSION['user'])) {
  header("Location: login.php");
  exit;
}

$user_id = $_SESSION['user']['id'];

// POST: 装備変更処理
if ($_SERVER["REQUEST_METHOD"] === "POST") {
  $slot = $_POST["slot"];
  $equipment_id = (int)$_POST["equipment_id"];

  $stmt = $pdo->prepare("SELECT * FROM user_avatar_equipments WHERE user_id = ? AND slot = ?");
  $stmt->execute([$user_id, $slot]);
  if ($stmt->fetch()) {
    $update = $pdo->prepare("UPDATE user_avatar_equipments SET equipment_id = ? WHERE user_id = ? AND slot = ?");
    $update->execute([$equipment_id, $user_id, $slot]);
  } else {
    $insert = $pdo->prepare("INSERT INTO user_avatar_equipments (user_id, slot, equipment_id) VALUES (?, ?, ?)");
    $insert->execute([$user_id, $slot, $equipment_id]);
  }

  $message = "✅ 「$slot」を着せ替えました！";
}

// 所持装備を取得
$stmt = $pdo->prepare("
  SELECT ue.*, e.name, e.slot, e.image_path, e.attack, e.defense
  FROM user_equipments ue
  JOIN equipments e ON ue.equipment_id = e.id
  WHERE ue.user_id = ?
");
$stmt->execute([$user_id]);
$my_equipments_raw = $stmt->fetchAll(PDO::FETCH_ASSOC);

// slot ごとにグループ化
$my_equipments = [];
foreach ($my_equipments_raw as $eq) {
  $my_equipments[$eq['slot']][] = $eq;
}

// 現在の装備
$stmt = $pdo->prepare("
  SELECT uae.slot, uae.equipment_id, e.name, e.image_path, e.attack, e.defense
  FROM user_avatar_equipments uae
  JOIN equipments e ON uae.equipment_id = e.id
  WHERE uae.user_id = ?
");
$stmt->execute([$user_id]);
$current = [];
foreach ($stmt->fetchAll() as $eq) {
  $current[$eq['slot']] = $eq;
}

// ステータス集計
$total_atk = 0;
$total_def = 0;
foreach ($current as $item) {
  $total_atk += $item['attack'] ?? 0;
  $total_def += $item['defense'] ?? 0;
}
?>

<!DOCTYPE html>
<html lang="ja">
<head>
  <meta charset="UTF-8">
  <title>アバター着せ替え</title>
  <style>
    body {
      margin: 0;
      display: flex;
      font-family: sans-serif;
      background: #f4f4f4;
    }
    .container {
    display: flex;
    min-height: 100vh;
    }
    .content {
    flex: 1;
    padding: 2rem;
    background: #f4f4f4;
  }

    .section {
      background: white;
      padding: 1rem;
      margin-bottom: 2rem;
      border-radius: 10px;
    }
    .equip-item {
      display: flex;
      align-items: center;
      margin-bottom: 0.8rem;
      gap: 1rem;
    }
    .equip-item img {
      width: 50px;
      height: 50px;
      object-fit: contain;
    }
    button {
      padding: 0.5rem 1rem;
      border: none;
      border-radius: 5px;
      background: #008cba;
      color: white;
      cursor: pointer;
    }
    .avatar-container {
      position: relative;
      width: 200px;
      height: 200px;
      margin-bottom: 1rem;
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
    <h1>アバターの着せ替え</h1>
    <?php if (isset($message)) echo "<p>$message</p>"; ?>

    <div style="display: flex; gap: 2rem;">
      <!-- 左側：アバターとステータス -->
      <div style="flex: 1;">
        <div class="section">
          <h2>現在のアバター</h2>
          <?= renderAvatarLayers($current) ?>
          <p><strong>攻撃力:</strong> <?= $total_atk ?> / <strong>防御力:</strong> <?= $total_def ?></p>
        </div>
      </div>

      <!-- 右側：装備選択 -->
      <div style="flex: 2;">
        <?php foreach (['head', 'body', 'weapon', 'shield', 'feet'] as $slot): ?>
          <?php if (isset($my_equipments[$slot])): ?>
            <div class="section">
              <h2><?= ucfirst($slot) ?> を選ぶ</h2>
              <?php foreach ($my_equipments[$slot] as $eq): ?>
                <div class="equip-item">
                  <img src="assets/avatars/<?= htmlspecialchars($eq['image_path']) ?>" alt="">
                  <div>
                    <div><strong><?= htmlspecialchars($eq['name']) ?></strong></div>
                    <div>攻: <?= $eq['attack'] ?> / 防: <?= $eq['defense'] ?></div>
                  </div>
                  <form method="POST" style="margin-left:auto;">
                    <input type="hidden" name="slot" value="<?= $slot ?>">
                    <input type="hidden" name="equipment_id" value="<?= $eq['equipment_id'] ?>">
                    <button type="submit">装備</button>
                  </form>
                </div>
              <?php endforeach; ?>
            </div>
          <?php endif; ?>
        <?php endforeach; ?>
      </div>
    </div>

    <p><a href="dashboard.php">← ホームへ戻る</a></p>
  </main>
</div>
</body>
</html>
