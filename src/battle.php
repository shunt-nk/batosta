<?php
// battle.php — mockup styled (自分HP/SP数値のみ表示、相手はゲージだけ)
declare(strict_types=1);
session_start();
require 'includes/db.php';
require 'includes/functions.php';
require 'includes/skill_repo.php';

if (!isset($_SESSION['user'])) { header("Location: index.php"); exit; }
$meId = (int)$_SESSION['user']['id'];
$vsId = isset($_GET['vs']) ? (int)$_GET['vs'] : 0;
if ($vsId <= 0 || $vsId === $meId) {
  $st = $pdo->prepare("SELECT id FROM users WHERE id <> ? ORDER BY id LIMIT 1");
  $st->execute([$meId]);
  $vsId = (int)($st->fetchColumn() ?: $meId);
}

function get_profile(PDO $pdo, int $uid): array {
  $st = $pdo->prepare("SELECT id, username, profile_icon_url FROM users WHERE id=?");
  $st->execute([$uid]);
  $u = $st->fetch(PDO::FETCH_ASSOC) ?: ['id'=>$uid, 'username'=>"ユーザー", 'profile_icon_url'=>''];
  $stat = getAvatarStatusWithEquip($pdo, $uid);
  $level = (int)($stat['level'] ?? 1);
  $atk   = (int)($stat['attack'] ?? 5);
  $def   = (int)($stat['defense'] ?? 5);
  $hpmax = 100; // モックでは固定
  $spmax = 100;
  return ['id'=>(int)$u['id'], 'name'=>$u['username'] ?? 'ユーザー', 'icon'=>$u['profile_icon_url'] ?? '', 'level'=>$level, 'atk'=>$atk, 'def'=>$def, 'hpmax'=>$hpmax, 'spmax'=>$spmax];
}
$me  = get_profile($pdo, $meId);
$opp = get_profile($pdo, $vsId);

$skillsMe  = fetch_available_skills($pdo, $me['id'],  $me['level']);
$skillsOpp = fetch_available_skills($pdo, $opp['id'], $opp['level']);

