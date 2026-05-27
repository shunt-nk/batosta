<?php
// profile.php（自分/他人 兼用・フレンドCTA付き）
declare(strict_types=1);
require_once 'includes/session.php';
require 'includes/db.php';
require 'includes/functions.php'; // hasAvatarBody, fetchSelectedPartsBySlots, fetchEquipPaths, renderAvatarFull, getAvatarStatusWithEquip, ensureInitialOutfitEquipped

if (!isset($_SESSION['user'])) { header("Location: index.php"); exit; }
$auth      = $_SESSION['user'];
$auth_id   = (int)$auth['id'];

/* ------- 表示対象の切り替え（?uid= があればそのユーザー） ------- */
$view_id   = isset($_GET['uid']) ? max(0, (int)$_GET['uid']) : $auth_id;
$isSelf    = ($view_id === $auth_id);

/* --- スロット名（DB→UI の吸収） --- */
function db_to_ui_slot(string $db): string {
  static $map = [
    'weapon'=>'weapon','shield'=>'shield','head'=>'head',
    'outfit'=>'outfit','armor'=>'outfit','body'=>'outfit',
    'boots'=>'boots','legs'=>'boots',
  ];
  return $map[$db] ?? $db;
}

/* --- 未装備アイコンの取得（テーブル無くても empty.png を使う） --- */
function fetch_empty_icon_map_profile(PDO $pdo, array $uiSlots): array {
  $map = [];
  try {
    $st = $pdo->query("SELECT slot, empty_icon_path FROM equip_slot_master");
    foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $r) {
      $ui = db_to_ui_slot((string)$r['slot']);
      $map[$ui] = trim((string)$r['empty_icon_path']);
    }
  } catch (Throwable $e) { /* テーブル無しでもOK */ }
  foreach ($uiSlots as $s) if (!isset($map[$s]) || $map[$s]==='') $map[$s] = 'empty.png';
  return $map;
}

/* --- アバター作成済み判定（自分の場合のみ ensureInitialOutfitEquipped を実行） --- */
$needsAvatar = !hasAvatarBody($pdo, $view_id);
if ($isSelf && !$needsAvatar && function_exists('ensureInitialOutfitEquipped')) {
  ensureInitialOutfitEquipped($pdo, $auth_id);
}

