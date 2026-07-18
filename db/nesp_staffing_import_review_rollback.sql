/* NESP staffing import review rollback.
 *
 * Run only after verifying no pending review batches need this metadata.
 * This removes review metadata columns only; it does not delete staffing rows
 * or broader Phase 2 forecast tables.
 */

ALTER TABLE `nesp_staffing_import_row`
    DROP INDEX IF EXISTS `IDX_reviewed_at`,
    DROP COLUMN IF EXISTS `date_modified`,
    DROP COLUMN IF EXISTS `reviewed_at`,
    DROP COLUMN IF EXISTS `reviewed_by_user_id`,
    DROP COLUMN IF EXISTS `review_note`;

ALTER TABLE `nesp_staffing_import_batch`
    DROP INDEX IF EXISTS `IDX_finalized_at`,
    DROP COLUMN IF EXISTS `finalized_at`,
    DROP COLUMN IF EXISTS `reviewed_by_user_id`,
    DROP COLUMN IF EXISTS `submitted_for_review_at`;