function pack_skills(array $g): array {
  $o=[]; foreach ($g as $k=>$arr) { $o[$k]=[]; foreach ($arr as $s) { $o[$k][]=[
    'code'=>$s['code'],'name'=>$s['name'],'action_class'=>$s['action_class'],'type'=>$s['type'],
    'hit_rate'=>(int)$s['hit_rate'],'power'=>(float)$s['power'],'cost_sp'=>(int)$s['cost_sp'],
    'effect'=>json_decode($s['effect_json']??"{}",true),'fg_bonus'=>json_decode($s['fg_bonus_json']??"{}",true),
    'description'=>$s['description']
  ]; } } return $o;
}
$DATA = [
  'me'=>$me, 'opp'=>$opp,
  'skills'=>pack_skills($skillsMe),
  'skillsOpp'=>pack_skills($skillsOpp),
  'const'=>['KDEF'=>1.0,'CRIT'=>1.5,'VAR'=>0.05,'GUARD'=>0.50,'GUARD_FG'=>0.30],
];
?><!doctype html>
<html lang="ja">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>バトル | バトスタ</title>
<link rel="stylesheet" href="styles/style.css">
<link rel="stylesheet" href="styles/battle.css">
</head>
<body>
<div class="container">

  <div class="top">
    <!-- 左：自分 -->
    <div class="side">
      <div class="name">
        <?=htmlspecialchars($me['name'])?> <span style="font-size:18px">Lv<?=$me['level']?></span>
        <span id="me-tag" class="resTag" style="display:none"></span>
      </div>
      <div class="bars">
        <div>HP <span id="me-hp-num">100</span></div>
        <div class="bar hp"><span id="me-hp" style="width:100%"></span></div>
        <div>SP <span id="me-sp-num">100</span></div>
        <div class="bar sp"><span id="me-sp" style="width:100%"></span></div>
        <div>FG</div>
        <div class="fg" id="me-fg"></div>
      </div>
      <div class="avatar" id="avatar-me">
        <?php
          $needsA = !hasAvatarBody($pdo,$meId);
          if($needsA){ echo '<div class="standin">アバター未作成</div>'; }
          else {
            $parts = fetchSelectedPartsBySlots($pdo,$meId,['body','hair','eyes','mouth','hand_base','hand_weapon']);
            $eq = fetchEquipPaths($pdo,$meId); unset($parts['hand']);
            if(!empty($eq['weapon']) && !empty($parts['hand_weapon'])) $parts['hand']=$parts['hand_weapon'];
            elseif(!empty($parts['hand_base'])) $parts['hand']=$parts['hand_base'];
            echo renderAvatarFull($parts,$eq);
          }
        ?>
      </div>
    </div>

    <!-- 中央：ターンボックス -->
    <div>
      <div class="centerBox">
        <h2 id="turn-title">ターン1</h2>
        <div id="turn-line1" class="line">ユーザーAのターン</div>
        <div id="turn-line2" class="line" style="color:#6b7280">行動選択中</div>
        <a class="link" id="open-log">ログを見る</a>
      </div>
      <div class="vs">VS</div>
      <div id="result-center" class="resultCenter" style="display:none">
        <a href="dashboard.php" class="btn primary big">ホームに戻る</a>
      </div>
    </div>

    <!-- 右：相手 -->
    <div class="side" style="position:relative">
      <div class="name">
        <?=htmlspecialchars($opp['name'])?> <span style="font-size:18px">Lv<?=$opp['level']?></span>
        <span id="opp-tag" class="resTag" style="display:none"></span>
      </div>
      <div class="bars">
        <div>HP</div>
        <div class="bar hp"><span id="opp-hp" style="width:100%"></span></div>
        <div>SP</div>
        <div class="bar sp"><span id="opp-sp" style="width:100%"></span></div>
        <div>FG</div>
        <div class="fg" id="opp-fg"></div>
      </div>
      <div class="avatar" id="avatar-opp">
        <?php
          $needsB = !hasAvatarBody($pdo,$vsId);
          if($needsB){ echo '<div class="standin">アバター未作成</div>'; }
          else {
            $parts = fetchSelectedPartsBySlots($pdo,$vsId,['body','hair','eyes','mouth','hand_base','hand_weapon']);
            $eq = fetchEquipPaths($pdo,$vsId); unset($parts['hand']);
            if(!empty($eq['weapon']) && !empty($parts['hand_weapon'])) $parts['hand']=$parts['hand_weapon'];
            elseif(!empty($parts['hand_base'])) $parts['hand']=$parts['hand_base'];
            echo renderAvatarFull($parts,$eq);
          }
        ?>
      </div>
      <div id="win-badge" class="badgeWin" style="display:none">win!</div>
      <div id="lose-badge" class="badgeLose" style="display:none">lose..</div>
    </div>
  </div>

  <!-- 行動パネル -->
  <div class="actionPanel">
    <div class="tabs">
      <button class="tab active" data-tab="attack">攻撃</button>
      <button class="tab" data-tab="guard">防御</button>
      <button class="tab" data-tab="heal">回復</button>
    </div>
    <div class="skillRow" id="skill-row"></div>
    <div class="footer_flex">
      <div id="state-text" class="stateText">あなたのターンです</div>
      <div class="footerRow">
        <label class="checkbox">
          <input type="checkbox" id="use-focus">
          集中する（FG消費）
        </label>
        <button class="btn primary" id="btn-act">スキルを使う</button>
      </div>
    </div>
  </div>

</div>

<div class="toast" id="toast"></div>
<div class="resultOverlay" id="result-banner">勝利しました！</div>
<div class="resultBtns" id="result-btns"><a href="home.php" class="btn primary">ホームに戻る</a></div>

