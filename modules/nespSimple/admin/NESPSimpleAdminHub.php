<?php

include_once(LEGACY_ROOT . '/lib/NESPWorkflow.php');
include_once(LEGACY_ROOT . '/lib/Attachments.php');
include_once(LEGACY_ROOT . '/lib/Candidates.php');

/**
 * Read-focused adapter for the simple NESP admin hub.
 *
 * The only write exposed here delegates to NESPWorkflow::createCandidateGrant()
 * so assignment authorization, duplicate protection, and auditing stay in the
 * existing workflow layer.
 */
class NESPSimpleAdminHub
{
    private $_db;
    private $_workflow;
    private $_attachments;
    private $_candidates;

    public function __construct($db = null, $workflow = null, $attachments = null, $candidates = null)
    {
        $this->_db = $db === null ? DatabaseConnection::getInstance() : $db;
        $this->_workflow = $workflow === null ? new NESPWorkflow($this->_db) : $workflow;
        $this->_attachments = $attachments === null ? new Attachments() : $attachments;
        $this->_candidates = $candidates === null ? new Candidates() : $candidates;
    }

    public function getApplicantRows($filters = array())
    {
        $rows = $this->_db->getAllAssoc(
            'SELECT
                cw.candidate_workflow_id,
                cw.candidate_id,
                cw.joborder_id,
                cw.date_modified AS last_activity,
                c.first_name,
                c.last_name,
                c.email1,
                c.phone_cell,
                c.phone_home,
                c.phone_work,
                c.source,
                jo.title AS role_title,
                ws.stage_key,
                ws.display_name AS stage_name,
                q.screening_questionnaire_id,
                q.status_key AS questionnaire_status_key,
                q.submitted_at AS questionnaire_submitted_at,
                i.interview_id,
                i.status_key AS interview_status_key,
                (
                    SELECT GROUP_CONCAT(DISTINCT ip.display_name ORDER BY ip.display_name SEPARATOR ", ")
                    FROM nesp_interviewer_candidate_grant cg
                    INNER JOIN nesp_interviewer_profile ip
                        ON ip.interviewer_profile_id = cg.interviewer_profile_id
                    WHERE cg.candidate_id = cw.candidate_id
                      AND cg.joborder_id = cw.joborder_id
                      AND cg.date_revoked IS NULL
                ) AS assigned_interviewer_names,
                (
                    SELECT COUNT(*)
                    FROM attachment resume_attachment
                    WHERE resume_attachment.data_item_type = ' . DATA_ITEM_CANDIDATE . '
                      AND resume_attachment.data_item_id = cw.candidate_id
                      AND resume_attachment.resume = 1
                ) AS resume_count
             FROM nesp_candidate_workflow cw
             INNER JOIN candidate c
                ON c.candidate_id = cw.candidate_id
             INNER JOIN joborder jo
                ON jo.joborder_id = cw.joborder_id
             INNER JOIN nesp_workflow_stage ws
                ON ws.workflow_stage_id = cw.workflow_stage_id
             LEFT JOIN nesp_screening_questionnaire q
                ON q.screening_questionnaire_id = (
                    SELECT MAX(q2.screening_questionnaire_id)
                    FROM nesp_screening_questionnaire q2
                    WHERE q2.candidate_id = cw.candidate_id
                      AND q2.joborder_id = cw.joborder_id
                )
             LEFT JOIN nesp_interview i
                ON i.interview_id = (
                    SELECT MAX(i2.interview_id)
                    FROM nesp_interview i2
                    WHERE i2.candidate_id = cw.candidate_id
                      AND i2.joborder_id = cw.joborder_id
                )
             ORDER BY cw.date_modified DESC, cw.candidate_workflow_id DESC'
        );

        return self::filterApplicantRows(self::decorateApplicantRows($rows), $filters);
    }

    public function getApplicantDetail($candidateID, $jobOrderID)
    {
        $candidateID = (int) $candidateID;
        $jobOrderID = (int) $jobOrderID;
        if ($candidateID <= 0 || $jobOrderID <= 0)
        {
            return array();
        }

        $applicant = array();
        foreach ($this->getApplicantRows() as $row)
        {
            if ((int) $row['candidate_id'] === $candidateID
                && (int) $row['joborder_id'] === $jobOrderID)
            {
                $applicant = $row;
                break;
            }
        }
        if (empty($applicant))
        {
            return array();
        }

        $questionnaire = array();
        if (!empty($applicant['questionnaire_id']))
        {
            $questionnaire = $this->_workflow->getQuestionnaireDetail(
                (int) $applicant['questionnaire_id']
            );
            $questionnaire = self::decorateExactQuestionnaireSnapshot($questionnaire);
        }

        $resumes = array();
        foreach ($this->_candidates->getResumes($candidateID) as $resume)
        {
            $attachment = $this->_attachments->get((int) $resume['attachmentID']);
            if (!empty($attachment))
            {
                $resumes[] = array(
                    'attachment_id' => (int) $resume['attachmentID'],
                    'title' => isset($attachment['title']) ? $attachment['title'] : $resume['title'],
                    'original_filename' => isset($attachment['originalFilename']) ? $attachment['originalFilename'] : '',
                    'retrieval_url' => isset($attachment['retrievalURL']) ? $attachment['retrievalURL'] : ''
                );
            }
        }

        $applicant['questionnaire'] = $questionnaire;
        $applicant['resumes'] = $resumes;
        $applicant['eligible_interviewers'] = $this->_workflow->getEligibleInterviewersForAssignment($jobOrderID);
        $applicant['candidate_url'] = CATSUtility::getIndexName()
            . '?m=candidates&amp;a=show&amp;candidateID=' . $candidateID;
        $applicant['schedule_url'] = CATSUtility::getIndexName()
            . '?m=nesp&amp;a=scheduleInterview&amp;candidateID=' . $candidateID
            . '&amp;jobOrderID=' . $jobOrderID;
        $applicant['interview_url'] = !empty($applicant['interview_id'])
            ? CATSUtility::getIndexName() . '?m=nesp&amp;a=recordInterviewOutcome&amp;interviewID='
                . (int) $applicant['interview_id']
            : '';

        return $applicant;
    }

