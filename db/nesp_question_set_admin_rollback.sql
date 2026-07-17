/* Rollback for NESP questionnaire admin additive migration.
 *
 * Run only after disabling NESP questionnaire workflows and preserving any
 * issued-link review history that must be retained.
 */

ALTER TABLE `nesp_screening_questionnaire`
  DROP KEY IF EXISTS `IDX_questionnaire_set_version`,
  DROP COLUMN IF EXISTS `question_snapshot_json`,
  DROP COLUMN IF EXISTS `question_set_version_id`;

DROP TABLE IF EXISTS `nesp_question_set_role_match`;
DROP TABLE IF EXISTS `nesp_question_set_question`;
DROP TABLE IF EXISTS `nesp_question_set_version`;
DROP TABLE IF EXISTS `nesp_question_set`;
