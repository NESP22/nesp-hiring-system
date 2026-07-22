<?php

use PHPUnit\Framework\TestCase;

include_once(LEGACY_ROOT . '/lib/NESPBoardIntakeScheduler.php');

class NESPBoardIntakeSchedulerTest extends TestCase
{
    public function testMorningAndEveningSlotsUseNewYorkLocalTimeAcrossDST()
    {
        $winterMorning = new DateTimeImmutable('2026-01-15 13:00:00', new DateTimeZone('UTC'));
        $summerMorning = new DateTimeImmutable('2026-07-15 12:00:00', new DateTimeZone('UTC'));
        $winterEvening = new DateTimeImmutable('2026-01-15 23:00:00', new DateTimeZone('UTC'));
        $summerEvening = new DateTimeImmutable('2026-07-15 22:00:00', new DateTimeZone('UTC'));

        $this->assertSame('2026-01-15-morning', NESPBoardIntakeScheduler::slotForTime($winterMorning));
        $this->assertSame('2026-07-15-morning', NESPBoardIntakeScheduler::slotForTime($summerMorning));
        $this->assertSame('2026-01-15-evening', NESPBoardIntakeScheduler::slotForTime($winterEvening));
        $this->assertSame('2026-07-15-evening', NESPBoardIntakeScheduler::slotForTime($summerEvening));
    }

    public function testCandidateUtcHoursOutsideTheMatchingLocalHourAreSkipped()
    {
        $winterWrongOffset = new DateTimeImmutable('2026-01-15 12:00:00', new DateTimeZone('UTC'));
        $summerWrongOffset = new DateTimeImmutable('2026-07-15 13:00:00', new DateTimeZone('UTC'));

        $this->assertSame('', NESPBoardIntakeScheduler::slotForTime($winterWrongOffset));
        $this->assertSame('', NESPBoardIntakeScheduler::slotForTime($summerWrongOffset));
    }

    public function testNextCheckMovesFromMorningToEveningToNextDay()
    {
        $zone = new DateTimeZone('America/New_York');

        $this->assertSame(
            '2026-07-22 08:00',
            NESPBoardIntakeScheduler::nextCheckAt(new DateTimeImmutable('2026-07-22 07:30', $zone))->format('Y-m-d H:i')
        );
        $this->assertSame(
            '2026-07-22 18:00',
            NESPBoardIntakeScheduler::nextCheckAt(new DateTimeImmutable('2026-07-22 08:30', $zone))->format('Y-m-d H:i')
        );
        $this->assertSame(
            '2026-07-23 08:00',
            NESPBoardIntakeScheduler::nextCheckAt(new DateTimeImmutable('2026-07-22 18:30', $zone))->format('Y-m-d H:i')
        );
    }

    public function testImplementationKeepsExactOnceClaimsAndPagedQueueDraining()
    {
        $source = file_get_contents(LEGACY_ROOT . '/lib/NESPBoardIntakeScheduler.php');

        $this->assertStringContainsString('GET_LOCK', $source);
        $this->assertStringContainsString('INSERT INTO nesp_board_intake_run', $source);
        $this->assertStringContainsString('INSERT IGNORE INTO nesp_board_intake_event', $source);
        $this->assertStringContainsString('EVENT_PAGE_SIZE = 100', $source);
        $this->assertStringContainsString('closeDuplicateBatch', $source);
        $this->assertStringContainsString('slot_recovery_conflict', $source);
        $this->assertStringContainsString("array('failed', 'degraded')", $source);
        $this->assertStringContainsString('unverified_notification', $source);
        $this->assertStringContainsString('nesp_board_intake_checkpoint', $source);
        $this->assertStringContainsString('AUTO_IMPORT_FEATURE_FLAG', $source);
        $this->assertStringContainsString('event_terminal_write_failed', $source);
        $this->assertStringContainsString('run_terminal_write_failed', $source);
        $this->assertStringContainsString('discoverConversationPage', $source);
        $this->assertStringContainsString('persistMessagePage', $source);
        $this->assertStringContainsString('retry_not_before_epoch', $source);
        $this->assertStringContainsString('importApprovedRowsWithoutApplicantContact', $source);
        $this->assertStringNotContainsString(
            '$this->_intake->importApprovedRows($actorUserID, $batchID)',
            $source
        );
    }

