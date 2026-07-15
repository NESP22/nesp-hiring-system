# Vapi Phone Screen Workflow

## Status

Code foundation added. Production calls remain disabled until all mock/security tests pass and Craig explicitly enables `NESP_VAPI_ENABLED`.

The workflow is candidate-scheduled. Craig approval generates a secure scheduling link; it does not place a call.

Current safety defaults:

- `NESP_VAPI_ENABLED = 0`
- Audio recording disabled.
- Transcript retained only after affirmative consent.
- No email, SMS, ranking, rejection, approval, hiring, assignment, or automatic workflow movement.
- Existing Customer Service Vapi resources must remain unchanged.

## Craig/Admin Flow

1. Open the NESP dashboard.
2. Select `Invite to Schedule Phone Screen` for a candidate/job row.
3. Review the confirmation screen:
   - candidate name
   - role
   - redacted destination phone
   - caller label `NESP Hiring`
   - assistant `NESP Hiring Phone Screen`
   - audio recording `Off`
   - transcription `After consent only`
   - consent notice and role-specific script
   - expected length approximately 7–10 minutes
4. Generate the scheduling link.
5. Copy the invitation text manually. The system does not send email or SMS.
6. Candidate opens the secure link, chooses an available Eastern Time appointment, reschedules, cancels, or requests human follow-up.
7. The hosted Render scheduler runs due-call processing. Craig's Mac or a local process is never required.
8. At the selected time the scheduler confirms `NESP_VAPI_ENABLED=1`, confirms the appointment is still active, atomically claims the call, places exactly one outbound call, and stores provider state.
9. Review Phone Screen shows status, consent, permitted transcript, structured result, original candidate/job links, and webhook event history.
10. Saving a review note requires Craig/admin confirmation and only writes an audit event.

## Copy-Only Invitation

```text
Hi [First Name], thank you for applying for the [Role] position with New England Sports Photo. Please choose a convenient time for a brief 7–10 minute automated phone screen using this secure link: [LINK]. The call will come from our NESP Hiring number. Audio will not be recorded; the conversation will be transcribed only after you consent. Every hiring decision is made by a person.
```

## Candidate Scheduling Page

The candidate page must show NESP branding, role title, expected call length, caller ID label, automated-call notice, no-audio-recording notice, transcription-after-consent notice, Eastern Time appointment windows, and confirmation controls.

The page uses random scheduling tokens and never displays candidate IDs, internal job-order IDs, interviewer/admin notes, integration secrets, or provider resource IDs.

Candidates may:

- select a time
- reschedule
- cancel
- request human follow-up

## Availability Defaults

Craig can edit all values in Phone Screen Availability:

- timezone: `America/New_York`
- Monday-Friday: 9:00 a.m.-6:00 p.m.
- Saturday: 9:00 a.m.-1:00 p.m.
- Sunday: unavailable
- 15-minute booking slots
- 10-minute call duration
- 5-minute buffer
- minimum 2-hour booking notice
- scheduling-link expiration
- earliest and latest call times
- maximum screens per hour and per day
- blackout dates

## Render Scheduler

Run due-call processing from a hosted Render cron job or worker command:

```bash
php modules/nesp/runDuePhoneScreens.php
```

The runner:

- marks due scheduled appointments as `Call Due`
- confirms `NESP_VAPI_ENABLED=1`
- confirms the appointment is active and unrevoked
- confirms no previous call attempt exists
- atomically claims the call
- places exactly one outbound call
- stores provider call ID and status when returned
- does not retry automatically
- audits every action

If a candidate does not answer, the webhook marks `No Answer`. Craig may then choose whether to allow another scheduling attempt.

## Consent Opening

The assistant must open with:

```text
Hi, this is the automated New England Sports Photo hiring assistant regarding your application.

This call will not be audio recorded, but it will be transcribed into text so the NESP hiring team can review your job-related responses. A person makes every hiring decision.

Do you consent to continue with this transcribed phone screening?
```

If consent is unclear or refused, the assistant must stop screening, offer human follow-up, and end politely. The webhook storage path keeps no substantive transcript when consent is refused.

## Role Scripts

Scripts are one question at a time, designed for 7–10 minutes, and contain no scoring or decision language.

- Job 41001: Customer Service, $22-$25/hour, Methuen in-office, weekday daytime availability, phone/email support, experience, issue-resolution example, candidate questions.
- Job 41002: Staff Photographer, $22-$25/hour, early weekends, license/transportation, travel, children/families, NESP equipment/workflow, candidate questions.
- Job 41003: Freelance Photographer, $22-$27/hour, contractor classification, early weekends, 60-90 minute travel, transportation, camera/lens/flash, manual knowledge, experience, candidate questions.
- Job 41005: Field Assistant, $18/hour, early weekends, transportation, outdoor work, group direction, standing/lifting, candidate questions.

## Structured Result

Store factual fields only:

- completed
- consent accepted
- still interested
- pay understood
- schedule understood
- location/travel understood
- transportation confirmed
- equipment confirmed when relevant
- experience summary
- concern/limitation
- candidate questions
- missing clarification
- suggested human interview questions

Do not store numeric scores, voice-trait analysis, protected-characteristic inferences, or automated hire/reject recommendations.
