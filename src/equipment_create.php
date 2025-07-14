<?php
session_start();
require 'includes/db.php';

if (!isset($_SESSION['user'])) {
  header("Location: login.php");
  exit;
}

$user_id = $_SESSION['user']['id'];

// 作成処理（POST）
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['equipment_id'])) {
  $eid = (int)$_POST['equipment_id'];

  // 必要素材を確認
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
    // 素材を消費
    foreach ($reqs as $req) {
      $update = $pdo->prepare("UPDATE user_materials SET quantity = quantity - ? WHERE user_id = ? AND material_id = ?");
      $update->execute([$req['quantity'], $user_id, $req['material_id']]);
    }

    // 装備を付与
    $insert = $pdo->prepare("INSERT INTO user_equipments (user_id, equipment_id) VALUES (?, ?)");
    $insert->execute([$user_id, $eid]);

    $message = "✅ 装備を作成しました！";
  } else {
    $message = "❌ 素材が足りません";
  }
}

// 装備一覧と必要素材を取得
$stmt = $pdo->query("
  SELECT e.*, GROUP_CONCAT(m.name, ':', er.quantity) AS materials
  FROM equipments e
  LEFT JOIN equipment_requirements er ON e.id = er.equipment_id
  LEFT JOIN materials m ON er.material_id = m.id
  GROUP BY e.id
");
$equipments = $stmt->fetchAll();

// ユーザーの素材所持
$stmt = $pdo->prepare("SELECT material_id, quantity FROM user_materials WHERE user_id = ?");
$stmt->execute([$user_id]);
$user_materials = [];
foreach ($stmt->fetchAll() as $row) {
  $user_materials[$row['material_id']] = $row['quantity'];
}

foreach ($requirements as $r) {
  list($mat, $qty) = explode(":", $r);
  // 所持数を表示
  $mat_id = array_search($mat, array_column($materials, 'name')); // 逆引き
  $have = $user_materials[$mat_id] ?? 0;
  echo "<li>$mat × $qty（所持：$have）</li>";
}

?>

<!DOCTYPE html>
<html lang="ja">
<head>
  <meta charset="UTF-8">
  <title>装備作成</title>
  <style>
    body { font-family: sans-serif; padding: 2rem; background: #f4f4f4; }
    .item { background: white; padding: 1rem; margin-bottom: 1rem; border-radius: 8px; }
    form { display: inline; }
    button { padding: 0.5rem 1rem; border: none; background: orange; color: white; border-radius: 5px; cursor: pointer; }
    button:disabled { background: gray; cursor: not-allowed; }
  </style>
</head>
<body>

<h2>装備を作る</h2>

<?php if (isset($message)) echo "<p>$message</p>"; ?>

<?php foreach ($equipments as $eq): ?>
  <div class="item">
    <strong><?= htmlspecialchars($eq['name']) ?>（<?= $eq['slot'] ?>）</strong><br>
    必要素材：
    <ul>
      <?php
      $canMake = true;
      $requirements = explode(",", $eq['materials']);
      foreach ($requirements as $r) {
        list($mat, $qty) = explode(":", $r);
        echo "<li>$mat × $qty</li>";
      }
      ?>
    </ul>
    <form method="POST">
      <input type="hidden" name="equipment_id" value="<?= $eq['id'] ?>">
      <button type="submit">作る</button>
    </form>
  </div>
<?php endforeach; ?>

<p><a href="dashboard.php">← ホームに戻る</a></p>

</body>
</html>
