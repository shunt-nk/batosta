<?php
session_start();
require 'includes/db.php';
require 'includes/functions.php';
if (!isset($_SESSION['user'])) { header('Location: login.php'); exit; }
$user_id = $_SESSION['user']['id'];

$slots = [
  'gender'     => '性別',        // UI上のカテゴリ。DBスロットではない
  'eye'        => '目',          // eye_left / eye_rightセット
  'nose'       => '鼻',
  'mouth'      => '口',
  'hair_front' => '髪（前）',
  'hair_back'  => '髪（後）',
];

// 現在のユーザー選択（あれば取得、なければ base01 を初期候補として埋める用）
function currentSelectedParts(PDO $pdo, int $uid): array {
  $st = $pdo->prepare("
    SELECT u.slot, c.image_path, c.key_name, c.gender_scope
    FROM user_avatar_parts u
    JOIN avatar_parts_catalog c ON u.part_id = c.id
    WHERE u.user_id=?
  ");
  $st->execute([$uid]);
  $res = [];
  foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $r) {
    $res[$r['slot']] = $r;
  }
  return $res;
}
$selected = currentSelectedParts($pdo, $user_id);

// パーツ一覧を取得（性別フィルタ付き取得関数）
function partsFor(PDO $pdo, string $slot, string $gender): array {
  // eye は left/right を別管理するため、slotを個別に呼ぶ
  $st = $pdo->prepare("
    SELECT id, slot, key_name, color, image_path, gender_scope
    FROM avatar_parts_catalog
    WHERE slot = ?
      AND (gender_scope='unisex' OR gender_scope=?)
    ORDER BY is_default DESC, key_name, color
  ");
  $st->execute([$slot, $gender]);
  return $st->fetchAll(PDO::FETCH_ASSOC);
}

// 初期性別（UI初期値）。DBに保存済みの body_base が male/female ならそれを採用
$initialGender = 'male';
if (!empty($selected['body_base']['gender_scope'])) {
  $g = $selected['body_base']['gender_scope'];
  if ($g === 'female') $initialGender = 'female';
}
?>
<!doctype html>
<html lang="ja">
<head>
<meta charset="utf-8">
<title>アバター作成</title>
<meta name="viewport" content="width=device-width, initial-scale=1">
<link rel="stylesheet" href="styles/style.css">
<style>
.page { display:grid; grid-template-columns: 80px 1fr 420px; gap:16px; }
.leftnav { display:flex; flex-direction:column; gap:8px; }
.leftnav button { width:72px; height:72px; border-radius:16px; background:#394176; color:#fff; border:none; cursor:pointer; }
.leftnav button.active { outline:3px solid #ff9800; }

.preview { background:#484e88; border-radius:20px; padding:16px; display:flex; align-items:center; justify-content:center; }
.sidebar { background:#2d325f; border-radius:20px; padding:16px; color:#fff; }

.parts-grid { display:grid; grid-template-columns: repeat(3, 1fr); gap:12px; max-height:60vh; overflow:auto; }
.card { background:#3a3f74; border-radius:12px; padding:8px; cursor:pointer; text-align:center; }
.card.selected { outline:3px solid #ffb300; }
.card img { width:100%; height:auto; }

.savebar { display:flex; justify-content:flex-end; padding-top:12px; }
.savebar button { background:#ffb300; color:#222; border:none; border-radius:12px; padding:10px 16px; font-weight:600; cursor:pointer; }
h2,h3 { color:#fff; margin:0 0 8px; }
.label { font-size:12px; opacity:.85; }
</style>
</head>
<body>
<div class="container">
  <h2>アバター作成</h2>
  <div class="page">

    <!-- 左：カテゴリ -->
    <nav class="leftnav" id="slotTabs">
      <button data-tab="gender"     class="active" title="性別">性別</button>
      <button data-tab="eye"        title="目">目</button>
      <button data-tab="nose"       title="鼻">鼻</button>
      <button data-tab="mouth"      title="口">口</button>
      <button data-tab="hair_front" title="髪前">前髪</button>
      <button data-tab="hair_back"  title="髪後">後髪</button>
    </nav>

    <!-- 中央：プレビュー -->
    <section class="preview">
      <div id="avatarPreview" class="avatar-container"></div>
    </section>

    <!-- 右：パーツ一覧 -->
    <aside class="sidebar">
      <h3 id="panelTitle">性別</h3>
      <div id="panelGender">
        <div style="display:flex; gap:8px; margin-bottom:12px">
          <label><input type="radio" name="gender" value="male"   checked> 男</label>
          <label><input type="radio" name="gender" value="female"> 女</label>
        </div>
        <p class="label">※ 性別を変えると body_base と一部パーツの候補が切り替わります</p>
      </div>

      <!-- パーツ一覧グリッド -->
      <div id="partsGrid" class="parts-grid" style="margin-top:8px"></div>

      <div class="savebar">
        <button id="saveBtn">この構成を保存</button>
      </div>
    </aside>

  </div>
</div>

<script>
// レイヤー順（6項目仕様）
const order = ['body_base','hair_back','eye_left','eye_right','nose','mouth','hair_front'];

// 現在の選択（imageだけ持つ。保存時にidへ置換）
const selected = {
  gender: '<?= $initialGender ?>',
  // 初期は「base01」を仮選択（未保存）。あなたの実画像に合わせて表示される想定
  body_base:  null,
  hair_front: null,
  hair_back:  null,
  eye_left:   null,
  eye_right:  null,
  nose:       null,
  mouth:      null
};

// PHPから初期候補（base01）をサーバーレンダで流し込む
// 性別変更に応じて右パネル（パーツ候補）をAjax無しでPHP生成でも良いが、
// ここでは最小化のためパネル切替時にサーバーサイドで描いた data-* を使う方式に。
</script>

<?php
// -------------------------
// 右パネルに必要な候補を server-side で用意 → data 属性で埋め込む
function listToJsData(array $rows): array {
  // id, slot, key_name, color, image_path, gender_scope
  return array_map(function($r){
    return [
      'id' => (int)$r['id'],
      'slot' => $r['slot'],
      'key' => $r['key_name'],
      'color' => $r['color'],
      'img' => $r['image_path'],
      'gender' => $r['gender_scope']
    ];
  }, $rows);
}
$maleSets = [
  'body_base'   => listToJsData(partsFor($pdo,'body_base','male')),
  'eye_left'    => listToJsData(partsFor($pdo,'eye_left','male')),
  'eye_right'   => listToJsData(partsFor($pdo,'eye_right','male')),
  'nose'        => listToJsData(partsFor($pdo,'nose','male')),
  'mouth'       => listToJsData(partsFor($pdo,'mouth','male')),
  'hair_front'  => listToJsData(partsFor($pdo,'hair_front','male')),
  'hair_back'   => listToJsData(partsFor($pdo,'hair_back','male')),
];
$femaleSets = [
  'body_base'   => listToJsData(partsFor($pdo,'body_base','female')),
  'eye_left'    => listToJsData(partsFor($pdo,'eye_left','female')),
  'eye_right'   => listToJsData(partsFor($pdo,'eye_right','female')),
  'nose'        => listToJsData(partsFor($pdo,'nose','female')),
  'mouth'       => listToJsData(partsFor($pdo,'mouth','female')),
  'hair_front'  => listToJsData(partsFor($pdo,'hair_front','female')),
  'hair_back'   => listToJsData(partsFor($pdo,'hair_back','female')),
];
?>

<script>
const maleSets   = <?= json_encode($maleSets,   JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE) ?>;
const femaleSets = <?= json_encode($femaleSets, JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE) ?>;

// 「base01」を初期選択（UI上で見せるだけ。保存はSave押下時）
function pickDefault(set) {
  const out = {};
  ['body_base','hair_front','hair_back','eye_left','eye_right','nose','mouth'].forEach(slot=>{
    const arr = set[slot] || [];
    const base = arr.find(x=>x.key==='base01') || arr[0];
    if (base) out[slot] = {id: base.id, img: base.img};
  });
  return out;
}

function applyInit() {
  const gender = selected.gender;
  const set = gender==='female' ? femaleSets : maleSets;
  const base = pickDefault(set);
  Object.assign(selected, base);
  renderPreview();
  // 左タブ初期：gender
  switchTab('gender');
  // パネル内容初期：gender（ラジオはPHP側でmaleにchecked）
  // 実パーツのGridは、タブ切替で描く
}
function renderPreview(){
  const wrap = document.getElementById('avatarPreview');
  wrap.innerHTML = '';
  order.forEach(slot=>{
    if (selected[slot]) {
      const img = document.createElement('img');
      img.className = 'avatar-layer slot-'+slot;
      img.src = selected[slot].img;
      img.alt = slot;
      wrap.appendChild(img);
    }
  });
}

// 右パネル：グリッドを描く
function drawGrid(slot){
  const grid = document.getElementById('partsGrid');
  grid.innerHTML = '';
  const set = (selected.gender==='female' ? femaleSets : maleSets)[slot] || [];
  set.forEach(item=>{
    const card = document.createElement('div');
    card.className = 'card';
    card.innerHTML = `<img src="${item.img}" alt=""><div class="label">${item.key}${item.color? ' / '+item.color:''}</div>`;
    if (selected[slot] && selected[slot].id == item.id) card.classList.add('selected');
    card.addEventListener('click', ()=>{
      // eye は左右同時に更新（同keyの left/right が揃っていればOK）
      if (slot==='eye_left' || slot==='eye_right') {
        const pair = (selected.gender==='female' ? femaleSets : maleSets);
        const left  = (pair['eye_left'] || []).find(x=>x.key===item.key && x.color===item.color) || item;
        const right = (pair['eye_right']|| []).find(x=>x.key===item.key && x.color===item.color) || item;
        selected['eye_left']  = {id:left.id,  img:left.img};
        selected['eye_right'] = {id:right.id, img:right.img};
        drawGrid('eye_left'); // 再描画して選択表示更新
      } else {
        selected[slot] = {id:item.id, img:item.img};
      }
      renderPreview();
      // 選択枠更新
      document.querySelectorAll('.card').forEach(c=>c.classList.remove('selected'));
      card.classList.add('selected');
    });
    grid.appendChild(card);
  });
}

// 左ナブ切替
function switchTab(tab){
  document.querySelectorAll('.leftnav button').forEach(b=>b.classList.remove('active'));
  document.querySelector(`.leftnav button[data-tab="${tab}"]`).classList.add('active');
  document.getElementById('panelTitle').textContent =
    (tab==='gender' ? '性別' :
    (tab==='eye' ? '目' :
    (tab==='nose' ? '鼻' :
    (tab==='mouth' ? '口' :
    (tab==='hair_front' ? '髪（前）' : '髪（後）')))));
  // 性別パネルの表示/非表示
  document.getElementById('panelGender').style.display = (tab==='gender') ? 'block' : 'none';
  // パーツグリッド描画
  if (tab !== 'gender') {
    const slot = (tab==='eye') ? 'eye_left' : tab; // eye は left ベースで候補表示
    drawGrid(slot);
  } else {
    document.getElementById('partsGrid').innerHTML = '';
  }
}

document.querySelectorAll('#slotTabs button').forEach(b=>{
  b.addEventListener('click', ()=> switchTab(b.dataset.tab));
});

// 性別変更
document.querySelectorAll('input[name="gender"]').forEach(r=>{
  if (r.value === '<?= $initialGender ?>') r.checked = true;
  r.addEventListener('change', ()=>{
    selected.gender = r.value;
    applyInit();               // 性別変えたら base01 から再構成
    switchTab('gender');       // タブはそのまま
  });
});

// 保存
document.getElementById('saveBtn').addEventListener('click', async ()=>{
  // 保存ペイロード： user_avatar_parts に書くのは DBスロットのみ
  const payload = [];
  ['body_base','hair_front','hair_back','eye_left','eye_right','nose','mouth'].forEach(slot=>{
    if (selected[slot]) payload.push({slot, part_id: selected[slot].id});
  });
  const res = await fetch('save_avatar.php', {
    method:'POST', headers:{'Content-Type':'application/json'},
    body: JSON.stringify(payload)
  });
  const ok = await res.json();
  if (ok.success) {
    alert('保存しました！');
    // 初回ならホームへ
    const params = new URLSearchParams(location.search);
    if (params.get('first')==='1') location.href='home.php';
  } else {
    alert('保存に失敗しました');
  }
});

applyInit();
</script>
</body>
</html>
