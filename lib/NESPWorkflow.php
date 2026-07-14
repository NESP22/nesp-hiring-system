<?php
/*
 * New England Sports Photo hosted hiring workflow foundation.
 *
 * Phase 2 keeps the hosted workflow human-reviewed and feature-flagged while
 * adding task queues, scoped interviewer views, scorecards, and staffing
 * forecast helpers. External integrations remain disabled by default.
 */

class NESPWorkflow
{
    private $_db;

    public function __construct($db = null)
    {
        $this->_db = ($db === null) ? DatabaseConnection::getInstance() : $db;
    }

    public static function getDefaultFeatureFlags()
    {
        return array(
            array('NESP_WORKFLOW_ENABLED', 'NESP Workflow', 'Craig-reviewed hiring workflow dashboard and task queues.', 0),
            array('NESP_INTERVIEWER_POOL_ENABLED', 'Interviewer Pool', 'Scoped interviewer access to assigned candidates and interviews.', 0),
            array('NESP_PRESCREEN_ENABLED', 'Prescreen Workflow', 'Craig-approved phone-screen workflow status and results.', 0),
            array('NESP_VAPI_ENABLED', 'Vapi Phone Screens', 'Disabled integration flag. No calls are placed by this module.', 0),
            array('NESP_ZOOM_ENABLED', 'Zoom Scheduling', 'Disabled integration flag. No meetings are created by this module.', 0),
            array('NESP_AI_REVIEW_ENABLED', 'AI Candidate Review', 'Disabled integration flag. No model calls are made by this module.', 0),
            array('NESP_STAFFING_FORECAST_ENABLED', 'Staffing Forecast', 'Seasonal staffing forecast screen and internal draft recommendations.', 0),
            array('NESP_STAFFING_DRIVE_IMPORT_ENABLED', 'Staffing Drive Import', 'Google Drive staffing schedule discovery and import controls.', 0)
        );
    }

    public static function getRequiredFeatureFlagKeys()
    {
        return array(
            'NESP_WORKFLOW_ENABLED',
            'NESP_INTERVIEWER_POOL_ENABLED',
            'NESP_PRESCREEN_ENABLED',
            'NESP_VAPI_ENABLED',
            'NESP_ZOOM_ENABLED',
            'NESP_AI_REVIEW_ENABLED',
            'NESP_STAFFING_FORECAST_ENABLED',
            'NESP_STAFFING_DRIVE_IMPORT_ENABLED'
        );
    }

    public static function getDashboardNavigation()
    {
        return array(
            array('key' => 'needsCraig', 'label' => 'Needs Craig', 'action' => 'dashboard'),
            array('key' => 'waiting', 'label' => 'Waiting', 'action' => 'waiting'),
            array('key' => 'interviews', 'label' => 'Interviews', 'action' => 'interviews'),
            array('key' => 'completed', 'label' => 'Completed', 'action' => 'completed'),
            array('key' => 'staffingForecast', 'label' => 'Staffing Forecast', 'action' => 'staffingForecast'),
            array('key' => 'settings', 'label' => 'Settings', 'action' => 'settings')
        );
    }

    public static function getDefaultWorkflowStages()
    {
        return array(
            array('new', 'New', 'New application awaiting human review.', 10, 0),
            array('needs_review', 'Needs Review', 'Craig or an authorized reviewer needs to inspect the application.', 20, 0),
            array('follow_up_needed', 'Follow Up Needed', 'Missing information or clarification is needed.', 30, 0),
            array('applicant_clarification_requested', 'Applicant Clarification Requested', 'Waiting on the applicant to clarify an application detail.', 35, 0),
            array('phone_screen_pending', 'Phone Screen Pending', 'A phone screen is approved but not completed.', 40, 0),
            array('phone_screen_complete', 'Phone Screen Complete', 'Phone-screen results are ready for human review.', 50, 0),
            array('interview_requested', 'Interview Requested', 'Craig wants an interview scheduled.', 60, 0),
            array('interview_confirmation_pending', 'Interview Confirmation Pending', 'Waiting for applicant confirmation or reschedule response.', 65, 0),
            array('interview_scheduled', 'Interview Scheduled', 'A human interview has been scheduled.', 70, 0),
            array('scorecard_pending', 'Scorecard Pending', 'An interviewer scorecard is expected.', 80, 0),
            array('scorecard_complete', 'Scorecard Complete', 'Completed scorecard is ready for Craig decision.', 85, 0),
            array('offer_review', 'Offer Review', 'Craig is reviewing a possible offer.', 90, 0),
            array('hired', 'Hired', 'Final human hiring decision recorded.', 100, 1),
            array('hold', 'Hold', 'Candidate is intentionally paused for future seasonal review.', 105, 1),
            array('not_selected', 'Not Selected', 'Final human decline decision recorded.', 110, 1),
            array('withdrawn', 'Withdrawn', 'Candidate withdrew or stopped the process.', 120, 1),
            array('declined', 'Declined', 'Legacy final human decline decision recorded.', 130, 1)
        );
    }

    public static function getDefaultIntegrationStatuses()
    {
        return array(
            array('vapi', 'Vapi Phone Screening', 'disabled', 'Disabled in Phase 2. No calls can be placed.'),
            array('zoom', 'Zoom Scheduling', 'disabled', 'Disabled in Phase 2. No meetings can be created.'),
            array('ai_review', 'AI Candidate Review', 'disabled', 'Disabled in Phase 2. No model calls can run.'),
            array('email', 'Applicant Email', 'disabled', 'Disabled in Phase 2. No outbound applicant email can be sent.')
        );
    }

    public static function isIntegrationEnabledFromFlags($featureFlags, $flagKey)
    {
        foreach ($featureFlags as $flag)
        {
            if (isset($flag['flag_key']) && $flag['flag_key'] === $flagKey)
            {
                return ((int) $flag['is_enabled']) === 1;
            }
        }

        return false;
    }

    public static function getFeatureFlagForAction($action)
    {
        if ($action === null || $action === '' || $action === 'dashboard')
        {
            return 'NESP_WORKFLOW_ENABLED';
        }

        if (in_array($action, array('waiting', 'interviews', 'completed', 'auditLog')))
        {
            return 'NESP_WORKFLOW_ENABLED';
        }

        if (in_array($action, array(
            'assignedCandidates',
            'assignedCandidate',
            'submitScorecard',
            'unlockScorecard',
            'interviewerAccess',
            'createInterviewer',
            'createInterviewerRoleRule',
            'createCandidateGrant',
            'createInterviewerAvailability'
        )))
        {
            return 'NESP_INTERVIEWER_POOL_ENABLED';
        }

        if (in_array($action, array('staffingForecast', 'createStaffingRecommendation')))
        {
            return 'NESP_STAFFING_FORECAST_ENABLED';
        }

        if (in_array($action, array('settings', 'featureFlags', 'saveFeatureFlags')))
        {
            return '';
        }

        return 'NESP_WORKFLOW_ENABLED';
    }

    public static function getQueueDefinitions()
    {
        return array(
            'needsCraig' => array(
                'title' => 'Needs Craig Now',
                'empty' => 'No Craig decisions are waiting.',
                'stageKeys' => array(
                    'new',
                    'needs_review',
                    'phone_screen_complete',
                    'interview_requested',
                    'scorecard_complete',
                    'offer_review'
                )
            ),
            'waitingApplicant' => array(
                'title' => 'Waiting on Applicant',
                'empty' => 'No applicant follow-up is waiting.',
                'stageKeys' => array(
                    'follow_up_needed',
                    'applicant_clarification_requested',
                    'phone_screen_pending',
                    'interview_confirmation_pending'
                )
            ),
            'waitingInterviewer' => array(
                'title' => 'Waiting on Interviewer',
                'empty' => 'No interviewer tasks are waiting.',
                'stageKeys' => array(
                    'interview_scheduled',
                    'scorecard_pending'
                )
            ),
            'upcomingInterviews' => array(
                'title' => 'Upcoming Interviews',
                'empty' => 'No upcoming interviews are scheduled.'
            ),
            'recentlyCompleted' => array(
                'title' => 'Recently Completed',
                'empty' => 'No recent completed decisions.',
                'stageKeys' => array(
                    'scorecard_complete',
                    'hired',
                    'hold',
                    'not_selected',
                    'withdrawn',
                    'declined'
                )
            )
        );
    }

    public static function getDefaultScorecardQuestions()
    {
        return array(
            array('key' => 'reliability', 'label' => 'Reliability and schedule fit', 'type' => 'rating'),
            array('key' => 'people_skills', 'label' => 'Comfort with athletes, families, coaches, and staff', 'type' => 'rating'),
            array('key' => 'role_fit', 'label' => 'Role-specific skills or trainability', 'type' => 'rating'),
            array('key' => 'notes', 'label' => 'Factual notes from the conversation', 'type' => 'textarea')
        );
    }

    public static function getDefaultAssignmentRuleExamples()
    {
        return array(
            array(
                'role_match_text' => 'photographer',
                'assignment_mode' => 'suggest_only',
                'notes' => 'Suggested routing for freelance and staff photographer applicants. Craig still approves any real interviewer grant.'
            ),
            array(
                'role_match_text' => 'customer service',
                'assignment_mode' => 'suggest_only',
                'notes' => 'Use for customer-service applicants; no automatic email or status change.'
            ),
            array(
                'role_match_text' => 'table greeter',
                'assignment_mode' => 'suggest_only',
                'notes' => 'Use for on-site table and field assistant applicants.'
            )
        );
    }

    public static function getDefaultAvailabilityTemplate()
    {
        return array(
            'timezone' => 'America/New_York',
            'slot_minutes' => 30,
            'buffer_minutes' => 10,
            'notes' => 'Internal availability only. Applicant self-booking and Zoom creation remain disabled until separately approved.'
        );
    }

    public static function isValidAvailabilityTime($time)
    {
        $time = trim($time);
        if (!preg_match('/^(\d{2}):(\d{2})$/', $time, $matches))
        {
            return false;
        }

        return (int) $matches[1] >= 0
            && (int) $matches[1] <= 23
            && (int) $matches[2] >= 0
            && (int) $matches[2] <= 59;
    }

    public static function matchAssignmentRuleForRole($roleTitle, $rules)
    {
        $roleTitle = strtolower(trim($roleTitle));
        if ($roleTitle === '')
        {
            return array();
        }

        foreach ($rules as $rule)
        {
            if (isset($rule['is_active']) && ((int) $rule['is_active']) !== 1)
            {
                continue;
            }

            $matchText = isset($rule['role_match_text']) ? strtolower(trim($rule['role_match_text'])) : '';
            if ($matchText !== '' && strpos($roleTitle, $matchText) !== false)
            {
                return $rule;
            }
        }

        return array();
    }

    public static function getDefaultStaffingForecastConfig()
    {
        return array(
            'photographer_ratio' => 1.0,
            'assistant_ratio' => 0.35,
            'table_staff_ratio' => 0.25,
            'buffer_percent' => 25,
            'expected_returning_staff' => 0,
            'confirmed_available_staff' => 0,
            'active_staff' => 0
        );
    }

