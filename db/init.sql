-- PostgreSQL schema for batosta
-- For Supabase: paste into the SQL Editor and run
-- To reset locally: npm run reset-db  (Docker uses MySQL — this file is for Supabase/PostgreSQL)

-- ============================================================
-- Schema
-- ============================================================

CREATE TABLE IF NOT EXISTS users (
  id                SERIAL       PRIMARY KEY,
  username          VARCHAR(50)  NOT NULL,
  email             VARCHAR(100) NOT NULL UNIQUE,
  nickname          VARCHAR(255) DEFAULT NULL,
  password          VARCHAR(255) NOT NULL,
  created_at        TIMESTAMP    DEFAULT NOW(),
  profile_cover_url VARCHAR(255) DEFAULT NULL,
  profile_icon_url  VARCHAR(255) DEFAULT NULL,
  presence          VARCHAR(20)  DEFAULT 'offline'
    CHECK (presence IN ('studying','battling','online','offline'))
);

CREATE TABLE IF NOT EXISTS avatars (
  id      SERIAL      PRIMARY KEY,
  user_id INTEGER     NOT NULL UNIQUE REFERENCES users(id),
  gender  VARCHAR(10) DEFAULT 'other' CHECK (gender IN ('male','female','other')),
  level   INTEGER     DEFAULT 1,
  exp     INTEGER     DEFAULT 0
);

CREATE TABLE IF NOT EXISTS avatars_status (
  user_id      INTEGER   NOT NULL PRIMARY KEY REFERENCES users(id) ON DELETE CASCADE,
  level        INTEGER   NOT NULL DEFAULT 1,
  exp          INTEGER   NOT NULL DEFAULT 0,
  base_attack  INTEGER   NOT NULL DEFAULT 5,
  base_defense INTEGER   NOT NULL DEFAULT 5,
  created_at   TIMESTAMP DEFAULT NOW(),
  updated_at   TIMESTAMP DEFAULT NOW(),
  attack       INTEGER   NOT NULL DEFAULT 1,
  defense      INTEGER   NOT NULL DEFAULT 1
);

CREATE TABLE IF NOT EXISTS avatar_parts_catalog (
  id           SERIAL       PRIMARY KEY,
  slot         VARCHAR(50)  NOT NULL,
  key_name     VARCHAR(50)  NOT NULL,
  color        VARCHAR(30)  DEFAULT NULL,
  image_path   VARCHAR(255) NOT NULL,
  rarity       SMALLINT     DEFAULT 0,
  is_default   SMALLINT     DEFAULT 0,
  gender_scope VARCHAR(10)  DEFAULT 'unisex'
    CHECK (gender_scope IN ('unisex','male','female'))
);

CREATE TABLE IF NOT EXISTS user_avatar_parts (
  user_id INTEGER     NOT NULL REFERENCES users(id),
  slot    VARCHAR(50) NOT NULL,
  part_id INTEGER     NOT NULL REFERENCES avatar_parts_catalog(id),
  PRIMARY KEY (user_id, slot)
);

CREATE TABLE IF NOT EXISTS user_owned_parts (
  user_id INTEGER NOT NULL REFERENCES users(id),
  part_id INTEGER NOT NULL REFERENCES avatar_parts_catalog(id),
  PRIMARY KEY (user_id, part_id)
);

CREATE TABLE IF NOT EXISTS equipments (
  id         SERIAL       PRIMARY KEY,
  name       VARCHAR(50)  NOT NULL,
  slot       VARCHAR(20)  NOT NULL
    CHECK (slot IN ('weapon','shield','head','armor','boots','outfit')),
  image_path VARCHAR(255) DEFAULT NULL,
  attack     INTEGER      DEFAULT 0,
  defense    INTEGER      DEFAULT 0,
  is_initial SMALLINT     NOT NULL DEFAULT 0
);

CREATE TABLE IF NOT EXISTS user_avatar_equipments (
  id           SERIAL   PRIMARY KEY,
  user_id      INTEGER  NOT NULL REFERENCES users(id),
  slot         VARCHAR(20) NOT NULL
    CHECK (slot IN ('weapon','shield','head','armor','boots','outfit')),
  equipment_id INTEGER  NOT NULL REFERENCES equipments(id),
  UNIQUE (user_id, slot)
);

