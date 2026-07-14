# NESP Phase 2 Incident And Rollback

## Stop Conditions

Stop a rollout immediately if any of these happen:

- Production health check fails.
- Candidate, job, or association counts unexpectedly change.
- OpenCATS mail is enabled unexpectedly.
- Installer lock is missing.
- Database or uploads are exposed publicly.
- Feature flags are enabled without Craig's approval.
- A migration partially applies.
- A dashboard route exposes unauthorized candidate data.
- An integration attempts a real call, meeting, email, SMS, ranking, rejection, or hiring action.

## Immediate Actions

1. Do not continue deployment.
2. Do not run additional migrations.
3. Preserve logs and checksums without printing secrets.
4. Disable Phase 2 feature flags if they were enabled.
5. Roll application code back to `backup/pre-phase2-workflow` if needed.
6. Restore from the fresh encrypted backup only if data integrity requires it.

## Rollback Assets

- Recovery branch: `backup/pre-phase2-workflow`.
- Rollback SQL: `db/nesp_phase2_rollback.sql`.
- Rollback runbook: `docs/nesp/PHASE2_ROLLBACK_RUNBOOK.md`.
- Staffing rollback notes: `docs/nesp/STAFFING_FORECAST_ROLLBACK.md`.

## Verification After Rollback

- Careers home returns 200.
- Public job pages return 200.
- Application forms return 200.
- Admin login page loads.
- Candidate count matches the pre-rollout baseline.
- Job count and active/public job count match the pre-rollout baseline.
- OpenCATS mail remains disabled.
- Phase 2 feature flags are disabled or absent.
- No plaintext backup remains outside the approved encrypted location.

## Current Pass

No production rollback was needed in this work session because no production merge, deployment, migration, feature flag, applicant record, account, email, SMS, Vapi, Zoom, AI, or Drive change occurred.
