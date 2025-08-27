<?php
// includes/skill_repo.php
declare(strict_types=1);

/**
 * 利用候補スキルを Level と個別付与で取得（学習済み or レベル到達）。
 * $opts['battle_id']/$opts['user_id'] を渡すと、残回数やCDもJOINして usable 判定が付く。
 */
function fetch_available_skills(PDO $pdo, int $userId, int $level, array $opts = []): array {
  $withState = isset($opts['battle_id'], $opts['user_id']);
  if ($withState) {
    $sql = "
      SELECT s.*,
             bss.charges_left, bss.cd_remaining,
             /* usable 判定（CD0 & 残回数OK は true、NULL=制限なし） */
             (CASE
                WHEN IFNULL(bss.cd_remaining,0) > 0 THEN 0
                WHEN s.max_charges IS NULL THEN 1
                WHEN bss.charges_left IS NULL THEN 1
                WHEN bss.charges_left > 0 THEN 1
                ELSE 0
              END) AS is_usable
      FROM skills s
      LEFT JOIN user_skills us
        ON us.user_id = :uid AND us.skill_id = s.id
      LEFT JOIN battle_skill_state bss
        ON bss.battle_id = :btl AND bss.user_id = :uid AND bss.skill_id = s.id
      WHERE s.min_level <= :lvl OR us.user_id IS NOT NULL
      ORDER BY s.action_class, s.min_level, s.id
    ";
    $st = $pdo->prepare($sql);
    $st->execute([':uid'=>$opts['user_id'], ':btl'=>$opts['battle_id'], ':lvl'=>$level]);
  } else {
    $sql = "
      SELECT s.*, NULL AS charges_left, NULL AS cd_remaining, 1 AS is_usable
      FROM skills s
      LEFT JOIN user_skills us
        ON us.user_id = :uid AND us.skill_id = s.id
      WHERE s.min_level <= :lvl OR us.user_id IS NOT NULL
      ORDER BY s.action_class, s.min_level, s.id
    ";
    $st = $pdo->prepare($sql);
    $st->execute([':uid'=>$userId, ':lvl'=>$level]);
  }
  $rows = $st->fetchAll(PDO::FETCH_ASSOC);
  // action_class ごとにまとめる
  $out = ['attack'=>[], 'guard'=>[], 'heal'=>[]];
  foreach ($rows as $r) { $out[$r['action_class']][] = $r; }
  return $out;
}

/** FGのデフォルト強化（fg_bonus_jsonが無いスキルに適用する係数） */
function default_fg_bonus(string $actionClass): array {
  switch ($actionClass) {
    case 'attack': return ['damage_mult'=>1.15, 'hit_bonus'=>10, 'guard_override'=>null, 'heal_mult'=>null];
    case 'guard':  return ['damage_mult'=>null, 'hit_bonus'=>0,  'guard_override'=>0.30, 'heal_mult'=>null];
    case 'heal':   return ['damage_mult'=>null, 'hit_bonus'=>0,  'guard_override'=>null, 'heal_mult'=>1.20];
    default:       return ['damage_mult'=>null, 'hit_bonus'=>0,  'guard_override'=>null, 'heal_mult'=>null];
  }
}
