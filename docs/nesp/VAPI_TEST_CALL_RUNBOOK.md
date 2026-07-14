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
