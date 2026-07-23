-- Disable before rollback. This removes delivery tracking only; it does not revoke
-- public Koalendar pages or alter questionnaire answers, candidates, or assignments.

UPDATE nesp_feature_flag
SET is_enabled = 0,
    date_modified = NOW()
WHERE flag_key = 'NESP_KOALENDAR_BOOKING_EMAIL_ENABLED';

ALTER TABLE nesp_screening_questionnaire
    DROP INDEX IF EXISTS IDX_questionnaire_koalendar_booking_email_status,
    DROP COLUMN IF EXISTS koalendar_booking_email_send_count,
    DROP COLUMN IF EXISTS koalendar_booking_email_sent_at,
    DROP COLUMN IF EXISTS koalendar_booking_email_attempted_at,
    DROP COLUMN IF EXISTS koalendar_booking_email_status_key;
