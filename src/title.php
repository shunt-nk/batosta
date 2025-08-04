<?php
session_start();
require 'includes/db.php';

if (!isset($_SESSION['user'])) {
  header("Location: index.php");
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

<link rel="stylesheet" href="styles/style.css">
<link rel="stylesheet" href="styles/title.css">
<script src="js/title.js"></script>
<div class="container">
  <?php include 'includes/navbar.php'; ?>
    <!-- 入手方法モーダル -->
  <div id="methodModal" class="modal-overlay">
    <div class="modal-content">
      <h3>入手方法</h3>
      <p id="methodText"></p>
      <button onclick="closeModal()">閉じる</button>
    </div>
  </div>
  <main class="title-content">
    <div class="title-main">
      <!-- 左：現在の称号、選択中の称号 -->
      <div class="title-left">
        <div class="title-box">
          <div class="title-header">セット中の称号</div>
          <div class="title-current">
            <?php
              $current = array_filter($all_titles, fn($t) => $t['id'] == $equipped_id);
              echo $current ? htmlspecialchars($current[array_key_first($current)]['name']) : 'なし';
            ?>
          </div>
        </div>

        <div class="title-box">
          <div class="title-header">選択中の称号</div>
          <div class="title-selected">
            <?php if (isset($_POST['equip_id'])): ?>
              <?php
                $selected = array_filter($all_titles, fn($t) => $t['id'] == $_POST['equip_id']);
                echo $selected ? htmlspecialchars($selected[array_key_first($selected)]['name']) : '未選択';
              ?>
            <?php else: ?>
              <span class="lock-icon">🔒</span>
            <?php endif; ?>
          </div>
          <div class="title-buttons">
          <?php if (isset($_POST['equip_id'])): ?>
              <?php
                $selected = array_filter($all_titles, fn($t) => $t['id'] == $_POST['equip_id']);
                $selected_title = $selected ? $selected[array_key_first($selected)] : null;
              ?>
              <?php if ($selected_title): ?>
                <button onclick="openModal('<?= htmlspecialchars($selected_title['description'], ENT_QUOTES) ?>')">入手方法</button>
              <?php endif; ?>
            <?php endif; ?>
            <?php if (isset($_POST['equip_id']) && isset($owned[$_POST['equip_id']])): ?>
              <form method="POST">
                <input type="hidden" name="equip_id" value="<?= $_POST['equip_id'] ?>">
                <button class="btn-pink">セットする</button>
              </form>
            <?php endif; ?>
          </div>
        </div>
      </div>

      <!-- 右：称号一覧とタブ -->
      <div class="title-right">
        <div class="title-tabs">
          <a href="?filter=all" class="tab <?= $filter === 'all' ? 'active' : '' ?>">称号一覧</a>
          <a href="?filter=owned" class="tab <?= $filter === 'owned' ? 'active' : '' ?>">勉強</a>
          <a href="?filter=unowned" class="tab <?= $filter === 'unowned' ? 'active' : '' ?>">バトル</a>
        </div>

        <div class="title-grid">
          <?php foreach ($titles as $t): ?>
            <div class="title-card <?= isset($owned[$t['id']]) ? 'owned' : 'locked' ?>">
              <?php if (isset($owned[$t['id']])): ?>
                <?= htmlspecialchars($t['name']) ?>
              <?php else: ?>
                <span class="lock-icon">🔒</span>
              <?php endif; ?>
            </div>
          <?php endforeach; ?>
        </div>
      </div>
    </div>
  </main>
</div>
