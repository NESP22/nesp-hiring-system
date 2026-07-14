/* NESP Phase 2 production preflight.
 *
 * Read-only report. Do not run from Codex against production. Run only during a
 * controlled deployment review before applying db/nesp_phase2_additive.sql.
 */

SELECT 'module_schema_nesp' AS check_name, COALESCE(MAX(version), -1) AS check_value
FROM module_schema
WHERE name = 'nesp';

SELECT 'candidate_count' AS check_name, COUNT(*) AS check_value
FROM candidate;

SELECT 'job_count' AS check_name, COUNT(*) AS check_value
FROM joborder;

SELECT 'active_public_job_count' AS check_name, COUNT(*) AS check_value
FROM joborder
WHERE status = 'Active'
  AND public = 1;

SELECT 'existing_phase2_tables' AS check_name, COUNT(*) AS check_value
FROM information_schema.tables
WHERE table_schema = DATABASE()
  AND table_name IN (
    'nesp_session_security_event',
    'nesp_interviewer_role_rule',
    'nesp_interviewer_availability',
    'nesp_interview_slot',
    'nesp_staffing_schedule_history',
    'nesp_staffing_import_batch',
    'nesp_staffing_import_row',
    'nesp_staffing_import_issue',
    'nesp_staffing_forecast',
    'nesp_staffing_recommendation'
  );

SELECT table_name AS expected_new_table, table_name IS NOT NULL AS already_exists
FROM information_schema.tables
WHERE table_schema = DATABASE()
  AND table_name IN (
    'nesp_session_security_event',
    'nesp_interviewer_role_rule',
    'nesp_interviewer_availability',
    'nesp_interview_slot',
    'nesp_staffing_schedule_history',
    'nesp_staffing_import_batch',
    'nesp_staffing_import_row',
    'nesp_staffing_import_issue',
    'nesp_staffing_forecast',
    'nesp_staffing_recommendation'
  )
ORDER BY table_name;

SELECT flag_key AS existing_phase2_flag, is_enabled
FROM nesp_feature_flag
WHERE flag_key IN (
  'NESP_WORKFLOW_ENABLED',
  'NESP_INTERVIEWER_POOL_ENABLED',
  'NESP_PRESCREEN_ENABLED',
  'NESP_VAPI_ENABLED',
  'NESP_ZOOM_ENABLED',
  'NESP_AI_REVIEW_ENABLED',
  'NESP_STAFFING_FORECAST_ENABLED',
  'NESP_STAFFING_DRIVE_IMPORT_ENABLED'
)
ORDER BY flag_key;
