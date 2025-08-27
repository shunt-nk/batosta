<?php
// src/battle.php
declare(strict_types=1);
session_start();
require 'includes/db.php';
require 'includes/functions.php';

if (!isset($_SESSION['user'])) { header('Location: index.php'); exit; }
$me  = (int)$_SESSION['user']['id'];
$vs  = isset($_GET['vs']) ? (int)$_GET['vs'] : 0;
if ($vs <= 0) { header('Location: friend.php'); exit; }

// ざっくり2人の見た目
[$partsMe, $equipMe] = loadAvatarStacks($pdo, $me);
[$partsVs, $equipVs] = loadAvatarStacks($pdo, $vs);
$meHtml = renderAvatarFull($partsMe, $equipMe);
$vsHtml = renderAvatarFull($partsVs, $equipVs);
?>
<!doctype html>
<html lang="ja">
<head>
<meta charset="utf-8"><title>バトル（β）</title>
<style>
  body{background:#2F3C61;color:#fff;font-family:sans-serif;margin:0;display:grid;place-items:center;height:100dvh;}
  .arena{display:flex;gap:60px;align-items:center;}
  .avatar-container{position:relative;width:200px;height:200px}
  .avatar-container img{position:absolute;inset:0;width:100%;height:100%;object-fit:contain}
  .vs{font-size:42px;font-weight:900;opacity:.8}
</style>
</head>
<body>
  <div class="arena">
    <div><?= $meHtml ?></div>
    <div class="vs">VS</div>
    <div><?= $vsHtml ?></div>
  </div>
</body>
</html>
