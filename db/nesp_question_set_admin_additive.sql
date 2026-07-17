/* NESP questionnaire admin additive migration.
 *
 * Launch-safe defaults:
 * - Does not alter legacy career portal questionnaire tables.
 * - Does not email, text, call, rank, approve, reject, hire, publish ads, or
 *   move candidate stages.
 * - Existing public questionnaire links continue to use hashed tokens only.
 */

CREATE TABLE IF NOT EXISTS `nesp_question_set` (
  `question_set_id` INT(11) NOT NULL AUTO_INCREMENT,
  `set_key` VARCHAR(96) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `display_name` VARCHAR(160) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `description` TEXT COLLATE utf8mb4_unicode_ci,
  `status_key` VARCHAR(64) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'active',
  `current_version_id` INT(11),
  `created_by_user_id` INT(11),
  `date_created` DATETIME NOT NULL DEFAULT '1000-01-01 00:00:00',
  `date_modified` DATETIME NOT NULL DEFAULT '1000-01-01 00:00:00',
  PRIMARY KEY (`question_set_id`),
  UNIQUE KEY `IDX_nesp_question_set_key` (`set_key`),
  KEY `IDX_nesp_question_set_status` (`status_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `nesp_question_set_version` (
  `question_set_version_id` INT(11) NOT NULL AUTO_INCREMENT,
  `question_set_id` INT(11) NOT NULL,
  `version_number` INT(11) NOT NULL DEFAULT 1,
  `status_key` VARCHAR(64) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'draft',
  `display_name` VARCHAR(160) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `description` TEXT COLLATE utf8mb4_unicode_ci,
  `role_match_snapshot_json` MEDIUMTEXT COLLATE utf8mb4_unicode_ci,
  `snapshot_json` MEDIUMTEXT COLLATE utf8mb4_unicode_ci,
  `draft_source_version_id` INT(11),
  `created_by_user_id` INT(11),
  `published_by_user_id` INT(11),
  `published_at` DATETIME,
  `archived_at` DATETIME,
  `date_created` DATETIME NOT NULL DEFAULT '1000-01-01 00:00:00',
  `date_modified` DATETIME NOT NULL DEFAULT '1000-01-01 00:00:00',
  PRIMARY KEY (`question_set_version_id`),
  UNIQUE KEY `IDX_nesp_question_set_version` (`question_set_id`, `version_number`),
  KEY `IDX_nesp_question_set_version_status` (`status_key`),
  KEY `IDX_nesp_question_set_version_source` (`draft_source_version_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `nesp_question_set_question` (
  `question_set_question_id` INT(11) NOT NULL AUTO_INCREMENT,
  `question_set_version_id` INT(11) NOT NULL,
  `question_key` VARCHAR(96) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `question_label` VARCHAR(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `help_text` TEXT COLLATE utf8mb4_unicode_ci,
  `question_type` VARCHAR(32) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'textarea',
  `is_required` TINYINT(1) NOT NULL DEFAULT '1',
  `choices_json` MEDIUMTEXT COLLATE utf8mb4_unicode_ci,
  `sort_order` INT(11) NOT NULL DEFAULT 0,
  `date_created` DATETIME NOT NULL DEFAULT '1000-01-01 00:00:00',
  `date_modified` DATETIME NOT NULL DEFAULT '1000-01-01 00:00:00',
  PRIMARY KEY (`question_set_question_id`),
  UNIQUE KEY `IDX_nesp_question_version_key` (`question_set_version_id`, `question_key`),
  KEY `IDX_nesp_question_version_order` (`question_set_version_id`, `sort_order`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `nesp_question_set_role_match` (
  `question_set_role_match_id` INT(11) NOT NULL AUTO_INCREMENT,
  `question_set_id` INT(11) NOT NULL,
  `match_text` VARCHAR(160) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `joborder_id` INT(11),
  `priority` INT(11) NOT NULL DEFAULT 50,
  `is_active` TINYINT(1) NOT NULL DEFAULT '1',
  `date_created` DATETIME NOT NULL DEFAULT '1000-01-01 00:00:00',
  `date_modified` DATETIME NOT NULL DEFAULT '1000-01-01 00:00:00',
  PRIMARY KEY (`question_set_role_match_id`),
  KEY `IDX_nesp_question_role_set` (`question_set_id`, `is_active`),
  KEY `IDX_nesp_question_role_job` (`joborder_id`, `is_active`),
  KEY `IDX_nesp_question_role_text` (`match_text`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE `nesp_screening_questionnaire`
  ADD COLUMN IF NOT EXISTS `question_set_version_id` INT(11) AFTER `question_set_version`,
  ADD COLUMN IF NOT EXISTS `question_snapshot_json` MEDIUMTEXT COLLATE utf8mb4_unicode_ci AFTER `question_set_version_id`,
  ADD KEY IF NOT EXISTS `IDX_questionnaire_set_version` (`question_set_version_id`);

ALTER TABLE `nesp_question_set_version`
  ADD COLUMN IF NOT EXISTS `display_name` VARCHAR(160) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '' AFTER `status_key`,
  ADD COLUMN IF NOT EXISTS `description` TEXT COLLATE utf8mb4_unicode_ci AFTER `display_name`,
  ADD COLUMN IF NOT EXISTS `role_match_snapshot_json` MEDIUMTEXT COLLATE utf8mb4_unicode_ci AFTER `description`;
