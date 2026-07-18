# Interviewer Scheduling Integrations

This module is safe-by-default scheduling infrastructure. It stores interviewer
availability, blocked time, Google Calendar connection state, cached free/busy
windows, and interviewer Zoom setup notes. It does not send invitations, create
Zoom meetings, read event details, or call production Google APIs unless a later
approval gate explicitly enables those paths.

## Google Calendar

Default scope:

`https://www.googleapis.com/auth/calendar.freebusy`

Google documents this scope as viewing calendar availability. Do not request
Gmail, Drive, Contacts, event-detail, event-write, or broad Calendar scopes for
the default interviewer connection path.

Official scope reference:
https://developers.google.com/workspace/calendar/api/auth

Stored token fields are encrypted-token fields only. Refresh tokens and
credentials must never be rendered in templates, logs, audit metadata, or error
messages.

## Zoom

Interviewer Zoom setup is recorded as host email, optional meeting URL, status,
and setup notes. Meeting creation remains disabled until Craig approves a
separate production Zoom rollout.

## Scheduling Gate

Before saving or rescheduling an interview, call the scheduling validation path
and block saves when conflicts exist unless an admin override has a reason and
is recorded in audit history.
