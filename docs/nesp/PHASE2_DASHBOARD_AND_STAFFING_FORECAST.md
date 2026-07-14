# NESP Phase 2 Dashboard and Staffing Forecast

## Dashboard Model

The NESP dashboard is intentionally task-oriented. Routine hiring work should start at `index.php?m=nesp`, not by moving through several OpenCATS menus.

Primary views:

- Needs Craig: applications, completed phone screens, assignment decisions, completed scorecards, missing notes, and overdue items.
- Waiting: applicant follow-up and interviewer follow-up.
- Interviews: upcoming interview list and interviewer-owned tasks.
- Completed: hired, hold, not selected, withdrawn, declined, and scorecard-complete records.
- Staffing Forecast: seasonal photographer demand planning.
- Settings: feature flags, inactive interviewer-profile staging, and audit-reviewed controls.
- Interviewer routing: admin-created role rules suggest an interviewer by role text or exact job ID.
- Scheduling foundation: interviewer availability and internal interview slots are stored with Zoom disabled.

Each candidate card shows candidate name, applied role, current stage, waiting party, factual summary, last activity, one primary action, and small secondary links.

## Scoped Interviewer Workflow

Interviewers see only assignments granted through `nesp_interviewer_candidate_grant`.

The workflow rejects access unless:

- The interviewer profile is active.
- The profile is attached to the current OpenCATS user.
- The candidate/job grant is active.
- The requested candidate and job order match that grant.

Scorecard submission uses the same grant check and writes to `nesp_scorecard_response`.

## Staffing Forecast

Forecasts use normalized `nesp_staffing_import_row` rows when imported and legacy `nesp_staffing_schedule_history` rows for review context. The included fixture files use synthetic photographer schedule history and are not Craig-verified production history.

The current calculation groups historical rows by month and estimates:

- average weekly event count
- average weekly photographer slots
- average weekly photographer hours
- recommended pool, backup pool, and hiring gap
- low/medium confidence based on available history rows

The forecast is read-only planning guidance. It does not create job postings, send messages, change applicant stages, or enable feature flags.

The interviewer routing and scheduling foundation is also read-only until Craig
approves a specific workflow action. Routing rules suggest an owner but do not
grant candidate access automatically. Availability blocks and interview slots do
not email applicants, send SMS, create Zoom meetings, or change statuses.

## Test Fixture Boundary

`db/nesp_phase2_fake_fixtures.sql` is for local/test review only. It uses synthetic names and `example.test` email addresses. Do not run it in production.