<!-- モーダル -->
<div class="modal" id="modal">
  <div class="back"></div>
  <div class="panel">
    <div class="head"><b>バトルログ</b><button id="close-log" class="btn">閉じる</button></div>
    <div id="log-all" class="log"></div>
  </div>
</div>

<script>
const INIT = <?=json_encode($DATA, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES)?>;
const KDEF=INIT.const.KDEF, CRIT=INIT.const.CRIT, VAR=INIT.const.VAR, GUARD=INIT.const.GUARD, GUARD_FG=INIT.const.GUARD_FG;

const S = {
  turn:1,
  me:{...INIT.me, hp:INIT.me.hpmax, sp:INIT.me.spmax, fg:1, fat:{A:0,G:0,H:0}, chain:'---', last:'none', eff:{}},
  opp:{...INIT.opp, hp:INIT.opp.hpmax, sp:INIT.opp.spmax, fg:1, fat:{A:0,G:0,H:0}, chain:'---', last:'none', eff:{}},
  skills: INIT.skills,
  skillsOpp: INIT.skillsOpp,
  waiting:false,
  logs:[]
};

// ------------ UI helpers ------------
const el=(id)=>document.getElementById(id);
const pct=(cur,max)=>Math.max(0, Math.floor(cur/max*100));
function renderHUD(){
  el('me-hp').style.width=pct(S.me.hp,S.me.hpmax)+'%';
  el('me-sp').style.width=pct(S.me.sp,S.me.spmax)+'%';
  el('opp-hp').style.width=pct(S.opp.hp,S.opp.hpmax)+'%';
  el('opp-sp').style.width=pct(S.opp.sp,S.opp.spmax)+'%';
  el('me-hp-num').textContent=S.me.hp;
  el('me-sp-num').textContent=S.me.sp;
  const dots=(x)=>Array.from({length:3}).map((_,i)=>`<div class="dot ${i<x?'on':''}"></div>`).join('');
  el('me-fg').innerHTML=dots(S.me.fg); el('opp-fg').innerHTML=dots(S.opp.fg);
  el('turn-title').textContent='ターン'+S.turn;
}
function pushLog(line){ S.logs.push({t:S.turn, line}); const div=document.createElement('div'); div.className='line'; div.textContent='T'+S.turn+': '+line; el('log-all').prepend(div); }
function showToast(msg){ const t=el('toast'); t.textContent=msg; t.style.display='block'; setTimeout(()=>t.style.display='none',1400); }

// ------------ Chains & fatigue ------------
function evalChain(chain3){
  const t={
    'AGA':{type:'attack', dmg:1.25, hit:10},
    'GHA':{type:'attack', dmg:1.15, taken:0.90},
    'HGA':{type:'attack', dmg:1.10, fg:1},
    'AAH':{type:'heal',   heal:1.20, reduceA:1},
    'GHH':{type:'heal',   heal:1.15, cleanse:1},
    'AGG':{type:'guard',  gOverride:0.35},
  };
  return t[chain3]||null;
}
const fatMul=(v)=>({0:1.0,1:0.9,2:0.8,3:0.7}[v]||1.0);

// ------------ Skills UI ------------
let currentTab='attack', selected=null;

// FG効果文字列（fg_bonus_json 優先）
function fgBonusText(s){
  const b=s.fg_bonus||{};
  const parts=[];
  if (typeof b.damage_mult==='number') parts.push(`与ダメ×${b.damage_mult.toFixed(2)}`);
  if (typeof b.hit_bonus==='number')   parts.push(`命中+${b.hit_bonus}`);
  if (typeof b.heal_mult==='number')   parts.push(`回復×${b.heal_mult.toFixed(2)}`);
  if (typeof b.guard_override==='number') parts.push(`被ダメ軽減${Math.round((1-b.guard_override)*100)}%`);
  return parts.length? parts.join(' / ') : null;
}

