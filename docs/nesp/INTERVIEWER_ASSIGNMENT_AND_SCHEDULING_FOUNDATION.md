# NESP Interviewer Assignment and Scheduling Foundation

This document records the Phase 2.1 foundation for interviewer routing,
availability, and Craig's dashboard visibility. It is intentionally limited to
safe internal planning. It does not send applicant messages, create Zoom
meetings, change applicant status, create real interviewer accounts, or grant
real interviewer access by itself.

## What This Adds

- Role routing rules that suggest an interviewer for a job title or exact job
  ID.
- Interviewer availability blocks, such as Tuesday evening or Saturday morning.
- Interview slot records with `zoom_status_key = disabled` by default.
- A dashboard routing panel showing candidates ready for an interviewer and the
  suggested owner.
- A manual admin assignment action that grants one interviewer access to one
  candidate/job pair after Craig confirms it.
- An interviewer follow-through panel showing assigned candidates, open
  interviews, scorecards due, overdue work, and availability blocks.

## ADHD-Friendly Dashboard Intent

Craig should be able to open the NESP dashboard and answer:

1. What needs Craig right now?
2. What is stuck waiting on an applicant?
3. What is stuck waiting on an interviewer?
4. Who owns the next interview step?
5. Which interviewer has overdue scorecards or missing follow-through?
6. Are there enough availability blocks to schedule interviews?

The dashboard remains task-oriented. Routine work should not require digging
through several OpenCATS menus.

## Routing Rules

Routing rules are suggestions only. Example future rules:

- `photographer` -> Suthir or another photographer lead.
- `freelance photographer` -> Suthir or another contractor reviewer.
- `customer service` -> Craig or another office reviewer.

For production, create the inactive interviewer profile first, then add a role
match rule. Craig still approves any real candidate grant.

Manual candidate assignment requires:

- interviewer profile ID
- candidate ID
- job ID
- admin CSRF token

It creates a scoped `nesp_interviewer_candidate_grant` row and writes an audit
event. It does not email the interviewer or applicant.

## Scheduling Foundation

Availability blocks describe when an interviewer can interview applicants. The
current foundation stores the internal schedule shape only.

Later controlled work can add:

- candidate self-booking pages
- booking tokens
- calendar holds
- Zoom meeting creation
- email or SMS confirmations

Those steps must stay behind explicit approval and disabled feature flags until
Craig approves them.

## Safety Defaults

- `NESP_ZOOM_ENABLED` remains disabled.
- `NESP_INTERVIEWER_POOL_ENABLED` remains disabled unless Craig explicitly
  approves enabling it.
- Interview slots default to `zoom_status_key = disabled`.
- Role rules do not automatically grant candidate access.
- Availability blocks do not contact applicants.
- No automatic ranking, rejection, hiring, or candidate status change occurs.

## Rollback

`db/nesp_phase2_rollback.sql` drops the additive routing, availability, and slot
tables:

- `nesp_interviewer_role_rule`
- `nesp_interviewer_availability`
- `nesp_interview_slot`

It does not delete legacy OpenCATS candidates, jobs, users, activities, uploads,
attachments, or public job postings.
