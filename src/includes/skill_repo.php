<?php
// includes/skill_repo.php
declare(strict_types=1);

/**
 * ユーザーのレベル/習得データから使えるスキルを取得。
 * 返り値は action_class ごとに配列を分ける。
 */
function fetch_available_skills(PDO $pdo, int $userId, int $level): array {
  $sql = "
    SELECT s.*
    FROM skills s
    LEFT JOIN user_skills us
      ON us.user_id = :uid AND us.skill_id = s.id
    WHERE s.min_level <= :lvl OR us.user_id IS NOT NULL
    ORDER BY s.action_class, s.min_level, s.id
  ";
  $st = $pdo->prepare($sql);
  $st->execute([':uid'=>$userId, ':lvl'=>$level]);
  $rows = $st->fetchAll(PDO::FETCH_ASSOC);
  $out = ['attack'=>[], 'guard'=>[], 'heal'=>[]];
  foreach ($rows as $r) { $out[$r['action_class']][] = $r; }
  return $out;
}
