# Staffing Forecast Calculation Methods

## Implemented Formulas

- Historical events per season: distinct normalized event keys grouped by year.
- Events per week: normalized event rows grouped by Monday week start.
- Events by weekday: normalized event rows grouped by weekday.
- Events by state: normalized event rows grouped by state, or `Unknown`.
- Events by sport: normalized event rows grouped by sport, or `Unknown`.
- Unique staff per season: unique staff names grouped by season year.
- Staff by role: sum of normalized staff counts by role.
- Peak day staffing: maximum staff count on one normalized event date.
- Peak concurrent staff: currently same as peak day staffing until verified event times are complete.
- Total staff-hours: sum of normalized row staff-hours.
- Average staff per event: total staff assignments divided by distinct event count.
- Recommended pool: `ceil(peak_day_staffing * (1 + buffer_percent / 100))`.
- Recommended backup: `ceil(recommended_pool * buffer_percent / 100)`.
- Hiring gap: `max(0, recommended_pool + recommended_backup - active_staff - expected_returning_staff - confirmed_available_staff)`.

## Confidence

- High: at least 3 usable seasons and no open import issues.
- Medium: at least 2 usable seasons.
- Low: fewer than 2 usable seasons or insufficient normalized data.

## Deferred

The current implementation does not claim guaranteed forecasts. More precise concurrent staffing should be recalculated after verified event start/end times are imported from real schedules.
