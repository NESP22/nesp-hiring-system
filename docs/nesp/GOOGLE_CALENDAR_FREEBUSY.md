# Google Calendar Free/Busy

This is a disabled-by-default scaffold for interviewer availability checks only.
It is intended for a later handoff to the interviewer conflict engine.

## Safety Defaults

- Feature flag: `NESP_GOOGLE_CALENDAR_FREEBUSY_ENABLED = 0`
- Calendar event creation remains disabled. If `NESP_CALENDAR_EVENT_CREATION_ENABLED`
  exists in a deployment, keep it set to `0`.
- No production calendars are connected by this migration.
- No Google Calendar events are created, updated, cancelled, deleted, or invited.
- Free/busy responses are reduced to busy start/end windows only. Event titles,
  descriptions, locations, attendees, and calendar names are not stored or shown.

## OAuth Scope

Use only:

`https://www.googleapis.com/auth/calendar.freebusy`

Google documents this as the Calendar API scope for viewing availability in a
user's calendars. The freeBusy endpoint also accepts broader scopes, but this
integration intentionally uses the narrowest scope.

## Environment Variables

- `NESP_GOOGLE_CALENDAR_CLIENT_ID`
- `NESP_GOOGLE_CALENDAR_CLIENT_SECRET`
- `NESP_GOOGLE_CALENDAR_REDIRECT_URI`
- `NESP_GOOGLE_CALENDAR_TOKEN_ENCRYPTION_KEY`

`NESP_GOOGLE_CALENDAR_TOKEN_ENCRYPTION_KEY` should be a high-entropy secret. A
base64-encoded 32-byte key is preferred. Token values must never be logged,
rendered in templates, or stored unencrypted.

## Token Storage

`nesp_google_calendar_connection` stores:

- encrypted access token
- encrypted refresh token
- token fingerprints for audit/debug correlation
- hashed Google subject and calendar identifiers
- connection state such as `disconnected`, `reauthorize_required`, `connected`,
  and `error`

The current admin action prepares an OAuth consent URL for an approved test
environment. The callback/code exchange should be wired in a later controlled
step using the same table and `NESPGoogleCalendarFreeBusy::encryptToken()`.

## Adapter Contract

`NESPGoogleCalendarFreeBusy::queryFreeBusy()` returns:

- `disabled` when the feature flag is off
- `busy` with sanitized busy windows when Google returns busy intervals
- `available` when Google returns no busy intervals
- `partial_error` for per-calendar free/busy errors
- `reauthorize_required` for revoked/expired authorization responses
- `error` for failed requests

This adapter is read-only and suitable for Agent 2's future conflict engine to
call without receiving event details.
