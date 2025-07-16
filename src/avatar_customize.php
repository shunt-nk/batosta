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

$current_page = 'customize'; 

?>

<!DOCTYPE html>
<html lang="ja">
<head>
  <meta charset="UTF-8">
  <title>アバター着せ替え</title>
</head>
<style>
  /* avatar_customize.css */

body {
  margin: 0;
  display: flex;
  background: #5D73A9;
  font-family: sans-serif;
  color: #fff;
}

.container {
  display: flex;
  min-height: 100vh;
  width: 100%;
}

.content {
  padding: 2rem;
  flex: 1;
  display: flex;
  flex-direction: column;
  gap: 2rem;
}

.section {
  background: #fff;
  color: #333;
  padding: 1rem;
  border-radius: 10px;
  box-shadow: 0 2px 5px rgba(0,0,0,0.2);
}

.avatar-preview-area {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 1rem;
  flex: 1;
}

.avatar-container {
  position: relative;
  width: 200px;
  height: 200px;
}

.avatar-layer {
  position: absolute;
  top: 0;
  left: 0;
  width: 100%;
}

.status-box {
  display: flex;
  gap: 2rem;
  font-weight: bold;
  font-size: 1.2rem;
  justify-content: center;
}

.slot-icons {
  display: flex;
  gap: 1rem;
  justify-content: center;
  margin-bottom: 1rem;
}

.slot-icons button {
  background: #fff;
  border: none;
  border-radius: 8px;
  width: 50px;
  height: 50px;
  cursor: pointer;
  padding: 0;
}

.slot-icons .active {
  background: linear-gradient(135deg, #FF5F7E, #FFA35C);
}

.equip-preview {
  display: flex;
  gap: 1rem;
  align-items: center;
  background: #DB9963;
  color: white;
  padding: 1rem;
  border-radius: 10px;
}

.equip-preview img {
  width: 80px;
  height: 80px;
}

.equip-grid {
  display: grid;
  grid-template-columns: repeat(4, 1fr);
  gap: 1rem;
  margin-top: 1rem;
}

.equip-grid .item {
  background: #fff;
  border-radius: 8px;
  width: 60px;
  height: 60px;
  display: flex;
  justify-content: center;
  align-items: center;
  cursor: pointer;
  position: relative;
}

.equip-grid .locked::after {
  content: "";
  background: rgba(0,0,0,0.5);
  position: absolute;
  top: 0;
  left: 0;
  width: 100%;
  height: 100%;
  border-radius: 8px;
}

button.equip-button {
  margin-left: auto;
  padding: 0.5rem 1rem;
  background: #008cba;
  color: #fff;
  border: none;
  border-radius: 5px;
  cursor: pointer;
}

</style>

<body>
<div class="container">
  <?php include 'includes/navbar.php'; ?>
  <main class="content">
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

  </main>
</div>
</body>
</html>
