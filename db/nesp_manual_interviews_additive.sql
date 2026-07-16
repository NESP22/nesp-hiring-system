/* NESP manual interview tracking additive migration.
 *
 * Safe defaults:
 * - Does not add Zoom API credentials, OAuth, webhooks, or automatic meeting creation.
 * - Does not send email, SMS, calendar invites, or Zoom invitations.
 * - Stores only the applicant join URL for human-reviewed interview tracking.
 */

ALTER TABLE `nesp_interview`
    ADD COLUMN IF NOT EXISTS `manual_zoom_join_url` VARCHAR(1000) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
    ADD COLUMN IF NOT EXISTS `timezone` VARCHAR(64) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'America/New_York',
    ADD COLUMN IF NOT EXISTS `invitation_status_key` VARCHAR(64) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'not_generated',
    ADD COLUMN IF NOT EXISTS `invitation_preview_text` TEXT COLLATE utf8mb4_unicode_ci,
    ADD COLUMN IF NOT EXISTS `internal_notes` TEXT COLLATE utf8mb4_unicode_ci,
    ADD COLUMN IF NOT EXISTS `outcome_key` VARCHAR(64) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
    ADD COLUMN IF NOT EXISTS `outcome_notes` TEXT COLLATE utf8mb4_unicode_ci,
    ADD COLUMN IF NOT EXISTS `scheduled_by_user_id` INT(11),
    ADD COLUMN IF NOT EXISTS `reschedule_count` INT(11) NOT NULL DEFAULT '0',
    ADD COLUMN IF NOT EXISTS `cancelled_at` DATETIME,
    ADD COLUMN IF NOT EXISTS `completed_at` DATETIME,
    ADD INDEX IF NOT EXISTS `IDX_nesp_interview_invitation_status` (`invitation_status_key`),
    ADD INDEX IF NOT EXISTS `IDX_nesp_interview_outcome` (`outcome_key`);

UPDATE `nesp_integration_status`
SET `message` = 'Manual Zoom interview tracking only. No meetings are created, updated, cancelled, or synced by this app.',
    `date_modified` = NOW()
WHERE `integration_key` = 'zoom';
