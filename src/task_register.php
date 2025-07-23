<?php
session_start();
require 'includes/task_data.php'; // 教科・種類・時間リストを取得（配列）

// セッションに動的に追加した種類/時間があれば取得
$types = $_SESSION['types'] ?? $default_types;
$times = $_SESSION['times'] ?? $default_times;

$subject_classes = [
  '算数' => 'math',
  '国語' => 'japanese',
  '英語' => 'english',
  '理科' => 'science',
  '社会' => 'social',
  'その他' => 'other',
];


$current_page = 'task'; // task_register.phpでこれを定義
?>
<link rel="stylesheet" href="styles/style.css">
<link rel="stylesheet" href="styles/task_register.css">
<script src="js/task_register.js"></script>
<body>
  <div class="container">
    <?php include 'includes/navbar.php'; ?>
    <main class="content">
      
      <h1>宿題の登録</h1>
      <form method="POST" action="task_timer.php" id="taskForm">
<!-- 教科セクション -->
<div class="s-t_container">
  <div class="section">
    <div class="subject_section">
      <h2>教科</h2>
      <div class="s-btn_grid">
        <?php foreach ($subjects as $subject): ?>
          <?php $icon_file = strtolower($subject_classes[$subject] ?? 'default') . '.svg'; ?>
          <button 
            type="button" 
            class="subject-btn <?= $subject_classes[$subject] ?? '' ?>" 
            onclick="select('subject', '<?= $subject ?>')">
            <img src="assets/icons/<?= $icon_file ?>" alt="<?= $subject ?>" class="subject-icon">
            <?= $subject ?>
          </button>
        <?php endforeach; ?>
      </div>
    </div>
  </div>
  <!-- 種類セクション -->
  <div class="section">
    <div class="type_section">
      <h2>種類</h2>
      <div id="types" class="t-btn_grid">
        <?php foreach ($types as $type): ?>
          <button type="button" class="type-btn" onclick="select('type', '<?= $type ?>')"><?= $type ?></button>
        <?php endforeach; ?>
      </div>
      <div class="add_container">
        <input type="text" id="newType" class="add_text" placeholder="新しく追加する">
        <button type="button" onclick="addItem('type')" class="add_btn">追加</button>
      </div>
    </div>
    </div>
</div>

<!-- 時間セクション -->
<div class="section">
  <div class="p-t_container">
    <!-- 選択内容 -->
    <div class="preview-area">
      <div class="preview-wrap">
        <h2>教科</h2>
        <div id="selected-subject"></div>
      </div>
      <div class="preview-wrap">
        <h2>種類</h2>
        <div id="selected-type"></div>
      </div>
    </div>
    <div class="time_container">
      <h2>時間</h2>
      <div class="time_content">
        <div id="times">
          <?php foreach ($times as $time): ?>
            <button type="button" class="time_btn" onclick="select('time', '<?= $time ?>')"><?= $time ?></button>
          <?php endforeach; ?>
        </div>
      </div>  
    </div>

  </div>
</div>


<!-- 宿題を始める -->
<button type="button" onclick="openModal()" class="start_btn">
  宿題を始める
</button>
</form>
      
      <div id="confirmModal">
      <div>
        <h3>この内容で宿題を始めますか？</h3>
        <p id="confirmContent"></p>
        <form method="POST" action="task_timer.php">
          <input type="hidden" name="subject">
          <input type="hidden" name="type">
          <input type="hidden" name="time">
          <button type="submit" id="modal_start-btn">開始する</button>
          <button type="button" onclick="closeModal()" id="modal_cancel-btn">キャンセル</button>
        </form>
      </div>
    </div>
    
</main>
</div>
</body>