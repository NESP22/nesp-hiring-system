# Vapi Test Call Runbook

## Preconditions

Do not place the controlled test call until all are true:

- mock/security tests passed
- code deployed with `NESP_VAPI_ENABLED=0`
- additive migration applied after a fresh encrypted production backup
- webhook URL configured on the hiring assistant or call-level configuration
- Render variables verified present without printing values
- Craig explicitly confirms the test destination phone
- fake test candidate is clearly labeled

## One Controlled Test Call

1. Confirm no real applicant data is used.
2. Create or select one fake test candidate.
3. Set `NESP_VAPI_ENABLED=1`.
4. Start exactly one call to Craig's approved phone.
5. Verify caller ID is `NESP Hiring`.
6. Verify assistant is `NESP Hiring Phone Screen`.
7. Verify consent is requested before screening questions.
8. Verify audio recording remains off.
9. Verify transcript appears only after affirmative consent.
10. Verify structured results arrive.
11. Verify Craig dashboard review is populated.
12. Verify no automatic stage change, email, SMS, ranking, rejection, approval, hire, or assignment occurs.
13. Archive/delete the fake test record after review.

If any check fails, immediately set `NESP_VAPI_ENABLED=0`.

## Final State

Leave `NESP_VAPI_ENABLED=0` unless Craig explicitly approves continued controlled testing.

## Production Test Log

### 2026-07-14 Controlled Hiring Assistant Test

- Deployed commit: `83ad287b5cacaa6fac8b8ad1ac6052ecf1fab488`.
- Test candidate: fictional, clearly labeled, created only for this validation.
- Test job: Staff Photographer public job.
- `NESP_VAPI_ENABLED` was enabled only for the call-start window, then returned to `0`.
- Exactly one outbound start request was attempted after Craig's confirmation.
- Result: Vapi returned `provider_http_400`; no retry was performed.
- Provider call ID recorded: no.
- Webhook events received: none.
- Audio/video recording settings were not changed by this test.
- Customer Service Vapi resources were not modified.
- Email/SMS sending remained disabled.
- No automatic candidate stage change, ranking, rejection, approval, hire, assignment, email, or SMS occurred.
- Cleanup completed: fictional candidate, candidate-job association, and prepared phone-screen record were deleted.
- Post-cleanup baseline: zero candidates, five total jobs, four active public jobs, zero candidate-job associations, zero phone-screen rows, and `NESP_VAPI_ENABLED=0`.
- Public regression passed for the careers home page, job list, Staff Photographer job detail page, and application page.

Next action: investigate the provider `400` response safely using redacted payload/error metadata only. Do not retry a live call until the request-shape issue is fixed and Craig explicitly approves another controlled test.
