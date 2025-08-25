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
function fetchSelectedPartsBySlots(PDO $pdo, int $userId, array $slots): array {
  if (empty($slots)) return [];
  $in = implode(',', array_fill(0, count($slots), '?'));
  $params = array_merge([$userId], $slots);

  $sql = "SELECT u.slot, c.image_path
          FROM user_avatar_parts u
          JOIN avatar_parts_catalog c ON u.part_id=c.id
          WHERE u.user_id=? AND u.slot IN ($in)";
  $st = $pdo->prepare($sql);
  $st->execute($params);

  $out = [];
  foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $r) {
    $out[$r['slot']] = $r['image_path'];
  }
  return $out;
}

/**
 * 装備の image_path を slot => path で返す（ダッシュボードやプレビュー用）
 */
function fetchEquipPaths(PDO $pdo, int $userId): array {
  $st = $pdo->prepare("
    SELECT uae.slot AS slot, e.image_path AS path
    FROM user_avatar_equipments uae
    JOIN equipments e ON e.id = uae.equipment_id
    WHERE uae.user_id = ?
  ");
  $st->execute([$userId]);

  $out = [];
  foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $r) {
    $out[$r['slot']] = $r['path'];
  }
  return $out;
}

/**
 * 旧・簡易レンダラ（顔パーツだけ）：
 * レイヤー順：body → hair → eyes → mouth
 */
function renderAvatarLayers(array $bySlot): string {
  $order = ['body','hair','eyes','mouth'];
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
function renderAvatarFull(array $partsBySlot, array $equipBySlot = []): string {
  // hand 自動切替（画像パスを partsBySlot['hand'] に確定させる）
  $hasWeapon = !empty($equipBySlot['weapon']);
  if ($hasWeapon) {
    if (!empty($partsBySlot['hand_weapon'])) {
      $partsBySlot['hand'] = $partsBySlot['hand_weapon'];
    } elseif (!empty($partsBySlot['hand_base'])) {
      $partsBySlot['hand'] = $partsBySlot['hand_base'];
    }
  } else {
    if (!empty($partsBySlot['hand_base'])) {
      $partsBySlot['hand'] = $partsBySlot['hand_base'];
    } elseif (!empty($partsBySlot['hand_weapon'])) {
      $partsBySlot['hand'] = $partsBySlot['hand_weapon'];
    }
  }

  $order = ['body','hair','eyes','mouth','outfit','weapon','hand'];

  $html = '<div class="avatar-container">';
  foreach ($order as $slot) {
    $src = $partsBySlot[$slot] ?? $equipBySlot[$slot] ?? null;
    if (!$src) continue;
    $html .= '<img class="avatar-layer slot-'.$slot.'" src="'.
             htmlspecialchars($src, ENT_QUOTES, 'UTF-8').'" alt="'.$slot.'">';
  }
  $html .= '</div>';
  return $html;
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
function getAvatarStatusWithEquip(PDO $pdo, int $user_id): array {
  // 基本ステータス
  $stmt = $pdo->prepare("
    SELECT
      COALESCE(level, 1)   AS level,
      COALESCE(exp, 0)     AS exp,
      COALESCE(attack, 0)  AS base_attack,
      COALESCE(defense, 0) AS base_defense
    FROM avatars_status
    WHERE user_id = ?
    LIMIT 1
  ");
  $stmt->execute([$user_id]);
  $base = $stmt->fetch(PDO::FETCH_ASSOC) ?: ['level'=>1,'exp'=>0,'base_attack'=>0,'base_defense'=>0];

  // 装備加算
  $stmt = $pdo->prepare("
    SELECT
      COALESCE(SUM(e.attack), 0)  AS equip_attack,
      COALESCE(SUM(e.defense), 0) AS equip_defense
    FROM user_avatar_equipments uae
    JOIN equipments e ON uae.equipment_id = e.id
    WHERE uae.user_id = ?
  ");
  $stmt->execute([$user_id]);
  $equip = $stmt->fetch(PDO::FETCH_ASSOC) ?: ['equip_attack'=>0,'equip_defense'=>0];

  return [
    'level'        => (int)$base['level'],
    'exp'          => (int)$base['exp'],
    'attack'       => (int)$base['base_attack']  + (int)$equip['equip_attack'],
    'defense'      => (int)$base['base_defense'] + (int)$equip['equip_defense'],
    'base_attack'  => (int)$base['base_attack'],
    'base_defense' => (int)$base['base_defense'],
    'equip_attack' => (int)$equip['equip_attack'],
    'equip_defense'=> (int)$equip['equip_defense'],
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
