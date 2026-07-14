# NESP Integration Security

## Default State

All external integrations are disabled by default:

- `NESP_PRESCREEN_ENABLED = 0`
- `NESP_VAPI_ENABLED = 0`
- `NESP_ZOOM_ENABLED = 0`
- `NESP_AI_REVIEW_ENABLED = 0`
- `NESP_STAFFING_DRIVE_IMPORT_ENABLED = 0`

## Rules

- Do not commit credentials.
- Do not print API keys, tokens, webhook secrets, encryption keys, passwords, meeting host URLs, applicant data, resumes, transcripts, recordings, or Drive contents.
- Do not run Vapi, Zoom, or AI against real candidates until Craig approves a separate controlled task.
- Do not enable OpenCATS outbound mail as part of Phase 2 dashboard rollout.
- Do not send SMS automatically.
- Do not use AI to rank, reject, hire, compare candidates, infer protected characteristics, or analyze voice or writing style as personality.

## Required Future Controls

Before any real integration is enabled, the implementation must include:

- Provider abstraction.
- Mock/test provider.
- Admin confirmation before any real action.
- Idempotency keys.
- Webhook signature verification where available.
- Timestamp and replay checks.
- Rate limits.
- Redacted logs.
- Audit events.
- Rollback procedure.

## Current Status

- Implemented: feature-flag defaults and disabled-route gating for Phase 2 dashboard, interviewer pool, and staffing forecast surfaces.
- Documented: Vapi, Zoom, AI, and Drive-import requirements.
- Deferred: production credentials, real provider calls, real meetings, real AI analysis, and applicant messaging.
