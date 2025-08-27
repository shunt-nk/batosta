<?php
// dashboard.php（DB保存そのまま表示／デフォ・保険ゼロ／手はhand_*の切替のみ）
declare(strict_types=1);
session_start();

require 'includes/db.php';
require 'includes/functions.php'; // hasAvatarBody, fetchSelectedPartsBySlots, fetchEquipPaths, renderAvatarFull, getAvatarStatusWithEquip, requiredExp

if (!isset($_SESSION['user'])) {
  header('Location: index.php');
  exit;
}

$user_id = (int)$_SESSION['user']['id'];

// 1) アバター作成済み？
$needsAvatar = !hasAvatarBody($pdo, $user_id);

if ($needsAvatar) {
  $parts = [];
  $equipPaths = [];
} else {
  // ← これ“だけ”でOK（重複取得はNG）
  list($parts, $equipPaths) = loadAvatarStacks($pdo, $user_id);

  // hand を最終決定（武器あり→hand_weapon / なし→hand_base）
  unset($parts['hand']);
  if (!empty($equipPaths['weapon']) && !empty($parts['hand_weapon'])) {
    $parts['hand'] = $parts['hand_weapon'];
  } elseif (!empty($parts['hand_base'])) {
    $parts['hand'] = $parts['hand_base'];
  }
}




// 5) ステータス（装備込み）
$status = getAvatarStatusWithEquip($pdo, $user_id);
$level  = (int)($status['level'] ?? 1);
$exp    = (int)($status['exp']   ?? 0);
$reqExp = (int)max(1, requiredExp($level));
$progress_percent = max(0, min(100, (int)round(($exp / $reqExp) * 100)));

// 6) 今日の宿題
$today = date("Y-m-d");
$stmt = $pdo->prepare("
  SELECT subject, type, duration_minutes, started_at
  FROM study_logs
  WHERE user_id = ? AND DATE(started_at) = ?
  ORDER BY started_at DESC
");
$stmt->execute([$user_id, $today]);
$logs = $stmt->fetchAll(PDO::FETCH_ASSOC);

// 7) 所持素材
$stmt = $pdo->prepare("
  SELECT m.name, COALESCE(um.quantity,0) AS quantity
  FROM user_materials um
  JOIN materials m ON um.material_id = m.id
  WHERE um.user_id = ?
  ORDER BY m.name
");
$stmt->execute([$user_id]);
$materials = $stmt->fetchAll(PDO::FETCH_ASSOC);

// 8) navbar 用
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
      <h2>ステータス</h2>
      <p>
        レベル：<?= htmlspecialchars((string)$level, ENT_QUOTES, 'UTF-8') ?>　
        経験値：<?= htmlspecialchars((string)$exp, ENT_QUOTES, 'UTF-8') ?>
        （次のレベルまで：<?= htmlspecialchars((string)max(0, $reqExp - $exp), ENT_QUOTES, 'UTF-8') ?>）
      </p>
      <div class="progress-bar" aria-label="経験値進捗">
        <div class="progress" style="width: <?= $progress_percent ?>%;"></div>
      </div>
      <p style="margin-top:8px;">
        攻撃力：<?= htmlspecialchars((string)($status['attack'] ?? 0), ENT_QUOTES, 'UTF-8') ?>　
        防御力：<?= htmlspecialchars((string)($status['defense'] ?? 0), ENT_QUOTES, 'UTF-8') ?>
      </p>
    </section>

    <section class="avatar_section">
      <h2>あなたのアバター</h2>
      <?php
        if ($needsAvatar) {
          echo '<div style="opacity:.7">未作成です。「アバター作成へ」から設定してください。</div>';
        } else {
          // ここが肝：DB保存そのまま（手は hand_* → hand を上で確定）で描画
          echo renderAvatarFull($parts, $equipPaths);
        }
      ?>
      <div style="margin-top:12px;">
        <a href="avatar_customize.php" class="btn" style="text-decoration:underline;">着せ替えに進む</a>
      </div>
    </section>

    <div class="dashboard-columns">
      <section class="hw">
        <h3>今日の宿題履歴（<?= (int)count($logs) ?>件）</h3>
        <ul>
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
      </section>

      <section class="mt">
        <h3>所持素材</h3>
        <ul>
          <?php if (!$materials): ?>
            <li>素材はまだありません</li>
          <?php else: ?>
            <?php foreach ($materials as $m): ?>
              <li><?= htmlspecialchars($m['name'], ENT_QUOTES, 'UTF-8') ?> × <?= (int)$m['quantity'] ?></li>
            <?php endforeach; ?>
          <?php endif; ?>
        </ul>
      </section>
    </div>

  </main>
</div>
</body>
</html>
