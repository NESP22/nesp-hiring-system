<?php

include_once(LEGACY_ROOT . '/lib/BoardApplicantIntake.php');
include_once(LEGACY_ROOT . '/lib/NESPBoardInboxIntegration.php');

/**
 * Twice-daily, exact-once board-notification intake coordinator.
 */
class NESPBoardIntakeScheduler
{
    const FEATURE_FLAG = 'NESP_BOARD_INTAKE_SCHEDULER_ENABLED';
    const AUTO_IMPORT_FEATURE_FLAG = 'NESP_BOARD_INTAKE_AUTO_IMPORT_ENABLED';
    const TIMEZONE = 'America/New_York';
    const MORNING_HOUR = 8;
    const EVENING_HOUR = 18;
    const EVENT_PAGE_SIZE = 100;
    const LOCK_NAME = 'nesp_board_intake_scheduler';

    private $_db;
    private $_intake;
    private $_inbox;

    public function __construct($db = null, $intake = null, $inbox = null)
    {
        $this->_db = $db ?: DatabaseConnection::getInstance();
        $this->_intake = $intake ?: new BoardApplicantIntake($this->_db);
        $this->_inbox = $inbox ?: new NESPBoardInboxIntegration();
    }

    public static function slotForTime($time)
    {
        if (!$time instanceof DateTimeInterface)
        {
            $time = new DateTimeImmutable((string) $time, new DateTimeZone(self::TIMEZONE));
        }
        $local = (new DateTimeImmutable($time->format('c')))->setTimezone(new DateTimeZone(self::TIMEZONE));
        $hour = (int) $local->format('G');
        if ($hour === self::MORNING_HOUR)
        {
            return $local->format('Y-m-d') . '-morning';
        }
        if ($hour === self::EVENING_HOUR)
        {
            return $local->format('Y-m-d') . '-evening';
        }
        return '';
    }

    public static function nextCheckAt($time = null)
    {
        $zone = new DateTimeZone(self::TIMEZONE);
        $now = $time instanceof DateTimeInterface
            ? (new DateTimeImmutable($time->format('c')))->setTimezone($zone)
            : new DateTimeImmutable($time ?: 'now', $zone);
        $morning = $now->setTime(self::MORNING_HOUR, 0, 0);
        $evening = $now->setTime(self::EVENING_HOUR, 0, 0);
        if ($now < $morning)
        {
            return $morning;
        }
        if ($now < $evening)
        {
            return $evening;
        }
        return $morning->modify('+1 day');
    }

    public function isEnabled()
    {
        if (!$this->tableExists('nesp_feature_flag'))
        {
            return false;
        }
        return (int) $this->scalarValue($this->_db->getColumn(sprintf(
            'SELECT is_enabled FROM nesp_feature_flag WHERE flag_key = %s LIMIT 1',
            $this->_db->makeQueryString(self::FEATURE_FLAG)
        ), 0, 0)) === 1;
    }

    public function isAutoImportEnabled()
    {
        if (!$this->tableExists('nesp_feature_flag'))
        {
            return false;
        }
        return (int) $this->scalarValue($this->_db->getColumn(sprintf(
            'SELECT is_enabled FROM nesp_feature_flag WHERE flag_key = %s LIMIT 1',
            $this->_db->makeQueryString(self::AUTO_IMPORT_FEATURE_FLAG)
        ), 0, 0)) === 1;
    }

