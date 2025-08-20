<?php
// save_avatar.php
session_start();
require 'includes/db.php';
if (!isset($_SESSION['user'])) { http_response_code(401); exit; }
$user_id = $_SESSION['user']['id'];

$payload = json_decode(file_get_contents('php://input'), true);
$pdo->beginTransaction();
try{
  $stmt = $pdo->prepare("REPLACE INTO user_avatar_parts (user_id, slot, part_id) VALUES (?, ?, ?)");
  foreach ($payload as $row) {
    $stmt->execute([$user_id, $row['slot'], $row['part_id']]);
  }
  $pdo->commit();
  echo json_encode(['success'=>true]);
}catch(Exception $e){
  $pdo->rollBack();
  http_response_code(500);
  echo json_encode(['success'=>false,'error'=>$e->getMessage()]);
}
