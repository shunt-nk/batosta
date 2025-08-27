<?php
// profile.php（自分/他人 兼用・フレンドCTA付き）
declare(strict_types=1);
session_start();
require 'includes/db.php';
require 'includes/functions.php'; // hasAvatarBody, fetchSelectedPartsBySlots, fetchEquipPaths, renderAvatarFull, getAvatarStatusWithEquip, ensureInitialOutfitEquipped

if (!isset($_SESSION['user'])) { header("Location: index.php"); exit; }
$auth      = $_SESSION['user'];
$auth_id   = (int)$auth['id'];

/* ------- 表示対象の切り替え（?uid= があればそのユーザー） ------- */
$view_id   = isset($_GET['uid']) ? max(0, (int)$_GET['uid']) : $auth_id;
$isSelf    = ($view_id === $auth_id);

/* --- アバター作成済み判定（自分の場合のみ ensureInitialOutfitEquipped を実行） --- */
$needsAvatar = !hasAvatarBody($pdo, $view_id);
if ($isSelf && !$needsAvatar && function_exists('ensureInitialOutfitEquipped')) {
  ensureInitialOutfitEquipped($pdo, $auth_id);
}

/* --- 対象ユーザー情報（カバー／アイコン／プレゼンス／ユーザー名） --- */
$stmt = $pdo->prepare("
  SELECT id, username, profile_cover_url, profile_icon_url, presence
  FROM users WHERE id = ? LIMIT 1
");
$stmt->execute([$view_id]);
$u = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$u) { header('Location: friend.php'); exit; }

$coverUrl = $u['profile_cover_url'] ?? '';
$iconUrl  = $u['profile_icon_url']  ?? '';
$presence = $u['presence']          ?? 'offline';
$username = $u['username']          ?? 'ユーザー名';

