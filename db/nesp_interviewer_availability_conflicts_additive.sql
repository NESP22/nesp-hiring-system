/* NESP interviewer availability conflict checks additive migration.
 *
 * Safe defaults:
 * - Feature flag remains disabled.
 * - No production interviews are rescheduled.
 * - No applicants, interviewers, calendars, Zoom meetings, emails, or SMS are contacted.
 */

INSERT INTO `nesp_feature_flag`
    (`flag_key`, `display_name`, `description`, `is_enabled`, `requires_admin_approval`, `date_created`, `date_modified`)
VALUES
    ('NESP_INTERVIEWER_AVAILABILITY_ENABLED', 'Interviewer Availability', 'Interviewer availability windows, block time, and schedule conflict checks.', 0, 1, NOW(), NOW())
ON DUPLICATE KEY UPDATE
    display_name = VALUES(display_name),
    description = VALUES(description),
    is_enabled = 0,
    requires_admin_approval = 1,
    date_modified = NOW();

ALTER TABLE `nesp_interviewer_profile`
    ADD COLUMN IF NOT EXISTS `min_notice_minutes` INT(11) NOT NULL DEFAULT '1440';