    public static function parseStaffingCSVText($csvText, $sourceLabel = 'uploaded CSV')
    {
        $lines = preg_split('/\r\n|\n|\r/', $csvText);
        $rows = array();
        foreach ($lines as $line)
        {
            if (trim($line) === '')
            {
                continue;
            }
            $rows[] = str_getcsv($line);
        }

        return self::normalizeStaffingRows($rows, $sourceLabel, 'CSV');
    }

    public static function parseStaffingXLSXFile($filePath, $sourceLabel = 'uploaded XLSX')
    {
        if (!class_exists('ZipArchive'))
        {
            return array(
                'rows' => array(),
                'issues' => array(
                    array('row_number' => 0, 'issue_key' => 'xlsx_unavailable', 'message' => 'XLSX parsing requires the PHP ZipArchive extension.')
                ),
                'checksum' => is_file($filePath) ? hash_file('sha256', $filePath) : '',
                'source_label' => $sourceLabel
            );
        }

        $zip = new ZipArchive();
        if ($zip->open($filePath) !== true)
        {
            return array(
                'rows' => array(),
                'issues' => array(
                    array('row_number' => 0, 'issue_key' => 'xlsx_open_failed', 'message' => 'The XLSX file could not be opened.')
                ),
                'checksum' => is_file($filePath) ? hash_file('sha256', $filePath) : '',
                'source_label' => $sourceLabel
            );
        }

        $sharedStrings = array();
        $sharedXML = $zip->getFromName('xl/sharedStrings.xml');
        if ($sharedXML !== false)
        {
            $xml = @simplexml_load_string($sharedXML);
            if ($xml !== false)
            {
                foreach ($xml->si as $stringItem)
                {
                    $sharedStrings[] = (string) $stringItem->t;
                }
            }
        }

        $sheetXML = $zip->getFromName('xl/worksheets/sheet1.xml');
        $rows = array();
        if ($sheetXML !== false)
        {
            $xml = @simplexml_load_string($sheetXML);
            if ($xml !== false)
            {
                foreach ($xml->sheetData->row as $row)
                {
                    $values = array();
                    foreach ($row->c as $cell)
                    {
                        $value = (string) $cell->v;
                        if ((string) $cell['t'] === 's' && isset($sharedStrings[(int) $value]))
                        {
                            $value = $sharedStrings[(int) $value];
                        }
                        $values[] = $value;
                    }
                    $rows[] = $values;
                }
            }
        }
        $zip->close();

        $result = self::normalizeStaffingRows($rows, $sourceLabel, 'XLSX');
        $result['checksum'] = is_file($filePath) ? hash_file('sha256', $filePath) : '';
        return $result;
    }

    public static function normalizeStaffingRows($rows, $sourceLabel, $sourceType = 'CSV')
    {
        $normalized = array();
        $issues = array();
        if (empty($rows))
        {
            return array(
                'rows' => $normalized,
                'issues' => array(array('row_number' => 0, 'issue_key' => 'empty_source', 'message' => 'No rows were found.')),
                'checksum' => hash('sha256', ''),
                'source_label' => $sourceLabel,
                'source_type' => $sourceType
            );
        }

        $headerRowIndex = self::findStaffingHeaderRow($rows);
        $header = self::normalizeHeader($rows[$headerRowIndex]);
        $dateColumns = array();
        foreach ($header as $index => $name)
        {
            $sourceHeader = isset($rows[$headerRowIndex][$index]) ? $rows[$headerRowIndex][$index] : $name;
            $date = self::parseStaffingDate($sourceHeader);
            if ($date !== '')
            {
                $dateColumns[$index] = $date;
            }
        }

        $seenHashes = array();
        for ($i = $headerRowIndex + 1; $i < count($rows); $i++)
        {
            $row = $rows[$i];
            $rawText = implode(' | ', $row);
            if (trim($rawText) === '')
            {
                continue;
            }

            $rowNumber = $i + 1;
            if (!empty($dateColumns))
            {
                foreach ($dateColumns as $columnIndex => $date)
                {
                    $staffText = isset($row[$columnIndex]) ? trim($row[$columnIndex]) : '';
                    if ($staffText === '')
                    {
                        continue;
                    }

                    $base = self::rowValueMap($header, $row);
                    $base['date'] = $date;
                    $base['staff'] = $staffText;
                    $result = self::normalizeStaffingRow($base, $rowNumber, $rawText, $sourceLabel);
                    if (isset($seenHashes[$result['row']['source_row_hash']]))
                    {
                        $result['issues'][] = array(
                            'row_number' => $rowNumber,
                            'issue_key' => 'duplicate_source_row',
                            'message' => 'This source row appears to duplicate an earlier normalized row.'
                        );
                        $result['row']['issue_count']++;
                        $result['row']['status_key'] = 'needs_review';
                        $result['row']['source_row_hash'] = hash('sha256', $result['row']['source_row_hash'] . '|' . $rowNumber);
                    }
                    $seenHashes[$result['row']['source_row_hash']] = true;
                    $normalized[] = $result['row'];
                    $issues = array_merge($issues, $result['issues']);
                }
                continue;
            }

            $mapped = self::rowValueMap($header, $row);
            $result = self::normalizeStaffingRow($mapped, $rowNumber, $rawText, $sourceLabel);
            if (isset($seenHashes[$result['row']['source_row_hash']]))
            {
                $result['issues'][] = array(
                    'row_number' => $rowNumber,
                    'issue_key' => 'duplicate_source_row',
                    'message' => 'This source row appears to duplicate an earlier normalized row.'
                );
                $result['row']['issue_count']++;
                $result['row']['status_key'] = 'needs_review';
                $result['row']['source_row_hash'] = hash('sha256', $result['row']['source_row_hash'] . '|' . $rowNumber);
            }
            $seenHashes[$result['row']['source_row_hash']] = true;
            $normalized[] = $result['row'];
            $issues = array_merge($issues, $result['issues']);
        }

        return array(
            'rows' => $normalized,
            'issues' => $issues,
            'checksum' => hash('sha256', json_encode($rows)),
            'source_label' => $sourceLabel,
            'source_type' => $sourceType
        );
    }

    public static function calculateStaffingForecastMetrics($rows, $config = array())
    {
        $config = array_merge(self::getDefaultStaffingForecastConfig(), $config);
        $metrics = array(
            'events_by_season' => array(),
            'events_by_week' => array(),
            'events_by_weekday' => array(),
            'events_by_state' => array(),
            'events_by_sport' => array(),
            'unique_staff_by_season' => array(),
            'staff_by_role' => array(),
            'staff_hours' => 0.0,
            'total_events' => 0,
            'total_staff_assignments' => 0,
            'peak_day_staffing' => 0,
            'peak_concurrent_staff' => 0,
            'average_staff_per_event' => 0,
            'recommended_pool' => 0,
            'recommended_backup' => 0,
            'hiring_gap' => 0,
            'confidence' => 'Low',
            'formulas' => array(
                'recommended_pool' => 'ceil(peak_day_staffing * (1 + buffer_percent / 100))',
                'recommended_backup' => 'ceil(recommended_pool * buffer_percent / 100)',
                'hiring_gap' => 'max(0, recommended_pool + recommended_backup - active_staff - expected_returning_staff - confirmed_available_staff)',
                'confidence' => 'High requires at least 3 usable seasons and no open import issues; Medium requires at least 2 usable seasons.'
            )
        );

        $events = array();
        $dayStaff = array();
        $staffBySeason = array();
        $openIssues = 0;
        foreach ($rows as $row)
        {
            if (!empty($row['issue_count']))
            {
                $openIssues += (int) $row['issue_count'];
            }

            if (empty($row['event_date']))
            {
                continue;
            }

            $season = substr($row['event_date'], 0, 4);
            $week = date('Y-m-d', strtotime('monday this week', strtotime($row['event_date'])));
            $weekday = date('l', strtotime($row['event_date']));
            $eventKey = $row['event_date'] . '|' . $row['event_name'] . '|' . $row['state'];
            $isNewEvent = !isset($events[$eventKey]);
            $events[$eventKey] = true;

            if ($isNewEvent)
            {
                self::incrementMetric($metrics['events_by_season'], $season, 1);
                self::incrementMetric($metrics['events_by_week'], $week, 1);
                self::incrementMetric($metrics['events_by_weekday'], $weekday, 1);
                self::incrementMetric($metrics['events_by_state'], $row['state'] === '' ? 'Unknown' : $row['state'], 1);
                self::incrementMetric($metrics['events_by_sport'], $row['sport'] === '' ? 'Unknown' : $row['sport'], 1);
            }
            self::incrementMetric($metrics['staff_by_role'], $row['role_key'] === '' ? 'unknown' : $row['role_key'], max(1, (int) $row['staff_count']));

            if (!isset($staffBySeason[$season]))
            {
                $staffBySeason[$season] = array();
            }
            if ($row['staff_name'] !== '')
            {
                foreach (preg_split('/[;,]+/', $row['staff_name']) as $staffName)
                {
                    $staffName = trim($staffName);
                    if ($staffName !== '')
                    {
                        $staffBySeason[$season][$staffName] = true;
                    }
                }
            }

            if (!isset($dayStaff[$row['event_date']]))
            {
                $dayStaff[$row['event_date']] = 0;
            }
            $dayStaff[$row['event_date']] += max(1, (int) $row['staff_count']);
            $metrics['staff_hours'] += (float) $row['staff_hours'];
            $metrics['total_staff_assignments'] += max(1, (int) $row['staff_count']);
        }

        foreach ($staffBySeason as $season => $staff)
        {
            $metrics['unique_staff_by_season'][$season] = count($staff);
        }

        $metrics['total_events'] = count($events);
        $metrics['peak_day_staffing'] = empty($dayStaff) ? 0 : max($dayStaff);
        $metrics['peak_concurrent_staff'] = $metrics['peak_day_staffing'];
        $metrics['average_staff_per_event'] = $metrics['total_events'] > 0
            ? round($metrics['total_staff_assignments'] / $metrics['total_events'], 2)
            : 0;
        $metrics['staff_hours'] = round($metrics['staff_hours'], 2);
        $metrics['recommended_pool'] = (int) ceil($metrics['peak_day_staffing'] * (1 + ((float) $config['buffer_percent'] / 100)));
        $metrics['recommended_backup'] = (int) ceil($metrics['recommended_pool'] * ((float) $config['buffer_percent'] / 100));
        $available = (int) $config['active_staff'] + (int) $config['expected_returning_staff'] + (int) $config['confirmed_available_staff'];
        $metrics['hiring_gap'] = max(0, $metrics['recommended_pool'] + $metrics['recommended_backup'] - $available);

        $usableSeasons = count($metrics['events_by_season']);
        if ($usableSeasons >= 3 && $openIssues === 0)
        {
            $metrics['confidence'] = 'High';
        }
        else if ($usableSeasons >= 2)
        {
            $metrics['confidence'] = 'Medium';
        }

        ksort($metrics['events_by_season']);
        ksort($metrics['events_by_week']);
        return $metrics;
    }

