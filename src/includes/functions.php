<?php
declare(strict_types=1);

/**
 * usersごとの「body」パーツが保存済みか
 */
function hasAvatarBody(PDO $pdo, int $user_id): bool {
  $st = $pdo->prepare("SELECT 1 FROM user_avatar_parts WHERE user_id = ? AND slot = 'body' LIMIT 1");
  $st->execute([$user_id]);
  return (bool)$st->fetchColumn();
}

/**
 * 現在の選択パーツ（image_path）を slot => path で返す
 * 既定は 4スロット（body/hair/eyes/mouth）
 */
function fetchSelectedParts(PDO $pdo, int $user_id, array $slots = ['body','hair','eyes','mouth']): array {
  if (empty($slots)) return [];
  $placeholders = implode(',', array_fill(0, count($slots), '?'));
  $params = array_merge([$user_id], $slots);

  $sql = "
    SELECT u.slot, c.image_path
    FROM user_avatar_parts u
    JOIN avatar_parts_catalog c ON u.part_id = c.id
    WHERE u.user_id = ?
      AND u.slot IN ($placeholders)
  ";
  $stmt = $pdo->prepare($sql);
  $stmt->execute($params);

  $bySlot = [];
  foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $r) {
    $bySlot[$r['slot']] = $r['image_path'];
  }
  return $bySlot;
}

/**
 * 任意スロットだけまとめて取得（hand_base/hand_weapon などを追加で取りたい時に）
 */
function fetchSelectedPartsBySlots(PDO $pdo, int $user_id, array $slots): array {
  if (!$slots) return [];
  $in = implode(',', array_fill(0, count($slots), '?'));
  $params = array_merge([$user_id], $slots);
  $sql = "SELECT u.slot, c.image_path
          FROM user_avatar_parts u
          JOIN avatar_parts_catalog c ON c.id=u.part_id
          WHERE u.user_id=? AND u.slot IN ($in)";
  $st = $pdo->prepare($sql);
  $st->execute($params);
  $out = [];
  foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $r) $out[$r['slot']] = $r['image_path'];
  return $out;
}

/**
 * 装備の image_path を slot => path で返す（ダッシュボードやプレビュー用）
 */
