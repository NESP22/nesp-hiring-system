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
6. Add staffing forecast tables and screen based on imported or fixture historical photographer schedules.
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
- NESP dashboard renders task queues with one primary next action per candidate card.
- Settings write routes require POST and a valid CSRF token.
- Assigned-candidate detail rejects users without explicit grants.
- Scorecard submission writes only through scoped grants.
- Staffing forecast reads NESP staffing history rows and does not publish or message anyone.
- Fake fixture SQL is clearly labeled test/local only.
