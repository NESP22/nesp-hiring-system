/* NESP interview participant-link tracking additive migration.
 *
 * Safe defaults:
 * - Stores only an opaque-token hash, never the public tracking token.
 * - Does not send email, SMS, calendar invitations, or Zoom invitations.
 * - Does not add Zoom API, OAuth, webhooks, or meeting automation.
 */

ALTER TABLE `nesp_interview`
    ADD COLUMN IF NOT EXISTS `participant_link_token_hash` CHAR(64) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '' AFTER `manual_zoom_join_url`,
    ADD COLUMN IF NOT EXISTS `participant_link_opened_at` DATETIME NULL AFTER `participant_link_token_hash`,
    ADD COLUMN IF NOT EXISTS `participant_link_last_opened_at` DATETIME NULL AFTER `participant_link_opened_at`,
    ADD COLUMN IF NOT EXISTS `participant_link_open_count` INT(11) NOT NULL DEFAULT '0' AFTER `participant_link_last_opened_at`,
    ADD COLUMN IF NOT EXISTS `participant_link_revoked_at` DATETIME NULL AFTER `participant_link_open_count`,
    ADD INDEX IF NOT EXISTS `IDX_nesp_interview_participant_token` (`participant_link_token_hash`);
