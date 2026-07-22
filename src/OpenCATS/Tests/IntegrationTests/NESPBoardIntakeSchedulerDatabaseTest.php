<?php
namespace OpenCATS\Tests\IntegrationTests;

include_once(LEGACY_ROOT . '/lib/BoardApplicantIntake.php');

class NESPBoardIntakeSchedulerSessionStub
{
    public function isLoggedIn()
    {
        return true;
    }

    public function getUserID()
    {
        return 1;
    }

    public function getTimeZoneOffset()
    {
        return 0;
    }

    public function isDateDMY()
    {
        return false;
    }
}

class NESPBoardIntakeSchedulerFakeInbox
{
    private $messages = array();
    private $discoveryResult = array('ok' => true, 'events' => array());
    private $conversationPageResults = array();
    private $messagePageResults = array();
    public $lastSinceEpoch = null;
    public $conversationPageCalls = array();
    public $messagePageCalls = array();

    public function isConfigured()
    {
        return true;
    }

    public function discoverConversationPage($sinceEpoch = null, $untilEpoch = null)
    {
        $this->lastSinceEpoch = $sinceEpoch;
        $this->conversationPageCalls[] = array(
            'since' => $sinceEpoch,
            'until' => $untilEpoch
        );
        if ($this->conversationPageResults)
        {
            return array_shift($this->conversationPageResults);
        }
        if (empty($this->discoveryResult['ok']))
        {
            return $this->discoveryResult;
        }
        return array(
            'ok' => true,
            'conversation_ids' => array(),
            'next_until' => null,
            'complete' => true
        );
    }

    public function discoverConversationMessagePage(
        $conversationID,
        $sinceEpoch = null,
        $untilEpoch = null
    ) {
        $this->messagePageCalls[] = array(
            'conversation_id' => $conversationID,
            'since' => $sinceEpoch,
            'until' => $untilEpoch
        );
        if (isset($this->messagePageResults[$conversationID])
            && $this->messagePageResults[$conversationID])
        {
            return array_shift($this->messagePageResults[$conversationID]);
        }
        return array(
            'ok' => true,
            'events' => array(),
            'next_until' => null,
            'complete' => true
        );
    }

    public function fetchMessage($providerMessageID)
    {
        if (!isset($this->messages[$providerMessageID]))
        {
            return array('ok' => false, 'error' => 'fixture_message_missing');
        }

        $message = $this->messages[$providerMessageID];
        return \NESPBoardInboxIntegration::fetchMessage(
            $providerMessageID,
            'integration-test-token',
            function () use ($message) {
                return array(
                    'status_code' => 200,
                    'body' => json_encode(array('messages' => $message))
                );
            }
        );
    }

    public function classifyMessage($message)
    {
        return \NESPBoardInboxIntegration::classifyMessage($message);
    }

    public function getConfigurationStatus()
    {
        return array('configured' => true, 'ready' => true);
    }

    public function addMessage($providerMessageID, $message)
    {
        $message['id'] = $providerMessageID;
        $this->messages[$providerMessageID] = $message;
    }

    public function setDiscoveryResult($result)
    {
        $this->discoveryResult = $result;
    }

    public function queueConversationPageResult($result)
    {
        $this->conversationPageResults[] = $result;
    }

    public function queueMessagePageResult($conversationID, $result)
    {
        if (!isset($this->messagePageResults[$conversationID]))
        {
            $this->messagePageResults[$conversationID] = array();
        }
        $this->messagePageResults[$conversationID][] = $result;
    }
}

class NESPBoardIntakeSchedulerRecordingIntake extends \BoardApplicantIntake
{
    public $lastFailure = '';
    public $deliveryCapableImportCalls = 0;
    public $noContactImportCalls = 0;

    public function importApprovedRows($actorUserID, $batchID)
    {
        $this->deliveryCapableImportCalls++;
        try
        {
            return parent::importApprovedRows($actorUserID, $batchID);
        }
        catch (\Throwable $e)
        {
            $this->lastFailure = $e->getMessage();
            throw $e;
        }
    }

    public function importApprovedRowsWithoutApplicantContact($actorUserID, $batchID)
    {
        $this->noContactImportCalls++;
        try
        {
            return parent::importApprovedRowsWithoutApplicantContact($actorUserID, $batchID);
        }
        catch (\Throwable $e)
        {
            $this->lastFailure = $e->getMessage();
            throw $e;
        }
    }
}

class NESPBoardIntakeSchedulerDatabaseTest extends DatabaseTestCase
{
    private $db;
    private $scheduler;
    private $inbox;
    private $intake;
    private $lockConnection;
    private $previousSession;
    private $previousEnvironment = array();

