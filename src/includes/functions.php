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
// 現在の選択を取得
function fetchSelectedParts(PDO $pdo, int $user_id): array {
  $stmt = $pdo->prepare("
    SELECT u.slot, c.image_path
    FROM user_avatar_parts u
    JOIN avatar_parts_catalog c ON u.part_id = c.id
    WHERE u.user_id = ?
  ");
  $stmt->execute([$user_id]);
  $bySlot = [];
  foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
    $bySlot[$row['slot']] = $row['image_path'];
  }
  return $bySlot;
}
function renderAvatarLayers(array $bySlot): string {
  $order = [
    'body_base',   // 性別によって差し替わる想定
    'hair_back',
    'eye_left','eye_right',
    'nose',
    'mouth',
    'hair_front',
  ];
  $html = '<div class="avatar-container">';
  foreach ($order as $slot) {
    if (!empty($bySlot[$slot])) {
      $src = htmlspecialchars($bySlot[$slot], ENT_QUOTES, 'UTF-8');
      $html .= '<img class="avatar-layer slot-'.$slot.'" src="'.$src.'" alt="'.$slot.'">';
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