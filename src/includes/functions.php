<?php

function calculateUserStats($pdo, $user_id) {
  $stmt = $pdo->prepare("
    SELECT e.attack, e.defense
    FROM user_avatar_equipments uae
    JOIN equipments e ON uae.equipment_id = e.id
    WHERE uae.user_id = ?
  ");
  $stmt->execute([$user_id]);
  $rows = $stmt->fetchAll();

  $stats = ['attack' => 0, 'defense' => 0];
  foreach ($rows as $row) {
    $stats['attack'] += $row['attack'];
    $stats['defense'] += $row['defense'];
  }
  return $stats;
}
function renderAvatarLayers($equipped) {
  $html = '<div class="avatar-container">';
  $html .= '<img src="avatars/base_body.png" class="avatar-layer">';
  $slots = ['head', 'body', 'weapon', 'shield', 'feet'];
  foreach ($slots as $slot) {
    if (isset($equipped[$slot])) {
      $filename = "{$slot}_" . rawurlencode($equipped[$slot]) . ".png";
      $html .= '<img src="avatars/' . $filename . '" class="avatar-layer">';
    }
  }
  $html .= '</div>';
  return $html;
}

?>