    protected function setUp(): void
    {
        parent::setUp();

        include_once(LEGACY_ROOT . '/lib/NESPBoardIntakeScheduler.php');

        foreach (array(
            'NESP_BOARD_INTAKE_MISSIVE_RULE_ID',
            'NESP_BOARD_INTAKE_MISSIVE_WEBHOOK_SECRET',
            'NESP_SERVICE_ROLE',
            'OPENCATS_MAIL_ENABLED'
        ) as $key)
        {
            $this->previousEnvironment[$key] = getenv($key);
        }
        putenv('NESP_BOARD_INTAKE_MISSIVE_RULE_ID=integration-approved-rule');
        putenv('NESP_BOARD_INTAKE_MISSIVE_WEBHOOK_SECRET=integration-webhook-secret');
        putenv('NESP_SERVICE_ROLE=cron');
        putenv('OPENCATS_MAIL_ENABLED=0');

        $this->previousSession = isset($_SESSION['CATS']) ? $_SESSION['CATS'] : null;
        $_SESSION['CATS'] = new NESPBoardIntakeSchedulerSessionStub();
        $this->db = \DatabaseConnection::getInstance();
        $fixture = file_get_contents(
            __DIR__ . '/fixtures/nesp_board_intake_scheduler.sql'
        );
        $this->db->queryMultiple($fixture, ";\n");
        $this->inbox = new NESPBoardIntakeSchedulerFakeInbox();
        $this->intake = new NESPBoardIntakeSchedulerRecordingIntake($this->db);
        $this->scheduler = new \NESPBoardIntakeScheduler(
            $this->db,
            $this->intake,
            $this->inbox
        );
    }

    protected function tearDown(): void
    {
        if ($this->lockConnection instanceof \mysqli)
        {
            @mysqli_query(
                $this->lockConnection,
                "SELECT RELEASE_LOCK('nesp_board_intake_scheduler')"
            );
            @mysqli_close($this->lockConnection);
        }

        if ($this->previousSession === null)
        {
            unset($_SESSION['CATS']);
        }
        else
        {
            $_SESSION['CATS'] = $this->previousSession;
        }
        foreach ($this->previousEnvironment as $key => $value)
        {
            if ($value === false)
            {
                putenv($key);
            }
            else
            {
                putenv($key . '=' . $value);
            }
        }

        parent::tearDown();
    }

    public function testDisabledFeatureFlagPreventsRunClaim()
    {
        $result = $this->scheduler->runScheduledSlot(
            1,
            $this->scheduledTime('2026-07-22 08:00:00')
        );

        $this->assertFalse($this->scheduler->isEnabled());
        $this->assertSame('disabled', $result['status']);
        $this->assertSame('feature_disabled', $result['reason']);
        $this->assertSame(0, $this->countRows('nesp_board_intake_run'));
    }

    public function testFeatureFlagHandlesLegacyGetColumnRowArray()
    {
        $rawValue = $this->db->getColumn('SELECT 1 AS enabled', 0, 0);

        $this->assertIsArray($rawValue);
        $this->assertSame('1', $rawValue[0]);

        $this->enableScheduler();

        $this->assertTrue($this->scheduler->isEnabled());
    }

    public function testNamedLockDenialDoesNotCreateRun()
    {
        $this->enableScheduler();
        $this->lockConnection = $this->newConnection();

        $lock = mysqli_query(
            $this->lockConnection,
            "SELECT GET_LOCK('nesp_board_intake_scheduler', 0) AS acquired"
        );
        $lockRow = mysqli_fetch_assoc($lock);
        $this->assertSame('1', $lockRow['acquired']);

        $result = $this->scheduler->runScheduledSlot(
            1,
            $this->scheduledTime('2026-07-22 08:00:00')
        );

        $this->assertSame('busy', $result['status']);
        $this->assertSame('another_run_active', $result['reason']);
        $this->assertSame(0, $this->countRows('nesp_board_intake_run'));
    }

    public function testSlotRunsExactlyOnceAndFailedRunCanRecover()
    {
        $this->enableScheduler();
        $scheduledTime = $this->scheduledTime('2026-07-22 08:00:00');

        $first = $this->scheduler->runScheduledSlot(1, $scheduledTime);
        $duplicate = $this->scheduler->runScheduledSlot(1, $scheduledTime);

        $this->assertSame('completed', $first['status']);
        $this->assertSame('already_ran', $duplicate['status']);
        $this->assertSame('slot_already_claimed', $duplicate['reason']);
        $this->assertSame(1, $this->countRows('nesp_board_intake_run'));

        $this->query(sprintf(
            'UPDATE nesp_board_intake_run SET status_key = "failed", '
            . 'date_modified = DATE_SUB(NOW(), INTERVAL 31 MINUTE) WHERE run_id = %d',
            (int) $first['run_id']
        ));

        $recovered = $this->scheduler->runScheduledSlot(2, $scheduledTime);

        $this->assertSame('completed', $recovered['status']);
        $this->assertSame((int) $first['run_id'], (int) $recovered['run_id']);
        $this->assertSame(1, $this->countRows('nesp_board_intake_run'));
        $this->assertSame(
            1,
            $this->countRows(
                'nesp_board_intake_run',
                'slot_key = "2026-07-22-morning" AND status_key = "completed"'
            )
        );
    }

