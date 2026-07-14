# Vapi Phone Screen Workflow

## Status

Code foundation added. Production calls remain disabled until all mock/security tests pass and Craig explicitly enables `NESP_VAPI_ENABLED`.

Current safety defaults:

- `NESP_VAPI_ENABLED = 0`
- Audio recording disabled.
- Transcript retained only after affirmative consent.
- No email, SMS, ranking, rejection, approval, hiring, assignment, or automatic workflow movement.
- Existing Customer Service Vapi resources must remain unchanged.

## Craig/Admin Flow

1. Open the NESP dashboard.
2. Select `Invite to Phone Screen` for a candidate/job row.
3. Review the confirmation screen:
   - candidate name
   - role
   - redacted destination phone
   - caller label `NESP Hiring`
   - assistant `NESP Hiring Phone Screen`
   - audio recording `Off`
   - transcription `After consent only`
   - consent notice and role-specific script
4. Prepare the phone-screen request.
5. Start Call is blocked until `NESP_VAPI_ENABLED=1` and Vapi configuration is healthy.
6. Review Phone Screen shows status, consent, permitted transcript, structured result, original candidate/job links, and webhook event history.
7. Saving a review note requires Craig/admin confirmation and only writes an audit event.

## Consent Opening

The assistant must open with:

```text
Hi, this is the automated New England Sports Photo hiring assistant regarding your application.

This call will not be audio recorded, but it will be transcribed into text so the NESP hiring team can review your job-related responses. A person makes every hiring decision.

Do you consent to continue with this transcribed phone screening?
```

If consent is unclear or refused, the assistant must stop screening, offer human follow-up, and end politely. The webhook storage path keeps no substantive transcript when consent is refused.

## Role Scripts

Scripts are one question at a time, designed for 7-10 minutes, and contain no scoring or decision language.

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
