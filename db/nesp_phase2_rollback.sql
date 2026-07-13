/* NESP Phase 2 rollback migration.
 *
 * Run only after restoring or independently verifying a production database
 * backup. This removes Phase 2 additive data structures and disabled flags
 * without touching legacy OpenCATS candidate/job data.
 */

DELETE FROM `nesp_scorecard_template`
WHERE `template_key` = 'nesp_standard_interview';

DELETE FROM `nesp_feature_flag`
WHERE `flag_key` IN (
    'NESP_WORKFLOW_ENABLED',
    'NESP_INTERVIEWER_POOL_ENABLED',
    'NESP_PRESCREEN_ENABLED',
    'NESP_VAPI_ENABLED',
    'NESP_ZOOM_ENABLED',
    'NESP_AI_REVIEW_ENABLED'
);

DELETE FROM `nesp_workflow_stage`
WHERE `stage_key` IN (
    'applicant_clarification_requested',
    'interview_confirmation_pending',
    'scorecard_complete',
    'hold',
    'not_selected',
    'withdrawn'
);

DROP TABLE IF EXISTS `nesp_staffing_forecast`;
DROP TABLE IF EXISTS `nesp_staffing_schedule_history`;
DROP TABLE IF EXISTS `nesp_session_security_event`;

ALTER TABLE `nesp_interviewer_profile`
    DROP COLUMN IF EXISTS `can_add_notes`,
    DROP COLUMN IF EXISTS `can_submit_scorecard`;

ALTER TABLE `nesp_candidate_workflow`
    DROP COLUMN IF EXISTS `waiting_on_key`,
    DROP COLUMN IF EXISTS `summary`,
    DROP COLUMN IF EXISTS `next_action_label`,
    DROP COLUMN IF EXISTS `due_at`;
