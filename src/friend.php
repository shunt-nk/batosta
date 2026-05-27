<?php
// src/friend.php
declare(strict_types=1);
require_once 'includes/session.php';
require 'includes/db.php';
require 'includes/functions.php'; // loadAvatarStacks, renderAvatarFull, getAvatarStatusWithEquip

if (!isset($_SESSION['user'])) { header('Location: index.php'); exit; }
$me = (int)$_SESSION['user']['id'];

$activeTab = ($_GET['tab'] ?? 'all') === 'requests' ? 'requests' : 'all';

/* ========================
   1) ベース取得
   ======================== */
// 全ユーザー（自分以外）
$users = $pdo->query("
  SELECT u.id, u.username, u.profile_icon_url, u.presence
  FROM users u
  WHERE u.id <> {$me}
  ORDER BY u.id DESC
")->fetchAll(PDO::FETCH_ASSOC);

// 既存フレンド
$st = $pdo->prepare("SELECT friend_id FROM friends WHERE user_id=?");
$st->execute([$me]);
$myFriends = array_column($st->fetchAll(PDO::FETCH_ASSOC), 'friend_id');
$isFriend  = array_fill_keys($myFriends, true);

// 申請（自分→相手／相手→自分）
$reqOut = $pdo->prepare("SELECT addressee_id AS uid, status FROM friend_requests WHERE requester_id=?");
$reqOut->execute([$me]);
$myOutgoing = [];
foreach ($reqOut->fetchAll(PDO::FETCH_ASSOC) as $r) { $myOutgoing[(int)$r['uid']] = $r['status']; }

$reqIn  = $pdo->prepare("
  SELECT fr.requester_id AS uid, fr.status,
         u.username, u.profile_icon_url, u.presence
  FROM friend_requests fr
  JOIN users u ON u.id = fr.requester_id
  WHERE fr.addressee_id=? AND fr.status='pending'
");
$reqIn->execute([$me]);
$incomingPending = $reqIn->fetchAll(PDO::FETCH_ASSOC); // 申請一覧で使う
$incomingMap = [];
foreach ($incomingPending as $r) { $incomingMap[(int)$r['uid']] = $r['status']; }

/* ========================
   2) プレゼンス色
   ======================== */
$presenceColor = [
  'studying' => '#DB9963', // 勉強中（橙）
  'battling' => '#E15B64', // バトル中（赤）
  'online'   => '#32C27F', // オンライン（緑）
  'offline'  => '#6B8DFF', // オフライン（青）
];

/* ========================
   3) ヘルパー描画
   ======================== */
function render_user_icon($u, $dotColor, $cardBg='#3E4F7B'){
  ob_start(); ?>
  <div class="user-icon">
    <?php if (!empty($u['profile_icon_url'])): ?>
      <img src="<?= htmlspecialchars($u['profile_icon_url']) ?>" alt="icon">
    <?php else: ?>
      <svg viewBox="0 0 24 24" width="28" height="28" fill="#9FB0D6" aria-hidden="true">
        <path d="M12 12c2.9 0 5-2.24 5-5s-2.1-5-5-5-5 2.24-5 5 2.1 5 5 5Zm0 2c-4.42 0-8 2.24-8 5v1h16v-1c0-2.76-3.58-5-8-5Z"/>
      </svg>
    <?php endif; ?>
    <span class="presence-dot" style="background:<?= $dotColor ?>; border-color:<?= $cardBg ?>"></span>
  </div>
  <?php return ob_get_clean();
}
$current_page = 'friend';

?>
<!doctype html>
<html lang="ja">
<head>
  <meta charset="utf-8">
  <title>フレンド</title>
  <link rel="stylesheet" href="styles/style.css">
  <link rel="stylesheet" href="styles/friend.css">
</head>
<body>
<div class="container">
<?php include 'includes/navbar.php'; ?>
<main class="content">

  <!-- タブ -->
  <nav class="tabs" role="tablist" aria-label="friend tabs">
    <a class="tab <?= $activeTab==='all' ? 'active':'' ?>" href="friend.php?tab=all">すべて</a>
    <a class="tab <?= $activeTab==='requests' ? 'active':'' ?>" href="friend.php?tab=requests">
      申請一覧 <span class="badge"><?= count($incomingPending) ?></span>
    </a>
  </nav>

  <?php if ($activeTab === 'requests'): ?>
    <!-- ========== 申請一覧タブ ========== -->
    <?php if (empty($incomingPending)): ?>
      <p class="empty">現在、受信しているフレンド申請はありません。</p>
    <?php else: ?>
    <section class="friends-grid">
      <?php foreach ($incomingPending as $u):
        $uid = (int)$u['uid'];
        $presence = $u['presence'] ?? 'offline';
        $dot = $presenceColor[$presence] ?? $presenceColor['offline'];

        // アバター
        [$parts, $equip] = loadAvatarStacks($pdo, $uid);
        $avatarHtml = renderAvatarFull($parts, $equip);

        // ステータス（簡略：level/攻撃/防御）
        $st = getAvatarStatusWithEquip($pdo, $uid);
        $level   = (int)($st['level']   ?? 1);
        $attack  = (int)($st['attack']  ?? 0);
        $defense = (int)($st['defense'] ?? 0);
      ?>
      <article class="friend-card" role="article" aria-label="friend request">
        <header class="friend-hd">
          <?= render_user_icon($u, $dot) ?>
          <div class="user-name"><?= htmlspecialchars($u['username']) ?></div>
        </header>
        <div class="avatar-wrap"><?= $avatarHtml ?></div>
        <dl class="stats">
          <div class="stat-row"><dt>レベル</dt><dd><?= $level ?></dd></div>
          <div class="stat-row"><dt>攻撃</dt><dd><?= $attack ?></dd></div>
          <div class="stat-row"><dt>防御</dt><dd><?= $defense ?></dd></div>
        </dl>
        <div class="cta">
          <form method="post" action="friend_action.php">
            <input type="hidden" name="action" value="accept_request">
            <input type="hidden" name="target_id" value="<?= $uid ?>">
            <button class="btn" type="submit">申請を承認</button>
          </form>
          <form method="post" action="friend_action.php">
            <input type="hidden" name="action" value="reject_request">
            <input type="hidden" name="target_id" value="<?= $uid ?>">
            <button class="btn danger" type="submit">拒否</button>
          </form>
        </div>
      </article>
      <?php endforeach; ?>
    </section>
    <?php endif; ?>

  <?php else: ?>
    <!-- ========== 既存の一覧タブ（従来表示） ========== -->
    <section class="friends-grid">
    <?php foreach ($users as $u):
      $uid = (int)$u['id'];
      // アバター（装備込み）
      [$parts, $equip] = loadAvatarStacks($pdo, $uid);
      $avatarHtml = renderAvatarFull($parts, $equip);

      $st = getAvatarStatusWithEquip($pdo, $uid);
      $level   = (int)($st['level']   ?? 1);
      $attack  = (int)($st['attack']  ?? 0);
      $defense = (int)($st['defense'] ?? 0);

      $eqCount = (int)$pdo->query("SELECT COUNT(*) FROM user_avatar_equipments WHERE user_id={$uid}")->fetchColumn();
      $titleCount = (int)$pdo->query("SELECT COUNT(*) FROM user_titles WHERE user_id={$uid}")->fetchColumn();

      $presence = $u['presence'] ?? 'offline';
      $dot = $presenceColor[$presence] ?? $presenceColor['offline'];

      $friendNow = isset($isFriend[$uid]);
      $outStatus = $myOutgoing[$uid] ?? null;
      $inPending = isset($incomingMap[$uid]); // 相手→自分 pending 中
    ?>
      <article class="friend-card">
        <header class="friend-hd">
          <?= render_user_icon($u, $dot) ?>
          <div class="user-name"><?= htmlspecialchars($u['username']) ?></div>
        </header>

        <div class="avatar-wrap"><?= $avatarHtml ?></div>

        <dl class="stats">
          <div class="stat-row"><dt>レベル</dt><dd><?= $level ?></dd></div>
          <div class="stat-row"><dt>攻撃</dt><dd><?= $attack ?></dd></div>
          <div class="stat-row"><dt>防御</dt><dd><?= $defense ?></dd></div>
          <div class="stat-row"><dt>装備数</dt><dd><?= $eqCount ?></dd></div>
          <div class="stat-row"><dt>称号数</dt><dd><?= $titleCount ?></dd></div>
        </dl>

        <div class="cta">
          <?php if ($friendNow): ?>
            <form method="post" action="friend_action.php">
              <input type="hidden" name="action" value="battle_request">
              <input type="hidden" name="target_id" value="<?= $uid ?>">
              <button class="btn" type="submit">バトルを申し込む</button>
            </form>
          <?php elseif ($inPending): ?>
            <form method="post" action="friend_action.php">
              <input type="hidden" name="action" value="accept_request">
              <input type="hidden" name="target_id" value="<?= $uid ?>">
              <button class="btn" type="submit">申請を承認</button>
            </form>
            <form method="post" action="friend_action.php">
              <input type="hidden" name="action" value="reject_request">
              <input type="hidden" name="target_id" value="<?= $uid ?>">
              <button class="btn danger" type="submit">拒否</button>
            </form>
          <?php elseif ($outStatus === 'pending'): ?>
            <button class="btn secondary" disabled>申請中…</button>
          <?php else: ?>
            <form method="post" action="friend_action.php">
              <input type="hidden" name="action" value="send_request">
              <input type="hidden" name="target_id" value="<?= $uid ?>">
              <button class="btn" type="submit">フレンド申請</button>
            </form>
          <?php endif; ?>

          <!-- ← 状態に関係なく常に出す -->
          <a class="btn secondary" href="profile.php?uid=<?= $uid ?>">プロフィールを見る</a>
        </div>
      </article>
    <?php endforeach; ?>
    </section>
  <?php endif; ?>
</main>
</div>
</body>
</html>