CREATE TABLE IF NOT EXISTS user_equipments (
  id           SERIAL  PRIMARY KEY,
  user_id      INTEGER NOT NULL REFERENCES users(id),
  equipment_id INTEGER NOT NULL REFERENCES equipments(id)
);
CREATE INDEX IF NOT EXISTS idx_user_equipments_user  ON user_equipments(user_id);
CREATE INDEX IF NOT EXISTS idx_user_equipments_equip ON user_equipments(equipment_id);

CREATE TABLE IF NOT EXISTS materials (
  id   SERIAL      PRIMARY KEY,
  name VARCHAR(50) NOT NULL
);

CREATE TABLE IF NOT EXISTS equipment_requirements (
  id           SERIAL  PRIMARY KEY,
  equipment_id INTEGER NOT NULL REFERENCES equipments(id),
  material_id  INTEGER NOT NULL REFERENCES materials(id),
  quantity     INTEGER NOT NULL
);
CREATE INDEX IF NOT EXISTS idx_er_equipment ON equipment_requirements(equipment_id);
CREATE INDEX IF NOT EXISTS idx_er_material  ON equipment_requirements(material_id);

CREATE TABLE IF NOT EXISTS user_materials (
  id          SERIAL  PRIMARY KEY,
  user_id     INTEGER NOT NULL REFERENCES users(id),
  material_id INTEGER NOT NULL REFERENCES materials(id),
  quantity    INTEGER DEFAULT 0
);
CREATE INDEX IF NOT EXISTS idx_user_materials_user ON user_materials(user_id);

CREATE TABLE IF NOT EXISTS study_logs (
  id               SERIAL   PRIMARY KEY,
  user_id          INTEGER  NOT NULL REFERENCES users(id),
  subject          VARCHAR(50)  DEFAULT NULL,
  type             VARCHAR(50)  DEFAULT NULL,
  duration_minutes INTEGER      DEFAULT NULL,
  study_date       DATE         DEFAULT NULL,
  started_at       TIMESTAMP    DEFAULT NULL,
  ended_at         TIMESTAMP    DEFAULT NULL
);
CREATE INDEX IF NOT EXISTS idx_study_logs_user ON study_logs(user_id);

CREATE TABLE IF NOT EXISTS friend_requests (
  id           SERIAL  PRIMARY KEY,
  requester_id INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
  addressee_id INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
  status       VARCHAR(10) DEFAULT 'pending'
    CHECK (status IN ('pending','accepted','rejected','canceled')),
  created_at   TIMESTAMP DEFAULT NOW(),
  UNIQUE (requester_id, addressee_id)
);

CREATE TABLE IF NOT EXISTS friends (
  id         SERIAL  PRIMARY KEY,
  user_id    INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
  friend_id  INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
  created_at TIMESTAMP DEFAULT NOW(),
  UNIQUE (user_id, friend_id)
);

CREATE TABLE IF NOT EXISTS titles (
  id          SERIAL       PRIMARY KEY,
  name        VARCHAR(100) NOT NULL,
  description TEXT         DEFAULT NULL,
  image_path  VARCHAR(255) DEFAULT NULL,
  how_to_get  TEXT         DEFAULT NULL,
  requirement TEXT         DEFAULT NULL
);

CREATE TABLE IF NOT EXISTS user_titles (
  id          SERIAL    PRIMARY KEY,
  user_id     INTEGER   NOT NULL REFERENCES users(id) ON DELETE CASCADE,
  title_id    INTEGER   NOT NULL REFERENCES titles(id) ON DELETE CASCADE,
  equipped    BOOLEAN   DEFAULT FALSE,
  acquired_at TIMESTAMP DEFAULT NOW()
);

