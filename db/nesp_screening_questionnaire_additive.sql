/* NESP screening questionnaire additive migration.
 *
 * Launch-safe defaults:
 * - Does not enable NESP_VAPI_ENABLED.
 * - Does not place calls, create cron jobs, send email, send SMS, publish ads,
 *   rank applicants, or change candidate stages.
 * - Stores only hashed public tokens.
 */

CREATE TABLE IF NOT EXISTS `nesp_screening_questionnaire` (
  `screening_questionnaire_id` INT(11) NOT NULL AUTO_INCREMENT,
  `candidate_id` INT(11) NOT NULL,
  `joborder_id` INT(11) NOT NULL,
  `active_candidate_job_key` CHAR(64) COLLATE utf8mb4_unicode_ci,
  `status_key` VARCHAR(64) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'not_invited',
  `question_set_key` VARCHAR(96) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `question_set_version` INT(11) NOT NULL DEFAULT 1,
  `question_set_version_id` INT(11),
  `question_snapshot_json` MEDIUMTEXT COLLATE utf8mb4_unicode_ci,
  `token_hash` CHAR(64) COLLATE utf8mb4_unicode_ci,
  `token_expires_at` DATETIME,
  `token_revoked_at` DATETIME,
  `token_used_at` DATETIME,
  `link_created_at` DATETIME,
  `invitation_copied_at` DATETIME,
  `started_at` DATETIME,
  `submitted_at` DATETIME,
  `requested_by_user_id` INT(11),
  `reviewer_profile_id` INT(11),
  `review_status_key` VARCHAR(64) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'not_started',
  `review_notes` TEXT COLLATE utf8mb4_unicode_ci,
  `review_completed_by_user_id` INT(11),
  `review_completed_at` DATETIME,
  `human_follow_up_requested_at` DATETIME,
  `date_created` DATETIME NOT NULL DEFAULT '1000-01-01 00:00:00',
  `date_modified` DATETIME NOT NULL DEFAULT '1000-01-01 00:00:00',
  PRIMARY KEY (`screening_questionnaire_id`),
  UNIQUE KEY `IDX_questionnaire_token_hash` (`token_hash`),
  UNIQUE KEY `IDX_questionnaire_active_candidate_job` (`active_candidate_job_key`),
  KEY `IDX_questionnaire_candidate_job` (`candidate_id`, `joborder_id`),
  KEY `IDX_questionnaire_status` (`status_key`),
  KEY `IDX_questionnaire_set_version` (`question_set_version_id`),
  KEY `IDX_questionnaire_reviewer` (`reviewer_profile_id`, `review_status_key`),
  KEY `IDX_questionnaire_submitted` (`submitted_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `nesp_screening_questionnaire_answer` (
  `questionnaire_answer_id` INT(11) NOT NULL AUTO_INCREMENT,
  `screening_questionnaire_id` INT(11) NOT NULL,
  `question_key` VARCHAR(96) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `question_label` VARCHAR(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `answer_text` MEDIUMTEXT COLLATE utf8mb4_unicode_ci,
  `sort_order` INT(11) NOT NULL DEFAULT 0,
  `date_created` DATETIME NOT NULL DEFAULT '1000-01-01 00:00:00',
  `date_modified` DATETIME NOT NULL DEFAULT '1000-01-01 00:00:00',
  PRIMARY KEY (`questionnaire_answer_id`),
  UNIQUE KEY `IDX_questionnaire_answer_key` (`screening_questionnaire_id`, `question_key`),
  KEY `IDX_questionnaire_answer_parent` (`screening_questionnaire_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `nesp_screening_questionnaire_activity` (
  `questionnaire_activity_id` INT(11) NOT NULL AUTO_INCREMENT,
  `screening_questionnaire_id` INT(11),
  `token_hash` CHAR(64) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `activity_key` VARCHAR(96) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `ip_hash` CHAR(64) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `user_agent_hash` CHAR(64) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `metadata_json` MEDIUMTEXT COLLATE utf8mb4_unicode_ci,
  `date_created` DATETIME NOT NULL DEFAULT '1000-01-01 00:00:00',
  PRIMARY KEY (`questionnaire_activity_id`),
  KEY `IDX_questionnaire_activity_token` (`token_hash`, `date_created`),
  KEY `IDX_questionnaire_activity_parent` (`screening_questionnaire_id`, `date_created`),
  KEY `IDX_questionnaire_activity_ip` (`ip_hash`, `date_created`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

UPDATE `nesp_feature_flag`
SET `is_enabled` = 0,
    `date_modified` = NOW()
WHERE `flag_key` = 'NESP_VAPI_ENABLED';

UPDATE `nesp_integration_status`
SET `status_key` = 'disabled',
    `message` = 'Optional automated phone screen — currently disabled pending final test.',
    `date_modified` = NOW()
WHERE `integration_key` = 'vapi';
