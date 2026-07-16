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
    'NESP_AI_REVIEW_ENABLED',
    'NESP_STAFFING_FORECAST_ENABLED',
    'NESP_STAFFING_DRIVE_IMPORT_ENABLED'
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
DROP TABLE IF EXISTS `nesp_staffing_recommendation`;
DROP TABLE IF EXISTS `nesp_historical_job_staffing`;
DROP TABLE IF EXISTS `nesp_staffing_import_issue`;
DROP TABLE IF EXISTS `nesp_staffing_import_row`;
DROP TABLE IF EXISTS `nesp_staffing_import_batch`;
DROP TABLE IF EXISTS `nesp_staffing_schedule_history`;
DROP TABLE IF EXISTS `nesp_interview_slot`;
DROP TABLE IF EXISTS `nesp_interviewer_availability`;
DROP TABLE IF EXISTS `nesp_interviewer_role_rule`;
DROP TABLE IF EXISTS `nesp_session_security_event`;

ALTER TABLE `nesp_scorecard_response`
    DROP INDEX IF EXISTS `IDX_nesp_scorecard_interview`,
    DROP INDEX IF EXISTS `IDX_nesp_scorecard_submitted`,
    DROP COLUMN IF EXISTS `locked_at`,
    DROP COLUMN IF EXISTS `unlocked_at`,
    DROP COLUMN IF EXISTS `unlocked_by_user_id`,
    DROP COLUMN IF EXISTS `lock_reason`;

ALTER TABLE `nesp_interview`
    DROP INDEX IF EXISTS `IDX_nesp_interview_schedule`,
    DROP INDEX IF EXISTS `IDX_nesp_interview_invitation_status`,
    DROP INDEX IF EXISTS `IDX_nesp_interview_outcome`,
    DROP COLUMN IF EXISTS `manual_zoom_join_url`,
    DROP COLUMN IF EXISTS `timezone`,
    DROP COLUMN IF EXISTS `invitation_status_key`,
    DROP COLUMN IF EXISTS `invitation_preview_text`,
    DROP COLUMN IF EXISTS `internal_notes`,
    DROP COLUMN IF EXISTS `outcome_key`,
    DROP COLUMN IF EXISTS `outcome_notes`,
    DROP COLUMN IF EXISTS `scheduled_by_user_id`,
    DROP COLUMN IF EXISTS `reschedule_count`,
    DROP COLUMN IF EXISTS `cancelled_at`,
    DROP COLUMN IF EXISTS `completed_at`;

ALTER TABLE `nesp_interviewer_profile`
    DROP COLUMN IF EXISTS `can_add_notes`,
    DROP COLUMN IF EXISTS `can_submit_scorecard`;

ALTER TABLE `nesp_candidate_workflow`
    DROP INDEX IF EXISTS `IDX_nesp_dashboard_due`,
    DROP INDEX IF EXISTS `IDX_nesp_waiting_on`,
    DROP COLUMN IF EXISTS `waiting_on_key`,
    DROP COLUMN IF EXISTS `summary`,
    DROP COLUMN IF EXISTS `next_action_label`,
    DROP COLUMN IF EXISTS `due_at`;
