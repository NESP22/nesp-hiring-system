/* NESP Google Calendar free/busy rollback.
 *
 * Run only after restoring or independently verifying a production database
 * backup. This removes only the disabled free/busy scaffolding and encrypted
 * token records; it does not touch calendar events.
 */

DROP TABLE IF EXISTS `nesp_google_calendar_connection`;

DELETE FROM `nesp_integration_status`
WHERE `integration_key` = 'google_calendar_freebusy';

DELETE FROM `nesp_feature_flag`
WHERE `flag_key` = 'NESP_GOOGLE_CALENDAR_FREEBUSY_ENABLED';