CREATE TABLE IF NOT EXISTS skills (
  id            SERIAL       PRIMARY KEY,
  code          VARCHAR(64)  NOT NULL UNIQUE,
  name          VARCHAR(64)  NOT NULL,
  action_class  VARCHAR(10)  NOT NULL CHECK (action_class IN ('attack','guard','heal')),
  type          VARCHAR(10)  NOT NULL DEFAULT 'attack'
    CHECK (type IN ('attack','guard','heal','buff','debuff','special')),
  subject       VARCHAR(10)  NOT NULL DEFAULT 'common'
    CHECK (subject IN ('common','math','language','science','music')),
  min_level     SMALLINT     NOT NULL DEFAULT 1,
  power         DECIMAL(5,2) NOT NULL DEFAULT 0.00,
  hit_rate      SMALLINT     NOT NULL DEFAULT 100,
  cost_sp       SMALLINT     NOT NULL DEFAULT 0,
  max_charges   SMALLINT     DEFAULT NULL,
  cooldown      SMALLINT     NOT NULL DEFAULT 0,
  priority      SMALLINT     NOT NULL DEFAULT 0,
  fg_required   SMALLINT     NOT NULL DEFAULT 0,
  effect_json   JSONB        NOT NULL DEFAULT '{}',
  fg_bonus_json JSONB        DEFAULT NULL,
  description   VARCHAR(255) NOT NULL DEFAULT '',
  created_at    TIMESTAMP    NOT NULL DEFAULT NOW()
);
CREATE INDEX IF NOT EXISTS idx_skills_minlvl  ON skills (min_level, action_class);
CREATE INDEX IF NOT EXISTS idx_skills_subject ON skills (subject);

CREATE TABLE IF NOT EXISTS user_skills (
  user_id    INTEGER   NOT NULL REFERENCES users(id) ON DELETE CASCADE,
  skill_id   INTEGER   NOT NULL REFERENCES skills(id) ON DELETE CASCADE,
  learned_at TIMESTAMP NOT NULL DEFAULT NOW(),
  PRIMARY KEY (user_id, skill_id)
);

CREATE TABLE IF NOT EXISTS user_task_types (
  id         SERIAL       PRIMARY KEY,
  user_id    INTEGER      NOT NULL REFERENCES users(id) ON DELETE CASCADE,
  type_name  VARCHAR(255) NOT NULL,
  created_at TIMESTAMP    DEFAULT NOW(),
  UNIQUE (user_id, type_name)
);

-- Battle tables
CREATE TABLE IF NOT EXISTS battles (
  id             BIGSERIAL   PRIMARY KEY,
  p1_id          INTEGER     NOT NULL REFERENCES users(id) ON DELETE CASCADE,
  p2_id          INTEGER     NOT NULL REFERENCES users(id) ON DELETE CASCADE,
  status         VARCHAR(10) NOT NULL DEFAULT 'active'
    CHECK (status IN ('matching','active','finished','canceled')),
  turn_no        INTEGER     NOT NULL DEFAULT 1,
  max_turns      SMALLINT    NOT NULL DEFAULT 12,
  winner_user_id INTEGER     DEFAULT NULL,
  started_at     TIMESTAMP   NOT NULL DEFAULT NOW(),
  ended_at       TIMESTAMP   DEFAULT NULL
);
CREATE INDEX IF NOT EXISTS idx_btl_p1 ON battles(p1_id);
CREATE INDEX IF NOT EXISTS idx_btl_p2 ON battles(p2_id);

CREATE TABLE IF NOT EXISTS battle_participants (
  battle_id   BIGINT   NOT NULL REFERENCES battles(id) ON DELETE CASCADE,
  user_id     INTEGER  NOT NULL REFERENCES users(id) ON DELETE CASCADE,
  level       INTEGER  NOT NULL,
  atk         INTEGER  NOT NULL,
  def         INTEGER  NOT NULL,
  spd         INTEGER  NOT NULL,
  hp_max      INTEGER  NOT NULL,
  sp_max      INTEGER  NOT NULL,
  hp_current  INTEGER  NOT NULL,
  sp_current  INTEGER  NOT NULL,
  fg          SMALLINT NOT NULL DEFAULT 0,
  fatigue_a   SMALLINT NOT NULL DEFAULT 0,
  fatigue_g   SMALLINT NOT NULL DEFAULT 0,
  fatigue_h   SMALLINT NOT NULL DEFAULT 0,
  chain_hist  CHAR(3)  NOT NULL DEFAULT '---',
  last_action VARCHAR(10) NOT NULL DEFAULT 'none'
    CHECK (last_action IN ('attack','guard','heal','none')),
  flags_json  JSONB    DEFAULT NULL,
  updated_at  TIMESTAMP NOT NULL DEFAULT NOW(),
  PRIMARY KEY (battle_id, user_id)
);
CREATE INDEX IF NOT EXISTS idx_bp_user ON battle_participants(user_id);

