<?php

include_once(LEGACY_ROOT . '/lib/Candidates.php');
include_once(LEGACY_ROOT . '/lib/Pipelines.php');
include_once(LEGACY_ROOT . '/lib/NESPWorkflow.php');

/**
 * Controlled staging and import service for external board applicants.
 *
 * This intentionally does not use the generic CSV importer. Rows are staged
 * with an explicit platform, source label, and job order before any candidate
 * or pipeline write is possible.
 */
class BoardApplicantIntake
{
    const DEFAULT_JOB_ORDER_ID = 41001;
    const MAX_CSV_BYTES = 2097152;
    const STAGING_RETENTION_DAYS = 30;

    private $_db;

    public function __construct($db = null)
    {
        $this->_db = $db ?: DatabaseConnection::getInstance();
    }

    public static function allowedJobOrders()
    {
        return array(
            self::DEFAULT_JOB_ORDER_ID => 'Part-Time Customer Service Representative',
            41002 => 'Staff Photographer'
        );
    }

    public static function allowedPlatforms()
    {
        return array('indeed', 'linkedin', 'craigslist', 'masshire', 'handshake', 'other');
    }

    public static function canonicalSourceLabel($platform, $sourceLabel)
    {
        $platform = strtolower(trim((string) $platform));
        $sourceLabel = trim((string) $sourceLabel);
        if (!in_array($platform, self::allowedPlatforms(), true) || $sourceLabel === '')
        {
            return '';
        }

        $displayNames = array(
            'indeed' => 'Indeed',
            'linkedin' => 'LinkedIn',
            'craigslist' => 'Craigslist',
            'masshire' => 'MassHire',
            'handshake' => 'Handshake',
            'other' => 'Other'
        );
        $expected = 'NESP Ad: ' . $displayNames[$platform];
        return strcasecmp($sourceLabel, $expected) === 0 ? $expected : '';
    }

    public static function importIdempotencyKey($platform, $externalID)
    {
        $platform = strtolower(trim((string) $platform));
        $externalID = trim((string) $externalID);
        if ($platform === '' || $externalID === '')
        {
            return null;
        }

        return $platform . ':' . $externalID;
    }

    public static function batchDuplicateRowIDs($rows)
    {
        $emailRows = array();
        $nameRows = array();
        $externalRows = array();
        foreach ((array) $rows as $row)
        {
            if (($row['validation_status'] ?? '') !== 'valid')
            {
                continue;
            }
            $emailRows[strtolower(trim($row['email']))][] = (int) $row['intake_row_id'];
            $nameKey = strtolower(trim($row['first_name'])) . "\0" . strtolower(trim($row['last_name']));
            $nameRows[$nameKey][] = (int) $row['intake_row_id'];
            if (!empty($row['external_id']))
            {
                $externalRows[strtolower(trim($row['external_id']))][] = (int) $row['intake_row_id'];
            }
        }

        $duplicateIDs = array();
        foreach ((array) $rows as $row)
        {
            if (($row['validation_status'] ?? '') !== 'valid')
            {
                continue;
            }
            $emailKey = strtolower(trim($row['email']));
            $nameKey = strtolower(trim($row['first_name'])) . "\0" . strtolower(trim($row['last_name']));
            $externalKey = strtolower(trim((string) ($row['external_id'] ?? '')));
            if (count($emailRows[$emailKey]) > 1 || count($nameRows[$nameKey]) > 1 || ($externalKey !== '' && count($externalRows[$externalKey]) > 1))
            {
                $duplicateIDs[] = (int) $row['intake_row_id'];
            }
        }

        return $duplicateIDs;
    }

