/* Rollback for NESP Brandon interviewer email hotfix.
 *
 * Run only after a verified production backup and only if the corrected
 * Brandon email must be reverted.
 */

UPDATE `nesp_interviewer_profile`
SET email = 'brandon@sportsphoto.com',
    email_warning = 'Please confirm that brandon@sportsphoto.com is the correct email address.',
    date_modified = NOW()
WHERE display_name = 'Brandon'
  AND email = 'brandon@nesportsphoto.com'
  AND is_active = 0;

