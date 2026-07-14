# Craig Owner Walkthrough - NESP Hiring

This guide is for Craig's day-to-day use of the NESP hiring dashboard after the candidate-scheduled Vapi workflow is deployed and approved. It uses plain steps and assumes all hiring decisions stay human-reviewed.

Sanitized callout screenshot: `docs/nesp/screenshots/craig-phone-screen-workflow-callouts.svg`

## 1. Log In

1. Go to the NESP hiring site.
2. Sign in with Craig's approved OpenCATS administrator account.
3. Open the **NESP Hiring** tab.
4. Start on **Needs Craig**.

Do not share the admin login. Do not create new interviewer or admin accounts without a separate review.

## 2. Review a New Applicant

1. Open **Needs Craig**.
2. Choose the applicant card.
3. Read the application, resume, job-related prescreen answers, and notes.
4. Decide the next human action:
   - keep reviewing,
   - ask for clarification,
   - invite the candidate to schedule a phone screen,
   - hold,
   - or make another human-reviewed stage change.

The system does not automatically rank, reject, or hire anyone.

## 3. Generate a Phone-Screen Scheduling Link

1. From the applicant card, click **Invite to Schedule Phone Screen**.
2. Confirm the role, phone-screen safety settings, and whether the candidate has a usable phone number.
3. Click the button to prepare the scheduling link.
4. Open the generated phone-screen review page.

This does not call the candidate. It only prepares a scheduling link and copy-only invitation text.

## 4. Copy and Send the Invitation Manually

1. On the phone-screen review page, copy the invitation text.
2. Paste it into the communication method Craig chooses outside the system.
3. Review it before sending.
4. Send it manually.
5. Mark the invitation as copied in the dashboard if desired.

The system does not send email or SMS.

## 5. Revoke or Regenerate a Link

Use **Revoke Link** if:

- the wrong person received the link,
- the link was copied into the wrong place,
- the candidate should no longer schedule,
- or Craig wants to stop that invitation.

Use **Allow Reschedule** only when Craig wants the candidate to choose a new time after a no-answer, cancellation, or reschedule request.

Only one active appointment should exist for a candidate phone screen.

## 6. Set Vapi Availability Hours

1. Open **Phone Screens**.
2. Click **Edit Phone Screen Availability**.
3. Set the time zone. The default is **America/New_York**.
4. Add or remove available days and time blocks.
5. Save settings.

Suggested starting settings:

- Monday-Friday: 9:00 a.m.-6:00 p.m.
- Saturday: 9:00 a.m.-1:00 p.m.
- Sunday: unavailable
- 15-minute booking slots
- 10-minute call duration
- 5-minute buffer
- at least 2 hours of booking notice

## 7. How Limits Work

- **Blackout dates** block all phone-screen appointments for that date.
- **Buffers** keep calls from being booked too close together.
- **Notice period** prevents last-minute scheduling.
- **Maximum screens per hour** protects against too many calls in one hour.
- **Maximum screens per day** protects Craig's review workload.
- **Scheduling-link expiration** stops old links from being used.

All appointment times shown to candidates are Eastern Time.

## 8. View Scheduled Calls

Open **Phone Screens** and review these sections:

- Scheduling Links Ready
- Waiting to Schedule
- Phone Screens Today
- Upcoming Phone Screens
- No Answer / Reschedule Needed
- Completed Phone Screens

No calls are placed unless `NESP_VAPI_ENABLED=1` and the hosted scheduler claims a due appointment.

## 9. Handle Common Outcomes

### Completed Calls

1. Open the completed phone screen.
2. Review transcript and structured answers.
3. Add Craig's review note.
4. Decide the next human step.

### No Answer

1. Open **No Answer / Reschedule Needed**.
2. Do not reject automatically.
3. Decide whether to allow one more scheduling attempt.
4. If approved, click **Allow Reschedule** and send the new copy-only invitation manually.

### Reschedule Requests

1. Open the phone-screen detail.
2. Review the candidate request.
3. Allow reschedule only if Craig approves.

### Cancelled Appointments

1. Review why the appointment was cancelled.
2. Decide whether to allow a new link.
3. No automatic candidate stage change happens.

### Human Follow-Up Requests

1. Open the phone-screen detail.
2. Contact the candidate manually if Craig approves.
3. Record the human follow-up note.

## 10. Review Transcripts and Structured Answers

Open the completed phone-screen page. Review:

- consent status,
- transcript,
- structured answers,
- provider call status,
- Craig review notes.

Transcription is allowed only after candidate consent. Audio recording remains off.

## 11. Confirm Audio Recording Remains Off

In **Settings**, review **Vapi Configuration Status**:

- Recording Disabled should say **Yes**.
- Feature Enabled shows whether scheduled calling is on.

For provider-side verification, check the Vapi assistant metadata in a controlled technical task. Do not print or share Vapi IDs or secrets.

## 12. Turn Scheduled Calling On or Off

Scheduled calling is controlled by `NESP_VAPI_ENABLED`.

- Off: candidates can schedule, but no Vapi call is placed.
- On: the hosted scheduler may place due calls after it atomically claims an active appointment.

Only turn it on after a controlled production approval.

## 13. Stop All Calls Immediately

Use the emergency stop guide. The short version:

1. Set `NESP_VAPI_ENABLED=0`.
2. Confirm the hosted scheduler is not placing calls.
3. Revoke or pause affected scheduling links if needed.
4. Do not delete applicant data.

## 14. Verify the Render Scheduler

In a controlled technical task, verify:

- Render service is deployed from the expected commit.
- The scheduler command points to the hosted runner.
- Logs show scheduler checks without errors.
- No duplicate claim attempts are occurring.

Do not create a Render cron job without approval.

## 15. Check Vapi Usage and Status

In Vapi, Craig or an approved technical helper can check:

- usage,
- call status,
- assistant safety settings,
- webhook delivery status,
- failed calls.

Do not modify the Customer Service assistant or phone number.

## 16. Roll Back Safely

Before production rollout, confirm there is a fresh backup and recovery branch. If rollback is needed:

1. Turn `NESP_VAPI_ENABLED=0`.
2. Stop scheduled calling.
3. Restore the approved app commit if needed.
4. Apply rollback SQL only in a controlled task.
5. Verify applicant counts and no unexpected stage changes.

## 17. Remove a Test Candidate

Only remove fake test candidates that Craig intentionally created for testing. Do not delete real applicants casually.

Use the normal OpenCATS candidate tools or a controlled technical cleanup task, then verify:

- fake candidate removed,
- fake candidate-job association removed,
- fake phone-screen row removed if applicable,
- audit trail reviewed.

## 18. Never Change Without Technical Review

Do not change these without technical review:

- Render environment variables,
- Vapi assistant recording or routing settings,
- webhook secrets,
- database migrations,
- cron/scheduler setup,
- feature flags in production,
- DNS or Cloudflare,
- applicant stage automation,
- email/SMS automation,
- paid ad budgets or billing,
- platform account terms or identity verification.