    public function isSchemaInstalled()
    {
        $requiredTables = array(
            'nesp_feature_flag',
            'nesp_candidate_workflow',
            'nesp_interviewer_role_rule',
            'nesp_interviewer_availability',
            'nesp_interview_slot',
            'nesp_staffing_schedule_history',
            'nesp_staffing_import_batch',
            'nesp_staffing_import_row',
            'nesp_staffing_import_issue',
            'nesp_staffing_forecast',
            'nesp_staffing_recommendation'
        );

        foreach ($requiredTables as $table)
        {
            $tableExists = $this->_db->getAssoc(
                sprintf("SHOW TABLES LIKE %s", $this->_db->makeQueryString($table))
            );
            if (empty($tableExists))
            {
                return false;
            }
        }

        $requiredColumns = array(
            array('nesp_candidate_workflow', 'summary'),
            array('nesp_candidate_workflow', 'next_action_label'),
            array('nesp_staffing_import_batch', 'undone_at'),
            array('nesp_staffing_import_row', 'source_row_hash'),
            array('nesp_staffing_import_issue', 'status_key')
        );

        foreach ($requiredColumns as $column)
        {
            $columnExists = $this->_db->getAssoc(
                sprintf(
                    "SHOW COLUMNS FROM %s LIKE %s",
                    $column[0],
                    $this->_db->makeQueryString($column[1])
                )
            );
            if (empty($columnExists))
            {
                return false;
            }
        }

        return true;
    }

    public function getFeatureFlags()
    {
        return $this->_db->getAllAssoc(
            'SELECT
                flag_key,
                display_name,
                description,
                is_enabled,
                requires_admin_approval,
                date_modified
            FROM
                nesp_feature_flag
            WHERE
                flag_key IN ("NESP_WORKFLOW_ENABLED", "NESP_INTERVIEWER_POOL_ENABLED", "NESP_PRESCREEN_ENABLED", "NESP_VAPI_ENABLED", "NESP_ZOOM_ENABLED", "NESP_AI_REVIEW_ENABLED", "NESP_STAFFING_FORECAST_ENABLED", "NESP_STAFFING_DRIVE_IMPORT_ENABLED")
            ORDER BY
                display_name'
        );
    }

    public function updateFeatureFlag($flagKey, $isEnabled, $actorUserID)
    {
        if (!in_array($flagKey, self::getRequiredFeatureFlagKeys()))
        {
            return false;
        }

        $sql = sprintf(
            'UPDATE
                nesp_feature_flag
             SET
                is_enabled = %s,
                date_modified = NOW()
             WHERE
                flag_key = %s',
            ((int) $isEnabled) === 1 ? '1' : '0',
            $this->_db->makeQueryString($flagKey)
        );

        $this->_db->query($sql);
        $this->logAuditEvent(
            $actorUserID,
            'feature_flag_updated',
            'feature_flag',
            null,
            array('flag_key' => $flagKey, 'is_enabled' => ((int) $isEnabled) === 1 ? 1 : 0)
        );

        return true;
    }

    public function isFeatureFlagEnabled($flagKey)
    {
        if (!in_array($flagKey, self::getRequiredFeatureFlagKeys()))
        {
            return false;
        }

        $row = $this->_db->getAssoc(
            sprintf(
                'SELECT is_enabled
                 FROM nesp_feature_flag
                 WHERE flag_key = %s
                 LIMIT 1',
                $this->_db->makeQueryString($flagKey)
            )
        );

        return !empty($row) && ((int) $row['is_enabled']) === 1;
    }

    public function getWorkflowStages()
    {
        return $this->_db->getAllAssoc(
            'SELECT
                stage_key,
                display_name,
                description,
                sort_order,
                is_terminal,
                is_enabled
            FROM
                nesp_workflow_stage
            ORDER BY
                sort_order'
        );
    }

    public function getIntegrationStatuses()
    {
        return $this->_db->getAllAssoc(
            'SELECT
                integration_key,
                display_name,
                status_key,
                message,
                last_checked_at,
                date_modified
            FROM
                nesp_integration_status
            ORDER BY
                display_name'
        );
    }

    public function getInterviewerAccessSummary()
    {
        return array(
            'activeInterviewers' => $this->countRows(
                'SELECT COUNT(*) AS total FROM nesp_interviewer_profile WHERE is_active = 1'
            ),
            'candidateGrants' => $this->countRows(
                'SELECT COUNT(*) AS total FROM nesp_interviewer_candidate_grant WHERE date_revoked IS NULL'
            ),
            'scheduledInterviews' => $this->countRows(
                "SELECT COUNT(*) AS total FROM nesp_interview WHERE status_key = 'scheduled'"
            ),
            'pendingScorecards' => $this->countRows(
                "SELECT COUNT(*) AS total FROM nesp_scorecard_response WHERE status_key = 'draft'"
            ),
            'assignmentRules' => $this->countRows(
                'SELECT COUNT(*) AS total FROM nesp_interviewer_role_rule WHERE is_active = 1'
            ),
            'availabilityBlocks' => $this->countRows(
                'SELECT COUNT(*) AS total FROM nesp_interviewer_availability WHERE is_active = 1'
            ),
            'openInterviewSlots' => $this->countRows(
                "SELECT COUNT(*) AS total FROM nesp_interview_slot WHERE slot_status_key = 'open'"
            )
        );
    }

    public function getDashboardSummary()
    {
        return array(
            'publicJobs' => $this->countRows(
                "SELECT COUNT(*) AS total FROM joborder WHERE public = 1 AND status = 'Active'"
            ),
            'allCandidates' => $this->countRows(
                'SELECT COUNT(*) AS total FROM candidate WHERE is_active = 1'
            ),
            'workflowTrackedCandidates' => $this->countRows(
                'SELECT COUNT(*) AS total FROM nesp_candidate_workflow'
            ),
            'needsReview' => $this->countRows(
                "SELECT COUNT(*) AS total
                 FROM nesp_candidate_workflow cw
                 INNER JOIN nesp_workflow_stage ws
                    ON ws.workflow_stage_id = cw.workflow_stage_id
                 WHERE ws.stage_key IN ('new', 'needs_review', 'follow_up_needed')"
            ),
            'integrationsEnabled' => $this->countRows(
                'SELECT COUNT(*) AS total FROM nesp_feature_flag WHERE is_enabled = 1'
            ),
            'recentAuditEvents' => $this->countRows(
                'SELECT COUNT(*) AS total
                 FROM nesp_audit_event
                 WHERE date_created >= DATE_SUB(NOW(), INTERVAL 7 DAY)'
            ),
            'interviewsThisWeek' => $this->countRows(
                'SELECT COUNT(*) AS total
                 FROM nesp_interview
                 WHERE scheduled_start >= CURDATE()
                   AND scheduled_start < DATE_ADD(CURDATE(), INTERVAL 7 DAY)
                   AND status_key IN ("scheduled", "confirmed", "needs_notes")'
            ),
            'overdueItems' => $this->countRows(
                'SELECT COUNT(*) AS total
                 FROM nesp_candidate_workflow cw
                 INNER JOIN nesp_workflow_stage ws
                    ON ws.workflow_stage_id = cw.workflow_stage_id
                 WHERE cw.due_at IS NOT NULL
                   AND cw.due_at < NOW()
                   AND ws.is_terminal = 0'
            ),
            'assignmentRules' => $this->countRows(
                'SELECT COUNT(*) AS total FROM nesp_interviewer_role_rule WHERE is_active = 1'
            ),
            'availabilityBlocks' => $this->countRows(
                'SELECT COUNT(*) AS total FROM nesp_interviewer_availability WHERE is_active = 1'
            )
        );
    }

    public function getDashboardQueues()
    {
        $rows = $this->getDashboardCandidateRows(120);
        $queues = $this->buildDashboardQueueSet($rows, true);

        foreach ($queues as $queueKey => $cards)
        {
            $queues[$queueKey] = array_slice($cards, 0, 12);
        }

        return $queues;
    }

    public function getDashboardQueueCounts()
    {
        $rows = $this->getDashboardCandidateRows(120);
        $queues = $this->buildDashboardQueueSet($rows, false);
        $counts = array();

        foreach ($queues as $queueKey => $cards)
        {
            $counts[$queueKey] = count($cards);
        }

        return $counts;
    }

    private function buildDashboardQueueSet($rows, $prioritizeOverdue)
    {
        $queues = array(
            'needsCraig' => array(),
            'waitingApplicant' => array(),
            'waitingInterviewer' => array(),
            'upcomingInterviews' => array(),
            'recentlyCompleted' => array()
        );
        $seen = array(
            'needsCraig' => array(),
            'waitingApplicant' => array(),
            'waitingInterviewer' => array(),
            'upcomingInterviews' => array(),
            'recentlyCompleted' => array()
        );
        $definitions = self::getQueueDefinitions();

        foreach ($rows as $row)
        {
            $card = $this->normalizeDashboardCard($row);
            $cardKey = $row['candidate_workflow_id'];
            foreach (array('needsCraig', 'waitingApplicant', 'waitingInterviewer', 'recentlyCompleted') as $queueKey)
            {
                if (in_array($row['stage_key'], $definitions[$queueKey]['stageKeys']) && !isset($seen[$queueKey][$cardKey]))
                {
                    $queues[$queueKey][] = $card;
                    $seen[$queueKey][$cardKey] = true;
                }
            }

            if ($row['scheduled_start'] !== null && $row['scheduled_start'] !== ''
                && strtotime($row['scheduled_start']) >= time()
                && in_array($row['interview_status_key'], array('scheduled', 'confirmed', 'needs_notes'))
                && !isset($seen['upcomingInterviews'][$cardKey]))
            {
                $queues['upcomingInterviews'][] = $card;
                $seen['upcomingInterviews'][$cardKey] = true;
            }

            if ($row['due_at'] !== null && $row['due_at'] !== ''
                && strtotime($row['due_at']) < time()
                && !in_array($row['stage_key'], array('hired', 'hold', 'not_selected', 'withdrawn', 'declined'))
                && !isset($seen['needsCraig'][$cardKey]))
            {
                $card['summary'] = 'Overdue item: ' . $card['summary'];
                if ($prioritizeOverdue)
                {
                    array_unshift($queues['needsCraig'], $card);
                }
                else
                {
                    $queues['needsCraig'][] = $card;
                }
                $seen['needsCraig'][$cardKey] = true;
            }
        }

        return $queues;
    }

