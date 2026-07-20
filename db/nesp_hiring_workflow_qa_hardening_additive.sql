/* NESP hiring workflow QA hardening migration.
 *
 * Apply after the questionnaire, question-set, and interviewer-settings
 * additive migrations. This migration does not enable feature flags, send
 * messages, contact applicants, or connect external services.
 */

ALTER TABLE `nesp_screening_questionnaire`
  ADD COLUMN IF NOT EXISTS `active_candidate_job_key` CHAR(64) COLLATE utf8mb4_unicode_ci AFTER `joborder_id`;

/* Preserve the newest active link for a pair; older duplicate active links
 * become revoked history before the unique lock is introduced. */
UPDATE `nesp_screening_questionnaire` q
INNER JOIN (
    SELECT candidate_id, joborder_id, MAX(screening_questionnaire_id) AS retained_questionnaire_id
    FROM `nesp_screening_questionnaire`
    WHERE status_key IN ('link_ready', 'waiting', 'in_progress', 'human_follow_up_requested')
    GROUP BY candidate_id, joborder_id
    HAVING COUNT(*) > 1
) duplicate_pair
    ON duplicate_pair.candidate_id = q.candidate_id
   AND duplicate_pair.joborder_id = q.joborder_id
SET q.status_key = 'revoked',
    q.token_revoked_at = COALESCE(q.token_revoked_at, UTC_TIMESTAMP()),
    q.active_candidate_job_key = NULL,
    q.date_modified = NOW()
WHERE q.screening_questionnaire_id <> duplicate_pair.retained_questionnaire_id
  AND q.status_key IN ('link_ready', 'waiting', 'in_progress', 'human_follow_up_requested');

UPDATE `nesp_screening_questionnaire`
SET active_candidate_job_key = SHA2(CONCAT('nesp-questionnaire-active:', candidate_id, ':', joborder_id), 256)
WHERE status_key IN ('link_ready', 'waiting', 'in_progress', 'human_follow_up_requested')
  AND active_candidate_job_key IS NULL;

ALTER TABLE `nesp_screening_questionnaire`
  ADD UNIQUE KEY IF NOT EXISTS `IDX_questionnaire_active_candidate_job` (`active_candidate_job_key`);

/* Tracks the managed Field Staff First and Photographer releases without
 * changing snapshots already issued to applicants. The application publishes
 * a new immutable current version on first safe initialization after this
 * migration when the deployed content differs from the approved release. */
CREATE TABLE IF NOT EXISTS `nesp_question_set_builtin_release` (
  `set_key` VARCHAR(96) COLLATE utf8mb4_unicode_ci NOT NULL,
  `release_hash` CHAR(64) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `question_set_version_id` INT(11) NOT NULL,
  `published_by_user_id` INT(11),
  `date_created` DATETIME NOT NULL DEFAULT '1000-01-01 00:00:00',
  `date_modified` DATETIME NOT NULL DEFAULT '1000-01-01 00:00:00',
  PRIMARY KEY (`set_key`),
  KEY `IDX_nesp_question_set_builtin_release_version` (`question_set_version_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

/* Customer Service remains Craig/manual-only even if an older profile was
 * granted that role. Nate is returned to profile-only until roles are granted
 * through the administrator workflow. */
UPDATE `nesp_interviewer_job_role`
SET is_active = 0,
    date_modified = NOW()
WHERE joborder_id = 41001;

UPDATE `nesp_interviewer_profile`
SET is_active = 0,
    account_state_key = 'profile_created',
    private_admin_notes = 'Profile only. Craig must explicitly assign approved job roles before this interviewer can receive candidates.',
    date_modified = NOW()
WHERE email = 'nate@nesportsphoto.com';

UPDATE `nesp_interviewer_job_role` ijr
INNER JOIN `nesp_interviewer_profile` ip
    ON ip.interviewer_profile_id = ijr.interviewer_profile_id
SET ijr.is_active = 0,
    ijr.date_modified = NOW()
WHERE ip.email = 'nate@nesportsphoto.com';
