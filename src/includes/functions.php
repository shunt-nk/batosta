<?php
declare(strict_types=1);

/* ============================================================
   共通：パス系ユーティリティ（全ページで共通の振る舞い）
   ============================================================ */

/** http/https はそのまま、それ以外は先頭のスラッシュを削除して返す */
if (!function_exists('equip_img_src')) {
  function equip_img_src(?string $p): string {
    if (!$p) return '';
    $p = trim($p);
    if ($p === '') return '';
    if (preg_match('~^https?://~i', $p)) return $p;
    return ltrim($p, '/');
  }
}

/** 「ファイル名のみ or サブパス or フルパス」を安全に avatars 直下へ解決 */
function build_avatar_src(string $slot, ?string $file): string {
  $file = trim((string)$file);
  if ($file === '') return '';
  // すでに assets/ から始まる（先頭スラッシュあり・なし両対応）or 絶対URL の場合はそのまま整形
  if (preg_match('~^(/?assets/|https?://)~i', $file)) {
    return equip_img_src($file);
  }
  // サブパスを含む（例: weapon/xxx.png, outfit/base/xxx.png）
  if (strpos($file, '/') !== false) {
    return equip_img_src('assets/avatars/'.$file);
  }
  // ファイル名のみ（slot ディレクトリを付与）
  return equip_img_src('assets/avatars/'.$slot.'/'.$file);
}

/** icons 版（一覧・バッジ・未装備アイコンに使用） */
function build_icon_src(string $slot, ?string $file): string {
  $file = trim((string)$file);
  if ($file === '') return '';
  if (preg_match('~^(/?assets/|https?://)~i', $file)) {
    return equip_img_src($file);
  }
  if (strpos($file, '/') !== false) {
    return equip_img_src('assets/icons/'.$file);
  }
  return equip_img_src('assets/icons/'.$slot.'/'.$file);
}

/** DB で使うスロット名の正規化（UI と DB の差異吸収：armor→outfit） */
function normalize_db_slot(string $slot): string {
  return $slot === 'armor' ? 'outfit' : $slot;
}

/* ============================================================
   アバター：存在確認・パーツ取得
   ============================================================ */

/** users ごとの body パーツが保存済みか */
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
    $bySlot[$r['slot']] = equip_img_src((string)$r['image_path']);
  }
  return $bySlot;
}

/** 任意スロットだけまとめて取得（hand_base/hand_weapon など追加取得用） */
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
  foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $r) {
    $out[$r['slot']] = equip_img_src((string)$r['image_path']);
  }
  return $out;
}

/* ============================================================
   装備：取得（全ページ共通で “プレビュー＝avatars” を返す）
   ============================================================ */

/**
 * 装備のプレビュー画像（avatar用）を slot => src で返す
 * - 優先順: equipments.avatar_path があればそれ → なければ equipments.image_path を avatars に解決
 * - slot は armor を outfit に正規化
 * - 戻り値は <img src=""> にそのまま入れられるパス
 */
