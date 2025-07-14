<?php
session_start();
require 'includes/db.php';

if (!isset($_SESSION['user'])) {
  header("Location: login.php");
  exit;
}

$user_id = $_SESSION['user']['id'];

// POSTで装備を変更
if ($_SERVER["REQUEST_METHOD"] === "POST") {
  $slot = $_POST["slot"];
  $equipment_id = (int)$_POST["equipment_id"];

  // 同じslotがあれば更新、なければ挿入
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
  SELECT ue.*, e.name, e.slot
  FROM user_equipments ue
  JOIN equipments e ON ue.equipment_id = e.id
  WHERE ue.user_id = ?
");
$stmt->execute([$user_id]);
$my_equipments = $stmt->fetchAll(PDO::FETCH_GROUP|PDO::FETCH_ASSOC);

// 現在の着用中装備
$stmt = $pdo->prepare("
  SELECT slot, equipment_id, e.name
  FROM user_avatar_equipments uae
  JOIN equipments e ON uae.equipment_id = e.id
  WHERE uae.user_id = ?
");
$stmt->execute([$user_id]);
$current = [];
foreach ($stmt->fetchAll() as $row) {
  $current[$row['slot']] = $row;
}
?>
<style>
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

<!DOCTYPE html>
<html lang="ja">
<head>
  <meta charset="UTF-8">
  <title>アバター着せ替え</title>
  <style>
    body { font-family: sans-serif; padding: 2rem; background: #f4f4f4; }
    h2 { margin-top: 2rem; }
    .section { background: white; padding: 1rem; margin-bottom: 2rem; border-radius: 10px; }
    form { display: inline-block; margin: 0.5rem; }
    button { padding: 0.5rem 1rem; border: none; border-radius: 5px; background: #008cba; color: white; cursor: pointer; }
  </style>
</head>
<body>

<h1>アバターの着せ替え</h1>
<?php if (isset($message)) echo "<p>$message</p>"; ?>

<div class="section">
  <h2>現在の装備</h2>
  <ul>
    <?php foreach (['head', 'body', 'weapon', 'shield', 'feet'] as $slot): ?>
      <li><?= ucfirst($slot) ?>:
        <?= isset($current[$slot]) ? htmlspecialchars($current[$slot]['name']) : 'なし' ?>
      </li>
    <?php endforeach; ?>
  </ul>
</div>

<div class="section">
  <h2>アバターのプレビュー</h2>
  <?= renderAvatarLayers($current) ?>
</div>

<?php foreach (['head', 'body', 'weapon', 'shield', 'feet'] as $slot): ?>
  <?php if (isset($my_equipments[$slot])): ?>
    <div class="section">
      <h2><?= ucfirst($slot) ?> を選ぶ</h2>
      <?php foreach ($my_equipments[$slot] as $eq): ?>
        <form method="POST">
          <input type="hidden" name="slot" value="<?= $slot ?>">
          <input type="hidden" name="equipment_id" value="<?= $eq['equipment_id'] ?>">
          <button type="submit"><?= htmlspecialchars($eq['name']) ?></button>
        </form>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
<?php endforeach; ?>

<p><a href="dashboard.php">← ホームへ戻る</a></p>

</body>
</html>
