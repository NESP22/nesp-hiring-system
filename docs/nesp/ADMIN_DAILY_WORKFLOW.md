# NESP Admin Daily Workflow

## Start Here: 6-Step Quickstart

Use this sequence for the next hiring action. In the pinned build, the queue is labeled `Needs Craig`; this is the **Needs Me Now** worklist.

| Step | What to click | Success looks like | Stop gate |
| --- | --- | --- | --- |
| 1. Login | Open `https://careers.nesportsphoto.com/index.php?m=login`; sign in as Craig/admin. | `NESP Hiring` opens and the admin-only controls are available. | Stop if the URL, account, or role is unexpected. |
| 2. Needs Me Now | Click `NESP Hiring`, then `Needs Craig` (the live label for Needs Me Now). | The queue shows the candidate card, exact role, waiting state, and one next action. | Stop on a duplicate, wrong role, or unclear next action. |
| 3. Generate link | On the correct card open `Questionnaire`; verify the set; click `Generate Secure Questionnaire Link`. | The candidate/job and question set are correct; a copy-only invitation is shown. Field Staff 41005 uses **Field Staff Pre-Interview**; photographer 41002/41003 uses **Photographer Pre-Interview**. | Stop before Generate if the role or set is unclear. |
| 4. Manually share | Click `Copy Invitation`, then `Mark Invitation Copied`; send through an approved channel only after separate approval. | `copied` and `manually sent` are tracked separately; the dashboard does not send. | Stop after copying unless sending is explicitly approved. |
| 5. Review response | Click `Questionnaires`, find the candidate under `Completed Questionnaires`, click `Review`; read answers and click `Save Review` only for human notes. | Status is completed, answers match the candidate/job, and no automatic ranking, rejection, hiring, or stage move occurs. | Stop on duplicate, wrong candidate, or unexpected automation. |
| 6. Schedule/track interview | From the card click `Schedule Interview`; check no active interview exists; complete fields; click `Create Interview Preview`. Later click `Track` in `Interviews`, then `Save Human Outcome`. | The interview appears once in Upcoming Interviews; Track shows the same candidate, date, interviewer, and masked participant link. | Stop before sending an invitation, creating Zoom/calendar events, or creating a duplicate. |

## Release And Access Boundary

