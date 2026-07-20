/* Rollback for NESP hiring workflow QA hardening.
 *
 * Run only with the matching application code rolled back. This intentionally
 * does not reactivate Customer Service grants or Nate role mappings: restoring
 * access automatically would be unsafe. Restore those only through the
 * administrator workflow after human review.
 */

DROP TABLE IF EXISTS `nesp_question_set_builtin_release`;

ALTER TABLE `nesp_screening_questionnaire`
  DROP KEY IF EXISTS `IDX_questionnaire_active_candidate_job`,
  DROP COLUMN IF EXISTS `active_candidate_job_key`;
