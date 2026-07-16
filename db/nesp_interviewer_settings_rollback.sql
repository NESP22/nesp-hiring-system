/* NESP interviewer settings rollback.
 *
 * Run only after a verified production backup. This removes additive
 * interviewer-settings structures while preserving legacy OpenCATS data.
 */

DROP TABLE IF EXISTS `nesp_interviewer_blackout`;
DROP TABLE IF EXISTS `nesp_interviewer_availability_override`;
DROP TABLE IF EXISTS `nesp_interviewer_job_role`;

ALTER TABLE `nesp_interviewer_profile`
    DROP INDEX IF EXISTS `IDX_account_state_key`,
    DROP INDEX IF EXISTS `IDX_availability_status_key`,
    DROP COLUMN IF EXISTS `account_state_key`,
    DROP COLUMN IF EXISTS `timezone`,
    DROP COLUMN IF EXISTS `availability_status_key`,
    DROP COLUMN IF EXISTS `availability_closed_until`,
    DROP COLUMN IF EXISTS `availability_close_reason`,
    DROP COLUMN IF EXISTS `max_interviews_per_day`,
    DROP COLUMN IF EXISTS `max_interviews_per_week`,
    DROP COLUMN IF EXISTS `min_notice_minutes`,
    DROP COLUMN IF EXISTS `default_interview_minutes`,
    DROP COLUMN IF EXISTS `buffer_minutes`,
    DROP COLUMN IF EXISTS `earliest_time`,
    DROP COLUMN IF EXISTS `latest_time`,
    DROP COLUMN IF EXISTS `craig_must_attend`,
    DROP COLUMN IF EXISTS `may_recommend`,
    DROP COLUMN IF EXISTS `private_admin_notes`,
    DROP COLUMN IF EXISTS `last_login_at`,
    DROP COLUMN IF EXISTS `email_warning`;
