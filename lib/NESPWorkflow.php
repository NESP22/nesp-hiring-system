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
            array('NESP_AI_REVIEW_ENABLED', 'AI Candidate Review', 'Disabled integration flag. No model calls are made by this module.', 0)
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
            'NESP_AI_REVIEW_ENABLED'
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

    public function isSchemaInstalled()
    {
        $featureFlags = $this->_db->getAssoc(
            "SHOW TABLES LIKE 'nesp_feature_flag'"
        );

        $staffingHistory = $this->_db->getAssoc(
            "SHOW TABLES LIKE 'nesp_staffing_schedule_history'"
        );

        $workflowSummary = $this->_db->getAssoc(
            "SHOW COLUMNS FROM nesp_candidate_workflow LIKE 'summary'"
        );

        return !empty($featureFlags) && !empty($staffingHistory) && !empty($workflowSummary);
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
                flag_key IN ("NESP_WORKFLOW_ENABLED", "NESP_INTERVIEWER_POOL_ENABLED", "NESP_PRESCREEN_ENABLED", "NESP_VAPI_ENABLED", "NESP_ZOOM_ENABLED", "NESP_AI_REVIEW_ENABLED")
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
            )
        );
    }

    public function getDashboardQueues()
    {
        $rows = $this->getDashboardCandidateRows(120);
        $queues = array(
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
            foreach (array('needsCraig', 'waitingApplicant', 'waitingInterviewer', 'recentlyCompleted') as $queueKey)
            {
                if (in_array($row['stage_key'], $definitions[$queueKey]['stageKeys']))
                {
                    $queues[$queueKey][] = $card;
                }
            }

            if ($row['scheduled_start'] !== null && $row['scheduled_start'] !== ''
                && in_array($row['interview_status_key'], array('scheduled', 'confirmed', 'needs_notes')))
            {
                $queues['upcomingInterviews'][] = $card;
            }

            if ($row['due_at'] !== null && $row['due_at'] !== ''
                && strtotime($row['due_at']) < time()
                && !in_array($row['stage_key'], array('hired', 'hold', 'not_selected', 'withdrawn', 'declined')))
            {
                $card['summary'] = 'Overdue item: ' . $card['summary'];
                array_unshift($queues['needsCraig'], $card);
            }
        }

        foreach ($queues as $queueKey => $cards)
        {
            $queues[$queueKey] = array_slice($cards, 0, 12);
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
                'SELECT scorecard_response_id, status_key, overall_recommendation, answers_json, submitted_at
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

        return $rs;
    }

    public function submitScorecard($userID, $candidateID, $jobOrderID, $answers, $recommendation)
    {
        $detail = $this->getAssignedCandidateDetail($userID, $candidateID, $jobOrderID);
        if (empty($detail) || ((int) $detail['can_submit_scorecard']) !== 1)
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

        $sql = sprintf(
            'INSERT INTO nesp_scorecard_response
                (scorecard_template_id, interview_id, candidate_id, joborder_id, interviewer_profile_id, answers_json, overall_recommendation, status_key, submitted_at, date_created, date_modified)
             VALUES
                (%s, %s, %s, %s, %s, %s, %s, "submitted", NOW(), NOW(), NOW())',
            empty($template) ? 'NULL' : $this->_db->makeQueryInteger($template['scorecard_template_id']),
            $interviewID,
            $this->_db->makeQueryInteger($candidateID),
            $this->_db->makeQueryInteger($jobOrderID),
            $this->_db->makeQueryInteger($detail['interviewer_profile_id']),
            $this->_db->makeQueryString($answersJSON),
            $this->_db->makeQueryString($recommendation)
        );

        $this->_db->query($sql);
        $responseID = $this->_db->getLastInsertID();
        $this->logAuditEvent(
            $userID,
            'scorecard_submitted',
            'scorecard_response',
            $responseID,
            array('candidate_id' => (int) $candidateID, 'joborder_id' => (int) $jobOrderID)
        );

        return $responseID;
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
            'history' => $history,
            'months' => array_values($months),
            'assumptions' => array(
                'Historical schedule rows are opt-in fixtures unless Craig imports verified schedule history.',
                'Pipeline target uses 125% of average weekly photographer slots to leave room for declines, conflicts, and weather movement.',
                'Forecast output is planning guidance only and does not publish jobs, contact applicants, or change feature flags.'
            )
        );
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
}

?>
