<?php
session_start();
require 'includes/db.php';

if (!isset($_SESSION['user'])) {
  header("Location: login.php");
  exit;
}

$user_id = $_SESSION['user']['id'];
$filter = $_GET['filter'] ?? 'all';

// すべての称号
$all_titles_stmt = $pdo->query("SELECT * FROM titles");
$all_titles = $all_titles_stmt->fetchAll(PDO::FETCH_ASSOC);

// ユーザーが獲得した称号一覧
$owned_stmt = $pdo->prepare("SELECT title_id, equipped FROM user_titles WHERE user_id = ?");
$owned_stmt->execute([$user_id]);
$owned = $owned_stmt->fetchAll(PDO::FETCH_KEY_PAIR); // [title_id => equipped]

// 現在装備中の称号を特定
$equipped_id = array_search(1, $owned); // equipped = true

// フィルター処理
$titles = array_filter($all_titles, function ($t) use ($filter, $owned) {
  if ($filter === 'owned') return isset($owned[$t['id']]);
  if ($filter === 'unowned') return !isset($owned[$t['id']]);
  return true;
});

// 称号変更処理（POST）
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['equip_id'])) {
  $equip_id = (int)$_POST['equip_id'];
  if (isset($owned[$equip_id])) {
    // 一旦すべて外す
    $pdo->prepare("UPDATE user_titles SET equipped = FALSE WHERE user_id = ?")->execute([$user_id]);
    // 対象を装備
    $pdo->prepare("UPDATE user_titles SET equipped = TRUE WHERE user_id = ? AND title_id = ?")
        ->execute([$user_id, $equip_id]);
    header("Location: title.php?filter=$filter");
    exit;
  }
}

$current_page = 'title'; 

?>

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

</style>

<div class="container">
<?php include 'includes/navbar.php'; ?>
<main class="content">


  <!-- フィルター -->
  <div>
    <a href="?filter=all">すべて</a> |
    <a href="?filter=owned">獲得済み</a> |
    <a href="?filter=unowned">未獲得</a>
  </div>

  <div style="display: flex; margin-top: 2rem;">
    <!-- 左側：装備中・選択中 -->
    <div style="width: 40%; padding-right: 2rem;">
      <h2>現在の称号</h2>
      <p>
        <?php
          $current = array_filter($all_titles, fn($t) => $t['id'] == $equipped_id);
          echo $current ? htmlspecialchars($current[array_key_first($current)]['name']) : '未設定';
        ?>
      </p>
    </div>

    <!-- 右側：称号一覧 -->
    <div style="width: 60%;">
      <h2>称号一覧</h2>
      <div style="display: flex; flex-wrap: wrap; gap: 1rem;">
        <?php foreach ($titles as $t): ?>
          <?php
            $owned_flag = isset($owned[$t['id']]);
            $is_equipped = $owned_flag && $owned[$t['id']];
          ?>
          <form method="POST" style="border: 1px solid #ccc; padding: 1rem; width: 45%; background: <?= $owned_flag ? '#fff' : '#eee' ?>;">
            <strong><?= htmlspecialchars($t['name']) ?></strong>
            <p style="font-size: 0.9rem;"><?= htmlspecialchars($t['description']) ?></p>
            <?php if ($owned_flag): ?>
              <?php if (!$is_equipped): ?>
                <input type="hidden" name="equip_id" value="<?= $t['id'] ?>">
                <button type="submit">この称号を装備</button>
              <?php else: ?>
                <span style="color: green;">✔ 装備中</span>
              <?php endif; ?>
            <?php else: ?>
              <span style="color: gray;">未獲得</span>
            <?php endif; ?>
          </form>
        <?php endforeach; ?>
      </div>
    </div>
  </div>
</main>
</div>
