# Staffing Forecast Architecture

## Implemented

- `NESP_STAFFING_FORECAST_ENABLED` defaults off.
- `NESP_STAFFING_DRIVE_IMPORT_ENABLED` defaults off.
- Forecast source status is stored in `nesp_staffing_import_batch`.
- Normalized row lineage is stored in `nesp_staffing_import_row`.
- Import issues are stored in `nesp_staffing_import_issue`.
- Draft internal recommendations are stored in `nesp_staffing_recommendation`.
- One-import undo marks an import batch as `undone` without deleting native OpenCATS data.

## Source Flow

1. Discover source files through Google Drive only after Craig authorizes a later integration task.
2. Alternatively upload CSV/XLSX in a controlled admin workflow.
3. Preview sheets/tabs and columns.
4. Map columns to normalized fields.
5. Preview normalized rows and warnings.
6. Confirm import.
7. Review issues.
8. Undo an import batch if needed.

## Required Future Drive Access

No credential values belong in the repo. A later controlled task will need read-only Google Drive access to the historical schedule folder and enough metadata permission to list file names, MIME types, modified times, and download selected spreadsheet exports.

Expected scopes:

- `https://www.googleapis.com/auth/drive.metadata.readonly`
- `https://www.googleapis.com/auth/drive.readonly`

## Deferred

- Live Google Drive discovery and file download are intentionally disabled.
- Browser upload endpoints are not production-enabled by default.
- Real historical schedule import is not claimed by this PR.