    public function testDuplicateWebhookIdentityIsQueuedOnce()
    {
        $event = array(
            'provider_message_id' => 'message-123',
            'email_message_id' => '<first@example.invalid>',
            'payload_hash' => hash('sha256', 'payload-one'),
            'subject_hash' => hash('sha256', 'subject-one'),
            'sender_hash' => hash('sha256', 'sender-one'),
            'verification_key' => \NESPBoardInboxIntegration::VERIFICATION_APPROVED_RULE_HMAC,
            'approved_rule_hash' => hash('sha256', 'integration-approved-rule'),
            'verification_proof' => \NESPBoardInboxIntegration::buildApprovedRuleVerificationProof(
                'message-123',
                hash('sha256', 'payload-one'),
                'integration-approved-rule',
                'integration-webhook-secret'
            ),
            'signature_verified_at' => '2026-07-22 12:00:00',
            'approved_rule_verified_at' => '2026-07-22 12:00:00',
            'received_at' => '2026-07-22 12:00:01'
        );

        $first = $this->scheduler->queueWebhookEvent($event);
        $event['email_message_id'] = '<changed@example.invalid>';
        $event['payload_hash'] = hash('sha256', 'payload-two');
        $duplicate = $this->scheduler->queueWebhookEvent($event);

        $this->assertTrue($first['ok']);
        $this->assertFalse($first['duplicate']);
        $this->assertTrue($duplicate['ok']);
        $this->assertTrue($duplicate['duplicate']);
        $this->assertSame(1, $this->countRows('nesp_board_intake_event'));

        $stored = $this->fetchOne(
            'SELECT provider_key, provider_message_id, email_message_hash, payload_hash '
            . 'FROM nesp_board_intake_event'
        );
        $this->assertSame('missive', $stored['provider_key']);
        $this->assertSame('message-123', $stored['provider_message_id']);
        $this->assertSame(hash('sha256', '<first@example.invalid>'), $stored['email_message_hash']);
        $this->assertSame(hash('sha256', 'payload-one'), $stored['payload_hash']);
    }

    public function testDurableCheckpointRetainsActiveScanAfterReconciliationFailure()
    {
        $this->enableScheduler();
        $scheduledTime = $this->scheduledTime('2026-07-22 08:00:00');

        $successful = $this->scheduler->runScheduledSlot(1, $scheduledTime);

        $this->assertSame('completed', $successful['status']);
        $this->assertSame(0, $this->inbox->lastSinceEpoch);
        $checkpoint = $this->fetchOne(
            'SELECT high_water_epoch, last_run_id FROM nesp_board_intake_checkpoint '
            . 'WHERE provider_key = "missive"'
        );
        $this->assertSame((string) $scheduledTime->getTimestamp(), $checkpoint['high_water_epoch']);
        $this->assertSame((string) $successful['run_id'], $checkpoint['last_run_id']);

        $this->inbox->setDiscoveryResult(array('ok' => false, 'error' => 'missive_request_failed'));
        $failed = $this->scheduler->runScheduledSlot(
            1,
            $this->scheduledTime('2026-07-22 18:00:00')
        );
        $this->assertSame('failed', $failed['status']);
        $this->assertSame('missive_request_failed', $failed['reason']);
        $activeScan = $this->fetchOne(
            'SELECT high_water_epoch, scan_high_water_epoch, last_run_id '
            . 'FROM nesp_board_intake_checkpoint '
            . 'WHERE provider_key = "missive"'
        );
        $this->assertSame($checkpoint['high_water_epoch'], $activeScan['high_water_epoch']);
        $this->assertSame(
            (string) $this->scheduledTime('2026-07-22 18:00:00')->getTimestamp(),
            $activeScan['scan_high_water_epoch']
        );
        $this->assertSame((string) $failed['run_id'], $activeScan['last_run_id']);
    }

