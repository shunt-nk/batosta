<?php

function getAvatarStatusWithEquip($pdo, $user_id) {
  // 基本ステータス（avatars_status）
  $stmt = $pdo->prepare("SELECT level, exp, attack AS base_attack, defense AS base_defense FROM avatars_status WHERE user_id = ?");
  $stmt->execute([$user_id]);
  $base = $stmt->fetch(PDO::FETCH_ASSOC);
  if (!$base) {
    $base = ['level' => 1, 'exp' => 0, 'base_attack' => 0, 'base_defense' => 0];
  }

  // 装備による加算ステータス
  $stmt = $pdo->prepare("
    SELECT SUM(e.attack) AS equip_attack, SUM(e.defense) AS equip_defense
    FROM user_avatar_equipments uae
    JOIN equipments e ON uae.equipment_id = e.id
    WHERE uae.user_id = ?
  ");
  $stmt->execute([$user_id]);
  $equip = $stmt->fetch(PDO::FETCH_ASSOC);
  if (!$equip) {
    $equip = ['equip_attack' => 0, 'equip_defense' => 0];
  }

  return [
    'level' => (int)$base['level'],
    'exp' => (int)$base['exp'],
    'attack' => (int)$base['base_attack'] + (int)$equip['equip_attack'],
    'defense' => (int)$base['base_defense'] + (int)$equip['equip_defense'],
    'base_attack' => (int)$base['base_attack'],
    'base_defense' => (int)$base['base_defense'],
    'equip_attack' => (int)$equip['equip_attack'],
    'equip_defense' => (int)$equip['equip_defense']
  ];
}
function renderAvatarLayers($equipped) {
  $html = '<div class="avatar-container">';
  $html .= '<img src="assets/avatars/base.png" class="avatar-layer">';
  $slots = ['head', 'body', 'weapon', 'shield', 'feet'];
  foreach ($slots as $slot) {
    if (!empty($equipped[$slot]['image_path'])) {
      $path = htmlspecialchars($equipped[$slot]['image_path']);
      $timestamp = time(); // キャッシュ防止
      $html .= '<img src="assets/avatars/' . $path . '?v=' . $timestamp . '" class="avatar-layer slot-' . $slot . '">';
    }
  }
  $html .= '</div>';
  return $html;
}
function getAvatarStatus($pdo, $user_id) {
  $stmt = $pdo->prepare("SELECT * FROM avatars_status WHERE user_id = ?");
  $stmt->execute([$user_id]);
  $status = $stmt->fetch();


  return $status;
}

?>