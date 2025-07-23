<?php
session_start();
require 'includes/db.php';

if (!isset($_SESSION['user'])) {
  header("Location: login.php");
  exit;
}

$user_id = $_SESSION['user']['id'];

// ユーザーごとの勉強時間を取得してランキング化
$stmt = $pdo->query("
  SELECT u.id, u.nickname, u.email, COALESCE(SUM(s.duration_minutes), 0) AS total_minutes
  FROM users u
  LEFT JOIN study_logs s ON u.id = s.user_id
  GROUP BY u.id
  ORDER BY total_minutes DESC
");
$users = $stmt->fetchAll();

$current_page = 'ranking'; 

?>

<link rel="stylesheet" href="styles/style.css">

<div class="container">
<?php include 'includes/navbar.php'; ?>

  <main class="content">

    <h1>ランキング</h1>
  
    <table border="1" cellpadding="10" cellspacing="0">
      <tr>
        <th>順位</th>
        <th>ニックネーム</th>
        <th>レベル</th>
        <th>総勉強時間（分）</th>
      </tr>
      <?php
      $rank = 1;
      foreach ($users as $u):
        $level = floor($u['total_minutes'] / 60) + 1;
        $highlight = $u['id'] == $user_id ? 'style="background: #ffffcc;"' : '';
      ?>
        <tr <?= $highlight ?>>
          <td><?= $rank++ ?></td>
          <td><?= htmlspecialchars($u['nickname'] ?? '未設定') ?></td>
          <td>Lv<?= $level ?></td>
          <td><?= $u['total_minutes'] ?></td>
        </tr>
      <?php endforeach; ?>
    </table>
  </main>
</div>