function descFor(s, useFocus){
  if (!useFocus){ return s.description || 'スキル詳細'; }
  const text = fgBonusText(s);
  if (text) return `集中時：${text}`;
  // fg_bonus が無いスキルは既定の効果
  if (s.action_class==='attack') return '集中時：命中+10 / 与ダメ×1.15';
  if (s.action_class==='guard')  return '集中時：被ダメ軽減30%（上書き）';
  if (s.action_class==='heal')   return '集中時：回復量×1.20';
  return '集中';
}

function renderSkills(){
  const row = el('skill-row');
  const arr = S.skills[currentTab] || [];

  // ★攻撃タブ：DBにBASIC_ATTACKが「無い場合だけ」擬似カードを追加
  const hasBasic = arr.some(s => s.code === 'BASIC_ATTACK');
  const pseudo = [{code:'BASIC_ATTACK', name:'通常攻撃', action_class:'attack', hit_rate:100, power:1.0, cost_sp:0, description:'命中100% 基本の一撃'}];

  const all = (currentTab === 'attack' && !hasBasic) ? pseudo.concat(arr) : arr;

  row.innerHTML = all.map(s=>{
    const cost = s.cost_sp > 0 ? `<span class="pill">SP ${s.cost_sp}</span>` : `<span class="pill">SP -</span>`;
    const desc = descFor(s, el('use-focus').checked);
    return `<div class="skill" data-code="${s.code}">${cost}<div class="title">${s.name}</div><div class="desc">${desc}</div></div>`;
  }).join('');

  row.querySelectorAll('.skill').forEach(card=>{
    card.onclick=()=>{
      row.querySelectorAll('.skill').forEach(x=>x.classList.remove('selected'));
      card.classList.add('selected');
      selected = all.find(x=>x.code===card.dataset.code);
    };
  });

  const first=row.querySelector('.skill');
  if(first){ first.classList.add('selected'); selected = all[0]; }
}
document.querySelectorAll('.tab').forEach(b=>{
  b.onclick=()=>{
    document.querySelectorAll('.tab').forEach(x=>x.classList.remove('active'));
    b.classList.add('active'); currentTab=b.dataset.tab; renderSkills();
    el('btn-act').textContent= currentTab==='heal' ? '回復する' : (currentTab==='guard' ? '防御する' : 'スキルを使う');
  };
});
el('use-focus').addEventListener('change',()=>{ renderSkills(); });

// ------------ Battle Math ------------
function mitRatio(def){ return 100/(100 + KDEF*def); }
function roll(p){ return Math.random()*100<p; }
function dmgCalc(att,tgt,power,hit,fg, Mchain, chainHit, gOverride){
  const finalHit = Math.max(5, Math.min(100, hit + (fg?10:0) + (chainHit||0)));
  if (!roll(finalHit)) return {hit:false,dmg:0,crit:false};
  const Matk = 1.0, MdmgA = 1.0, Mdef = 1.0;
  const Mfat = ({0:1.0,1:0.9,2:0.8,3:0.7}[att.fat.A]||1.0);
  const Mfg  = fg?1.15:1.0;
  let dmg = power * att.atk * Matk * MdmgA * Mfg * Mfat * Mchain * mitRatio(tgt.def*Mdef);
  let crit=false; if (roll(8)) {crit=true; dmg *= CRIT;}
  dmg *= (1 + (Math.random()*2-1)*VAR);
  const gmul = gOverride ?? (tgt.eff['guard']?.mag ?? 1.0);
  dmg *= gmul;
  return {hit:true, dmg:Math.max(1, Math.round(dmg)), crit};
}
function healCalc(unit,ratio,fg,Mchain){
  const Mfat=({0:1.0,1:0.9,2:0.8,3:0.7}[unit.fat.H]||1.0), Mfg=fg?1.20:1.0;
  const val = Math.min(unit.hpmax-unit.hp, Math.floor(unit.hpmax*ratio*Mfg*Mchain*Mfat));
  unit.hp += val; return val;
}

