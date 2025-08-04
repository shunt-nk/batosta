<?php
session_start();
require 'includes/db.php';

if (!isset($_SESSION['user'])) {
  header("Location: index.php");
  exit;
}

$user_id = $_SESSION['user']['id'];
$category = $_GET['category'] ?? 'weekly';

switch ($category) {
  case 'weekly':
    $stmt = $pdo->query("
      SELECT u.id, u.username, COALESCE(SUM(s.duration_minutes), 0) AS value
      FROM users u
      LEFT JOIN study_logs s ON u.id = s.user_id
      AND s.study_date >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
      GROUP BY u.id
      ORDER BY value DESC
    ");
    $label = '週間勉強時間（分）';
    break;

  case 'level':
    $stmt = $pdo->query("
      SELECT u.id, u.username, COALESCE(a.level, 1) AS value
      FROM users u
      LEFT JOIN avatars_status a ON u.id = a.user_id
      ORDER BY value DESC
    ");
    $label = 'レベル';
    break;

  case 'titles':
    $stmt = $pdo->query("
      SELECT u.id, u.username, COUNT(t.id) AS value
      FROM users u
      LEFT JOIN user_titles t ON u.id = t.user_id
      GROUP BY u.id
      ORDER BY value DESC
    ");
    $label = '称号数';
    break;

  default:
    header("Location: ranking.php?category=weekly");
    exit;
}

$users = $stmt->fetchAll();
$current_page = 'ranking';
?>

<link rel="stylesheet" href="styles/style.css">
<link rel="stylesheet" href="styles/ranking.css">

<div class="container">
  <?php include 'includes/navbar.php'; ?>

  <main class="content">

    <form method="GET" action="ranking.php" class="ranking-filter" style="margin-bottom: 20px;">
      <label for="category">表示部門：</label>
      <select name="category" id="category" onchange="this.form.submit()">
        <option value="weekly" <?= $category === 'weekly' ? 'selected' : '' ?>>週間勉強時間</option>
        <option value="level" <?= $category === 'level' ? 'selected' : '' ?>>レベル</option>
        <option value="titles" <?= $category === 'titles' ? 'selected' : '' ?>>称号数</option>
      </select>
    </form>

    <!-- 上位3人表示 -->
    <div class="top-three">
      <?php for ($i = 1; $i <= 3; $i++): ?>
        <?php if (isset($users[$i - 1])): ?>
          <?php $u = $users[$i - 1]; ?>
          <div class="top-card rank<?= $i ?> <?= $u['id'] == $user_id ? 'highlight' : '' ?>">
            <div class="top-rank">第<?= $i ?>位</div>
            <div class="top-name"><?= htmlspecialchars($u['username'] ?? '未設定') ?></div>
            <div class="top-value">
              <?= $category === 'level' ? 'Lv' . $u['value'] : $u['value'] ?>
            </div>
          </div>
        <?php endif; ?>
      <?php endfor; ?>
    </div>

    <!-- 4位以降 -->
    <table cellpadding="10" cellspacing="0">
      <tr>
        <th class="rank_title">順位</th>
        <th class="name_title">ユーザー名</th>
        <th class="data_taitle"><?= htmlspecialchars($label) ?></th>
      </tr>
      <?php
      $rank = 1;
      foreach ($users as $u):
        if ($rank <= 3) {
          $rank++;
          continue;
        }
        $highlight = $u['id'] == $user_id ? 'style="background: #DB9963;"' : '';
      ?>
        <tr <?= $highlight ?>>
          <td class="rank"><?= $rank ?></td>
          <td class="name"><?= htmlspecialchars($u['username'] ?? '未設定') ?></td>
          <td class="data">
            <?= $category === 'level' ? 'Lv' . $u['value'] : $u['value'] ?>
          </td>
        </tr>
      <?php $rank++; endforeach; ?>
    </table>

    <!-- 自分の順位表示 -->
    <?php
    foreach ($users as $i => $u) {
      if ($u['id'] == $user_id) {
        $my_rank = $i + 1;
        break;
      }
    }
    ?>
    <div class="my-rank-box">
      あなたの順位：<?= $my_rank ?>位
    </div>

  </main>
</div>
