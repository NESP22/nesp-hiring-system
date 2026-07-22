/* NESP board inbox scheduler additive migration.
 *
 * Safe defaults:
 * - The scheduler and auto-import feature flags are installed OFF.
 * - Webhook bodies, sender details, subjects, and message text are not stored.
 * - Retained sender and subject metadata is represented only by SHA-256 hashes.
 */

CREATE TABLE IF NOT EXISTS `nesp_board_intake_run` (
  `run_id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `run_key` VARCHAR(64) COLLATE utf8mb4_bin NOT NULL,
  `slot_key` VARCHAR(32) COLLATE utf8mb4_bin NOT NULL,
  `status_key` VARCHAR(32) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pending',
  `actor_user_id` INT(11) NOT NULL,
  `started_at` DATETIME,
  `completed_at` DATETIME,
  `queued_count` INT UNSIGNED NOT NULL DEFAULT '0',
  `imported_count` INT UNSIGNED NOT NULL DEFAULT '0',
  `duplicate_count` INT UNSIGNED NOT NULL DEFAULT '0',
  `review_count` INT UNSIGNED NOT NULL DEFAULT '0',
  `failed_count` INT UNSIGNED NOT NULL DEFAULT '0',
  `error_code` VARCHAR(64) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `date_created` DATETIME NOT NULL DEFAULT '1000-01-01 00:00:00',
  `date_modified` DATETIME NOT NULL DEFAULT '1000-01-01 00:00:00',
  PRIMARY KEY (`run_id`),
  UNIQUE KEY `IDX_board_intake_run_key` (`run_key`),
  UNIQUE KEY `IDX_board_intake_slot_once` (`slot_key`),
  KEY `IDX_board_intake_run_status` (`status_key`, `started_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `nesp_board_intake_checkpoint` (
  `provider_key` VARCHAR(32) COLLATE utf8mb4_bin NOT NULL,
  `high_water_epoch` BIGINT UNSIGNED NOT NULL DEFAULT '0',
  `last_run_id` BIGINT UNSIGNED,
  `date_created` DATETIME NOT NULL DEFAULT '1000-01-01 00:00:00',
  `date_modified` DATETIME NOT NULL DEFAULT '1000-01-01 00:00:00',
  PRIMARY KEY (`provider_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `nesp_board_intake_checkpoint`
    (`provider_key`, `high_water_epoch`, `date_created`, `date_modified`)
VALUES
    ('missive', 0, NOW(), NOW())
ON DUPLICATE KEY UPDATE
    `provider_key` = VALUES(`provider_key`);

CREATE TABLE IF NOT EXISTS `nesp_board_intake_event` (
  `event_id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `provider_key` VARCHAR(32) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'missive',
  `provider_message_id` VARCHAR(128) COLLATE utf8mb4_bin NOT NULL,
  `email_message_hash` CHAR(64) COLLATE utf8mb4_bin NOT NULL DEFAULT '',
  `payload_hash` CHAR(64) COLLATE utf8mb4_bin NOT NULL DEFAULT '',
  `subject_hash` CHAR(64) COLLATE utf8mb4_bin NOT NULL DEFAULT '',
  `sender_hash` CHAR(64) COLLATE utf8mb4_bin NOT NULL DEFAULT '',
  `verification_key` VARCHAR(64) COLLATE utf8mb4_bin NOT NULL DEFAULT '',
  `approved_rule_hash` CHAR(64) COLLATE utf8mb4_bin NOT NULL DEFAULT '',
  `verification_proof` CHAR(64) COLLATE utf8mb4_bin NOT NULL DEFAULT '',
  `signature_verified_at` DATETIME,
  `approved_rule_verified_at` DATETIME,
  `status_key` VARCHAR(32) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pending',
  `provider_received_at` DATETIME,
  `run_id` BIGINT UNSIGNED,
  `attempt_count` INT UNSIGNED NOT NULL DEFAULT '0',
  `last_attempt_at` DATETIME,
  `platform_key` VARCHAR(32) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `joborder_id` INT(11),
  `candidate_id` INT(11),
  `processed_at` DATETIME,
  `error_code` VARCHAR(64) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `date_created` DATETIME NOT NULL DEFAULT '1000-01-01 00:00:00',
  `date_modified` DATETIME NOT NULL DEFAULT '1000-01-01 00:00:00',
  PRIMARY KEY (`event_id`),
  UNIQUE KEY `IDX_board_intake_message_once` (`provider_key`, `provider_message_id`),
  KEY `IDX_board_intake_event_queue` (`status_key`, `provider_received_at`),
  KEY `IDX_board_intake_event_run` (`run_id`),
  KEY `IDX_board_intake_event_candidate` (`candidate_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `nesp_feature_flag`
    (`flag_key`, `display_name`, `description`, `is_enabled`, `requires_admin_approval`, `date_created`, `date_modified`)
VALUES
    ('NESP_BOARD_INTAKE_SCHEDULER_ENABLED', 'Board Inbox Scheduler', 'Twice-daily reconciliation and manual-review queue for application notifications. Disabled until Missive and system-user configuration is approved.', 0, 1, NOW(), NOW()),
    ('NESP_BOARD_INTAKE_AUTO_IMPORT_ENABLED', 'Board Inbox Auto-Import', 'Allows signed notifications carrying the configured approved-rule proof to create candidates in Needs Craig. Shared-label recovery remains manual review.', 0, 1, NOW(), NOW())
ON DUPLICATE KEY UPDATE
    `display_name` = VALUES(`display_name`),
    `description` = VALUES(`description`),
    `requires_admin_approval` = 1,
    `date_modified` = NOW();
