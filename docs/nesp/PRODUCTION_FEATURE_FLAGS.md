# Production Feature Flags

## Required Defaults

All integration and workflow flags are default-off in migrations and fresh schema:

- `NESP_WORKFLOW_ENABLED = 0`
- `NESP_INTERVIEWER_POOL_ENABLED = 0`
- `NESP_PRESCREEN_ENABLED = 0`
- `NESP_VAPI_ENABLED = 0`
- `NESP_ZOOM_ENABLED = 0`
- `NESP_AI_REVIEW_ENABLED = 0`
- `NESP_STAFFING_FORECAST_ENABLED = 0`
- `NESP_STAFFING_DRIVE_IMPORT_ENABLED = 0`

## Vapi Rule

`NESP_VAPI_ENABLED` gates outbound provider calls. It must stay `0` until:

- Vapi configuration status is healthy
- webhook mock/security tests pass
- production backup is created and verified
- deployment health checks pass
- Craig explicitly confirms the controlled test phone

## Immediate Disable

```sql
UPDATE nesp_feature_flag
SET is_enabled = 0,
    date_modified = NOW()
WHERE flag_key = 'NESP_VAPI_ENABLED';
```

Disabling the flag does not delete audit history or webhook event records.
