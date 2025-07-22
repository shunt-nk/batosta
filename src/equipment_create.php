<?php
session_start();
require 'includes/db.php';

if (!isset($_SESSION['user'])) {
  header("Location: login.php");
  exit;
}

$user_id = $_SESSION['user']['id'];

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
    foreach ($reqs as $req) {
      $update = $pdo->prepare("UPDATE user_materials SET quantity = quantity - ? WHERE user_id = ? AND material_id = ?");
      $update->execute([$req['quantity'], $user_id, $req['material_id']]);
    }

    $insert = $pdo->prepare("INSERT INTO user_equipments (user_id, equipment_id) VALUES (?, ?)");
    $insert->execute([$user_id, $eid]);

    $message = "✅ 装備を作成しました！";
  } else {
    $message = "❌ 素材が足りません";
  }
}

// 素材一覧
$stmt = $pdo->query("SELECT * FROM materials");
$materials = $stmt->fetchAll(PDO::FETCH_ASSOC);
$material_map = [];
foreach ($materials as $m) {
  $material_map[$m['name']] = $m['id'];
}

// 装備一覧と必要素材
$stmt = $pdo->query("
  SELECT e.*, GROUP_CONCAT(m.name, ':', er.quantity) AS materials
  FROM equipments e
  LEFT JOIN equipment_requirements er ON e.id = er.equipment_id
  LEFT JOIN materials m ON er.material_id = m.id
  GROUP BY e.id
");
$equipments = $stmt->fetchAll();

// ユーザー素材所持
$stmt = $pdo->prepare("SELECT material_id, quantity FROM user_materials WHERE user_id = ?");
$stmt->execute([$user_id]);
$user_materials = [];
foreach ($stmt->fetchAll() as $row) {
  $user_materials[$row['material_id']] = $row['quantity'];
}

$current_page = 'create'; 

?>

<!DOCTYPE html>
<html lang="ja">
<head>
  <meta charset="UTF-8">
  <title>装備作成</title>
  <style>
  body {
    margin: 0;
    font-family: sans-serif;
    background: #5D73A9;
    overflow: hidden;
  }
  
  .container {
    display: flex;
    width: 1180px;
    height: 840px;
    overflow: hidden;
  }
  
  
  .content {
    flex: 1;
    padding: 1.5rem;
    display: flex;
    flex-direction: column;
    gap: 1.5rem;
    overflow-y: auto;
  }
    h2{
      color: #fff;
    }

    .item { 
      background: white; 
      padding: 1rem; 
      margin-bottom: 1rem; 
      border-radius: 8px; 
    }
    button { 
      padding: 0.5rem 1rem; 
      border: none; 
      background: orange; 
      color: white;
       border-radius: 5px; 
       cursor: pointer; 
      }
    button:disabled { 
      background: gray; 
      cursor: not-allowed; 
    }

    .equipment-grid {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
      gap: 1.5rem;
      max-width: 1000px;
      margin: 0 auto; /* 中央寄せ */
    }

    .item {
      background: white;
      padding: 1rem;
      border-radius: 8px;
      box-shadow: 0 2px 4px rgba(0,0,0,0.1);
      display: flex;
      flex-direction: column;
      justify-content: space-between;
    }

</style>
</head>
<body>

<div class="container">
<?php include 'includes/navbar.php'; ?>

  <main class="content">

    
    <?php if (isset($message)) echo "<p>$message</p>"; ?>
    <div class="equipment-grid">
      <?php foreach ($equipments as $eq): ?>
        <div class="item">
          <strong><?= htmlspecialchars($eq['name']) ?>（<?= $eq['slot'] ?>）</strong><br>
          必要素材：
          <ul>
            <?php
            $requirements = explode(",", $eq['materials']);
            $canMake = true;
      
            foreach ($requirements as $r) {
              list($mat, $qty) = explode(":", $r);
              $qty = (int)$qty;
              $mat_id = $material_map[$mat] ?? null;
              $have = $mat_id && isset($user_materials[$mat_id]) ? $user_materials[$mat_id] : 0;
      
              echo "<li>{$mat} × {$qty}（所持：{$have}）</li>";
      
              if ($have < $qty) {
                $canMake = false;
              }
            }
            ?>
          </ul>
          <form method="POST">
            <input type="hidden" name="equipment_id" value="<?= $eq['id'] ?>">
            <button type="submit" <?= $canMake ? '' : 'disabled' ?>>作る</button>
          </form>
        </div>
      <?php endforeach; ?>
    </div>
  </main>
</div>

</body>
</html>