    /**
     * Parse a deliberately narrow CSV shape without retaining raw rows.
     *
     * Accepted columns: external_id, first_name, last_name, email, phone.
     * LinkedIn may omit email when external_id is present.
     * Resume and attachment URLs are rejected rather than silently stored.
     */
    public static function parseCsv($contents, $platform, $jobOrderID, $sourceLabel)
    {
        $platform = strtolower(trim((string) $platform));
        $jobOrderID = (int) $jobOrderID;
        $sourceLabel = self::canonicalSourceLabel($platform, $sourceLabel);

        $errors = array();
        if (!in_array($platform, self::allowedPlatforms(), true))
        {
            $errors[] = 'Select a supported board.';
        }
        if (!isset(self::allowedJobOrders()[$jobOrderID]))
        {
            $errors[] = 'Select an approved job order.';
        }
        if ($sourceLabel === '')
        {
            $errors[] = 'Use the matching source label, such as NESP Ad: Indeed.';
        }
        if (strlen((string) $contents) > self::MAX_CSV_BYTES)
        {
            $errors[] = 'CSV exceeds the 2 MB review limit.';
        }
        if ($errors)
        {
            return array('rows' => array(), 'errors' => $errors);
        }

        $handle = fopen('php://temp', 'r+');
        fwrite($handle, (string) $contents);
        rewind($handle);
        $headers = fgetcsv($handle, 0, ',', '"', '\\');
        if (!is_array($headers))
        {
            return array('rows' => array(), 'errors' => array('CSV is empty.'));
        }

        $headers = array_map(function ($header) {
            return strtolower(trim((string) $header));
        }, $headers);
        $allowed = array('external_id', 'first_name', 'last_name', 'email', 'phone');
        $unsupported = array_diff(array_filter($headers), $allowed);
        if ($unsupported)
        {
            return array(
                'rows' => array(),
                'errors' => array('Unsupported columns: ' . implode(', ', $unsupported) . '. Remove resume or attachment URLs; this tool does not import them.')
            );
        }

        $requiredFields = array('external_id', 'first_name', 'last_name');
        if ($platform !== 'linkedin')
        {
            $requiredFields[] = 'email';
        }
        foreach ($requiredFields as $required)
        {
            if (!in_array($required, $headers, true))
            {
                $errors[] = 'CSV must include ' . $required . '.';
            }
        }
        if ($errors)
        {
            return array('rows' => array(), 'errors' => $errors);
        }

        $rows = array();
        $sourceRowNumber = 1;
        while (($values = fgetcsv($handle, 0, ',', '"', '\\')) !== false)
        {
            $sourceRowNumber++;
            if (count($values) === 1 && trim((string) $values[0]) === '')
            {
                continue;
            }

            $mapped = array();
            foreach ($headers as $index => $header)
            {
                $mapped[$header] = isset($values[$index]) ? trim((string) $values[$index]) : '';
            }

            $rowErrors = array();
            $email = isset($mapped['email']) ? strtolower(trim($mapped['email'])) : '';
            $externalID = isset($mapped['external_id']) && $mapped['external_id'] !== ''
                ? $mapped['external_id']
                : null;
            if ($mapped['first_name'] === '' || $mapped['last_name'] === '')
            {
                $rowErrors[] = 'First and last name are required.';
            }
            if ($email === '')
            {
                if ($platform !== 'linkedin' || empty($externalID))
                {
                    $rowErrors[] = 'A valid email is required.';
                }
            }
            elseif (!filter_var($email, FILTER_VALIDATE_EMAIL))
            {
                $rowErrors[] = 'A valid email is required.';
            }

            if ($externalID === null)
            {
                $rowErrors[] = 'external_id is required for exactly-once import.';
            }
            $rows[] = array(
                'source_row_number' => $sourceRowNumber,
                'external_id' => $externalID,
                'first_name' => $mapped['first_name'],
                'last_name' => $mapped['last_name'],
                'email' => $email,
                'phone' => isset($mapped['phone']) ? $mapped['phone'] : '',
                'row_hash' => hash('sha256', implode('|', array(
                    $platform, $jobOrderID, $externalID ?: '', $mapped['first_name'],
                    $mapped['last_name'], $email, isset($mapped['phone']) ? $mapped['phone'] : ''
                ))),
                'idempotency_key' => self::importIdempotencyKey($platform, $externalID),
                'validation_status' => $rowErrors ? 'invalid' : 'valid',
                'validation_errors' => $rowErrors,
                'duplicate_status' => 'unchecked',
                'duplicate_candidate_id' => null,
                'review_status' => 'pending'
            );
        }
        fclose($handle);

        if (!$rows)
        {
            $errors[] = 'CSV contains no applicant rows.';
        }

        return array('rows' => $rows, 'errors' => $errors);
    }