    public function testCompletedPagePersistsAndLaterPageFailureResumesExactly()
    {
        $this->enableScheduler();
        $firstEventID = 'reconciled-page-one-application';
        $secondEventID = 'reconciled-page-two-application';
        $this->inbox->addMessage($firstEventID, $this->applicationMessage(
            '<reconciled-page-one@example.invalid>',
            'reconciled-page-one-external'
        ));
        $this->inbox->addMessage($secondEventID, $this->applicationMessage(
            '<reconciled-page-two@example.invalid>',
            'reconciled-page-two-external'
        ));
        $this->inbox->queueConversationPageResult(array(
            'ok' => true,
            'conversation_ids' => array('conversation-one', 'conversation-two'),
            'next_until' => null,
            'complete' => true
        ));
        $this->inbox->queueMessagePageResult('conversation-one', array(
            'ok' => true,
            'events' => array($this->reconciledEvent($firstEventID)),
            'next_until' => null,
            'complete' => true
        ));
        $this->inbox->queueMessagePageResult('conversation-two', array(
            'ok' => false,
            'error' => 'missive_request_failed'
        ));

        $firstTime = $this->scheduledTime('2026-07-22 08:00:00');
        $first = $this->scheduler->runScheduledSlot(1, $firstTime);

        $this->assertSame('degraded', $first['status']);
        $this->assertSame('missive_request_failed', $first['reason']);
        $this->assertSame(1, $first['counts']['review']);
        $checkpoint = $this->fetchOne(
            'SELECT high_water_epoch, scan_high_water_epoch, conversation_page_json, '
            . 'conversation_page_index, message_until_epoch '
            . 'FROM nesp_board_intake_checkpoint WHERE provider_key = "missive"'
        );
        $this->assertSame('0', $checkpoint['high_water_epoch']);
        $this->assertSame((string) $firstTime->getTimestamp(), $checkpoint['scan_high_water_epoch']);
        $this->assertSame(
            array('conversation-one', 'conversation-two'),
            json_decode($checkpoint['conversation_page_json'], true)
        );
        $this->assertSame('1', $checkpoint['conversation_page_index']);
        $this->assertNull($checkpoint['message_until_epoch']);

        $this->inbox->queueMessagePageResult('conversation-two', array(
            'ok' => true,
            'events' => array($this->reconciledEvent($secondEventID)),
            'next_until' => null,
            'complete' => true
        ));
        $second = $this->scheduler->runScheduledSlot(
            1,
            $this->scheduledTime('2026-07-22 18:00:00')
        );

        $this->assertSame('completed', $second['status']);
        $this->assertSame(1, $second['counts']['review']);
        $this->assertCount(1, $this->inbox->conversationPageCalls);
        $this->assertSame(
            array('conversation-one', 'conversation-two', 'conversation-two'),
            array_column($this->inbox->messagePageCalls, 'conversation_id')
        );
        $completed = $this->fetchOne(
            'SELECT high_water_epoch, scan_high_water_epoch, conversation_page_json, '
            . 'conversation_page_index FROM nesp_board_intake_checkpoint '
            . 'WHERE provider_key = "missive"'
        );
        $this->assertSame((string) $firstTime->getTimestamp(), $completed['high_water_epoch']);
        $this->assertSame('0', $completed['scan_high_water_epoch']);
        $this->assertSame('[]', $completed['conversation_page_json']);
        $this->assertSame('0', $completed['conversation_page_index']);
    }

    public function testLongRateLimitBackoffPersistsMessageCursorAndPacesResume()
    {
        $this->enableScheduler();
        $start = $this->scheduledTime('2026-07-22 08:00:00');
        $rateLimitedAt = $start->modify('+45 seconds')->getTimestamp();
        $providerMessageID = 'reconciled-before-long-backoff';
        $this->inbox->addMessage($providerMessageID, $this->applicationMessage(
            '<reconciled-before-long-backoff@example.invalid>',
            'reconciled-before-long-backoff-external'
        ));
        $this->inbox->queueConversationPageResult(array(
            'ok' => true,
            'conversation_ids' => array('rate-limited-conversation'),
            'next_until' => null,
            'complete' => true
        ));
        $this->inbox->queueMessagePageResult('rate-limited-conversation', array(
            'ok' => true,
            'events' => array($this->reconciledEvent($providerMessageID)),
            'next_until' => 5000,
            'complete' => false
        ));
        $this->inbox->queueMessagePageResult('rate-limited-conversation', array(
            'ok' => false,
            'error' => 'missive_rate_limited',
            'retry_after_seconds' => 60,
            'rate_limited_at_epoch' => $rateLimitedAt
        ));
        $this->inbox->queueMessagePageResult('rate-limited-conversation', array(
            'ok' => true,
            'events' => array(),
            'next_until' => null,
            'complete' => true
        ));

        $first = $this->scheduler->runScheduledSlot(1, $start);

        $this->assertSame('degraded', $first['status']);
        $this->assertSame('missive_rate_limited', $first['reason']);
        $this->assertCount(2, $this->inbox->messagePageCalls);
        $paused = $this->fetchOne(
            'SELECT message_until_epoch, retry_not_before_epoch, scan_high_water_epoch '
            . 'FROM nesp_board_intake_checkpoint WHERE provider_key = "missive"'
        );
        $this->assertSame('5000', $paused['message_until_epoch']);
        $this->assertSame((string) ($rateLimitedAt + 60), $paused['retry_not_before_epoch']);
        $this->assertSame((string) $start->getTimestamp(), $paused['scan_high_water_epoch']);

        $paced = $this->scheduler->runScheduledSlot(
            1,
            $start->modify('+90 seconds'),
            true
        );
        $this->assertSame('failed', $paced['status']);
        $this->assertSame('missive_rate_limited', $paced['reason']);
        $this->assertCount(2, $this->inbox->messagePageCalls);

        $resumed = $this->scheduler->runScheduledSlot(
            1,
            $start->modify('+106 seconds'),
            true
        );
        $this->assertSame('completed', $resumed['status']);
        $this->assertCount(3, $this->inbox->messagePageCalls);
        $this->assertSame(5000, $this->inbox->messagePageCalls[2]['until']);
        $completed = $this->fetchOne(
            'SELECT high_water_epoch, scan_high_water_epoch, message_until_epoch, '
            . 'retry_not_before_epoch FROM nesp_board_intake_checkpoint '
            . 'WHERE provider_key = "missive"'
        );
        $this->assertSame((string) $start->getTimestamp(), $completed['high_water_epoch']);
        $this->assertSame('0', $completed['scan_high_water_epoch']);
        $this->assertNull($completed['message_until_epoch']);
        $this->assertSame('0', $completed['retry_not_before_epoch']);
    }

