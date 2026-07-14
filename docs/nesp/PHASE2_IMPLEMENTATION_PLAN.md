# NESP Phase 2 Implementation Plan

## Checkpoint

- Source/master checkpoint: `141324b27876e9638571079e7d95ca6f6c57225c`
- Required development branch: `codex/phase2-hosted-hiring-workflow`
- Backup branch to create before production deployment: `backup/pre-phase2-workflow`

Codex did not create or verify a production backup. Production backup creation requires a separate controlled task with approved production access.

## Safe Defaults

All Phase 2 feature flags default to disabled:

- `NESP_WORKFLOW_ENABLED`
- `NESP_INTERVIEWER_POOL_ENABLED`
- `NESP_PRESCREEN_ENABLED`
- `NESP_VAPI_ENABLED`
- `NESP_ZOOM_ENABLED`
- `NESP_AI_REVIEW_ENABLED`
- `NESP_STAFFING_FORECAST_ENABLED`
- `NESP_STAFFING_DRIVE_IMPORT_ENABLED`

The dashboard, interviewer workflow, scorecard forms, and staffing forecast do not send email or SMS, initiate Vapi calls, create Zoom meetings, run AI review, deploy to Render, publish postings, create real interviewer accounts, or modify production feature flags.

## Build Scope

1. Preserve the Phase 2 branch from the source checkpoint.
2. Add additive Phase 2 migration SQL and rollback SQL.
3. Convert the NESP dashboard into an ADHD-friendly task board:
   - Needs Craig
   - Waiting
   - Interviews
   - Completed
   - Staffing Forecast
   - Settings
4. Add scoped interviewer views:
   - Assigned-candidates queue
   - Assigned-candidate detail
   - Scorecard submission with explicit grant checks
5. Add admin settings:
   - Writable feature flags with CSRF and audit logging
   - Inactive interviewer-profile staging without creating real accounts
6. Add staffing forecast tables, import lineage, import issue review, draft recommendations, and forecast screen based on imported or fixture historical photographer schedules.
7. Add fake fixtures for local/test review only.
8. Add unit and schema tests.

## Assumptions

- Historical photographer schedules are represented by `nesp_staffing_schedule_history`. The included fixture rows are synthetic and must not be treated as Craig-verified production history.
- Interviewer profiles can be created inactive without attached OpenCATS users. Real interviewer accounts remain a controlled production task.
- Scorecard templates are seeded disabled so Craig can review form wording before enabling workflow use.
- Existing OpenCATS candidate and job records remain the source of candidate identity and role titles.

## Acceptance Checks

- Branch is not `master`.
- `db/nesp_phase2_additive.sql` adds only Phase 2 structures/default-off flags.
- `db/nesp_phase2_rollback.sql` removes Phase 2 additions without touching legacy OpenCATS records.
- `db/nesp_phase2_preflight.sql` reports schema/version/collision counts before controlled deployment.
- NESP dashboard renders task queues with one primary next action per candidate card.
- Settings write routes require POST and a valid CSRF token.
- Assigned-candidate detail rejects users without explicit grants.
- Scorecard submission writes only through scoped grants.
- Staffing forecast reads NESP staffing history rows and does not publish or message anyone.
- Fake fixture SQL is clearly labeled test/local only.

## Production Rollout Record - 2026-07-14

- PR: `https://github.com/NESP22/nesp-hiring-system/pull/3`.
- Approved PR head: `609adca6816b42020d64311fe1d5f1b4e24dffba`.
- Merge method: GitHub merge commit with expected-head protection.
- Merge commit / resulting `master`: `d2be22c37da6ab23f5c3a9c35732742a3d2c43e2`.
- Render service: `nesp-hiring-web`.
- Deployed commit: `d2be22c37da6ab23f5c3a9c35732742a3d2c43e2`.
- Recovery branch: `backup/pre-phase2-workflow` at `141324b27876e9638571079e7d95ca6f6c57225c`.
- Fresh encrypted backup: `20260714T140425Z`, `nesp-hiring-backup-20260714T140425Z.tar.gz.cms`.
- Backup SHA-256: `72de58712e878ed40900946b1afc2f3ae2d30a24941d9b15ba1b11692df0306b`.
- Additive migration: `db/nesp_phase2_additive.sql`.
- Additive migration SHA-256: `58e2cbfeda4756a5886111e4c6592fbb442002c95d77aecbac47d80c4c1f7cd1`.
- Migration result: exit status `0`.
- NESP schema version after migration: `0`.
- Before and after counts: candidates `0`, total jobs `5`, active/public jobs `4`, candidate-job associations `0`.
- Expected additive Phase 2 tables present: `10`.
- Expected named indexes present. `IDX_nesp_import_source` is the non-unique three-column index defined by the approved migration.
- Fake fixture SQL was not applied. Fixture candidate and fixture interviewer counts remained `0`.
- Public checks after deploy/migration returned HTTP `200` for the careers homepage, careers listing, four job pages, four application forms, OpenCATS login, and `render-health.txt`.
- Anonymous NESP route guessing returned the OpenCATS login page.
- Authenticated production dashboard UI verification remains pending Craig/admin login; Codex did not use, print, or infer credentials.

## Production Feature Flags After Rollout

- `NESP_WORKFLOW_ENABLED=1`.
- `NESP_INTERVIEWER_POOL_ENABLED=0`.
- `NESP_PRESCREEN_ENABLED=0`.
- `NESP_VAPI_ENABLED=0`.
- `NESP_ZOOM_ENABLED=0`.
- `NESP_AI_REVIEW_ENABLED=0`.
- `NESP_STAFFING_FORECAST_ENABLED=0`.
- `NESP_STAFFING_DRIVE_IMPORT_ENABLED=0`.

`NESP_WORKFLOW_ENABLED` was enabled through a controlled database-backed rollout action. Audit event `1` recorded actor user ID `1`, old value `0`, new value `1`, and rollout commit `d2be22c37da6ab23f5c3a9c35732742a3d2c43e2`.

Mail remained disabled during rollout: `OPENCATS_MAIL_ENABLED=0`, `MAIL_MAILER` unset, and no SMTP provider variables detected.
