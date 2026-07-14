# Staffing Forecast Import Guide

## Implemented

- Sanitized CSV fixtures cover dates in rows, dates in columns, staff names in one cell, multiple staff roles, blank separator rows, heading rows, malformed dates, and duplicate rows.
- CSV normalization supports row-style and column-style schedule exports.
- XLSX parsing is attempted only when PHP `ZipArchive` is available; otherwise the importer returns a review issue.
- Row lineage uses source checksum plus per-row hash to support idempotency.
- Import undo marks a batch undone and removes it from forecast calculations.

## Manual CSV Fallback

Export the selected schedule tab as CSV, then use the same column concepts:

- Date or date columns
- Start time
- End time
- State
- Sport
- Event or league/school name
- Role
- Staff names

Do not invent missing values. Missing or malformed values must stay unresolved and be reviewed.

## XLSX Fallback

If XLSX parsing is unavailable, export the sheet/tab to CSV and import the CSV. Do not add a new dependency during production deployment without a dependency/security review.

## Fixture-Only

Files under `src/OpenCATS/Tests/Fixtures/nesp/` are synthetic and must not be treated as real Craig schedules.
