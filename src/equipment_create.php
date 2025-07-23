<?php
session_start();
require 'includes/db.php';

if (!isset($_SESSION['user'])) {
  header("Location: login.php");
  exit;
}

$user_id = $_SESSION['user']['id'];

// ユーザーが持っている装備一覧を取得
$stmt = $pdo->prepare("SELECT equipment_id FROM user_equipments WHERE user_id = ?");
$stmt->execute([$user_id]);
$owned_equipment_ids = array_column($stmt->fetchAll(), 'equipment_id');

$slot_labels = [
  'head' => '頭防具',
  'body' => '体防具',
  'weapon' => '武器',
  'shield' => '盾',
  'feet' => '足防具'
];

// 作成処理（POST）
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['equipment_id'])) {
  $eid = (int)$_POST['equipment_id'];

  // すでに所持しているかチェック
  if (in_array($eid, $owned_equipment_ids)) {
    $message = "❌ すでに作成済みの装備です";
  } else {
    // 素材チェック
    $stmt = $pdo->prepare("
      SELECT er.material_id, er.quantity, um.quantity AS owned
      FROM equipment_requirements er
      LEFT JOIN user_materials um ON er.material_id = um.material_id AND um.user_id = ?
      WHERE er.equipment_id = ?
    ");
    $stmt->execute([$user_id, $eid]);
    $reqs = $stmt->fetchAll();

    $canCraft = true;
    foreach ($reqs as $req) {
      if ((int)$req['owned'] < (int)$req['quantity']) {
        $canCraft = false;
        break;
      }
    }

    if ($canCraft) {
      // 素材消費
      foreach ($reqs as $req) {
        $update = $pdo->prepare("UPDATE user_materials SET quantity = quantity - ? WHERE user_id = ? AND material_id = ?");
        $update->execute([$req['quantity'], $user_id, $req['material_id']]);
      }

      // 装備登録
      $insert = $pdo->prepare("INSERT INTO user_equipments (user_id, equipment_id) VALUES (?, ?)");
      $insert->execute([$user_id, $eid]);

      $message = "✅ 装備を作成しました！";
      $owned_equipment_ids[] = $eid; // 表示用に追加
    } else {
      $message = "❌ 素材が足りません";
    }
  }
}

$slot = $_GET['slot'] ?? '';
$sort = $_GET['sort'] ?? '';

$sql = "
  SELECT e.*, GROUP_CONCAT(m.name, ':', er.quantity) AS materials
  FROM equipments e
  LEFT JOIN equipment_requirements er ON e.id = er.equipment_id
  LEFT JOIN materials m ON er.material_id = m.id
";

// WHERE句追加
$conditions = [];
$params = [];

if ($slot) {
  $conditions[] = "e.slot = ?";
  $params[] = $slot;
}

if ($conditions) {
  $sql .= " WHERE " . implode(" AND ", $conditions);
}

$sql .= " GROUP BY e.id ";

// 並び替え
if ($sort === 'attack') {
  $sql .= " ORDER BY e.attack DESC";
} elseif ($sort === 'defense') {
  $sql .= " ORDER BY e.defense DESC";
}

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$equipments = $stmt->fetchAll();

$stmt = $pdo->query("SELECT * FROM materials");
$materials = $stmt->fetchAll(PDO::FETCH_ASSOC);
$material_map = [];
foreach ($materials as $m) {
  $material_map[$m['name']] = $m['id'];
}
$stmt = $pdo->prepare("SELECT material_id, quantity FROM user_materials WHERE user_id = ?");
$stmt->execute([$user_id]);
$user_materials = [];
foreach ($stmt->fetchAll() as $row) {
  $user_materials[$row['material_id']] = $row['quantity'];
}

?>

<!DOCTYPE html>
<html lang="ja">
<head>
  <meta charset="UTF-8">
  <title>装備作成</title>
  <link rel="stylesheet" href="styles/style.css">
  <link rel="stylesheet" href="styles/equipment_create.css">
</head>
<body>
<div class="container">
<?php include 'includes/navbar.php'; ?>
  <main class="content">
    <?php if (isset($message)) echo "<p>$message</p>"; ?>

    <!-- フィルターエリア（次で実装） -->
    <form method="GET" class="filter-form">
      <div class="filter-section">
        <label>カテゴリ：</label>
        <select name="slot">
          <option value="">すべて</option>
          <?php foreach ($slot_labels as $key => $label): ?>
            <option value="<?= $key ?>" <?= ($_GET['slot'] ?? '') === $key ? 'selected' : '' ?>>
              <?= $label ?>
            </option>
          <?php endforeach; ?>
        </select>

        <label>並び替え：</label>
        <select name="sort">
          <option value="">指定なし</option>
          <option value="attack" <?= ($_GET['sort'] ?? '') === 'attack' ? 'selected' : '' ?>>攻撃力順</option>
          <option value="defense" <?= ($_GET['sort'] ?? '') === 'defense' ? 'selected' : '' ?>>防御力順</option>
        </select>

        <button type="submit">適用</button>
      </div>
    </form>
    <div class="equipment-grid">
      <?php foreach ($equipments as $eq): ?>
        <div class="item">
          <div class="thumb">
            <img src="assets/avatars/<?= htmlspecialchars($eq['image_path']) ?>" alt="<?= htmlspecialchars($eq['name']) ?>">
          </div>
          <div class="info">
            <div class="name">
              <?= htmlspecialchars($eq['name']) ?>
            </div>
            <div class="slot">
              <?= $slot_labels[$eq['slot']] ?? $eq['slot'] ?>
            </div>
            <ul class="materials">
              <?php
              $requirements = explode(",", $eq['materials']);
              $canMake = true;
              foreach ($requirements as $r) {
                list($mat, $qty) = explode(":", $r);
                $qty = (int)$qty;
                $mat_id = $material_map[$mat] ?? null;
                $have = $mat_id && isset($user_materials[$mat_id]) ? $user_materials[$mat_id] : 0;
                if ($have < $qty) $canMake = false;
                echo "<li>{$mat} × {$qty}（所持：{$have}）</li>";
              }
              ?>
            </ul>
            <form method="POST">
              <div class="create_btn">
                <input type="hidden" name="equipment_id" value="<?= $eq['id'] ?>">
                <?php if (in_array($eq['id'], $owned_equipment_ids)): ?>
                  <button type="button" disabled class="owned">作成済み</button>
                <?php else: ?>
                  <button type="submit" <?= $canMake ? '' : 'disabled' ?>>作る</button>
                <?php endif; ?>
              </div>
            </form>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
  </main>
</div>
</body>
</html>
