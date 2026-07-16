/* NESP interviewer settings additive migration.
 *
 * Safe defaults:
 * - Does not create OpenCATS users.
 * - Does not activate interviewer access.
 * - Does not send email, SMS, Zoom invitations, or Vapi calls.
 * - Does not create candidate grants or applicant-facing scheduling links.
 */

ALTER TABLE `nesp_interviewer_profile`
    ADD COLUMN IF NOT EXISTS `account_state_key` VARCHAR(64) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'profile_created',
    ADD COLUMN IF NOT EXISTS `timezone` VARCHAR(64) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'America/New_York',
    ADD COLUMN IF NOT EXISTS `availability_status_key` VARCHAR(64) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'open',
    ADD COLUMN IF NOT EXISTS `availability_closed_until` DATETIME,
    ADD COLUMN IF NOT EXISTS `availability_close_reason` TEXT COLLATE utf8mb4_unicode_ci,
    ADD COLUMN IF NOT EXISTS `max_interviews_per_day` INT(11) NOT NULL DEFAULT '3',
    ADD COLUMN IF NOT EXISTS `max_interviews_per_week` INT(11) NOT NULL DEFAULT '12',
    ADD COLUMN IF NOT EXISTS `min_notice_minutes` INT(11) NOT NULL DEFAULT '1440',
    ADD COLUMN IF NOT EXISTS `default_interview_minutes` INT(11) NOT NULL DEFAULT '30',
    ADD COLUMN IF NOT EXISTS `buffer_minutes` INT(11) NOT NULL DEFAULT '15',
    ADD COLUMN IF NOT EXISTS `earliest_time` TIME NOT NULL DEFAULT '09:00:00',
    ADD COLUMN IF NOT EXISTS `latest_time` TIME NOT NULL DEFAULT '17:00:00',
    ADD COLUMN IF NOT EXISTS `craig_must_attend` TINYINT(1) NOT NULL DEFAULT '0',
    ADD COLUMN IF NOT EXISTS `may_recommend` TINYINT(1) NOT NULL DEFAULT '1',
    ADD COLUMN IF NOT EXISTS `private_admin_notes` TEXT COLLATE utf8mb4_unicode_ci,
    ADD COLUMN IF NOT EXISTS `last_login_at` DATETIME,
    ADD COLUMN IF NOT EXISTS `email_warning` TEXT COLLATE utf8mb4_unicode_ci,
    ADD COLUMN IF NOT EXISTS `default_zoom_join_url` VARCHAR(1000) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
    ADD INDEX IF NOT EXISTS `IDX_account_state_key` (`account_state_key`),
    ADD INDEX IF NOT EXISTS `IDX_availability_status_key` (`availability_status_key`);

CREATE TABLE IF NOT EXISTS `nesp_interviewer_job_role` (
  `interviewer_job_role_id` INT(11) NOT NULL AUTO_INCREMENT,
  `interviewer_profile_id` INT(11) NOT NULL,
  `joborder_id` INT(11) NOT NULL,
  `role_key` VARCHAR(64) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `is_active` TINYINT(1) NOT NULL DEFAULT '1',
  `created_by_user_id` INT(11),
  `date_created` DATETIME NOT NULL DEFAULT '1000-01-01 00:00:00',
  `date_modified` DATETIME NOT NULL DEFAULT '1000-01-01 00:00:00',
  PRIMARY KEY (`interviewer_job_role_id`),
  UNIQUE KEY `IDX_interviewer_job_role` (`interviewer_profile_id`, `joborder_id`),
  KEY `IDX_joborder_id` (`joborder_id`),
  KEY `IDX_role_key` (`role_key`),
  KEY `IDX_is_active` (`is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `nesp_interviewer_availability_override` (
  `override_id` INT(11) NOT NULL AUTO_INCREMENT,
  `interviewer_profile_id` INT(11) NOT NULL,
  `override_date` DATE NOT NULL,
  `override_type_key` VARCHAR(64) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'available',
  `start_time` TIME,
  `end_time` TIME,
  `timezone` VARCHAR(64) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'America/New_York',
  `private_reason` TEXT COLLATE utf8mb4_unicode_ci,
  `is_active` TINYINT(1) NOT NULL DEFAULT '1',
  `created_by_user_id` INT(11),
  `date_created` DATETIME NOT NULL DEFAULT '1000-01-01 00:00:00',
  `date_modified` DATETIME NOT NULL DEFAULT '1000-01-01 00:00:00',
  PRIMARY KEY (`override_id`),
  KEY `IDX_interviewer_profile_id` (`interviewer_profile_id`),
  KEY `IDX_override_date` (`override_date`),
  KEY `IDX_override_type_key` (`override_type_key`),
  KEY `IDX_is_active` (`is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `nesp_interviewer_blackout` (
  `blackout_id` INT(11) NOT NULL AUTO_INCREMENT,
  `interviewer_profile_id` INT(11) NOT NULL,
  `starts_at` DATETIME NOT NULL,
  `ends_at` DATETIME NOT NULL,
  `is_all_day` TINYINT(1) NOT NULL DEFAULT '0',
  `timezone` VARCHAR(64) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'America/New_York',
  `private_reason` TEXT COLLATE utf8mb4_unicode_ci,
  `is_active` TINYINT(1) NOT NULL DEFAULT '1',
  `created_by_user_id` INT(11),
  `date_created` DATETIME NOT NULL DEFAULT '1000-01-01 00:00:00',
  `date_modified` DATETIME NOT NULL DEFAULT '1000-01-01 00:00:00',
  PRIMARY KEY (`blackout_id`),
  KEY `IDX_interviewer_profile_id` (`interviewer_profile_id`),
  KEY `IDX_blackout_window` (`starts_at`, `ends_at`),
  KEY `IDX_is_active` (`is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
