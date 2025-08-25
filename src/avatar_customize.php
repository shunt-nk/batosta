<?php
// avatar_customize.php
declare(strict_types=1);
session_start();
require 'includes/db.php';
require 'includes/functions.php';

if (!isset($_SESSION['user'])) { header("Location: index.php"); exit; }
$user_id = (int)$_SESSION['user']['id'];

$selected_slot = $_GET['slot'] ?? 'weapon'; // 初期タブ

// --- ステータス（合算）
$status = getAvatarStatusWithEquip($pdo, $user_id);

// --- 装備解除
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_GET['remove']) && !empty($_POST["slot"])) {
  $slot = $_POST["slot"];
  $stmt = $pdo->prepare("DELETE FROM user_avatar_equipments WHERE user_id = ? AND slot = ?");
  $stmt->execute([$user_id, $slot]);
  header("Location: avatar_customize.php?slot=".urlencode($slot));
  exit;
}

// --- 装備変更
if ($_SERVER["REQUEST_METHOD"] === "POST" && empty($_GET['remove'])) {
  $slot = $_POST["slot"] ?? '';
  $equipment_id = (int)($_POST["equipment_id"] ?? 0);
  if ($slot && $equipment_id) {
    $stmt = $pdo->prepare("SELECT 1 FROM user_avatar_equipments WHERE user_id=? AND slot=? LIMIT 1");
    $stmt->execute([$user_id, $slot]);
    if ($stmt->fetchColumn()) {
      $update = $pdo->prepare("UPDATE user_avatar_equipments SET equipment_id=? WHERE user_id=? AND slot=?");
      $update->execute([$equipment_id, $user_id, $slot]);
    } else {
      $insert = $pdo->prepare("INSERT INTO user_avatar_equipments (user_id, slot, equipment_id) VALUES (?, ?, ?)");
      $insert->execute([$user_id, $slot, $equipment_id]);
    }
    $selected_slot = $slot;
  }
}

// --- ベースアバター（必要スロットをまとめて取得）
$baseParts = fetchSelectedPartsBySlots($pdo, $user_id, [
  'body','hair','eyes','mouth','hand_base','hand_weapon'
]);

// --- 現在の装備（スロット → 行）
$stmt = $pdo->prepare("
  SELECT uae.slot AS slot, uae.equipment_id AS equipment_id, e.name, e.image_path, e.attack, e.defense
  FROM user_avatar_equipments uae
  JOIN equipments e ON uae.equipment_id = e.id
  WHERE uae.user_id = ?
");
$stmt->execute([$user_id]);
$current = [];
foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $eq) {
  $current[$eq['slot']] = $eq;
}

// --- プレビュー用にパス配列を作成（slot => image_path）
$currentEquipPaths = [];
foreach ($current as $slot => $row) {
  if (!empty($row['image_path'])) {
    $currentEquipPaths[$slot] = $row['image_path'];
  }
}

// --- 所持装備（slotごとに配列化）
$stmt = $pdo->prepare("
  SELECT ue.*, e.name, e.slot, e.image_path, e.attack, e.defense
  FROM user_equipments ue
  JOIN equipments e ON ue.equipment_id = e.id
  WHERE ue.user_id = ?
");
$stmt->execute([$user_id]);
$my_equipments = [];
foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $eq) {
  $my_equipments[$eq['slot']][] = $eq;
}

// 表示ラベル
$slot_labels = ['weapon'=>'武器','shield'=>'盾','head'=>'頭防具','body'=>'体防具','feet'=>'足防具'];

$current_page = 'customize';
?>
?>
<!doctype html>
<html lang="ja">
<head>
  <meta charset="utf-8">
  <title>アバター着せ替え</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link rel="stylesheet" href="styles/style.css">
  <link rel="stylesheet" href="styles/avatar_customize.css">
