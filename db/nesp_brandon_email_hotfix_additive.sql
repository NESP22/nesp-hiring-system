/* NESP Brandon interviewer email hotfix.
 *
 * Safe defaults:
 * - Updates only Brandon's inactive interviewer profile.
 * - Does not activate interviewer access.
 * - Does not create OpenCATS users.
 * - Does not send email, SMS, Zoom invitations, calendar invites, or Vapi calls.
 * - Does not modify applicants or other interviewer profiles.
 */

UPDATE `nesp_interviewer_profile`
SET email = 'brandon@nesportsphoto.com',
    email_warning = 'Please confirm that brandon@nesportsphoto.com is the correct email address.',
    date_modified = NOW()
WHERE display_name = 'Brandon'
  AND email = 'brandon@sportsphoto.com'
  AND is_active = 0;

