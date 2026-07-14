# Vapi Phone Screen Workflow

## Status

Production-disabled and deferred. No real Vapi calls are made by this Phase 2 dashboard foundation.

Required flag:

- `NESP_VAPI_ENABLED = 0`

## Future Opening Script

`Thank you for applying to New England Sports Photo. This short phone screen confirms your interest, availability, and understanding of the role. Your responses will be reviewed by a person and will not result in an automatic hiring decision.`

## Future Structured Result

- Call completed.
- Still interested.
- Pay understood.
- Schedule understood.
- Location/travel understood.
- Transportation confirmed.
- Equipment where applicable.
- Candidate concerns.
- Candidate questions.
- Missing clarification.
- Suggested interview questions.

No numeric score is allowed.

## Prohibited

- Production calls without Craig approval.
- Recording by default.
- Automatic rejection, ranking, hiring, or status changes.
- Analysis of accent, tone, emotion, speech speed, personality, or protected characteristics.

## Future Security Requirements

- Provider abstraction.
- Mock provider.
- Webhook signature verification.
- Timestamp and replay checks.
- Idempotency.
- Rate limits.
- Redacted logs.
- Audit events.
