<?php
/*
 * New England Sports Photo hosted hiring workflow foundation.
 *
 * Phase 1 stores durable workflow data and exposes read-only dashboard
 * summaries. External integrations remain disabled unless future work adds
 * reviewed controls.
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
            array('interviewer_portal_enabled', 'Interviewer Portal', 'Scoped interviewer access to assigned candidates and interviews.', 0),
            array('scorecards_enabled', 'Interview Scorecards', 'Browser-based interview scorecards for assigned interviewers.', 0),
            array('vapi_phone_screening_enabled', 'Vapi Phone Screens', 'Craig-approved phone-screen workflow status and results.', 0),
            array('zoom_scheduling_enabled', 'Zoom Scheduling', 'Craig-approved Zoom interview scheduling workflow.', 0),
            array('ai_candidate_review_enabled', 'AI Candidate Review', 'On-demand candidate summary controlled by Craig.', 0),
            array('external_email_enabled', 'Applicant Email', 'Outbound applicant email delivery.', 0)
        );
    }

    public static function getDefaultWorkflowStages()
    {
        return array(
            array('new', 'New', 'New application awaiting human review.', 10, 0),
            array('needs_review', 'Needs Review', 'Craig or an authorized reviewer needs to inspect the application.', 20, 0),
            array('follow_up_needed', 'Follow Up Needed', 'Missing information or clarification is needed.', 30, 0),
            array('phone_screen_pending', 'Phone Screen Pending', 'A phone screen is approved but not completed.', 40, 0),
            array('phone_screen_complete', 'Phone Screen Complete', 'Phone-screen results are ready for human review.', 50, 0),
            array('interview_requested', 'Interview Requested', 'Craig wants an interview scheduled.', 60, 0),
            array('interview_scheduled', 'Interview Scheduled', 'A human interview has been scheduled.', 70, 0),
            array('scorecard_pending', 'Scorecard Pending', 'An interviewer scorecard is expected.', 80, 0),
            array('offer_review', 'Offer Review', 'Craig is reviewing a possible offer.', 90, 0),
            array('hired', 'Hired', 'Final human hiring decision recorded.', 100, 1),
            array('declined', 'Declined', 'Final human decline decision recorded.', 110, 1)
        );
    }

    public static function getDefaultIntegrationStatuses()
    {
        return array(
            array('vapi', 'Vapi Phone Screening', 'disabled', 'Disabled in Phase 1. No calls can be placed.'),
            array('zoom', 'Zoom Scheduling', 'disabled', 'Disabled in Phase 1. No meetings can be created.'),
            array('ai_review', 'AI Candidate Review', 'disabled', 'Disabled in Phase 1. No model calls can run.'),
            array('email', 'Applicant Email', 'disabled', 'Disabled in Phase 1. No outbound applicant email can be sent.')
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

    public function isSchemaInstalled()
    {
        $rs = $this->_db->getAssoc(
            "SHOW TABLES LIKE 'nesp_feature_flag'"
        );

        return !empty($rs);
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
            ORDER BY
                display_name'
        );
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
