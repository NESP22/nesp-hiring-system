/* NESP screening questionnaire rollback migration.
 *
 * Run only after preserving audit evidence. This removes only the additive
 * questionnaire launch tables and leaves candidate records, stages, Vapi,
 * interviewer settings, recruiting controls, and feature flags otherwise
 * untouched except keeping NESP_VAPI_ENABLED off.
 */

UPDATE `nesp_feature_flag`
SET `is_enabled` = 0,
    `date_modified` = NOW()
WHERE `flag_key` = 'NESP_VAPI_ENABLED';

DROP TABLE IF EXISTS `nesp_screening_questionnaire_activity`;
DROP TABLE IF EXISTS `nesp_screening_questionnaire_answer`;
DROP TABLE IF EXISTS `nesp_screening_questionnaire`;
