/* NESP staffing import review additive migration.
 *
 * Safe defaults:
 * - Adds review metadata only.
 * - Does not import staffing rows, touch Google Sheets, contact applicants,
 *   change feature flags, or finalize any pending batch.
 */

ALTER TABLE `nesp_staffing_import_batch`
    ADD COLUMN IF NOT EXISTS `submitted_for_review_at` DATETIME,
    ADD COLUMN IF NOT EXISTS `reviewed_by_user_id` INT(11),
    ADD COLUMN IF NOT EXISTS `finalized_at` DATETIME,
    ADD INDEX IF NOT EXISTS `IDX_finalized_at` (`finalized_at`);

ALTER TABLE `nesp_staffing_import_row`
    ADD COLUMN IF NOT EXISTS `review_note` TEXT COLLATE utf8mb4_unicode_ci,
    ADD COLUMN IF NOT EXISTS `reviewed_by_user_id` INT(11),
    ADD COLUMN IF NOT EXISTS `reviewed_at` DATETIME,
    ADD COLUMN IF NOT EXISTS `date_modified` DATETIME NOT NULL DEFAULT '1000-01-01 00:00:00',
    ADD INDEX IF NOT EXISTS `IDX_reviewed_at` (`reviewed_at`);