    public function testVerifiedNotificationStopsForManualReviewWhileAutoImportIsOff()
    {
        $this->enableScheduler();
        $providerMessageID = 'verified-default-review-application';
        $this->inbox->addMessage($providerMessageID, $this->applicationMessage(
            '<verified-default-review@example.invalid>',
            'indeed-default-review-001'
        ));
        $this->assertTrue($this->scheduler->queueWebhookEvent($this->verifiedEvent(
            $providerMessageID,
            '<verified-default-review@example.invalid>'
        ))['ok']);

        $result = $this->scheduler->runScheduledSlot(
            1,
            $this->scheduledTime('2026-07-22 08:00:00')
        );

        $this->assertSame('completed', $result['status']);
        $this->assertSame(1, $result['counts']['review']);
        $event = $this->fetchOne(sprintf(
            'SELECT status_key, error_code, processed_at FROM nesp_board_intake_event '
            . 'WHERE provider_message_id = "%s"',
            $providerMessageID
        ));
        $this->assertSame('review', $event['status_key']);
        $this->assertSame('auto_import_disabled', $event['error_code']);
        $this->assertNotEmpty($event['processed_at']);
        $this->assertSame(0, $this->countRows('candidate'));
        $this->assertFalse($this->scheduler->isAutoImportEnabled());
    }

