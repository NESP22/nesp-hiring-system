# AI Candidate Review

## Status

Production-disabled and deferred. No real AI review is run by this Phase 2 dashboard foundation.

Required flag:

- `NESP_AI_REVIEW_ENABLED = 0`

## Allowed Future Inputs

- Job description.
- Application.
- Prescreen answers.
- Resume.
- Vapi structured answers.
- Interviewer scorecard.

Only approved source fields should be sent to a provider.

## Allowed Future Outputs

- Relevant experience facts.
- Schedule facts.
- Travel facts.
- Transportation facts.
- Equipment facts.
- Missing information.
- Conflicting answers.
- Candidate questions.
- Suggested interview questions.
- Source fields used.

## Prohibited

- Ranking candidates.
- Comparing candidates.
- Rejecting, hiring, or changing status.
- Inferring protected characteristics.
- Personality judgments.
- Voice or writing-style personality analysis.
- Saving an AI note without explicit Craig confirmation.

## Future Security Requirements

- Provider abstraction.
- Mock provider.
- Click-to-run confirmation.
- Source-field citations.
- Redacted logs.
- Audit events.
- Clear indication that output is an internal draft for human review.
