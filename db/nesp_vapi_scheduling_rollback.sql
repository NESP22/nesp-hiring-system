/* NESP candidate-scheduled Vapi phone-screen rollback migration.
 *
 * Run only after setting NESP_VAPI_ENABLED=0 and preserving audit evidence
 * needed for incident review. This rollback removes scheduling-specific
 * additions but leaves the core Vapi phone-screen and webhook tables intact.
 */

UPDATE `nesp_feature_flag`
SET `is_enabled` = 0,
    `date_modified` = NOW()
WHERE `flag_key` = 'NESP_VAPI_ENABLED';

DROP TABLE IF EXISTS `nesp_vapi_scheduling_activity`;
DROP TABLE IF EXISTS `nesp_vapi_blackout_date`;
DROP TABLE IF EXISTS `nesp_vapi_availability_block`;
DROP TABLE IF EXISTS `nesp_vapi_phone_screen_setting`;

ALTER TABLE `nesp_vapi_phone_screen`
    DROP INDEX IF EXISTS `IDX_scheduler_due`,
    DROP INDEX IF EXISTS `IDX_scheduled_start_at_utc`,
    DROP INDEX IF EXISTS `IDX_scheduling_token_hash`,
    DROP COLUMN IF EXISTS `last_scheduler_error`,
    DROP COLUMN IF EXISTS `scheduler_claim_key`,
    DROP COLUMN IF EXISTS `call_attempt_count`,
    DROP COLUMN IF EXISTS `call_attempted_at`,
    DROP COLUMN IF EXISTS `call_claimed_at`,
    DROP COLUMN IF EXISTS `reschedule_count`,
    DROP COLUMN IF EXISTS `candidate_scheduling_note`,
    DROP COLUMN IF EXISTS `scheduled_timezone`,
    DROP COLUMN IF EXISTS `scheduled_start_et`,
    DROP COLUMN IF EXISTS `scheduled_end_at_utc`,
    DROP COLUMN IF EXISTS `scheduled_start_at_utc`,
    DROP COLUMN IF EXISTS `scheduling_invitation_copied_at`,
    DROP COLUMN IF EXISTS `invitation_copy_text`,
    DROP COLUMN IF EXISTS `scheduling_link_url`,
    DROP COLUMN IF EXISTS `scheduling_link_created_at`,
    DROP COLUMN IF EXISTS `scheduling_token_used_at`,
    DROP COLUMN IF EXISTS `scheduling_token_revoked_at`,
    DROP COLUMN IF EXISTS `scheduling_token_expires_at`,
    DROP COLUMN IF EXISTS `scheduling_token_hash`;
