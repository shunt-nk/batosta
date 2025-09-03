<?php
// avatar_customize.php 既存機能温存 + スロット整合 + パス仕様 + 安全装備更新
declare(strict_types=1);
session_start();
require 'includes/db.php';
require 'includes/functions.php';

if (!isset($_SESSION['user'])) { header("Location: index.php"); exit; }
$user_id = (int)$_SESSION['user']['id'];

/* ---------------- スロット名の相互変換（UI↔DB） ---------------- */
function ui_to_db_slot(string $ui): string {
  static $map = ['weapon'=>'weapon','shield'=>'shield','head'=>'head','outfit'=>'body','boots'=>'legs'];
  return $map[$ui] ?? $ui;
}
function db_to_ui_slot(string $db): string {
  static $map = ['weapon'=>'weapon','shield'=>'shield','head'=>'head','body'=>'outfit','legs'=>'boots'];
  return $map[$db] ?? $db;
}

/* ---------------- 画像パスユーティリティ（既存崩さず） ---------------- */
if (!function_exists('equip_img_src')) {
  function equip_img_src(?string $p): string {
    if (!$p) return '';
    $p = trim($p);
    if (preg_match('~^https?://~i', $p)) return $p;
    return ltrim($p, '/');
  }
}
function norm_file(?string $f): string { return ltrim(trim((string)$f), '/'); }
function build_path(string $base, string $slotUi, ?string $file): string {
  $file = norm_file($file);
  if ($file === '') return '';
  if (strpos($file, '/') !== false) { // 既にサブディレクトリ含む場合も許容
    return equip_img_src($base.'/'.$file);
  }
  return equip_img_src($base.'/'.$slotUi.'/'.$file);
}

/* 未装備アイコン（テーブルが無くても empty.png を使う） */
function fetch_empty_icon_map(PDO $pdo, array $uiSlots): array {
  $map = [];
  try {
    $st = $pdo->query("SELECT slot, empty_icon_path FROM equip_slot_master");
    foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $r) {
      // このテーブルは UI スロット名で持つ想定。DB名だった場合もディレクトリはUI名に揃える
      $ui = db_to_ui_slot((string)$r['slot']);
      $map[$ui] = norm_file($r['empty_icon_path']);
    }
  } catch (\Throwable $e) { /* テーブル無しでもOK */ }
  foreach ($uiSlots as $s) if (!isset($map[$s]) || $map[$s]==='') $map[$s] = 'empty.png';
  return $map;
}

/* 一覧/小枠アイコン用 */
function ui_icon_src(string $slotUi, ?array $row, array $emptyMap): string {
  if ($row && !empty($row['icon_path'])) {
    return build_path('assets/icons', $slotUi, $row['icon_path']);
  }
  if ($row && !empty($row['image_path'])) {
    return build_path('assets/icons', $slotUi, basename(norm_file($row['image_path'])));
  }
  return build_path('assets/icons', $slotUi, $emptyMap[$slotUi] ?? 'empty.png');
}
/* プレビュー用（右の大サムネ） */
function ui_avatar_src(string $slotUi, ?array $row): string {
  if ($row && !empty($row['avatar_path'])) {
    return build_path('assets/avatars', $slotUi, $row['avatar_path']);
  }
  if ($row && !empty($row['image_path'])) {
    return build_path('assets/avatars', $slotUi, $row['image_path']);
  }
  return '';
}

/* ---------- アバター作成済み判定 ---------- */
if (!hasAvatarBody($pdo, $user_id)) {
  ?>
  <!doctype html><html lang="ja"><head>
    <meta charset="utf-8"><title>着せ替え | バトスタ</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="styles/style.css">
  </head><body>
    <div class="container">
      <?php include 'includes/navbar.php'; ?>
      <main class="content">
        <h2>着せ替え</h2>
        <div style="background:#fff7e6;border:2px dashed #e6d4ae;color:#6b4d22;padding:12px 14px;border-radius:12px;margin:16px 0;">
          まずはアバターを作成しましょう →
          <a href="avatar_create.php?first=1" style="font-weight:700;color:#2e7d32;text-decoration:underline;">アバター作成へ</a>
        </div>
      </main>
    </div>
  </body></html>
  <?php
  exit;
}

/* ---------- 見た目パーツ（手の確定ロジックは既存維持） ---------- */
list($parts, $equipPaths) = loadAvatarStacks($pdo, $user_id);
unset($parts['hand']);
if (!empty($equipPaths['weapon']) && !empty($parts['hand_weapon'])) {
  $parts['hand'] = $parts['hand_weapon'];
} elseif (!empty($parts['hand_base'])) {
  $parts['hand'] = $parts['hand_base'];
}

/* ---------- スロット／タブ（UI名） ---------- */
$SLOTS_UI = ['weapon','shield','head','outfit','boots'];
$LABEL = ['weapon'=>'武器','shield'=>'盾','head'=>'頭防具','outfit'=>'体防具','boots'=>'足防具'];
$slotUi = $_GET['slot'] ?? 'weapon';
if (!in_array($slotUi, $SLOTS_UI, true)) $slotUi = 'weapon';