    public function queueWebhookEvent($event)
    {
        if (!$this->tableExists('nesp_board_intake_event') || empty($event['provider_message_id']))
        {
            return array('ok' => false, 'error' => 'scheduler_schema_missing');
        }

        $sql = sprintf(
            'INSERT IGNORE INTO nesp_board_intake_event
                (provider_key, provider_message_id, email_message_hash, payload_hash, subject_hash,
                 sender_hash, verification_key, approved_rule_hash, verification_proof, signature_verified_at,
                 approved_rule_verified_at, status_key, provider_received_at,
                 date_created, date_modified)
             VALUES ("missive", %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, "pending", %s, NOW(), NOW())',
            $this->_db->makeQueryString($event['provider_message_id']),
            $this->_db->makeQueryString(hash(
                'sha256',
                isset($event['email_message_id']) ? (string) $event['email_message_id'] : ''
            )),
            $this->_db->makeQueryString(isset($event['payload_hash']) ? $event['payload_hash'] : ''),
            $this->_db->makeQueryString(isset($event['subject_hash']) ? $event['subject_hash'] : ''),
            $this->_db->makeQueryString(isset($event['sender_hash']) ? $event['sender_hash'] : ''),
            $this->_db->makeQueryString(isset($event['verification_key']) ? $event['verification_key'] : ''),
            $this->_db->makeQueryString(
                isset($event['approved_rule_hash'])
                    && preg_match('/^[a-f0-9]{64}$/D', (string) $event['approved_rule_hash'])
                    ? $event['approved_rule_hash']
                    : ''
            ),
            $this->_db->makeQueryString(
                isset($event['verification_proof'])
                    && preg_match('/^[a-f0-9]{64}$/D', (string) $event['verification_proof'])
                    ? $event['verification_proof']
                    : ''
            ),
            isset($event['signature_verified_at']) && $event['signature_verified_at'] !== ''
                ? $this->_db->makeQueryString($event['signature_verified_at'])
                : 'NULL',
            isset($event['approved_rule_verified_at']) && $event['approved_rule_verified_at'] !== ''
                ? $this->_db->makeQueryString($event['approved_rule_verified_at'])
                : 'NULL',
            $this->_db->makeQueryString(isset($event['received_at']) ? $event['received_at'] : gmdate('Y-m-d H:i:s'))
        );
        if (!$this->_db->query($sql))
        {
            return array('ok' => false, 'error' => 'queue_failed');
        }
        return array('ok' => true, 'duplicate' => (int) $this->_db->getAffectedRows() === 0);
    }

