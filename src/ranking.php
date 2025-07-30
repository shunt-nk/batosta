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
    // fallback to weekly
    header("Location: ranking.php?category=weekly");
    exit;
}

$users = $stmt->fetchAll();
$current_page = 'ranking';
?>

<link rel="stylesheet" href="styles/style.css">

<div class="container">
  <?php include 'includes/navbar.php'; ?>

  <main class="content">
    <h1>ランキング</h1>

    <!-- 部門切り替え -->
    <form method="GET" action="ranking.php" class="ranking-filter" style="margin-bottom: 20px;">
      <label for="category">表示部門：</label>
      <select name="category" id="category" onchange="this.form.submit()">
        <option value="weekly" <?= $category === 'weekly' ? 'selected' : '' ?>>週間勉強時間</option>
        <option value="level" <?= $category === 'level' ? 'selected' : '' ?>>レベル</option>
        <option value="titles" <?= $category === 'titles' ? 'selected' : '' ?>>称号数</option>
      </select>
    </form>

    <table border="1" cellpadding="10" cellspacing="0">
      <tr>
        <th>順位</th>
        <th>ユーザー名</th>
        <th><?= htmlspecialchars($label) ?></th>
      </tr>
      <?php
      $rank = 1;
      foreach ($users as $u):
        $highlight = $u['id'] == $user_id ? 'style="background: #ffffcc;"' : '';
      ?>
        <tr <?= $highlight ?>>
          <td><?= $rank++ ?></td>
          <td><?= htmlspecialchars($u['username'] ?? '未設定') ?></td>
          <td>
            <?php
              if ($category === 'level') {
                echo 'Lv' . $u['value'];
              } else {
                echo $u['value'];
              }
            ?>
          </td>
        </tr>
      <?php endforeach; ?>
    </table>
  </main>
</div>
