<?php
session_start();
require 'includes/task_data.php'; // 教科・種類・時間リストを取得（配列）

// セッションに動的に追加した種類/時間があれば取得
$types = $_SESSION['types'] ?? $default_types;
$times = $_SESSION['times'] ?? $default_times;
?>
<style>
  body {
    margin: 0;
    display: flex;
    font-family: sans-serif;
    background: #f4f4f4;
  }

  .container {
    display: flex;
    min-height: 100vh;
  }


  .content {
    flex: 1;
    padding: 2rem;
    background: #f4f4f4;
  }

</style>

<body>
  <div class="container">
    <?php include 'includes/navbar.php'; ?>
    <main class="content">
      
      <h2>宿題の登録</h2>
      <form method="POST" action="task_timer.php" id="taskForm">
        <h3>教科</h3>
        <?php foreach ($subjects as $subject): ?>
          <button type="button" onclick="select('subject', '<?= $subject ?>')"><?= $subject ?></button>
          <?php endforeach; ?>
          
          <h3>種類</h3>
          <div id="types">
            <?php foreach ($types as $type): ?>
              <button type="button" onclick="select('type', '<?= $type ?>')"><?= $type ?></button>
              <?php endforeach; ?>
            </div>
            <input type="text" id="newType" placeholder="新しく追加する種類">
            <button type="button" onclick="addItem('type')">追加</button>
            
            <h3>時間</h3>
            <div id="times">
              <?php foreach ($times as $time): ?>
                <button type="button" onclick="select('time', '<?= $time ?>')"><?= $time ?></button>
                <?php endforeach; ?>
              </div>
              <input type="text" id="newTime" placeholder="新しく追加する時間（分）">
              <button type="button" onclick="addItem('time')">追加</button>
              
              <h3>選択内容</h3>
              <p id="preview">教科：- / 種類：- / 時間：-</p>
    
      <input type="hidden" name="subject">
      <input type="hidden" name="type">
      <input type="hidden" name="time">
      
      <!-- 旧: <button type="submit">宿題を始める</button> -->
      <button type="button" onclick="openModal()">宿題を始める</button>
      
      <div id="confirmModal" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%;
        background:rgba(0,0,0,0.5); justify-content:center; align-items:center;">
      <div style="background:#fff; padding:20px; border-radius:10px; width:300px; text-align:center;">
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
    
  </form>
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

  function updatePreview() {
    document.getElementById('preview').innerText =
      `教科：${selected.subject || '-'} / 種類：${selected.type || '-'} / 時間：${selected.time || '-'}`;
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

  // モーダル表示
  function openModal() {
    if (!selected.subject || !selected.type || !selected.time) {
      alert("すべての項目を選んでください");
      return;
    }

    document.querySelector('#confirmModal input[name="subject"]').value = selected.subject;
    document.querySelector('#confirmModal input[name="type"]').value = selected.type;
    document.querySelector('#confirmModal input[name="time"]').value = selected.time;

    document.getElementById('confirmContent').innerText =
      `教科：${selected.subject} / 種類：${selected.type} / 時間：${selected.time}`;
    document.getElementById('confirmModal').style.display = "flex";
  }

  function closeModal() {
    document.getElementById('confirmModal').style.display = "none";
  }
</script>
