<?php
// dashboard.php
declare(strict_types=1);
session_start();
require 'includes/db.php';
require 'includes/functions.php'; // hasAvatarBody, fetchSelectedPartsBySlots, fetchEquipPaths, renderAvatarFull, getAvatarStatus*, requiredExp

if (!isset($_SESSION['user'])) {
  header('Location: index.php');
  exit;
}
$user    = $_SESSION['user'];
$user_id = (int)$user['id'];

// アバター作成済みか
$needsAvatar = !hasAvatarBody($pdo, $user_id);

// ベースパーツ（単手仕様に合わせて hand_* も取得）
$parts = $needsAvatar
  ? []
  : fetchSelectedPartsBySlots($pdo, $user_id, ['body','hair','eyes','mouth','hand_base','hand_weapon']);

// ステータス（装備込み）
$status = getAvatarStatusWithEquip($pdo, $user_id);

// 装備（プレビュー用に slot => image_path で取得）
$equipPaths = $needsAvatar ? [] : fetchEquipPaths($pdo, $user_id);

// 素材所持数
$stmt = $pdo->prepare("
  SELECT m.name, um.quantity
  FROM user_materials um
  JOIN materials m ON um.material_id = m.id
  WHERE um.user_id = ?
");
$stmt->execute([$user_id]);
$materials = $stmt->fetchAll(PDO::FETCH_ASSOC);

// 今日の宿題
$today = date("Y-m-d");
$stmt = $pdo->prepare("
  SELECT * FROM study_logs
  WHERE user_id = ? AND DATE(started_at) = ?
  ORDER BY started_at DESC
");
$stmt->execute([$user_id, $today]);
$logs = $stmt->fetchAll(PDO::FETCH_ASSOC);

$current_page = 'home';

// 進捗バー%
$level = (int)($status['level'] ?? 1);
$exp   = (int)($status['exp']   ?? 0);
$progress_percent = max(0, min(100, (int)round($exp / max(1, requiredExp($level)) * 100)));
?>
<!DOCTYPE html>
<html lang="ja">
<head>
  <meta charset="UTF-8">
  <title>バトスタ ダッシュボード</title>
  <link rel="stylesheet" href="styles/style.css">
  <link rel="stylesheet" href="styles/dashboard.css">
</head>
<body>
<div class="container">
  <?php include 'includes/navbar.php'; ?>

  <main class="content">

    <?php if ($needsAvatar): ?>
      <section class="avatar_section">
        <div style="background:#fff7e6;border:2px dashed #e6d4ae;color:#6b4d22;padding:12px 14px;border-radius:12px;margin-bottom:16px;">
          まずはアバターを作成しましょう →
          <a href="avatar_create.php?first=1" style="font-weight:700;color:#2e7d32;text-decoration:underline;">アバター作成へ</a>
        </div>
      </section>
    <?php endif; ?>

    <section>
      <p>レベル：<?= htmlspecialchars((string)$level) ?>
         / 経験値：<?= htmlspecialchars((string)$exp) ?></p>
      <div class="progress-bar">
        <div class="progress" style="width: <?= $progress_percent ?>%;"></div>
      </div>
    </section>

    <section>
      <h2>現在のステータス</h2>
      <p>攻撃力：<?= htmlspecialchars((string)($status['attack'] ?? 0)) ?>
         / 防御力：<?= htmlspecialchars((string)($status['defense'] ?? 0)) ?></p>
    </section>

    <section class="avatar_section">
      <h2>あなたのアバター</h2>
      <?php
        if ($needsAvatar) {
          echo '<div style="opacity:.7">未作成です。「アバター作成へ」から設定してください。</div>';
        } else {
          // 同一キャンバス原寸の前提で、重ね順のみで描画
          echo renderAvatarFull($parts, $equipPaths);
        }
      ?>
    </section>

    <div class="dashboard-columns">
      <section class="hw">
        <h3>今日の宿題履歴（<?= count($logs) ?>件）</h3>
        <ul>
          <?php foreach ($logs as $log): ?>
            <li><?= htmlspecialchars($log['subject']) ?> / <?= htmlspecialchars($log['type']) ?>（<?= (int)$log['duration_minutes'] ?>分）</li>
          <?php endforeach; ?>
          <?php if (count($logs) === 0): ?>
            <li>まだ今日の宿題はありません</li>
          <?php endif; ?>
        </ul>
      </section>

      <section class="mt">
        <h3>所持素材</h3>
        <ul>
          <?php foreach ($materials as $m): ?>
            <li><?= htmlspecialchars($m['name']) ?> × <?= (int)$m['quantity'] ?></li>
          <?php endforeach; ?>
          <?php if (count($materials) === 0): ?>
            <li>素材はまだありません</li>
          <?php endif; ?>
        </ul>
      </section>
    </div>

  </main>
</div>
</body>
</html>