    public function testRealFetchClassifyDedupeCandidateWorkflowQuestionnaireAndEventPath()
    {
        $this->enableScheduler();
        $this->enableAutoImport();
        $this->enableApplicantEmailWithoutCronMailRuntime();
        $providerMessageID = 'signed-staff-photographer-application';
        $this->inbox->addMessage($providerMessageID, $this->applicationMessage(
            '<signed-staff-photographer@example.invalid>',
            'indeed-applicant-41002-001'
        ));
        $queued = $this->scheduler->queueWebhookEvent($this->verifiedEvent(
            $providerMessageID,
            '<signed-staff-photographer@example.invalid>'
        ));

        $this->assertTrue($queued['ok']);
        $this->assertFalse($queued['duplicate']);

        $result = $this->scheduler->runScheduledSlot(
            1,
            $this->scheduledTime('2026-07-22 08:00:00')
        );

        $event = $this->fetchOne(sprintf(
            'SELECT * FROM nesp_board_intake_event WHERE provider_message_id = "%s"',
            $providerMessageID
        ));
        $failureContext = json_encode(array(
            'result' => $result,
            'event' => $event,
            'candidates' => $this->countRows('candidate'),
            'batches' => $this->countRows('nesp_board_intake_batch'),
            'rows' => $this->countRows('nesp_board_intake_row'),
            'identities' => $this->countRows('nesp_board_intake_identity'),
            'workflows' => $this->countRows('nesp_candidate_workflow'),
            'questionnaires' => $this->countRows('nesp_screening_questionnaire'),
            'intake_failure' => $this->intake->lastFailure
        ));
        $this->assertSame('completed', $result['status'], $failureContext);
        $this->assertSame(1, $result['counts']['queued']);
        $this->assertSame(1, $result['counts']['imported']);
        $this->assertSame(0, $result['counts']['review']);
        $this->assertSame(0, $result['counts']['failed']);

        $this->assertSame('imported', $event['status_key']);
        $this->assertSame('indeed', $event['platform_key']);
        $this->assertSame('41002', $event['joborder_id']);
        $this->assertSame((string) $result['run_id'], $event['run_id']);
        $this->assertSame('1', $event['attempt_count']);
        $this->assertSame('', $event['error_code']);
        $this->assertNotEmpty($event['processed_at']);
        $candidateID = (int) $event['candidate_id'];
        $this->assertGreaterThan(0, $candidateID);

        $candidate = $this->fetchOne(sprintf(
            'SELECT first_name, last_name, email1, phone_home, source '
            . 'FROM candidate WHERE candidate_id = %d',
            $candidateID
        ));
        $this->assertSame('Integration', $candidate['first_name']);
        $this->assertSame('Applicant', $candidate['last_name']);
        $this->assertSame('integration.applicant@example.invalid', $candidate['email1']);
        $this->assertSame('555-0102', $candidate['phone_home']);
        $this->assertSame('NESP Ad: Indeed', $candidate['source']);

        $jobLink = $this->fetchOne(sprintf(
            'SELECT status, added_by FROM candidate_joborder '
            . 'WHERE candidate_id = %d AND joborder_id = 41002',
            $candidateID
        ));
        $this->assertSame('100', $jobLink['status']);
        $this->assertSame('1', $jobLink['added_by']);

        $workflow = $this->fetchOne(sprintf(
            'SELECT stage.stage_key, workflow.waiting_on_key, workflow.next_action_label '
            . 'FROM nesp_candidate_workflow AS workflow '
            . 'INNER JOIN nesp_workflow_stage AS stage '
            . 'ON stage.workflow_stage_id = workflow.workflow_stage_id '
            . 'WHERE workflow.candidate_id = %d AND workflow.joborder_id = 41002',
            $candidateID
        ));
        $this->assertSame('new', $workflow['stage_key']);
        $this->assertSame('Craig', $workflow['waiting_on_key']);
        $this->assertSame('Send questionnaire', $workflow['next_action_label']);

        $identity = $this->fetchOne(
            'SELECT intake_row_id, candidate_id FROM nesp_board_intake_identity '
            . 'WHERE platform_key = "indeed" '
            . 'AND external_id = "indeed-applicant-41002-001"'
        );
        $this->assertSame((string) $candidateID, $identity['candidate_id']);
        $intakeRow = $this->fetchOne(sprintf(
            'SELECT review_status, candidate_id, first_name, last_name, email, phone, pii_redacted_at '
            . 'FROM nesp_board_intake_row WHERE intake_row_id = %d',
            (int) $identity['intake_row_id']
        ));
        $this->assertSame('imported', $intakeRow['review_status']);
        $this->assertSame((string) $candidateID, $intakeRow['candidate_id']);
        $this->assertSame('', $intakeRow['first_name']);
        $this->assertSame('', $intakeRow['last_name']);
        $this->assertSame('', $intakeRow['email']);
        $this->assertSame('', $intakeRow['phone']);
        $this->assertNotEmpty($intakeRow['pii_redacted_at']);

        $questionnaire = $this->fetchOne(sprintf(
            'SELECT status_key, auto_email_status_key FROM nesp_screening_questionnaire '
            . 'WHERE candidate_id = %d AND joborder_id = 41002',
            $candidateID
        ));
        $this->assertSame('link_ready', $questionnaire['status_key']);
        $this->assertSame('not_attempted', $questionnaire['auto_email_status_key']);
        $this->assertSame(1, $this->countRows(
            'nesp_feature_flag',
            'flag_key = "NESP_APPLICANT_EMAIL_ENABLED" AND is_enabled = 1'
        ));
        $this->assertSame(0, $this->countRows(
            'nesp_audit_event',
            'event_type IN ("screening_questionnaire_auto_email_attempt_started", '
            . '"screening_questionnaire_auto_email_sent", "screening_questionnaire_auto_email_failed")'
        ));
        $this->assertSame(0, $this->intake->deliveryCapableImportCalls);
        $this->assertSame(1, $this->intake->noContactImportCalls);

        $run = $this->fetchOne(sprintf(
            'SELECT status_key, queued_count, imported_count, review_count, failed_count '
            . 'FROM nesp_board_intake_run WHERE run_id = %d',
            (int) $result['run_id']
        ));
        $this->assertSame('completed', $run['status_key']);
        $this->assertSame('1', $run['queued_count']);
        $this->assertSame('1', $run['imported_count']);
        $this->assertSame('0', $run['review_count']);
        $this->assertSame('0', $run['failed_count']);

        $duplicateMessageID = 'signed-staff-photographer-application-duplicate';
        $this->inbox->addMessage($duplicateMessageID, $this->applicationMessage(
            '<signed-staff-photographer-duplicate@example.invalid>',
            'indeed-applicant-41002-001'
        ));
        $duplicateQueued = $this->scheduler->queueWebhookEvent($this->verifiedEvent(
            $duplicateMessageID,
            '<signed-staff-photographer-duplicate@example.invalid>'
        ));
        $this->assertTrue($duplicateQueued['ok']);

        $duplicateRun = $this->scheduler->runScheduledSlot(
            1,
            $this->scheduledTime('2026-07-22 18:00:00')
        );
        $this->assertSame('completed', $duplicateRun['status']);
        $this->assertSame(1, $duplicateRun['counts']['duplicates']);
        $this->assertSame(1, $this->countRows('candidate'));
        $duplicateEvent = $this->fetchOne(sprintf(
            'SELECT status_key, candidate_id, processed_at FROM nesp_board_intake_event '
            . 'WHERE provider_message_id = "%s"',
            $duplicateMessageID
        ));
        $this->assertSame('duplicate', $duplicateEvent['status_key']);
        $this->assertSame((string) $candidateID, $duplicateEvent['candidate_id']);
        $this->assertNotEmpty($duplicateEvent['processed_at']);
    }

