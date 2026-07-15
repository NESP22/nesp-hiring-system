# Vapi Configuration Guide

## Required Environment Variables

Names only. Never print or commit values.

- `VAPI_API_KEY`
- `VAPI_PHONE_NUMBER_ID`
- `VAPI_HIRING_ASSISTANT_ID`
- `VAPI_WEBHOOK_SECRET`
- `NESP_VAPI_ENABLED`

`NESP_VAPI_ENABLED` must remain `0` until mock/security validation passes.

## Expected Resources

- Phone label: `NESP Hiring`
- Assistant name: `NESP Hiring Phone Screen`
- Audio recording: disabled
- Text transcription: allowed only after affirmative consent

Abort if the configured phone-number ID or assistant ID points to any other resource.

## Webhook URL

Production URL after deployment:

```text
https://careers.nesportsphoto.com/modules/nesp/vapiWebhook.php
```

## Recommended Server URL Location

Prefer assistant-level server URL configuration for `NESP Hiring Phone Screen`, or call-level configuration if Vapi supports it cleanly for this call flow.

Do not set or overwrite the Customer Service phone number, Customer Service assistant, or account-wide URL for this hiring workflow.

Vapi server URL priority is Function, Assistant, Phone Number, then account-wide. Use one source of truth and document it before the test call.

## Authentication

Use a Vapi credential that sends either:

- `Authorization: Bearer <VAPI_WEBHOOK_SECRET>`
- `X-Vapi-Secret: <VAPI_WEBHOOK_SECRET>`

Also configure a timestamp header for replay protection:

- `X-Vapi-Timestamp`

The endpoint rejects missing/wrong secrets, missing/expired timestamps, unsupported event types, malformed JSON, and oversized payloads.
