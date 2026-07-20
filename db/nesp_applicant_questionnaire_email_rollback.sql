/* Roll back only after a verified backup and after disabling applicant email.
 * Existing questionnaire and audit history are preserved.
 */

UPDATE `nesp_feature_flag`
SET `is_enabled` = 0,
    `date_modified` = NOW()
WHERE `flag_key` = 'NESP_APPLICANT_EMAIL_ENABLED';

ALTER TABLE `nesp_screening_questionnaire`
    DROP INDEX IF EXISTS `IDX_questionnaire_auto_email_status`,
    DROP COLUMN IF EXISTS `auto_email_sent_at`,
    DROP COLUMN IF EXISTS `auto_email_attempted_at`,
    DROP COLUMN IF EXISTS `auto_email_status_key`;

DELETE FROM `nesp_feature_flag`
WHERE `flag_key` = 'NESP_APPLICANT_EMAIL_ENABLED';
