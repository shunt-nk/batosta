<?php
// save_avatar.php
declare(strict_types=1);

session_start();
require 'includes/db.php';
header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['user'])) {
  http_response_code(401);
  echo json_encode(['success'=>false, 'error'=>'unauthorized']);
  exit;
}
$user_id = (int)$_SESSION['user']['id'];

// 受信JSONを確認
$payload = json_decode(file_get_contents('php://input'), true);
if (!is_array($payload)) {
  http_response_code(400);
  echo json_encode(['success'=>false, 'error'=>'bad payload']);
  exit;
}

// 必須スロット（作成画面の4パーツ）
$required = ['body', 'hair', 'eyes', 'mouth'];
$got = array_map(static function($r){ return (string)($r['slot'] ?? ''); }, $payload);
$missing = array_values(array_diff($required, $got));
if (!empty($missing)) {
  http_response_code(400);
  echo json_encode(['success'=>false, 'error'=>'missing slots: '.implode(',', $missing)]);
  exit;
}

try {
  $pdo->beginTransaction();

  // 1) ユーザーのベースパーツを保存（UPSERT）
  $ins = $pdo->prepare("
    REPLACE INTO user_avatar_parts (user_id, slot, part_id)
    VALUES (?, ?, ?)
  ");
  foreach ($payload as $row) {
    $slot    = (string)($row['slot']    ?? '');
    $part_id = (int)   ($row['part_id'] ?? 0);
    if ($slot === '' || $part_id <= 0) continue;
    $ins->execute([$user_id, $slot, $part_id]);
  }

  // 2) body の画像パスから性別を推定（female/_f を含めば女性）
  $st = $pdo->prepare("
    SELECT c.image_path
    FROM user_avatar_parts u
    JOIN avatar_parts_catalog c ON c.id = u.part_id
    WHERE u.user_id = ? AND u.slot = 'body'
    LIMIT 1
  ");
  $st->execute([$user_id]);
  $bodyPath = (string)($st->fetchColumn() ?: '');
  $isFemale = (strpos($bodyPath, 'female') !== false || strpos($bodyPath, '_f') !== false);

  // 3) outfit スロットの存在を確認
  $chk = $pdo->prepare("SELECT COUNT(*) FROM equipments WHERE slot = 'outfit' LIMIT 1");
  $chk->execute();
  $hasOutfitSlot = (bool)$chk->fetchColumn();

  // 4) 初期服の自動装備（key_name が無くても image_path 優先で拾う）
  if ($hasOutfitSlot) {
    $equipId = null;

    // 4-1) 画像パスに gender を含むものを優先
    $likeGender = $isFemale ? '%female%' : '%male%';
    $q1 = $pdo->prepare("
      SELECT id FROM equipments
      WHERE slot = 'outfit' AND image_path LIKE ?
      ORDER BY id ASC LIMIT 1
    ");
    $q1->execute([$likeGender]);
    $equipId = $q1->fetchColumn();

    // 4-2) 無ければ base01 に近い名前（image_path 検索）
    if (empty($equipId)) {
      $q2 = $pdo->prepare("
        SELECT id FROM equipments
        WHERE slot = 'outfit' AND image_path REGEXP 'outfit.*base(01)?'
        ORDER BY id ASC LIMIT 1
      ");
      $q2->execute();
      $equipId = $q2->fetchColumn();
    }

    // 4-3) それでも無ければ slot='outfit' の最小ID
    if (empty($equipId)) {
      $q3 = $pdo->prepare("SELECT id FROM equipments WHERE slot='outfit' ORDER BY id ASC LIMIT 1");
      $q3->execute();
      $equipId = $q3->fetchColumn();
    }

    // 4-4) 見つかったら outfit を UPSERT
    if (!empty($equipId)) {
      // (user_id, slot) に UNIQUE がある前提（なければ追加推奨）
      $up = $pdo->prepare("
        INSERT INTO user_avatar_equipments (user_id, slot, equipment_id)
        VALUES (?, 'outfit', ?)
        ON DUPLICATE KEY UPDATE equipment_id = VALUES(equipment_id)
      ");
      $up->execute([$user_id, (int)$equipId]);
    }
  }

  $pdo->commit();
  echo json_encode(['success'=>true]);

} catch (Throwable $e) {
  if ($pdo->inTransaction()) $pdo->rollBack();
  http_response_code(500);
  echo json_encode(['success'=>false, 'error'=>$e->getMessage()]);
}
