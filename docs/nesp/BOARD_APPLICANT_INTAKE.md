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
7. The transaction creates each candidate and attaches it once to the selected
   job order. It does not send email, SMS, calls, calendar invites, or other
   external messages.

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

## Deliberate exclusions

- Resume and attachment URLs are rejected; they are not stored in notes.
- Raw CSV rows are not retained by the service.
- No automatic board sync or applicant contact is included.
- The feature does not alter questionnaire or interviewer screens.

The route is `?m=boardintake` and requires administrator access plus CSRF
validation for every write action.
