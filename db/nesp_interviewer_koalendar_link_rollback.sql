-- Roll back only the per-interviewer Koalendar booking-link field.
-- This does not alter assignments, questionnaires, interviews, or external services.

ALTER TABLE `nesp_interviewer_profile`
    DROP COLUMN IF EXISTS `koalendar_booking_url`;