    public function getDashboardCandidateRows($limit)
    {
        $limit = max(1, min(250, (int) $limit));

        return $this->_db->getAllAssoc(
            sprintf(
                'SELECT
                    cw.candidate_workflow_id,
                    cw.candidate_id,
                    cw.joborder_id,
                    cw.waiting_on_key,
                    cw.summary,
                    cw.next_action_label,
                    cw.due_at,
                    cw.date_modified,
                    c.first_name,
                    c.last_name,
                    jo.title AS role_title,
                    ws.stage_key,
                    ws.display_name AS stage_name,
                    i.interview_id,
                    i.scheduled_start,
                    i.scheduled_end,
                    i.status_key AS interview_status_key,
                    ip.display_name AS interviewer_name,
                    sr.status_key AS scorecard_status_key,
                    sr.overall_recommendation
                FROM
                    nesp_candidate_workflow cw
                INNER JOIN candidate c
                    ON c.candidate_id = cw.candidate_id
                INNER JOIN joborder jo
                    ON jo.joborder_id = cw.joborder_id
                INNER JOIN nesp_workflow_stage ws
                    ON ws.workflow_stage_id = cw.workflow_stage_id
                LEFT JOIN nesp_interview i
                    ON i.interview_id = (
                        SELECT MAX(i2.interview_id)
                        FROM nesp_interview i2
                        WHERE i2.candidate_id = cw.candidate_id
                          AND i2.joborder_id = cw.joborder_id
                    )
                LEFT JOIN nesp_interviewer_profile ip
                    ON ip.interviewer_profile_id = i.interviewer_profile_id
                LEFT JOIN nesp_scorecard_response sr
                    ON sr.scorecard_response_id = (
                        SELECT MAX(sr2.scorecard_response_id)
                        FROM nesp_scorecard_response sr2
                        WHERE sr2.candidate_id = cw.candidate_id
                          AND sr2.joborder_id = cw.joborder_id
                    )
                WHERE
                    c.is_active = 1
                ORDER BY
                    CASE WHEN cw.due_at IS NULL THEN 1 ELSE 0 END,
                    cw.due_at ASC,
                    cw.date_modified DESC
                LIMIT %s',
                $this->_db->makeQueryInteger($limit)
            )
        );
    }

    public function getUpcomingInterviews($limit)
    {
        $limit = max(1, min(100, (int) $limit));

        return $this->_db->getAllAssoc(
            sprintf(
                'SELECT
                    i.interview_id,
                    i.candidate_id,
                    i.joborder_id,
                    CONCAT(c.first_name, " ", c.last_name) AS candidate_name,
                    jo.title AS role_title,
                    ip.display_name AS interviewer_name,
                    i.scheduled_start,
                    i.scheduled_end,
                    TIMESTAMPDIFF(MINUTE, i.scheduled_start, i.scheduled_end) AS duration_minutes,
                    i.status_key
                FROM
                    nesp_interview i
                INNER JOIN candidate c
                    ON c.candidate_id = i.candidate_id
                INNER JOIN joborder jo
                    ON jo.joborder_id = i.joborder_id
                LEFT JOIN nesp_interviewer_profile ip
                    ON ip.interviewer_profile_id = i.interviewer_profile_id
                WHERE
                    i.scheduled_start IS NOT NULL
                    AND i.scheduled_start >= NOW()
                    AND i.status_key IN ("scheduled", "confirmed", "needs_notes")
                ORDER BY
                    i.scheduled_start ASC
                LIMIT %s',
                $this->_db->makeQueryInteger($limit)
            )
        );
    }

    public function getInterviewerProfiles()
    {
        return $this->_db->getAllAssoc(
            'SELECT
                ip.interviewer_profile_id,
                ip.user_id,
                ip.display_name,
                ip.email,
                ip.role_key,
                ip.is_active,
                ip.can_view_resume,
                ip.can_add_notes,
                ip.can_submit_scorecard,
                ip.date_modified,
                COUNT(cg.grant_id) AS active_grants
             FROM
                nesp_interviewer_profile ip
             LEFT JOIN nesp_interviewer_candidate_grant cg
                ON cg.interviewer_profile_id = ip.interviewer_profile_id
                AND cg.date_revoked IS NULL
             GROUP BY
                ip.interviewer_profile_id
             ORDER BY
                ip.is_active DESC,
                ip.display_name ASC'
        );
    }

    public function getInterviewerRoleRules()
    {
        return $this->_db->getAllAssoc(
            'SELECT
                rr.role_rule_id,
                rr.interviewer_profile_id,
                rr.joborder_id,
                rr.role_match_text,
                rr.assignment_mode,
                rr.priority,
                rr.is_active,
                rr.notes,
                rr.date_modified,
                ip.display_name AS interviewer_name,
                jo.title AS job_title
             FROM
                nesp_interviewer_role_rule rr
             LEFT JOIN nesp_interviewer_profile ip
                ON ip.interviewer_profile_id = rr.interviewer_profile_id
             LEFT JOIN joborder jo
                ON jo.joborder_id = rr.joborder_id
             ORDER BY
                rr.is_active DESC,
                rr.priority ASC,
                rr.role_match_text ASC'
        );
    }

    public function createInterviewerRoleRule($interviewerProfileID, $jobOrderID, $roleMatchText, $assignmentMode, $priority, $notes, $actorUserID)
    {
        $interviewerProfileID = (int) $interviewerProfileID;
        $jobOrderID = (int) $jobOrderID;
        $roleMatchText = trim($roleMatchText);
        $assignmentMode = in_array($assignmentMode, array('suggest_only', 'manual_review')) ? $assignmentMode : 'suggest_only';
        $priority = max(1, min(999, (int) $priority));
        $notes = trim($notes);

        if ($interviewerProfileID <= 0 || ($jobOrderID <= 0 && $roleMatchText === ''))
        {
            return false;
        }

        $sql = sprintf(
            'INSERT INTO nesp_interviewer_role_rule
                (interviewer_profile_id, joborder_id, role_match_text, assignment_mode, priority, is_active, notes, created_by_user_id, date_created, date_modified)
             VALUES
                (%s, %s, %s, %s, %s, 1, %s, %s, NOW(), NOW())',
            $this->_db->makeQueryInteger($interviewerProfileID),
            $jobOrderID <= 0 ? 'NULL' : $this->_db->makeQueryInteger($jobOrderID),
            $this->_db->makeQueryString($roleMatchText),
            $this->_db->makeQueryString($assignmentMode),
            $this->_db->makeQueryInteger($priority),
            $this->_db->makeQueryString($notes),
            $actorUserID === null ? 'NULL' : $this->_db->makeQueryInteger($actorUserID)
        );
        $this->_db->query($sql);
        $ruleID = $this->_db->getLastInsertID();

        $this->logAuditEvent(
            $actorUserID,
            'interviewer_role_rule_created',
            'interviewer_role_rule',
            $ruleID,
            array('interviewer_profile_id' => $interviewerProfileID, 'assignment_mode' => $assignmentMode)
        );

        return $ruleID;
    }

    public function createCandidateGrant($interviewerProfileID, $candidateID, $jobOrderID, $actorUserID)
    {
        $interviewerProfileID = (int) $interviewerProfileID;
        $candidateID = (int) $candidateID;
        $jobOrderID = (int) $jobOrderID;

        if ($interviewerProfileID <= 0 || $candidateID <= 0 || $jobOrderID <= 0)
        {
            return false;
        }

        $candidateJobOrder = $this->_db->getAssoc(
            sprintf(
                'SELECT cjo.candidate_joborder_id
                 FROM candidate_joborder cjo
                 INNER JOIN candidate c
                    ON c.candidate_id = cjo.candidate_id
                    AND c.is_active = 1
                 INNER JOIN joborder jo
                    ON jo.joborder_id = cjo.joborder_id
                 WHERE cjo.candidate_id = %s
                   AND cjo.joborder_id = %s
                 LIMIT 1',
                $this->_db->makeQueryInteger($candidateID),
                $this->_db->makeQueryInteger($jobOrderID)
            )
        );
        if (empty($candidateJobOrder))
        {
            return false;
        }

        $existing = $this->_db->getAssoc(
            sprintf(
                'SELECT grant_id
                 FROM nesp_interviewer_candidate_grant
                 WHERE interviewer_profile_id = %s
                   AND candidate_id = %s
                   AND joborder_id = %s
                   AND date_revoked IS NULL
                 LIMIT 1',
                $this->_db->makeQueryInteger($interviewerProfileID),
                $this->_db->makeQueryInteger($candidateID),
                $this->_db->makeQueryInteger($jobOrderID)
            )
        );
        if (!empty($existing))
        {
            return (int) $existing['grant_id'];
        }

        $sql = sprintf(
            'INSERT INTO nesp_interviewer_candidate_grant
                (interviewer_profile_id, candidate_id, joborder_id, granted_by_user_id, access_level_key, can_view_resume, can_add_notes, can_submit_scorecard, date_granted, date_revoked)
             VALUES
                (%s, %s, %s, %s, "interview", 1, 1, 1, NOW(), NULL)',
            $this->_db->makeQueryInteger($interviewerProfileID),
            $this->_db->makeQueryInteger($candidateID),
            $this->_db->makeQueryInteger($jobOrderID),
            $actorUserID === null ? 'NULL' : $this->_db->makeQueryInteger($actorUserID)
        );
        $this->_db->query($sql);
        $grantID = $this->_db->getLastInsertID();

        $this->logAuditEvent(
            $actorUserID,
            'interviewer_candidate_grant_created',
            'interviewer_candidate_grant',
            $grantID,
            array('interviewer_profile_id' => $interviewerProfileID, 'candidate_id' => $candidateID, 'joborder_id' => $jobOrderID)
        );

        return $grantID;
    }

    public function getInterviewerAvailability()
    {
        return $this->_db->getAllAssoc(
            'SELECT
                ia.availability_id,
                ia.interviewer_profile_id,
                ip.display_name AS interviewer_name,
                ia.weekday_key,
                ia.start_time,
                ia.end_time,
                ia.timezone,
                ia.slot_minutes,
                ia.buffer_minutes,
                ia.is_active,
                ia.notes,
                ia.date_modified
             FROM
                nesp_interviewer_availability ia
             INNER JOIN nesp_interviewer_profile ip
                ON ip.interviewer_profile_id = ia.interviewer_profile_id
             ORDER BY
                ia.is_active DESC,
                ip.display_name ASC,
                FIELD(ia.weekday_key, "Monday", "Tuesday", "Wednesday", "Thursday", "Friday", "Saturday", "Sunday"),
                ia.start_time ASC'
        );
    }

