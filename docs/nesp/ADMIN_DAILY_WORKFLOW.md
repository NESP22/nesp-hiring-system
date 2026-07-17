# NESP Admin Daily Workflow

## Purpose

This is the one-page daily guide for Craig. The Phase 2 dashboard is designed to reduce menu-hunting and keep the next hiring action obvious.

## Morning

1. Open `NESP Hiring`.
2. Start on `Needs Craig`.
3. Review new applications and applications awaiting review.
4. Review completed phone screens if any are waiting.
5. Check `Interviews` for today and tomorrow.

## For Each Candidate

1. Read the short factual summary.
2. Look at the current stage and who the candidate is waiting on.
3. Click the one large next-action button.
4. Add a note only when it helps the next person.
5. Move on to the next card.

## Midday

1. Open `Waiting`.
2. Check `Waiting on Applicant` for clarification, phone-screen, confirmation, and reschedule items.
3. Check `Waiting on Interviewer` for scorecards due, scorecards overdue, and missing notes.
4. Use Settings only when an admin change is needed.

## End Of Day

1. Re-open `Needs Craig`.
2. Confirm urgent items are handled or intentionally left for tomorrow.
3. Open `Interviews`.
4. Confirm tomorrow's interviews have an owner and clear status.
5. Do not enable integrations unless a separate controlled rollout task approves it.

## Staffing Forecast Check

1. Open `Staffing Forecast`.
2. Confirm the top message says `No historical schedules imported yet.` until Craig approves a real schedule import.
3. Treat all zero or not-enough-history metrics as expected while Drive import remains off.
4. Do not use forecast numbers for hiring decisions until verified historical schedules are imported in a separate controlled task.

## Protected Preflight And Dry-Run Gate

Run this gate before generating a questionnaire link, activating an interviewer, or approving a Staffing Forecast import. Keep the test account, candidate, workbook, and links synthetic. Do not use production applicants, real interviewer credentials, or a real Google Drive connection.

### Settings Preflight

1. Open `Settings` and record the current value of every feature flag and integration status.
2. Confirm these remain disabled for the protected test: workflow activation, prescreening, Vapi, Zoom, AI review, Staffing Forecast, and Staffing Drive Import (`NESP_STAFFING_DRIVE_IMPORT_ENABLED`). Also confirm outbound email/SMS and any calendar or job-ad connection are disabled or disconnected.
3. Stop if any disabled integration is unexpectedly enabled, healthy against a real provider, or connected to a real account. Do not "test around" the setting; return to the controlled rollout task.

### Interviewer Login And Scope Test

Use a synthetic interviewer profile and one synthetic candidate/job grant.

1. Prepare a login, verify it is disabled, then activate it only for the protected test. Confirm the account is a read-only `nesp_interviewer`, has no admin or site-admin access, and uses a unique temporary credential shared only through the approved channel.
2. Sign in as the interviewer and confirm the assigned candidate/job is visible.
3. Try a direct URL for an unassigned candidate, another interviewer's candidate, an unapproved job, Settings, feature flags, integration credentials, and candidate-status controls. Each must be denied or absent.
4. Sign out and confirm the same protected routes return to the login boundary. Suspend the test login and confirm it can no longer sign in.
5. Record the result as `pass` only when both the allowed path and denied paths are evidenced. A successful login alone is not a scope test.

### Questionnaire Link States

Before selecting **Generate**, open the candidate's exact job and verify the question set. Staff Photographer `41002` and Freelance Photographer `41003` use **Photographer Pre-Interview**. Field Staff/Table Greeter `41005` uses **Field Staff Pre-Interview**. If the role or set is unclear, stop and ask Craig; do not generate a link.

Track these as separate states in the review note or run log:

| State | Meaning | Required evidence |
| --- | --- | --- |
| `generated` | The system created a tokenized link for the verified candidate, job, and published question-set version. | Candidate, job, set/version, and timestamp |
| `copied` | An authorized operator copied the raw link from the one-time response. | Operator and timestamp; never paste the token into source control or a shared log |
| `manually sent` | An operator sent the copied link through an approved channel. | Channel, recipient, and timestamp; this is not implied by `copied` |
| `response received` | The applicant completed the link and the response is visible for human review. | Submission timestamp and questionnaire result |

Never label a link `sent` merely because it was generated or copied. Review the received answers against the candidate and role before taking the next hiring action.

### Staffing Forecast Dry-Run Approval

1. Upload the approved synthetic/exported workbook and run **Dry-Run**. Confirm the dry-run says no source rows were imported and no external message or integration action occurred.
2. Inspect every displayed review row, including valid rows, duplicate warnings, ambiguous rows, and rows needing review. Check source tab and row number, date, event/location, original staffing text, parsed roles, and total required staff.
3. Approve only the individual rows that were inspected and verified. Do not use **Approve all valid rows** as a substitute for row-by-row review. Leave ambiguous, duplicate, malformed, or unexpected rows unapproved and run a new dry-run after correcting the source.
4. Before any controlled import, verify the fresh encrypted backup, the exact approved-row count, and that Staffing Drive Import remains disabled. Stop before import unless Craig separately approves that controlled task.

### Compact Protected End-To-End Matrix

| Check | Protected action | Pass evidence | Stop condition |
| --- | --- | --- | --- |
| Settings | Review all flags and integrations | All required disabled states recorded, including Staffing Drive Import | Any unexpected enabled/connected integration |
| Admin boundary | Use admin-only Settings and question-set screens | Admin can view controls; no link or import is sent automatically | Non-admin reaches an admin screen |
| Interviewer scope | Login, allowed grant, then denied direct URLs | Assigned job works; unassigned job/candidate and admin controls are denied | Any cross-candidate, cross-job, or admin access |
| Question link | Verify role/set, generate, copy, manually send synthetic link | Four states recorded separately; correct published set/version shown | Wrong set, unclear role, or `sent` inferred from `copied` |
| Response review | Submit synthetic response | `response received` is visible and human review is required | Automatic ranking, rejection, hiring, or status change |
| Staffing dry-run | Upload synthetic workbook and inspect all rows | Every row checked; only explicitly reviewed rows selected | Bulk approval without row-by-row inspection |
| Integration safety | Repeat settings check after the test | Flags remain unchanged; no real provider/account activity | Any real email, SMS, Vapi, Zoom, Calendar, Drive, or ad action |

## Safety Notes

- The dashboard does not hire, reject, rank, or message applicants automatically.
- The interviewer-pool foundation and staffing-forecast shell are live, but no real interviewer accounts or staffing history have been added.
- Vapi, Zoom, AI review, prescreening, outbound email, SMS, and Drive import remain disabled.