    public function testUnsignedReconciliationEventFailsClosedToPersistedReview()
    {
        $this->enableScheduler();
        $providerMessageID = 'unsigned-reconciled-application';
        $this->inbox->addMessage($providerMessageID, $this->applicationMessage(
            '<unsigned-reconciled@example.invalid>',
            'linkedin-applicant-unsigned-001',
            'jobs-noreply@linkedin.com',
            'Unsigned',
            'unsigned.applicant@example.invalid'
        ));
        $queued = $this->scheduler->queueWebhookEvent(array(
            'provider_message_id' => $providerMessageID,
            'email_message_id' => '<unsigned-reconciled@example.invalid>',
            'payload_hash' => hash('sha256', 'unsigned-payload'),
            'received_at' => '2026-07-22 12:05:00'
        ));

        $this->assertTrue($queued['ok']);
        $this->assertFalse($queued['duplicate']);

        $result = $this->scheduler->runScheduledSlot(
            1,
            $this->scheduledTime('2026-07-22 18:00:00')
        );

        $this->assertSame('completed', $result['status']);
        $this->assertSame(1, $result['counts']['queued']);
        $this->assertSame(0, $result['counts']['imported']);
        $this->assertSame(1, $result['counts']['review']);
        $this->assertSame(0, $result['counts']['failed']);

        $event = $this->fetchOne(sprintf(
            'SELECT status_key, platform_key, joborder_id, candidate_id, run_id, '
            . 'attempt_count, error_code, processed_at '
            . 'FROM nesp_board_intake_event WHERE provider_message_id = "%s"',
            $providerMessageID
        ));
        $this->assertSame('review', $event['status_key']);
        $this->assertSame('linkedin', $event['platform_key']);
        $this->assertSame('41002', $event['joborder_id']);
        $this->assertNull($event['candidate_id']);
        $this->assertSame((string) $result['run_id'], $event['run_id']);
        $this->assertSame('1', $event['attempt_count']);
        $this->assertSame('unverified_notification', $event['error_code']);
        $this->assertNotEmpty($event['processed_at']);

        $this->assertSame(0, $this->countRows('candidate'));
        $this->assertSame(0, $this->countRows('nesp_board_intake_batch'));
        $this->assertSame(0, $this->countRows('nesp_board_intake_identity'));

        $run = $this->fetchOne(sprintf(
            'SELECT status_key, queued_count, imported_count, review_count, failed_count '
            . 'FROM nesp_board_intake_run WHERE run_id = %d',
            (int) $result['run_id']
        ));
        $this->assertSame('completed', $run['status_key']);
        $this->assertSame('1', $run['queued_count']);
        $this->assertSame('0', $run['imported_count']);
        $this->assertSame('1', $run['review_count']);
        $this->assertSame('0', $run['failed_count']);
    }

    public function testEventTerminalWriteFailureIsReturnedAndRecordedOnRun()
    {
        $this->enableScheduler();
        $providerMessageID = 'terminal-event-write-failure';
        $this->inbox->addMessage($providerMessageID, $this->applicationMessage(
            '<terminal-event-write-failure@example.invalid>',
            'terminal-event-write-failure-001'
        ));
        $this->assertTrue($this->scheduler->queueWebhookEvent(array(
            'provider_message_id' => $providerMessageID,
            'email_message_id' => '<terminal-event-write-failure@example.invalid>',
            'received_at' => '2026-07-22 12:00:00'
        ))['ok']);
        $this->query(
            'CREATE TRIGGER nesp_test_fail_event_terminal BEFORE UPDATE ON nesp_board_intake_event '
            . 'FOR EACH ROW BEGIN IF NEW.processed_at IS NOT NULL THEN '
            . 'SIGNAL SQLSTATE "45000" SET MESSAGE_TEXT = "forced event terminal failure"; '
            . 'END IF; END'
        );

        $result = $this->scheduler->runScheduledSlot(
            1,
            $this->scheduledTime('2026-07-22 08:00:00')
        );

        $this->assertSame('failed', $result['status']);
        $this->assertSame('event_terminal_write_failed', $result['reason']);
        $run = $this->fetchOne('SELECT status_key, error_code FROM nesp_board_intake_run LIMIT 1');
        $this->assertSame('failed', $run['status_key']);
        $this->assertSame('event_terminal_write_failed', $run['error_code']);
    }

