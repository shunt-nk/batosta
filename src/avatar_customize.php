<?php
session_start();
require 'includes/db.php';

// POST: 装備解除処理
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_GET['remove']) && $_POST["slot"]) {
  $slot = $_POST["slot"];
  $stmt = $pdo->prepare("DELETE FROM user_avatar_equipments WHERE user_id = ? AND slot = ?");
  $stmt->execute([$user_id, $slot]);
  header("Location: avatar_customize.php"); // リダイレクトでリロード
  exit;
}


function renderAvatarLayers($current, $default_icons) {
  $html = '<div class="avatar-preview-wrapper">';
  $html .= '<div class="avatar-container">';
  $html .= '<img src="assets/avatars/base.png" class="avatar-layer">';

  foreach (['head',  'weapon', 'shield'] as $slot) {
    if (!empty($current[$slot]['image_path'])) {
      $image_path = htmlspecialchars($current[$slot]['image_path']);
      $html .= '<img src="assets/avatars/' . $image_path . '" class="avatar-layer ' . $slot . '">';
    } else {
      $html .= '<img src="assets/icons/' . $default_icons[$slot] . '" class="slot-icon" data-slot="' . $slot . '">';
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

$current_page = 'customize'; 

?>

<!DOCTYPE html>
<html lang="ja">
<head>
  <meta charset="UTF-8">
  <title>アバター着せ替え</title>
  <link rel="stylesheet" href="styles/avatar_customize.css">
</head>
<script>
function showEquipModal(equipment) {
  document.getElementById('modal-name').innerText = equipment.name;
  document.getElementById('modal-image').src = 'assets/avatars/' + equipment.image_path;
  document.getElementById('modal-attack').innerText = '攻撃力: ' + equipment.attack;
  document.getElementById('modal-defense').innerText = '防御力: ' + equipment.defense;
  document.getElementById('modal-slot').value = equipment.slot;
  document.getElementById('modal-equipment-id').value = equipment.equipment_id;
  document.getElementById('equipModal').style.display = 'flex';
}

function closeModal() {
  document.getElementById('equipModal').style.display = 'none';
}
</script>

<body>
<div class="container">
  <?php include 'includes/navbar.php'; ?>
  <!-- 装備詳細モーダル -->
  <div id="equipModal" class="modal" style="display:none;">
    <div class="modal-content">
      <span class="close-btn" onclick="closeModal()">&times;</span>
      <h2 id="modal-name">装備名</h2>
      <img id="modal-image" src="" alt="" style="width:80px; height:80px; object-fit:contain;">
      <p id="modal-attack">攻撃力: -</p>
      <p id="modal-defense">防御力: -</p>
      <form method="POST" action="avatar_customize.php">
        <input type="hidden" name="slot" id="modal-slot">
        <input type="hidden" name="equipment_id" id="modal-equipment-id">
        <button type="submit" class="equip-confirm-btn">装備する</button>
      </form>
    </div>
  </div>

  <main class="content">
    <h1>アバターの着せ替え</h1>
    <?php if (isset($message)) echo "<p>$message</p>"; ?>

    <div class="customize-grid">
      <div class="left-panel">
        <div class="current-equips">
          <?php foreach (['head', 'body', 'weapon', 'shield', 'feet'] as $slot): ?>
            <?php if (!empty($current[$slot]['image_path'])): ?>
              <form method="POST" action="?remove=1">
                <input type="hidden" name="slot" value="<?= $slot ?>">
                <button type="submit" class="current-equip-thumb" title="<?= $slot_labels[$slot] ?>">
                  <img src="assets/avatars/<?= htmlspecialchars($current[$slot]['image_path']) ?>" alt="<?= htmlspecialchars($current[$slot]['name']) ?>">
                  <span class="remove-btn">×</span>
                </button>
              </form>
              <?php endif; ?>
              <?php endforeach; ?>
            </div>
            <?= renderAvatarLayers($current, $default_icons) ?>
            <div class="status-summary">
              <p>攻撃力: <?= $total_atk ?> / 防御力: <?= $total_def ?></p>
            </div>
          </div>

      <div class="right-panel">
        <?php foreach (['head', 'body', 'weapon', 'shield', 'feet'] as $slot): ?>
          <?php if (isset($my_equipments[$slot])): ?>
            <div class="equip-section">
              <h2><?= $slot_labels[$slot] ?></h2>
              <div class="equip-grid">
                <?php foreach ($my_equipments[$slot] as $eq): ?>
                    <button type="button" class="equip-thumb" onclick='showEquipModal(<?= json_encode([
                      'name' => $eq['name'],
                      'image_path' => $eq['image_path'],
                      'attack' => $eq['attack'],
                      'defense' => $eq['defense'],
                      'equipment_id' => $eq['equipment_id'],
                      'slot' => $eq['slot']
                    ]) ?>)'>
                      <img src="assets/avatars/<?= htmlspecialchars($eq['image_path']) ?>" alt="<?= htmlspecialchars($eq['name']) ?>">
                    </button>

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