    public function runScheduledSlot($actorUserID = 0, $now = null, $force = false)
    {
        $actorUserID = (int) $actorUserID;
        if ($actorUserID <= 0)
        {
            return array('status' => 'stopped', 'reason' => 'system_user_missing');
        }
        if (!$this->isEnabled())
        {
            return array('status' => 'disabled', 'reason' => 'feature_disabled');
        }
        if (!$this->_inbox->isConfigured())
        {
            return array('status' => 'stopped', 'reason' => 'provider_not_configured');
        }
        if (!$this->tableExists('nesp_board_intake_event')
            || !$this->tableExists('nesp_board_intake_run')
            || !$this->tableExists('nesp_board_intake_checkpoint'))
        {
            return array('status' => 'stopped', 'reason' => 'scheduler_schema_missing');
        }

        $zone = new DateTimeZone(self::TIMEZONE);
        $localNow = $now instanceof DateTimeInterface
            ? (new DateTimeImmutable($now->format('c')))->setTimezone($zone)
            : new DateTimeImmutable($now ?: 'now', $zone);
        $slot = $force ? 'manual-' . $localNow->format('Ymd-His') . '-' . substr(hash('sha256', uniqid('', true)), 0, 8)
            : self::slotForTime($localNow);
        if ($slot === '')
        {
            return array('status' => 'not_due', 'reason' => 'outside_local_check_window');
        }

        if ((int) $this->scalarValue($this->_db->getColumn(sprintf(
            'SELECT GET_LOCK(%s, 0)', $this->_db->makeQueryString(self::LOCK_NAME)
        ), 0, 0)) !== 1)
        {
            return array('status' => 'busy', 'reason' => 'another_run_active');
        }

        $runID = 0;
        $counts = array('queued' => 0, 'imported' => 0, 'duplicates' => 0, 'review' => 0, 'failed' => 0);
        $runErrorCode = '';
        try
        {
            $existingRun = $this->_db->getAssoc(sprintf(
                'SELECT run_id, status_key, date_modified FROM nesp_board_intake_run
                 WHERE slot_key = %s LIMIT 1',
                $this->_db->makeQueryString($slot)
            ));
            if (!empty($existingRun))
            {
                $recoverable = in_array($existingRun['status_key'], array('failed', 'degraded'), true)
                    || ($existingRun['status_key'] === 'running'
                        && strtotime((string) $existingRun['date_modified']) < time() - 1800);
                if (!$recoverable)
                {
                    return array('status' => 'already_ran', 'reason' => 'slot_already_claimed');
                }

                $runID = (int) $existingRun['run_id'];
                $reclaim = sprintf(
                    'UPDATE nesp_board_intake_run SET status_key = "running", actor_user_id = %s,
                        started_at = NOW(), completed_at = NULL, queued_count = 0, imported_count = 0,
                        duplicate_count = 0, review_count = 0, failed_count = 0, error_code = "",
                        date_modified = NOW()
                     WHERE run_id = %s AND (status_key IN ("failed", "degraded")
                        OR (status_key = "running" AND date_modified < DATE_SUB(NOW(), INTERVAL 30 MINUTE)))',
                    $this->_db->makeQueryInteger($actorUserID),
                    $this->_db->makeQueryInteger($runID)
                );
                if (!$this->_db->query($reclaim) || (int) $this->_db->getAffectedRows() !== 1)
                {
                    return array('status' => 'busy', 'reason' => 'slot_recovery_conflict');
                }
            }
            else
            {
                $insert = sprintf(
                    'INSERT INTO nesp_board_intake_run
                        (run_key, slot_key, status_key, actor_user_id, started_at, date_created, date_modified)
                     VALUES (%s, %s, "running", %s, NOW(), NOW(), NOW())',
                    $this->_db->makeQueryString($slot),
                    $this->_db->makeQueryString($slot),
                    $this->_db->makeQueryInteger($actorUserID)
                );
                if (!$this->_db->query($insert))
                {
                    throw new RuntimeException('Run claim failed.');
                }
                $runID = (int) $this->_db->getLastInsertID();
            }

            $reconciliation = $this->reconcileProviderPages(
                $runID,
                $localNow->getTimestamp()
            );
            if (empty($reconciliation['ok']))
            {
                $counts['failed']++;
                $runErrorCode = isset($reconciliation['error'])
                    ? substr((string) $reconciliation['error'], 0, 64)
                    : 'provider_reconciliation_failed';
            }

            while (true)
            {
                $events = $this->_db->getAllAssoc(sprintf(
                    'SELECT * FROM nesp_board_intake_event
                     WHERE (status_key = "pending" AND (run_id IS NULL OR run_id <> %s))
                        OR (status_key = "processing" AND date_modified < DATE_SUB(NOW(), INTERVAL 30 MINUTE))
                     ORDER BY event_id ASC LIMIT %d',
                    $this->_db->makeQueryInteger($runID),
                    self::EVENT_PAGE_SIZE
                ));
                if (!$events)
                {
                    break;
                }
                $counts['queued'] += count($events);

                foreach ($events as $event)
                {
                    $eventID = (int) $event['event_id'];
                    $claim = sprintf(
                        'UPDATE nesp_board_intake_event SET status_key = "processing", run_id = %s,
                            attempt_count = attempt_count + 1, last_attempt_at = NOW(), date_modified = NOW()
                         WHERE event_id = %s AND ((status_key = "pending" AND (run_id IS NULL OR run_id <> %s))
                            OR (status_key = "processing" AND date_modified < DATE_SUB(NOW(), INTERVAL 30 MINUTE)))',
                        $this->_db->makeQueryInteger($runID),
                        $this->_db->makeQueryInteger($eventID),
                        $this->_db->makeQueryInteger($runID)
                    );
                    if (!$this->_db->query($claim) || (int) $this->_db->getAffectedRows() !== 1)
                    {
                        continue;
                    }

                    $result = $this->processEvent($event, $actorUserID, $runID);
                    $bucket = isset($result['bucket']) ? $result['bucket'] : 'failed';
                    $counts[$bucket] = isset($counts[$bucket]) ? $counts[$bucket] + 1 : 1;
                }
            }

            $successfulOutcomes = $counts['imported'] + $counts['duplicates'] + $counts['review'];
            $runStatus = $counts['failed'] > 0
                ? ($successfulOutcomes > 0 ? 'degraded' : 'failed')
                : 'completed';
            if ($counts['failed'] > 0 && $runErrorCode === '')
            {
                $runErrorCode = 'provider_or_import_failure';
            }
            if (!$this->finishRun(
                $runID,
                $runStatus,
                $counts,
                $runErrorCode
            ))
            {
                throw new RuntimeException('run_terminal_write_failed');
            }
            return array(
                'status' => $runStatus,
                'reason' => $runErrorCode,
                'run_id' => $runID,
                'counts' => $counts
            );
        }
        catch (Throwable $e)
        {
            $failureReason = in_array($e->getMessage(), array(
                'event_terminal_write_failed',
                'run_terminal_write_failed',
                'reconciliation_checkpoint_write_failed',
                'reconciliation_checkpoint_missing',
                'reconciliation_checkpoint_corrupt'
            ), true) ? $e->getMessage() : 'run_failed';
            if ($runID > 0)
            {
                $counts['failed']++;
                if (!$this->finishRun($runID, 'failed', $counts, $failureReason))
                {
                    return array(
                        'status' => 'failed',
                        'reason' => 'run_terminal_write_failed',
                        'counts' => $counts,
                        'terminal_write_failed' => true
                    );
                }
            }
            return array('status' => 'failed', 'reason' => $failureReason, 'counts' => $counts);
        }
        finally
        {
            $this->scalarValue($this->_db->getColumn(sprintf(
                'SELECT RELEASE_LOCK(%s)', $this->_db->makeQueryString(self::LOCK_NAME)
            ), 0, 0));
        }
    }