    public function testQuestionnaireRecordIsTransactionalAndDeliveryIsAfterCommit()
    {
        $source = file_get_contents(LEGACY_ROOT . '/lib/BoardApplicantIntake.php');
        $importPosition = strpos($source, 'function importApprovedRows');
        $recordPosition = strpos($source, 'prepareQuestionnaireRecordForHumanReview(', $importPosition);
        $commitPosition = strpos($source, '$this->_db->commitTransaction();', $importPosition);
        $deliveryPosition = strpos($source, 'deliverPreparedQuestionnaireForHumanReview(', $commitPosition);

        $this->assertNotFalse($importPosition);
        $this->assertNotFalse($recordPosition);
        $this->assertNotFalse($commitPosition);
        $this->assertNotFalse($deliveryPosition);
        $this->assertGreaterThan($recordPosition, $commitPosition);
        $this->assertGreaterThan($commitPosition, $deliveryPosition);
        $this->assertStringContainsString('questionnaire_failed', $source);
    }

    public function testCronRoleCannotRewritePersistentMailSettings()
    {
        $entrypoint = file_get_contents(LEGACY_ROOT . '/docker/render/entrypoint.sh');
        $blueprint = file_get_contents(LEGACY_ROOT . '/render.yaml');

        $this->assertStringContainsString("mailEnv('NESP_SERVICE_ROLE') !== 'cron'", $entrypoint);
        $this->assertStringContainsString('NESP_SERVICE_ROLE', $blueprint);
        $this->assertStringContainsString('nesp-board-intake-scheduler', $blueprint);
        $this->assertStringContainsString('0 12,13,22,23 * * *', $blueprint);
        $this->assertStringContainsString('`access_level` >= 400', file_get_contents(
            LEGACY_ROOT . '/scripts/nesp_board_intake_scheduler.php'
        ));
    }

    public function testBasicAuthExemptsOnlyExactHmacWebhookAndHealthPaths()
    {
        $entrypoint = file_get_contents(LEGACY_ROOT . '/docker/render/entrypoint.sh');
        $webhook = file_get_contents(
            LEGACY_ROOT . '/modules/boardintake/missiveWebhook.php'
        );
        $protectedLocationPattern = '#^/(?!render-health\.txt$|modules/boardintake/missiveWebhook\.php$)#';

        $this->assertStringContainsString(
            '<LocationMatch "^/(?!render-health\\.txt$|modules/boardintake/missiveWebhook\\.php$)">',
            $entrypoint
        );
        $this->assertSame(0, preg_match($protectedLocationPattern, '/render-health.txt'));
        $this->assertSame(
            0,
            preg_match($protectedLocationPattern, '/modules/boardintake/missiveWebhook.php')
        );
        $this->assertSame(1, preg_match($protectedLocationPattern, '/index.php'));
        $this->assertSame(1, preg_match($protectedLocationPattern, '/modules/boardintake/'));
        $this->assertSame(
            1,
            preg_match($protectedLocationPattern, '/modules/boardintake/missiveWebhook.php/extra')
        );
        $this->assertStringContainsString('validateWebhookRequest(', $webhook);
        $this->assertStringContainsString("if (empty(\$validation['ok']))", $webhook);
    }

    public function testMigrationIsAdditiveAndDoesNotDisableAnExistingApproval()
    {
        $migration = file_get_contents(LEGACY_ROOT . '/db/nesp_board_intake_scheduler_additive.sql');
        $rollback = file_get_contents(LEGACY_ROOT . '/db/nesp_board_intake_scheduler_rollback.sql');

        $this->assertStringContainsString('CREATE TABLE IF NOT EXISTS `nesp_board_intake_run`', $migration);
        $this->assertStringContainsString('CREATE TABLE IF NOT EXISTS `nesp_board_intake_checkpoint`', $migration);
        $this->assertStringContainsString('CREATE TABLE IF NOT EXISTS `nesp_board_intake_event`', $migration);
        $this->assertStringContainsString('scan_high_water_epoch', $migration);
        $this->assertStringContainsString('conversation_page_json', $migration);
        $this->assertStringContainsString('message_until_epoch', $migration);
        $this->assertStringContainsString('retry_not_before_epoch', $migration);
        $this->assertStringContainsString('ADD COLUMN IF NOT EXISTS', $migration);
        $this->assertStringContainsString('NESP_BOARD_INTAKE_AUTO_IMPORT_ENABLED', $migration);
        $this->assertStringNotContainsString('`is_enabled` = 0', $migration);
        $this->assertStringContainsString('DROP TABLE IF EXISTS `nesp_board_intake_event`', $rollback);
        $this->assertStringContainsString('DROP TABLE IF EXISTS `nesp_board_intake_checkpoint`', $rollback);
    }
}