    public function createInterviewerAvailability($interviewerProfileID, $weekdayKey, $startTime, $endTime, $timezone, $slotMinutes, $bufferMinutes, $notes, $actorUserID)
    {
        $interviewerProfileID = (int) $interviewerProfileID;
        $weekdayKey = trim($weekdayKey);
        $startTime = trim($startTime);
        $endTime = trim($endTime);
        $defaultAvailability = self::getDefaultAvailabilityTemplate();
        $timezone = trim($timezone) === '' ? $defaultAvailability['timezone'] : trim($timezone);
        $slotMinutes = max(15, min(180, (int) $slotMinutes));
        $bufferMinutes = max(0, min(60, (int) $bufferMinutes));
        $notes = trim($notes);

        if ($interviewerProfileID <= 0 || !in_array($weekdayKey, array('Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday')))
        {
            return false;
        }
        if (!self::isValidAvailabilityTime($startTime) || !self::isValidAvailabilityTime($endTime) || strcmp($startTime, $endTime) >= 0)
        {
            return false;
        }

        $sql = sprintf(
            'INSERT INTO nesp_interviewer_availability
                (interviewer_profile_id, weekday_key, start_time, end_time, timezone, slot_minutes, buffer_minutes, is_active, notes, created_by_user_id, date_created, date_modified)
             VALUES
                (%s, %s, %s, %s, %s, %s, %s, 1, %s, %s, NOW(), NOW())',
            $this->_db->makeQueryInteger($interviewerProfileID),
            $this->_db->makeQueryString($weekdayKey),
            $this->_db->makeQueryString($startTime),
            $this->_db->makeQueryString($endTime),
            $this->_db->makeQueryString($timezone),
            $this->_db->makeQueryInteger($slotMinutes),
            $this->_db->makeQueryInteger($bufferMinutes),
            $this->_db->makeQueryString($notes),
            $actorUserID === null ? 'NULL' : $this->_db->makeQueryInteger($actorUserID)
        );
        $this->_db->query($sql);
        $availabilityID = $this->_db->getLastInsertID();

        $this->logAuditEvent(
            $actorUserID,
            'interviewer_availability_created',
            'interviewer_availability',
            $availabilityID,
            array('interviewer_profile_id' => $interviewerProfileID, 'weekday_key' => $weekdayKey)
        );

        return $availabilityID;
    }

    public function getInterviewerAccountability()
    {
        return $this->_db->getAllAssoc(
            'SELECT
                ip.interviewer_profile_id,
                ip.display_name,
                ip.role_key,
                ip.is_active,
                COUNT(DISTINCT cg.grant_id) AS active_grants,
                COUNT(DISTINCT CASE WHEN i.status_key IN ("scheduled", "confirmed", "needs_notes") THEN i.interview_id END) AS open_interviews,
                COUNT(DISTINCT CASE WHEN cg.grant_id IS NOT NULL AND (sr.scorecard_response_id IS NULL OR sr.status_key = "draft") THEN cg.grant_id END) AS scorecards_due,
                COUNT(DISTINCT CASE WHEN cw.due_at IS NOT NULL AND cw.due_at < NOW() THEN cw.candidate_workflow_id END) AS overdue_items,
                COUNT(DISTINCT ia.availability_id) AS availability_blocks
             FROM
                nesp_interviewer_profile ip
             LEFT JOIN nesp_interviewer_candidate_grant cg
                ON cg.interviewer_profile_id = ip.interviewer_profile_id
                AND cg.date_revoked IS NULL
             LEFT JOIN nesp_interview i
                ON i.interviewer_profile_id = ip.interviewer_profile_id
             LEFT JOIN nesp_scorecard_response sr
                ON sr.interviewer_profile_id = ip.interviewer_profile_id
                AND sr.candidate_id = cg.candidate_id
                AND sr.joborder_id = cg.joborder_id
             LEFT JOIN nesp_candidate_workflow cw
                ON cw.candidate_id = cg.candidate_id
                AND cw.joborder_id = cg.joborder_id
             LEFT JOIN nesp_interviewer_availability ia
                ON ia.interviewer_profile_id = ip.interviewer_profile_id
                AND ia.is_active = 1
             GROUP BY
                ip.interviewer_profile_id
             ORDER BY
                overdue_items DESC,
                scorecards_due DESC,
                open_interviews DESC,
                ip.display_name ASC'
        );
    }

    public function getAssignmentSuggestions($limit)
    {
        $limit = max(1, min(100, (int) $limit));
        $rows = $this->getDashboardCandidateRows($limit);
        $rules = $this->getInterviewerRoleRules();
        $suggestions = array();

        foreach ($rows as $row)
        {
            if (!in_array($row['stage_key'], array('interview_requested', 'needs_review', 'phone_screen_complete')))
            {
                continue;
            }

            $matchedRule = array();
            foreach ($rules as $rule)
            {
                if ((int) $rule['is_active'] !== 1)
                {
                    continue;
                }
                if (!empty($rule['joborder_id']) && (int) $rule['joborder_id'] === (int) $row['joborder_id'])
                {
                    $matchedRule = $rule;
                    break;
                }
            }
            if (empty($matchedRule))
            {
                $matchedRule = self::matchAssignmentRuleForRole($row['role_title'], $rules);
            }

            $card = $this->normalizeDashboardCard($row);
            $card['suggested_interviewer'] = empty($matchedRule) ? 'No rule yet' : $matchedRule['interviewer_name'];
            $card['assignment_rule'] = empty($matchedRule) ? 'Create routing rule' : $matchedRule['role_match_text'];
            $card['assignment_mode'] = empty($matchedRule) ? 'manual_review' : $matchedRule['assignment_mode'];
            $suggestions[] = $card;
        }

        return array_slice($suggestions, 0, 12);
    }

    public function getScorecardSummaries($limit)
    {
        $limit = max(1, min(200, (int) $limit));

        return $this->_db->getAllAssoc(
            sprintf(
                'SELECT
                    sr.scorecard_response_id,
                    sr.candidate_id,
                    sr.joborder_id,
                    CONCAT(c.first_name, " ", c.last_name) AS candidate_name,
                    jo.title AS role_title,
                    ip.display_name AS interviewer_name,
                    sr.status_key,
                    sr.overall_recommendation,
                    sr.submitted_at,
                    sr.locked_at,
                    sr.unlocked_at,
                    sr.date_modified
                 FROM
                    nesp_scorecard_response sr
                 INNER JOIN candidate c
                    ON c.candidate_id = sr.candidate_id
                 INNER JOIN joborder jo
                    ON jo.joborder_id = sr.joborder_id
                 LEFT JOIN nesp_interviewer_profile ip
                    ON ip.interviewer_profile_id = sr.interviewer_profile_id
                 ORDER BY
                    sr.date_modified DESC,
                    sr.scorecard_response_id DESC
                 LIMIT %s',
                $this->_db->makeQueryInteger($limit)
            )
        );
    }

    public function createInactiveInterviewerProfile($displayName, $email, $roleKey, $actorUserID)
    {
        $displayName = trim($displayName);
        $email = trim($email);
        $roleKey = trim($roleKey) === '' ? 'interviewer' : trim($roleKey);

        if ($displayName === '')
        {
            return false;
        }

        $sql = sprintf(
            'INSERT INTO nesp_interviewer_profile
                (user_id, display_name, email, role_key, is_active, can_view_resume, can_add_notes, can_submit_scorecard, date_created, date_modified)
             VALUES
                (NULL, %s, %s, %s, 0, 0, 1, 1, NOW(), NOW())',
            $this->_db->makeQueryString($displayName),
            $this->_db->makeQueryString($email),
            $this->_db->makeQueryString($roleKey)
        );

        $this->_db->query($sql);
        $profileID = $this->_db->getLastInsertID();
        $this->logAuditEvent(
            $actorUserID,
            'interviewer_profile_created_inactive',
            'interviewer_profile',
            $profileID,
            array('display_name' => $displayName, 'role_key' => $roleKey)
        );

        return $profileID;
    }

    public function getAssignedCandidatesForUser($userID)
    {
        return $this->_db->getAllAssoc(
            sprintf(
                'SELECT
                    cg.grant_id,
                    cg.candidate_id,
                    cg.joborder_id,
                    CONCAT(c.first_name, " ", c.last_name) AS candidate_name,
                    jo.title AS role_title,
                    ws.display_name AS stage_name,
                    ws.stage_key,
                    cw.summary,
                    cw.waiting_on_key,
                    cw.date_modified AS last_activity,
                    i.interview_id,
                    i.scheduled_start,
                    i.scheduled_end,
                    i.status_key AS interview_status_key,
                    sr.status_key AS scorecard_status_key
                FROM
                    nesp_interviewer_profile ip
                INNER JOIN nesp_interviewer_candidate_grant cg
                    ON cg.interviewer_profile_id = ip.interviewer_profile_id
                    AND cg.date_revoked IS NULL
                INNER JOIN candidate c
                    ON c.candidate_id = cg.candidate_id
                INNER JOIN joborder jo
                    ON jo.joborder_id = cg.joborder_id
                LEFT JOIN nesp_candidate_workflow cw
                    ON cw.candidate_id = cg.candidate_id
                    AND cw.joborder_id = cg.joborder_id
                LEFT JOIN nesp_workflow_stage ws
                    ON ws.workflow_stage_id = cw.workflow_stage_id
                LEFT JOIN nesp_interview i
                    ON i.interview_id = (
                        SELECT MAX(i2.interview_id)
                        FROM nesp_interview i2
                        WHERE i2.candidate_id = cg.candidate_id
                          AND i2.joborder_id = cg.joborder_id
                          AND i2.interviewer_profile_id = ip.interviewer_profile_id
                    )
                LEFT JOIN nesp_scorecard_response sr
                    ON sr.scorecard_response_id = (
                        SELECT MAX(sr2.scorecard_response_id)
                        FROM nesp_scorecard_response sr2
                        WHERE sr2.candidate_id = cg.candidate_id
                          AND sr2.joborder_id = cg.joborder_id
                          AND sr2.interviewer_profile_id = ip.interviewer_profile_id
                    )
                WHERE
                    ip.user_id = %s
                    AND ip.is_active = 1
                    AND c.is_active = 1
                ORDER BY
                    i.scheduled_start ASC,
                    cw.date_modified DESC',
                $this->_db->makeQueryInteger($userID)
            )
        );
    }