    public function getStatusSummary($now = null)
    {
        $config = $this->_inbox->getConfigurationStatus();
        $summary = array(
            'feature_enabled' => $this->isEnabled(),
            'auto_import_enabled' => $this->isAutoImportEnabled(),
            'provider_configured' => $this->_inbox->isConfigured(),
            'configuration' => $config,
            'last_run' => array(),
            'pending_count' => 0,
            'review_count' => 0,
            'error_count' => 0,
            'next_check_at' => self::nextCheckAt($now)->format('Y-m-d g:i A T')
        );
        if (!$this->tableExists('nesp_board_intake_event') || !$this->tableExists('nesp_board_intake_run'))
        {
            return $summary;
        }
        $summary['last_run'] = $this->_db->getAssoc(
            'SELECT status_key, queued_count, imported_count, duplicate_count,
                    review_count, failed_count, error_code, started_at, completed_at
             FROM nesp_board_intake_run ORDER BY run_id DESC LIMIT 1'
        );
        $counts = $this->_db->getAllAssoc(
            'SELECT status_key, COUNT(*) AS item_count FROM nesp_board_intake_event
             WHERE status_key IN ("pending", "review", "error") GROUP BY status_key'
        );
        foreach ($counts as $row)
        {
            $key = $row['status_key'] . '_count';
            $summary[$key] = (int) $row['item_count'];
        }
        return $summary;
    }

    public function getAttentionItems($limit = 20)
    {
        if (!$this->tableExists('nesp_board_intake_event'))
        {
            return array();
        }
        $limit = max(1, min(50, (int) $limit));
        return $this->_db->getAllAssoc(sprintf(
            'SELECT event_id, status_key, platform_key, joborder_id, error_code,
                    provider_received_at, processed_at
             FROM nesp_board_intake_event
             WHERE status_key IN ("review", "error")
             ORDER BY event_id DESC LIMIT %d',
            $limit
        ));
    }

