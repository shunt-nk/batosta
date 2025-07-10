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

// ① study_logsに保存
$stmt = $pdo->prepare("INSERT INTO study_logs (user_id, subject, type, duration_minutes, started_at, ended_at)
                       VALUES (?, ?, ?, ?, ?, ?)");
$stmt->execute([$user_id, $subject, $type, $duration, $started_at, $ended_at]);

// ② 素材報酬をランダムに生成（1〜3種類、各1〜5個）
$stmt = $pdo->query("SELECT * FROM materials");
$materials = $stmt->fetchAll(PDO::FETCH_ASSOC);
shuffle($materials);
$reward_items = array_slice($materials, 0, rand(1, 3)); // ランダムに1〜3種

$rewards = [];
foreach ($reward_items as $material) {
  $count = rand(1, 5);
  $rewards[] = ['name' => $material['name'], 'count' => $count];

  // ③ user_materials に保存（すでにあれば加算）
  $check = $pdo->prepare("SELECT * FROM user_materials WHERE user_id = ? AND material_id = ?");
  $check->execute([$user_id, $material['id']]);
  if ($row = $check->fetch()) {
    $update = $pdo->prepare("UPDATE user_materials SET quantity = quantity + ? WHERE id = ?");
    $update->execute([$count, $row['id']]);
  } else {
    $insert = $pdo->prepare("INSERT INTO user_materials (user_id, material_id, quantity) VALUES (?, ?, ?)");
    $insert->execute([$user_id, $material['id'], $count]);
  }
}

// セッションをクリア
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
      <li class="reward"><?= $reward['name'] ?> × <?= $reward['count'] ?></li>
    <?php endforeach; ?>
  </ul>

  <button onclick="location.href='dashboard.php'">ホームへ戻る</button>
  <button onclick="location.href='equipment_create.php'">装備を作る</button>
</div>

<script>
  const events = <?= json_encode($event_log, JSON_UNESCAPED_UNICODE) ?>;
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
