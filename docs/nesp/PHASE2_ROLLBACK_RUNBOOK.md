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

The rollback removes:

- Disabled Phase 2 `NESP_*` flags
- Phase 2 workflow stages
- Disabled standard scorecard template
- `nesp_session_security_event`
- `nesp_staffing_schedule_history`
- `nesp_staffing_forecast`
- Phase 2 dashboard helper columns from `nesp_candidate_workflow`
- Phase 2 interviewer helper columns from `nesp_interviewer_profile`

It does not delete legacy OpenCATS candidates, job orders, users, activities, calendar events, attachments, or standard pipeline records.

## Fixture Cleanup

If `db/nesp_phase2_fake_fixtures.sql` was run in a non-production environment, reset the local/test database from a clean fixture or delete rows with IDs starting at `920001`. Do not run the fake fixture rollback steps in production because the fixture SQL is not intended for production.

## Verification

After rollback:

- `SELECT COUNT(*) FROM nesp_feature_flag WHERE flag_key LIKE 'NESP_%';` returns `0`.
- `SHOW TABLES LIKE 'nesp_staffing_schedule_history';` returns no rows.
- `SHOW TABLES LIKE 'nesp_staffing_forecast';` returns no rows.
- `SHOW TABLES LIKE 'nesp_session_security_event';` returns no rows.
- Legacy OpenCATS login and candidate pages still load.
- No Vapi, Zoom, AI, email, SMS, or external job-posting action was initiated.