    private function processEvent($event, $actorUserID, $runID)
    {
        $eventID = (int) $event['event_id'];
        $fetched = $this->_inbox->fetchMessage($event['provider_message_id']);
        if (empty($fetched['ok']))
        {
            $attempt = isset($event['attempt_count']) ? (int) $event['attempt_count'] + 1 : 1;
            $this->markEvent(
                $eventID,
                $attempt < 3 ? 'pending' : 'error',
                '',
                0,
                0,
                isset($fetched['error']) ? $fetched['error'] : 'provider_error'
            );
            return array('bucket' => 'failed');
        }

        $classified = $this->_inbox->classifyMessage($fetched['message']);
        if (!NESPBoardInboxIntegration::hasVerifiedApprovedRuleMetadata($event))
        {
            $this->markEvent(
                $eventID,
                'review',
                isset($classified['platform']) ? $classified['platform'] : '',
                isset($classified['joborder_id']) ? (int) $classified['joborder_id'] : 0,
                0,
                'unverified_notification'
            );
            return array('bucket' => 'review');
        }
        if (!$this->isAutoImportEnabled())
        {
            $this->markEvent(
                $eventID,
                'review',
                isset($classified['platform']) ? $classified['platform'] : '',
                isset($classified['joborder_id']) ? (int) $classified['joborder_id'] : 0,
                0,
                'auto_import_disabled'
            );
            return array('bucket' => 'review');
        }
        if (!empty($classified['review_required']))
        {
            $reviewReason = !empty($classified['review_reasons'][0])
                ? $classified['review_reasons'][0]
                : 'review_required';
            $this->markEvent(
                $eventID,
                'review',
                isset($classified['platform']) ? $classified['platform'] : '',
                isset($classified['joborder_id']) ? (int) $classified['joborder_id'] : 0,
                0,
                $reviewReason
            );
            return array('bucket' => 'review');
        }

        $csvHandle = fopen('php://temp', 'r+');
        fputcsv($csvHandle, array('external_id', 'first_name', 'last_name', 'email', 'phone'));
        fputcsv($csvHandle, array(
            $classified['external_id'], $classified['first_name'], $classified['last_name'],
            $classified['email'], $classified['phone']
        ));
        rewind($csvHandle);
        $csv = stream_get_contents($csvHandle);
        fclose($csvHandle);

        $parsed = BoardApplicantIntake::parseCsv(
            $csv,
            $classified['platform'],
            (int) $classified['joborder_id'],
            $classified['source_label']
        );
        if (!empty($parsed['errors']) || count($parsed['rows']) !== 1)
        {
            $this->markEvent($eventID, 'review', $classified['platform'], (int) $classified['joborder_id'], 0, 'validation_failed');
            return array('bucket' => 'review');
        }

        $batchID = $this->_intake->createBatch(
            $actorUserID,
            $classified['platform'],
            (int) $classified['joborder_id'],
            $classified['source_label'],
            $parsed['rows'],
            hash('sha256', 'missive|' . $event['provider_message_id'])
        );
        if ($batchID <= 0)
        {
            $this->markEvent($eventID, 'error', $classified['platform'], (int) $classified['joborder_id'], 0, 'batch_failed');
            return array('bucket' => 'failed');
        }
        $this->_intake->applyDuplicateChecks($batchID);
        $rows = $this->_intake->getRows($batchID);
        if (!$rows)
        {
            $this->markEvent($eventID, 'error', $classified['platform'], (int) $classified['joborder_id'], 0, 'duplicate_check_failed');
            return array('bucket' => 'failed');
        }
        if ($rows[0]['duplicate_status'] === 'already_imported')
        {
            $candidateID = isset($rows[0]['duplicate_candidate_id'])
                ? (int) $rows[0]['duplicate_candidate_id']
                : 0;
            $this->_intake->closeDuplicateBatch($batchID);
            $this->markEvent($eventID, 'duplicate', $classified['platform'], (int) $classified['joborder_id'], $candidateID, 'already_imported');
            return array('bucket' => 'duplicates');
        }
        if ($rows[0]['duplicate_status'] !== 'none')
        {
            $candidateID = isset($rows[0]['duplicate_candidate_id'])
                ? (int) $rows[0]['duplicate_candidate_id']
                : 0;
            $this->markEvent(
                $eventID,
                'review',
                $classified['platform'],
                (int) $classified['joborder_id'],
                $candidateID,
                $rows[0]['duplicate_status']
            );
            return array('bucket' => 'review');
        }

        $this->_intake->recordPreview($batchID, $actorUserID);
        $approved = $this->_intake->approveRows($batchID, array((int) $rows[0]['intake_row_id']), $actorUserID);
        if ($approved !== 1)
        {
            $this->markEvent($eventID, 'review', $classified['platform'], (int) $classified['joborder_id'], 0, 'approval_failed');
            return array('bucket' => 'review');
        }
        $import = $this->_intake->importApprovedRowsWithoutApplicantContact(
            $actorUserID,
            $batchID
        );
        if ((int) $import['imported'] !== 1)
        {
            $this->markEvent($eventID, 'error', $classified['platform'], (int) $classified['joborder_id'], 0, 'import_failed');
            return array('bucket' => 'failed');
        }

        $importedRows = $this->_intake->getRows($batchID);
        $candidateID = isset($importedRows[0]['candidate_id']) ? (int) $importedRows[0]['candidate_id'] : 0;
        if (!empty($import['questionnaire_failed']))
        {
            $this->markEvent($eventID, 'review', $classified['platform'], (int) $classified['joborder_id'], $candidateID, 'questionnaire_delivery_failed');
            return array('bucket' => 'review');
        }
        $this->markEvent($eventID, 'imported', $classified['platform'], (int) $classified['joborder_id'], $candidateID, '');
        return array('bucket' => 'imported');
    }

