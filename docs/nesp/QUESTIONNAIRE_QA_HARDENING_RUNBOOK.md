# Questionnaire QA Hardening Runbook

Apply this release only with the matching application code. It does not enable email, SMS, calls, calendars, Zoom, AI review, job ads, or any other external integration.

## Migration order

1. `db/nesp_screening_questionnaire_additive.sql`
2. `db/nesp_question_set_admin_additive.sql`
3. `db/nesp_interviewer_settings_additive.sql`
4. `db/nesp_hiring_workflow_qa_hardening_additive.sql`

The fourth migration adds a unique active-questionnaire lock, preserves only the newest active duplicate as usable history, makes Customer Service role `41001` unavailable to interviewer grants, and returns Nate's staged profile to profile-only with no job roles.

## Publish the requested questionnaire content

After the code and migration are in place, open **NESP Hiring > Manage Question Sets** once as an authorized administrator. The application compares the current Field Staff First and Photographer Pre-Interview sets with the approved built-in release. If an older deployed version is current, it creates and audits a new immutable published version. Existing issued links retain their stored snapshots.

Verify both current sets show the requested labels:

- `Field Staff Pre-Interview` with `Field Staff First` in its introduction.
- `Photographer Pre-Interview` with `Photographer Pre-Interview - Staff or Freelance` in its introduction.

## Rollback

Run `db/nesp_hiring_workflow_qa_hardening_rollback.sql` only after rolling back the matching application code. It removes the release-tracking table and active-questionnaire lock. It deliberately does not reactivate Customer Service grants or Nate's prior roles; any access restoration must be reviewed and performed through the interviewer administration workflow.