    public function createBatch($actorUserID, $platform, $jobOrderID, $sourceLabel, $rows, $sourceChecksum)
    {
        foreach ($rows as $row)
        {
            if (empty($row['external_id']) || empty($row['idempotency_key']))
            {
                return -1;
            }
        }

        $transactionStarted = $this->_db->beginTransaction();
        try
        {
            $sql = sprintf(
                'INSERT INTO nesp_board_intake_batch
                    (platform_key, joborder_id, source_label, source_checksum, status_key, row_count, created_by_user_id, expires_at, date_created, date_modified)
                 VALUES (%s, %s, %s, %s, "review", %s, %s, DATE_ADD(NOW(), INTERVAL %d DAY), NOW(), NOW())',
                $this->_db->makeQueryString($platform),
                $this->_db->makeQueryInteger($jobOrderID),
                $this->_db->makeQueryString($sourceLabel),
                $this->_db->makeQueryString($sourceChecksum),
                $this->_db->makeQueryInteger(count($rows)),
                $this->_db->makeQueryInteger($actorUserID),
                self::STAGING_RETENTION_DAYS
            );
            if (!$this->_db->query($sql))
            {
                throw new RuntimeException('Review batch creation failed.');
            }
            $batchID = $this->_db->getLastInsertID();

            foreach ($rows as $row)
            {
                $sql = sprintf(
                    'INSERT INTO nesp_board_intake_row
                        (batch_id, platform_key, source_row_number, external_id, first_name, last_name, email, phone, row_hash, idempotency_key, validation_status, validation_message, duplicate_status, review_status, date_created, date_modified)
                     VALUES (%s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, "unchecked", "pending", NOW(), NOW())',
                    $this->_db->makeQueryInteger($batchID),
                    $this->_db->makeQueryString($platform),
                    $this->_db->makeQueryInteger($row['source_row_number']),
                    $row['external_id'] === null ? 'NULL' : $this->_db->makeQueryString($row['external_id']),
                    $this->_db->makeQueryString($row['first_name']),
                    $this->_db->makeQueryString($row['last_name']),
                    $this->_db->makeQueryString($row['email']),
                    $this->_db->makeQueryString($row['phone']),
                    $this->_db->makeQueryString($row['row_hash']),
                    $row['idempotency_key'] === null ? 'NULL' : $this->_db->makeQueryString($row['idempotency_key']),
                    $this->_db->makeQueryString($row['validation_status']),
                    $this->_db->makeQueryString(implode(' ', $row['validation_errors']))
                );
                if (!$this->_db->query($sql))
                {
                    throw new RuntimeException('Review row creation failed.');
                }
            }

            if ($transactionStarted)
            {
                $this->_db->commitTransaction();
            }
            return (int) $batchID;
        }
        catch (Throwable $e)
        {
            if ($transactionStarted)
            {
                $this->_db->rollbackTransaction();
            }
            return -1;
        }
    }

    public function applyDuplicateChecks($batchID)
    {
        $rows = $this->getRows($batchID);
        $batchDuplicateIDs = array_flip(self::batchDuplicateRowIDs($rows));

        foreach ($rows as $row)
        {
            if ($row['validation_status'] !== 'valid')
            {
                continue;
            }

            $duplicateID = 0;
            $duplicateStatus = isset($batchDuplicateIDs[(int) $row['intake_row_id']]) ? 'batch_duplicate' : 'none';
            if ($duplicateStatus === 'none')
            {
                $duplicateID = $this->findDuplicateCandidate($row);
                $duplicateStatus = $duplicateID ? 'candidate_duplicate' : 'none';
            }
            if ($duplicateStatus === 'none' && $row['external_id'] !== null && $row['external_id'] !== '')
            {
                $duplicateID = $this->findImportedIdempotencyCandidate($row['platform_key'], $row['external_id']);
                $duplicateStatus = $duplicateID ? 'already_imported' : 'none';
            }

            $sql = sprintf(
                'UPDATE nesp_board_intake_row SET duplicate_status = %s, duplicate_candidate_id = %s, date_modified = NOW() WHERE intake_row_id = %s',
                $this->_db->makeQueryString($duplicateStatus),
                $duplicateID ? $this->_db->makeQueryInteger($duplicateID) : 'NULL',
                $this->_db->makeQueryInteger($row['intake_row_id'])
            );
            $this->_db->query($sql);
        }
    }