    public function assignInterviewer($interviewerProfileID, $candidateID, $jobOrderID, $actorUserID)
    {
        $interviewerProfileID = (int) $interviewerProfileID;
        $candidateID = (int) $candidateID;
        $jobOrderID = (int) $jobOrderID;
        if ($interviewerProfileID <= 0 || $candidateID <= 0 || $jobOrderID <= 0)
        {
            return array('ok' => false, 'error' => 'Choose a valid interviewer.');
        }

        $eligible = false;
        foreach ($this->_workflow->getEligibleInterviewersForAssignment($jobOrderID) as $profile)
        {
            if ((int) $profile['interviewer_profile_id'] === $interviewerProfileID)
            {
                $eligible = true;
                break;
            }
        }
        if (!$eligible)
        {
            return array('ok' => false, 'error' => 'This interviewer is not active and approved for the selected role.');
        }

        $grantID = $this->_workflow->createCandidateGrant(
            $interviewerProfileID,
            $candidateID,
            $jobOrderID,
            $actorUserID
        );
        if (!$grantID)
        {
            return array('ok' => false, 'error' => 'The interviewer assignment could not be saved.');
        }

        return array('ok' => true, 'grant_id' => (int) $grantID);
    }

    public function buildExportCSV($filters = array())
    {
        $stream = fopen('php://temp', 'w+');
        fputcsv($stream, array(
            'Candidate ID',
            'Candidate',
            'Email',
            'Phone',
            'Job',
            'Source',
            'Workflow Status',
            'Questionnaire Submitted',
            'Resume',
            'Assigned Interviewer',
            'Interview Status',
            'Last Activity'
        ), ',', '"', '\\');

        foreach ($this->getApplicantRows($filters) as $row)
        {
            fputcsv($stream, array(
                (int) $row['candidate_id'],
                self::safeCSVCell($row['candidate_name']),
                self::safeCSVCell($row['email1']),
                self::safeCSVCell($row['contact_phone']),
                self::safeCSVCell($row['role_title']),
                self::safeCSVCell($row['source_label']),
                self::safeCSVCell($row['stage_name']),
                $row['questionnaire_submitted'] ? 'Yes' : 'No',
                $row['has_resume'] ? 'Yes' : 'No',
                self::safeCSVCell($row['assigned_interviewer_names']),
                self::safeCSVCell($row['interview_status_label']),
                self::safeCSVCell($row['last_activity'])
            ), ',', '"', '\\');
        }

        rewind($stream);
        $csv = stream_get_contents($stream);
        fclose($stream);
        return $csv;
    }

    public static function decorateApplicantRows($rows)
    {
        $interviewLabels = NESPWorkflow::getManualInterviewStatusLabels();
        foreach ($rows as $index => $row)
        {
            $interviewKey = isset($row['interview_status_key']) ? trim((string) $row['interview_status_key']) : '';
            $rows[$index]['candidate_id'] = (int) $row['candidate_id'];
            $rows[$index]['joborder_id'] = (int) $row['joborder_id'];
            $rows[$index]['candidate_name'] = trim($row['first_name'] . ' ' . $row['last_name']);
            $rows[$index]['email1'] = trim(isset($row['email1']) ? (string) $row['email1'] : '');
            $rows[$index]['contact_phone'] = self::firstNonEmptyValue(array(
                isset($row['phone_cell']) ? $row['phone_cell'] : '',
                isset($row['phone_home']) ? $row['phone_home'] : '',
                isset($row['phone_work']) ? $row['phone_work'] : ''
            ));
            $rows[$index]['source_label'] = trim((string) $row['source']) !== ''
                ? trim((string) $row['source'])
                : 'NESP Portal';
            $rows[$index]['questionnaire_id'] = isset($row['screening_questionnaire_id'])
                ? (int) $row['screening_questionnaire_id']
                : 0;
            $rows[$index]['questionnaire_submitted'] = !empty($row['questionnaire_submitted_at']);
            $rows[$index]['questionnaire_status_label'] = self::questionnaireStatusLabel(
                isset($row['questionnaire_status_key']) ? $row['questionnaire_status_key'] : '',
                $rows[$index]['questionnaire_submitted']
            );
            $rows[$index]['has_resume'] = !empty($row['resume_count']);
            $rows[$index]['assigned_interviewer_names'] = trim(
                isset($row['assigned_interviewer_names']) ? (string) $row['assigned_interviewer_names'] : ''
            );
            $rows[$index]['interview_id'] = isset($row['interview_id']) ? (int) $row['interview_id'] : 0;
            $rows[$index]['interview_status_label'] = $interviewKey === ''
                ? 'Not scheduled'
                : (isset($interviewLabels[$interviewKey]) ? $interviewLabels[$interviewKey] : $interviewKey);
        }
        return $rows;
    }

