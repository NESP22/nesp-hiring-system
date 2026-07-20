/* NESP automatic applicant questionnaire email additive migration.
 *
 * Safe defaults:
 * - Applicant email remains disabled.
 * - No existing questionnaire is sent or retried.
 * - No SMS, phone, Zoom, calendar, AI, job-board, or employment-decision
 *   feature is enabled.
 */

INSERT INTO `nesp_feature_flag`
    (`flag_key`, `display_name`, `description`, `is_enabled`, `requires_admin_approval`, `date_created`, `date_modified`)
VALUES
    ('NESP_APPLICANT_EMAIL_ENABLED', 'Applicant Questionnaire Email', 'Sends one secure role-specific questionnaire email only after a new applicant has a valid email and linked job.', 0, 1, NOW(), NOW())
ON DUPLICATE KEY UPDATE
    display_name = VALUES(display_name),
    description = VALUES(description),
    is_enabled = 0,
    requires_admin_approval = 1,
    date_modified = NOW();

ALTER TABLE `nesp_screening_questionnaire`
    ADD COLUMN IF NOT EXISTS `auto_email_status_key` VARCHAR(32) NOT NULL DEFAULT 'not_attempted' AFTER `invitation_copied_at`,
    ADD COLUMN IF NOT EXISTS `auto_email_attempted_at` DATETIME NULL AFTER `auto_email_status_key`,
    ADD COLUMN IF NOT EXISTS `auto_email_sent_at` DATETIME NULL AFTER `auto_email_attempted_at`,
    ADD KEY IF NOT EXISTS `IDX_questionnaire_auto_email_status` (`auto_email_status_key`);
