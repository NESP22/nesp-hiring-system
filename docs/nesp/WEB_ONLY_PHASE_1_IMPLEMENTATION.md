# NESP Web-Only Hiring Workflow Phase 1

Phase 1 establishes the hosted database and browser UI foundation for the NESP
hiring workflow inside OpenCATS. It does not enable real external services or
create production interviewer accounts.

## Added Staff Pages

- `index.php?m=nesp` - NESP Hiring dashboard skeleton.
- `index.php?m=nesp&a=featureFlags` - read-only feature-flag status.
- `index.php?m=nesp&a=interviewerAccess` - read-only interviewer access
  summary.
- `index.php?m=nesp&a=auditLog` - read-only audit event list.

The tab requires an authenticated OpenCATS session. Admin-only pages require
super-administrator access.

## Database Foundation

Migration `394` and the fresh schema add these MariaDB tables:

- `nesp_feature_flag`
- `nesp_workflow_stage`
- `nesp_candidate_workflow`
- `nesp_interviewer_profile`
- `nesp_interviewer_candidate_grant`
- `nesp_interview`
- `nesp_scorecard_template`
- `nesp_scorecard_response`
- `nesp_integration_status`
- `nesp_vapi_phone_screen`
- `nesp_zoom_interview`
- `nesp_ai_candidate_review`
- `nesp_audit_event`

All production workflow state belongs in MariaDB or approved Render persistent
storage. No Phase 1 workflow state is stored in browser localStorage, local JSON
files, local databases, or desktop background scripts.

## Safe Defaults

Phase 2 supersedes the original Phase 1 flag names with these disabled flags:

- `NESP_WORKFLOW_ENABLED`
- `NESP_INTERVIEWER_POOL_ENABLED`
- `NESP_PRESCREEN_ENABLED`
- `NESP_VAPI_ENABLED`
- `NESP_ZOOM_ENABLED`
- `NESP_AI_REVIEW_ENABLED`

The integration-status rows for Vapi, Zoom, AI review, and applicant email are
also seeded as `disabled`.

## Still Required Later

- Scoped candidate detail pages for interviewers.
- Real Interviewer 2/3/4 account creation after scoped access testing.
- CSRF-protected feature-flag management.
- Scorecard form submission.
- Craig-approved Vapi phone-screen initiation and result capture.
- Craig-approved Zoom scheduling.
- Craig-clicked AI candidate review with protected-characteristic safeguards.
- Stronger session controls, including timeout and logout-all-sessions where
  feasible.
