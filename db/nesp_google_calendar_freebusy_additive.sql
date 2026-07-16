/* NESP Google Calendar free/busy additive migration.
 *
 * Safe defaults:
 * - Does not enable NESP_GOOGLE_CALENDAR_FREEBUSY_ENABLED.
 * - Does not enable NESP_CALENDAR_EVENT_CREATION_ENABLED.
 * - Does not create, update, delete, invite, or sync calendar events.
 * - Stores only encrypted OAuth tokens and token fingerprints.
 */

CREATE TABLE IF NOT EXISTS `nesp_google_calendar_connection` (
  `google_calendar_connection_id` INT(11) NOT NULL AUTO_INCREMENT,
  `interviewer_profile_id` INT(11) NOT NULL,
  `status_key` VARCHAR(64) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'disconnected',
  `google_subject_hash` CHAR(64) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `calendar_id_hash` CHAR(64) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `token_scope` VARCHAR(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'https://www.googleapis.com/auth/calendar.freebusy',
  `encrypted_access_token` MEDIUMTEXT COLLATE utf8mb4_unicode_ci,
  `encrypted_refresh_token` MEDIUMTEXT COLLATE utf8mb4_unicode_ci,
  `access_token_fingerprint` CHAR(64) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `refresh_token_fingerprint` CHAR(64) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `token_expires_at` DATETIME,
  `connected_at` DATETIME,
  `disconnected_at` DATETIME,
  `reauthorize_required_at` DATETIME,
  `last_freebusy_check_at` DATETIME,
  `last_error` VARCHAR(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `created_by_user_id` INT(11),
  `modified_by_user_id` INT(11),
  `date_created` DATETIME NOT NULL DEFAULT '1000-01-01 00:00:00',
  `date_modified` DATETIME NOT NULL DEFAULT '1000-01-01 00:00:00',
  PRIMARY KEY (`google_calendar_connection_id`),
  UNIQUE KEY `IDX_google_calendar_interviewer` (`interviewer_profile_id`),
  KEY `IDX_status_key` (`status_key`),
  KEY `IDX_reauthorize_required_at` (`reauthorize_required_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `nesp_feature_flag`
    (`flag_key`, `display_name`, `description`, `is_enabled`, `requires_admin_approval`, `date_created`, `date_modified`)
SELECT
    'NESP_GOOGLE_CALENDAR_FREEBUSY_ENABLED',
    'Google Calendar Free/Busy',
    'Optional interviewer availability lookup using only Google Calendar free/busy scope. No event details are read and no events are created.',
    0,
    1,
    NOW(),
    NOW()
FROM DUAL
WHERE NOT EXISTS (
    SELECT 1 FROM `nesp_feature_flag` WHERE `flag_key` = 'NESP_GOOGLE_CALENDAR_FREEBUSY_ENABLED'
);

UPDATE `nesp_feature_flag`
SET `is_enabled` = 0,
    `date_modified` = NOW()
WHERE `flag_key` = 'NESP_GOOGLE_CALENDAR_FREEBUSY_ENABLED';

UPDATE `nesp_feature_flag`
SET `is_enabled` = 0,
    `date_modified` = NOW()
WHERE `flag_key` = 'NESP_CALENDAR_EVENT_CREATION_ENABLED';

INSERT INTO `nesp_integration_status`
    (`integration_key`, `display_name`, `status_key`, `message`, `date_created`, `date_modified`)
SELECT
    'google_calendar_freebusy',
    'Google Calendar Free/Busy',
    'disabled',
    'Optional interviewer availability lookup. Uses only free/busy scope and never creates calendar events.',
    NOW(),
    NOW()
FROM DUAL
WHERE NOT EXISTS (
    SELECT 1 FROM `nesp_integration_status` WHERE `integration_key` = 'google_calendar_freebusy'
);
