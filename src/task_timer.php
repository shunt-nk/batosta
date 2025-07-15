<?php
session_start();
require 'includes/db.php';

if (!isset($_SESSION['user']) || !isset($_POST['subject'])) {
  header("Location: task_register.php");
  exit;
}

$user_id = $_SESSION['user']['id'];
$subject = $_POST['subject'];
$type = $_POST['type'];
$time_raw = $_POST['time'];

$minutes = ($time_raw === '1時間') ? 60 : (int)str_replace('分', '', $time_raw);

// ランダムイベント生成
$event_pool = [
  "宝箱を見つけた！", "罠にかかった…", "分かれ道で迷った", "レアモンスターが現れた！",
  "回復の泉を見つけた！", "特に何も起こらなかった", "古い本を発見！", "鍵付きの扉に遭遇"
];

$event_log = [];
for ($i = 0; $i < $minutes; $i++) {
  $event_log[] = $event_pool[array_rand($event_pool)];
}

$_SESSION['study'] = [
  'subject' => $subject,
  'type' => $type,
  'duration' => $minutes,
  'started_at' => date('Y-m-d H:i:s'),
  'event_log' => $event_log
];
?>

<!DOCTYPE html>
<html lang="ja">
<head>
  <meta charset="UTF-8">
  <title>ダンジョン攻略中</title>
  <style>
    body {
      margin: 0;
      font-family: sans-serif;
      color: white;
      text-align: center;
      background-image: url('assets/dungeon_bg.png');
      background-size: cover;
      background-position: center;
    }
    #timer {
      font-size: 2.5rem;
      margin-top: 30vh;
    }
    #status {
      font-size: 1.5rem;
      margin-top: 1rem;
    }
    #buttons {
      margin-top: 2rem;
    }
    button {
      padding: 1rem 2rem;
      font-size: 1.2rem;
      margin: 0.5rem;
      border-radius: 10px;
      border: none;
      background: #ffffffcc;
      color: #333;
      cursor: pointer;
    }
    button:hover {
      background: #fff;
    }
  </style>
</head>
<body>
  <h2>ダンジョン攻略中</h2>
  <div id="timer"><?= $minutes ?>:00</div>
  <div id="status">▶ タイマーを開始してください</div>
  <div id="buttons">
    <button onclick="startTimer()">▶ 開始</button>
    <button onclick="stopTimer()">⏹ 中断</button>
  </div>

  <script>
    let duration = <?= $minutes ?> * 60;
    let remaining = duration;
    let timer = null;
    let started = false;

    const statusTexts = [
      "罠に注意して進んでいる…",
      "宝箱を探している…",
      "モンスターを警戒中…",
      "道を選んでいる…",
      "アイテムを探している…",
      "地図を確認している…",
      "静かに歩いている…"
    ];

    function randomStatus() {
      const idx = Math.floor(Math.random() * statusTexts.length);
      document.getElementById("status").innerText = statusTexts[idx];
    }

    function startTimer() {
      if (started) return;
      started = true;

      timer = setInterval(() => {
        if (remaining <= 0) {
          clearInterval(timer);
          window.location.href = "task_result.php";
        } else {
          remaining--;
          const min = Math.floor(remaining / 60);
          const sec = String(remaining % 60).padStart(2, '0');
          document.getElementById("timer").innerText = `${min}:${sec}`;

          if (remaining % 30 === 0) randomStatus();
        }
      }, 1000);

      document.getElementById("status").innerText = "攻略中…";
    }

    function stopTimer() {
      if (timer) clearInterval(timer);
      alert("タイマーを中断しました。最初からやり直してください。");
      window.location.href = "task_register.php";
    }
  </script>
</body>
</html>