    private function markEvent($eventID, $status, $platform, $jobOrderID, $candidateID, $errorCode)
    {
        $sql = sprintf(
            'UPDATE nesp_board_intake_event SET status_key = %s, platform_key = %s,
                joborder_id = %s, candidate_id = %s, error_code = %s,
                processed_at = %s, date_modified = NOW() WHERE event_id = %s',
            $this->_db->makeQueryString($status),
            $this->_db->makeQueryString($platform),
            $jobOrderID > 0 ? $this->_db->makeQueryInteger($jobOrderID) : 'NULL',
            $candidateID > 0 ? $this->_db->makeQueryInteger($candidateID) : 'NULL',
            $this->_db->makeQueryString(substr((string) $errorCode, 0, 64)),
            in_array($status, array('imported', 'duplicate', 'review', 'error'), true) ? 'NOW()' : 'NULL',
            $this->_db->makeQueryInteger($eventID)
        );
        try
        {
            $updated = $this->_db->query($sql);
            $affectedRows = (int) $this->_db->getAffectedRows();
        }
        catch (Throwable $exception)
        {
            throw new RuntimeException('event_terminal_write_failed', 0, $exception);
        }
        if (!$updated || $affectedRows !== 1)
        {
            throw new RuntimeException('event_terminal_write_failed');
        }
    }

    private function finishRun($runID, $status, $counts, $errorCode)
    {
        $sql = sprintf(
            'UPDATE nesp_board_intake_run SET status_key = %s,
                queued_count = %s, imported_count = %s, duplicate_count = %s,
                review_count = %s, failed_count = %s, error_code = %s,
                completed_at = NOW(), date_modified = NOW() WHERE run_id = %s',
            $this->_db->makeQueryString($status),
            $this->_db->makeQueryInteger($counts['queued']),
            $this->_db->makeQueryInteger($counts['imported']),
            $this->_db->makeQueryInteger($counts['duplicates']),
            $this->_db->makeQueryInteger($counts['review']),
            $this->_db->makeQueryInteger($counts['failed']),
            $this->_db->makeQueryString($errorCode),
            $this->_db->makeQueryInteger($runID)
        );
        try
        {
            return $this->_db->query($sql) && (int) $this->_db->getAffectedRows() === 1;
        }
        catch (Throwable $exception)
        {
            return false;
        }
    }

    private function reconcileProviderPages($runID, $nowEpoch)
    {
        $nowEpoch = max(0, (int) $nowEpoch);
        $checkpoint = $this->getReconciliationCheckpoint();
        if ($checkpoint['retry_not_before_epoch'] > $nowEpoch)
        {
            return array(
                'ok' => false,
                'error' => 'missive_rate_limited',
                'retry_after_seconds' => $checkpoint['retry_not_before_epoch'] - $nowEpoch,
                'backoff_active' => true
            );
        }

        if ($checkpoint['scan_high_water_epoch'] === 0)
        {
            $scanHighWaterEpoch = max($checkpoint['high_water_epoch'], $nowEpoch);
            if (!$this->startReconciliationScan($checkpoint['high_water_epoch'], $scanHighWaterEpoch, $runID))
            {
                throw new RuntimeException('reconciliation_checkpoint_write_failed');
            }
            $checkpoint = $this->getReconciliationCheckpoint();
        }

        while (true)
        {
            $conversationCount = count($checkpoint['conversation_page']);
            if ($checkpoint['conversation_page_index'] >= $conversationCount)
            {
                if (!empty($checkpoint['conversation_page_complete']))
                {
                    if (!$this->completeReconciliationScan($checkpoint, $runID))
                    {
                        throw new RuntimeException('reconciliation_checkpoint_write_failed');
                    }
                    return array('ok' => true, 'error' => '');
                }

                $conversationPage = $this->_inbox->discoverConversationPage(
                    $checkpoint['high_water_epoch'],
                    $checkpoint['conversation_until_epoch'] > 0
                        ? $checkpoint['conversation_until_epoch']
                        : null
                );
                if (empty($conversationPage['ok']))
                {
                    $this->persistProviderBackoff($conversationPage, $runID);
                    return $conversationPage;
                }
                $conversationIDs = isset($conversationPage['conversation_ids'])
                    && is_array($conversationPage['conversation_ids'])
                    ? array_values($conversationPage['conversation_ids'])
                    : null;
                if ($conversationIDs === null
                    || (!$conversationIDs && empty($conversationPage['complete'])))
                {
                    return array('ok' => false, 'error' => 'invalid_missive_response');
                }
                if (!$this->persistConversationPage($conversationPage, $runID))
                {
                    throw new RuntimeException('reconciliation_checkpoint_write_failed');
                }
                $checkpoint = $this->getReconciliationCheckpoint();
                continue;
            }

            $conversationID = $checkpoint['conversation_page'][$checkpoint['conversation_page_index']];
            $messagePage = $this->_inbox->discoverConversationMessagePage(
                $conversationID,
                $checkpoint['high_water_epoch'],
                $checkpoint['message_until_epoch'] > 0
                    ? $checkpoint['message_until_epoch']
                    : null
            );
            if (empty($messagePage['ok']))
            {
                $this->persistProviderBackoff($messagePage, $runID);
                return $messagePage;
            }
            if (!isset($messagePage['events']) || !is_array($messagePage['events']))
            {
                return array('ok' => false, 'error' => 'invalid_missive_response');
            }
            foreach ($messagePage['events'] as $discoveredEvent)
            {
                $queued = $this->queueWebhookEvent($discoveredEvent);
                if (empty($queued['ok']))
                {
                    throw new RuntimeException('Reconciled event could not be queued.');
                }
            }
            if (!$this->persistMessagePage($checkpoint, $messagePage, $runID))
            {
                throw new RuntimeException('reconciliation_checkpoint_write_failed');
            }
            $checkpoint = $this->getReconciliationCheckpoint();
        }
    }