/* --- 学習時間（簡易集計：対象ユーザー） --- */
$totalStmt = $pdo->prepare("
  SELECT 
    COALESCE(SUM(duration_minutes),0) AS total,
    COALESCE(SUM(CASE WHEN started_at >= DATE_SUB(CURDATE(), INTERVAL 7 DAY) THEN duration_minutes END),0) AS week
  FROM study_logs WHERE user_id=?
");
$totalStmt->execute([$view_id]);
$sum          = $totalStmt->fetch(PDO::FETCH_ASSOC) ?: ['total'=>0,'week'=>0];
$totalMin     = (int)$sum['total'];
$weekMin      = (int)$sum['week'];
$avgPerDayMin = (int)round($totalMin / 30); // 日数管理が無ければ30日で割る（従来のまま）

/* --- ステータス（対象ユーザー） --- */
$stat  = getAvatarStatusWithEquip($pdo, $view_id);
$level = (int)($stat['level'] ?? 1);
$hp    = $stat['hp'] ?? (100 + $level * 10);
$sp    = $stat['sp'] ?? (20 + (int)floor($level / 2));

/* --- アバター（対象ユーザー） --- */
if ($needsAvatar) {
  $parts = []; $equipPaths = [];
} else {
  $parts      = fetchSelectedPartsBySlots($pdo, $view_id, ['body','hair','eyes','mouth','hand_base','hand_weapon']);
  $equipPaths = fetchEquipPaths($pdo, $view_id);

  // hand を確定（武器があれば hand_weapon、無ければ hand_base）
  unset($parts['hand']);
  if (!empty($equipPaths['weapon']) && !empty($parts['hand_weapon'])) {
    $parts['hand'] = $parts['hand_weapon'];
  } elseif (!empty($parts['hand_base'])) {
    $parts['hand'] = $parts['hand_base'];
  }
}

/* --- 装備一覧（右側の5枠表示用：対象ユーザー） --- */
$stmt = $pdo->prepare("
  SELECT uae.slot AS slot, e.image_path AS image_path
  FROM user_avatar_equipments uae
  JOIN equipments e ON uae.equipment_id = e.id
  WHERE uae.user_id = ?
");
$stmt->execute([$view_id]);
$equipments = [];
foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $r) {
  $equipments[$r['slot']] = $r['image_path'];
}

/* 表示したい5枠：DBに合わせて outfit を使用（旧 armor は使わない） */
$equipSlots    = ['head','weapon','shield','outfit','boots'];
$current_page  = 'profile';

/* ------- フレンド状態（他人ページのときだけ使う） ------- */
$friendNow = false; $outStatus = null; $inPending = false;
if (!$isSelf) {
  // 自分⇆相手 が friends にあるか
  $stf = $pdo->prepare("SELECT 1 FROM friends WHERE user_id=? AND friend_id=?");
  $stf->execute([$auth_id, $view_id]);
  $friendNow = (bool)$stf->fetchColumn();

  // 自分→相手 の申請状態
  $reqOut = $pdo->prepare("SELECT status FROM friend_requests WHERE requester_id=? AND addressee_id=?");
  $reqOut->execute([$auth_id, $view_id]);
  $outStatus = $reqOut->fetchColumn() ?: null;

  // 相手→自分 の pending 有無
  $reqIn = $pdo->prepare("SELECT status FROM friend_requests WHERE requester_id=? AND addressee_id=?");
  $reqIn->execute([$view_id, $auth_id]);
  $inPending = ($reqIn->fetchColumn() === 'pending');
}
?>
<!doctype html>
<html lang="ja">
<head>
<meta charset="utf-8">
<title>プロフィール | バトスタ</title>
<meta name="viewport" content="width=device-width, initial-scale=1">
<link rel="stylesheet" href="styles/style.css">
<link rel="stylesheet" href="styles/profile.css">
<style>
/* 既存デザインを崩さない最小の補助 */
.profile .cta-wrap{display:flex; gap:10px; margin-top:12px; flex-wrap:wrap;}
.profile .btn{border:none; border-radius:14px; padding:12px 18px; font-weight:700; cursor:pointer}
.profile .btn.primary{background:#DB9963; color:#fff;}
.profile .btn.secondary{background:#C9CFDF; color:#243252;}
.profile .btn.danger{background:#E15B64; color:#fff;}
.profile .btn[disabled]{opacity:.6; cursor:not-allowed;}
</style>
</head>
<body>
<div class="container">
  <?php include 'includes/navbar.php'; ?>

  <main class="profile">
    <!-- カバー -->
    <div class="cover" style="<?= $coverUrl ? "background-image:url('".htmlspecialchars($coverUrl,ENT_QUOTES)."');" : '' ?>">
      <div class="cover-overlay"></div>
    </div>

    <section class="profile-grid">
      <!-- 左：プロフィールカード -->
      <div class="identity-card">
        <div class="avatar-circle">
          <?php if ($iconUrl): ?>
            <img src="<?= htmlspecialchars($iconUrl) ?>" alt="ユーザーアイコン">
          <?php else: ?>
            <svg viewBox="0 0 100 100" aria-hidden="true">
              <circle cx="50" cy="34" r="18" fill="#ffffff"/>
              <rect x="20" y="60" width="60" height="28" rx="14" fill="#ffffff"/>
            </svg>
          <?php endif; ?>
          <span class="presence <?= htmlspecialchars($presence) ?>"></span>
        </div>

        <div class="identity-name"><?= htmlspecialchars($username) ?></div>

        <?php if ($isSelf): ?>
          <!-- 自分のときは従来どおり設定ボタン -->
          <div class="study-card">
            <div class="row"><span>勉強の記録</span></div>
            <div class="row"><span>1日の平均</span><b><?= floor($avgPerDayMin/60) ?>時間<?= $avgPerDayMin%60 ?>分</b></div>
            <div class="row"><span>週間</span><b><?= floor($weekMin/60) ?>時間<?= $weekMin%60 ?>分</b></div>
            <div class="row"><span>累計</span><b><?= floor($totalMin/60) ?>時間<?= $totalMin%60 ?>分</b></div>
          </div>
          <a class="settings-btn" href="settings.php">⚙ 設定</a>
        <?php else: ?>
          <!-- 他人プロフィール：学習記録 + フレンドCTA -->
          <div class="study-card">
            <div class="row"><span>勉強の記録</span></div>
            <div class="row"><span>1日の平均</span><b><?= floor($avgPerDayMin/60) ?>時間<?= $avgPerDayMin%60 ?>分</b></div>
            <div class="row"><span>週間</span><b><?= floor($weekMin/60) ?>時間<?= $weekMin%60 ?>分</b></div>
            <div class="row"><span>累計</span><b><?= floor($totalMin/60) ?>時間<?= $totalMin%60 ?>分</b></div>
          </div>

          <div class="cta-wrap">
            <?php if ($friendNow): ?>
              <form method="post" action="friend_action.php">
                <input type="hidden" name="action" value="battle_request">
                <input type="hidden" name="target_id" value="<?= $view_id ?>">
                <button class="btn primary" type="submit">バトルを申し込む</button>
              </form>
            <?php elseif ($inPending): ?>
              <form method="post" action="friend_action.php">
                <input type="hidden" name="action" value="accept_request">
                <input type="hidden" name="target_id" value="<?= $view_id ?>">
                <button class="btn primary" type="submit">申請を承認</button>
              </form>
              <form method="post" action="friend_action.php">
                <input type="hidden" name="action" value="reject_request">
                <input type="hidden" name="target_id" value="<?= $view_id ?>">
                <button class="btn danger" type="submit">拒否</button>
              </form>
            <?php elseif ($outStatus === 'pending'): ?>
              <button class="btn secondary" disabled>申請中…</button>
            <?php else: ?>
              <form method="post" action="friend_action.php">
                <input type="hidden" name="action" value="send_request">
                <input type="hidden" name="target_id" value="<?= $view_id ?>">
                <button class="btn primary" type="submit">フレンド申請</button>
              </form>
            <?php endif; ?>
          </div>
        <?php endif; ?>
      </div>

      <!-- 中央：アバター -->
      <section class="avatar_section">
        <h2>アバター</h2>
        <?php if ($needsAvatar): ?>
          <?php if ($isSelf): ?>
            <div style="background:#fff7e6;border:2px dashed #e6d4ae;color:#6b4d22;padding:12px 14px;border-radius:12px;margin:16px 0;">
              まずはアバターを作成しましょう →
              <a href="avatar_create.php?first=1" style="font-weight:700;color:#2e7d32;text-decoration:underline;">アバター作成へ</a>
            </div>
          <?php else: ?>
            <div style="background:#eef3ff;border:2px dashed #c9d4ff;color:#394a7a;padding:12px 14px;border-radius:12px;margin:16px 0;">
              このユーザーはまだアバターを作成していません。
            </div>
          <?php endif; ?>
        <?php else: ?>
          <?= renderAvatarFull($parts, $equipPaths) ?>
        <?php endif; ?>
      </section>

      <!-- 右：装備 + ステータス -->
      <div class="stats-card">
        <div class="stats-table">
          <div class="k">レベル</div><div class="v"><?= $level ?></div>
          <div class="k">HP</div>    <div class="v"><?= (int)$hp ?></div>
          <div class="k">SP</div>    <div class="v"><?= (int)$sp ?></div>
          <div class="k">こうげき</div><div class="v"><?= (int)($stat['attack'] ?? 0) ?></div>
          <div class="k">ぼうぎょ</div><div class="v"><?= (int)($stat['defense'] ?? 0) ?></div>
        </div>
        <div class="gear-grid">
          <?php foreach ($equipSlots as $slot): ?>
            <div class="gear-cell">
              <?php if (!empty($equipments[$slot])): ?>
                <img src="<?= htmlspecialchars($equipments[$slot]) ?>" alt="<?= htmlspecialchars($slot) ?>">
              <?php endif; ?>
            </div>
          <?php endforeach; ?>
        </div>
      </div>

      <!-- 下段：称号 -->
      <div class="title-area">
        <h3>取得した称号</h3>
        <div class="title-grid">
          <?php
          $ts = $pdo->prepare("
            SELECT t.name
            FROM user_titles ut
            JOIN titles t ON ut.title_id=t.id
            WHERE ut.user_id=?
            ORDER BY ut.updated_at DESC
            LIMIT 8
          ");
          $ts->execute([$view_id]);
          $titles = $ts->fetchAll(PDO::FETCH_COLUMN);
          if ($titles) {
            foreach ($titles as $name) echo '<div class="title-chip">'.htmlspecialchars($name).'</div>';
          } else {
            echo '<div class="title-chip ghost">称号はまだありません</div>';
          }
          ?>
        </div>
      </div>
    </section>
  </main>
</div>
</body>
</html>
