# Today Build Status

Date: 2026-07-14

Branch: `codex/phase2-vapi-phone-screen`

Starting commit: `06a9133a71df108ae355c7819e6bd63b5f513a12`

## Overnight Production Status

- PR #7 was merged and deployed with scheduled Vapi calling disabled.
- Additive scheduler and recruiting-control migrations were applied after the verified encrypted production backup.
- PR #8 hotfix was merged and deployed to fix the public phone-screen scheduler bootstrap path.
- Current deployed commit after the hotfix: `a806ceea0c2bf34f09942a4a76ee33e32a2c192a`.
- `NESP_VAPI_ENABLED` remained `0`.
- OpenCATS mail remained disabled.
- No Render scheduled-call cron was created.
- No phone call, applicant contact, email, SMS, ad publication, ad spend, candidate stage change, or Customer Service Vapi modification occurred.
- Public scheduler invalid-token checks returned the safe branded "Scheduling link unavailable" page without stack traces, SQL errors, filesystem paths, raw IDs, or secrets.
- A valid production scheduling-token page was not exercised because no production fake candidate/token may be created without Craig's later approval.

See `docs/nesp/MORNING_HANDOFF_2026-07-15.md` for the owner handoff and approval gates.

## Completed In Code

- Added Vapi safety helper for consent script, role scripts, webhook validation, redaction, status mapping, and outbound payload construction.
- Added sessionless webhook endpoint at `/modules/nesp/vapiWebhook.php`.
- Added admin-only phone-screen list, confirmation, and review screens.
- Added request/start/cancel/review-note actions with CSRF and admin gates.
- Added feature-off outbound call blocking.
- Added additive Vapi phone-screen migration and rollback migration.
- Updated fresh install schema and schema tests.
- Added unit tests for webhook rejection, valid auth, consent refusal transcript suppression, and outbound payload safety.

## Not Done In This Local Build

- No production backup was created.
- No Render deployment was performed.
- No production migration was applied.
- No Vapi live API read/write check was performed.
- No real or test phone call was placed.
- No applicant was emailed, texted, ranked, rejected, approved, hired, assigned, or moved automatically.
- No Customer Service Vapi resource was changed.

## Required Production Follow-Up

1. Create and verify a fresh encrypted production backup.
2. Deploy reviewed code with `NESP_VAPI_ENABLED=0`.
3. Apply `db/nesp_vapi_phone_screen_additive.sql`.
4. Verify public careers, admin login, candidate counts, job counts, mail disabled, and Vapi configuration status.
5. Configure the webhook URL only for the hiring assistant or call-level source.
6. Run mock/security webhook tests against production URL.
7. Ask Craig to explicitly confirm the one controlled test phone.
8. Enable `NESP_VAPI_ENABLED=1` only for the controlled fake-candidate test call.
9. Disable the flag immediately if any check fails.
