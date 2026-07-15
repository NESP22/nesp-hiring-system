# Zoom And Vapi Activation Guide

## Current State

- `NESP_ZOOM_ENABLED=0`.
- `NESP_VAPI_ENABLED=0`.
- No Zoom meetings are created automatically.
- No Vapi calls are placed automatically.
- No applicant invitation, reminder, email, or SMS is sent automatically.

## Zoom Setup For Craig

Create a dedicated Zoom Server-to-Server OAuth app named `NESP Hiring Interviews`.

Steps:

1. Sign in to the Zoom App Marketplace as the Zoom account owner or administrator.
2. Choose `Develop / Build App`.
3. Create a `Server-to-Server OAuth` application.
4. Name it `NESP Hiring Interviews`.
5. Add only the minimum scopes needed for NESP hiring interviews.
6. Activate the application.
7. Copy the Account ID, Client ID, and Client Secret from Zoom.
8. Enter those values directly in Render environment variables for `nesp-hiring-web`.
9. Never paste credentials into chat, GitHub, source files, screenshots, reports, or documentation.
10. Keep `NESP_ZOOM_ENABLED=0` until mock tests, authorization tests, webhook-security tests, and one Craig-approved test meeting pass.

Zoom documents that Server-to-Server OAuth uses account credentials, the `account_credentials` grant, and app credentials consisting of Account ID, Client ID, and Client Secret. Zoom access tokens expire after one hour and use the scopes selected in the Marketplace app.

Source checked: https://developers.zoom.us/docs/internal-apps/s2s-oauth/

## Recommended Zoom Scopes

Use Zoom's current least-privilege Marketplace scope picker for:

- Read the approved host user.
- Create meetings for the approved host user.
- Read meetings created by the integration.
- Update/reschedule meetings created by the integration.
- Delete/cancel meetings created by the integration.
- Receive and verify only approved meeting webhook events.

Do not request broad unrelated Zoom scopes.

## Zoom Environment Variables

Store only in Render:

- `ZOOM_ACCOUNT_ID`
- `ZOOM_CLIENT_ID`
- `ZOOM_CLIENT_SECRET`
- `ZOOM_HOST_USER_ID`
- `ZOOM_WEBHOOK_SECRET_TOKEN`
- `NESP_ZOOM_ENABLED`

## Zoom Meeting Defaults

- Waiting room enabled.
- Passcode enabled.
- Join before host disabled.
- Recording disabled.
- Participant video optional.
- Eastern Time.
- Candidate receives join URL only after Craig approves the exact message.
- Host start URL is never shown to applicants or ordinary interviewers.

## Vapi Current-Number Decision

Do not change Craig's existing Customer Service Vapi number, inbound routing, or assistant.

Before reuse, verify in the Vapi account:

1. Whether the existing number can place outbound hiring calls with a separate hiring assistant selected per call.
2. Whether the number is permanently bound to one assistant or workflow.
3. Whether reuse would mix Customer Service and hiring reporting, call logs, routing, or caller experience.
4. Whether inbound applicant return calls could be routed safely without entering the Customer Service workflow.

Recommendation: use a separate hiring number when practical because it keeps applicant calls, reporting, inbound routing, consent language, and future troubleshooting separate from Customer Service.

Do not buy or provision a second number until Craig reviews:

- Monthly cost.
- Per-minute implications.
- Number location or area code.
- Inbound routing plan.
- Outbound caller-ID behavior.
- Whether texting is included.
- Cancellation procedure.

## Vapi Environment Variables

Store only in Render:

- `VAPI_API_KEY`
- `VAPI_PHONE_NUMBER_ID`
- `VAPI_HIRING_ASSISTANT_ID`
- `VAPI_WEBHOOK_SECRET`
- `NESP_VAPI_ENABLED`

## Activation Gates

All integrations remain disabled until:

- Environment variables exist in Render.
- Mock tests pass.
- Authorization tests pass.
- Webhook-security tests pass.
- A test-mode action succeeds.
- Craig reviews the result.
- Craig explicitly approves production activation.
