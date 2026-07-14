/* NESP Vapi phone-screen rollback migration.
 *
 * Run only after setting NESP_VAPI_ENABLED=0 and preserving audit history
 * externally if incident review requires it.
 */

UPDATE `nesp_feature_flag`
SET `is_enabled` = 0,
    `date_modified` = NOW()
WHERE `flag_key` = 'NESP_VAPI_ENABLED';

UPDATE `nesp_integration_status`
SET `status_key` = 'disabled',
    `message` = 'Vapi phone screening rolled back. No calls can be placed.',
    `date_modified` = NOW()
WHERE `integration_key` = 'vapi';

DROP TABLE IF EXISTS `nesp_vapi_webhook_event`;

ALTER TABLE `nesp_vapi_phone_screen`
    DROP INDEX IF EXISTS `IDX_call_request_key`,
    DROP INDEX IF EXISTS `IDX_provider_call_id`,
    DROP INDEX IF EXISTS `IDX_consent_status`,
    DROP INDEX IF EXISTS `IDX_last_webhook_event_id`,
    DROP COLUMN IF EXISTS `call_request_key`,
    DROP COLUMN IF EXISTS `destination_phone_hash`,
    DROP COLUMN IF EXISTS `destination_phone_last4`,
    DROP COLUMN IF EXISTS `consent_status`,
    DROP COLUMN IF EXISTS `consent_requested_at`,
    DROP COLUMN IF EXISTS `consent_response_raw`,
    DROP COLUMN IF EXISTS `consent_accepted_at`,
    DROP COLUMN IF EXISTS `transcript_text`,
    DROP COLUMN IF EXISTS `structured_result_json`,
    DROP COLUMN IF EXISTS `provider_end_reason`,
    DROP COLUMN IF EXISTS `last_webhook_event_id`,
    DROP COLUMN IF EXISTS `last_webhook_at`,
    DROP COLUMN IF EXISTS `requested_by_user_id`,
    DROP COLUMN IF EXISTS `caller_label`,
    DROP COLUMN IF EXISTS `assistant_label`,
    DROP COLUMN IF EXISTS `started_at`,
    DROP COLUMN IF EXISTS `completed_at`,
    DROP COLUMN IF EXISTS `cancelled_at`;
