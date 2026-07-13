# NESP Web-Only Hiring Workflow Requirements

The completed NESP hiring workflow must be fully usable through a web browser
from Craig's different computers. The local repository is for development and
deployment only. Production workflow features must continue operating when all
developer computers are powered off.

## Hard Production Rule

Production must run in the existing Render-based NESP hiring system.

Production must not depend on:

- Craig's Mac being powered on.
- `localhost` services.
- Local databases.
- Local files outside the approved backup process.
- A particular browser profile.
- A particular computer.
- Manually running background scripts from a desktop.

Production workflow state must not be stored only in:

- Browser `localStorage`.
- Cookies, except secure session handling.
- Local JSON files.
- The developer's computer.
- Temporary container filesystems.

## Production Storage

Production data must be stored in:

- The private persistent MariaDB database.
- Approved Render persistent storage where files, resumes, uploads, exports, or
  generated artifacts are required.

All integration credentials and API keys must remain in Render environment
variables or Render secret configuration. No passwords, tokens, API keys,
database dumps, applicant uploads, or production applicant records may be
committed to the repository or stored on developer machines.

## Required Web Application Areas

The browser-based application must provide access to:

- ADHD-friendly hiring dashboard.
- Applicant records.
- NESP prescreen answers.
- Workflow stages.
- Interviewer management.
- Vapi phone-screen status and results.
- Zoom scheduling.
- Interview scorecards.
- AI candidate reviews.
- Notes and audit history.
- Settings and feature flags.

## Account Requirements

Production must use individual web accounts for:

- Craig as administrator.
- Interviewer 2.
- Interviewer 3.
- Interviewer 4.

Do not create shared interviewer accounts.

Each interviewer must be able to:

- Log in through the browser.
- See only candidates and interviews they are authorized to access.
- View assigned interviews.
- Complete scorecards.
- Add interview notes.
- Avoid access to integration credentials or system administration unless
  explicitly authorized.

Craig must be able to:

- Use any computer.
- See the full dashboard.
- Configure interviewers.
- Review all candidates.
- Approve phone screens.
- Create Zoom interviews.
- Run AI reviews.
- Make final hiring decisions.
- Deactivate interviewer access.

## Current Production Status

Current Render/OpenCATS production already provides these web-hosted pieces:

- Public NESP careers portal.
- Browser-based OpenCATS staff login.
- Applicant records in persistent MariaDB.
- NESP prescreen answers stored in candidate questionnaire history.
- Candidate/job pipeline status history.
- Notes and activity history.
- HTTPS on `careers.nesportsphoto.com`.
- Disabled outbound mail by environment setting.
- Private database and persistent Render storage per the deployment plan.

Current production does not yet fully provide these required pieces:

- ADHD-friendly NESP dashboard.
- Scoped interviewer portal with row-level candidate/interview authorization.
- Individual Interviewer 2/3/4 production accounts with least-privilege access.
- Vapi phone-screen status/results inside the web application.
- Zoom interview creation and scheduling inside the web application.
- Structured interview scorecards.
- Server-side AI candidate review UI and audit trail.
- Feature-flag management UI.
- Logout-all-sessions control.
- Complete server-side audit log for integrations and reviewer actions.

These missing pieces are blockers for calling the complete workflow finished.

## Security Requirements

The completed workflow must include:

- HTTPS for all production browser access.
- Secure server-side sessions.
- CSRF protection on state-changing actions.
- Role-based access control.
- Candidate/interview-level authorization for interviewer users.
- Inactivity/session timeout.
- Logout-all-sessions control where feasible.
- No API keys exposed to browser JavaScript unless strictly required.
- No applicant information exposed to browser JavaScript beyond what the
  authenticated user is authorized to see.
- Secrets stored only in Render environment variables or approved secret
  configuration.
- No production applicant data stored on developer machines.

## Minimum Implementation Architecture

Add NESP-specific server-side workflow tables in MariaDB for:

- Feature flags.
- Interviewer profiles and account mapping.
- Candidate/interview authorization grants.
- Phone-screen requests, status, transcript/result metadata, and Craig approval.
- Zoom meeting records and scheduling status.
- Scorecard templates and completed scorecards.
- AI review requests, outputs, source-field references, and human approval
  status.
- Audit events for reviewer actions, integration actions, settings changes, and
  access changes.

Add NESP-specific PHP modules/pages for:

- Dashboard.
- Interviewer management.
- Candidate workflow details.
- Interview assignment and scorecard completion.
- Vapi phone-screen review.
- Zoom scheduling.
- AI review preview/save.
- Feature flags and integration settings.
- Audit history.