    public function testRunTerminalWriteFailureIsReturnedExplicitly()
    {
        $this->enableScheduler();
        $this->query(
            'CREATE TRIGGER nesp_test_fail_run_terminal BEFORE UPDATE ON nesp_board_intake_run '
            . 'FOR EACH ROW SIGNAL SQLSTATE "45000" SET MESSAGE_TEXT = "forced run terminal failure"'
        );

        $result = $this->scheduler->runScheduledSlot(
            1,
            $this->scheduledTime('2026-07-22 08:00:00')
        );

        $this->assertSame('failed', $result['status']);
        $this->assertSame('run_terminal_write_failed', $result['reason']);
        $this->assertTrue($result['terminal_write_failed']);
        $run = $this->fetchOne('SELECT status_key, completed_at FROM nesp_board_intake_run LIMIT 1');
        $this->assertSame('running', $run['status_key']);
        $this->assertNull($run['completed_at']);
    }

    private function enableScheduler()
    {
        $this->query(
            'UPDATE nesp_feature_flag SET is_enabled = 1 '
            . 'WHERE flag_key = "NESP_BOARD_INTAKE_SCHEDULER_ENABLED"'
        );
    }

    private function enableAutoImport()
    {
        $this->query(
            'UPDATE nesp_feature_flag SET is_enabled = 1 '
            . 'WHERE flag_key = "NESP_BOARD_INTAKE_AUTO_IMPORT_ENABLED"'
        );
    }

    private function enableApplicantEmailWithoutCronMailRuntime()
    {
        $this->query(
            'UPDATE nesp_feature_flag SET is_enabled = 1 '
            . 'WHERE flag_key = "NESP_APPLICANT_EMAIL_ENABLED"'
        );
    }

    private function verifiedEvent($providerMessageID, $emailMessageID)
    {
        $payloadHash = hash('sha256', 'payload|' . $providerMessageID);
        return array(
            'provider_message_id' => $providerMessageID,
            'email_message_id' => $emailMessageID,
            'payload_hash' => $payloadHash,
            'subject_hash' => hash('sha256', 'subject|' . $providerMessageID),
            'sender_hash' => hash('sha256', 'notifications@indeed.com'),
            'verification_key' => \NESPBoardInboxIntegration::VERIFICATION_APPROVED_RULE_HMAC,
            'approved_rule_hash' => hash('sha256', 'integration-approved-rule'),
            'verification_proof' => \NESPBoardInboxIntegration::buildApprovedRuleVerificationProof(
                $providerMessageID,
                $payloadHash,
                'integration-approved-rule',
                'integration-webhook-secret'
            ),
            'signature_verified_at' => '2026-07-22 12:00:00',
            'approved_rule_verified_at' => '2026-07-22 12:00:00',
            'received_at' => '2026-07-22 12:00:01'
        );
    }

    private function reconciledEvent($providerMessageID)
    {
        return array(
            'provider_message_id' => $providerMessageID,
            'email_message_id' => '<' . $providerMessageID . '@example.invalid>',
            'payload_hash' => hash('sha256', 'reconciled|' . $providerMessageID),
            'subject_hash' => hash('sha256', 'subject|' . $providerMessageID),
            'sender_hash' => hash('sha256', 'notifications@indeed.com'),
            'verification_key' => \NESPBoardInboxIntegration::VERIFICATION_SHARED_LABEL_ONLY,
            'received_at' => '2026-07-22 12:00:00'
        );
    }

    private function applicationMessage(
        $emailMessageID,
        $externalID,
        $fromAddress = 'notifications@indeed.com',
        $firstName = 'Integration',
        $email = 'integration.applicant@example.invalid'
    ) {
        return array(
            'type' => 'email',
            'email_message_id' => $emailMessageID,
            'subject' => 'New application for Staff Photographer',
            'preview' => 'A candidate has applied for Staff Photographer',
            'body' => "NESP Job Order: 41002\nApplication ID: " . $externalID
                . "\nApplicant Name: " . $firstName . " Applicant\nApplicant Email: " . $email
                . "\nPhone: 555-0102",
            'from_field' => array(
                'name' => 'Board Notifications',
                'address' => $fromAddress
            )
        );
    }

    private function scheduledTime($time)
    {
        return new \DateTimeImmutable($time, new \DateTimeZone('America/New_York'));
    }

    private function newConnection()
    {
        $connection = mysqli_connect(
            DATABASE_HOST,
            DATABASE_USER,
            DATABASE_PASS,
            DATABASE_NAME
        );
        $this->assertInstanceOf(\mysqli::class, $connection);

        return $connection;
    }

    private function query($sql)
    {
        global $mySQLConnection;

        $result = mysqli_query($mySQLConnection, $sql);
        $this->assertNotFalse(
            $result,
            mysqli_error($mySQLConnection) . ' while running: ' . $sql
        );

        return $result;
    }

    private function countRows($table, $where = '1 = 1')
    {
        $row = $this->fetchOne(sprintf(
            'SELECT COUNT(*) AS total FROM `%s` WHERE %s',
            $table,
            $where
        ));

        return (int) $row['total'];
    }

    private function fetchOne($sql)
    {
        $result = $this->query($sql);
        $row = mysqli_fetch_assoc($result);
        $this->assertIsArray($row);

        return $row;
    }
}
