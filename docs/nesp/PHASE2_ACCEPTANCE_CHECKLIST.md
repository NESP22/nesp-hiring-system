# Phase 2 Acceptance Checklist

## Implemented

- Dedicated branch: `codex/phase2-hosted-hiring-workflow`.
- Draft PR #3 remains unmerged.
- Dashboard queues are query-backed.
- Empty states and sensible queue limits are present.
- Scoped interviewer assigned-candidate views are grant-gated.
- Scorecards support draft save, submit, lock, and Craig/admin unlock.
- Staffing source status, import lineage, import issues, formulas, hiring gap, and draft recommendations are implemented.
- Additive, rollback, fake fixture, and preflight SQL files are present.

## Tested Locally

- `git diff --check`.
- Unit coverage was added for feature flags, dashboard queue definitions, CSV normalization, and forecast formulas.
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
- Read-only Google Drive credentials for real schedule discovery/import.

## Deferred

- Real Google Drive import execution.
- Real historical schedule normalization.
- Screenshot capture if no browser/PHP runtime is available locally.