    public function getAssignedCandidateDetail($userID, $candidateID, $jobOrderID)
    {
        if (!$this->userCanAccessCandidate($userID, $candidateID, $jobOrderID))
        {
            return array();
        }

        $rs = $this->_db->getAssoc(
            sprintf(
                'SELECT
                    cg.grant_id,
                    cg.candidate_id,
                    cg.joborder_id,
                    cg.can_view_resume,
                    cg.can_add_notes,
                    cg.can_submit_scorecard,
                    ip.interviewer_profile_id,
                    CONCAT(c.first_name, " ", c.last_name) AS candidate_name,
                    c.email1,
                    c.phone_cell,
                    c.key_skills,
                    c.notes,
                    jo.title AS role_title,
                    ws.display_name AS stage_name,
                    ws.stage_key,
                    cw.summary,
                    cw.waiting_on_key,
                    cw.date_modified AS last_activity
                FROM
                    nesp_interviewer_profile ip
                INNER JOIN nesp_interviewer_candidate_grant cg
                    ON cg.interviewer_profile_id = ip.interviewer_profile_id
                    AND cg.date_revoked IS NULL
                INNER JOIN candidate c
                    ON c.candidate_id = cg.candidate_id
                INNER JOIN joborder jo
                    ON jo.joborder_id = cg.joborder_id
                LEFT JOIN nesp_candidate_workflow cw
                    ON cw.candidate_id = cg.candidate_id
                    AND cw.joborder_id = cg.joborder_id
                LEFT JOIN nesp_workflow_stage ws
                    ON ws.workflow_stage_id = cw.workflow_stage_id
                WHERE
                    ip.user_id = %s
                    AND ip.is_active = 1
                    AND cg.candidate_id = %s
                    AND cg.joborder_id = %s
                LIMIT 1',
                $this->_db->makeQueryInteger($userID),
                $this->_db->makeQueryInteger($candidateID),
                $this->_db->makeQueryInteger($jobOrderID)
            )
        );

        if (empty($rs))
        {
            return array();
        }

        $rs['interviews'] = $this->_db->getAllAssoc(
            sprintf(
                'SELECT interview_id, scheduled_start, scheduled_end, status_key
                 FROM nesp_interview
                 WHERE candidate_id = %s
                   AND joborder_id = %s
                   AND interviewer_profile_id = %s
                 ORDER BY scheduled_start DESC',
                $this->_db->makeQueryInteger($candidateID),
                $this->_db->makeQueryInteger($jobOrderID),
                $this->_db->makeQueryInteger($rs['interviewer_profile_id'])
            )
        );

        $rs['scorecard'] = $this->_db->getAssoc(
            sprintf(
                'SELECT scorecard_response_id, status_key, overall_recommendation, answers_json, submitted_at, locked_at, unlocked_at, lock_reason
                 FROM nesp_scorecard_response
                 WHERE candidate_id = %s
                   AND joborder_id = %s
                   AND interviewer_profile_id = %s
                 ORDER BY scorecard_response_id DESC
                 LIMIT 1',
                $this->_db->makeQueryInteger($candidateID),
                $this->_db->makeQueryInteger($jobOrderID),
                $this->_db->makeQueryInteger($rs['interviewer_profile_id'])
            )
        );
        $rs['scorecard_answers'] = array();
        if (!empty($rs['scorecard']) && isset($rs['scorecard']['answers_json']))
        {
            $decodedAnswers = json_decode($rs['scorecard']['answers_json'], true);
            if (is_array($decodedAnswers))
            {
                $rs['scorecard_answers'] = $decodedAnswers;
            }
        }

        return $rs;
    }

    public function submitScorecard($userID, $candidateID, $jobOrderID, $answers, $recommendation)
    {
        return $this->persistScorecard($userID, $candidateID, $jobOrderID, $answers, $recommendation, 'submitted');
    }

    public function saveScorecardDraft($userID, $candidateID, $jobOrderID, $answers, $recommendation)
    {
        return $this->persistScorecard($userID, $candidateID, $jobOrderID, $answers, $recommendation, 'draft');
    }

    public function unlockScorecard($actorUserID, $scorecardResponseID)
    {
        $sql = sprintf(
            'UPDATE nesp_scorecard_response
             SET unlocked_at = NOW(),
                 unlocked_by_user_id = %s,
                 lock_reason = "Craig/admin reopened for correction",
                 date_modified = NOW()
             WHERE scorecard_response_id = %s
               AND locked_at IS NOT NULL',
            $actorUserID === null ? 'NULL' : $this->_db->makeQueryInteger($actorUserID),
            $this->_db->makeQueryInteger($scorecardResponseID)
        );
        $this->_db->query($sql);
        $this->logAuditEvent(
            $actorUserID,
            'scorecard_unlocked',
            'scorecard_response',
            $scorecardResponseID,
            array()
        );

        return true;
    }

    private function persistScorecard($userID, $candidateID, $jobOrderID, $answers, $recommendation, $statusKey)
    {
        $detail = $this->getAssignedCandidateDetail($userID, $candidateID, $jobOrderID);
        if (empty($detail) || ((int) $detail['can_submit_scorecard']) !== 1)
        {
            return false;
        }

        if (!empty($detail['scorecard'])
            && $detail['scorecard']['locked_at'] !== null
            && $detail['scorecard']['unlocked_at'] === null)
        {
            return false;
        }

        $template = $this->getEnabledScorecardTemplate();
        $answersJSON = json_encode($answers);
        if ($answersJSON === false)
        {
            $answersJSON = '{}';
        }

        $interviewID = 'NULL';
        if (!empty($detail['interviews']))
        {
            $interviewID = $this->_db->makeQueryInteger($detail['interviews'][0]['interview_id']);
        }

        $submittedAt = $statusKey === 'submitted' ? 'NOW()' : 'NULL';
        $lockedAt = $statusKey === 'submitted' ? 'NOW()' : 'NULL';
        $lockReason = $statusKey === 'submitted' ? 'Submitted by assigned interviewer' : '';
        if (!empty($detail['scorecard']) && $detail['scorecard']['status_key'] === 'draft')
        {
            $responseID = (int) $detail['scorecard']['scorecard_response_id'];
            $sql = sprintf(
                'UPDATE nesp_scorecard_response
                 SET answers_json = %s,
                     overall_recommendation = %s,
                     status_key = %s,
                     submitted_at = %s,
                     locked_at = %s,
                     unlocked_at = NULL,
                     unlocked_by_user_id = NULL,
                     lock_reason = %s,
                     date_modified = NOW()
                 WHERE scorecard_response_id = %s',
                $this->_db->makeQueryString($answersJSON),
                $this->_db->makeQueryString($recommendation),
                $this->_db->makeQueryString($statusKey),
                $submittedAt,
                $lockedAt,
                $this->_db->makeQueryString($lockReason),
                $this->_db->makeQueryInteger($responseID)
            );
            $this->_db->query($sql);
        }
        else
        {
            $sql = sprintf(
                'INSERT INTO nesp_scorecard_response
                    (scorecard_template_id, interview_id, candidate_id, joborder_id, interviewer_profile_id, answers_json, overall_recommendation, status_key, submitted_at, locked_at, lock_reason, date_created, date_modified)
                 VALUES
                    (%s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, NOW(), NOW())',
                empty($template) ? 'NULL' : $this->_db->makeQueryInteger($template['scorecard_template_id']),
                $interviewID,
                $this->_db->makeQueryInteger($candidateID),
                $this->_db->makeQueryInteger($jobOrderID),
                $this->_db->makeQueryInteger($detail['interviewer_profile_id']),
                $this->_db->makeQueryString($answersJSON),
                $this->_db->makeQueryString($recommendation),
                $this->_db->makeQueryString($statusKey),
                $submittedAt,
                $lockedAt,
                $this->_db->makeQueryString($lockReason)
            );
            $this->_db->query($sql);
            $responseID = $this->_db->getLastInsertID();
        }

        $this->logAuditEvent(
            $userID,
            $statusKey === 'submitted' ? 'scorecard_submitted' : 'scorecard_draft_saved',
            'scorecard_response',
            $responseID,
            array('candidate_id' => (int) $candidateID, 'joborder_id' => (int) $jobOrderID)
        );

        if ($statusKey === 'submitted')
        {
            $this->markWorkflowScorecardComplete($userID, $candidateID, $jobOrderID, $responseID);
        }

        return $responseID;
    }

    private function markWorkflowScorecardComplete($actorUserID, $candidateID, $jobOrderID, $scorecardResponseID)
    {
        $targetStage = $this->_db->getAssoc(
            "SELECT workflow_stage_id, stage_key
             FROM nesp_workflow_stage
             WHERE stage_key = 'scorecard_complete'
             LIMIT 1"
        );
        if (empty($targetStage))
        {
            return false;
        }

        $workflow = $this->_db->getAssoc(
            sprintf(
                'SELECT
                    cw.candidate_workflow_id,
                    cw.workflow_stage_id,
                    ws.stage_key
                 FROM nesp_candidate_workflow cw
                 LEFT JOIN nesp_workflow_stage ws
                    ON ws.workflow_stage_id = cw.workflow_stage_id
                 WHERE cw.candidate_id = %s
                   AND cw.joborder_id = %s
                 LIMIT 1',
                $this->_db->makeQueryInteger($candidateID),
                $this->_db->makeQueryInteger($jobOrderID)
            )
        );
        if (empty($workflow))
        {
            return false;
        }

        $sql = sprintf(
            'UPDATE nesp_candidate_workflow
             SET workflow_stage_id = %s,
                 waiting_on_key = "Craig",
                 summary = "Scorecard submitted by interviewer and ready for Craig review.",
                 next_action_label = "Review scorecard",
                 due_at = NOW(),
                 date_modified = NOW()
             WHERE candidate_workflow_id = %s',
            $this->_db->makeQueryInteger($targetStage['workflow_stage_id']),
            $this->_db->makeQueryInteger($workflow['candidate_workflow_id'])
        );
        $this->_db->query($sql);

        $this->logAuditEvent(
            $actorUserID,
            'candidate_workflow_stage_changed',
            'candidate_workflow',
            $workflow['candidate_workflow_id'],
            array(
                'candidate_id' => (int) $candidateID,
                'joborder_id' => (int) $jobOrderID,
                'previous_stage' => isset($workflow['stage_key']) ? $workflow['stage_key'] : '',
                'new_stage' => 'scorecard_complete',
                'reason' => 'scorecard_submitted',
                'scorecard_response_id' => (int) $scorecardResponseID,
                'result' => 'ready_for_craig_review'
            )
        );

        return true;
    }

    public function getEnabledScorecardTemplate()
    {
        return $this->_db->getAssoc(
            "SELECT scorecard_template_id, template_key, display_name, questions_json
             FROM nesp_scorecard_template
             WHERE template_key = 'nesp_standard_interview'
             ORDER BY is_enabled DESC, scorecard_template_id ASC
             LIMIT 1"
        );
    }

    public function getStaffingForecast()
    {
        $sourceStatus = $this->getStaffingSourceStatus();
        $importRows = $this->getNormalizedStaffingRows();
        $metrics = self::calculateStaffingForecastMetrics($importRows, self::getDefaultStaffingForecastConfig());
        $history = $this->_db->getAllAssoc(
            'SELECT
                schedule_history_id,
                season_year,
                season_name,
                week_start,
                event_count,
                photographer_slots,
                photographer_hours,
                source_label,
                notes
             FROM
                nesp_staffing_schedule_history
             ORDER BY
                week_start ASC'
        );

        $months = array();
        foreach ($history as $row)
        {
            $monthKey = date('m', strtotime($row['week_start']));
            if (!isset($months[$monthKey]))
            {
                $months[$monthKey] = array(
                    'month' => date('F', strtotime($row['week_start'])),
                    'weeks' => 0,
                    'events' => 0,
                    'slots' => 0,
                    'hours' => 0
                );
            }

            $months[$monthKey]['weeks']++;
            $months[$monthKey]['events'] += (int) $row['event_count'];
            $months[$monthKey]['slots'] += (int) $row['photographer_slots'];
            $months[$monthKey]['hours'] += (float) $row['photographer_hours'];
        }

        foreach ($months as $monthKey => $month)
        {
            $weeks = max(1, (int) $month['weeks']);
            $avgSlots = $month['slots'] / $weeks;
            $months[$monthKey]['avg_events'] = round($month['events'] / $weeks, 1);
            $months[$monthKey]['avg_slots'] = round($avgSlots, 1);
            $months[$monthKey]['avg_hours'] = round($month['hours'] / $weeks, 1);
            $months[$monthKey]['recommended_pipeline'] = (int) ceil($avgSlots * 1.25);
            $months[$monthKey]['confidence'] = $weeks >= 6 ? 'medium' : 'low';
        }

        return array(
            'sourceStatus' => $sourceStatus,
            'history' => $history,
            'normalizedRows' => array_slice($importRows, 0, 100),
            'metrics' => $metrics,
            'months' => array_values($months),
            'importIssues' => $this->getOpenStaffingImportIssues(50),
            'assumptions' => array(
                'No real historical Drive schedule has been imported by this PR.',
                'Historical schedule rows are opt-in fixtures unless Craig imports verified schedule history through a controlled task.',
                'Pipeline target uses 125% of average weekly photographer slots to leave room for declines, conflicts, and weather movement.',
                'Forecast output is planning guidance only and does not publish jobs, contact applicants, edit job records, or change feature flags.'
            )
        );
    }

