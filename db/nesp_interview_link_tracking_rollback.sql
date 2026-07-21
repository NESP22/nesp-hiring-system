/* NESP interview participant-link tracking rollback.
 *
 * Run only after preserving audit records and verifying no active interview
 * still depends on a tracked participant link.
 */

ALTER TABLE `nesp_interview`
    DROP INDEX IF EXISTS `IDX_nesp_interview_participant_token`,
    DROP COLUMN IF EXISTS `participant_link_revoked_at`,
    DROP COLUMN IF EXISTS `participant_link_open_count`,
    DROP COLUMN IF EXISTS `participant_link_last_opened_at`,
    DROP COLUMN IF EXISTS `participant_link_opened_at`,
    DROP COLUMN IF EXISTS `participant_link_token_hash`;