    public function recordPreview($batchID, $actorUserID)
    {
        $this->purgeExpiredStaging();
        $batch = $this->getBatch($batchID);
        if (empty($batch) || $batch['status_key'] !== 'review')
        {
            throw new RuntimeException('Batch is not available for preview.');
        }

        $sql = sprintf(
            'UPDATE nesp_board_intake_batch
                SET previewed_at = NOW(), previewed_by_user_id = %s, date_modified = NOW()
             WHERE batch_id = %s AND status_key = "review"',
            $this->_db->makeQueryInteger($actorUserID),
            $this->_db->makeQueryInteger($batchID)
        );
        if (!$this->_db->query($sql))
        {
            throw new RuntimeException('Preview confirmation could not be recorded.');
        }
    }

    public function approveRows($batchID, $rowIDs, $actorUserID)
    {
        $batch = $this->getBatch($batchID);
        if (empty($batch) || $batch['status_key'] !== 'review' || empty($batch['previewed_at']) || empty($batch['previewed_by_user_id']))
        {
            throw new RuntimeException('Record the complete preview before approving rows.');
        }

        $rowIDs = array_values(array_unique(array_map('intval', (array) $rowIDs)));
        if (!$rowIDs)
        {
            $rowIDs = array();
        }

        $resetSQL = sprintf(
            'UPDATE nesp_board_intake_row
                SET review_status = "pending", date_modified = NOW()
             WHERE batch_id = %s AND review_status = "approved"',
            $this->_db->makeQueryInteger($batchID)
        );
        if (!$this->_db->query($resetSQL))
        {
            throw new RuntimeException('Existing approvals could not be reset safely.');
        }

        $approved = 0;
        foreach ($rowIDs as $rowID)
        {
            $sql = sprintf(
                'UPDATE nesp_board_intake_row
                    SET review_status = "approved", date_modified = NOW()
                 WHERE intake_row_id = %s AND batch_id = %s
                   AND validation_status = "valid" AND duplicate_status = "none"
                   AND idempotency_key IS NOT NULL AND external_id IS NOT NULL',
                $this->_db->makeQueryInteger($rowID),
                $this->_db->makeQueryInteger($batchID)
            );
            if ($this->_db->query($sql))
            {
                $approved += (int) $this->_db->getAffectedRows();
            }
        }

        if ($approved > 0)
        {
            $sql = sprintf(
                'UPDATE nesp_board_intake_batch
                    SET approved_at = NOW(), approved_by_user_id = %s, date_modified = NOW()
                 WHERE batch_id = %s AND status_key = "review"',
                $this->_db->makeQueryInteger($actorUserID),
                $this->_db->makeQueryInteger($batchID)
            );
            if (!$this->_db->query($sql))
            {
                throw new RuntimeException('Approval record could not be saved.');
            }
        }
        else
        {
            $sql = sprintf(
                'UPDATE nesp_board_intake_batch
                    SET approved_at = NULL, approved_by_user_id = NULL, date_modified = NOW()
                 WHERE batch_id = %s AND status_key = "review"',
                $this->_db->makeQueryInteger($batchID)
            );
            if (!$this->_db->query($sql))
            {
                throw new RuntimeException('Approval reset could not be saved.');
            }
        }

        return $approved;
    }

