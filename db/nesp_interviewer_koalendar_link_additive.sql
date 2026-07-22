-- Add only the per-interviewer Koalendar public booking-page field.

ALTER TABLE `nesp_interviewer_profile`
    ADD COLUMN IF NOT EXISTS `koalendar_booking_url` VARCHAR(1000) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '' AFTER `default_zoom_join_url`;
