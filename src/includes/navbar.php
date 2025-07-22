<?php
if (!isset($_SESSION['user'])) {
  header("Location: login.php");
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
  color: white;
  display: flex;
  flex-direction: column;
  font-family: sans-serif;
}


/* 通常のナビメニュー */
.nav-item {
  text-align: left;
  display: flex;
  align-items: center;
  justify-content: space-around;
  gap: 0.5rem;
  padding: 1.5rem 1rem;
  color: white;
  font-weight: bold;
  text-decoration: none;
  border-bottom: 1px solid #444;
  background: #2C3E75;
  transition: background 0.5s;
}
.nav-item:hover {
  background: #3e5088;
}

/* アクティブ状態 */
.nav-item.active {
  background: #DB9963;
}

</style>
<nav class="sidebar">
  <a href="profile.php" class="nav-item <?= $current_page === 'profile' ? 'active' : '' ?>">
    <img src="assets/icons/user.png" alt="User Icon" class="icons">
    <span><?= htmlspecialchars($user['username']) ?> </span>
  </a>

  <a href="dashboard.php" class="nav-item <?= $current_page === 'home' ? 'active' : '' ?>">
    <img src="assets/icons/home.png" alt="Home Icon" class="icons">ホーム</a>
  <a href="task_register.php" class="nav-item <?= $current_page === 'task' ? 'active' : '' ?>">
    <img src="assets/icons/task.png" alt="Task Icon" class="icons">宿題</a>
  <a href="avatar_customize.php" class="nav-item <?= $current_page === 'customize' ? 'active' : '' ?>">
    <img src="assets/icons/customize.png" alt="Customize Icon" class="icons">着せ替え</a>
  <a href="equipment_create.php" class="nav-item <?= $current_page === 'create' ? 'active' : '' ?>">
    <img src="assets/icons/create.png" alt="Create Icon" class="icons">装備作成</a>
  <a href="friend.php" class="nav-item <?= $current_page === 'friend' ? 'active' : '' ?>">
    <img src="assets/icons/friend.png" alt="Friend Icon" class="icons">フレンド</a>
  <a href="ranking.php" class="nav-item <?= $current_page === 'ranking' ? 'active' : '' ?>">
    <img src="assets/icons/ranking.png" alt="Ranking Icon" class="icons">ランキング</a>
  <a href="title.php" class="nav-item <?= $current_page === 'title' ? 'active' : '' ?>">
    <img src="assets/icons/title.png" alt="Title Icon" class="icons">称号</a>
</nav>