/* ---------- POST: 装備 or 解除（ユニーク制約不要の置換更新） ---------- */
if ($_SERVER['REQUEST_METHOD']==='POST') {
  $action = $_POST['equip_action'] ?? '';
  $slotUiP  = $_POST['slot'] ?? '';
  if (in_array($slotUiP, $SLOTS_UI, true)) {
    $slotDb = ui_to_db_slot($slotUiP);
    if ($action==='equip') {
      $equip_id = (int)($_POST['equipment_id'] ?? 0);
      if ($equip_id>0) {
        // 所持＆スロット一致（DBスロットで検証！）
        $chk = $pdo->prepare("
          SELECT 1
          FROM user_equipments ue
          JOIN equipments e ON e.id = ue.equipment_id
          WHERE ue.user_id=? AND ue.equipment_id=? AND e.slot=?
        ");
        $chk->execute([$user_id,$equip_id,$slotDb]);
        if ($chk->fetchColumn()) {
          // 安全な置換（ユニークキー不要）
          $pdo->beginTransaction();
          try {
            $del = $pdo->prepare("DELETE FROM user_avatar_equipments WHERE user_id=? AND slot=?");
            $del->execute([$user_id,$slotDb]);
            $ins = $pdo->prepare("INSERT INTO user_avatar_equipments (user_id,slot,equipment_id) VALUES (?,?,?)");
            $ins->execute([$user_id,$slotDb,$equip_id]);
            $pdo->commit();
          } catch (\Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            throw $e;
          }
        }
      }
    } elseif ($action==='remove') {
      $del = $pdo->prepare("DELETE FROM user_avatar_equipments WHERE user_id=? AND slot=?");
      $del->execute([$user_id,$slotDb]);
    }
    header("Location: avatar_customize.php?slot=".urlencode($slotUiP)); exit;
  }
}

/* ---------- 現在装備（DB→UIへキー整形） ---------- */
$hasIcon = false; $hasAvatar = false;
/* 列存在チェック（あれば使う・無ければ NULL 列名を立てる） */
try {
  $cs = $pdo->prepare("
    SELECT COUNT(*) FROM information_schema.columns
    WHERE table_schema=DATABASE() AND table_name='equipments' AND column_name='icon_path'
  "); $cs->execute(); $hasIcon = (bool)$cs->fetchColumn();
  $cs = $pdo->prepare("
    SELECT COUNT(*) FROM information_schema.columns
    WHERE table_schema=DATABASE() AND table_name='equipments' AND column_name='avatar_path'
  "); $cs->execute(); $hasAvatar = (bool)$cs->fetchColumn();
} catch (\Throwable $e) {}

$cols = "uae.slot, e.id AS equipment_id, e.name, e.slot AS e_slot, e.image_path, e.attack, e.defense";
$cols .= $hasIcon   ? ", e.icon_path"   : ", NULL AS icon_path";
$cols .= $hasAvatar ? ", e.avatar_path" : ", NULL AS avatar_path";

$st = $pdo->prepare("SELECT $cols FROM user_avatar_equipments uae JOIN equipments e ON e.id = uae.equipment_id WHERE uae.user_id = ?");
$st->execute([$user_id]);
$current = []; // UIキーで持つ
foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $r) {
  $ui = db_to_ui_slot((string)$r['slot']);
  $current[$ui] = $r;
}

/* ---------- 所持装備（一覧：UIスロットでグルーピング） ---------- */
$cols2 = "e.id AS equipment_id, e.name, e.slot, e.image_path, e.attack, e.defense";
$cols2 .= $hasIcon   ? ", e.icon_path"   : ", NULL AS icon_path";
$cols2 .= $hasAvatar ? ", e.avatar_path" : ", NULL AS avatar_path";

$st = $pdo->prepare("
  SELECT $cols2
  FROM user_equipments ue
  JOIN equipments e ON e.id = ue.equipment_id
  WHERE ue.user_id = ?
  ORDER BY e.id
");
$st->execute([$user_id]);
$owned = [];
foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $r) {
  $ui = db_to_ui_slot((string)$r['slot']);
  if (!in_array($ui, $SLOTS_UI, true)) continue;
  $owned[$ui][] = $r;
}

/* ---------- 未装備アイコン（UIスロット別） ---------- */
$emptyIconMap = fetch_empty_icon_map($pdo, $SLOTS_UI);

/* ---------- 右の詳細：選択中の装備（UIスロット空間で判定） ---------- */
$selectId = isset($_GET['select']) ? (int)$_GET['select'] : 0;
$selected = null;
if ($selectId>0) {
  foreach ($owned[$slotUi] ?? [] as $it) {
    if ((int)$it['equipment_id'] === $selectId) { $selected = $it; break; }
  }
}
if (!$selected) { $selected = $current[$slotUi] ?? ($owned[$slotUi][0] ?? null); }

$current_page = 'customize';


?>
<!doctype html>
<html lang="ja">
<head>
  <meta charset="utf-8">
  <title>アバター着せ替え</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link rel="stylesheet" href="styles/style.css">
  <link rel="stylesheet" href="styles/avatar_customize.css">
</head>
<body>
<div class="container">
  <?php include 'includes/navbar.php'; ?>

  <main class="customize">
    <!-- ==== 左：アバター＆プレビュータブ ==== -->
    <section class="left">
      <div class="preview-bar">
        <?php
          // 既存の固定レイアウト（上2 + 下3）
          $layout = ['weapon', 'shield', null, 'head', 'outfit', 'boots'];
          foreach ($layout as $s):
            if ($s === null){
              echo '<div class="preview-slot spacer" aria-hidden="true"></div>';
              continue;
            }
            $active = ($slotUi === $s) ? 'active' : '';
            // 装備中ならアイコン、未装備は empty.png
            $thumbSrc = isset($current[$s])
              ? ui_icon_src($s, $current[$s], $emptyIconMap)
              : build_path('assets/icons', $s, $emptyIconMap[$s] ?? 'empty.png');
        ?>
          <a class="preview-slot <?= $active ?>" href="?slot=<?= htmlspecialchars($s,ENT_QUOTES) ?>" title="<?= $LABEL[$s] ?>">
            <img src="<?= htmlspecialchars($thumbSrc, ENT_QUOTES) ?>" alt="<?= $LABEL[$s] ?>">
          </a>
        <?php endforeach; ?>
      </div>

      <!-- アバター本体（既存をそのまま） -->
      <div class="avatar-stage">
        <?= renderAvatarFull($parts, $equipPaths) ?>
        
      </div>
    </section>

    <!-- ==== 右：詳細＋装備一覧 ==== -->
    <section class="right">
      <div class="detail-card">
        <?php if ($selected): ?>
          <div class="detail-thumb">
            <?php
              $selSlot = db_to_ui_slot($selected['slot'] ?? $slotUi ?? 'weapon');
              $detailIconSrc = ui_icon_src($selSlot, $selected, $emptyIconMap);
            ?>
            <img src="<?= htmlspecialchars($detailIconSrc, ENT_QUOTES) ?>" alt="">
          </div>
          <div class="detail-meta">
            <h2><?= htmlspecialchars($selected['name'],ENT_QUOTES) ?></h2>
            <div>攻撃力 <?= (int)$selected['attack'] ?> / 防御力 <?= (int)$selected['defense'] ?></div>

            <div class="detail-actions">
              <?php
                $equipNowId = (int)($current[$slotUi]['equipment_id'] ?? 0);
                $isEquipped = $equipNowId === (int)$selected['equipment_id'];
              ?>
              <?php if ($isEquipped): ?>
                <form method="post" class="inline">
                  <input type="hidden" name="equip_action" value="remove">
                  <input type="hidden" name="slot" value="<?= htmlspecialchars($slotUi,ENT_QUOTES) ?>">
                  <button type="submit" class="btn ghost">装備を外す</button>
                </form>
                <span class="equipped-badge">装備中</span>
              <?php else: ?>
                <form method="post" class="inline">
                  <input type="hidden" name="equip_action" value="equip">
                  <input type="hidden" name="slot" value="<?= htmlspecialchars($slotUi,ENT_QUOTES) ?>">
                  <input type="hidden" name="equipment_id" value="<?= (int)$selected['equipment_id'] ?>">
                  <button type="submit" class="btn primary">装備する</button>
                </form>
              <?php endif; ?>
            </div>
          </div>
        <?php else: ?>
          <div class="detail-empty">このカテゴリの装備は未所持です</div>
        <?php endif; ?>
      </div>

      <h3 class="grid-title"><?= $LABEL[$slotUi] ?> 一覧</h3>
      <div class="equip-grid">
        <?php foreach ($owned[$slotUi] ?? [] as $it):
          $sel = ($selected && (int)$selected['equipment_id']===(int)$it['equipment_id']) ? 'selected' : '';
          $iconSrc = ui_icon_src($slotUi, $it, $emptyIconMap);
        ?>
          <a class="equip-thumb <?= $sel ?>"
             href="?slot=<?= htmlspecialchars($slotUi,ENT_QUOTES) ?>&select=<?= (int)$it['equipment_id'] ?>"
             title="<?= htmlspecialchars($it['name'],ENT_QUOTES) ?>">
            <img src="<?= htmlspecialchars($iconSrc, ENT_QUOTES) ?>" alt="<?= htmlspecialchars($it['name'],ENT_QUOTES) ?>">
          </a>
        <?php endforeach; ?>
      </div>
    </section>
  </main>
</div>
</body>
</html>