    public function getStaffingSourceStatus()
    {
        $summary = $this->_db->getAssoc(
            'SELECT
                COUNT(*) AS import_batches,
                COALESCE(SUM(discovered_file_count), 0) AS files_discovered,
                COALESCE(SUM(imported_file_count), 0) AS files_imported,
                COALESCE(SUM(rows_imported), 0) AS rows_imported,
                COALESCE(SUM(rows_requiring_review), 0) AS rows_requiring_review,
                MAX(last_imported_at) AS last_import_date
             FROM
                nesp_staffing_import_batch
             WHERE
                undone_at IS NULL'
        );

        if (empty($summary))
        {
            $summary = array(
                'import_batches' => 0,
                'files_discovered' => 0,
                'files_imported' => 0,
                'rows_imported' => 0,
                'rows_requiring_review' => 0,
                'last_import_date' => null
            );
        }

        $summary['status_label'] = ((int) $summary['rows_imported']) > 0
            ? 'Files imported'
            : 'No historical data imported';

        return $summary;
    }

    public function getNormalizedStaffingRows()
    {
        return $this->_db->getAllAssoc(
            'SELECT
                import_row_id,
                import_batch_id,
                event_date,
                event_start_time,
                event_end_time,
                state,
                sport,
                event_name,
                role_key,
                staff_name,
                staff_count,
                staff_hours,
                raw_source_text,
                issue_count,
                status_key
             FROM
                nesp_staffing_import_row
             WHERE
                import_batch_id IN (
                    SELECT import_batch_id
                    FROM nesp_staffing_import_batch
                    WHERE undone_at IS NULL
                )
             ORDER BY
                event_date ASC,
                event_start_time ASC,
                import_row_id ASC'
        );
    }

    public function getOpenStaffingImportIssues($limit)
    {
        $limit = max(1, min(200, (int) $limit));

        return $this->_db->getAllAssoc(
            sprintf(
                'SELECT
                    issue.import_issue_id,
                    issue.import_batch_id,
                    issue.import_row_id,
                    issue.issue_key,
                    issue.severity_key,
                    issue.message,
                    issue.date_created
                 FROM
                    nesp_staffing_import_issue issue
                 INNER JOIN nesp_staffing_import_batch batch
                    ON batch.import_batch_id = issue.import_batch_id
                    AND batch.undone_at IS NULL
                 WHERE
                    issue.status_key = "open"
                 ORDER BY
                    issue.date_created DESC,
                    issue.import_issue_id DESC
                 LIMIT %s',
                $this->_db->makeQueryInteger($limit)
            )
        );
    }

    public function createDraftStaffingRecommendation($actorUserID, $title, $recommendation)
    {
        $recommendationJSON = json_encode($recommendation);
        if ($recommendationJSON === false)
        {
            $recommendationJSON = '{}';
        }

        $sql = sprintf(
            'INSERT INTO nesp_staffing_recommendation
                (created_by_user_id, title, recommendation_json, status_key, date_created, date_modified)
             VALUES
                (%s, %s, %s, "draft", NOW(), NOW())',
            $actorUserID === null ? 'NULL' : $this->_db->makeQueryInteger($actorUserID),
            $this->_db->makeQueryString($title),
            $this->_db->makeQueryString($recommendationJSON)
        );

        $this->_db->query($sql);
        $recommendationID = $this->_db->getLastInsertID();
        $this->logAuditEvent(
            $actorUserID,
            'staffing_recommendation_draft_created',
            'staffing_recommendation',
            $recommendationID,
            array('title' => $title)
        );

        return $recommendationID;
    }

    public function saveStaffingImport($actorUserID, $sourceType, $sourceIdentifier, $sourceLabel, $parseResult)
    {
        $checksum = isset($parseResult['checksum']) ? $parseResult['checksum'] : '';
        $existing = $this->_db->getAssoc(
            sprintf(
                'SELECT import_batch_id
                 FROM nesp_staffing_import_batch
                 WHERE source_type = %s
                   AND source_identifier = %s
                   AND source_checksum = %s
                   AND undone_at IS NULL
                 LIMIT 1',
                $this->_db->makeQueryString($sourceType),
                $this->_db->makeQueryString($sourceIdentifier),
                $this->_db->makeQueryString($checksum)
            )
        );

        if (!empty($existing))
        {
            return array('import_batch_id' => (int) $existing['import_batch_id'], 'status' => 'duplicate_skipped');
        }

        $rows = isset($parseResult['rows']) ? $parseResult['rows'] : array();
        $issues = isset($parseResult['issues']) ? $parseResult['issues'] : array();
        $reviewRows = 0;
        foreach ($rows as $row)
        {
            if ((int) $row['issue_count'] > 0)
            {
                $reviewRows++;
            }
        }

        $transactionStarted = $this->_db->beginTransaction();

        $sql = sprintf(
            'INSERT INTO nesp_staffing_import_batch
                (source_type, source_identifier, source_checksum, source_label, status_key, discovered_file_count, imported_file_count, rows_imported, rows_requiring_review, created_by_user_id, last_imported_at, date_created, date_modified)
             VALUES
                (%s, %s, %s, %s, "imported", 1, 1, %s, %s, %s, NOW(), NOW(), NOW())',
            $this->_db->makeQueryString($sourceType),
            $this->_db->makeQueryString($sourceIdentifier),
            $this->_db->makeQueryString($checksum),
            $this->_db->makeQueryString($sourceLabel),
            $this->_db->makeQueryInteger(count($rows)),
            $this->_db->makeQueryInteger($reviewRows),
            $actorUserID === null ? 'NULL' : $this->_db->makeQueryInteger($actorUserID)
        );
        $this->_db->query($sql);
        $batchID = $this->_db->getLastInsertID();

        $rowIDBySourceNumber = array();
        foreach ($rows as $row)
        {
            $rowSQL = sprintf(
                'INSERT INTO nesp_staffing_import_row
                    (import_batch_id, source_row_hash, source_sheet_name, source_row_number, event_date, event_start_time, event_end_time, state, sport, event_name, role_key, staff_name, staff_count, staff_hours, raw_source_text, unresolved_json, issue_count, status_key, date_created)
                 VALUES
                    (%s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, NOW())',
                $this->_db->makeQueryInteger($batchID),
                $this->_db->makeQueryString($row['source_row_hash']),
                $this->_db->makeQueryString($row['source_sheet_name']),
                $this->_db->makeQueryInteger($row['source_row_number']),
                $row['event_date'] === '' ? 'NULL' : $this->_db->makeQueryString($row['event_date']),
                $row['event_start_time'] === null ? 'NULL' : $this->_db->makeQueryString($row['event_start_time']),
                $row['event_end_time'] === null ? 'NULL' : $this->_db->makeQueryString($row['event_end_time']),
                $this->_db->makeQueryString($row['state']),
                $this->_db->makeQueryString($row['sport']),
                $this->_db->makeQueryString($row['event_name']),
                $this->_db->makeQueryString($row['role_key']),
                $this->_db->makeQueryString($row['staff_name']),
                $this->_db->makeQueryInteger($row['staff_count']),
                $this->_db->makeQueryString($row['staff_hours']),
                $this->_db->makeQueryString($row['raw_source_text']),
                $this->_db->makeQueryString($row['unresolved_json']),
                $this->_db->makeQueryInteger($row['issue_count']),
                $this->_db->makeQueryString($row['status_key'])
            );
            $this->_db->query($rowSQL);
            $rowIDBySourceNumber[$row['source_row_number']] = $this->_db->getLastInsertID();
        }

        foreach ($issues as $issue)
        {
            $rowID = isset($rowIDBySourceNumber[$issue['row_number']]) ? $rowIDBySourceNumber[$issue['row_number']] : null;
            $issueSQL = sprintf(
                'INSERT INTO nesp_staffing_import_issue
                    (import_batch_id, import_row_id, issue_key, severity_key, message, status_key, date_created)
                 VALUES
                    (%s, %s, %s, "review", %s, "open", NOW())',
                $this->_db->makeQueryInteger($batchID),
                $rowID === null ? 'NULL' : $this->_db->makeQueryInteger($rowID),
                $this->_db->makeQueryString($issue['issue_key']),
                $this->_db->makeQueryString($issue['message'])
            );
            $this->_db->query($issueSQL);
        }

        if ($transactionStarted)
        {
            $this->_db->commitTransaction();
        }

        $this->logAuditEvent(
            $actorUserID,
            'staffing_import_saved',
            'staffing_import_batch',
            $batchID,
            array('source_type' => $sourceType, 'rows_imported' => count($rows), 'rows_requiring_review' => $reviewRows)
        );

        return array('import_batch_id' => (int) $batchID, 'status' => 'imported');
    }

    public function undoStaffingImport($actorUserID, $importBatchID)
    {
        $sql = sprintf(
            'UPDATE nesp_staffing_import_batch
             SET undone_at = NOW(),
                 undone_by_user_id = %s,
                 status_key = "undone",
                 date_modified = NOW()
             WHERE import_batch_id = %s
               AND undone_at IS NULL',
            $actorUserID === null ? 'NULL' : $this->_db->makeQueryInteger($actorUserID),
            $this->_db->makeQueryInteger($importBatchID)
        );
        $this->_db->query($sql);
        $this->logAuditEvent(
            $actorUserID,
            'staffing_import_undone',
            'staffing_import_batch',
            $importBatchID,
            array()
        );

        return true;
    }

    public function getRecentAuditEvents($limit)
    {
        $limit = max(1, min(100, (int) $limit));

        return $this->_db->getAllAssoc(
            sprintf(
                'SELECT
                    audit_event_id,
                    actor_user_id,
                    event_type,
                    entity_type,
                    entity_id,
                    metadata_json,
                    date_created
                FROM
                    nesp_audit_event
                ORDER BY
                    date_created DESC,
                    audit_event_id DESC
                LIMIT %s',
                $this->_db->makeQueryInteger($limit)
            )
        );
    }

    public function userCanAccessCandidate($userID, $candidateID, $jobOrderID)
    {
        $sql = sprintf(
            'SELECT
                grant_id
            FROM
                nesp_interviewer_profile ip
            INNER JOIN nesp_interviewer_candidate_grant cg
                ON cg.interviewer_profile_id = ip.interviewer_profile_id
            WHERE
                ip.user_id = %s
                AND ip.is_active = 1
                AND cg.candidate_id = %s
                AND cg.joborder_id = %s
                AND cg.date_revoked IS NULL
            LIMIT 1',
            $this->_db->makeQueryInteger($userID),
            $this->_db->makeQueryInteger($candidateID),
            $this->_db->makeQueryInteger($jobOrderID)
        );

        $rs = $this->_db->getAssoc($sql);
        return !empty($rs);
    }