function chainApply(unit, letter){
  const key = unit.chain.slice(-2)+letter;
  const c = evalChain(key); const res={M:1.0, hit:0, gOverride:null, nextBuff:null, fgGain:0, cleanse:false, reduceA:0};
  if (c){
    if (c.dmg) res.M = c.dmg;
    if (c.hit) res.hit = c.hit;
    if (c.gOverride) res.gOverride = c.gOverride;
    if (c.taken) res.nextBuff = {code:'guard',mag:c.taken,turns:1};
    if (c.fg) res.fgGain = c.fg;
    if (c.cleanse) res.cleanse=true;
    if (c.reduceA) res.reduceA=1;
  }
  return res;
}
function afterAction(unit, letter, chain){
  unit.fat[letter] = Math.min(3,(unit.fat[letter]||0)+1);
  for (const k of ['A','G','H']) if (k!==letter) unit.fat[k] = Math.max(0,(unit.fat[k]||0)-1);
  if (chain.reduceA) unit.fat.A = Math.max(0, unit.fat.A-1);
  if (chain.fgGain) unit.fg = Math.min(3, unit.fg+chain.fgGain);
  if (chain.nextBuff) unit.eff[chain.nextBuff.code] = chain.nextBuff;
  unit.chain = (unit.chain + letter).slice(-3);
  unit.last = (letter==='A'?'attack':letter==='G'?'guard':'heal');
}

function chime(){
  if (S.turn % 3 === 0){ S.me.fg=Math.min(3,S.me.fg+1); S.opp.fg=Math.min(3,S.opp.fg+1); pushLog('＊ チャイムが鳴った！FG+1'); }
}

function setTurnTexts(l1,l2){ el('turn-line1').textContent=l1; el('turn-line2').textContent=l2; }
function setPlayerState(myTurn){
  const btn = el('btn-act');
  if (myTurn){ btn.classList.remove('disabled'); btn.disabled=false; el('state-text').textContent='あなたのターンです'; }
  else { btn.classList.add('disabled'); btn.disabled=true; el('state-text').textContent='相手のターンです'; }
}

// ------------ One turn flow ------------
function doPlayer(skill, useFg){
  if (!skill) return;
  if (useFg && S.me.fg<=0){ showToast('集中できません（FGが足りません）'); return; }
  setPlayerState(false); S.waiting=true; el('btn-act').textContent='相手が行動を選択中';

  const L = skill.action_class==='guard'?'G':(skill.action_class==='heal'?'H':'A');
  const chain = chainApply(S.me, L);
  let line = '';
  if (L==='A'){
    const out = dmgCalc(S.me,S.opp, skill.power||1.0, skill.hit_rate||100, useFg, chain.M, chain.hit, chain.gOverride);
    if (!out.hit) line = `あなたの${skill.name}は外れた…`;
    else { S.opp.hp=Math.max(0,S.opp.hp-out.dmg); line = `あなたの${skill.name}！ 相手に${out.dmg}ダメージ`; }
  } else if (L==='G'){
    S.me.eff['guard'] = {mag: (useFg?GUARD_FG:GUARD), turns:1}; line = `あなたは身構えた！`;
  } else {
    const ratio = (skill.effect?.heal?.ratio_hp) ?? 0.20;
    const val = healCalc(S.me,ratio,useFg,chain.M); line = `あなたは${skill.name}で${val}回復`;
  }
  if (useFg) S.me.fg = Math.max(0,S.me.fg-1);
  afterAction(S.me, L, chain);
  renderHUD(); pushLog(line);
  setTurnTexts(`ユーザーAの行動`, line);

  if (S.opp.hp<=0){ endBattle(true); return; }

  setTimeout(()=>doAI(), 700);
}