    private function getReconciliationCheckpoint()
    {
        $checkpoint = $this->_db->getAssoc(
            'SELECT high_water_epoch, scan_high_water_epoch, conversation_until_epoch,
                    conversation_page_json, conversation_page_index,
                    conversation_page_complete, message_until_epoch,
                    retry_not_before_epoch, last_run_id
             FROM nesp_board_intake_checkpoint
             WHERE provider_key = "missive" LIMIT 1'
        );
        if (!is_array($checkpoint) || !array_key_exists('high_water_epoch', $checkpoint))
        {
            throw new RuntimeException('reconciliation_checkpoint_missing');
        }

        $conversationPage = json_decode((string) $checkpoint['conversation_page_json'], true);
        if (!is_array($conversationPage) || !array_is_list($conversationPage))
        {
            throw new RuntimeException('reconciliation_checkpoint_corrupt');
        }
        foreach ($conversationPage as $conversationID)
        {
            if (!is_string($conversationID) || $conversationID === '' || strlen($conversationID) > 128)
            {
                throw new RuntimeException('reconciliation_checkpoint_corrupt');
            }
        }
        $pageIndex = max(0, (int) $checkpoint['conversation_page_index']);
        if ($pageIndex > count($conversationPage))
        {
            throw new RuntimeException('reconciliation_checkpoint_corrupt');
        }

        return array(
            'high_water_epoch' => max(0, (int) $checkpoint['high_water_epoch']),
            'scan_high_water_epoch' => max(0, (int) $checkpoint['scan_high_water_epoch']),
            'conversation_until_epoch' => max(0, (int) $checkpoint['conversation_until_epoch']),
            'conversation_page' => $conversationPage,
            'conversation_page_index' => $pageIndex,
            'conversation_page_complete' => (int) $checkpoint['conversation_page_complete'] === 1,
            'message_until_epoch' => max(0, (int) $checkpoint['message_until_epoch']),
            'retry_not_before_epoch' => max(0, (int) $checkpoint['retry_not_before_epoch']),
            'last_run_id' => max(0, (int) $checkpoint['last_run_id'])
        );
    }

    private function startReconciliationScan($expectedHighWaterEpoch, $scanHighWaterEpoch, $runID)
    {
        return $this->writeCheckpoint(sprintf(
            'UPDATE nesp_board_intake_checkpoint
             SET scan_high_water_epoch = %s, conversation_until_epoch = NULL,
                 conversation_page_json = "[]", conversation_page_index = 0,
                 conversation_page_complete = 0, message_until_epoch = NULL,
                 retry_not_before_epoch = 0, last_run_id = %s, date_modified = NOW()
             WHERE provider_key = "missive" AND high_water_epoch = %s
                AND scan_high_water_epoch = 0',
            $this->_db->makeQueryInteger($scanHighWaterEpoch),
            $this->_db->makeQueryInteger((int) $runID),
            $this->_db->makeQueryInteger($expectedHighWaterEpoch)
        ));
    }

