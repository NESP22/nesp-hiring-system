<?php

include_once(LEGACY_ROOT . '/lib/Candidates.php');
include_once(LEGACY_ROOT . '/lib/Pipelines.php');
include_once(LEGACY_ROOT . '/lib/NESPWorkflow.php');
include_once(LEGACY_ROOT . '/lib/Attachments.php');

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
    const MAX_RESUME_BYTES = 10485760;
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
            41002 => 'Staff Photographer',
            41003 => 'Freelance/Contract Youth Sports Photographer',
            41005 => 'Weekend Table Greeter / Field Assistant'
        );
    }

    public static function allowedPlatforms()
    {
        return array('indeed', 'linkedin', 'craigslist', 'masshire', 'handshake', 'other');
    }

    public static function allowedResumeExtensions()
    {
        return array('pdf', 'doc', 'docx', 'rtf', 'odt');
    }

    public static function validateResumeUpload($file)
    {
        if (!is_array($file) || !isset($file['error']) || is_array($file['error']) ||
            (int) $file['error'] !== UPLOAD_ERR_OK)
        {
            return 'Choose a readable local resume file.';
        }
        if (!isset($file['tmp_name']) || !is_string($file['tmp_name']) || trim($file['tmp_name']) === '')
        {
            return 'Choose a readable local resume file.';
        }

        if (!isset($file['size']) || is_array($file['size']))
        {
            return 'Choose a readable local resume file.';
        }
        $size = isset($file['size']) ? (int) $file['size'] : 0;
        if ($size <= 0)
        {
            return 'The resume file is empty.';
        }
        if ($size > self::MAX_RESUME_BYTES)
        {
            return 'The resume exceeds the 10 MB upload limit.';
        }

        $name = isset($file['name']) && is_string($file['name']) ? $file['name'] : '';
        $extension = strtolower(pathinfo($name, PATHINFO_EXTENSION));
        if (!in_array($extension, self::allowedResumeExtensions(), true))
        {
            return 'Upload a PDF, DOC, DOCX, RTF, or ODT resume.';
        }

        return '';
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

    /**
     * Turn a forwarded board-notification email into the same review-only
     * shape as a CSV export. The caller must still record preview, choose rows,
     * and explicitly import them. No mailbox is polled and no applicant is
     * contacted from this path.
     */
    public static function parseInboxNotification($contents, $platform, $jobOrderID, $sourceLabel)
    {
        $contents = trim((string) $contents);
        if ($contents === '')
        {
            return array('rows' => array(), 'errors' => array('Paste the board notification text first.'));
        }
        if (strlen($contents) > self::MAX_CSV_BYTES)
        {
            return array('rows' => array(), 'errors' => array('Inbox notification exceeds the 2 MB review limit.'));
        }

        $values = array();
        $labels = array(
            'external_id' => array('external id', 'applicant id', 'application id', 'candidate id'),
            'first_name' => array('first name', 'given name'),
            'last_name' => array('last name', 'family name', 'surname'),
            'email' => array('email', 'email address'),
            'phone' => array('phone', 'phone number', 'mobile')
        );

        foreach ($labels as $field => $fieldLabels)
        {
            foreach ($fieldLabels as $label)
            {
                $pattern = '/^\\s*' . preg_quote($label, '/') . '\\s*[:\\-]\\s*(.+?)\\s*$/im';
                if (preg_match($pattern, $contents, $matches))
                {
                    $values[$field] = trim($matches[1]);
                    break;
                }
            }
        }

        if ((!isset($values['first_name']) || !isset($values['last_name'])) && preg_match('/^\\s*(?:applicant|candidate|name)\\s*[:\\-]\\s*(.+?)\\s*$/im', $contents, $matches))
        {
            $parts = preg_split('/\\s+/', trim($matches[1]), 2);
            if (count($parts) === 2)
            {
                $values['first_name'] = isset($values['first_name']) ? $values['first_name'] : $parts[0];
                $values['last_name'] = isset($values['last_name']) ? $values['last_name'] : $parts[1];
            }
        }

        $row = array(
            isset($values['external_id']) ? $values['external_id'] : '',
            isset($values['first_name']) ? $values['first_name'] : '',
            isset($values['last_name']) ? $values['last_name'] : '',
            isset($values['email']) ? $values['email'] : '',
            isset($values['phone']) ? $values['phone'] : ''
        );
        $handle = fopen('php://temp', 'r+');
        fputcsv($handle, array('external_id', 'first_name', 'last_name', 'email', 'phone'));
        fputcsv($handle, $row);
        rewind($handle);
        $csv = stream_get_contents($handle);
        fclose($handle);

        return self::parseCsv($csv, $platform, $jobOrderID, $sourceLabel);
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
            $emailKey = strtolower(trim((string) $row['email']));
            if ($emailKey !== '')
            {
                $emailRows[$emailKey][] = (int) $row['intake_row_id'];
            }
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
            if (($emailKey !== '' && count($emailRows[$emailKey]) > 1) || count($nameRows[$nameKey]) > 1 || ($externalKey !== '' && count($externalRows[$externalKey]) > 1))
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
     * A board may omit email when external_id is present. Such rows remain
     * human-review only until contact details are supplied.
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
                if (empty($externalID))
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
                $contactDetailsRequired = trim($row['email']) === '';
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

                $this->ensureCandidateJobOrderLink(
                    $candidateID,
                    (int) $batch['joborder_id'],
                    $actorUserID
                );

                $workflow = new NESPWorkflow($this->_db);
                $workflowSummary = $contactDetailsRequired
                    ? 'Contact details required before any questionnaire or outreach. New board application awaiting human review.'
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
                // for Craig's review immediately. The link is always hashed;
                // delivery occurs only when the separately confirmed applicant
                // email feature and configured sender are both active.
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

    /**
     * Ensures that an imported candidate can be read back through the same
     * candidate_joborder relation that Candidate Details renders. A successful
     * insert alone is not enough: a missing job order or an unreadable link
     * must stop the import before it is marked complete.
     */
    private function ensureCandidateJobOrderLink($candidateID, $jobOrderID, $actorUserID)
    {
        $candidateID = (int) $candidateID;
        $jobOrderID = (int) $jobOrderID;
        if ($candidateID <= 0 || $jobOrderID <= 0)
        {
            throw new RuntimeException('A valid candidate and approved job order are required.');
        }

        $candidate = $this->_db->getAssoc(sprintf(
            'SELECT candidate_id FROM candidate WHERE candidate_id = %s LIMIT 1',
            $this->_db->makeQueryInteger($candidateID)
        ));
        $jobOrder = $this->_db->getAssoc(sprintf(
            'SELECT joborder_id FROM joborder WHERE joborder_id = %s LIMIT 1',
            $this->_db->makeQueryInteger($jobOrderID)
        ));
        if (empty($candidate) || empty($jobOrder))
        {
            throw new RuntimeException('The approved candidate or job order could not be verified.');
        }

        $link = $this->_db->getAssoc(sprintf(
            'SELECT COUNT(*) AS link_count, MIN(candidate_joborder_id) AS candidate_joborder_id
             FROM candidate_joborder
             WHERE candidate_id = %s AND joborder_id = %s',
            $this->_db->makeQueryInteger($candidateID),
            $this->_db->makeQueryInteger($jobOrderID)
        ));
        $linkCount = isset($link['link_count']) ? (int) $link['link_count'] : 0;
        if ($linkCount > 1)
        {
            throw new RuntimeException('More than one candidate/job-order link was found. Repair stopped safely.');
        }
        if ($linkCount === 0)
        {
            $pipelines = new Pipelines();
            if (!$pipelines->add($candidateID, $jobOrderID, $actorUserID))
            {
                throw new RuntimeException('Candidate job-order attachment failed.');
            }

            $link = $this->_db->getAssoc(sprintf(
                'SELECT COUNT(*) AS link_count, MIN(candidate_joborder_id) AS candidate_joborder_id
                 FROM candidate_joborder
                 WHERE candidate_id = %s AND joborder_id = %s',
                $this->_db->makeQueryInteger($candidateID),
                $this->_db->makeQueryInteger($jobOrderID)
            ));
            $linkCount = isset($link['link_count']) ? (int) $link['link_count'] : 0;
        }

        if ($linkCount !== 1 || empty($link['candidate_joborder_id']))
        {
            throw new RuntimeException('Candidate job-order attachment could not be verified.');
        }

        return (int) $link['candidate_joborder_id'];
    }

    /**
     * Repairs only missing candidate/job-order links for an already imported
     * batch. It never creates candidates, changes contact data, or sends a
     * questionnaire. The existing identity and imported-row checks keep this
     * idempotent and confined to the approved batch.
     */
    public function repairImportedCandidateJobOrderLinks($actorUserID, $batchID)
    {
        $batch = $this->getBatch($batchID);
        if (empty($batch) || $batch['status_key'] !== 'imported')
        {
            throw new RuntimeException('Choose an imported intake batch to repair.');
        }

        $rows = $this->_db->getAllAssoc(sprintf(
            'SELECT intake_row.intake_row_id, intake_row.candidate_id, intake_batch.joborder_id
             FROM nesp_board_intake_row AS intake_row
             INNER JOIN nesp_board_intake_batch AS intake_batch
                ON intake_batch.batch_id = intake_row.batch_id
             INNER JOIN nesp_board_intake_identity AS intake_identity
                ON intake_identity.intake_row_id = intake_row.intake_row_id
               AND intake_identity.candidate_id = intake_row.candidate_id
             WHERE intake_row.batch_id = %s
               AND intake_row.review_status = "imported"
               AND intake_row.candidate_id IS NOT NULL',
            $this->_db->makeQueryInteger($batchID)
        ));
        if (!$rows)
        {
            return array('verified' => 0, 'repaired' => 0);
        }

        $transactionStarted = $this->_db->beginTransaction();
        $verified = 0;
        $repaired = 0;
        try
        {
            foreach ($rows as $row)
            {
                $before = $this->_db->getAssoc(sprintf(
                    'SELECT COUNT(*) AS link_count FROM candidate_joborder
                     WHERE candidate_id = %s AND joborder_id = %s',
                    $this->_db->makeQueryInteger($row['candidate_id']),
                    $this->_db->makeQueryInteger($row['joborder_id'])
                ));
                $this->ensureCandidateJobOrderLink(
                    (int) $row['candidate_id'],
                    (int) $row['joborder_id'],
                    $actorUserID
                );
                $verified++;
                if (empty($before) || (int) $before['link_count'] === 0)
                {
                    $repaired++;
                }
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

        return array('verified' => $verified, 'repaired' => $repaired);
    }

    /**
     * Imports every already-reviewed batch one batch at a time. Each batch keeps
     * its own transaction so a duplicate or failure cannot undo another batch.
     */
    public function importAllApprovedRows($actorUserID)
    {
        $summary = array(
            'imported' => 0,
            'skipped' => 0,
            'failed' => 0,
            'batches' => array()
        );

        foreach ($this->getOpenBatches() as $batch)
        {
            $batchID = (int) $batch['batch_id'];
            if (empty($batch['previewed_at']) || empty($batch['approved_at']) ||
                empty($batch['previewed_by_user_id']) || empty($batch['approved_by_user_id']))
            {
                $summary['batches'][] = array(
                    'batch_id' => $batchID,
                    'status' => 'skipped',
                    'message' => 'Preview and explicit approval are still required.'
                );
                continue;
            }

            // Recheck immediately before import so an earlier batch cannot create a duplicate.
            $this->applyDuplicateChecks($batchID);
            $rows = $this->getRows($batchID, 'approved');
            $eligible = 0;
            foreach ($rows as $row)
            {
                if ($row['validation_status'] === 'valid' && $row['duplicate_status'] === 'none' &&
                    !empty($row['external_id']) && !empty($row['idempotency_key']))
                {
                    $eligible++;
                }
            }

            if ($eligible === 0)
            {
                $summary['skipped'] += count($rows);
                $summary['batches'][] = array(
                    'batch_id' => $batchID,
                    'status' => 'skipped',
                    'message' => 'No currently importable approved rows remain after duplicate checks.'
                );
                continue;
            }

            try
            {
                $result = $this->importApprovedRows($actorUserID, $batchID);
                $summary['imported'] += (int) $result['imported'];
                $summary['skipped'] += (int) $result['skipped'];
                $summary['failed'] += (int) $result['failed'];
                $summary['batches'][] = array(
                    'batch_id' => $batchID,
                    'status' => 'imported',
                    'message' => (int) $result['imported'] . ' applicant(s) imported.'
                );
            }
            catch (Throwable $e)
            {
                $summary['failed'] += $eligible;
                $summary['batches'][] = array(
                    'batch_id' => $batchID,
                    'status' => 'stopped',
                    'message' => 'Import stopped safely: ' . $e->getMessage()
                );
            }
        }

        return $summary;
    }

    /**
     * Confirms that an imported intake row still resolves through its claimed
     * board identity to the same candidate and selected job order.
     */
    public function getConfirmedResumeTarget($batchID, $intakeRowID)
    {
        $batchID = (int) $batchID;
        $intakeRowID = (int) $intakeRowID;
        if ($batchID <= 0 || $intakeRowID <= 0)
        {
            throw new RuntimeException('Choose a specific imported applicant.');
        }

        $sql = sprintf(
            'SELECT
                intake_row.intake_row_id,
                intake_row.candidate_id,
                intake_batch.joborder_id,
                intake_identity.identity_id,
                candidate_joborder.candidate_joborder_id
             FROM nesp_board_intake_row AS intake_row
             INNER JOIN nesp_board_intake_batch AS intake_batch
                ON intake_batch.batch_id = intake_row.batch_id
             INNER JOIN nesp_board_intake_identity AS intake_identity
                ON intake_identity.intake_row_id = intake_row.intake_row_id
               AND intake_identity.platform_key = intake_row.platform_key
               AND intake_identity.external_id = intake_row.external_id
               AND intake_identity.candidate_id = intake_row.candidate_id
             INNER JOIN candidate
                ON candidate.candidate_id = intake_row.candidate_id
             INNER JOIN candidate_joborder
                ON candidate_joborder.candidate_id = intake_row.candidate_id
               AND candidate_joborder.joborder_id = intake_batch.joborder_id
             WHERE intake_row.batch_id = %s
               AND intake_row.intake_row_id = %s
               AND intake_row.review_status = "imported"
               AND intake_batch.status_key = "imported"
               AND intake_row.candidate_id IS NOT NULL
             LIMIT 1',
            $this->_db->makeQueryInteger($batchID),
            $this->_db->makeQueryInteger($intakeRowID)
        );
        $target = $this->_db->getAssoc($sql);
        if (empty($target) || !isset($target['candidate_id']) || !isset($target['joborder_id']) ||
            (int) $target['candidate_id'] <= 0 || (int) $target['joborder_id'] <= 0)
        {
            throw new RuntimeException('Candidate identity and job mapping could not be confirmed.');
        }

        return array(
            'batch_id' => $batchID,
            'intake_row_id' => $intakeRowID,
            'candidate_id' => (int) $target['candidate_id'],
            'joborder_id' => (int) $target['joborder_id']
        );
    }

    /**
     * Attaches one locally uploaded resume without changing candidate fields.
     */
    public function attachResumeUpload($batchID, $intakeRowID, $fileField,
        $attachmentCreator = null, $isUploadedFile = null)
    {
        $target = $this->getConfirmedResumeTarget($batchID, $intakeRowID);
        if (!isset($_FILES[$fileField]))
        {
            throw new RuntimeException('Choose a readable local resume file.');
        }

        $validationError = self::validateResumeUpload($_FILES[$fileField]);
        if ($validationError !== '')
        {
            throw new RuntimeException($validationError);
        }

        $isUploadedFile = $isUploadedFile ?: 'is_uploaded_file';
        if (!call_user_func($isUploadedFile, $_FILES[$fileField]['tmp_name']))
        {
            throw new RuntimeException('Only a local file upload is accepted.');
        }

        $attachmentCreator = $attachmentCreator ?: new AttachmentCreator();
        $created = $attachmentCreator->createFromUpload(
            DATA_ITEM_CANDIDATE,
            $target['candidate_id'],
            $fileField,
            false,
            true
        );
        if (!$created)
        {
            if ($attachmentCreator->duplicatesOccurred())
            {
                throw new RuntimeException('This resume is already attached to the candidate.');
            }
            throw new RuntimeException('The resume could not be attached.');
        }

        $target['attachment_id'] = (int) $attachmentCreator->getAttachmentID();
        return $target;
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

    public function getRecentImportedBatches()
    {
        return $this->_db->getAllAssoc(
            'SELECT * FROM nesp_board_intake_batch WHERE status_key = "imported" ORDER BY date_modified DESC LIMIT 20'
        );
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
