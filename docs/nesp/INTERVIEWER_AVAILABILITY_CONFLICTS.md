# Interviewer Availability Conflict Engine

This slice owns internal interviewer availability/block-time data and conflict checks for manual NESP interviews.

## Per-Interviewer Scheduling Model

- Scheduling evaluates the selected `interviewer_profile_id`; it does not use a shared Craig/NESP calendar, shared availability record, or shared Zoom link.
- Craig/admin users may view and manage interviewer scheduling setup, but each interviewer owns their recurring availability, one-time blocks, vacation/unavailable days, buffers, min notice, and max interview limits.
- Manual interview conflict checks load the selected interviewer's profile, approved roles, availability rows, overrides, blackouts, active NESP interviews, and future external busy windows.

## Feature Gate

- `NESP_INTERVIEWER_AVAILABILITY_ENABLED` is installed disabled by default.
- When disabled, existing manual interview scheduling behavior is preserved.
- When enabled, manual interview save/reschedule checks the selected interviewer against NESP availability data before the internal interview record is written.

## Conflict Inputs

- `nesp_interviewer_profile`: timezone, min notice, default duration, buffer, max interviews/day, max interviews/week, open/closed status.
- `nesp_interviewer_job_role`: approved job roles.
- `nesp_interviewer_availability`: recurring available windows.
- `nesp_interviewer_availability_override`: date-specific available/all-day/unavailable overrides.
- `nesp_interviewer_blackout`: one-time blocked time or all-day unavailable windows.
- `nesp_interview`: existing active NESP interviews for overlap, buffer, and max/day checks.

## Calendar/Zoom Integration Notes

- Do not create, update, cancel, or sync Zoom meetings from this conflict engine.
- Do not connect Google Calendar or any external calendar from this conflict engine.
- Future calendar sync must be per interviewer. PR #20 should connect a selected `interviewer_profile_id` to that interviewer's own calendar account, then feed normalized busy windows through `NESPWorkflow::findSchedulingConflicts(..., $externalBusyWindows)` or `NESPWorkflow::getExternalBusyWindowsForInterviewer($interviewerProfileID, $startTime, $endTime)`.
- Future Zoom UI must be per interviewer. PR #21 should attach any saved default participant/join link to the selected `interviewer_profile_id` through `NESPWorkflow::getDefaultParticipantJoinURLForInterviewer($interviewerProfileID)`. Host/start URLs remain rejected.
- Calendar busy windows and Zoom participant links must never fall back to one global Craig/NESP account unless Craig explicitly approves that as a separate feature.
- Admin conflict override requires a reason and writes `manual_interview_availability_override_used` to `nesp_audit_event`.
