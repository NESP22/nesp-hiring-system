/* NESP real interviewer profile staging.
 *
 * Run only after db/nesp_interviewer_settings_additive.sql.
 *
 * This stages inactive interviewer profiles and approved job-role permissions.
 * It does not create OpenCATS user accounts, activate access, send email,
 * create candidate grants, schedule interviews, create Zoom meetings, or place
 * Vapi calls.
 */

UPDATE `nesp_interviewer_profile`
SET display_name = 'Suthir',
    role_key = 'photographer_interviewer',
    is_active = 0,
    account_state_key = 'ready_for_account_creation',
    timezone = 'America/New_York',
    availability_status_key = 'open',
    max_interviews_per_day = 3,
    max_interviews_per_week = 12,
    default_interview_minutes = 30,
    buffer_minutes = 15,
    earliest_time = '09:00:00',
    latest_time = '17:00:00',
    may_recommend = 1,
    private_admin_notes = 'Approved for photographer interviews only. No Customer Service or Field Assistant access unless Craig changes permissions.',
    email_warning = '',
    date_modified = NOW()
WHERE email = 'suthir@nesportsphoto.com';

INSERT INTO `nesp_interviewer_profile`
    (`user_id`, `display_name`, `email`, `role_key`, `is_active`, `can_view_resume`, `can_add_notes`, `can_submit_scorecard`, `account_state_key`, `timezone`, `availability_status_key`, `max_interviews_per_day`, `max_interviews_per_week`, `default_interview_minutes`, `buffer_minutes`, `earliest_time`, `latest_time`, `craig_must_attend`, `may_recommend`, `private_admin_notes`, `email_warning`, `date_created`, `date_modified`)
SELECT NULL, 'Suthir', 'suthir@nesportsphoto.com', 'photographer_interviewer', 0, 1, 1, 1, 'ready_for_account_creation', 'America/New_York', 'open', 3, 12, 30, 15, '09:00:00', '17:00:00', 0, 1, 'Approved for photographer interviews only. No Customer Service or Field Assistant access unless Craig changes permissions.', '', NOW(), NOW()
FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM `nesp_interviewer_profile` WHERE email = 'suthir@nesportsphoto.com');

UPDATE `nesp_interviewer_profile`
SET email = 'brandon@nesportsphoto.com',
    email_warning = 'Please confirm that brandon@nesportsphoto.com is the correct email address.',
    date_modified = NOW()
WHERE display_name = 'Brandon'
  AND email = 'brandon@sportsphoto.com'
  AND is_active = 0;

UPDATE `nesp_interviewer_profile`
SET display_name = 'Brandon',
    role_key = 'field_support_interviewer',
    is_active = 0,
    account_state_key = 'email_needs_confirmation',
    timezone = 'America/New_York',
    availability_status_key = 'open',
    max_interviews_per_day = 3,
    max_interviews_per_week = 12,
    default_interview_minutes = 20,
    buffer_minutes = 15,
    earliest_time = '09:00:00',
    latest_time = '17:00:00',
    may_recommend = 0,
    private_admin_notes = 'Approved for Field Assistant interviews after Craig confirms email address and activation.',
    email_warning = 'Please confirm that brandon@nesportsphoto.com is the correct email address.',
    date_modified = NOW()
WHERE email = 'brandon@nesportsphoto.com';

INSERT INTO `nesp_interviewer_profile`
    (`user_id`, `display_name`, `email`, `role_key`, `is_active`, `can_view_resume`, `can_add_notes`, `can_submit_scorecard`, `account_state_key`, `timezone`, `availability_status_key`, `max_interviews_per_day`, `max_interviews_per_week`, `default_interview_minutes`, `buffer_minutes`, `earliest_time`, `latest_time`, `craig_must_attend`, `may_recommend`, `private_admin_notes`, `email_warning`, `date_created`, `date_modified`)
SELECT NULL, 'Brandon', 'brandon@nesportsphoto.com', 'field_support_interviewer', 0, 1, 1, 1, 'email_needs_confirmation', 'America/New_York', 'open', 3, 12, 20, 15, '09:00:00', '17:00:00', 0, 0, 'Approved for Field Assistant interviews after Craig confirms email address and activation.', 'Please confirm that brandon@nesportsphoto.com is the correct email address.', NOW(), NOW()
FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM `nesp_interviewer_profile` WHERE email = 'brandon@nesportsphoto.com');

UPDATE `nesp_interviewer_profile`
SET display_name = 'Nate',
    role_key = 'field_interviewer',
    is_active = 0,
    account_state_key = 'ready_for_account_creation',
    timezone = 'America/New_York',
    availability_status_key = 'open',
    max_interviews_per_day = 3,
    max_interviews_per_week = 12,
    default_interview_minutes = 25,
    buffer_minutes = 15,
    earliest_time = '09:00:00',
    latest_time = '17:00:00',
    may_recommend = 1,
    private_admin_notes = 'Approved for Staff Photographer, Freelance Photographer, and Field Assistant only. Customer Service is explicitly forbidden.',
    email_warning = '',
    date_modified = NOW()
WHERE email = 'nate@nesportsphoto.com';

INSERT INTO `nesp_interviewer_profile`
    (`user_id`, `display_name`, `email`, `role_key`, `is_active`, `can_view_resume`, `can_add_notes`, `can_submit_scorecard`, `account_state_key`, `timezone`, `availability_status_key`, `max_interviews_per_day`, `max_interviews_per_week`, `default_interview_minutes`, `buffer_minutes`, `earliest_time`, `latest_time`, `craig_must_attend`, `may_recommend`, `private_admin_notes`, `email_warning`, `date_created`, `date_modified`)
SELECT NULL, 'Nate', 'nate@nesportsphoto.com', 'field_interviewer', 0, 1, 1, 1, 'ready_for_account_creation', 'America/New_York', 'open', 3, 12, 25, 15, '09:00:00', '17:00:00', 0, 1, 'Approved for Staff Photographer, Freelance Photographer, and Field Assistant only. Customer Service is explicitly forbidden.', '', NOW(), NOW()
FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM `nesp_interviewer_profile` WHERE email = 'nate@nesportsphoto.com');

INSERT INTO `nesp_interviewer_job_role`
    (`interviewer_profile_id`, `joborder_id`, `role_key`, `is_active`, `created_by_user_id`, `date_created`, `date_modified`)
SELECT ip.interviewer_profile_id, role_map.joborder_id, role_map.role_key, 1, 1, NOW(), NOW()
FROM `nesp_interviewer_profile` ip
INNER JOIN (
    SELECT 'suthir@nesportsphoto.com' AS email, 41002 AS joborder_id, 'staff_photographer' AS role_key
    UNION ALL SELECT 'suthir@nesportsphoto.com', 41003, 'freelance_photographer'
    UNION ALL SELECT 'brandon@nesportsphoto.com', 41005, 'field_assistant'
    UNION ALL SELECT 'nate@nesportsphoto.com', 41002, 'staff_photographer'
    UNION ALL SELECT 'nate@nesportsphoto.com', 41003, 'freelance_photographer'
    UNION ALL SELECT 'nate@nesportsphoto.com', 41005, 'field_assistant'
) role_map
    ON role_map.email = ip.email
ON DUPLICATE KEY UPDATE
    role_key = VALUES(role_key),
    is_active = 1,
    date_modified = NOW();
