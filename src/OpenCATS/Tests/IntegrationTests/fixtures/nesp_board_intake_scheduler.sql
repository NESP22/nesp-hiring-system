CREATE TABLE `nesp_board_intake_run` (
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

CREATE TABLE `nesp_board_intake_checkpoint` (
  `provider_key` VARCHAR(32) COLLATE utf8mb4_bin NOT NULL,
  `high_water_epoch` BIGINT UNSIGNED NOT NULL DEFAULT '0',
  `last_run_id` BIGINT UNSIGNED,
  `date_created` DATETIME NOT NULL DEFAULT '1000-01-01 00:00:00',
  `date_modified` DATETIME NOT NULL DEFAULT '1000-01-01 00:00:00',
  PRIMARY KEY (`provider_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `nesp_board_intake_checkpoint`
    (`provider_key`, `high_water_epoch`, `date_created`, `date_modified`)
VALUES ('missive', 0, NOW(), NOW());

CREATE TABLE `nesp_board_intake_event` (
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

CREATE TABLE `nesp_board_intake_batch` (
  `batch_id` INT(11) NOT NULL AUTO_INCREMENT,
  `platform_key` VARCHAR(32) NOT NULL,
  `joborder_id` INT(11) NOT NULL,
  `source_label` VARCHAR(128) NOT NULL,
  `source_checksum` CHAR(64) NOT NULL,
  `status_key` VARCHAR(32) NOT NULL DEFAULT 'review',
  `row_count` INT(11) NOT NULL DEFAULT '0',
  `imported_count` INT(11) NOT NULL DEFAULT '0',
  `created_by_user_id` INT(11) NOT NULL,
  `previewed_at` DATETIME,
  `previewed_by_user_id` INT(11),
  `approved_at` DATETIME,
  `approved_by_user_id` INT(11),
  `expires_at` DATETIME NOT NULL,
  `date_created` DATETIME NOT NULL DEFAULT '1000-01-01 00:00:00',
  `date_modified` DATETIME NOT NULL DEFAULT '1000-01-01 00:00:00',
  PRIMARY KEY (`batch_id`),
  KEY `IDX_board_intake_status` (`status_key`),
  KEY `IDX_board_intake_source` (`platform_key`, `source_checksum`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `nesp_board_intake_row` (
  `intake_row_id` INT(11) NOT NULL AUTO_INCREMENT,
  `batch_id` INT(11) NOT NULL,
  `platform_key` VARCHAR(32) NOT NULL,
  `source_row_number` INT(11) NOT NULL,
  `external_id` VARCHAR(255),
  `first_name` VARCHAR(128) NOT NULL,
  `last_name` VARCHAR(128) NOT NULL,
  `email` VARCHAR(255) NOT NULL,
  `phone` VARCHAR(64) NOT NULL DEFAULT '',
  `row_hash` CHAR(64) NOT NULL,
  `idempotency_key` VARCHAR(320),
  `validation_status` VARCHAR(32) NOT NULL DEFAULT 'valid',
  `validation_message` TEXT,
  `duplicate_status` VARCHAR(32) NOT NULL DEFAULT 'unchecked',
  `duplicate_candidate_id` INT(11),
  `review_status` VARCHAR(32) NOT NULL DEFAULT 'pending',
  `candidate_id` INT(11),
  `pii_redacted_at` DATETIME,
  `date_created` DATETIME NOT NULL DEFAULT '1000-01-01 00:00:00',
  `date_modified` DATETIME NOT NULL DEFAULT '1000-01-01 00:00:00',
  PRIMARY KEY (`intake_row_id`),
  UNIQUE KEY `IDX_board_intake_row_hash` (`batch_id`, `row_hash`),
  KEY `IDX_board_intake_external` (`platform_key`, `idempotency_key`),
  KEY `IDX_board_intake_batch` (`batch_id`),
  KEY `IDX_board_intake_duplicate` (`duplicate_status`),
  KEY `IDX_board_intake_review` (`review_status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `nesp_board_intake_identity` (
  `identity_id` INT(11) NOT NULL AUTO_INCREMENT,
  `platform_key` VARCHAR(32) NOT NULL,
  `external_id` VARCHAR(255) NOT NULL,
  `intake_row_id` INT(11) NOT NULL,
  `candidate_id` INT(11),
  `date_created` DATETIME NOT NULL DEFAULT '1000-01-01 00:00:00',
  PRIMARY KEY (`identity_id`),
  UNIQUE KEY `IDX_board_intake_identity` (`platform_key`, `external_id`),
  KEY `IDX_board_intake_identity_candidate` (`candidate_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `joborder`
    (`joborder_id`, `entered_by`, `owner`, `title`, `status`, `city`, `state`, `country`, `date_created`, `date_modified`, `public`)
VALUES
    (41002, 1, 1, 'Weekend Staff Portrait & Team Photographer - Youth Sports', 'Active', 'Methuen', 'MA', 'US', NOW(), NOW(), 1);

INSERT INTO `nesp_feature_flag`
    (`flag_key`, `display_name`, `description`, `is_enabled`, `requires_admin_approval`, `date_created`, `date_modified`)
VALUES
    ('NESP_BOARD_INTAKE_SCHEDULER_ENABLED', 'Board Inbox Scheduler', 'Integration-test fixture.', 0, 1, NOW(), NOW()),
    ('NESP_BOARD_INTAKE_AUTO_IMPORT_ENABLED', 'Board Inbox Auto-Import', 'Integration-test fixture.', 0, 1, NOW(), NOW())
ON DUPLICATE KEY UPDATE
    `is_enabled` = 0,
    `requires_admin_approval` = 1,
    `date_modified` = NOW();
