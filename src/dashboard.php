<?php
// dashboard.php（既存の進捗バー＆宿題履歴は機能そのまま／見た目だけ整形）
declare(strict_types=1);
session_start();

require 'includes/db.php';
require 'includes/functions.php'; // hasAvatarBody, loadAvatarStacks, renderAvatarFull, getAvatarStatusWithEquip, requiredExp

if (!isset($_SESSION['user'])) {
  header('Location: index.php');
  exit;
}

$user_id = (int)$_SESSION['user']['id'];

/* 1) アバター作成済み？ */
$needsAvatar = !hasAvatarBody($pdo, $user_id);

if ($needsAvatar) {
  $parts = [];
  $equipPaths = [];
} else {
  // DB保存の選択パーツ＋装備パスを一括取得（重複取得はしない）
  list($parts, $equipPaths) = loadAvatarStacks($pdo, $user_id);

  // hand を最終決定（武器あり→hand_weapon / なし→hand_base）
  unset($parts['hand']);
  if (!empty($equipPaths['weapon']) && !empty($parts['hand_weapon'])) {
    $parts['hand'] = $parts['hand_weapon'];
  } elseif (!empty($parts['hand_base'])) {
    $parts['hand'] = $parts['hand_base'];
  }
}

/* 5) ステータス（装備込み） */
$status = getAvatarStatusWithEquip($pdo, $user_id);
$level  = (int)($status['level'] ?? 1);
$exp    = (int)($status['exp']   ?? 0);
$reqExp = (int)max(1, requiredExp($level));
$progress_percent = max(0, min(100, (int)round(($exp / $reqExp) * 100)));

/* 6) 今日の宿題（←既存そのまま） */
$today = date("Y-m-d");
$stmt = $pdo->prepare("
  SELECT subject, type, duration_minutes, started_at
  FROM study_logs
  WHERE user_id = ? AND DATE(started_at) = ?
  ORDER BY started_at DESC
");
$stmt->execute([$user_id, $today]);
$logs = $stmt->fetchAll(PDO::FETCH_ASSOC);

/* 7) 所持素材（←既存そのまま） */
$stmt = $pdo->prepare("
  SELECT m.name, COALESCE(um.quantity,0) AS quantity
  FROM user_materials um
  JOIN materials m ON um.material_id = m.id
  WHERE um.user_id = ?
  ORDER BY m.name
");
$stmt->execute([$user_id]);
$materials = $stmt->fetchAll(PDO::FETCH_ASSOC);

/* 8) navbar 用 */
$current_page = 'home';
?>
<!DOCTYPE html>
<html lang="ja">
<head>
  <meta charset="UTF-8">
  <title>バトスタ｜ダッシュボード</title>
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <link rel="stylesheet" href="styles/style.css">
  <link rel="stylesheet" href="styles/dashboard.css">
</head>
<body>
<div class="container">
  <?php include 'includes/navbar.php'; ?>

  <!-- ▼ 見た目のためのラップ：左右2カラム -->
  <main class="dash">
    <!-- 左レーン -->
    <section class="left">
      <div class="lvl">
        <div class="lvl-row">
          <div class="lvl-title">レベル<?= htmlspecialchars((string)$level, ENT_QUOTES, 'UTF-8') ?></div>
          <div class="lvl-sub">勉強見習い</div>
        </div>

        <!-- ★進捗バーは既存の progress-bar / progress をそのまま使う -->
        <div class="lvl-progress">
          <div class="progress-bar" aria-label="経験値進捗">
            <div class="progress" style="width: <?= $progress_percent ?>%;"></div>
          </div>
        </div>

        <div class="lvl-note">
          経験値：<?= htmlspecialchars((string)$exp, ENT_QUOTES, 'UTF-8') ?> / <?= htmlspecialchars((string)$reqExp, ENT_QUOTES, 'UTF-8') ?>
          （次のレベルまで：<?= htmlspecialchars((string)max(0, $reqExp - $exp), ENT_QUOTES, 'UTF-8') ?>）
        </div>
      </div>

      <div class="avatar-box">
        <?php
          if ($needsAvatar) {
            echo '<div class="avatar-standin">アバター未作成<br><a href="avatar_create.php?first=1">作成へ</a></div>';
          } else {
            echo renderAvatarFull($parts, $equipPaths);
          }
        ?>
        <div style="margin-top:12px;text-align:center;">
        </div>
      </div>
    </section>

    <!-- 右レーン -->
    <section class="right">
      <!-- 今日の宿題履歴：機能・出力は既存のまま（ULリスト） -->
      <div class="card hw">
        <div class="card-h">今日の宿題履歴</div>
        <div class="hw-body">
          <ul class="hw-list">
            <?php if (!$logs): ?>
              <li>まだ今日の宿題はありません</li>
            <?php else: ?>
              <?php foreach ($logs as $log): ?>
                <li>
                  <?= htmlspecialchars($log['subject'] ?? '', ENT_QUOTES, 'UTF-8') ?> /
                  <?= htmlspecialchars($log['type'] ?? '', ENT_QUOTES, 'UTF-8') ?>（
                  <?= (int)($log['duration_minutes'] ?? 0) ?>分）
                </li>
              <?php endforeach; ?>
            <?php endif; ?>
          </ul>
        </div>
      </div>

      <div class="right-grid">
        <div class="card simple">
          <div class="card-h">ステータス</div>
          <div class="status">
            <div class="row"><span>攻撃力</span><b><?= (int)($status['attack'] ?? 0) ?></b></div>
            <div class="row"><span>防御力</span><b><?= (int)($status['defense'] ?? 0) ?></b></div>
          </div>
        </div>

        <div class="card mats">
          <div class="card-h">所持素材一覧</div>
          <ul class="mat-list">
            <?php if (!$materials): ?>
              <li>素材はまだありません</li>
            <?php else: ?>
              <?php foreach ($materials as $m): ?>
                <li><span class="name"><?= htmlspecialchars($m['name'], ENT_QUOTES, 'UTF-8') ?></span><span class="qty">×<?= (int)$m['quantity'] ?></span></li>
              <?php endforeach; ?>
            <?php endif; ?>
          </ul>
        </div>
      </div>
    </section>
  </main>
</div>
</body>
</html>