// includes/functions.php
function fetchEquipPaths(PDO $pdo, int $user_id): array {
  $st = $pdo->prepare("
    SELECT
      CASE
        WHEN uae.slot='armor' THEN 'outfit'
        ELSE uae.slot
      END AS slot,
      e.image_path
    FROM user_avatar_equipments uae
    JOIN equipments e ON e.id = uae.equipment_id
    WHERE uae.user_id = ?
  ");
  $st->execute([$user_id]);
  $rows = $st->fetchAll(PDO::FETCH_ASSOC);
  return $rows ? array_column($rows, 'image_path', 'slot') : [];
}

/**
 * 旧・簡易レンダラ（顔パーツだけ）：
 * レイヤー順：body → hair → eyes → mouth
 */
function renderAvatarLayers(array $bySlot): string {
  $order = ['body','hair','eyes','mouth','hand'];
  $html = '<div class="avatar-container">';
  foreach ($order as $slot) {
    if (!empty($bySlot[$slot])) {
      $src = htmlspecialchars((string)$bySlot[$slot], ENT_QUOTES, 'UTF-8');
      $html .= '<img class="avatar-layer slot-' . $slot . '" src="' . $src . '" alt="' . $slot . '">';
    }
  }
  $html .= '</div>';
  return $html;
}

/**
 * フルレンダラ（単手仕様）：
 * - 同一キャンバス原寸のパーツ/装備を z-index で重ねるだけ
 * - hand は 1枚のレイヤに統合。weapon があれば hand_weapon、無ければ hand_base を自動選択
 * レイヤー順：body > hair > eyes > mouth > outfit > weapon > hand
 */
function renderAvatarFull(array $partsBySlot, array $equipBySlot = []): string {  // hand の自動切替: weaponがあれば hand_weapon、なければ hand_base
  // functions.php 内 renderAvatarFull の冒頭
if (!empty($equipBySlot['weapon'])) {
  if (!empty($partsBySlot['hand_weapon'])) $partsBySlot['hand'] = $partsBySlot['hand_weapon'];
  elseif (!empty($partsBySlot['hand_base'])) $partsBySlot['hand'] = $partsBySlot['hand_base'];
} else {
  if (!empty($partsBySlot['hand_base'])) $partsBySlot['hand'] = $partsBySlot['hand_base'];
  elseif (!empty($partsBySlot['hand_weapon'])) $partsBySlot['hand'] = $partsBySlot['hand_weapon'];
}


  // レイヤ順（body→hair→eyes→mouth→outfit→weapon→hand）
  $order = ['body','hair','eyes','mouth','outfit','head','shield','weapon','hand','boots'];

  $html = '<div class="avatar-container">';
  foreach ($order as $slot) {
    $src = $partsBySlot[$slot] ?? $equipBySlot[$slot] ?? null;
    if (!$src) continue;
    $html .= '<img class="avatar-layer slot-'.$slot.'" src="'.htmlspecialchars($src, ENT_QUOTES, 'UTF-8').'" alt="'.$slot.'">';
  }
  return $html.'</div>';
}
/**
 * 必要経験値（任意に使用）
 */
function requiredExp(int $level): int {
  return 100 + max(0, $level - 1) * 20;
}

/**
 * 基本＋装備の合算ステータス（欠損時でも安全値で返す）
 */
// includes/functions.php
function getAvatarStatusWithEquip(PDO $pdo, int $user_id): array {
  // 基礎
  $stmt = $pdo->prepare("
    SELECT
      COALESCE(level, 1)          AS level,
      COALESCE(exp, 0)            AS exp,
      COALESCE(base_attack, 0)    AS base_attack,
      COALESCE(base_defense, 0)   AS base_defense
    FROM avatars_status
    WHERE user_id = ?
    LIMIT 1
  ");
  $stmt->execute([$user_id]);
  $base = $stmt->fetch(PDO::FETCH_ASSOC) ?: ['level'=>1,'exp'=>0,'base_attack'=>0,'base_defense'=>0];

  // 装備加算
  $stmt = $pdo->prepare("
    SELECT COALESCE(SUM(e.attack),0) AS equip_attack,
           COALESCE(SUM(e.defense),0) AS equip_defense
    FROM user_avatar_equipments uae
    JOIN equipments e ON uae.equipment_id = e.id
    WHERE uae.user_id = ?
  ");
  $stmt->execute([$user_id]);
  $equip = $stmt->fetch(PDO::FETCH_ASSOC) ?: ['equip_attack'=>0,'equip_defense'=>0];

  return [
    'level'         => (int)$base['level'],
    'exp'           => (int)$base['exp'],
    'base_attack'   => (int)$base['base_attack'],
    'base_defense'  => (int)$base['base_defense'],
    'equip_attack'  => (int)$equip['equip_attack'],
    'equip_defense' => (int)$equip['equip_defense'],
    'attack'        => (int)$base['base_attack']  + (int)$equip['equip_attack'],
    'defense'       => (int)$base['base_defense'] + (int)$equip['equip_defense'],
  ];
}

/**
 * 基本ステータスのみ（欠損時は既定値で返す）
 */
function getAvatarStatus(PDO $pdo, int $user_id): array {
  $stmt = $pdo->prepare("SELECT level, exp, attack, defense FROM avatars_status WHERE user_id = ? LIMIT 1");
  $stmt->execute([$user_id]);
  $row = $stmt->fetch(PDO::FETCH_ASSOC);

  if (!$row) {
    return ['level'=>1, 'exp'=>0, 'attack'=>0, 'defense'=>0];
  }
  return [
    'level'   => (int)$row['level'],
    'exp'     => (int)$row['exp'],
    'attack'  => (int)$row['attack'],
    'defense' => (int)$row['defense'],
  ];
}

/* =========================
   互換ヘルパ（key_name が無くても動く候補取得）
   avatar_create.php の候補一覧に使用
   ========================= */

/** テーブルにカラムが存在するか（MySQL） */
function db_has_column(PDO $pdo, string $table, string $column): bool {
  $st = $pdo->prepare("SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?");
  $st->execute([$table, $column]);
  return (bool)$st->fetchColumn();
}

/**
 * avatar_parts_catalog から slot+gender の候補を「ベースっぽい順」で返す
 * - key_name / is_default / color が無くてもOK
 * - 並び順の優先度: is_default=1 > key_nameに base > image_pathに base
 */
function partOptionsCompat(PDO $pdo, string $slot, string $gender): array {
  $hasKey   = db_has_column($pdo, 'avatar_parts_catalog', 'key_name');
  $hasColor = db_has_column($pdo, 'avatar_parts_catalog', 'color');
  $hasDef   = db_has_column($pdo, 'avatar_parts_catalog', 'is_default');

  $selKey   = $hasKey   ? "key_name" : "'' AS key_name";
  $selColor = $hasColor ? "color"    : "'' AS color";

  $prefExpr = "(
    " . ($hasDef ? "CASE WHEN is_default=1 THEN 3 ELSE 0 END" : "0") . " +
    " . ($hasKey ? "CASE WHEN key_name REGEXP '(^|[_-])base(01)?($|[_-])' THEN 2 ELSE 0 END" : "0") . " +
    CASE WHEN image_path REGEXP 'base(01)?|/base/|_base_|-base' THEN 1 ELSE 0 END
  ) AS pref";

  $sql = "
    SELECT id, slot, $selKey, $selColor, image_path, $prefExpr
    FROM avatar_parts_catalog
    WHERE slot = ?
      AND (gender_scope='unisex' OR gender_scope=?)
    ORDER BY pref DESC, id ASC
  ";
  $st = $pdo->prepare($sql);
  $st->execute([$slot, $gender]);
  return $st->fetchAll(PDO::FETCH_ASSOC);
}
// --- 1) ユーザーの性別を body 画像名から推定（DBに gender カラムが無い前提ならこれで十分） ---
// すでにあるなら差し替え／無ければ追加

// 1) 性別取得：まず avatars.gender、なければ現在装備の outfit 画像パスで推定
function getUserGender(PDO $pdo, int $user_id): string {
  $st = $pdo->prepare("SELECT gender FROM avatars WHERE user_id=? LIMIT 1");
  $st->execute([$user_id]);
  $g = strtolower((string)($st->fetchColumn() ?: ''));
  if ($g === 'male' || $g === 'female') return $g;

  // fallback: いま装備している outfit のパスから推定
  $st = $pdo->prepare("
    SELECT e.image_path
    FROM user_avatar_equipments uae
    JOIN equipments e ON e.id = uae.equipment_id
    WHERE uae.user_id=? AND uae.slot='outfit'
    LIMIT 1
  ");
  $st->execute([$user_id]);
  $p = (string)($st->fetchColumn() ?: '');
  if ($p !== '' && stripos($p, 'female') !== false) return 'female';
  if ($p !== '' && stripos($p, 'male')   !== false) return 'male';

  return 'male'; // 最後のデフォルト
}

// 2) 性別に合った初期 outfit の equipment_id を見つける
function findInitialOutfitId(PDO $pdo, string $gender): ?int {
  // gender_scope カラムがあればそれを優先（無ければ画像パスで判定）
  $hasGenderScope = (bool)$pdo->query("
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME='equipments' AND COLUMN_NAME='gender_scope'
  ")->fetchColumn();

  if ($hasGenderScope) {
    $st = $pdo->prepare("
      SELECT id
      FROM equipments
      WHERE slot='outfit' AND COALESCE(is_initial,0)=1
        AND (gender_scope=? OR gender_scope='unisex')
      ORDER BY (gender_scope='unisex') ASC, id ASC
      LIMIT 1
    ");
    $st->execute([$gender]);
    if ($id = $st->fetchColumn()) return (int)$id;
  }

  // 画像パス名で厳密に
  if ($gender === 'female') {
    $st = $pdo->query("
      SELECT id FROM equipments
      WHERE slot='outfit' AND COALESCE(is_initial,0)=1
        AND image_path REGEXP '(?i)(^|[_/])female([_.]|$)'
      ORDER BY id ASC LIMIT 1
    ");
    if ($id = $st->fetchColumn()) return (int)$id;
  } else {
    $st = $pdo->query("
      SELECT id FROM equipments
      WHERE slot='outfit' AND COALESCE(is_initial,0)=1
        AND image_path REGEXP '(?i)(^|[_/])male([_.]|$)'
      ORDER BY id ASC LIMIT 1
    ");
    if ($id = $st->fetchColumn()) return (int)$id;
  }

  // それでも無ければ is_initial=1 の先頭
  $st = $pdo->query("
    SELECT id FROM equipments
    WHERE slot='outfit' AND COALESCE(is_initial,0)=1
    ORDER BY id ASC LIMIT 1
  ");
  return ($id = $st->fetchColumn()) ? (int)$id : null;
}

// --- 3) まだ outfit を装備していなければ、性別に合った初期 outfit を装備として保存 ---
// functions.php
function ensureInitialOutfitEquipped(PDO $pdo, int $user_id): void {
  // 既に outfit 装備があれば何もしない
  $st = $pdo->prepare("SELECT 1 FROM user_avatar_equipments WHERE user_id=? AND slot='outfit' LIMIT 1");
  $st->execute([$user_id]);
  if ($st->fetchColumn()) return;

  $gender  = getUserGender($pdo, $user_id);
  $outfitId = findInitialOutfitId($pdo, $gender);
  if (!$outfitId) return;

  $ins = $pdo->prepare("INSERT INTO user_avatar_equipments (user_id, slot, equipment_id) VALUES (?, 'outfit', ?)");
  $ins->execute([$user_id, $outfitId]);
}

// --- 4) どの画面でも同じ描画入力を得るユーティリティ（手パーツもまとめて取得） ---
function loadAvatarStacks(PDO $pdo, int $user_id): array {
  // hand_* を含めて取得（DBにあるものだけ）
  $parts = fetchSelectedPartsBySlots($pdo, $user_id, ['body','hair','eyes','mouth','hand_base','hand_weapon']);

  // 装備（DBそのまま）
  $equip = fetchEquipPaths($pdo, $user_id);

  return [$parts, $equip];
}

/* 既存の equip_img_src() をそのまま活かします（無ければ簡易版） */
if (!function_exists('equip_img_src')) {
  function equip_img_src(?string $p): string {
    if (!$p) return '';
    $p = trim($p);
    if (preg_match('~^https?://~i', $p)) return $p;
    return ltrim($p, '/');
  }
}

/** スロットの正規化 */
if (!function_exists('normalize_slot')) {
  function normalize_slot(string $slot, array $allowed): string {
    return in_array($slot, $allowed, true) ? $slot : '';
  }
}

/** 装備中（ユーザー）の取得：id と code（key_name）を返す */
if (!function_exists('fetchEquipped')) {
  function fetchEquipped(PDO $pdo, int $user_id): array {
    $sql = "
      SELECT ue.slot, ue.equip_id, ec.key_name AS code
      FROM user_equips ue
      JOIN equip_catalog ec ON ec.id = ue.equip_id
      WHERE ue.user_id = ?
    ";
    $st = $pdo->prepare($sql);
    $st->execute([$user_id]);
    $codes = []; $ids = [];
    foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $r) {
      $slot = (string)$r['slot'];
      $codes[$slot] = (string)$r['code'];
      $ids[$slot]   = (int)$r['equip_id'];
    }
    return ['codes'=>$codes, 'ids'=>$ids];
  }
}

/** スロットごとのカタログ（UIパス用の列も取得） */
if (!function_exists('fetchCatalogBySlot')) {
  function fetchCatalogBySlot(PDO $pdo, array $slots): array {
    if (empty($slots)) return [];
    $in = implode(',', array_fill(0, count($slots), '?'));
    $sql = "
      SELECT id, slot, key_name, name, icon_path, avatar_path
      FROM equip_catalog
      WHERE slot IN ($in)
      ORDER BY slot, id
    ";
    $st = $pdo->prepare($sql);
    $st->execute($slots);

    $out = array_fill_keys($slots, []);
    foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $r) {
      $slot = (string)$r['slot'];
      $out[$slot][] = [
        'id'          => (int)$r['id'],
        'slot'        => $slot,
        'key_name'    => (string)$r['key_name'],
        'name'        => (string)($r['name'] ?? $r['key_name']),
        'icon_path'   => (string)($r['icon_path'] ?? ''),   // ファイル名（例: sword_iron.png）
        'avatar_path' => (string)($r['avatar_path'] ?? ''), // ファイル名（例: sword_iron.png）
      ];
    }
    return $out;
  }
}

/** スロットごとの未装備アイコン（ファイル名） */
if (!function_exists('fetchSlotEmptyIcons')) {
  function fetchSlotEmptyIcons(PDO $pdo, array $slots): array {
    if (empty($slots)) return [];
    $in = implode(',', array_fill(0, count($slots), '?'));
    $sql = "SELECT slot, empty_icon_path FROM equip_slot_master WHERE slot IN ($in)";
    $st = $pdo->prepare($sql);
    $st->execute($slots);
    $out = [];
    foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $r) {
      $out[(string)$r['slot']] = (string)$r['empty_icon_path']; // 例: empty.png
    }
    // 足りないスロットはデフォルトを補完
    foreach ($slots as $s) {
      if (!isset($out[$s])) $out[$s] = 'empty.png';
    }
    return $out;
  }
}

/**
 * UI 用の画像パスを生成（プロジェクト仕様に合わせた “やり直し版”）
 *
 * 種別:
 *  - 'list_icon'       : 一覧アイコン → assets/icons/{slot}/{icon_path} (装備あり)
 *  - 'preview_equip'   : プレビュー（装備中）→ assets/avatars/{slot}/{avatar_path}
 *  - 'preview_empty'   : プレビュー（未装備）→ assets/icons/{slot}/{empty_icon_path}
 *
 * $row は fetchCatalogBySlot() の 1 行（装備アイテム）。未装備時は null で呼び出し。
 */
if (!function_exists('equip_ui_path')) {
  function equip_ui_path(string $type, string $slot, ?array $row, array $slotEmptyMap): string {
    $slot = trim($slot);
    switch ($type) {
      case 'list_icon':
        if ($row && !empty($row['icon_path'])) {
          return equip_img_src("assets/icons/{$slot}/{$row['icon_path']}");
        }
        break;

      case 'preview_equip':
        if ($row && !empty($row['avatar_path'])) {
          return equip_img_src("assets/avatars/{$slot}/{$row['avatar_path']}");
        }
        break;

      case 'preview_empty':
        $empty = $slotEmptyMap[$slot] ?? 'empty.png';
        return equip_img_src("assets/icons/{$slot}/{$empty}");
    }
    // フォールバック（DB未整備時の保険）
    if ($type === 'list_icon' && $row && !empty($row['key_name'])) {
      return equip_img_src("assets/icons/{$slot}/{$row['key_name']}.png");
    }
    if ($type === 'preview_equip' && $row && !empty($row['key_name'])) {
      return equip_img_src("assets/avatars/{$slot}/{$row['key_name']}.png");
    }
    if ($type === 'preview_empty') {
      return equip_img_src("assets/icons/{$slot}/empty.png");
    }
    return '';
  }
}

/** 装備の更新（装備/解除） */
if (!function_exists('updateUserEquipForSlot')) {
  function updateUserEquipForSlot(PDO $pdo, int $user_id, string $slot, $equip_id): void {
    $pdo->beginTransaction();
    try {
      $del = $pdo->prepare("DELETE FROM user_equips WHERE user_id=? AND slot=?");
      $del->execute([$user_id, $slot]);
      if (!empty($equip_id)) {
        $ins = $pdo->prepare("INSERT INTO user_equips (user_id, slot, equip_id) VALUES (?, ?, ?)");
        $ins->execute([$user_id, $slot, (int)$equip_id]);
      }
      $pdo->commit();
    } catch (Throwable $e) {
      if ($pdo->inTransaction()) $pdo->rollBack();
      throw $e;
    }
  }
}