    public static function filterApplicantRows($rows, $filters)
    {
        $search = isset($filters['search']) ? strtolower(trim((string) $filters['search'])) : '';
        $role = isset($filters['role']) ? trim((string) $filters['role']) : '';
        $source = isset($filters['source']) ? trim((string) $filters['source']) : '';
        $status = isset($filters['status']) ? trim((string) $filters['status']) : '';

        return array_values(array_filter($rows, function ($row) use ($search, $role, $source, $status) {
            if ($role !== '' && (string) $row['joborder_id'] !== $role)
            {
                return false;
            }
            if ($source !== '' && strcasecmp($row['source_label'], $source) !== 0)
            {
                return false;
            }
            if ($status !== '' && (string) $row['stage_key'] !== $status)
            {
                return false;
            }
            if ($search !== '')
            {
                $haystack = strtolower(implode(' ', array(
                    $row['candidate_name'],
                    $row['email1'],
                    $row['contact_phone'],
                    $row['role_title'],
                    $row['source_label'],
                    $row['stage_name'],
                    $row['assigned_interviewer_names']
                )));
                if (strpos($haystack, $search) === false)
                {
                    return false;
                }
            }
            return true;
        }));
    }

    public static function filterOptions($rows)
    {
        $roles = array();
        $sources = array();
        $statuses = array();
        foreach ($rows as $row)
        {
            $roles[(string) $row['joborder_id']] = $row['role_title'];
            $sources[$row['source_label']] = $row['source_label'];
            $statuses[$row['stage_key']] = $row['stage_name'];
        }
        natcasesort($roles);
        natcasesort($sources);
        natcasesort($statuses);
        return array('roles' => $roles, 'sources' => $sources, 'statuses' => $statuses);
    }

    public static function safeCSVCell($value)
    {
        $value = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', '', (string) $value);
        if ($value !== '' && in_array($value[0], array('=', '+', '-', '@'), true))
        {
            return "'" . $value;
        }
        return $value;
    }

    public static function decorateExactQuestionnaireSnapshot($questionnaire)
    {
        if (empty($questionnaire))
        {
            return array();
        }

        $snapshot = array();
        if (!empty($questionnaire['question_snapshot_json']))
        {
            $decoded = json_decode((string) $questionnaire['question_snapshot_json'], true);
            if (is_array($decoded))
            {
                $snapshot = NESPWorkflow::normalizeQuestionnaireSnapshotQuestions($decoded);
            }
        }

        $answerMap = array();
        foreach (isset($questionnaire['answers']) ? (array) $questionnaire['answers'] : array() as $answer)
        {
            $answerMap[(string) $answer['question_key']] = isset($answer['answer_text'])
                ? (string) $answer['answer_text']
                : '';
        }

        $snapshotRows = array();
        foreach ($snapshot as $question)
        {
            $key = isset($question['key']) ? (string) $question['key'] : '';
            $snapshotRows[] = array(
                'question_key' => $key,
                'question_label' => isset($question['label']) ? (string) $question['label'] : '',
                'answer_text' => isset($answerMap[$key]) ? $answerMap[$key] : ''
            );
        }

        $questionnaire['exact_snapshot_available'] = !empty($snapshot);
        $questionnaire['snapshot_rows'] = $snapshotRows;
        return $questionnaire;
    }

    private static function firstNonEmptyValue($values)
    {
        foreach ($values as $value)
        {
            if (trim((string) $value) !== '')
            {
                return trim((string) $value);
            }
        }
        return '';
    }

    private static function questionnaireStatusLabel($statusKey, $submitted)
    {
        if ($submitted)
        {
            return 'Submitted';
        }
        $labels = array(
            '' => 'Not created',
            'link_ready' => 'Link ready',
            'waiting' => 'Waiting',
            'in_progress' => 'In progress',
            'completed' => 'Completed',
            'human_follow_up_requested' => 'Follow-up requested',
            'revoked' => 'Revoked',
            'expired' => 'Expired'
        );
        return isset($labels[$statusKey]) ? $labels[$statusKey] : $statusKey;
    }
}
