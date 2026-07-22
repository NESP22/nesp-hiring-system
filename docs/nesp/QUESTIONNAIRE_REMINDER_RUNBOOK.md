# Questionnaire Reminder Runbook

## Behavior

- The initial questionnaire email remains the existing role-specific message.
- One reminder becomes eligible four full days after that email was sent.
- A successfully delivered reminder creates a fresh secure questionnaire link and invalidates the link in the original email.
- If reminder delivery fails, the original emailed link is restored and remains usable; the record is flagged for human review and is not retried automatically.
- If the mail provider accepts a reminder but the app cannot save a conclusive result, the applicant appears under **Reminder Delivery Review**. An administrator checks the provider log and either confirms delivery or keeps the applicant active. Keeping the applicant active restores the original emailed link and extends it for four days. That review sends no message.
- A completed, started, revoked, inactive, or advanced applicant is skipped.
- A second reminder is never sent.
- Four full days after a successful reminder, the questionnaire appears under **Questionnaires > Close Review Due**.
- No applicant is rejected or removed automatically. An administrator must review the applicant, enter a reason, and confirm **Close Review**.
- Closing the review changes the workflow to **Not Selected**, revokes the questionnaire link, records an audit event, and sends no message.

## Production Gate

1. Confirm the approved release commit and green CI.
2. Confirm no deployment is already running.
3. Create and verify a fresh encrypted production database backup.
4. Deploy the approved commit once. No schema migration is required for this feature.
5. Create the `nesp-questionnaire-reminders` Render cron service from `render.yaml`. The service starts with `APP_BASIC_AUTH=0`, runs with `NESP_SERVICE_ROLE=cron`, and must not rewrite the shared database mail settings.
6. Confirm the cron is disabled and can start without installation credentials or mail credentials. It must exit safely without sending.
7. Copy the already approved mail configuration into the cron service's protected environment settings without printing any secret values.
8. Set `NESP_QUESTIONNAIRE_REMINDER_SYSTEM_USER_ID` to a verified active OpenCATS administrator user ID.
9. Leave `NESP_QUESTIONNAIRE_REMINDERS_ENABLED` disabled until the protected test is ready.
10. Verify that SMS, calls, Zoom, Calendar, AI review, job ads, reminders from other systems, ranking, rejection, and automatic hiring decisions remain disabled.

## Protected Test

1. Use a protected candidate and an approved internal test email only.
2. Confirm the questionnaire is waiting, the initial email is recorded as sent, and no answer has started.
3. In a test environment, age the initial delivery timestamp past four days. Do not alter a real applicant timestamp.
4. Set `NESP_QUESTIONNAIRE_REMINDERS_ENABLED=1` on the cron service.
5. Run the cron once at its valid 9:00 AM America/New_York window.
6. Confirm exactly one reminder is received and that its fresh link opens the correct questionnaire.
7. Run the cron again and confirm no duplicate reminder is sent.
8. Submit the protected questionnaire and confirm no later reminder or closure action appears.

## Daily Use

- Craig does not need to send reminders manually.
- Open **Questionnaires** to see reminder status.
- Resolve **Reminder Delivery Review** items only after checking the approved mail provider's delivery log. The system never retries an uncertain delivery automatically.
- Review **Close Review Due** items individually.
- Select **Keep Active** when more time or personal follow-up is appropriate.
- Select **Confirm Close Review** only after a person reviews the record and enters a reason.

## Stop And Roll Back

Set `NESP_QUESTIONNAIRE_REMINDERS_ENABLED=0` on the cron service. This stops future reminder sends without deleting questionnaires, candidates, audit history, or prior delivery records.

If the release itself must be rolled back, redeploy the prior verified commit. No database rollback is needed because this feature adds no tables or columns.
