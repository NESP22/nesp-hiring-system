/* NESP interviewer availability conflict checks rollback.
 *
 * Run only after a verified backup. This removes the availability-conflict
 * feature gate and min-notice field without touching interview records,
 * interviewer profiles, availability blocks, blackouts, or audit history.
 */

DELETE FROM `nesp_feature_flag`
WHERE `flag_key` = 'NESP_INTERVIEWER_AVAILABILITY_ENABLED';

ALTER TABLE `nesp_interviewer_profile`
    DROP COLUMN IF EXISTS `min_notice_minutes`;
