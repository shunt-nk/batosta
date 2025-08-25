<?php
// avatar_create.php
declare(strict_types=1);
session_start();
require 'includes/db.php';
require 'includes/functions.php'; // partOptionsCompat, fetchSelectedPartsBySlots, fetchEquipPaths など
if (!isset($_SESSION['user'])) { header('Location: index.php'); exit; }
$user_id = (int)$_SESSION['user']['id'];

/* ---------------- パーツ候補の取得 ---------------- */
// 既存選択（保存済みがあれば拾う）
$selStmt = $pdo->prepare("
  SELECT u.slot, u.part_id, c.image_path, c.key_name
  FROM user_avatar_parts u
  JOIN avatar_parts_catalog c ON u.part_id=c.id
  WHERE u.user_id=? AND u.slot IN ('body','hair','eyes','mouth')
");
$selStmt->execute([$user_id]);
$current = [];
foreach ($selStmt->fetchAll(PDO::FETCH_ASSOC) as $r) { $current[$r['slot']] = $r; }

// 初期性別推定（body の画像名から簡易判定。無ければ male）
$initialGender = 'male';
if (!empty($current['body']['image_path'])) {
  $p = $current['body']['image_path'];
  if (strpos($p, 'female') !== false || strpos($p, '_f') !== false) $initialGender = 'female';
}

/* partOptionsCompat は functions.php 側の互換ヘルパ想定（無ければ下で定義） */
if (!function_exists('partOptionsCompat')) {
  function partOptionsCompat(PDO $pdo, string $slot, string $gender): array {
    $st = $pdo->prepare("
      SELECT id, slot, key_name, color, image_path, gender_scope, is_default
      FROM avatar_parts_catalog
      WHERE slot = ?
        AND (gender_scope='unisex' OR gender_scope=? OR gender_scope IS NULL OR gender_scope='')
      ORDER BY COALESCE(is_default,0) DESC, key_name, color, id
    ");
    $st->execute([$slot, $gender]);
    return $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
  }
}

$maleSets = [
  'body'  => partOptionsCompat($pdo,'body','male'),
  'hair'  => partOptionsCompat($pdo,'hair','male'),
  'eyes'  => partOptionsCompat($pdo,'eyes','male'),
  'mouth' => partOptionsCompat($pdo,'mouth','male'),
];
$femaleSets = [
  'body'  => partOptionsCompat($pdo,'body','female'),
  'hair'  => partOptionsCompat($pdo,'hair','female'),
  'eyes'  => partOptionsCompat($pdo,'eyes','female'),
  'mouth' => partOptionsCompat($pdo,'mouth','female'),
];

/* ---------------- 手（hand）の用意 ---------------- */
// user_avatar_parts から取得（未保存でもプレビューしたいので catalog で補完）
$handParts = fetchSelectedPartsBySlots($pdo, $user_id, ['hand_base','hand_weapon']);

if (empty($handParts['hand_base'])) {
  $st = $pdo->prepare("SELECT image_path FROM avatar_parts_catalog WHERE slot='hand_base' ORDER BY COALESCE(is_default,0) DESC, id ASC LIMIT 1");
  $st->execute(); $handParts['hand_base'] = $st->fetchColumn() ?: null;
}
if (empty($handParts['hand_weapon'])) {
  $st = $pdo->prepare("SELECT image_path FROM avatar_parts_catalog WHERE slot='hand_weapon' ORDER BY COALESCE(is_default,0) DESC, id ASC LIMIT 1");
  $st->execute(); $handParts['hand_weapon'] = $st->fetchColumn() ?: null;
}

/* ---------------- 初期服（outfit）を性別別に取得 ---------------- */
// female に male が含まれて誤マッチしないよう REGEXP で厳密化
function findInitialOutfit(PDO $pdo, string $gender): ?string {
  // 単語境界的に male / female をマッチ（_ や / の直後、_ or . or 終端の直前）
  $needle = ($gender === 'female')
    ? "(^|[_/])female([_.]|$)"
    : "(^|[_/])male([_.]|$)";

  // 1) is_initial=1 かつ 厳密な gender マッチ
  $st = $pdo->prepare("
    SELECT image_path
    FROM equipments
    WHERE slot='outfit' AND is_initial=1
      AND image_path REGEXP ?
    ORDER BY id ASC
    LIMIT 1
  ");
  $st->execute([$needle]);
  $p = $st->fetchColumn();
  if ($p) return (string)$p;

  // 2) is_initial=1 のどれか
  $st = $pdo->query("
    SELECT image_path
    FROM equipments
    WHERE slot='outfit' AND is_initial=1
    ORDER BY id ASC
    LIMIT 1
  ");
  $p = $st->fetchColumn();
  if ($p) return (string)$p;

  // 3) 最後のフォールバック（base01 っぽいもの）
  $st = $pdo->query("
    SELECT image_path
    FROM equipments
    WHERE slot='outfit' AND image_path REGEXP 'outfit.*base(01)?'
    ORDER BY id ASC
    LIMIT 1
  ");
  $p = $st->fetchColumn();
  return $p ? (string)$p : null;
}
$initialOutfits = [
  'male'   => findInitialOutfit($pdo, 'male'),
  'female' => findInitialOutfit($pdo, 'female'),
];

/* ---------------- 既に装備がある場合のプレビュー用（任意） ---------------- */
$equipPaths = fetchEquipPaths($pdo, $user_id); // ['outfit'=>'...', 'weapon'=>'...'] など
?>
<!doctype html>
<html lang="ja">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>アバター作成</title>
<link rel="stylesheet" href="styles/style.css">
<link rel="stylesheet" href="styles/avatar_create.css">
</head>
<body>
<div class="container">
  <h2>アバター作成</h2>
  <div class="layout">
    <!-- 左：4タブ -->
    <nav class="leftnav" id="tabs">
      <button data-tab="gender" class="active" title="性別">性別</button>
      <button data-tab="eyes"   title="目">目</button>
      <button data-tab="mouth"  title="口">口</button>
      <button data-tab="hair"   title="髪">髪</button>
    </nav>

    <!-- 中央：プレビュー -->
    <section class="preview">
      <div id="avatar" class="avatar-container"></div>
    </section>

    <!-- 右：候補 -->
    <aside class="sidebar">
      <div class="panel-head">
        <h3 id="panelTitle" style="margin:0">性別</h3>
        <div class="tab-badges">
          <div class="badge">プリセット</div>
          <div class="badge">カスタム</div>
        </div>
      </div>

      <!-- 性別 -->
      <div id="panelGender">
        <label><input type="radio" name="gender" value="male"   <?= $initialGender==='male'?'checked':''; ?>> 男</label>
        <label><input type="radio" name="gender" value="female" <?= $initialGender==='female'?'checked':''; ?>> 女</label>
      </div>

      <!-- パーツ一覧 -->
      <div id="parts" class="parts" style="margin-top:8px"></div>

      <!-- 保存 -->
      <div class="savebar"><button id="saveBtn">完成する</button></div>
    </aside>
  </div>
</div>

<script>
// -------------------- 初期状態 --------------------
const state = {
  gender: "<?= htmlspecialchars($initialGender, ENT_QUOTES, 'UTF-8') ?>",
  body:  <?= !empty($current['body'])  ? json_encode(['id'=>$current['body']['part_id'],'img'=>$current['body']['image_path']], JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE)  : 'null' ?>,
  hair:  <?= !empty($current['hair'])  ? json_encode(['id'=>$current['hair']['part_id'],'img'=>$current['hair']['image_path']], JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE)  : 'null' ?>,
  eyes:  <?= !empty($current['eyes'])  ? json_encode(['id'=>$current['eyes']['part_id'],'img'=>$current['eyes']['image_path']], JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE)  : 'null' ?>,
  mouth: <?= !empty($current['mouth']) ? json_encode(['id'=>$current['mouth']['part_id'],'img'=>$current['mouth']['image_path']], JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE) : 'null' ?>,
};

// パーツ候補（サーバ埋め込み）
const male   = <?= json_encode($maleSets,   JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE) ?>;
const female = <?= json_encode($femaleSets, JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE) ?>;

// 初期服（性別別）
const initialOutfit = <?= json_encode($initialOutfits, JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE) ?>;

// 既装備（ある場合のみ読み込む）— 作成画面では性別の初期服を優先表示したいので後で上書き
const equip = <?= json_encode($equipPaths, JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE) ?> || {};
equip.outfit = initialOutfit[state.gender] || null;

// 手（単手仕様）
const hand = <?= json_encode([
  'base'   => $handParts['hand_base']   ?? null,
  'weapon' => $handParts['hand_weapon'] ?? null,
], JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE) ?>;

// 画像パスの正規化
function normPath(p){
  if(!p) return null;
  if (p.startsWith('/assets/') || p.startsWith('http://') || p.startsWith('https://')) return p;
  if (p.startsWith('assets/')) return '/' + p;
  return '/assets/avatars/' + p.replace(/^\/+/, '');
}

// key_name がないDBでも動く：一致しなければ null
function pickByKey(arr, key){
  if (!Array.isArray(arr)) return null;
  return arr.find(x => (x && typeof x.key_name === 'string' && x.key_name === key)) || null;
}

// 性別デフォルト（男は eyes=base03、それ以外 base01）
const genderDefaults = {
  female: { body:'base01', eyes:'base01', hair:'base01', mouth:'base01' },
  male:   { body:'base01', eyes:'base03', hair:'base01', mouth:'base01' }
};

// 既存選択が無いスロットを、その性別のデフォルトで埋める
function ensureDefaults(set, gender){
  const wants = genderDefaults[gender] || genderDefaults.female;
  ['body','hair','eyes','mouth'].forEach(slot=>{
    if (!state[slot]) {
      let pick = null;
      const arr = set[slot] || [];
      if (arr.length) {
        pick = pickByKey(arr, wants[slot]) || arr[0]; // なければ先頭（is_default優先）
      }
      if (pick) state[slot] = { id: pick.id, img: pick.image_path };
    }
  });
}

// 現在の性別セット
function currentSet(){ return state.gender==='female' ? female : male; }

// 右パネル：カード一覧
function drawGrid(slot){
  const set = currentSet()[slot] || [];
  const el = document.getElementById('parts');
  el.innerHTML = '';

  if (set.length === 0) {
    el.innerHTML = '<div style="grid-column:1/-1;opacity:.8">候補がありません</div>';
    return;
  }
  set.forEach(item=>{
    const label = (item.key_name || 'base') + (item.color ? ' / ' + item.color : '');
    const card = document.createElement('div');
    card.className = 'card';
    card.innerHTML = `
      <img src="${normPath(item.image_path)}" alt="">
      <div class="label">${label}</div>
    `;
    if (state[slot] && state[slot].id == item.id) card.classList.add('selected');
    card.addEventListener('click', ()=>{
      state[slot] = { id:item.id, img:item.image_path };
      render();
      document.querySelectorAll('.card').forEach(c=>c.classList.remove('selected'));
      card.classList.add('selected');
    });
    el.appendChild(card);
  });
}

// 左タブ切替
function switchTab(tab){
  document.querySelectorAll('#tabs button').forEach(b=>b.classList.remove('active'));
  const btn = document.querySelector(`#tabs button[data-tab="${tab}"]`);
  if (btn) btn.classList.add('active');

  document.getElementById('panelTitle').textContent =
    (tab==='gender'?'性別': tab==='eyes'?'目': tab==='mouth'?'口':'髪');

  document.getElementById('panelGender').style.display = (tab==='gender')?'block':'none';
  document.getElementById('parts').style.display       = (tab==='gender')?'none':'grid';

  if (tab!=='gender') drawGrid(tab);
}

// レンダリング（服と手も重ねる）
function render(){
  const wrap = document.getElementById('avatar');
  wrap.innerHTML = '';

  ['body','hair','eyes','mouth'].forEach(slot=>{
    if(state[slot]){
      const img = document.createElement('img');
      img.className = 'avatar-layer slot-'+slot;
      img.src = normPath(state[slot].img);
      img.alt = slot;
      wrap.appendChild(img);
    }
  });

  // outfit：equip.outfit を最優先（性別切替時に上書き済み）→ fallback で initialOutfit
  const outfitPath = equip.outfit || (initialOutfit[state.gender] || null);
  if (outfitPath) {
    const img = document.createElement('img');
    img.className = 'avatar-layer slot-outfit';
    img.src = normPath(outfitPath);
    img.alt = 'outfit';
    wrap.appendChild(img);
  }

  // weapon（作成画面では触らないが、もし所持で見せたいなら残す）
  if (equip.weapon) {
    const img = document.createElement('img');
    img.className = 'avatar-layer slot-weapon';
    img.src = normPath(equip.weapon);
    img.alt = 'weapon';
    wrap.appendChild(img);
  }

  // hand（単手仕様：weapon があれば hand_weapon、なければ hand_base）
  const handSrc = equip.weapon ? (hand.weapon || hand.base) : (hand.base || hand.weapon);
  if (handSrc) {
    const img = document.createElement('img');
    img.className = 'avatar-layer slot-hand';
    img.src = normPath(handSrc);
    img.alt = 'hand';
    wrap.appendChild(img);
  }
}

// タブイベント
document.querySelectorAll('#tabs button').forEach(b=>{
  b.addEventListener('click', ()=> switchTab(b.dataset.tab));
});

// 性別ラジオ：切り替え時に outfit も性別に合わせて上書き
document.querySelectorAll('input[name="gender"]').forEach(r=>{
  r.addEventListener('change', ()=>{
    state.gender = r.value;
    state.body = state.hair = state.eyes = state.mouth = null;
    equip.outfit = initialOutfit[state.gender] || null; // ←性別に応じて初期服を切替（誤マッチなし）
    ensureDefaults(currentSet(), state.gender);
    render();
    switchTab('gender');
  });
});

// 初期化：既存選択がないスロットを性別デフォルトで埋める → 表示
ensureDefaults(currentSet(), state.gender);
render();
switchTab('gender');

// 保存
document.getElementById('saveBtn').addEventListener('click', async ()=>{
  const payload=[];
  ['body','hair','eyes','mouth'].forEach(s=>{
    if(state[s]) payload.push({slot:s, part_id:state[s].id});
  });
  const res = await fetch('save_avatar.php',{
    method:'POST',
    headers:{'Content-Type':'application/json'},
    body: JSON.stringify(payload)
  });
  const ok = await res.json();
  if(ok.success){
    window.location.href = 'dashboard.php'; // 保存成功後はダッシュボードへ
  }else{
    alert('保存に失敗しました: ' + (ok.error || '不明なエラー'));
  }
});
</script>
</body>
</html>
