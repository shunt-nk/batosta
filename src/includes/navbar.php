<?php
if (!isset($_SESSION['user'])) {
  header("Location: index.php");
  exit;
}
$user = $_SESSION['user'];
$current_page = $current_page ?? '';
?>
<style>
  /* サイドバー全体 */
  .sidebar {
    width: 220px;
    background: #2C3E75;
    color: #fff;
    display: flex;
    flex-direction: column;
    font-family: sans-serif;
  }

  /* ナビ項目：アイコン列(固定28px) + ラベル列(自動) の2カラムで整列 */
  .nav-item {
    display: grid;
    grid-template-columns: 28px 1fr; /* ←この固定列が“縦一直線”の柱 */
    align-items: center;
    text-align: center;
    column-gap: 30px;
    padding: 2.1rem 2rem ;
    color: #fff;
    font-weight: bold;
    text-decoration: none;
    border-bottom: 1px solid #516C8D;
    background: #2C3E75;
    transition: background .25s ease;
  }
  .nav-item:hover { background: #3e5088; }
  .nav-item.active { background: #DB9963; }

  /* アイコンを柱の中央に揃え、サイズを統一 */
  .nav-item .icons {
    width: 35px;
    height: 35px;
    object-fit: contain;
    justify-self: center; /* ←アイコン列の中央に配置 */
    display: block;
  }

  /* ラベルは1行に収める（長い時は…に） */
  .nav-item .label {
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
    line-height: 1.1;
  }
</style>

<nav class="sidebar">
  <a href="profile.php" class="nav-item <?= $current_page === 'profile' ? 'active' : '' ?>">
    <img src="assets/icons/user.png" alt="User Icon" class="icons">
    <span class="label"><?php
      // 59行目の出力をこれに
      $navUsername = $_SESSION['user']['name'] ?? $_SESSION['user']['username'] ?? '';
      echo $navUsername !== ''
        ? htmlspecialchars($navUsername, ENT_QUOTES, 'UTF-8')
        : 'ユーザー';
      ?>
    </span>
  </a>

  <a href="dashboard.php" class="nav-item <?= $current_page === 'home' ? 'active' : '' ?>">
    <img src="assets/icons/home.png" alt="Home Icon" class="icons">
    <span class="label">ホーム</span>
  </a>

  <a href="task_register.php" class="nav-item <?= $current_page === 'task' ? 'active' : '' ?>">
    <img src="assets/icons/task.png" alt="Task Icon" class="icons">
    <span class="label">宿題</span>
  </a>

  <a href="avatar_customize.php" class="nav-item <?= $current_page === 'customize' ? 'active' : '' ?>">
    <img src="assets/icons/customize.png" alt="Customize Icon" class="icons">
    <span class="label">着せ替え</span>
  </a>

  <a href="equipment_create.php" class="nav-item <?= $current_page === 'create' ? 'active' : '' ?>">
    <img src="assets/icons/create.png" alt="Create Icon" class="icons">
    <span class="label">装備作成</span>
  </a>

  <a href="friend.php" class="nav-item <?= $current_page === 'friend' ? 'active' : '' ?>">
    <img src="assets/icons/friend.png" alt="Friend Icon" class="icons">
    <span class="label">フレンド</span>
  </a>

  <a href="ranking.php" class="nav-item <?= $current_page === 'ranking' ? 'active' : '' ?>">
    <img src="assets/icons/ranking.png" alt="Ranking Icon" class="icons">
    <span class="label">ランキング</span>
  </a>

  <a href="title.php" class="nav-item <?= $current_page === 'title' ? 'active' : '' ?>">
    <img src="assets/icons/title.png" alt="Title Icon" class="icons">
    <span class="label">称号</span>
  </a>
</nav>