CREATE TABLE IF NOT EXISTS battle_effects (
  id              BIGSERIAL    PRIMARY KEY,
  battle_id       BIGINT       NOT NULL REFERENCES battles(id) ON DELETE CASCADE,
  user_id         INTEGER      NOT NULL REFERENCES users(id) ON DELETE CASCADE,
  effect_code     VARCHAR(20)  NOT NULL
    CHECK (effect_code IN ('atk_up','def_up','dmg_up','dmg_down','guard_up','dot','hot','barrier','evasion_up','cleanse_block')),
  magnitude       DECIMAL(6,3) NOT NULL DEFAULT 0.000,
  remaining_turns SMALLINT     NOT NULL DEFAULT 1,
  source_skill_id INTEGER      DEFAULT NULL REFERENCES skills(id) ON DELETE SET NULL,
  UNIQUE (battle_id, user_id, effect_code)
);
CREATE INDEX IF NOT EXISTS idx_be_user  ON battle_effects(user_id);
CREATE INDEX IF NOT EXISTS idx_be_skill ON battle_effects(source_skill_id);

CREATE TABLE IF NOT EXISTS battle_skill_state (
  battle_id    BIGINT   NOT NULL REFERENCES battles(id) ON DELETE CASCADE,
  user_id      INTEGER  NOT NULL REFERENCES users(id) ON DELETE CASCADE,
  skill_id     INTEGER  NOT NULL REFERENCES skills(id) ON DELETE CASCADE,
  charges_left SMALLINT DEFAULT NULL,
  cd_remaining SMALLINT NOT NULL DEFAULT 0,
  PRIMARY KEY (battle_id, user_id, skill_id)
);
CREATE INDEX IF NOT EXISTS idx_bss_skill ON battle_skill_state(skill_id);
CREATE INDEX IF NOT EXISTS idx_bss_user  ON battle_skill_state(user_id);

CREATE TABLE IF NOT EXISTS battle_turns (
  id             BIGSERIAL  PRIMARY KEY,
  battle_id      BIGINT     NOT NULL REFERENCES battles(id) ON DELETE CASCADE,
  turn_no        INTEGER    NOT NULL,
  actor_user_id  INTEGER    DEFAULT NULL,
  target_user_id INTEGER    DEFAULT NULL,
  action_class   VARCHAR(10) NOT NULL
    CHECK (action_class IN ('attack','guard','heal','event')),
  skill_id       INTEGER    DEFAULT NULL REFERENCES skills(id) ON DELETE SET NULL,
  event_type     VARCHAR(10) DEFAULT NULL
    CHECK (event_type IS NULL OR event_type IN ('CHIME','REMIND')),
  damage_done    INTEGER    NOT NULL DEFAULT 0,
  heal_done      INTEGER    NOT NULL DEFAULT 0,
  sp_spent       SMALLINT   NOT NULL DEFAULT 0,
  fg_change      SMALLINT   NOT NULL DEFAULT 0,
  tags_json      JSONB      DEFAULT NULL,
  created_at     TIMESTAMP  NOT NULL DEFAULT NOW()
);
CREATE INDEX IF NOT EXISTS idx_bt_skill       ON battle_turns(skill_id);
CREATE INDEX IF NOT EXISTS idx_bt_battle_turn ON battle_turns(battle_id, turn_no);

CREATE TABLE IF NOT EXISTS equip_slot_master (
  id              SERIAL       PRIMARY KEY,
  slot            VARCHAR(50)  NOT NULL UNIQUE,
  label           VARCHAR(100) DEFAULT NULL,
  empty_icon_path VARCHAR(255) DEFAULT NULL
);

-- Session storage (for PHP DB session handler)
CREATE TABLE IF NOT EXISTS user_sessions (
  id            VARCHAR(128) NOT NULL PRIMARY KEY,
  data          TEXT         DEFAULT NULL,
  last_activity INTEGER      NOT NULL
);
CREATE INDEX IF NOT EXISTS idx_user_sessions_activity ON user_sessions(last_activity);

