/* NESP manual interview tracking rollback.
 *
 * Run only after preserving audit history and verifying no active manual
 * interview records depend on these columns.
 */

ALTER TABLE `nesp_interview`
    DROP INDEX IF EXISTS `IDX_nesp_interview_invitation_status`,
    DROP INDEX IF EXISTS `IDX_nesp_interview_outcome`,
    DROP COLUMN IF EXISTS `manual_zoom_join_url`,
    DROP COLUMN IF EXISTS `timezone`,
    DROP COLUMN IF EXISTS `invitation_status_key`,
    DROP COLUMN IF EXISTS `invitation_preview_text`,
    DROP COLUMN IF EXISTS `internal_notes`,
    DROP COLUMN IF EXISTS `outcome_key`,
    DROP COLUMN IF EXISTS `outcome_notes`,
    DROP COLUMN IF EXISTS `scheduled_by_user_id`,
    DROP COLUMN IF EXISTS `reschedule_count`,
    DROP COLUMN IF EXISTS `cancelled_at`,
    DROP COLUMN IF EXISTS `completed_at`;

UPDATE `nesp_integration_status`
SET `message` = 'Disabled in Phase 2. No meetings can be created.',
    `date_modified` = NOW()
WHERE `integration_key` = 'zoom';
