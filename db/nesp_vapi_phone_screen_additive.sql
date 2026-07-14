/* NESP Vapi phone-screen additive migration.
 *
 * Safe defaults:
 * - Does not enable NESP_VAPI_ENABLED.
 * - Does not create calls, applicants, emails, SMS messages, or fixtures.
 * - Stores only hashed/redacted destination phone references.
 */

ALTER TABLE `nesp_vapi_phone_screen`
    ADD COLUMN IF NOT EXISTS `call_request_key` CHAR(64) COLLATE utf8mb4_unicode_ci,
    ADD COLUMN IF NOT EXISTS `destination_phone_hash` CHAR(64) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
    ADD COLUMN IF NOT EXISTS `destination_phone_last4` CHAR(4) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
    ADD COLUMN IF NOT EXISTS `consent_status` VARCHAR(64) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'not_requested',
    ADD COLUMN IF NOT EXISTS `consent_requested_at` DATETIME,
    ADD COLUMN IF NOT EXISTS `consent_response_raw` VARCHAR(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
    ADD COLUMN IF NOT EXISTS `consent_accepted_at` DATETIME,
    ADD COLUMN IF NOT EXISTS `transcript_text` MEDIUMTEXT COLLATE utf8mb4_unicode_ci,
    ADD COLUMN IF NOT EXISTS `structured_result_json` MEDIUMTEXT COLLATE utf8mb4_unicode_ci,
    ADD COLUMN IF NOT EXISTS `provider_end_reason` VARCHAR(128) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
    ADD COLUMN IF NOT EXISTS `last_webhook_event_id` VARCHAR(128) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
    ADD COLUMN IF NOT EXISTS `last_webhook_at` DATETIME,
    ADD COLUMN IF NOT EXISTS `requested_by_user_id` INT(11),
    ADD COLUMN IF NOT EXISTS `caller_label` VARCHAR(128) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'NESP Hiring',
    ADD COLUMN IF NOT EXISTS `assistant_label` VARCHAR(128) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'NESP Hiring Phone Screen',
    ADD COLUMN IF NOT EXISTS `started_at` DATETIME,
    ADD COLUMN IF NOT EXISTS `completed_at` DATETIME,
    ADD COLUMN IF NOT EXISTS `cancelled_at` DATETIME,
    ADD UNIQUE KEY IF NOT EXISTS `IDX_call_request_key` (`call_request_key`),
    ADD UNIQUE KEY IF NOT EXISTS `IDX_provider_call_id` (`provider_call_id`),
    ADD INDEX IF NOT EXISTS `IDX_consent_status` (`consent_status`),
    ADD INDEX IF NOT EXISTS `IDX_last_webhook_event_id` (`last_webhook_event_id`);

CREATE TABLE IF NOT EXISTS `nesp_vapi_webhook_event` (
  `vapi_webhook_event_id` INT(11) NOT NULL AUTO_INCREMENT,
  `provider_event_id` VARCHAR(128) COLLATE utf8mb4_unicode_ci NOT NULL,
  `provider_call_id` VARCHAR(128) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `event_type` VARCHAR(96) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `event_timestamp` DATETIME,
  `payload_sha256` CHAR(64) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `redacted_payload_json` MEDIUMTEXT COLLATE utf8mb4_unicode_ci,
  `processed_at` DATETIME,
  `date_created` DATETIME NOT NULL DEFAULT '1000-01-01 00:00:00',
  PRIMARY KEY (`vapi_webhook_event_id`),
  UNIQUE KEY `IDX_provider_event_id` (`provider_event_id`),
  KEY `IDX_provider_call_id` (`provider_call_id`),
  KEY `IDX_event_type` (`event_type`),
  KEY `IDX_event_timestamp` (`event_timestamp`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

UPDATE `nesp_feature_flag`
SET `is_enabled` = 0,
    `date_modified` = NOW()
WHERE `flag_key` = 'NESP_VAPI_ENABLED';

UPDATE `nesp_integration_status`
SET `status_key` = 'disabled',
    `message` = 'Vapi phone screening code is installed. Outbound calls remain disabled until Craig enables NESP_VAPI_ENABLED after mock/security validation.',
    `date_modified` = NOW()
WHERE `integration_key` = 'vapi';
