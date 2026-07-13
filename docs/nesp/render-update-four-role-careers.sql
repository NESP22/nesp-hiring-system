-- NESP protected deployment update for the four-role careers lineup.
--
-- Run from the repository root inside the Render shell with the mysql client:
--
-- mysql --protocol=TCP -h "$DB_HOST" -P "${DB_PORT:-3306}" \
--   -u "$DB_USER" -p"$DB_PASSWORD" "$DB_NAME" \
--   < docs/nesp/render-update-four-role-careers.sql
--
-- This delegates to the shared safe seed/update file. That file updates job
-- records in place, sets the NESP careers template, marks job 41004 inactive
-- and nonpublic, and does not delete candidates or candidate-job assignments.

SOURCE docs/nesp/local-review/seed-local-careers.sql;
