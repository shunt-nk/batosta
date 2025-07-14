<?php
session_start();
require 'includes/db.php';

if (!isset($_SESSION["user"]) || !isset($_SESSION["study"])) {
  header("Location: task_register.php");
  exit;
}

$user_id = $_SESSION["user"]["id"];
$subject = $_SESSION["study"]["subject"];
$type = $_SESSION["study"]["type"];
$duration = $_SESSION["study"]["duration"];
$started_at = $_SESSION["study"]["started_at"];
$ended_at = date("Y-m-d H:i:s");
$event_log = $_SESSION["study"]["event_log"];

// レアイベント追加
$rare_events = [];
if (rand(1, 10) === 1) $rare_events[] = "巨大な宝箱を発見した！";
if (rand(1, 20) === 1) $rare_events[] = "ダンジョンのボスを討伐した！";
$event_log = array_merge($event_log, $rare_events);

// 勉強記録保存
$stmt = $pdo->prepare("INSERT INTO study_logs (user_id, subject, type, duration_minutes, started_at, ended_at)
                       VALUES (?, ?, ?, ?, ?, ?)");
$stmt->execute([$user_id, $subject, $type, $duration, $started_at, $ended_at]);

// 素材取得
$stmt = $pdo->query("SELECT * FROM materials");
$materials = $stmt->fetchAll(PDO::FETCH_ASSOC);

// ランダムに3種選ぶ（重複なし）
$reward_items = [];
$indexes = array_rand($materials, 3);
foreach ((array)$indexes as $i) {
  $reward_items[] = $materials[$i];
}

// rare素材チェック（骨・魔石が含まれない場合追加）
$rare_names = ['水晶', '魔石'];
$has_rare = false;
foreach ($reward_items as $item) {
  if (in_array($item['name'], $rare_names)) {
    $has_rare = true;
    break;
  }
}
if (!$has_rare) {
  $rare_pool = array_filter($materials, fn($m) => in_array($m['name'], $rare_names));
  if (!empty($rare_pool)) {
    $reward_items[] = $rare_pool[array_rand($rare_pool)];
  }
}

// 報酬計算
$reward_factor = ceil($duration / 5); // 5分→1, 15分→3, 60分→12
$rewards = [];

foreach ($reward_items as $material) {
  $base = rand(2, 4);
  $amount = $base * $reward_factor;

  foreach ($event_log as $event) {
    if (str_contains($event, '宝箱')) $amount += rand(1, 2);
    if (str_contains($event, '罠')) $amount = max(1, $amount - rand(1, 2));
    if (str_contains($event, '巨大')) $amount += 5;
    if (str_contains($event, 'ボス')) $amount += 8;
  }

  $rewards[] = ['id' => $material['id'], 'name' => $material['name'], 'count' => $amount];

  // user_materials 更新
  $check = $pdo->prepare("SELECT * FROM user_materials WHERE user_id = ? AND material_id = ?");
  $check->execute([$user_id, $material['id']]);
  if ($row = $check->fetch()) {
    $update = $pdo->prepare("UPDATE user_materials SET quantity = quantity + ? WHERE id = ?");
    $update->execute([$amount, $row['id']]);
  } else {
    $insert = $pdo->prepare("INSERT INTO user_materials (user_id, material_id, quantity) VALUES (?, ?, ?)");
    $insert->execute([$user_id, $material['id'], $amount]);
  }
}

// イベントまとめ表示
$event_summary = [];
foreach ($event_log as $event) {
  $event_summary[$event] = ($event_summary[$event] ?? 0) + 1;
}

$event_display = [];
foreach ($event_summary as $event => $count) {
  if (str_contains($event, '宝箱') && !str_contains($event, '巨大')) {
    $event_display[] = "宝箱を{$count}個見つけた！";
  } elseif (str_contains($event, '罠')) {
    $event_display[] = "罠に{$count}回引っかかった…";
  } elseif (str_contains($event, '敵')) {
    $event_display[] = "敵と{$count}回遭遇した！";
  } elseif (str_contains($event, 'ボス')) {
    $event_display[] = "ボスを討伐した！";
  } elseif (str_contains($event, '巨大')) {
    $event_display[] = "巨大な宝箱を見つけた！";
  } else {
    $event_display[] = "{$event} × {$count}回";
  }
}

// セッションクリア
unset($_SESSION["study"]);
?>
<!DOCTYPE html>
<html lang="ja">
<head>
  <meta charset="UTF-8">
  <title>攻略完了！</title>
  <style>
    body {
      font-family: sans-serif;
      text-align: center;
      padding: 2rem;
      background: linear-gradient(to bottom, #222, #000);
      color: white;
    }
    .event-log, .reward-log {
      margin: 1rem 0;
      font-size: 1.2rem;
    }
    .reward {
      font-size: 1.5rem;
      margin-top: 2rem;
    }
    .hidden {
      display: none;
    }
    button {
      margin-top: 2rem;
      padding: 1rem 2rem;
      font-size: 1rem;
      background: white;
      color: #333;
      border: none;
      border-radius: 10px;
      cursor: pointer;
    }
  </style>
</head>
<body>

<h1>攻略結果</h1>
<p>勉強内容：<?= htmlspecialchars($subject) ?> / <?= htmlspecialchars($type) ?>（<?= $duration ?>分）</p>

<div id="eventSection">
  <h2>ダンジョンでの出来事</h2>
  <div id="eventLogArea"></div>
</div>

<div id="rewardSection" class="hidden">
  <h2>報酬素材</h2>
  <ul>
    <?php foreach ($rewards as $reward): ?>
      <li class="reward"><?= htmlspecialchars($reward['name']) ?> × <?= $reward['count'] ?></li>
    <?php endforeach; ?>
  </ul>

  <button onclick="location.href='dashboard.php'">ホームへ戻る</button>
  <button onclick="location.href='equipment_create.php'">装備を作る</button>
</div>

<script>
  const events = <?= json_encode($event_display, JSON_UNESCAPED_UNICODE) ?>;
  const eventArea = document.getElementById("eventLogArea");
  const rewardSection = document.getElementById("rewardSection");

  let i = 0;
  function showNextEvent() {
    if (i < events.length) {
      const p = document.createElement("p");
      p.className = "event-log";
      p.innerText = `・${events[i]}`;
      eventArea.appendChild(p);
      i++;
      setTimeout(showNextEvent, 1000);
    } else {
      rewardSection.classList.remove("hidden");
    }
  }
  showNextEvent();
</script>

</body>
</html>
