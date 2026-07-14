# Staffing Forecast User Guide

## What The Screen Shows

- Source Status: whether historical data has been imported.
- Season Summary: events and unique staff by season.
- Week-by-Week: normalized events by week.
- Peak Weekends: busiest staffing periods.
- Staffing by State: normalized event rows by state.
- Staffing by Role: staff assignments by role.
- Historical Comparison: legacy summary rows for review context.
- Hiring Gap: recommended pool, backup, and hiring target.

## Important Safety Notes

Forecast numbers are planning guidance only. They do not publish jobs, send messages, change applicant stages, or edit OpenCATS job records.

The “Create Hiring Recommendation” action creates only a draft internal recommendation for review.

When no verified staffing history exists, the page shows `No historical schedules imported yet.` Metrics should read as zero, no data, or not enough history. The recommendation action is disabled while no rows have been imported.

Production state after the 2026-07-14 shell enablement:

- `NESP_STAFFING_FORECAST_ENABLED = 1`
- `NESP_STAFFING_DRIVE_IMPORT_ENABLED = 0`
- Staffing import batches: `0`
- Staffing import rows: `0`
- Staffing schedule history rows: `0`
- Staffing forecasts: `0`
- Staffing recommendations: `0`
- Audit event `3` records the flag change from `0` to `1`.

## Requires Production Configuration

Real Drive import requires a separate controlled task with read-only Google Drive credentials and Craig approval.
