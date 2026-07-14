# Interviewer Pool And Scorecards

## Purpose

The interviewer pool lets Craig stage future interviewers, assign them to specific candidate/job pairs, and track whether interview work is done.

## Implemented

- Inactive interviewer profiles can be created by an admin.
- Role routing rules can suggest an interviewer by role text or exact job ID.
- Manual candidate grants scope an interviewer to one candidate and one job.
- Candidate grants require an existing candidate-to-job association.
- Interviewers can see their assigned candidates only when their profile is active and the grant is active.
- Scorecards can be saved as drafts.
- Submitted scorecards lock.
- Admins can unlock submitted scorecards from Settings.
- Submitted scorecards move the candidate workflow to Craig review.
- Audit events are written for grants, scorecards, unlocks, and scorecard-complete workflow movement.

## Scorecard Fields

- Candidate attended.
- Schedule requirements understood.
- Travel requirements understood.
- Transportation confirmed.
- Communication met expectations.
- Relevant experience confirmed.
- Equipment confirmed where applicable.
- Strengths.
- Concerns.
- Interview notes.
- Advisory recommendation.

Recommendations are advisory only. They do not hire, reject, rank, or change candidate status automatically.

## Disabled By Default

- `NESP_INTERVIEWER_POOL_ENABLED = 0`
- `NESP_ZOOM_ENABLED = 0`
- `NESP_AI_REVIEW_ENABLED = 0`
- Outbound email and SMS remain disabled.

## Production Setup Still Required

- Craig must approve each real interviewer account separately.
- Craig must approve each real candidate assignment separately.
- Any applicant-facing scheduling or Zoom work requires a later controlled task.
