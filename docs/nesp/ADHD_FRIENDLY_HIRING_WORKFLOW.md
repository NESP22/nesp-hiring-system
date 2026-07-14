# ADHD-Friendly Hiring Workflow

## Design Goal

The NESP dashboard should answer one question at a time: what needs attention now?

It avoids a traditional ATS feel by using plain-language queues, short candidate cards, one main action per card, and visible waiting ownership.

## Main Views

- `Needs Craig`: work Craig can act on now.
- `Waiting`: work blocked by applicants or interviewers.
- `Interviews`: upcoming and follow-up interview work.
- `Completed`: finished, held, withdrawn, hired, or not selected work.
- `Staffing Forecast`: production-disabled planning foundation.
- `Settings`: admin-only controls and audit review.

## Card Rules

Each card should show:

- Candidate name.
- Applied role.
- Current stage.
- Waiting on whom.
- One factual summary sentence.
- Last activity.
- One large primary action.
- Small secondary actions.

## Queue Rules

- `Needs Craig` comes first.
- Overdue work is visible without relying only on color.
- Waiting queues separate applicant blockers from interviewer blockers.
- Dense tables are not the default candidate workflow view.
- Settings tables are allowed because they are admin maintenance tools, not Craig's daily triage view.

## Current Implementation

- Implemented: dashboard navigation, queue definitions, candidate cards, queue counts, upcoming interviews, recently completed work, feature-disabled screen, mobile overflow handling.
- Implemented: scorecard submissions return to Craig's review queue.
- Implemented: interviewer accountability counts include assigned candidates with no scorecard yet.
- Tested: unit, integration, default Behat, security Behat, Docker lint/build.
- Fixture-only: demo candidates and staffing history.
- Production-disabled: workflow and interviewer pool until Craig enables flags.

## Deferred

- Sanitized screenshots after an approved local preview login is available.
- Real staffing schedule import.
- Real interviewer accounts and applicant-facing scheduling.
- Any automatic messages or integrations.