-- ============================================================
-- Seed data
-- ============================================================

INSERT INTO avatar_parts_catalog (id, slot, key_name, color, image_path, rarity, is_default, gender_scope) VALUES
(21, 'body',        'base01',      NULL, '/assets/avatars/body/body_base01.PNG',   0, 0, 'unisex'),
(23, 'hair',        'base01',      '',   '/assets/avatars/hair/hair_base01.PNG',   0, 1, 'unisex'),
(24, 'mouth',       'base01',      '',   '/assets/avatars/mouth/mouth_base01.PNG', 0, 1, 'unisex'),
(25, 'eyes',        'base01',      '',   '/assets/avatars/eyes/eyes_base01.PNG',   0, 1, 'female'),
(26, 'eyes',        'base02',      NULL, '/assets/avatars/eyes/eyes_base02.PNG',   0, 0, 'unisex'),
(27, 'eyes',        'base03',      NULL, '/assets/avatars/eyes/eyes_base03.PNG',   0, 1, 'male'),
(28, 'eyes',        'base04',      NULL, '/assets/avatars/eyes/eyes_base04.PNG',   0, 0, 'unisex'),
(29, 'eyes',        'base05',      NULL, '/assets/avatars/eyes/eyes_base05.PNG',   0, 0, 'unisex'),
(30, 'mouth',       'base02',      NULL, '/assets/avatars/mouth/mouth_base02.PNG', 0, 0, 'unisex'),
(31, 'mouth',       'base03',      NULL, '/assets/avatars/mouth/mouth_base03.PNG', 0, 0, 'unisex'),
(32, 'hand_base',   'hand_base',   NULL, '/assets/avatars/hand/hand_base.PNG',     0, 0, 'unisex'),
(33, 'hand_weapon', 'hand_weapon', NULL, '/assets/avatars/hand/hand_weapon.PNG',   0, 0, 'unisex'),
(34, 'hair',        'base02',      NULL, '/assets/avatars/hair/hair_base02.PNG',   0, 0, 'unisex')
ON CONFLICT (id) DO NOTHING;

SELECT setval(pg_get_serial_sequence('avatar_parts_catalog', 'id'), (SELECT MAX(id) FROM avatar_parts_catalog));

INSERT INTO equipments (id, name, slot, image_path, attack, defense, is_initial) VALUES
(4, '鉄の剣',       'weapon', 'weapon/weapon_sword01.png',                        5, 0, 0),
(5, '木の盾',       'shield', 'shield/shield_wood01.png',                         0, 3, 0),
(6, '鉄のかぶと',   'head',   'head/head_helmet01.png',                           0, 2, 0),
(7, '鉄の斧',       'weapon', 'weapon/weapon_ax01.png',                           7, 0, 0),
(8, '初期服（女）', 'outfit', '/assets/avatars/outfit/outfit_base01_female.PNG',  0, 0, 1),
(9, '初期服（男）', 'outfit', '/assets/avatars/outfit/outfit_base01_male.PNG',    0, 0, 1)
ON CONFLICT (id) DO NOTHING;

SELECT setval(pg_get_serial_sequence('equipments', 'id'), (SELECT MAX(id) FROM equipments));

INSERT INTO materials (id, name) VALUES
(1, '木材'),
(2, '鉄くず'),
(3, '魔石'),
(4, '布'),
(5, '骨'),
(6, '水晶')
ON CONFLICT (id) DO NOTHING;

SELECT setval(pg_get_serial_sequence('materials', 'id'), (SELECT MAX(id) FROM materials));

INSERT INTO equipment_requirements (id, equipment_id, material_id, quantity) VALUES
(6, 4, 2, 5),
(7, 5, 1, 5),
(8, 6, 2, 4),
(9, 7, 2, 8)
ON CONFLICT (id) DO NOTHING;

SELECT setval(pg_get_serial_sequence('equipment_requirements', 'id'), (SELECT MAX(id) FROM equipment_requirements));

