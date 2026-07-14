/* NESP candidate-scheduled Vapi phone-screen additive migration.
 *
 * Safe defaults:
 * - Does not enable NESP_VAPI_ENABLED.
 * - Does not place calls, send email, send SMS, or create applicant fixtures.
 * - Adds scheduling-link, appointment, availability, blackout, and activity
 *   storage for a hosted Render-compatible scheduler.
 */

ALTER TABLE `nesp_vapi_phone_screen`
    ADD COLUMN IF NOT EXISTS `scheduling_token_hash` CHAR(64) COLLATE utf8mb4_unicode_ci,
    ADD COLUMN IF NOT EXISTS `scheduling_token_expires_at` DATETIME,
    ADD COLUMN IF NOT EXISTS `scheduling_token_revoked_at` DATETIME,
    ADD COLUMN IF NOT EXISTS `scheduling_token_used_at` DATETIME,
    ADD COLUMN IF NOT EXISTS `scheduling_link_created_at` DATETIME,
    ADD COLUMN IF NOT EXISTS `scheduling_link_url` VARCHAR(512) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
    ADD COLUMN IF NOT EXISTS `invitation_copy_text` TEXT COLLATE utf8mb4_unicode_ci,
    ADD COLUMN IF NOT EXISTS `scheduling_invitation_copied_at` DATETIME,
    ADD COLUMN IF NOT EXISTS `scheduled_start_at_utc` DATETIME,
    ADD COLUMN IF NOT EXISTS `scheduled_end_at_utc` DATETIME,
    ADD COLUMN IF NOT EXISTS `scheduled_start_et` DATETIME,
    ADD COLUMN IF NOT EXISTS `scheduled_timezone` VARCHAR(64) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'America/New_York',
    ADD COLUMN IF NOT EXISTS `candidate_scheduling_note` VARCHAR(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
    ADD COLUMN IF NOT EXISTS `reschedule_count` INT(11) NOT NULL DEFAULT 0,
    ADD COLUMN IF NOT EXISTS `call_claimed_at` DATETIME,
    ADD COLUMN IF NOT EXISTS `call_attempted_at` DATETIME,
    ADD COLUMN IF NOT EXISTS `call_attempt_count` INT(11) NOT NULL DEFAULT 0,
    ADD COLUMN IF NOT EXISTS `scheduler_claim_key` VARCHAR(96) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
    ADD COLUMN IF NOT EXISTS `last_scheduler_error` VARCHAR(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
    ADD UNIQUE KEY IF NOT EXISTS `IDX_scheduling_token_hash` (`scheduling_token_hash`),
    ADD INDEX IF NOT EXISTS `IDX_scheduled_start_at_utc` (`scheduled_start_at_utc`),
    ADD INDEX IF NOT EXISTS `IDX_scheduler_due` (`status_key`, `scheduled_start_at_utc`, `call_attempt_count`);

CREATE TABLE IF NOT EXISTS `nesp_vapi_phone_screen_setting` (
  `setting_key` VARCHAR(96) COLLATE utf8mb4_unicode_ci NOT NULL,
  `setting_value` VARCHAR(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `date_created` DATETIME NOT NULL DEFAULT '1000-01-01 00:00:00',
  `date_modified` DATETIME NOT NULL DEFAULT '1000-01-01 00:00:00',
  PRIMARY KEY (`setting_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `nesp_vapi_phone_screen_setting`
    (`setting_key`, `setting_value`, `date_created`, `date_modified`)
VALUES
    ('timezone', 'America/New_York', NOW(), NOW()),
    ('slot_minutes', '15', NOW(), NOW()),
    ('call_duration_minutes', '10', NOW(), NOW()),
    ('buffer_minutes', '5', NOW(), NOW()),
    ('min_booking_notice_minutes', '120', NOW(), NOW()),
    ('link_expiration_hours', '168', NOW(), NOW()),
    ('earliest_call_time', '09:00', NOW(), NOW()),
    ('latest_call_time', '18:00', NOW(), NOW()),
    ('max_screens_per_hour', '4', NOW(), NOW()),
    ('max_screens_per_day', '12', NOW(), NOW()),
    ('booking_horizon_days', '14', NOW(), NOW())
ON DUPLICATE KEY UPDATE
    `setting_value` = `setting_value`,
    `date_modified` = `date_modified`;

CREATE TABLE IF NOT EXISTS `nesp_vapi_availability_block` (
  `availability_block_id` INT(11) NOT NULL AUTO_INCREMENT,
  `weekday` TINYINT NOT NULL,
  `start_time` TIME NOT NULL,
  `end_time` TIME NOT NULL,
  `is_available` TINYINT(1) NOT NULL DEFAULT 1,
  `date_created` DATETIME NOT NULL DEFAULT '1000-01-01 00:00:00',
  `date_modified` DATETIME NOT NULL DEFAULT '1000-01-01 00:00:00',
  PRIMARY KEY (`availability_block_id`),
  KEY `IDX_weekday` (`weekday`, `is_available`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `nesp_vapi_availability_block`
    (`weekday`, `start_time`, `end_time`, `is_available`, `date_created`, `date_modified`)
SELECT 1, '09:00:00', '18:00:00', 1, NOW(), NOW()
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `nesp_vapi_availability_block`);
INSERT INTO `nesp_vapi_availability_block`
    (`weekday`, `start_time`, `end_time`, `is_available`, `date_created`, `date_modified`)
SELECT 2, '09:00:00', '18:00:00', 1, NOW(), NOW()
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `nesp_vapi_availability_block` WHERE `weekday` = 2);
INSERT INTO `nesp_vapi_availability_block`
    (`weekday`, `start_time`, `end_time`, `is_available`, `date_created`, `date_modified`)
SELECT 3, '09:00:00', '18:00:00', 1, NOW(), NOW()
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `nesp_vapi_availability_block` WHERE `weekday` = 3);
INSERT INTO `nesp_vapi_availability_block`
    (`weekday`, `start_time`, `end_time`, `is_available`, `date_created`, `date_modified`)
SELECT 4, '09:00:00', '18:00:00', 1, NOW(), NOW()
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `nesp_vapi_availability_block` WHERE `weekday` = 4);
INSERT INTO `nesp_vapi_availability_block`
    (`weekday`, `start_time`, `end_time`, `is_available`, `date_created`, `date_modified`)
SELECT 5, '09:00:00', '18:00:00', 1, NOW(), NOW()
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `nesp_vapi_availability_block` WHERE `weekday` = 5);
INSERT INTO `nesp_vapi_availability_block`
    (`weekday`, `start_time`, `end_time`, `is_available`, `date_created`, `date_modified`)
SELECT 6, '09:00:00', '13:00:00', 1, NOW(), NOW()
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `nesp_vapi_availability_block` WHERE `weekday` = 6);

CREATE TABLE IF NOT EXISTS `nesp_vapi_blackout_date` (
  `blackout_date_id` INT(11) NOT NULL AUTO_INCREMENT,
  `blackout_date` DATE NOT NULL,
  `label` VARCHAR(128) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `date_created` DATETIME NOT NULL DEFAULT '1000-01-01 00:00:00',
  `date_modified` DATETIME NOT NULL DEFAULT '1000-01-01 00:00:00',
  PRIMARY KEY (`blackout_date_id`),
  UNIQUE KEY `IDX_blackout_date` (`blackout_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `nesp_vapi_scheduling_activity` (
  `scheduling_activity_id` INT(11) NOT NULL AUTO_INCREMENT,
  `vapi_phone_screen_id` INT(11),
  `scheduling_token_hash` CHAR(64) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `activity_key` VARCHAR(96) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `ip_hash` CHAR(64) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `user_agent_hash` CHAR(64) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `metadata_json` MEDIUMTEXT COLLATE utf8mb4_unicode_ci,
  `date_created` DATETIME NOT NULL DEFAULT '1000-01-01 00:00:00',
  PRIMARY KEY (`scheduling_activity_id`),
  KEY `IDX_token_activity` (`scheduling_token_hash`, `date_created`),
  KEY `IDX_phone_screen_activity` (`vapi_phone_screen_id`, `date_created`),
  KEY `IDX_ip_activity` (`ip_hash`, `date_created`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

UPDATE `nesp_feature_flag`
SET `is_enabled` = 0,
    `date_modified` = NOW()
WHERE `flag_key` = 'NESP_VAPI_ENABLED';