function doAI(){
  // choose
  let s=null, focus=false;
  if (S.opp.hp/S.opp.hpmax<0.35 && (INIT.skillsOpp.heal||[]).length){ s=INIT.skillsOpp.heal[0]; focus=true; }
  else {
    const arr=INIT.skillsOpp.attack||[]; s=arr[0]||{code:'BASIC_ATTACK',name:'通常攻撃',action_class:'attack',hit_rate:100,power:1.0};
  }
  const L = s.action_class==='guard'?'G':(s.action_class==='heal'?'H':'A');
  const chain = chainApply(S.opp, L);

  let line='';
  if (L==='A'){
    const out = dmgCalc(S.opp,S.me, s.power||1.0, s.hit_rate||100, focus, chain.M, chain.hit, chain.gOverride);
    if (!out.hit) line = `相手の${s.name}は外れた…`;
    else { S.me.hp=Math.max(0,S.me.hp-out.dmg); line = `相手の${s.name}！ あなたは${out.dmg}ダメージ`; }
  } else if (L==='G'){
    S.opp.eff['guard'] = {mag:(focus?GUARD_FG:GUARD), turns:1}; line='相手は身構えている…';
  } else {
    const ratio=(s.effect?.heal?.ratio_hp)??0.20; const v=healCalc(S.opp,ratio,focus,chain.M); line=`相手は${s.name}で${v}回復`;
  }
  if (focus) S.opp.fg=Math.max(0,S.opp.fg-1);
  afterAction(S.opp,L,chain);
  renderHUD(); pushLog(line);
  setTurnTexts(`ユーザーBの行動`, line);

  if (S.me.hp<=0){ endBattle(false); return; }

  // end-of-turn
  chime();
  S.turn+=1; renderHUD();
  setPlayerState(true); el('btn-act').textContent='スキルを使う'; S.waiting=false;
  setTurnTexts('ユーザーAのターン','行動選択中');

  // 18ターン上限（19に進む前に終了）
  if (S.turn>18){ 
    if (S.me.hp>S.opp.hp) endBattle(true,'時間切れ：あなたのHPが上回った！');
    else if (S.me.hp<S.opp.hp) endBattle(false,'時間切れ：相手のHPが上回った…');
    else endBattle(null,'時間切れ：引き分け');
  }
}

function endBattle(win, reason=null){
  setPlayerState(false); el('btn-act').textContent='終了';
  if (reason) pushLog(reason);

  // Lvの横に勝敗ラベル
  const meTag  = el('me-tag');
  const oppTag = el('opp-tag');

  if (win === true){
    meTag.textContent = 'win!';   meTag.className = 'resTag win';   meTag.style.display='inline-block';
    oppTag.textContent = 'lose..'; oppTag.className = 'resTag lose'; oppTag.style.display='inline-block';
    el('result-banner').style.display='block';
    el('result-banner').textContent='勝利しました！';
  } else if (win === false){
    meTag.textContent = 'lose..'; meTag.className = 'resTag lose';  meTag.style.display='inline-block';
    oppTag.textContent = 'win!';  oppTag.className = 'resTag win';  oppTag.style.display='inline-block';
    el('result-banner').style.display='block';
    el('result-banner').textContent='負けてしまった…';
  } else {
    // 引き分けならラベルは非表示のまま
    meTag.style.display='none'; oppTag.style.display='none';
    el('result-banner').style.display='block';
    el('result-banner').textContent='引き分け';
  }

  // 中央列に戻るボタンを表示（dashboard.phpへ）
  el('result-center').style.display='block';
}

// ------------ wiring ------------
function init(){
  renderHUD(); renderSkills(); setPlayerState(true);
  el('open-log').onclick=()=>{ el('modal').style.display='block'; };
  el('close-log').onclick=()=>{ el('modal').style.display='none'; };
  el('modal').querySelector('.back').onclick=()=>{ el('modal').style.display='none'; };

  el('btn-act').onclick=()=>{
    if (S.waiting) return;
    if (!selected){ showToast('スキルを選択してください'); return; }
    const useFg = el('use-focus').checked;
    if (useFg && S.me.fg<=0){ showToast('集中できません（FGが足りません）'); return; }
    doPlayer(selected, useFg);
  };
}
init();
</script>
</body>
</html>
