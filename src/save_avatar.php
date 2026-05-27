<?php
// save_avatar.php
declare(strict_types=1);
require_once 'includes/session.php';
require 'includes/db.php';
require 'includes/functions.php'; // findInitialOutfitId を使う

header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['user'])) {
  http_response_code(401);
  echo json_encode(['success'=>false,'error'=>'UNAUTHORIZED']); exit;
}
$user_id = (int)$_SESSION['user']['id'];

// 受信（旧:配列 / 新:{gender, parts} 両対応）
$raw = file_get_contents('php://input');
$payload = json_decode($raw, true);
if ($payload === null) {
  http_response_code(400);
  echo json_encode(['success'=>false,'error'=>'INVALID_JSON']); exit;
}

$genderFromClient = null;
$partsArray = null;
if (is_array($payload) && array_is_list($payload)) {
  $partsArray = $payload;
} elseif (is_array($payload)) {
  if (isset($payload['gender'])) {
    $g = strtolower((string)$payload['gender']);
    if ($g==='male' || $g==='female') $genderFromClient = $g;
  }
  if (isset($payload['parts']) && is_array($payload['parts'])) {
    $partsArray = $payload['parts'];
  }
}
if (!$partsArray) {
  http_response_code(400);
  echo json_encode(['success'=>false,'error'=>'MISSING_PARTS']); exit;
}

// 4スロット整形
$incoming = [];
foreach ($partsArray as $row) {
  if (!isset($row['slot'], $row['part_id'])) continue;
  $slot = (string)$row['slot'];
  $pid  = (int)$row['part_id'];
  if ($pid > 0 && in_array($slot, ['body','hair','eyes','mouth'], true)) {
    $incoming[$slot] = $pid;
  }
}
if (count($incoming) < 4) {
  http_response_code(400);
  echo json_encode(['success'=>false,'error'=>'MISSING_SLOTS']); exit;
}

// トランザクション外で取得（PostgreSQLはトランザクション内でprepare失敗するとABORTED状態になるため）
$gender          = $genderFromClient ?? 'male';
$desiredOutfitId = findInitialOutfitId($pdo, $gender);

try {
  $pdo->beginTransaction();

  // 4スロット保存（置換）
  $pdo->prepare("DELETE FROM user_avatar_parts WHERE user_id=? AND slot IN ('body','hair','eyes','mouth')")
      ->execute([$user_id]);
  error_log('[save_avatar] DELETE OK');
  $insParts = $pdo->prepare("INSERT INTO user_avatar_parts (user_id, slot, part_id) VALUES (?,?,?)");
  foreach ($incoming as $slot => $pid) {
    error_log("[save_avatar] INSERT part: slot=$slot pid=$pid");
    $insParts->execute([$user_id, $slot, $pid]);
  }

  // hand_* 未保存なら補完
  foreach (['hand_base','hand_weapon'] as $hs) {
    $ex = $pdo->prepare("SELECT 1 FROM user_avatar_parts WHERE user_id=? AND slot=? LIMIT 1");
    $ex->execute([$user_id, $hs]);
    if (!$ex->fetchColumn()) {
      $q = $pdo->prepare("
        SELECT id FROM avatar_parts_catalog
        WHERE slot=? ORDER BY COALESCE(is_default,0) DESC, id ASC LIMIT 1
      ");
      $q->execute([$hs]);
      if ($pid = $q->fetchColumn()) $insParts->execute([$user_id, $hs, (int)$pid]);
    }
  }

  // avatars.gender を UPSERT
  $chkA = $pdo->prepare("SELECT id FROM avatars WHERE user_id=? LIMIT 1");
  $chkA->execute([$user_id]);
  if ($aid = $chkA->fetchColumn()) {
    $pdo->prepare("UPDATE avatars SET gender=? WHERE id=?")->execute([$gender, (int)$aid]);
  } else {
    $pdo->prepare("INSERT INTO avatars (user_id, gender) VALUES (?, ?)")->execute([$user_id, $gender]);
  }

  if ($desiredOutfitId) {
    // 現在の outfit が何かを見る
    $cur = $pdo->prepare("
      SELECT equipment_id FROM user_avatar_equipments
      WHERE user_id=? AND slot='outfit' LIMIT 1
    ");
    $cur->execute([$user_id]);
    $currentOutfitId = $cur->fetchColumn();

    if ($currentOutfitId === false) {
      // 未装備: 挿入
      $pdo->prepare("
        INSERT INTO user_avatar_equipments (user_id, slot, equipment_id)
        VALUES (?, 'outfit', ?)
      ")->execute([$user_id, $desiredOutfitId]);
    } elseif ((int)$currentOutfitId !== (int)$desiredOutfitId) {
      // 性別不一致: 更新
      $pdo->prepare("
        UPDATE user_avatar_equipments
        SET equipment_id=?
        WHERE user_id=? AND slot='outfit'
      ")->execute([$desiredOutfitId, $user_id]);
    }
  }

  $pdo->commit();
  echo json_encode(['success'=>true]);
} catch (Throwable $e) {
  if ($pdo->inTransaction()) $pdo->rollBack();
  error_log('[save_avatar] ERROR: ' . $e->getMessage() . ' | File: ' . $e->getFile() . ':' . $e->getLine());
  http_response_code(500);
  echo json_encode(['success'=>false,'error'=>'SERVER_ERROR','detail'=>$e->getMessage()]);
}
