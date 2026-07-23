-- Automatic, eligibility-gated Koalendar interview-invite email delivery.
-- New installs remain disabled until an administrator separately enables the feature flag.

INSERT INTO nesp_feature_flag
    (flag_key, display_name, description, is_enabled, requires_admin_approval, date_created, date_modified)
VALUES
    ('NESP_KOALENDAR_BOOKING_EMAIL_ENABLED', 'Koalendar Interview Invite Email', 'Automatically emails the public Koalendar booking page for the assigned interviewer after questionnaire review is completed. Disabled by default; no calendar event or automatic scheduling is created.', 0, 1, NOW(), NOW())
ON DUPLICATE KEY UPDATE
    display_name = VALUES(display_name),
    description = VALUES(description),
    is_enabled = 0,
    requires_admin_approval = 1,
    date_modified = NOW();

ALTER TABLE nesp_screening_questionnaire
    ADD COLUMN IF NOT EXISTS koalendar_booking_email_status_key VARCHAR(32) NOT NULL DEFAULT 'not_attempted' AFTER auto_email_sent_at,
    ADD COLUMN IF NOT EXISTS koalendar_booking_email_attempted_at DATETIME NULL AFTER koalendar_booking_email_status_key,
    ADD COLUMN IF NOT EXISTS koalendar_booking_email_sent_at DATETIME NULL AFTER koalendar_booking_email_attempted_at,
    ADD COLUMN IF NOT EXISTS koalendar_booking_email_send_count INT(11) NOT NULL DEFAULT '0' AFTER koalendar_booking_email_sent_at,
    ADD KEY IF NOT EXISTS IDX_questionnaire_koalendar_booking_email_status (koalendar_booking_email_status_key);