function fetchEquipPaths(PDO $pdo, int $user_id): array {
  // avatar_path / icon_path が存在しない環境でも動くように SELECT で例外吸収
  $rows = [];
  try {
    $st = $pdo->prepare("
      SELECT uae.slot AS _slot,
             e.image_path,
             e.avatar_path
      FROM user_avatar_equipments uae
      JOIN equipments e ON e.id = uae.equipment_id
      WHERE uae.user_id = ?
    ");
    $st->execute([$user_id]);
    $rows = $st->fetchAll(PDO::FETCH_ASSOC);
  } catch (\Throwable $e) {
    // avatar_path が無い古い環境：image_path のみ取得
    $st = $pdo->prepare("
      SELECT uae.slot AS _slot,
             e.image_path,
             '' AS avatar_path
      FROM user_avatar_equipments uae
      JOIN equipments e ON e.id = uae.equipment_id
      WHERE uae.user_id = ?
    ");
    $st->execute([$user_id]);
    $rows = $st->fetchAll(PDO::FETCH_ASSOC);
  }

  $out = [];
  foreach ($rows as $r) {
    $slot = normalize_db_slot((string)$r['_slot']);   // armor → outfit
    $avatarFile = trim((string)($r['avatar_path'] ?? ''));
    $imagePath  = trim((string)($r['image_path'] ?? ''));
    $src = $avatarFile !== ''
      ? build_avatar_src($slot, $avatarFile)
      : build_avatar_src($slot, $imagePath); // 旧互換（image_path を avatars に解決）

    if ($src !== '') $out[$slot] = $src;
  }
  return $out;
}

/* ============================================================
   アバター描画：どの画面でも同じ表示
   ============================================================ */

/**
 * 旧・簡易レンダラ（顔パーツだけ）
 * レイヤー順：body → hair → eyes → mouth → hand（あれば）
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
 * レイヤー順：body > hair > eyes > mouth > outfit > head > shield > weapon > hand > boots
 * - $partsBySlot は avatar_parts_catalog の image_path をそのまま（プロジェクト既存前提）
 * - $equipBySlot は fetchEquipPaths() の戻り（= プレビュー用に avatars へ解決済み）
 */
function renderAvatarFull(array $partsBySlot, array $equipBySlot = []): string {
  // hand の自動切替
  if (!empty($equipBySlot['weapon'])) {
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

  $order = ['body','hair','eyes','mouth','outfit','head','shield','weapon','hand','boots'];
  $html = '<div class="avatar-container">';
  foreach ($order as $slot) {
    $src = $partsBySlot[$slot] ?? $equipBySlot[$slot] ?? null;
    if (!$src) continue;
    $html .= '<img class="avatar-layer slot-'.$slot.'" src="'.htmlspecialchars($src, ENT_QUOTES, 'UTF-8').'" alt="'.$slot.'">';
  }
  $html .= '</div>';
  return $html;
}

/* ============================================================
   ステータス
   ============================================================ */

/** 必要経験値（任意に使用） */
function requiredExp(int $level): int {
  return 100 + max(0, $level - 1) * 20;
}

/** 基本＋装備の合算ステータス（欠損時でも安全値で返す） */
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

/** 基本ステータスのみ（欠損時は既定値で返す） */
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

/* ============================================================
   初期 outfit 装備（他ページでも同じ表示になるよう最低限の整備）
   ============================================================ */

/** ユーザーの性別（avatars.gender が無ければ outfit の画像パス名から推定） */
function getUserGender(PDO $pdo, int $user_id): string {
  try {
    $st = $pdo->prepare("SELECT gender FROM avatars WHERE user_id=? LIMIT 1");
    $st->execute([$user_id]);
    $g = strtolower((string)($st->fetchColumn() ?: ''));
    if ($g === 'male' || $g === 'female') return $g;
  } catch (\Throwable $e) { /* avatars テーブルが無い環境も想定 */ }

  // fallback: 現在装備の outfit のパスから推定
  $st = $pdo->prepare("
    SELECT e.image_path
    FROM user_avatar_equipments uae
    JOIN equipments e ON e.id = uae.equipment_id
    WHERE uae.user_id=? AND uae.slot IN ('outfit','armor')
    LIMIT 1
  ");
  $st->execute([$user_id]);
  $p = (string)($st->fetchColumn() ?: '');
  if ($p !== '' && stripos($p, 'female') !== false) return 'female';
  if ($p !== '' && stripos($p, 'male')   !== false) return 'male';

  return 'male'; // 最後のデフォルト
}

/** 性別に合った初期 outfit の equipment_id を取得（なければ null） */
function findInitialOutfitId(PDO $pdo, string $gender): ?int {
  // gender_scope がある場合のみ使う（ない環境でも例外で落ちないように 2段階）
  try {
    $st = $pdo->prepare("
      SELECT id
      FROM equipments
      WHERE slot IN ('outfit','armor') AND COALESCE(is_initial,0)=1
        AND (gender_scope=? OR gender_scope='unisex')
      ORDER BY (gender_scope='unisex') ASC, id ASC
      LIMIT 1
    ");
    $st->execute([$gender]);
    $id = $st->fetchColumn();
    if ($id) return (int)$id;
  } catch (\Throwable $e) {
    // gender_scope 無しの環境：画像名から male/female を推定
    if ($gender === 'female') {
      $st = $pdo->query("
        SELECT id FROM equipments
        WHERE slot IN ('outfit','armor') AND COALESCE(is_initial,0)=1
          AND image_path ~* '(^|[_/])female([_.]|$)'
        ORDER BY id ASC LIMIT 1
      ");
      $id = $st->fetchColumn();
      if ($id) return (int)$id;
    } else {
      $st = $pdo->query("
        SELECT id FROM equipments
        WHERE slot IN ('outfit','armor') AND COALESCE(is_initial,0)=1
          AND image_path ~* '(^|[_/])male([_.]|$)'
        ORDER BY id ASC LIMIT 1
      ");
      $id = $st->fetchColumn();
      if ($id) return (int)$id;
    }
  }

  // それでも無ければ is_initial=1 の先頭
  $st = $pdo->query("
    SELECT id FROM equipments
    WHERE slot IN ('outfit','armor') AND COALESCE(is_initial,0)=1
    ORDER BY id ASC LIMIT 1
  ");
  $id = $st->fetchColumn();
  return $id ? (int)$id : null;
}

/** outfit を未装備なら、性別に合った初期 outfit を装備として保存 */
function ensureInitialOutfitEquipped(PDO $pdo, int $user_id): void {
  $st = $pdo->prepare("SELECT 1 FROM user_avatar_equipments WHERE user_id=? AND slot IN ('outfit','armor') LIMIT 1");
  $st->execute([$user_id]);
  if ($st->fetchColumn()) return;

  $gender   = getUserGender($pdo, $user_id);
  $outfitId = findInitialOutfitId($pdo, $gender);
  if (!$outfitId) return;

  // armor で登録されているケースにも合わせて outfit で保存
  $ins = $pdo->prepare("INSERT INTO user_avatar_equipments (user_id, slot, equipment_id) VALUES (?, 'outfit', ?)");
  $ins->execute([$user_id, $outfitId]);
}

/** どの画面でも同じ描画入力を得る（手パーツもまとめて取得） */
function loadAvatarStacks(PDO $pdo, int $user_id): array {
  // hand_* を含めて取得（DBにあるものだけを想定）
  $parts = fetchSelectedPartsBySlots($pdo, $user_id, ['body','hair','eyes','mouth','hand_base','hand_weapon']);
  // 装備（プレビュー＝avatars に解決済みの src）
  $equip = fetchEquipPaths($pdo, $user_id);
  return [$parts, $equip];
}
