# NESP Phase 2 Rollback Runbook

## Required Pre-Rollback Safety

Before any production rollback:

1. Confirm the deployment backup branch exists: `backup/pre-phase2-workflow`.
2. Confirm an independently verified production database backup exists.
3. Confirm no Phase 2 feature flags are intentionally enabled for active production use.
4. Pause any controlled deployment work. Do not merge, deploy, or apply further migrations during rollback.

Codex cannot claim production backup creation without approved production access and independent verification.

## Application Rollback

1. Revert the deployed application revision to the pre-Phase-2 commit or the verified backup branch.
2. Confirm the app is serving the expected pre-Phase-2 revision.
3. Confirm the NESP Phase 2 dashboard routes are no longer exposed in the deployed revision.

## Database Rollback

Run `db/nesp_phase2_rollback.sql` only after backup verification.

If the real interviewer settings migration was applied separately, run `db/nesp_interviewer_settings_rollback.sql` only after disabling interviewer access and confirming no active production interviewer work depends on the new availability/settings tables.

The rollback removes:

- Disabled Phase 2 `NESP_*` flags
- Phase 2 workflow stages
- Disabled standard scorecard template
- `nesp_session_security_event`
- `nesp_staffing_schedule_history`
- `nesp_staffing_import_batch`
- `nesp_staffing_import_row`
- `nesp_staffing_import_issue`
- `nesp_staffing_forecast`
- `nesp_staffing_recommendation`
- Phase 2 dashboard helper columns from `nesp_candidate_workflow`
- Phase 2 interviewer helper columns from `nesp_interviewer_profile`

`db/nesp_interviewer_settings_rollback.sql` additionally removes:

- `nesp_interviewer_job_role`
- `nesp_interviewer_availability_override`
- `nesp_interviewer_blackout`
- New interviewer account-state, availability-status, limit, timing, notes, and warning columns

It does not delete legacy OpenCATS candidates, job orders, users, activities, calendar events, attachments, or standard pipeline records.

## Fixture Cleanup

If `db/nesp_phase2_fake_fixtures.sql` was run in a non-production environment, reset the local/test database from a clean fixture or delete rows with IDs starting at `920001`. Do not run the fake fixture rollback steps in production because the fixture SQL is not intended for production.

## Verification

After rollback:

- `SELECT COUNT(*) FROM nesp_feature_flag WHERE flag_key LIKE 'NESP_%';` returns `0`.
- `SHOW TABLES LIKE 'nesp_staffing_schedule_history';` returns no rows.
- `SHOW TABLES LIKE 'nesp_staffing_import_batch';` returns no rows.
- `SHOW TABLES LIKE 'nesp_staffing_forecast';` returns no rows.
- `SHOW TABLES LIKE 'nesp_session_security_event';` returns no rows.
- Legacy OpenCATS login and candidate pages still load.
- No Vapi, Zoom, AI, email, SMS, or external job-posting action was initiated.

## Production Rollout Recovery Points - 2026-07-14

- Pre-Phase-2 production commit: `141324b27876e9638571079e7d95ca6f6c57225c`.
- Recovery branch: `backup/pre-phase2-workflow` at `141324b27876e9638571079e7d95ca6f6c57225c`.
- Fresh encrypted backup: `/Users/craig/Documents/NESP-Hiring-Backups/daily/20260714T140425Z/nesp-hiring-backup-20260714T140425Z.tar.gz.cms`.
- Backup SHA-256: `72de58712e878ed40900946b1afc2f3ae2d30a24941d9b15ba1b11692df0306b`.
- Merge/deployed commit: `d2be22c37da6ab23f5c3a9c35732742a3d2c43e2`.
- Additive migration applied: `db/nesp_phase2_additive.sql`.
- Additive migration SHA-256: `58e2cbfeda4756a5886111e4c6592fbb442002c95d77aecbac47d80c4c1f7cd1`.
- Migration timestamp: 2026-07-14 rollout window.
- Counts before and after migration: candidates `0`, total jobs `5`, active/public jobs `4`, candidate-job associations `0`.
- Mail state during rollout: `OPENCATS_MAIL_ENABLED=0`, `MAIL_MAILER` unset, no SMTP provider variables detected.

## Immediate Rollback Actions

Use this order for an immediate rollback from the 2026-07-14 Phase 2 foundation rollout:

1. Disable the visible dashboard flag:
   `UPDATE nesp_feature_flag SET is_enabled = 0, date_modified = NOW() WHERE flag_key = 'NESP_WORKFLOW_ENABLED';`
2. Redeploy the recovery branch `backup/pre-phase2-workflow`.
3. Run `db/nesp_phase2_rollback.sql` only after backup verification.
4. Restore the encrypted backup only if application rollback plus SQL rollback does not return the system to the expected healthy baseline.

Keep `NESP_INTERVIEWER_POOL_ENABLED`, `NESP_PRESCREEN_ENABLED`, `NESP_VAPI_ENABLED`, `NESP_ZOOM_ENABLED`, `NESP_AI_REVIEW_ENABLED`, `NESP_STAFFING_FORECAST_ENABLED`, and `NESP_STAFFING_DRIVE_IMPORT_ENABLED` disabled throughout rollback.
