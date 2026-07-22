# Board Applicant Intake Review

This is the admin-only intake hub for approved job-board applications. It
supports manual CSV review and an optional Missive-backed inbox check. It is
separate from the legacy generic CSV importer and does not expose a public
applicant route.

## Automatic inbox check

When `NESP_BOARD_INTAKE_SCHEDULER_ENABLED` is separately approved and its
Missive connection is complete:

1. Indeed, LinkedIn, MassHire, Craigslist, or Handshake sends its native
   application notification to the approved shared hiring inbox.
2. The configured approved Missive rule calls the signed NESP webhook. NESP
   verifies both the webhook HMAC and the configured rule ID, then stores a
   derived HMAC proof bound to the message, payload hash, and rule. It stores no
   message body. A visible From address or shared label is not proof.
3. Render processes the signed queue at approximately 8:00 AM and 6:00 PM
   Eastern. An administrator can also select **Process New Applications Now**.
4. Auto-import has its own `NESP_BOARD_INTAKE_AUTO_IMPORT_ENABLED` approval and
   is off by default. While it is off, every notification stops in **Needs
   attention** for manual review.
5. When auto-import is separately enabled, only a complete notification with
   the persisted signed approved-rule proof enters OpenCATS and **Needs Craig**
   exactly once. Duplicate external identities do not create a second
   candidate.
6. Incomplete, ambiguous, shared-label-recovered, or unauthenticated
   notifications stop in **Needs attention**. They never auto-import or contact
   an applicant.
7. If the separately approved applicant-email feature is already enabled, an
   eligible successful import may send the one role-specific questionnaire
   email. The inbox scheduler does not enable mail itself.

The reconciliation checkpoint is a dedicated durable provider high-water mark,
initialized to the beginning of available history. Conversation and message
pages continue until the provider's documented end condition or the prior
checkpoint is reached. The checkpoint advances only after the complete scan has
been queued. A request, cursor, queue, or checkpoint-write failure leaves it
unchanged and makes the run report an error instead of silently skipping mail.

This integration checks the approved hiring inbox; it does not scrape job
boards or sign into board accounts.

## Safe flow

1. Choose the board, job order, and matching `NESP Ad: ...` source label.
2. Upload a narrow CSV containing required `external_id`, `first_name`, and
   `last_name`. Add `email` when the board provides it; `phone` is optional.
3. Review validation and duplicate results.
4. Record the completed preview in the server-backed gate.
5. Explicitly approve only valid, keyed, nonduplicate rows.
6. Confirm the import action.
7. The transaction creates each candidate, attaches it once to the selected
   job order, and puts it in `Needs Craig`. When a reviewed row includes
   contact details, the matching role-specific questionnaire link is prepared
   for Craig's review. Without contact details, the applicant remains in Needs
   Craig with a contact-details action and no questionnaire is prepared. A
   manual import sends nothing unless the separately approved applicant-email
   feature is enabled. SMS, calls, calendar invites, Zoom, ranking, rejection,
   and hiring decisions remain outside this workflow.
8. After import, an administrator may choose one local resume file for a
   specific imported row. The server reconfirms that row's external identity,
   candidate record, and selected job-order pipeline entry before passing the
   upload to OpenCATS `AttachmentCreator` as a resume.

The job-order allowlist contains Customer Service `41001`, Staff Photographer
`41002`, Freelance/Contract Youth Sports Photographer `41003`, and Weekend
Table Greeter / Field Assistant `41005`.

## Duplicate and idempotency rules

- Email matches are blocked against existing candidate email fields.
- Exact first/last-name matches are blocked against existing candidates.
- Duplicate email or name rows within the same review batch are blocked.
- Duplicate external IDs within the same review batch are blocked.
- A supplied board/external ID is claimed once per platform.
- The same candidate/job-order pipeline entry cannot be added twice.
- If candidate data changes after review, import stops and requires a new
  review.
- Rows without an external ID cannot be imported; this prevents an unkeyed
  import from creating duplicates.

## Preview, approval, and retention gates

- The server records who completed the preview before it permits row approval.
- The server records who approved rows before it permits import.
- Imports are transactional and protected by a unique platform/external-ID
  identity key.
- Review batches expire after 30 days and are purged automatically while they
  are still pending.
- After a successful import, staged names, email, and phone values are
  redacted. The candidate record and non-PII idempotency identity remain.

## Local resume upload

- Resume attachment is available only after the row and batch are imported.
- The identity claim, candidate ID, and candidate-job mapping must still agree.
- Only a PHP-confirmed local multipart upload is accepted.
- Files are limited to 10 MB and the PDF, DOC, DOCX, RTF, and ODT extensions.
- The upload creates a candidate resume attachment; it does not update the
  candidate's name, email addresses, phone numbers, or other contact fields.
- Duplicate attachments and incomplete mappings stop without a partial write.

## Deliberate exclusions

- Resume and attachment URLs are rejected; they are not stored in notes or
  fetched by the intake workflow.
- Raw CSV rows are not retained by the service.
- No board scraping, board-account sign-in, or automatic hiring decision is
  included.
- The feature does not alter questionnaire or interviewer screens.

The route is `?m=boardintake` and requires administrator access plus CSRF
validation for every write action.

## Production configuration and stop gate

The web and cron services require Render-only values for the Missive API token,
webhook secret, approved rule ID, approved shared-label ID, and a valid
OpenCATS administrator system-user ID. The cron service is a separate paid
Render service and must receive the already-approved mail settings too if
questionnaire delivery is expected from cron imports.

Keep both scheduler and auto-import feature flags off until the migration,
Missive rule, webhook, service secrets, encrypted backup, and one protected
signed-notification test are verified together. Scheduler-only enablement is the
manual-review mode; auto-import requires its own later approval.
