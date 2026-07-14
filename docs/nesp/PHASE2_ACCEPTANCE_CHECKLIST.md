# Phase 2 Acceptance Checklist

## Implemented

- Dedicated branch: `codex/phase2-hosted-hiring-workflow`.
- Draft PR #3 remains unmerged.
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

- `backup/pre-phase2-workflow`.
- Verified production database backup.
- Controlled Render deployment.
- Controlled additive migration.
- Explicit Craig approval before any feature flag is enabled.
- Explicit Craig approval before real interviewer access, applicant self-booking, or Zoom creation is enabled.
- Read-only Google Drive credentials for real schedule discovery/import.

## Deferred

- Real Google Drive import execution.
- Real historical schedule normalization.
- Real applicant self-booking pages.
- Real Zoom meeting creation.
- Screenshot capture until an approved local preview login is available.
- Production backup, Render preflight, merge, deployment, and migration remain separate controlled tasks.