    public function importApprovedRows($actorUserID, $batchID)
    {
        $batch = $this->getBatch($batchID);
        if (empty($batch) || $batch['status_key'] !== 'review' || empty($batch['previewed_at']) || empty($batch['approved_at']) || empty($batch['previewed_by_user_id']) || empty($batch['approved_by_user_id']))
        {
            throw new RuntimeException('Complete the recorded preview and row approval before importing.');
        }

        $rows = $this->getRows($batchID, 'approved');
        if (!$rows)
        {
            return array('imported' => 0, 'skipped' => 0, 'failed' => 0);
        }

        $transactionStarted = $this->_db->beginTransaction();
        $imported = 0;
        try
        {
            foreach ($rows as $row)
            {
                if ($row['duplicate_status'] !== 'none')
                {
                    continue;
                }

                if ($this->findDuplicateCandidate($row))
                {
                    throw new RuntimeException('A matching candidate appeared after review. Re-run the review batch.');
                }
                if (empty($row['external_id']) || empty($row['idempotency_key']))
                {
                    throw new RuntimeException('Every imported row must have an external applicant ID.');
                }
                if ($this->findImportedIdempotencyCandidate($row['platform_key'], $row['external_id']))
                {
                    throw new RuntimeException('This external applicant ID was already imported.');
                }

                if ($row['idempotency_key'] !== null)
                {
                    $identitySQL = sprintf(
                        'INSERT INTO nesp_board_intake_identity (platform_key, external_id, intake_row_id, date_created) VALUES (%s, %s, %s, NOW())',
                        $this->_db->makeQueryString($row['platform_key']),
                        $this->_db->makeQueryString($row['external_id']),
                        $this->_db->makeQueryInteger($row['intake_row_id'])
                    );
                    if (!$this->_db->query($identitySQL))
                    {
                        throw new RuntimeException('This external applicant ID was already claimed.');
                    }
                }

                $candidates = new Candidates();
                $candidateNotes = 'Board intake: ' . $batch['platform_key'];
                $contactDetailsRequired = $batch['platform_key'] === 'linkedin' && trim($row['email']) === '';
                if ($contactDetailsRequired)
                {
                    $candidateNotes .= '. Contact details required before any questionnaire or outreach.';
                }
                $candidateID = $candidates->add(
                    $row['first_name'], '', $row['last_name'], $row['email'], '',
                    $row['phone'], '', '', '', '', '', '', '', $batch['source_label'],
                    '', null, '', false, '', '', $candidateNotes, '',
                    '', $actorUserID, $actorUserID
                );
                if ($candidateID <= 0)
                {
                    throw new RuntimeException('Candidate creation failed.');
                }

                if ($row['idempotency_key'] !== null)
                {
                    $identityUpdate = sprintf(
                        'UPDATE nesp_board_intake_identity SET candidate_id = %s WHERE intake_row_id = %s',
                        $this->_db->makeQueryInteger($candidateID),
                        $this->_db->makeQueryInteger($row['intake_row_id'])
                    );
                    if (!$this->_db->query($identityUpdate))
                    {
                        throw new RuntimeException('External applicant identity update failed.');
                    }
                }

                $pipelines = new Pipelines();
                if (!$pipelines->add($candidateID, (int) $batch['joborder_id'], $actorUserID))
                {
                    throw new RuntimeException('Pipeline attachment failed or already exists.');
                }

                $workflow = new NESPWorkflow($this->_db);
                $workflowSummary = $contactDetailsRequired
                    ? 'Contact details required before any questionnaire or outreach. New LinkedIn application awaiting human review.'
                    : null;
                $workflowNextAction = $contactDetailsRequired ? 'Collect contact details' : null;
                if (!$workflow->ensureCandidateWorkflowRow(
                    $candidateID,
                    (int) $batch['joborder_id'],
                    $actorUserID,
                    $batch['source_label'],
                    $workflowSummary,
                    $workflowNextAction
                ))
                {
                    throw new RuntimeException('Workflow routing failed.');
                }

                // An approved board record with contact details can be prepared
                // for Craig's review immediately. This produces a hashed,
                // role-specific questionnaire link only; it never sends it.
                if (!$contactDetailsRequired)
                {
                    if (!$workflow->prepareQuestionnaireForHumanReview(
                        $candidateID,
                        (int) $batch['joborder_id'],
                        $actorUserID,
                        'New approved ' . $batch['platform_key'] . ' application. A role-specific secure questionnaire link is ready for human sending.',
                        'new'
                    ))
                    {
                        throw new RuntimeException('Questionnaire review routing failed.');
                    }
                }

                $sql = sprintf(
                    'UPDATE nesp_board_intake_row
                        SET review_status = "imported", candidate_id = %s,
                            first_name = "", last_name = "", email = "", phone = "",
                            pii_redacted_at = NOW(), date_modified = NOW()
                     WHERE intake_row_id = %s AND review_status = "approved"',
                    $this->_db->makeQueryInteger($candidateID),
                    $this->_db->makeQueryInteger($row['intake_row_id'])
                );
                if (!$this->_db->query($sql) || $this->_db->getAffectedRows() !== 1)
                {
                    throw new RuntimeException('Intake row update failed.');
                }
                $imported++;
            }

            $sql = sprintf(
                'UPDATE nesp_board_intake_batch SET status_key = "imported", imported_count = %s, date_modified = NOW() WHERE batch_id = %s',
                $this->_db->makeQueryInteger($imported),
                $this->_db->makeQueryInteger($batchID)
            );
            if (!$this->_db->query($sql))
            {
                throw new RuntimeException('Batch update failed.');
            }

            if ($transactionStarted)
            {
                $this->_db->commitTransaction();
            }
        }
        catch (Throwable $e)
        {
            if ($transactionStarted)
            {
                $this->_db->rollbackTransaction();
            }
            throw $e;
        }

        return array('imported' => $imported, 'skipped' => 0, 'failed' => 0);
    }