This guide is pinned to deployed code commit `9bf3760e6d0955d1fb0d399adda9eaa4217319bc` (merged PR #26, July 17, 2026). Confirm deployed head before relying on a control name or workflow.

- Dashboard: `https://careers.nesportsphoto.com/index.php?m=nesp`
- Login prerequisite: sign in through `https://careers.nesportsphoto.com/index.php?m=login` as Craig/admin before opening the dashboard.
- Protected scope: testing is Craig/admin-only. Use synthetic candidates, jobs, interviewer profiles, and files. Do not use real applicant records, real interviewer credentials, real calendar accounts, real Zoom links, or real contact channels.
- NESP applicant/interviewer workflows do not automatically email, SMS, call, create Zoom meetings, create calendar events, rank, reject, hire, or move candidates. This applies to NESP only; it does not disable unrelated legacy OpenCATS features such as password recovery.
- Feature state can differ by environment. Check `Settings` before acting; this manual does not authorize enabling flags, activating users, sending, or importing.

## Purpose

This is the one-page daily guide for Craig. The Phase 2 dashboard is designed to reduce menu-hunting and keep the next hiring action obvious.

## Morning

1. Open `NESP Hiring`.
2. Start on **Needs Me Now**; in this release click the `Needs Craig` navigation label.
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

1. Re-open **Needs Me Now**; in this release click the `Needs Craig` navigation label.
2. Confirm urgent items are handled or intentionally left for tomorrow.
3. Open `Interviews`.
4. Confirm tomorrow's interviews have an owner and clear status.
5. Do not enable integrations unless a separate controlled rollout task approves it.

## Staffing Forecast Check

1. Open `Staffing Forecast`.
2. Confirm the top message says `No historical schedules imported yet.` until Craig approves a real schedule import.
3. Treat all zero or not-enough-history metrics as expected while Drive import remains off.
4. Do not use forecast numbers for hiring decisions until verified historical schedules are imported in a separate controlled task.

The Staffing Forecast does not read a live Google Sheet. Upload an exported `.xlsx` or `.csv` file from Staffing Forecast. **Dry-Run** parses into a temporary review batch; it does not persist source rows. Only individually approved rows persist after a separate backup/import gate. Rerun the dry-run when the source changes or the temporary batch expires.

## Protected Preflight And Dry-Run Gate

Run this gate before generating a questionnaire link, activating an interviewer, or approving a Staffing Forecast import. Keep the test account, candidate, workbook, and links synthetic. Do not use production applicants, real interviewer credentials, or a real Google Drive connection.

### Settings Preflight

1. Sign in as Craig/admin, open the dashboard URL above, then open `Settings`.
2. Record the current value of every feature flag and integration status. Do not enable a flag as part of this checklist.
3. Confirm NESP automatic contact remains disabled: applicant/interviewer email, SMS, Vapi, Zoom API/sync, calendar event creation, AI review, and job ads. Confirm Staffing Drive Import (`NESP_STAFFING_DRIVE_IMPORT_ENABLED`) is disabled when using uploaded-file dry-run.
4. Stop if any disabled integration is unexpectedly enabled, healthy against a real provider, or connected to a real account. Do not "test around" the setting; return to the controlled rollout task.

### Interviewer Login And Scope Test

Use a synthetic interviewer profile and one synthetic candidate/job grant.

1. In `Interviewer Settings`, create a synthetic profile and click **Prepare Login**. Verify it is disabled before testing. One-time details are displayed for manual sharing; the app does not email them.
2. Activate it only for the protected test. Confirm the account is a read-only `nesp_interviewer`, has no admin or site-admin access, and uses a unique temporary credential shared only through the approved channel.
3. Sign in as the interviewer and confirm the assigned candidate/job is visible.
4. Try a direct URL for an unassigned candidate, another interviewer's candidate, an unapproved job, Settings, feature flags, integration credentials, and candidate-status controls. Each must be denied or absent.
5. Sign out and confirm the same protected routes return to the login boundary. Suspend the test login and confirm it can no longer sign in.
6. Record the result as `pass` only when both the allowed path and denied paths are evidenced. A successful login alone is not a scope test.

### Questionnaire Link States

Before selecting **Generate**, open the candidate's exact job and verify the question set. In the admin-enabled release, **Field Staff Pre-Interview** appears first for field/table assistant applicants; Field Staff/Table Greeter `41005` uses it. Staff Photographer `41002` and Freelance Photographer `41003` use **Photographer Pre-Interview**. If the role or set is unclear, stop and ask Craig; do not generate a link.

Track these as separate states in the review note or run log:

| State | Meaning | Required evidence |
| --- | --- | --- |
| `generated` | The system created a tokenized link for the verified candidate, job, and published question-set version. | Candidate, job, set/version, and timestamp |
| `copied` | An authorized operator copied the raw link from the one-time response. | Operator and timestamp; never paste the token into source control or a shared log |
| `manually sent` | An operator sent the copied link through an approved channel. | Channel, recipient, and timestamp; this is not implied by `copied` |
| `response received` | The applicant completed the link and the response is visible for human review. | Submission timestamp and questionnaire result |

Never label a link `sent` merely because it was generated or copied. Review the received answers against the candidate and role before taking the next hiring action.

### Staffing Forecast Dry-Run Approval

1. Upload an approved synthetic/exported `.xlsx` or `.csv` file and run **Dry-Run**. There is no direct Google Sheets integration in this workflow. Confirm the dry-run says no source rows were imported and no external message or integration action occurred; the review batch is temporary.
2. Inspect every displayed review row, including valid rows, duplicate warnings, ambiguous rows, and rows needing review. Check source tab and row number, date, event/location, original staffing text, parsed roles, and total required staff.
3. Approve only the individual rows that were inspected and verified. Do not use **Approve all valid rows** as a substitute for row-by-row review. Leave ambiguous, duplicate, malformed, or unexpected rows unapproved and run a new dry-run after correcting the source.
4. Before any controlled import, verify the fresh encrypted backup, the exact approved-row count, and that Staffing Drive Import remains disabled. Stop before **Import Approved Rows** unless Craig separately approves that controlled task.
5. After the protected dry-run, clear the synthetic selection and discard the temporary file. Do not import protected rows. If import accidentally starts, stop and escalate; do not continue or contact anyone.

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

### Stop-Before Gates

- **Before send:** stop after copying a synthetic questionnaire or interview invitation. Do not send unless a separate approval explicitly authorizes the protected send.
- **Before import:** stop after reviewing and selecting synthetic staffing rows. Do not click **Import Approved Rows** unless separately approved and a fresh encrypted backup is verified.
- **Before activation:** leave the synthetic interviewer suspended unless the protected login test is explicitly approved for that run.

## Safety Notes

- The dashboard does not hire, reject, rank, or message applicants automatically.
- The interviewer-pool foundation and staffing-forecast shell are live, but no real interviewer accounts or staffing history have been added.
- Vapi, Zoom, AI review, prescreening, outbound email, SMS, and Drive import remain disabled.
- Calendar event creation, job-ad publishing, and other automatic contact remain disabled for NESP unless a separate controlled rollout approves them. Unrelated legacy OpenCATS password recovery is outside this NESP setting.
