# Zoom Interview Integration

## Status

Production-disabled and deferred. No real Zoom meetings are created by this Phase 2 dashboard foundation.

Required flag:

- `NESP_ZOOM_ENABLED = 0`

## Implemented Foundation

- Interviewer availability records.
- Interview slot records.
- `zoom_status_key` defaults to disabled.
- Manual scheduling data model for later controlled work.

## Future Defaults

- Waiting room on.
- Passcode on.
- Join before host off.
- Recording off.
- Selected interviewer as host or shared host.
- Optional Craig attendance.

## Prohibited

- Creating production Zoom meetings without Craig approval.
- Sending invitations automatically.
- Storing host URLs in ordinary visible notes.
- Changing applicant status automatically.

## Future Work

- Provider abstraction.
- Fake/test Zoom provider.
- Meeting request model.
- Candidate join URL handling.
- Protected host URL storage.
- Reschedule and cancel flows.
- Attendance and no-show audit events.