    public function getBatch($batchID)
    {
        $this->purgeExpiredStaging();
        $sql = sprintf('SELECT * FROM nesp_board_intake_batch WHERE batch_id = %s', $this->_db->makeQueryInteger($batchID));
        return $this->_db->getAssoc($sql);
    }

    public function getRows($batchID, $reviewStatus = null)
    {
        $this->purgeExpiredStaging();
        $where = 'batch_id = ' . $this->_db->makeQueryInteger($batchID);
        if ($reviewStatus !== null)
        {
            $where .= ' AND review_status = ' . $this->_db->makeQueryString($reviewStatus);
        }
        return $this->_db->getAllAssoc('SELECT * FROM nesp_board_intake_row WHERE ' . $where . ' ORDER BY source_row_number');
    }

    public function getOpenBatches()
    {
        $this->purgeExpiredStaging();
        return $this->_db->getAllAssoc('SELECT * FROM nesp_board_intake_batch WHERE status_key = "review" ORDER BY date_created DESC');
    }

    public function purgeExpiredStaging()
    {
        $queries = array(
            'DELETE intake_row FROM nesp_board_intake_row intake_row
             INNER JOIN nesp_board_intake_batch intake_batch ON intake_batch.batch_id = intake_row.batch_id
             WHERE intake_batch.status_key = "review" AND intake_batch.expires_at < NOW()',
            'DELETE FROM nesp_board_intake_batch WHERE status_key = "review" AND expires_at < NOW()'
        );

        foreach ($queries as $query)
        {
            // Suppress raw database output, then fail closed if retention cleanup cannot complete.
            if (!$this->_db->query($query, true))
            {
                throw new RuntimeException('Staging retention cleanup failed; intake review is unavailable until cleanup succeeds.');
            }
        }

        return true;
    }

    private function findDuplicateCandidate($row)
    {
        $email = strtolower(trim($row['email']));
        $first = $this->_db->makeQueryString($row['first_name']);
        $last = $this->_db->makeQueryString($row['last_name']);
        $emailClause = $email === ''
            ? '1 = 0'
            : sprintf(
                'LOWER(TRIM(email1)) = LOWER(TRIM(%s)) OR LOWER(TRIM(email2)) = LOWER(TRIM(%s))',
                $this->_db->makeQueryString($email),
                $this->_db->makeQueryString($email)
            );
        $sql = sprintf(
            'SELECT candidate_id FROM candidate
             WHERE (%s)
                OR (LOWER(TRIM(first_name)) = LOWER(TRIM(%s)) AND LOWER(TRIM(last_name)) = LOWER(TRIM(%s)))
             ORDER BY candidate_id ASC LIMIT 1',
            $emailClause, $first, $last
        );
        return (int) $this->_db->getColumn($sql, 0, 0);
    }

    private function findImportedIdempotencyCandidate($platform, $externalID)
    {
        $sql = sprintf(
            'SELECT candidate_id FROM nesp_board_intake_identity WHERE platform_key = %s AND external_id = %s AND candidate_id IS NOT NULL ORDER BY identity_id ASC LIMIT 1',
            $this->_db->makeQueryString($platform),
            $this->_db->makeQueryString($externalID)
        );
        return (int) $this->_db->getColumn($sql, 0, 0);
    }
}
