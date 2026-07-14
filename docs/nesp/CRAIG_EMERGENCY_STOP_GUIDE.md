# Craig Emergency Stop Guide

Use this if scheduled Vapi calling must stop immediately.

## Fast Stop

1. Set `NESP_VAPI_ENABLED=0`.
2. Confirm the setting was saved.
3. Check the scheduler logs to confirm no new calls are being placed.
4. Leave applicant data in place.
5. Notify the technical owner before making further changes.

## Stop Candidate Scheduling Too

If candidates should not schedule new times:

1. Open **Phone Screens**.
2. Revoke active scheduling links that should no longer work.
3. Add blackout dates if a whole day should be blocked.
4. Set availability blocks to unavailable if needed.

## What Not To Touch During An Emergency

- Do not delete applicants.
- Do not delete phone-screen rows.
- Do not modify Customer Service Vapi resources.
- Do not change DNS or Cloudflare.
- Do not change Vapi assistant script, voice, or routing.
- Do not apply database rollback unless a controlled rollback task starts.

## After The Stop

- Record what happened.
- Record when `NESP_VAPI_ENABLED` was turned off.
- Review any calls that were already in progress.
- Decide whether candidates need manual follow-up.
- Restart only after Craig explicitly approves a controlled retry.