/* --- 対象ユーザー情報（カバー／アイコン／プレゼンス／ユーザー名） --- */
// profile_cover_url は後から追加されたカラムのため、存在しない環境に対応
$u = null;
try {
  $stmt = $pdo->prepare("
    SELECT id, username, profile_cover_url, profile_icon_url, presence
    FROM users WHERE id = ? LIMIT 1
  ");
  $stmt->execute([$view_id]);
  $u = $stmt->fetch(PDO::FETCH_ASSOC);
} catch (\Throwable $e) {
  // profile_cover_url が無い環境のフォールバック
  $stmt = $pdo->prepare("
    SELECT id, username, profile_icon_url, presence
    FROM users WHERE id = ? LIMIT 1
  ");
  $stmt->execute([$view_id]);
  $u = $stmt->fetch(PDO::FETCH_ASSOC);
}
if (!$u) { header('Location: friend.php'); exit; }

$coverUrl = (string)($u['profile_cover_url'] ?? '');
$iconUrl  = (string)($u['profile_icon_url']  ?? '');
$presence = (string)($u['presence']          ?? 'offline');
$username = (string)($u['username']          ?? 'ユーザー名');

/* --- 学習時間（簡易集計：対象ユーザー） --- */
$totalStmt = $pdo->prepare("
  SELECT 
    COALESCE(SUM(duration_minutes),0) AS total,
    COALESCE(SUM(CASE WHEN started_at >= CURRENT_DATE - INTERVAL '7 days' THEN duration_minutes END),0) AS week
  FROM study_logs WHERE user_id=?
");
$totalStmt->execute([$view_id]);
$sum          = $totalStmt->fetch(PDO::FETCH_ASSOC) ?: ['total'=>0,'week'=>0];
$totalMin     = (int)$sum['total'];
$weekMin      = (int)$sum['week'];
$avgPerDayMin = (int)round($totalMin / 30);

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
  $equipPaths = fetchEquipPaths($pdo, $view_id); // ← ここは avatars（プレビュー）として重ね描画
  unset($parts['hand']);
  if (!empty($equipPaths['weapon']) && !empty($parts['hand_weapon'])) {
    $parts['hand'] = $parts['hand_weapon'];
  } elseif (!empty($parts['hand_base'])) {
    $parts['hand'] = $parts['hand_base'];
  }
}

/* --- 装備アイコン（右側の5枠表示用：icons を使う） --- */
$SLOTS_UI = ['weapon','shield','head','outfit','boots'];
$emptyIconMap = fetch_empty_icon_map_profile($pdo, $SLOTS_UI);

/* icon_path が無い環境でも動くように 2段階クエリ */
$rows = [];
try {
  $stmt = $pdo->prepare("
    SELECT uae.slot AS slot_db, e.icon_path, e.image_path
    FROM user_avatar_equipments uae
    JOIN equipments e ON uae.equipment_id = e.id
    WHERE uae.user_id = ?
  ");
  $stmt->execute([$view_id]);
  $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
  // fallback（icon_path 列が無い）: image_path のみ
  $stmt = $pdo->prepare("
    SELECT uae.slot AS slot_db, '' AS icon_path, e.image_path
    FROM user_avatar_equipments uae
    JOIN equipments e ON uae.equipment_id = e.id
    WHERE uae.user_id = ?
  ");
  $stmt->execute([$view_id]);
  $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/* build_icon_src は functions.php に定義済み（無ければ自作してもOK） */
$gearIcons = array_fill_keys($SLOTS_UI, ''); // 最後に empty で埋める
foreach ($rows as $r) {
  $slotUi    = db_to_ui_slot((string)$r['slot_db']);
  if (!in_array($slotUi, $SLOTS_UI, true)) continue;

  $iconFile  = trim((string)($r['icon_path'] ?? ''));
  if ($iconFile === '' && !empty($r['image_path'])) {
    // 互換: image_path のファイル名をアイコンとして使う
    $iconFile = basename(trim((string)$r['image_path']));
  }
  $src = $iconFile !== ''
    ? build_icon_src($slotUi, $iconFile)
    : build_icon_src($slotUi, $emptyIconMap[$slotUi] ?? 'empty.png');

  $gearIcons[$slotUi] = $src;
}
// 未装備スロットを empty で補完
foreach ($gearIcons as $s => $v) {
  if ($v === '' || $v === null) {
    $gearIcons[$s] = build_icon_src($s, $emptyIconMap[$s] ?? 'empty.png');
  }
}

/* 5枠を 2+3 に並べる：上段(weapon, shield)／下段(head, outfit, boots) */
$gearLayout   = ['weapon', 'shield', null, 'head', 'outfit', 'boots'];
$current_page = 'profile';

/* ------- フレンド状態（他人ページのときだけ） ------- */
$friendNow = false; $outStatus = null; $inPending = false;
if (!$isSelf) {
  $stf = $pdo->prepare("SELECT 1 FROM friends WHERE user_id=? AND friend_id=?");
  $stf->execute([$auth_id, $view_id]);
  $friendNow = (bool)$stf->fetchColumn();

  $reqOut = $pdo->prepare("SELECT status FROM friend_requests WHERE requester_id=? AND addressee_id=?");
  $reqOut->execute([$auth_id, $view_id]);
  $outStatus = $reqOut->fetchColumn() ?: null;

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

        <div class="study-card">
          <div class="row"><span>勉強の記録</span></div>
          <div class="row"><span>1日の平均</span><b><?= floor($avgPerDayMin/60) ?>時間<?= $avgPerDayMin%60 ?>分</b></div>
          <div class="row"><span>週間</span><b><?= floor($weekMin/60) ?>時間<?= $weekMin%60 ?>分</b></div>
          <div class="row"><span>累計</span><b><?= floor($totalMin/60) ?>時間<?= $totalMin%60 ?>分</b></div>
        </div>

        <?php if ($isSelf): ?>
          <a class="settings-btn" href="settings.php">⚙ 設定</a>
        <?php else: ?>
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

      <!-- 中央：アバター（見出しなし、中央大きく） -->
      <section class="avatar-wrap">
        <div class="avatar-container">
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
        </div>
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
          <?php foreach (['weapon','shield',null,'head','outfit','boots'] as $slot): ?>
            <?php if ($slot === null): ?>
              <div class="gear-cell"></div>
            <?php else: ?>
              <div class="gear-cell">
                <img src="<?= htmlspecialchars($gearIcons[$slot], ENT_QUOTES) ?>" alt="<?= htmlspecialchars($slot) ?>">
              </div>
            <?php endif; ?>
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
            ORDER BY ut.id DESC
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