</head>
<body>
<div class="container">
  <?php include 'includes/navbar.php'; ?>

  <!-- モーダル -->
  <div id="equipModal" class="modal" style="display:none;">
    <div class="modal-content">
      <span class="close-btn" onclick="closeModal()">&times;</span>
      <h2 id="modal-name">装備名</h2>
      <img id="modal-image" src="" alt="" style="width:80px;height:80px;object-fit:contain;">
      <p id="modal-attack">攻撃力: -</p>
      <p id="modal-defense">防御力: -</p>
      <form method="POST">
        <input type="hidden" name="slot" id="modal-slot">
        <input type="hidden" name="equipment_id" id="modal-equipment-id">
        <button type="submit" class="equip-confirm-btn">装備する</button>
      </form>
    </div>
  </div>

  <main class="content">
    <!-- カテゴリタブ -->
    <div class="slot-tabs">
      <?php foreach (['weapon','shield','head','body','feet'] as $s):
        $active = ($selected_slot === $s) ? 'active' : ''; ?>
        <a class="slot-tab <?= $active ?>" href="?slot=<?= urlencode($s) ?>" title="<?= $slot_labels[$s] ?>">
          <img src="assets/icons/slot_<?= $s ?>.png" alt="<?= $slot_labels[$s] ?>">
        </a>
      <?php endforeach; ?>
    </div>

    <div class="customize-layout">
      <!-- 中央：アバター -->
      <section class="avatar-panel">
        <div class="avatar-preview-wrapper">
          <div class="avatar-container">
          <?= renderAvatarFull($baseParts, $currentEquipPaths) ?>
          </div>
        </div>

        <div class="status-summary">
          <p>攻撃力: <?= (int)$status['attack'] ?> / 防御力: <?= (int)$status['defense'] ?></p>
        </div>

        <!-- 現在装備（解除ボタン付き） -->
        <div class="current-equips">
          <?php foreach (['weapon','shield','head','body','feet'] as $s): ?>
            <?php if (!empty($current[$s]['image_path'])): ?>
              <form method="POST" action="?remove=1&slot=<?= urlencode($s) ?>">
                <input type="hidden" name="slot" value="<?= $s ?>">
                <button type="submit" class="current-equip-thumb" title="<?= $slot_labels[$s] ?>">
                  <img src="assets/avatars/<?= htmlspecialchars($current[$s]['image_path']) ?>" alt="<?= htmlspecialchars($current[$s]['name']) ?>">
                  <span class="remove-btn">×</span>
                </button>
              </form>
            <?php else: ?>
              <div class="current-equip-thumb empty" title="<?= $slot_labels[$s] ?>"></div>
            <?php endif; ?>
          <?php endforeach; ?>
        </div>
      </section>

      <!-- 右：プレビュー＋所持品 -->
      <aside class="right-panel">
        <div class="detail-card">
          <?php
            $first = $current[$selected_slot] ?? (($my_equipments[$selected_slot][0] ?? null));
            if ($first):
          ?>
            <div class="detail-thumb">
              <img src="assets/avatars/<?= htmlspecialchars($first['image_path']) ?>" alt="">
            </div>
            <div class="detail-meta">
              <h2><?= htmlspecialchars($first['name']) ?></h2>
              <div>攻撃力　<?= (int)($first['attack'] ?? 0) ?></div>
              <div>防御力　<?= (int)($first['defense'] ?? 0) ?></div>
            </div>
          <?php else: ?>
            <div class="detail-empty">このカテゴリの装備は未所持です</div>
          <?php endif; ?>
        </div>

        <h3 class="grid-title"><?= $slot_labels[$selected_slot] ?> 一覧</h3>
        <div class="equip-grid">
          <?php foreach ($my_equipments[$selected_slot] ?? [] as $eq): ?>
            <button type="button" class="equip-thumb"
              onclick='showEquipModal(<?= json_encode([
                "name"=>$eq["name"], "image_path"=>$eq["image_path"],
                "attack"=>(int)$eq["attack"], "defense"=>(int)$eq["defense"],
                "equipment_id"=>(int)$eq["equipment_id"], "slot"=>$eq["slot"]
              ], JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES) ?>)'>
              <img src="assets/avatars/<?= htmlspecialchars($eq['image_path']) ?>" alt="<?= htmlspecialchars($eq['name']) ?>">
            </button>
          <?php endforeach; ?>
        </div>
      </aside>
    </div>
  </main>
</div>

<script>
function showEquipModal(data){
  document.getElementById('modal-name').textContent = data.name;
  document.getElementById('modal-image').src = 'assets/avatars/' + data.image_path;
  document.getElementById('modal-attack').textContent = '攻撃力: ' + (data.attack||0);
  document.getElementById('modal-defense').textContent = '防御力: ' + (data.defense||0);
  document.getElementById('modal-slot').value = data.slot;
  document.getElementById('modal-equipment-id').value = data.equipment_id;
  document.getElementById('equipModal').style.display = 'flex';
}
function closeModal(){ document.getElementById('equipModal').style.display = 'none'; }
</script>
</body>
</html>
