<?php
session_start();
require 'includes/task_data.php'; // 教科・種類・時間リストを取得（配列）

// セッションに動的に追加した種類/時間があれば取得
$types = $_SESSION['types'] ?? $default_types;
$times = $_SESSION['times'] ?? $default_times;

$current_page = 'task'; // task_register.phpでこれを定義
?>
<style>
body {
  margin: 0;
  display: flex;
  font-family: sans-serif;
  background: #5D73A9;
  color: #fff;
}

.container {
  display: flex;
  min-height: 100vh;
  width: 100%;
}

.content {
  flex: 1;
  padding: 2rem;
  display: flex;
  flex-direction: column;
  gap: 2rem;
}

h2 {
  font-size: 1.8rem;
  margin-bottom: 1rem;
}

form {
  display: flex;
  flex-direction: column;
  gap: 1.5rem;
}

.section {
  background: #384E9A;
  padding: 1rem;
  border-radius: 16px;
}

h3 {
  margin: 0 0 1rem;
}

button {
  padding: 0.7rem 1.2rem;
  font-size: 1rem;
  border: none;
  border-radius: 10px;
  margin: 0.3rem;
  cursor: pointer;
  transition: background 0.2s;
}

button[type="button"]:hover {
  background-color: #fff2;
}

#types button, #times button {
  background: white;
  color: #DB9963;
  font-weight: bold;
}

#subjectButtons button {
  width: 120px;
  height: 60px;
  font-size: 1rem;
  color: white;
  border: none;
  border-radius: 12px;
  margin: 0.5rem;
}

#subjectButtons {
  display: flex;
  flex-wrap: wrap;
  gap: 0.5rem;
  justify-content: center;
}

.preview-area {
  display: flex;
  gap: 1rem;
  background: #2c3e75;
  padding: 1rem;
  border-radius: 10px;
  justify-content: center;
  align-items: center;
  color: #ccc;
}

#confirmModal {
  display: none;
  position: fixed;
  top: 0; left: 0;
  width: 100%;
  height: 100%;
  background: rgba(0,0,0,0.5);
  justify-content: center;
  align-items: center;
}

#confirmModal > div {
  background: #484E88;
  padding: 20px;
  border-radius: 10px;
  width: 300px;
  text-align: center;
}

</style>

<body>
  <div class="container">
    <?php include 'includes/navbar.php'; ?>
    <main class="content">
      
      <h2>宿題の登録</h2>
      <form method="POST" action="task_timer.php" id="taskForm">
<!-- 教科セクション -->
<div id="subjectButtons" class="section">
  <h3>教科</h3>
  <?php foreach ($subjects as $subject): ?>
    <button type="button" onclick="select('subject', '<?= $subject ?>')"><?= $subject ?></button>
  <?php endforeach; ?>
</div>

<!-- 種類セクション -->
<div class="section">
  <h3>種類</h3>
  <div id="types">
    <?php foreach ($types as $type): ?>
      <button type="button" onclick="select('type', '<?= $type ?>')"><?= $type ?></button>
    <?php endforeach; ?>
  </div>
  <input type="text" id="newType" placeholder="新しく追加する種類">
  <button type="button" onclick="addItem('type')">追加</button>
</div>

<!-- 時間セクション -->
<div class="section">
  <h3>時間</h3>
  <div id="times">
    <?php foreach ($times as $time): ?>
      <button type="button" onclick="select('time', '<?= $time ?>')"><?= $time ?></button>
    <?php endforeach; ?>
  </div>
  <input type="text" id="newTime" placeholder="新しく追加する時間（分）">
  <button type="button" onclick="addItem('time')">追加</button>
</div>

<!-- 選択内容 -->
<div class="preview-area">
  <span>教科：<strong id="selected-subject">-</strong></span>
  <span>種類：<strong id="selected-type">-</strong></span>
  <span>時間：<strong id="selected-time">-</strong></span>
</div>

<!-- 宿題を始める -->
<button type="button" onclick="openModal()" style="background:#384E9A; color:#fff; font-size:1.2rem;">
  宿題を始める
</button>
</form>
      
      <div id="confirmModal" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%;
        background:rgba(0,0,0,0.5); justify-content:center; align-items:center;">
      <div style="background:#484E88; padding:20px; border-radius:10px; width:300px; text-align:center;">
        <h3>この内容で宿題を始めますか？</h3>
        <p id="confirmContent"></p>
        <form method="POST" action="task_timer.php">
          <input type="hidden" name="subject">
          <input type="hidden" name="type">
          <input type="hidden" name="time">
          <button type="submit">開始する</button>
          <button type="button" onclick="closeModal()">キャンセル</button>
        </form>
      </div>
    </div>
    
</main>
</div>
</body>


<script>
  let selected = { subject: '', type: '', time: '' };

  function select(key, value) {
    selected[key] = value;
    document.querySelector(`input[name="${key}"]`).value = value;
    updatePreview();
  }
  function addItem(type) {
    const input = document.getElementById(type === 'type' ? 'newType' : 'newTime');
    const value = input.value.trim();
    if (!value) return;

    const container = document.getElementById(type + 's');
    const button = document.createElement('button');
    button.innerText = value;
    button.type = 'button';
    button.onclick = () => select(type, value);
    container.appendChild(button);

    input.value = '';
  }

  function updatePreview() {
  document.getElementById('selected-subject').innerText = selected.subject || '-';
  document.getElementById('selected-type').innerText = selected.type || '-';
  document.getElementById('selected-time').innerText = selected.time || '-';
}

  // モーダル表示
  function openModal() {
  if (!selected.subject || !selected.type || !selected.time) {
    alert("すべての項目を選んでください");
    return;
  }

  const modalForm = document.querySelector('#confirmModal form');
    modalForm.querySelector('input[name="subject"]').value = selected.subject;
    modalForm.querySelector('input[name="type"]').value = selected.type;
    modalForm.querySelector('input[name="time"]').value = selected.time;

    document.getElementById('confirmContent').innerText =
      `教科：${selected.subject} / 種類：${selected.type} / 時間：${selected.time}`;
    document.getElementById('confirmModal').style.display = "flex";
  }

  function closeModal() {
    document.getElementById('confirmModal').style.display = "none";
  }
</script>
