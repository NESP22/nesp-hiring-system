# Board Applicant Intake Review

This is an admin-only staging workflow for manually reviewed applicant CSVs
from Indeed or another approved job board. It is separate from the legacy
generic CSV importer and does not expose a public route.

## Safe flow

1. Choose the board, job order, and matching `NESP Ad: ...` source label.
2. Upload a narrow CSV containing required `external_id`, `first_name`,
   `last_name`, and `email`, plus optional `phone`.
3. Review validation and duplicate results.
4. Record the completed preview in the server-backed gate.
5. Explicitly approve only valid, keyed, nonduplicate rows.
6. Confirm the import action.
7. The transaction creates each candidate, attaches it once to the selected
   job order, and puts it in `Needs Craig`. When a reviewed row includes
   contact details, the matching role-specific questionnaire link is prepared
   for Craig's review. It does not send email, SMS, calls, calendar invites,
   questionnaires, or other external messages.
8. After import, an administrator may choose one local resume file for a
   specific imported row. The server reconfirms that row's external identity,
   candidate record, and selected job-order pipeline entry before passing the
   upload to OpenCATS `AttachmentCreator` as a resume.

The initial job-order allowlist contains Customer Service job `41001`.

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
- No automatic board sync, scraping, or applicant contact is included.
- The feature does not alter questionnaire or interviewer screens.

The route is `?m=boardintake` and requires administrator access plus CSRF
validation for every write action.
