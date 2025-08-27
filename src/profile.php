<?php
// profile.php
declare(strict_types=1);
session_start();
require 'includes/db.php';
require 'includes/functions.php'; // hasAvatarBody, fetchSelectedPartsBySlots, fetchEquipPaths, renderAvatarFull, getAvatarStatusWithEquip, ensureInitialOutfitEquipped

if (!isset($_SESSION['user'])) { header("Location: index.php"); exit; }
$user    = $_SESSION['user'];
$user_id = (int)$user['id'];

/* --- アバター作成済み判定（最優先で定義） --- */
$needsAvatar = !hasAvatarBody($pdo, $user_id);

/* --- outfit の初期装備は「作成済みのときだけ」保証 --- */
if (!$needsAvatar && function_exists('ensureInitialOutfitEquipped')) {
  ensureInitialOutfitEquipped($pdo, $user_id);
}

/* --- カバー画像／アイコン／プレゼンス --- */
$stmt = $pdo->prepare("
  SELECT profile_cover_url, profile_icon_url, presence
  FROM users WHERE id = ? LIMIT 1
");
$stmt->execute([$user_id]);
$u = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
$coverUrl = $u['profile_cover_url'] ?? '';
$iconUrl  = $u['profile_icon_url']  ?? '';
$presence = $u['presence']          ?? 'offline';

/* --- 学習時間（簡易集計） --- */
$totalStmt = $pdo->prepare("
  SELECT 
    COALESCE(SUM(duration_minutes),0) AS total,
    COALESCE(SUM(CASE WHEN started_at >= DATE_SUB(CURDATE(), INTERVAL 7 DAY) THEN duration_minutes END),0) AS week
  FROM study_logs WHERE user_id=?
");
$totalStmt->execute([$user_id]);
$sum          = $totalStmt->fetch(PDO::FETCH_ASSOC) ?: ['total'=>0,'week'=>0];
$totalMin     = (int)$sum['total'];
$weekMin      = (int)$sum['week'];
$avgPerDayMin = (int)round($totalMin / 30); // 仮の平均（日数管理が無ければ30日で割る）

/* --- ステータス --- */
$stat  = getAvatarStatusWithEquip($pdo, $user_id);
$level = (int)($stat['level'] ?? 1);
$hp    = $stat['hp'] ?? (100 + $level * 10);
$sp    = $stat['sp'] ?? (20 + (int)floor($level / 2));

/* --- アバター表示用（作成済みなら hand_* 含めて取得） --- */
/* --- アバター表示用（作成済みなら hand_* 含めて取得） --- */
$needsAvatar = !hasAvatarBody($pdo, $user_id);

if ($needsAvatar) {
  $parts = []; $equipPaths = [];
} else {
  $parts      = fetchSelectedPartsBySlots($pdo, $user_id, ['body','hair','eyes','mouth','hand_base','hand_weapon']);
  $equipPaths = fetchEquipPaths($pdo, $user_id);

  // hand を確定（武器があれば hand_weapon、無ければ hand_base）
  unset($parts['hand']);
  if (!empty($equipPaths['weapon']) && !empty($parts['hand_weapon'])) {
    $parts['hand'] = $parts['hand_weapon'];
  } elseif (!empty($parts['hand_base'])) {
    $parts['hand'] = $parts['hand_base'];
  }
}
/* --- 装備一覧（右側の5枠表示用） --- */
$stmt = $pdo->prepare("
  SELECT uae.slot AS slot, e.image_path AS image_path
  FROM user_avatar_equipments uae
  JOIN equipments e ON uae.equipment_id = e.id
  WHERE uae.user_id = ?
");
$stmt->execute([$user_id]);
$equipments = [];
foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $r) {
  $equipments[$r['slot']] = $r['image_path'];
}

/* 表示したい5枠：DBに合わせて outfit を使用（旧 armor は使わない） */
$equipSlots    = ['head','weapon','shield','outfit','boots'];
$current_page  = 'profile';
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
        <div class="identity-name"><?= htmlspecialchars($user['username'] ?? 'ユーザー名') ?></div>

        <div class="study-card">
          <div class="row"><span>勉強の記録</span></div>
          <div class="row"><span>1日の平均</span><b><?= floor($avgPerDayMin/60) ?>時間<?= $avgPerDayMin%60 ?>分</b></div>
          <div class="row"><span>週間</span><b><?= floor($weekMin/60) ?>時間<?= $weekMin%60 ?>分</b></div>
          <div class="row"><span>累計</span><b><?= floor($totalMin/60) ?>時間<?= $totalMin%60 ?>分</b></div>
        </div>

        <a class="settings-btn" href="settings.php">⚙ 設定</a>
      </div>

      <!-- 中央：アバター -->
      <section class="avatar_section">
        <h2>アバター</h2>
        <?php if ($needsAvatar): ?>
          <div style="background:#fff7e6;border:2px dashed #e6d4ae;color:#6b4d22;padding:12px 14px;border-radius:12px;margin:16px 0;">
            まずはアバターを作成しましょう →
            <a href="avatar_create.php?first=1" style="font-weight:700;color:#2e7d32;text-decoration:underline;">アバター作成へ</a>
          </div>
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
          $ts->execute([$user_id]);
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
