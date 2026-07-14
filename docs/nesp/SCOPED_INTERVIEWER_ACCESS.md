# Scoped Interviewer Access

## Access Model

Interviewers are scoped by `nesp_interviewer_candidate_grant`.

An interviewer can view a candidate only when:

- The current user is linked to an interviewer profile.
- The interviewer profile is active.
- The candidate/job grant is active.
- The requested candidate ID and job order ID match that grant.
- The candidate is still active.

## Interviewers May See

- Their assigned candidates.
- Their assigned interview details.
- Relevant job information.
- Approved candidate summary fields shown by the assigned-candidate page.
- Their own scorecard draft or submitted scorecard.

## Interviewers Must Not See

- Unassigned candidates.
- Other interviewers' assigned candidates.
- Other interviewers' scorecards.
- Feature flags.
- System administration.
- Integration credentials.
- Deletion controls.
- Hiring, rejection, offer, or candidate-status controls.

## Implemented Safeguards

- Direct candidate URL guesses are rejected by the same grant lookup.
- Scorecard save and submit use scoped access checks.
- Inactive interviewer profiles are denied.
- Admin-only screens use OpenCATS access checks.
- POST routes require CSRF.
- Feature-gated routes fail closed when flags are off.

## Test Coverage

- Unit coverage validates feature-gate routing.
- Integration coverage validates Phase 2 schema and safe defaults.
- Behat default and security suites pass in the local Docker test stack.

Additional targeted authorization tests should be added when real interviewer accounts are configured for a later preview.
