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
    'nesp_historical_job_staffing',
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
    'nesp_historical_job_staffing',
    'nesp_staffing_forecast',
    'nesp_staffing_recommendation'
  )
ORDER BY table_name;

SELECT CONCAT(table_name, '.', column_name) AS required_phase2_column
FROM information_schema.columns
WHERE table_schema = DATABASE()
  AND (
    (table_name = 'nesp_candidate_workflow' AND column_name IN ('summary', 'next_action_label', 'due_at'))
    OR (table_name = 'nesp_interview' AND column_name IN ('manual_zoom_join_url', 'timezone', 'invitation_status_key', 'outcome_key'))
    OR (table_name = 'nesp_staffing_import_batch' AND column_name IN ('status_key', 'undone_at'))
    OR (table_name = 'nesp_staffing_import_row' AND column_name IN ('source_row_hash', 'raw_source_text', 'unresolved_json'))
    OR (table_name = 'nesp_staffing_import_issue' AND column_name IN ('status_key', 'severity_key'))
    OR (table_name = 'nesp_historical_job_staffing' AND column_name IN ('source_row_hash', 'data_quality_status', 'total_required_staff'))
  )
ORDER BY table_name, column_name;

SELECT 'staffing_import_source_unique_indexes' AS check_name, COUNT(*) AS check_value
FROM information_schema.statistics
WHERE table_schema = DATABASE()
  AND table_name = 'nesp_staffing_import_batch'
  AND index_name = 'IDX_nesp_import_source'
  AND non_unique = 0;

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
