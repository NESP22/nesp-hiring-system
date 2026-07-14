# Vapi Webhook Security

## Endpoint

```text
https://careers.nesportsphoto.com/modules/nesp/vapiWebhook.php
```

The endpoint is sessionless and does not require a browser login. It returns only minimal JSON and never returns candidate data.

## Required Checks

- HTTPS only, including `X-Forwarded-Proto: https` behind Render.
- POST only.
- `Content-Type: application/json`.
- Request body max: 256 KB.
- Secret header validation with `Authorization: Bearer ...` or `X-Vapi-Secret`.
- Timestamp validation with `X-Vapi-Timestamp`.
- Supported event type validation.
- Provider call ID required.
- Unique provider event ID handling.
- Duplicate delivery idempotency.
- Redacted payload storage.
- No full applicant phone numbers in logs or audit metadata.

## Expected Events

The implementation accepts Vapi server events such as:

- `status-update`
- `transcript`
- `conversation-update`
- `end-of-call-report`
- `hang`

It also accepts normalized internal event names for tests and adapters:

- `call.created`
- `call.ringing`
- `call.answered`
- `call.completed`
- `call.no-answer`
- `call.failed`
- `call.cancelled`
- `structured-result`

## Rejection Cases

Reject:

- missing secret
- wrong secret
- expired timestamp
- malformed JSON
- oversized request
- unsupported event type
- missing call ID

## Replay And Idempotency

`nesp_vapi_webhook_event.provider_event_id` is unique. Duplicate deliveries return success without reapplying the phone-screen update.
