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

No production rollback was needed after enabling the safe foundation flags. `NESP_INTERVIEWER_POOL_ENABLED` and `NESP_STAFFING_FORECAST_ENABLED` are live, but no production applicant records, candidate assignments, interviewer accounts, interviewer profiles, staffing imports, email, SMS, Vapi, Zoom, AI, or Drive changes occurred.

No rollback was needed after deploying PR #7 and the PR #8 public scheduler hotfix. The deployed hotfix commit is `a806ceea0c2bf34f09942a4a76ee33e32a2c192a`.

Verified safe state after PR #8:

- `NESP_VAPI_ENABLED=0`.
- OpenCATS mail disabled.
- Render scheduled-call cron not created.
- Candidate count unchanged at zero.
- Candidate-job association count unchanged at zero.
- No call placed.
- No applicant contacted.
- No ad published.
- Customer Service Vapi resources were not touched.
- Missing/invalid scheduling links show the safe branded unavailable page.

The public endpoint verification created harmless scheduling-activity audit rows for missing/invalid token checks. They do not contain applicant data, raw scheduling tokens, transcripts, phone numbers, or provider resource IDs.

Immediate feature rollback:

- Set `NESP_INTERVIEWER_POOL_ENABLED = 0` to hide interviewer dashboards and scoped assigned-candidate routes.
- Set `NESP_STAFFING_FORECAST_ENABLED = 0` to hide the staffing forecast shell.
- Leave `NESP_STAFFING_DRIVE_IMPORT_ENABLED = 0`, `NESP_PRESCREEN_ENABLED = 0`, `NESP_VAPI_ENABLED = 0`, `NESP_ZOOM_ENABLED = 0`, and `NESP_AI_REVIEW_ENABLED = 0`.

## Vapi Immediate Rollback

Use this section for the hiring phone-screen integration only.

1. Set `NESP_VAPI_ENABLED=0`.
2. Disable or remove the hiring assistant server URL if the webhook is compromised.
3. Rotate `VAPI_WEBHOOK_SECRET`.
4. Preserve `nesp_audit_event` and `nesp_vapi_webhook_event` for incident review.
5. If schema rollback is required, run `db/nesp_vapi_phone_screen_rollback.sql` only after preserving any audit evidence needed.

Rollback SQL:

```sql
UPDATE nesp_feature_flag
SET is_enabled = 0,
    date_modified = NOW()
WHERE flag_key = 'NESP_VAPI_ENABLED';
```

Do not delete or alter the existing Customer Service Vapi phone number or assistants during hiring rollback.
