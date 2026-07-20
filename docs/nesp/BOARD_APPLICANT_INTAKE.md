# Board Applicant Intake Review

This is an admin-only staging workflow for manually reviewed applicant CSVs
from Indeed or another approved job board. It is separate from the legacy
generic CSV importer and does not expose a public route.

## Safe flow

1. Choose the board, job order, and matching `NESP Ad: ...` source label.
2. Upload a narrow CSV containing only `external_id`, `first_name`,
   `last_name`, `email`, and optional `phone`.
3. Review validation and duplicate results.
4. Explicitly approve only valid, nonduplicate rows.
5. Confirm the import action.
6. The transaction creates each candidate and attaches it once to the selected
   job order. It does not send email, SMS, calls, calendar invites, or other
   external messages.

The initial job-order allowlist contains Customer Service job `41001`.

## Duplicate and idempotency rules

- Email matches are blocked against existing candidate email fields.
- Exact first/last-name matches are blocked against existing candidates.
- Duplicate email or name rows within the same review batch are blocked.
- A supplied board/external ID is claimed once per platform.
- The same candidate/job-order pipeline entry cannot be added twice.
- If candidate data changes after review, import stops and requires a new
  review.

## Deliberate exclusions

- Resume and attachment URLs are rejected; they are not stored in notes.
- Raw CSV rows are not retained by the service.
- No automatic board sync or applicant contact is included.
- The feature does not alter questionnaire or interviewer screens.

The route is `?m=boardintake` and requires administrator access plus CSRF
validation for every write action.
