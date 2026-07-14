# Staffing Forecast Rollback

## Application Rollback

Revert the app to the approved pre-Phase-2 revision or backup branch. Do not merge or deploy additional forecast work during rollback.

## Database Rollback

Use `db/nesp_phase2_rollback.sql` only after a verified production database backup.

Forecast rollback removes only Phase 2 forecast/import structures:

- `nesp_staffing_recommendation`
- `nesp_staffing_import_issue`
- `nesp_staffing_import_row`
- `nesp_staffing_import_batch`
- `nesp_staffing_forecast`
- `nesp_staffing_schedule_history`
- Forecast feature flags

It does not delete native OpenCATS candidates, jobs, users, activities, attachments, or pipeline records.

## Import Undo

For a single bad import, use one-import undo by marking the batch `undone`. This excludes those rows from forecast calculations without deleting the lineage.
