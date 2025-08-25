<?php
// profile.php
declare(strict_types=1);
session_start();
require 'includes/db.php';
require 'includes/functions.php'; // fetchSelectedParts / renderAvatarLayers / getAvatarStatusWithEquip など

if (!isset($_SESSION['user'])) { header("Location: index.php"); exit; }
$user = $_SESSION['user'];
$user_id = (int)$user['id'];

/* --- カバー画像とプレゼンス（未実装ならデフォルト） --- */
$stmt = $pdo->prepare("SELECT profile_cover_url, profile_icon_url, presence
                       FROM users WHERE id = ? LIMIT 1");
$stmt->execute([$user_id]);
$u = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
$coverUrl = $u['profile_cover_url'] ?? '';         // 画像があれば上部カバーに敷く
$iconUrl  = $u['profile_icon_url']  ?? '';         // 円形アイコンに使う（無ければSVG）
$presence = $u['presence']          ?? 'offline';  // 'studying'|'battling'|'online'|'offline'

/* --- 学習時間（例：合計/週/日平均。無ければ0） --- */
$totalStmt = $pdo->prepare("SELECT 
  COALESCE(SUM(duration_minutes),0) AS total,
  COALESCE(SUM(CASE WHEN started_at >= DATE_SUB(CURDATE(), INTERVAL 7 DAY) THEN duration_minutes END),0) AS week
  FROM study_logs WHERE user_id=?");
$totalStmt->execute([$user_id]);
$sum = $totalStmt->fetch(PDO::FETCH_ASSOC) ?: ['total'=>0,'week'=>0];
$totalMin = (int)$sum['total'];
$weekMin  = (int)$sum['week'];
$avgPerDayMin = (int)round($totalMin / max(1, min(30, (int)$totalMin ? 30 : 1))); // 仮の平均（必要なら計算式を差し替え）

/* --- レベル/ステータス（未定義は安全値） --- */
$stat = getAvatarStatusWithEquip($pdo, $user_id);
$level = (int)($stat['level'] ?? 1);
$hp = $stat['hp'] ?? (100 + $level * 10);  // HP/SP は将来テーブル追加予定。今は見た目用の暫定値。
$sp = $stat['sp'] ?? (20 + (int)floor($level / 2));

/* --- アバター（4スロット） --- */
$parts = fetchSelectedParts($pdo, $user_id); // ['body'=>..., 'hair'=>..., 'eyes'=>..., 'mouth'=>...]

/* --- 装備（5枠分。無ければプレースホルダで空表示） --- */
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

// 表示したい5枠（スロット名はあなたのDBに合わせて調整可）
$equipSlots = ['head','weapon','shield','armor','boots']; // 例：5枠
$current_page = 'profile';
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
    <!-- カバー（ユーザー画像 or グラデ） -->
    <div class="cover" style="<?= $coverUrl ? "background-image:url('".htmlspecialchars($coverUrl,ENT_QUOTES)."');" : '' ?>">
      <div class="cover-overlay"></div>
    </div>

    <section class="profile-grid">
      <!-- 左の上段：丸アイコン＋ユーザー名 -->
      <div class="identity-card">
        <div class="avatar-circle">
          <?php if ($iconUrl): ?>
            <img src="<?= htmlspecialchars($iconUrl) ?>" alt="ユーザーアイコン">
          <?php else: ?>
            <!-- 代替の簡易アイコン（SVG） -->
            <svg viewBox="0 0 100 100" aria-hidden="true">
              <circle cx="50" cy="34" r="18" fill="#ffffff"/>
              <rect x="20" y="60" width="60" height="28" rx="14" fill="#ffffff"/>
            </svg>
          <?php endif; ?>
          <span class="presence <?= htmlspecialchars($presence) ?>"></span>
        </div>
        <div class="identity-name"><?= htmlspecialchars($user['username'] ?? 'ユーザー名') ?></div>

        <!-- 学習記録の小カード -->
        <div class="study-card">
          <div class="row"><span>勉強の記録</span></div>
          <div class="row"><span>1日の平均</span><b><?= floor($avgPerDayMin/60) ?>時間<?= $avgPerDayMin%60 ?>分</b></div>
          <div class="row"><span>週間</span><b><?= floor($weekMin/60) ?>時間<?= $weekMin%60 ?>分</b></div>
          <div class="row"><span>累計</span><b><?= floor($totalMin/60) ?>時間<?= $totalMin%60 ?>分</b></div>
        </div>

        <a class="settings-btn" href="settings.php">⚙ 設定</a>
      </div>

      <!-- 中央：アバター -->
      <div class="avatar-wrap">
        <?= renderAvatarLayers($parts) ?>
      </div>

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
              <?php if (!empty($equip[$slot])): ?>
                <img src="<?= htmlspecialchars($equip[$slot]) ?>" alt="<?= htmlspecialchars($slot) ?>">
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
          $ts = $pdo->prepare("SELECT t.name FROM user_titles ut JOIN titles t ON ut.title_id=t.id WHERE ut.user_id=? ORDER BY ut.updated_at DESC LIMIT 8");
          $ts->execute([$user_id]);
          $titles = $ts->fetchAll(PDO::FETCH_COLUMN);
          if ($titles) {
            foreach ($titles as $name) {
              echo '<div class="title-chip">'.htmlspecialchars($name).'</div>';
            }
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
