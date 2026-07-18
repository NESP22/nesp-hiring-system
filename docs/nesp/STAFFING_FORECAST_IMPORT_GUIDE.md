# Staffing Forecast Import Guide

## Implemented

- Sanitized CSV fixtures cover dates in rows, dates in columns, staff names in one cell, multiple staff roles, blank separator rows, heading rows, malformed dates, and duplicate rows.
- CSV normalization supports row-style and column-style schedule exports.
- XLSX parsing is attempted only when PHP `ZipArchive` is available; otherwise the importer returns a review issue.
- Row lineage uses source checksum plus per-row hash to support idempotency.
- Manual CSV import now creates a review batch first. Rows do not affect forecast math until an admin approves or rejects each row and finalizes the batch.
- Review decisions store reviewer, timestamp, status, and note metadata on `nesp_staffing_import_row`.
- Import undo marks a batch undone and removes it from forecast calculations.
- Preliminary Fall 2026 hiring-gap guidance uses only finalized, approved September-November historical rows.

## Manual CSV Fallback

Export the selected schedule tab as CSV, then paste the CSV into `Staffing Forecast` -> `Controlled Import Review`. Use the same column concepts:

- Date or date columns
- Start time
- End time
- State
- Sport
- Event or league/school name
- Role
- Staff names

Do not invent missing values. Missing or malformed values must stay unresolved and be approved only after human verification. Reject rows that should remain out of the forecast.

## Approval Gate

1. Create a review batch from pasted CSV.
2. Open the pending batch.
3. Approve verified rows.
4. Reject duplicate, malformed, or out-of-scope rows.
5. Finalize only after every row has a decision.

Finalizing stores approved rows for forecast calculations. It does not edit Google Sheets, contact applicants, create calendar events, create Zoom meetings, or change feature flags.

## XLSX Fallback

If XLSX parsing is unavailable, export the sheet/tab to CSV and import the CSV. Do not add a new dependency during production deployment without a dependency/security review.

## Fixture-Only

Files under `src/OpenCATS/Tests/Fixtures/nesp/` are synthetic and must not be treated as real Craig schedules.