    private function persistConversationPage($page, $runID)
    {
        $conversationIDs = array_values($page['conversation_ids']);
        $nextUntil = isset($page['next_until']) && (int) $page['next_until'] > 0
            ? $this->_db->makeQueryInteger((int) $page['next_until'])
            : 'NULL';
        return $this->writeCheckpoint(sprintf(
            'UPDATE nesp_board_intake_checkpoint
             SET conversation_until_epoch = %s, conversation_page_json = %s,
                 conversation_page_index = 0, conversation_page_complete = %s,
                 message_until_epoch = NULL, retry_not_before_epoch = 0,
                 last_run_id = %s, date_modified = NOW()
             WHERE provider_key = "missive" AND scan_high_water_epoch > 0',
            $nextUntil,
            $this->_db->makeQueryString(json_encode($conversationIDs)),
            !empty($page['complete']) ? '1' : '0',
            $this->_db->makeQueryInteger((int) $runID)
        ));
    }

    private function persistMessagePage($checkpoint, $page, $runID)
    {
        $complete = !empty($page['complete']);
        $nextUntil = isset($page['next_until']) ? (int) $page['next_until'] : 0;
        if (!$complete && ($nextUntil <= 0
            || ($checkpoint['message_until_epoch'] > 0
                && $nextUntil >= $checkpoint['message_until_epoch'])))
        {
            return false;
        }

        return $this->writeCheckpoint(sprintf(
            'UPDATE nesp_board_intake_checkpoint
             SET conversation_page_index = %s, message_until_epoch = %s,
                 retry_not_before_epoch = 0, last_run_id = %s, date_modified = NOW()
             WHERE provider_key = "missive" AND scan_high_water_epoch = %s
                AND conversation_page_index = %s',
            $this->_db->makeQueryInteger(
                $complete
                    ? $checkpoint['conversation_page_index'] + 1
                    : $checkpoint['conversation_page_index']
            ),
            $complete ? 'NULL' : $this->_db->makeQueryInteger($nextUntil),
            $this->_db->makeQueryInteger((int) $runID),
            $this->_db->makeQueryInteger($checkpoint['scan_high_water_epoch']),
            $this->_db->makeQueryInteger($checkpoint['conversation_page_index'])
        ));
    }

    private function persistProviderBackoff($result, $runID)
    {
        if (!isset($result['error']) || $result['error'] !== 'missive_rate_limited')
        {
            return;
        }
        $delay = isset($result['retry_after_seconds'])
            ? max(1, min(86400, (int) $result['retry_after_seconds']))
            : 60;
        $rateLimitedAt = isset($result['rate_limited_at_epoch'])
            && is_numeric($result['rate_limited_at_epoch'])
            && (int) $result['rate_limited_at_epoch'] > 0
            ? (int) $result['rate_limited_at_epoch']
            : time();
        if (!$this->writeCheckpoint(sprintf(
            'UPDATE nesp_board_intake_checkpoint
             SET retry_not_before_epoch = %s, last_run_id = %s, date_modified = NOW()
             WHERE provider_key = "missive" AND scan_high_water_epoch > 0',
            $this->_db->makeQueryInteger($rateLimitedAt + $delay),
            $this->_db->makeQueryInteger((int) $runID)
        )))
        {
            throw new RuntimeException('reconciliation_checkpoint_write_failed');
        }
    }

    private function completeReconciliationScan($checkpoint, $runID)
    {
        return $this->writeCheckpoint(sprintf(
            'UPDATE nesp_board_intake_checkpoint
             SET high_water_epoch = %s, scan_high_water_epoch = 0,
                 conversation_until_epoch = NULL, conversation_page_json = "[]",
                 conversation_page_index = 0, conversation_page_complete = 0,
                 message_until_epoch = NULL, retry_not_before_epoch = 0,
                 last_run_id = %s, date_modified = NOW()
             WHERE provider_key = "missive" AND high_water_epoch = %s
                AND scan_high_water_epoch = %s',
            $this->_db->makeQueryInteger($checkpoint['scan_high_water_epoch']),
            $this->_db->makeQueryInteger((int) $runID),
            $this->_db->makeQueryInteger($checkpoint['high_water_epoch']),
            $this->_db->makeQueryInteger($checkpoint['scan_high_water_epoch'])
        ));
    }

    private function writeCheckpoint($sql)
    {
        try
        {
            return $this->_db->query($sql) && (int) $this->_db->getAffectedRows() === 1;
        }
        catch (Throwable $exception)
        {
            return false;
        }
    }

    private function tableExists($table)
    {
        $result = $this->_db->getAssoc(sprintf(
            'SHOW TABLES LIKE %s', $this->_db->makeQueryString($table)
        ));
        return !empty($result);
    }

    private function scalarValue($value)
    {
        if (is_array($value))
        {
            return array_key_exists(0, $value) ? $value[0] : null;
        }
        return $value;
    }
}
