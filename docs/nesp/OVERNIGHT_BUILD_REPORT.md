# Overnight NESP Hiring System Build Report

## Scope

- Repository: `NESP22/nesp-hiring-system`
- Branch: `codex/phase2-hosted-hiring-workflow`
- Draft PR: `https://github.com/NESP22/nesp-hiring-system/pull/3`
- Production master checkpoint: `141324b27876e9638571079e7d95ca6f6c57225c`
- Recovery branch: `backup/pre-phase2-workflow`
- Production status: unchanged

This report covers safe local and PR-branch development only. No production merge, deployment, migration, feature-flag change, applicant update, interviewer account creation, email, SMS, Vapi call, Zoom meeting, AI review, Google Drive write, scheduler change, CRM change, or `craig-ai-brain` change was performed.

## Implemented in This Pass

- Added route-level feature gates so workflow, interviewer pool, and staffing forecast screens stay disabled unless their Phase 2 flags are explicitly enabled.
- Kept Settings and feature-flag administration reachable for administrators while feature-gated screens are disabled.
- Restricted the NESP dashboard and staffing forecast routes to administrators.
- Added a clear disabled-feature screen that names the required flag and keeps production behavior explicit.
- Fixed dashboard queue counts so top counters reflect the full queue, not just the visible page slice.
- De-duplicated dashboard candidates within each queue and kept overdue items prominent.
- Excluded past scheduled interviews from upcoming-interview lists.
- Updated interviewer accountability counts so assigned candidates with no scorecard are visible as work due.
- Required manual candidate grants to match an existing candidate-to-job association.
- Restored saved scorecard draft answers and recommendations in the interviewer scorecard form.
- Added administrator unlock controls for submitted scorecards from the Settings page.
- Moved submitted scorecards back to Craig's review queue with audit logging.
- Expanded schema-installed checks to cover the Phase 2 workflow and staffing tables/columns.
- Changed staffing import source indexing so an undone import can be re-imported safely while service-level active duplicate checks remain in place.
- Filtered undone staffing imports out of open issue counts.
- Wrapped staffing import persistence in a transaction when the database layer starts one.
- Adjusted forecast metrics so a multi-role event counts as one event while staff totals still count each role assignment.
- Added preflight checks for required Phase 2 columns and the staffing import source index shape.
- Added unit and schema-test coverage for the feature-gate map, multi-role forecast math, staffing import columns, and import index shape.
- Improved mobile table handling with horizontal overflow inside NESP panels/cards.

## Feature Flags

All Phase 2 feature flags remain default-off in migration and schema seed data:

- `NESP_WORKFLOW_ENABLED = 0`
- `NESP_INTERVIEWER_POOL_ENABLED = 0`
- `NESP_PRESCREEN_ENABLED = 0`
- `NESP_VAPI_ENABLED = 0`
- `NESP_ZOOM_ENABLED = 0`
- `NESP_AI_REVIEW_ENABLED = 0`
- `NESP_STAFFING_FORECAST_ENABLED = 0`
- `NESP_STAFFING_DRIVE_IMPORT_ENABLED = 0`

## Validation

- `git diff --check`: passed.
- Local Docker preview image build: passed for pending tree as `nesp-hiring-phase2-preview:overnight-local`.
- Focused NESP PHP/template lint inside Docker preview image: passed.
- NESP unit test subset inside Docker PHP runtime: passed, 16 tests and 76 assertions, with existing PHPUnit warning/deprecation noise.
- Full unit suite inside Docker Compose test stack: passed, 141 tests and 1356 assertions, with existing PHPUnit notices/warnings/deprecations.
- Full integration suite inside Docker Compose test stack: passed, 22 tests and 153 assertions, with existing PHPUnit warnings/deprecations.
- Default Behat suite inside Docker Compose test stack: passed, 28 scenarios and 451 steps.
- Security Behat suite inside Docker Compose test stack: passed, 1411 scenarios and 5873 steps.

## Blocked or Deferred

- Sanitized browser screenshots were not captured in this pass because the local preview browser session was at the OpenCATS login page and no new real or unknown credentials were used.
- Real Google Drive staffing files were not imported. The importer and forecast paths remain fixture/sanitized-data oriented until Craig approves Drive configuration and access in a separate task.
- Vapi, Zoom, AI review, prescreening, outbound email, and SMS remain disabled and were not exercised against real services.
- No production backup, production preflight, production migration, Render deployment, or rollout gate was run in this pass.

## Craig Review Notes

- The dashboard foundation now fails closed behind feature flags, so standard OpenCATS behavior remains the default until Craig intentionally enables a Phase 2 screen.
- The interviewer workflow now makes unfinished interviewer work more visible and sends submitted scorecards back to Craig for a decision instead of leaving them stranded.
- Staffing forecast calculations are still explainable and conservative; they should be reviewed with real historical schedule data only after the Drive import process is explicitly approved.