INSERT INTO titles (id, name, description, image_path, how_to_get, requirement) VALUES
(1, '初心者冒険者', 'はじめて勉強した人に与えられる称号', '/assets/titles/title_1.png',    '1回目の勉強を終える', '勉強1回以上'),
(2, '鉄人',         '累計10時間以上勉強した人',            '/assets/titles/title_2.png',    '累計10時間以上勉強する', '600分以上'),
(3, '初級学習者',   '累計で30分以上勉強したら獲得できる',  '/assets/titles/first_title.png', NULL, NULL)
ON CONFLICT (id) DO NOTHING;

SELECT setval(pg_get_serial_sequence('titles', 'id'), (SELECT MAX(id) FROM titles));

INSERT INTO skills (id, code, name, action_class, type, subject, min_level, power, hit_rate, cost_sp, max_charges, cooldown, priority, fg_required, effect_json, fg_bonus_json, description, created_at) VALUES
(1,   'BASIC_ATTACK',    '通常攻撃',         'attack', 'attack',  'common',   1,  1.00, 100, 0,  NULL, 0, 0,  0, '{"damage": {"mult": 1.0}}',                                                                          '{"crit": {"mult": 1.5}}',                                       '基本の一撃',           NOW()),
(2,   'FULL_NOTE',       '全力ノート',       'attack', 'attack',  'common',   3,  1.30, 95,  6,  NULL, 2, 0,  0, '{"damage": {"mult": 1.3}}',                                                                          '{"damage": {"mult": 1.4}, "guaranteed": true}',                 '高倍率の渾身攻撃',     NOW()),
(3,   'RAPID_CALC',      '連続計算',         'attack', 'attack',  'math',     5,  0.70, 92,  8,  2,    3, 0,  0, '{"multi": {"hits": 2, "mult": 0.7}}',                                                                '{"multi": {"hits": 3, "mult": 0.7}}',                           '2連撃（FGで3連）',     NOW()),
(4,   'SCI_EXPERIMENT',  '理科のじっけん',   'attack', 'debuff',  'science',  6,  0.90, 95,  7,  NULL, 2, 0,  0, '{"dot": {"mult": 0.15, "turns": 3}, "damage": {"mult": 0.9}}',                                       '{"dot": {"mult": 0.2, "turns": 3}}',                            '継続ダメージを付与',   NOW()),
(5,   'ERASER_TRICK',    'いたずら消しゴム', 'attack', 'debuff',  'language', 7,  0.90, 90,  8,  NULL, 2, 0,  0, '{"damage": {"mult": 0.9}, "debuff": {"def": -0.15, "turns": 2}}',                                    '{"debuff": {"def": -0.25, "turns": 2}}',                        '防御ダウンを伴う攻撃', NOW()),
(6,   'FOCUS_GUARD',     '集中ガード',       'guard',  'guard',   'common',   2,  0.00, 100, 5,  NULL, 2, 2,  0, '{"buff": {"turns": 1, "dmg_up": 0.1}, "guard": {"turns": 1, "reduction": 0.5}}',                     '{"buff": {"turns": 1, "dmg_up": 0.1}, "guard": {"turns": 1, "reduction": 0.7}}', '被ダメ大幅減＋次ターン少し強化', NOW()),
(7,   'NOTE_BLOCK',      'ノートでガード',   'guard',  'guard',   'common',   1,  0.00, 100, 0,  NULL, 1, 0,  0, '{"guard": {"turns": 1, "reduction": 0.3}}',                                                          NULL,                                                            '軽い防御',             NOW()),
(8,   'MIKIRI',          '見切り',           'guard',  'special', 'language', 8,  0.00, 100, 8,  NULL, 3, 10, 0, '{"buff": {"turns": 1, "dmg_up": 0.15}, "evasion": {"turns": 1, "chance": 0.5}}',                     '{"buff": {"turns": 1, "dmg_up": 0.2}, "evasion": {"turns": 1, "chance": 1.0}}', '回避しつつ次を強化', NOW()),
(9,   'WATER_BREAK',     '水分補給',         'heal',   'heal',    'common',   2,  0.00, 100, 6,  3,    2, 0,  0, '{"heal": {"ratio_hp": 0.25}}',                                                                       '{"heal": {"ratio_hp": 0.3}}',                                   'HP中回復（回数制限）', NOW()),
(10,  'DEEP_BREATH',     '深呼吸',           'heal',   'heal',    'common',   4,  0.00, 100, 8,  2,    3, 0,  0, '{"heal": {"ratio_hp": 0.18}, "cleanse": 1}',                                                         '{"heal": {"ratio_hp": 0.22}, "cleanse": 1}',                    '回復＋状態1解除',      NOW()),
(11,  'MUSIC_THERAPY',   '音楽鑑賞',         'heal',   'buff',    'music',    6,  0.00, 100, 9,  2,    3, 0,  0, '{"hot": {"turns": 3, "ratio_hp": 0.1}}',                                                             '{"hot": {"turns": 3, "ratio_hp": 0.12}}',                       '徐々に回復（3T）',     NOW()),
(12,  'COMMAND',         '号令',             'guard',  'buff',    'common',   5,  0.00, 100, 7,  NULL, 3, 0,  0, '{"buff": {"atk": 0.15, "turns": 2}}',                                                                '{"buff": {"atk": 0.25, "turns": 2}}',                           'ATKアップ（2T）',      NOW()),
(104, 'CHALK_SHOWER',    'チョークシャワー', 'attack', 'attack',  'common',   4,  0.45, 97,  5,  NULL, 2, 0,  0, '{"multi": {"hits": 3, "mult": 0.45}}',                                                               '{"multi": {"hits": 4, "mult": 0.45}}',                          '小ダメージの多段攻撃', NOW()),
(105, 'GEOMETRY_STRIKE', '図形ストライク',   'attack', 'attack',  'math',     6,  1.10, 99,  5,  NULL, 1, 0,  0, '{"damage": {"mult": 1.1}}',                                                                          '{"damage": {"mult": 1.2}, "guaranteed": true}',                 '命中に優れる安定打',   NOW()),
(106, 'VOCAB_BURST',     '語彙バースト',     'attack', 'debuff',  'language', 8,  1.05, 96,  6,  NULL, 2, 0,  0, '{"damage": {"mult": 1.05}, "debuff": {"turns": 2, "dmg_down": 0.12}}',                               '{"debuff": {"turns": 2, "dmg_down": 0.18}}',                    '相手の与ダメ低下付与', NOW()),
(107, 'ACID_SPRAY',      '酸とアルカリ',     'attack', 'debuff',  'science',  9,  0.95, 94,  7,  NULL, 2, 0,  0, '{"dot": {"mult": 0.12, "turns": 2}, "damage": {"mult": 0.95}, "debuff": {"def": -0.1, "turns": 2}}', '{"dot": {"mult": 0.16, "turns": 2}, "debuff": {"def": -0.15, "turns": 2}}', '継続ダメ＋防御小ダウン', NOW()),
(108, 'RHYTHM_STRIKE',   'リズムアタック',   'attack', 'buff',    'music',    6,  1.00, 98,  6,  NULL, 2, 0,  0, '{"buff": {"atk": 0.1, "turns": 1}, "damage": {"mult": 1.0}}',                                        '{"buff": {"atk": 0.2, "turns": 1}}',                            '次を少し強くする',     NOW()),
(109, 'PAPER_PLANE',     '紙ひこうき',       'attack', 'attack',  'common',   2,  0.60, 96,  4,  NULL, 1, 0,  0, '{"multi": {"hits": 2, "mult": 0.6}}',                                                                NULL,                                                            '命中高めの2連',        NOW()),
(110, 'HEAVY_TEXTBOOK',  '分厚い教科書',     'attack', 'attack',  'common',   10, 1.50, 90,  10, NULL, 3, 0,  0, '{"damage": {"mult": 1.5}}',                                                                          '{"guaranteed": true}',                                          '重い一撃（低命中）',   NOW()),
(111, 'GROUP_SHIELD',    '班のきずな',       'guard',  'buff',    'common',   7,  0.00, 100, 9,  NULL, 3, 0,  0, '{"barrier": {"turns": 2, "amount": 60}}',                                                            '{"barrier": {"turns": 2, "amount": 80}}',                       '一時的なバリア',       NOW()),
(112, 'LAB_COAT',        '白衣の守り',       'guard',  'buff',    'science',  5,  0.00, 100, 6,  NULL, 2, 0,  0, '{"buff": {"def": 0.2, "turns": 2}}',                                                                 '{"buff": {"def": 0.3, "turns": 2}}',                            '防御アップ',           NOW()),
(113, 'DESK_COVER',      '机のかげ',         'guard',  'guard',   'common',   3,  0.00, 100, 6,  NULL, 2, 0,  0, '{"guard": {"turns": 1, "reduction": 0.4}}',                                                          '{"guard": {"turns": 1, "reduction": 0.35}}',                    'しっかり防ぐ',         NOW()),
(114, 'SEAT_SWITCH',     '席替え',           'guard',  'debuff',  'common',   6,  0.00, 100, 6,  NULL, 3, 0,  0, '{"debuff": {"turns": 2, "dmg_down": 0.15}}',                                                         '{"debuff": {"turns": 2, "dmg_down": 0.2}}',                     '相手の与ダメを下げる', NOW()),
(115, 'EVASION_STEP',    '身のこなし',       'guard',  'special', 'common',   6,  0.00, 100, 7,  NULL, 3, 5,  0, '{"evasion": {"turns": 1, "chance": 0.35}}',                                                          '{"evasion": {"turns": 1, "chance": 1.0}}',                      '回避重視',             NOW()),
(116, 'RHYTHM_WALL',     'リズムウォール',   'guard',  'buff',    'music',    7,  0.00, 100, 7,  NULL, 3, 0,  0, '{"barrier": {"turns": 2, "amount": 40}}',                                                            '{"barrier": {"turns": 2, "amount": 60}}',                       '音の壁でバリア',       NOW()),
(117, 'NURSE_VISIT',     '保健室',           'heal',   'heal',    'common',   9,  0.00, 100, 12, 1,    3, 0,  0, '{"heal": {"ratio_hp": 0.4}}',                                                                        '{"heal": {"ratio_hp": 0.45}}',                                  '大回復（1回）',        NOW()),
(118, 'SNACK_TIME',      'おやつタイム',     'heal',   'heal',    'common',   5,  0.00, 100, 7,  2,    2, 0,  0, '{"hot": {"turns": 2, "ratio_hp": 0.05}, "heal": {"ratio_hp": 0.18}}',                                '{"hot": {"turns": 2, "ratio_hp": 0.06}, "heal": {"ratio_hp": 0.2}}',  '回復＋少しずつ回復',  NOW()),
(119, 'BANDAGE',         'ばんそうこう',     'heal',   'heal',    'common',   3,  0.00, 100, 3,  4,    1, 0,  0, '{"heal": {"ratio_hp": 0.12}}',                                                                       NULL,                                                            '小回復（軽コスト）',   NOW()),
(120, 'STUDY_GROUP',     '勉強会',           'heal',   'buff',    'language', 7,  0.00, 100, 8,  2,    3, 0,  0, '{"hot": {"turns": 3, "ratio_hp": 0.07}, "buff": {"turns": 1, "dmg_down": 0.1}}',                    '{"hot": {"turns": 3, "ratio_hp": 0.08}, "buff": {"turns": 1, "dmg_down": 0.15}}', '徐々に回復＋被ダメ軽減', NOW()),
(121, 'GOOD_SLEEP',      'ぐっすり睡眠',     'heal',   'heal',    'common',   10, 0.00, 100, 10, 1,    4, 0,  0, '{"hot": {"turns": 3, "ratio_hp": 0.12}}',                                                            '{"hot": {"turns": 3, "ratio_hp": 0.14}}',                       'しっかり休んで回復',   NOW())
ON CONFLICT (id) DO NOTHING;

SELECT setval(pg_get_serial_sequence('skills', 'id'), (SELECT MAX(id) FROM skills));

INSERT INTO equip_slot_master (slot, label, empty_icon_path) VALUES
('head',   '頭',   'empty.png'),
('outfit', '体',   'empty.png'),
('weapon', '武器', 'empty.png'),
('shield', '盾',   'empty.png'),
('boots',  '靴',   'empty.png')
ON CONFLICT (slot) DO NOTHING;