    private function normalizeDashboardCard($row)
    {
        $candidateName = trim($row['first_name'] . ' ' . $row['last_name']);
        $summary = trim($row['summary']);
        if ($summary === '')
        {
            $summary = 'Workflow item is waiting in the ' . $row['stage_name'] . ' stage.';
        }

        $waitingOn = trim($row['waiting_on_key']);
        if ($waitingOn === '')
        {
            $waitingOn = $this->inferWaitingOn($row['stage_key']);
        }

        $nextAction = trim($row['next_action_label']);
        if ($nextAction === '')
        {
            $nextAction = $this->inferNextAction($row['stage_key']);
        }

        return array(
            'candidate_id' => (int) $row['candidate_id'],
            'joborder_id' => (int) $row['joborder_id'],
            'candidate_name' => $candidateName,
            'role_title' => $row['role_title'],
            'stage_name' => $row['stage_name'],
            'stage_key' => $row['stage_key'],
            'waiting_on' => $waitingOn,
            'summary' => $summary,
            'last_activity' => $row['date_modified'],
            'next_action_label' => $nextAction,
            'candidate_url' => CATSUtility::getIndexName() . '?m=candidates&amp;a=show&amp;candidateID=' . (int) $row['candidate_id'],
            'job_url' => CATSUtility::getIndexName() . '?m=joborders&amp;a=show&amp;jobOrderID=' . (int) $row['joborder_id'],
            'scheduled_start' => $row['scheduled_start'],
            'scheduled_end' => $row['scheduled_end'],
            'interviewer_name' => $row['interviewer_name'],
            'interview_status_key' => $row['interview_status_key'],
            'scorecard_status_key' => $row['scorecard_status_key'],
            'overall_recommendation' => $row['overall_recommendation']
        );
    }

    private function inferWaitingOn($stageKey)
    {
        if (in_array($stageKey, array('follow_up_needed', 'applicant_clarification_requested', 'phone_screen_pending', 'interview_confirmation_pending')))
        {
            return 'Applicant';
        }
        if (in_array($stageKey, array('interview_scheduled', 'scorecard_pending')))
        {
            return 'Interviewer';
        }
        return 'Craig';
    }

    private function inferNextAction($stageKey)
    {
        $actions = array(
            'new' => 'Review application',
            'needs_review' => 'Make review decision',
            'follow_up_needed' => 'Check follow-up',
            'applicant_clarification_requested' => 'Review applicant reply',
            'phone_screen_pending' => 'Review phone screen status',
            'phone_screen_complete' => 'Review phone screen',
            'interview_requested' => 'Assign interviewer',
            'interview_confirmation_pending' => 'Check confirmation',
            'interview_scheduled' => 'Open interview',
            'scorecard_pending' => 'Check scorecard',
            'scorecard_complete' => 'Make decision',
            'offer_review' => 'Review offer',
            'hired' => 'Open record',
            'hold' => 'Open record',
            'not_selected' => 'Open record',
            'withdrawn' => 'Open record',
            'declined' => 'Open record'
        );

        return isset($actions[$stageKey]) ? $actions[$stageKey] : 'Open candidate';
    }

    public function logAuditEvent($actorUserID, $eventType, $entityType, $entityID, $metadata)
    {
        $metadataJSON = json_encode($metadata);
        if ($metadataJSON === false)
        {
            $metadataJSON = '{}';
        }

        $sql = sprintf(
            'INSERT INTO nesp_audit_event
                (actor_user_id, event_type, entity_type, entity_id, ip_address, user_agent, metadata_json, date_created)
             VALUES
                (%s, %s, %s, %s, %s, %s, %s, NOW())',
            $actorUserID === null ? 'NULL' : $this->_db->makeQueryInteger($actorUserID),
            $this->_db->makeQueryString($eventType),
            $this->_db->makeQueryString($entityType),
            $entityID === null ? 'NULL' : $this->_db->makeQueryInteger($entityID),
            $this->_db->makeQueryString(isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : ''),
            $this->_db->makeQueryString(isset($_SERVER['HTTP_USER_AGENT']) ? substr($_SERVER['HTTP_USER_AGENT'], 0, 255) : ''),
            $this->_db->makeQueryString($metadataJSON)
        );

        $this->_db->query($sql);
    }

    private function countRows($sql)
    {
        $rs = $this->_db->getAssoc($sql);
        if (empty($rs) || !isset($rs['total']))
        {
            return 0;
        }

        return (int) $rs['total'];
    }

    private static function normalizeHeader($headerRow)
    {
        $headers = array();
        foreach ($headerRow as $header)
        {
            $header = strtolower(trim($header));
            $header = preg_replace('/[^a-z0-9]+/', '_', $header);
            $headers[] = trim($header, '_');
        }

        return $headers;
    }

    private static function findStaffingHeaderRow($rows)
    {
        $limit = min(20, count($rows));
        for ($i = 0; $i < $limit; $i++)
        {
            $header = self::normalizeHeader($rows[$i]);
            $hasDate = in_array('date', $header) || in_array('event_date', $header) || in_array('picture_day', $header);
            $hasStaff = in_array('staff', $header) || in_array('photographers', $header) || in_array('assigned_staff', $header);
            $hasEvent = in_array('event', $header) || in_array('event_name', $header) || in_array('league', $header) || in_array('school', $header);
            $hasDateColumn = false;
            foreach ($rows[$i] as $cell)
            {
                if (self::parseStaffingDate($cell) !== '')
                {
                    $hasDateColumn = true;
                    break;
                }
            }

            if (($hasDate && $hasStaff) || ($hasEvent && $hasDateColumn))
            {
                return $i;
            }
        }

        return 0;
    }

    private static function rowValueMap($headers, $row)
    {
        $mapped = array();
        foreach ($headers as $index => $header)
        {
            if ($header === '')
            {
                continue;
            }
            $mapped[$header] = isset($row[$index]) ? trim($row[$index]) : '';
        }

        return $mapped;
    }

    private static function normalizeStaffingRow($mapped, $rowNumber, $rawText, $sourceLabel)
    {
        $issues = array();
        $date = self::firstMappedValue($mapped, array('date', 'event_date', 'picture_day', 'week'));
        $normalizedDate = self::parseStaffingDate($date);
        if ($normalizedDate === '')
        {
            $issues[] = array(
                'row_number' => $rowNumber,
                'issue_key' => 'missing_or_malformed_date',
                'message' => 'Date could not be normalized without manual review.'
            );
        }

        $staff = self::firstMappedValue($mapped, array('staff', 'photographers', 'photographer', 'assigned_staff', 'names', 'name'));
        $role = self::normalizeStaffingRole(self::firstMappedValue($mapped, array('role', 'staff_role', 'position')));
        $staffNames = self::splitStaffNames($staff);
        if (empty($staffNames))
        {
            $staffNames = array('');
            $issues[] = array(
                'row_number' => $rowNumber,
                'issue_key' => 'missing_staff',
                'message' => 'No staff name or count was found.'
            );
        }

        $startTime = self::parseStaffingTime(self::firstMappedValue($mapped, array('start', 'start_time', 'time')));
        $endTime = self::parseStaffingTime(self::firstMappedValue($mapped, array('end', 'end_time')));
        $staffHours = self::calculateStaffHours($startTime, $endTime, count($staffNames));
        $row = array(
            'source_sheet_name' => self::firstMappedValue($mapped, array('sheet', 'tab')),
            'source_row_number' => $rowNumber,
            'event_date' => $normalizedDate,
            'event_start_time' => $startTime,
            'event_end_time' => $endTime,
            'state' => strtoupper(self::firstMappedValue($mapped, array('state', 'st'))),
            'sport' => self::firstMappedValue($mapped, array('sport', 'league_sport')),
            'event_name' => self::firstMappedValue($mapped, array('event', 'event_name', 'league', 'school', 'organization')),
            'role_key' => $role,
            'staff_name' => implode('; ', $staffNames),
            'staff_count' => count($staffNames),
            'staff_hours' => $staffHours,
            'raw_source_text' => $rawText,
            'unresolved_json' => json_encode(array('source_label' => $sourceLabel)),
            'issue_count' => count($issues),
            'status_key' => count($issues) > 0 ? 'needs_review' : 'normalized'
        );
        $row['source_row_hash'] = hash('sha256', $rawText . '|' . $row['event_date'] . '|' . $row['event_name'] . '|' . $row['staff_name']);

        return array('row' => $row, 'issues' => $issues);
    }

    private static function firstMappedValue($mapped, $keys)
    {
        foreach ($keys as $key)
        {
            if (isset($mapped[$key]) && trim($mapped[$key]) !== '')
            {
                return trim($mapped[$key]);
            }
        }

        return '';
    }

    private static function parseStaffingDate($value)
    {
        $value = trim($value);
        if ($value === '')
        {
            return '';
        }

        $timestamp = strtotime($value);
        if ($timestamp === false)
        {
            return '';
        }

        return date('Y-m-d', $timestamp);
    }

    private static function parseStaffingTime($value)
    {
        $value = trim($value);
        if ($value === '')
        {
            return null;
        }

        $timestamp = strtotime($value);
        if ($timestamp === false)
        {
            return null;
        }

        return date('H:i:s', $timestamp);
    }

    private static function normalizeStaffingRole($role)
    {
        $role = strtolower(trim($role));
        if ($role === '')
        {
            return 'photographer';
        }
        if (strpos($role, 'assist') !== false)
        {
            return 'assistant';
        }
        if (strpos($role, 'table') !== false || strpos($role, 'greeter') !== false)
        {
            return 'table_staff';
        }

        return 'photographer';
    }

    private static function splitStaffNames($staff)
    {
        $staff = trim($staff);
        if ($staff === '')
        {
            return array();
        }

        $parts = preg_split('/[;,]+|\s+\+\s+|\s+\/\s+/', $staff);
        $names = array();
        foreach ($parts as $part)
        {
            $part = trim($part);
            if ($part !== '')
            {
                $names[] = $part;
            }
        }

        return $names;
    }

    private static function calculateStaffHours($startTime, $endTime, $staffCount)
    {
        if ($startTime === null || $endTime === null)
        {
            return 0.0;
        }

        $start = strtotime('2000-01-01 ' . $startTime);
        $end = strtotime('2000-01-01 ' . $endTime);
        if ($start === false || $end === false || $end <= $start)
        {
            return 0.0;
        }

        return round((($end - $start) / 3600) * max(1, (int) $staffCount), 2);
    }

    private static function incrementMetric(&$bucket, $key, $amount)
    {
        if (!isset($bucket[$key]))
        {
            $bucket[$key] = 0;
        }
        $bucket[$key] += $amount;
    }
}

?>
