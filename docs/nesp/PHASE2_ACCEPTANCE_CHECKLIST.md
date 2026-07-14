# Phase 2 Acceptance Checklist

## Implemented

- Dedicated branch: `codex/phase2-hosted-hiring-workflow`.
- PR #3 was merged during the controlled 2026-07-14 rollout.
- Phase 2 workflow, interviewer-pool, and staffing-forecast routes are gated by disabled feature flags.
- Dashboard and staffing forecast routes require administrator access.
- Dashboard queues are query-backed.
- Empty states and sensible queue limits are present.
- Top queue counters count the full queue rather than only the visible card slice.
- Scoped interviewer assigned-candidate views are grant-gated.
- Manual interviewer grants require an existing candidate-to-job association.
- Interviewer role routing rules suggest owners by role text or exact job ID.
- Interviewer availability and internal interview slots are stored with Zoom disabled.
- Dashboard follow-through counts show assigned work, open interviews, missing scorecards, overdue work, and availability blocks.
- Scorecards support draft save, submit, lock, and Craig/admin unlock.
- Submitted scorecards return to Craig's review queue with audit logging.
- Staffing source status, import lineage, import issues, formulas, hiring gap, and draft recommendations are implemented.
- Staffing import issues ignore undone imports.
- Staffing multi-role event metrics count the event once while preserving role staffing totals.
- Additive, rollback, fake fixture, and preflight SQL files are present.
- Craig-facing workflow, interviewer, integration-security, and incident/rollback docs are present.

## Tested Locally

- `git diff --check`.
- Local Docker preview image build.
- Focused NESP PHP/template lint inside the Docker preview image.
- NESP unit test subset inside Docker PHP runtime.
- Full PHPUnit unit suite inside the Docker Compose test stack.
- Full PHPUnit integration suite inside the Docker Compose test stack.
- Default Behat suite inside the Docker Compose test stack.
- Security Behat suite inside the Docker Compose test stack.
- Unit coverage was added for feature flags, dashboard queue definitions, routing suggestions, availability defaults, CSV normalization, and forecast formulas.
- Integration coverage was updated for Phase 2 tables and columns.

## Fixture-Only

- `db/nesp_phase2_fake_fixtures.sql`.
- `src/OpenCATS/Tests/Fixtures/nesp/*.csv`.
- No real applicant, interviewer, schedule, league, or staff data is embedded.

## Requires Production Configuration

- `backup/pre-phase2-workflow`: verified at `141324b27876e9638571079e7d95ca6f6c57225c`.
- Verified production database backup: `20260714T140425Z`, SHA-256 `72de58712e878ed40900946b1afc2f3ae2d30a24941d9b15ba1b11692df0306b`.
- Controlled Render deployment: completed for `d2be22c37da6ab23f5c3a9c35732742a3d2c43e2`.
- Controlled additive migration: completed with status `0`.
- Craig approved enabling only `NESP_WORKFLOW_ENABLED`.
- Explicit Craig approval before real interviewer access, applicant self-booking, or Zoom creation is enabled.
- Read-only Google Drive credentials for real schedule discovery/import.

## Production Rollout Verification - 2026-07-14

- PR pre-merge status: open, ready for review, mergeable, approved head `609adca6816b42020d64311fe1d5f1b4e24dffba`, checks successful except intentionally skipped automation/release jobs.
- Merge method: GitHub merge commit with expected-head protection.
- Merge commit / `master`: `d2be22c37da6ab23f5c3a9c35732742a3d2c43e2`.
- Render deployment: live for `d2be22c37da6ab23f5c3a9c35732742a3d2c43e2`.
- Public health after deploy and migration: careers homepage, careers listing, four job pages, four application forms, OpenCATS login, and `render-health.txt` returned HTTP `200`.
- Production counts before and after migration: candidates `0`, total jobs `5`, active/public jobs `4`, candidate-job associations `0`.
- Fake fixture records: `0` candidates and `0` interviewer profiles.
- Workflow records and interviewer grants: `0`.
- All Phase 2 flags remained disabled after migration; only `NESP_WORKFLOW_ENABLED` was later enabled.
- Audit event `1` recorded the workflow flag change with actor user ID `1`, old value `0`, and new value `1`.
- Anonymous users and direct route guessing are sent to the OpenCATS login page.
- Authenticated production dashboard UI verification is pending Craig/admin login. Codex did not use or expose credentials.
- Mail stayed disabled: `OPENCATS_MAIL_ENABLED=0`, `MAIL_MAILER` unset, no SMTP provider variables detected.

## Deferred

- Real Google Drive import execution.
- Real historical schedule normalization.
- Real applicant self-booking pages.
- Real Zoom meeting creation.
- Authenticated production dashboard screenshot capture until Craig/admin login is available.
