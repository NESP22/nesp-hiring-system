/* NESP board inbox scheduler rollback migration.
 *
 * This removes only scheduler-owned tables and its feature flag. It also removes
 * scheduler audit history, so production rollback requires a verified backup and
 * application rollback before this script is used.
 */

DROP TABLE IF EXISTS `nesp_board_intake_event`;
DROP TABLE IF EXISTS `nesp_board_intake_checkpoint`;
DROP TABLE IF EXISTS `nesp_board_intake_run`;

DELETE FROM `nesp_feature_flag`
WHERE `flag_key` IN (
    'NESP_BOARD_INTAKE_SCHEDULER_ENABLED',
    'NESP_BOARD_INTAKE_AUTO_IMPORT_ENABLED'
);
