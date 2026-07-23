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

Current status after overnight deployment:

- Scheduled-call code is deployed.
- Public scheduler hotfix is deployed.
- `NESP_VAPI_ENABLED=0`.
- Render scheduled-call cron has not been created.
- No production fake candidate exists.
- No valid production scheduling link has been exercised.
- Missing/invalid public scheduling links have been verified safe.

## One Controlled Scheduled Test Call

Craig must explicitly approve this phase before any fake candidate, cron command, Vapi enablement, or call attempt is created.

1. Confirm no real applicant data is used.
2. Create or select one fake test candidate.
3. Craig generates the scheduling link.
4. Craig copies the invitation manually; do not send email or SMS.
5. Schedule the fake candidate through the public token page.
6. Set `NESP_VAPI_ENABLED=1` only for the scheduled call window.
7. Run the hosted Render due-call scheduler once.
8. Verify exactly one outbound call attempt is made at the selected time.
9. Verify caller ID is `NESP Hiring`.
10. Verify assistant is `NESP Hiring Phone Screen`.
11. Verify consent is requested before screening questions.
12. Verify audio recording remains off.
13. Verify transcript appears only after affirmative consent.
14. Verify structured results arrive.
15. Verify Craig dashboard review is populated.
16. Verify no automatic stage change, email, SMS, ranking, rejection, approval, hire, or assignment occurs.
17. Archive/delete the fake test record after review.

If any check fails, immediately set `NESP_VAPI_ENABLED=0`.

## Final State

Leave `NESP_VAPI_ENABLED=0` unless Craig explicitly approves continued controlled testing.