All integration calls must run server-side from the Render application or an
approved Render worker/cron component. No desktop background process may be
required for Vapi, Zoom, AI review, backup, or workflow updates.

## Account Creation Gate

Do not create Interviewer 2/3/4 accounts with broad OpenCATS access if row-level
candidate/interview authorization has not been implemented.

Before creating production interviewer accounts, Craig must provide:

- Real name.
- Email address.
- Intended access scope.
- Whether the interviewer can see notes from other interviewers.
- Whether the interviewer can see resumes/attachments.
- Whether the interviewer can see AI review outputs.
- Whether the interviewer can change candidate workflow stage.

Passwords must be created or reset through the production web admin flow or a
secure Render shell process and delivered outside the repository. Passwords must
not be printed in logs, committed, or stored in docs.

## Feature Flags

New workflow features must default to disabled until Craig approves:

- `NESP_WORKFLOW_ENABLED`
- `NESP_INTERVIEWER_POOL_ENABLED`
- `NESP_PRESCREEN_ENABLED`
- `NESP_VAPI_ENABLED`
- `NESP_ZOOM_ENABLED`
- `NESP_AI_REVIEW_ENABLED`

Outbound email must remain disabled unless Craig separately approves the sender
domain, templates, test plan, opt-out/compliance handling, and production
cutover.

## AI Review Requirements

AI review must:

- Run only when Craig clicks `Analyze Candidate`.
- Use job description, application fields, NESP prescreen answers, notes, and
  resume text only as approved source fields.
- Summarize job-related experience.
- Identify availability, travel, equipment, and scheduling facts.
- Flag missing or conflicting information.
- Suggest tailored interview questions.
- Display source fields used.
- Save the result as an internal review note only after human confirmation.

AI review must not:

- Automatically reject.
- Rank candidates against each other.
- Recommend based on protected characteristics.
- Hire.
- Change candidate status.
- Send email.
- Schedule interviews automatically.

## Vapi And Zoom Requirements

Vapi phone screens must:

- Be requested/approved by Craig or an authorized user through the web app.
- Store status and results in MariaDB.
- Show transcript/result metadata only to authorized users.
- Never make final hiring decisions.

Zoom scheduling must:

- Be initiated through the web app by Craig or an authorized user.
- Store meeting metadata in MariaDB.
- Keep OAuth/API credentials in Render secrets.
- Avoid exposing Zoom credentials to browser JavaScript.
- Respect interviewer and candidate authorization.

## Backup And Restore

Backups must include:

- MariaDB workflow tables.
- OpenCATS applicant data.
- NESP workflow audit data.
- Required persistent files/uploads.

Backups must not require Craig's desktop to be powered on. Off-platform
encrypted backup and restore procedures must remain documented and periodically
tested.

## Launch Gates For Complete Workflow

The complete NESP hiring workflow is not ready until:

- All required production features run in Render-hosted web pages.
- Interviewer access is individual and scoped.
- No production workflow state depends on local desktop storage or scripts.
- Integration credentials are stored only in Render secret configuration.
- AI, Vapi, Zoom, and scorecards have feature flags and audit history.
- Security review confirms HTTPS, sessions, CSRF, RBAC, timeout, and secret
  handling.
- Craig validates the workflow from a different computer/browser session.

## Phase 1 Implementation Status

Phase 1 adds the browser-hosted foundation for the NESP workflow inside the
existing Render/OpenCATS application:

- Authenticated `NESP Hiring` staff tab.
- ADHD-friendly dashboard skeleton with simple status cards.
- Durable MariaDB tables for feature flags, workflow stages, interviewer
  profiles, candidate grants, interviews, scorecards, Vapi status, Zoom status,
  AI review records, and audit events.
- Read-only feature-flag review page.
- Read-only interviewer access summary.
- Read-only audit-log page.
- Schema migration `394` for existing deployments.
- Fresh-install schema coverage in `db/cats_schema.sql`.

Phase 1 intentionally does not:

- Create real interviewer accounts.
- Grant interviewer access to real candidates.
- Place Vapi calls.
- Create Zoom meetings.
- Run AI reviews.
- Send applicant email or SMS.
- Automatically reject, rank, hire, assign, or change candidate status.

All Phase 1 integration flags are seeded disabled. Later phases must add
reviewed admin controls, CSRF-protected state changes, scoped candidate detail
views, and explicit Craig approval gates before any integration can run.
