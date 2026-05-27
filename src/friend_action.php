<?php
// src/friend_action.php
declare(strict_types=1);
require_once 'includes/session.php';
require 'includes/db.php';

if (!isset($_SESSION['user'])) { header('Location: index.php'); exit; }
$me = (int)$_SESSION['user']['id'];

$action   = $_POST['action']   ?? '';
$targetId = (int)($_POST['target_id'] ?? 0);
if ($targetId <= 0 || $targetId === $me) { header('Location: friend.php'); exit; }

switch ($action) {
  case 'send_request':
    // 既存の保留があれば放置、なければ作成
    $st = $pdo->prepare("SELECT id,status FROM friend_requests WHERE requester_id=? AND addressee_id=?");
    $st->execute([$me, $targetId]);
    $row = $st->fetch(PDO::FETCH_ASSOC);
    if (!$row) {
      $ins = $pdo->prepare("INSERT INTO friend_requests (requester_id, addressee_id) VALUES (?,?)");
      $ins->execute([$me, $targetId]);
    }
    break;

  case 'accept_request':
    // 相手→自分 の pending を accepted にし、friends を双方に作成
    $pdo->beginTransaction();
    try {
      $upd = $pdo->prepare("UPDATE friend_requests SET status='accepted' WHERE requester_id=? AND addressee_id=? AND status='pending'");
      $upd->execute([$targetId, $me]);

      // friends（重複防止）
      foreach ([[ $me, $targetId ], [ $targetId, $me ]] as $pair) {
        $chk = $pdo->prepare("SELECT 1 FROM friends WHERE user_id=? AND friend_id=?");
        $chk->execute($pair);
        if (!$chk->fetchColumn()) {
          $ins = $pdo->prepare("INSERT INTO friends (user_id, friend_id) VALUES (?,?)");
          $ins->execute($pair);
        }
      }
      $pdo->commit();
    } catch (\Throwable $e) {
      $pdo->rollBack();
    }
    break;

  case 'reject_request':
    $upd = $pdo->prepare("UPDATE friend_requests SET status='rejected' WHERE requester_id=? AND addressee_id=? AND status='pending'");
    $upd->execute([$targetId, $me]);
    break;

  case 'battle_request':
    // ここでは“申込を送った”ことだけ反映（本格的な対戦は別画面へ）
    // 相手と自分を一時的に battlling にする（UI 用）
    $upd = $pdo->prepare("UPDATE users SET presence='battling' WHERE id IN (?,?)");
    $upd->execute([$me, $targetId]);

    // 将来の拡張に：battle_rooms を作ったらそこへ遷移
    header('Location: battle.php?vs=' . $targetId);
    exit;

  default:
    // 何もしない
    break;
}

header('Location: friend.php');
