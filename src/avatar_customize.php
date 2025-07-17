<?php
session_start();
require 'includes/db.php';

function renderAvatarLayers($current, $default_icons) {
  $html = '<div class="avatar-preview-wrapper">';
  $html .= '<div class="avatar-container">';
  $html .= '<img src="images/avatar/base.png" class="avatar-layer">';

  foreach (['head', 'body', 'weapon', 'shield', 'feet'] as $slot) {
    if (!empty($current[$slot]['image_path'])) {
      $image_path = htmlspecialchars($current[$slot]['image_path']);
      $html .= '<img src="images/avatar/' . $image_path . '" class="avatar-layer">';
    } else {
      $html .= '<img src="images/icons/' . $default_icons[$slot] . '" class="slot-icon" data-slot="' . $slot . '">';
    }
  }

  $html .= '</div></div>';
  return $html;
}

if (!isset($_SESSION['user'])) {
  header("Location: login.php");
  exit;
}

$user_id = $_SESSION['user']['id'];
$selected_slot = $_GET['slot'] ?? null;

$slot_labels = [
  'head' => '頭防具',
  'body' => '体防具',
  'weapon' => '武器',
  'shield' => '盾',
  'feet' => '足防具'
];

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
  $selected_slot = $slot;
}

// 所持装備取得
$stmt = $pdo->prepare("SELECT ue.*, e.name, e.slot, e.image_path, e.attack, e.defense
                       FROM user_equipments ue
                       JOIN equipments e ON ue.equipment_id = e.id
                       WHERE ue.user_id = ?");
$stmt->execute([$user_id]);
$my_equipments_raw = $stmt->fetchAll(PDO::FETCH_ASSOC);

$my_equipments = [];
foreach ($my_equipments_raw as $eq) {
  $my_equipments[$eq['slot']][] = $eq;
}

// 現在の装備
$stmt = $pdo->prepare("SELECT uae.slot, uae.equipment_id, e.name, e.image_path, e.attack, e.defense
                       FROM user_avatar_equipments uae
                       JOIN equipments e ON uae.equipment_id = e.id
                       WHERE uae.user_id = ?");
$stmt->execute([$user_id]);
$current = [];
foreach ($stmt->fetchAll() as $eq) {
  $current[$eq['slot']] = $eq;
}

$total_atk = 0;
$total_def = 0;
foreach ($current as $item) {
  $total_atk += $item['attack'] ?? 0;
  $total_def += $item['defense'] ?? 0;
}

$default_icons = [
  'head' => 'icon_head.png',
  'body' => 'icon_body.png',
  'weapon' => 'icon_weapon.png',
  'shield' => 'icon_shield.png',
  'feet' => 'icon_feet.png'
];
?>

<!DOCTYPE html>
<html lang="ja">
<head>
  <meta charset="UTF-8">
  <title>アバター着せ替え</title>
  <link rel="stylesheet" href="styles/avatar_customize.css">
</head>
<body>
<div class="container">
  <?php include 'includes/navbar.php'; ?>
  <main class="content">
    <h1>アバターの着せ替え</h1>
    <?php if (isset($message)) echo "<p>$message</p>"; ?>

    <div class="customize-grid">
      <div class="left-panel">
        <?= renderAvatarLayers($current, $default_icons) ?>
        <div class="status-summary">
          <p>攻撃力: <?= $total_atk ?> / 防御力: <?= $total_def ?></p>
        </div>
        <div class="preview-detail">
          <?php if ($selected_slot && isset($my_equipments[$selected_slot])): ?>
            <h3><?= ucfirst($selected_slot) ?> 装備</h3>
            <?php foreach ($my_equipments[$selected_slot] as $eq): ?>
              <div class="preview-item">
                <img src="assets/avatars/<?= htmlspecialchars($eq['image_path']) ?>">
                <p><strong><?= htmlspecialchars($eq['name']) ?></strong></p>
                <p>攻: <?= $eq['attack'] ?> / 防: <?= $eq['defense'] ?></p>
              </div>
            <?php endforeach; ?>
          <?php else: ?>
            <p>ここに装備の詳細が表示されます</p>
          <?php endif; ?>
        </div>
      </div>

      <div class="right-panel">
        <?php foreach (['head', 'body', 'weapon', 'shield', 'feet'] as $slot): ?>
          <?php if (isset($my_equipments[$slot])): ?>
            <div class="equip-section">
              <h2><?= $slot_labels[$slot] ?></h2>
              <div class="equip-grid">
                <?php foreach ($my_equipments[$slot] as $eq): ?>
                  <form method="POST" action="?slot=<?= $slot ?>">
                    <input type="hidden" name="slot" value="<?= $slot ?>">
                    <input type="hidden" name="equipment_id" value="<?= $eq['equipment_id'] ?>">
                    <button type="submit" class="equip-thumb">
                      <img src="assets/avatars/<?= htmlspecialchars($eq['image_path']) ?>" alt="<?= htmlspecialchars($eq['name']) ?>">
                    </button>
                  </form>
                <?php endforeach; ?>
              </div>
            </div>
          <?php endif; ?>
        <?php endforeach; ?>
      </div>
    </div>
  </main>
</div>
</body>
</html>
