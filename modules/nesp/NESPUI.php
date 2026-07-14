<?php
/*
 * New England Sports Photo hiring workflow module.
 */

include_once(LEGACY_ROOT . '/lib/NESPWorkflow.php');
include_once(LEGACY_ROOT . '/lib/CommonErrors.php');

class NESPUI extends UserInterface
{
    private $_workflow;

    public function __construct()
    {
        parent::__construct();

        $this->_authenticationRequired = true;
        $this->_moduleDirectory = 'nesp';
        $this->_moduleName = 'nesp';
        $this->_moduleTabText = 'NESP Hiring*al=' . ACCESS_LEVEL_READ;
        $this->_subTabs = array(
            'Needs Craig' => CATSUtility::getIndexName() . '?m=nesp*al=' . ACCESS_LEVEL_READ,
            'Waiting' => CATSUtility::getIndexName() . '?m=nesp&amp;a=waiting*al=' . ACCESS_LEVEL_READ,
            'Interviews' => CATSUtility::getIndexName() . '?m=nesp&amp;a=interviews*al=' . ACCESS_LEVEL_READ,
            'Completed' => CATSUtility::getIndexName() . '?m=nesp&amp;a=completed*al=' . ACCESS_LEVEL_READ,
            'Staffing Forecast' => CATSUtility::getIndexName() . '?m=nesp&amp;a=staffingForecast*al=' . ACCESS_LEVEL_READ,
            'Settings' => CATSUtility::getIndexName() . '?m=nesp&amp;a=settings*al=' . ACCESS_LEVEL_SA
        );

        $this->_workflow = new NESPWorkflow();
    }

    public function handleRequest()
    {
        $action = $this->getAction();

        if (!$this->_workflow->isSchemaInstalled())
        {
            $this->schemaMissing();
            return;
        }

        switch ($action)
        {
            case 'featureFlags':
            case 'settings':
                $this->adminOnly();
                $this->settings();
                break;

            case 'saveFeatureFlags':
                $this->adminOnly();
                $this->requirePostCSRF();
                $this->saveFeatureFlags();
                break;

            case 'createInterviewer':
                $this->adminOnly();
                $this->requirePostCSRF();
                $this->createInterviewer();
                break;

            case 'createInterviewerRoleRule':
                $this->adminOnly();
                $this->requirePostCSRF();
                $this->createInterviewerRoleRule();
                break;

            case 'createCandidateGrant':
                $this->adminOnly();
                $this->requirePostCSRF();
                $this->createCandidateGrant();
                break;

            case 'createInterviewerAvailability':
                $this->adminOnly();
                $this->requirePostCSRF();
                $this->createInterviewerAvailability();
                break;

            case 'interviewerAccess':
                $this->adminOnly();
                $this->interviewerAccess();
                break;

            case 'auditLog':
                $this->adminOnly();
                $this->auditLog();
                break;

            case 'assignedCandidates':
                $this->assignedCandidates();
                break;

            case 'assignedCandidate':
                $this->assignedCandidate();
                break;

            case 'submitScorecard':
                $this->requirePostCSRF();
                $this->submitScorecard();
                break;

            case 'unlockScorecard':
                $this->adminOnly();
                $this->requirePostCSRF();
                $this->unlockScorecard();
                break;

            case 'createStaffingRecommendation':
                $this->adminOnly();
                $this->requirePostCSRF();
                $this->createStaffingRecommendation();
                break;

            case 'staffingForecast':
                $this->staffingForecast();
                break;

            case 'waiting':
            case 'interviews':
            case 'completed':
            case 'dashboard':
            default:
                $this->dashboard($action);
                break;
        }
    }

    private function dashboard($viewKey = 'dashboard')
    {
        $queues = $this->_workflow->getDashboardQueues();
        $this->_template->assign('active', $this);
        $this->_template->assign('subActive', $this->subTabFromView($viewKey));
        $this->_template->assign('viewKey', $viewKey);
        $this->_template->assign('dashboardNavigation', NESPWorkflow::getDashboardNavigation());
        $this->_template->assign('summary', $this->_workflow->getDashboardSummary());
        $this->_template->assign('queueDefinitions', NESPWorkflow::getQueueDefinitions());
        $this->_template->assign('queues', $queues);
        $this->_template->assign('upcomingInterviews', $this->_workflow->getUpcomingInterviews(20));
        $this->_template->assign('integrationStatuses', $this->_workflow->getIntegrationStatuses());
        $this->_template->assign('workflowStages', $this->_workflow->getWorkflowStages());
        $this->_template->assign('assignmentSuggestions', $this->_workflow->getAssignmentSuggestions(50));
        $this->_template->assign('interviewerAccountability', $this->_workflow->getInterviewerAccountability());
        $this->_template->display('./modules/nesp/Dashboard.tpl');
    }

    private function settings()
    {
        $this->_template->assign('active', $this);
        $this->_template->assign('subActive', 'Settings');
        $this->_template->assign('viewKey', 'settings');
        $this->_template->assign('dashboardNavigation', NESPWorkflow::getDashboardNavigation());
        $this->_template->assign('featureFlags', $this->_workflow->getFeatureFlags());
        $this->_template->assign('interviewerProfiles', $this->_workflow->getInterviewerProfiles());
        $this->_template->assign('assignmentRules', $this->_workflow->getInterviewerRoleRules());
        $this->_template->assign('assignmentRuleExamples', NESPWorkflow::getDefaultAssignmentRuleExamples());
        $this->_template->assign('availabilityTemplate', NESPWorkflow::getDefaultAvailabilityTemplate());
        $this->_template->assign('interviewerAvailability', $this->_workflow->getInterviewerAvailability());
        $this->_template->assign('scorecards', $this->_workflow->getScorecardSummaries(50));
        $this->_template->assign('summary', $this->_workflow->getInterviewerAccessSummary());
        $this->_template->display('./modules/nesp/Settings.tpl');
    }

    private function saveFeatureFlags()
    {
        $enabledFlags = isset($_POST['featureFlags']) && is_array($_POST['featureFlags'])
            ? $_POST['featureFlags'] : array();

        foreach (NESPWorkflow::getRequiredFeatureFlagKeys() as $flagKey)
        {
            $this->_workflow->updateFeatureFlag(
                $flagKey,
                in_array($flagKey, $enabledFlags) ? 1 : 0,
                $this->_userID
            );
        }

        CATSUtility::transferRelativeURI('m=nesp&a=settings');
    }

    private function createInterviewer()
    {
        $displayName = isset($_POST['displayName']) ? $_POST['displayName'] : '';
        $email = isset($_POST['email']) ? $_POST['email'] : '';
        $roleKey = isset($_POST['roleKey']) ? $_POST['roleKey'] : 'interviewer';

        if ($this->_workflow->createInactiveInterviewerProfile($displayName, $email, $roleKey, $this->_userID) === false)
        {
            CommonErrors::fatal(COMMONERROR_MISSINGFIELDS, $this, 'Interviewer display name is required.');
        }

        CATSUtility::transferRelativeURI('m=nesp&a=settings');
    }

    private function createInterviewerRoleRule()
    {
        $interviewerProfileID = isset($_POST['interviewerProfileID']) ? (int) $_POST['interviewerProfileID'] : 0;
        $jobOrderID = isset($_POST['jobOrderID']) ? (int) $_POST['jobOrderID'] : 0;
        $roleMatchText = isset($_POST['roleMatchText']) ? $_POST['roleMatchText'] : '';
        $assignmentMode = isset($_POST['assignmentMode']) ? $_POST['assignmentMode'] : 'suggest_only';
        $priority = isset($_POST['priority']) ? (int) $_POST['priority'] : 50;
        $notes = isset($_POST['notes']) ? $_POST['notes'] : '';

        if ($this->_workflow->createInterviewerRoleRule($interviewerProfileID, $jobOrderID, $roleMatchText, $assignmentMode, $priority, $notes, $this->_userID) === false)
        {
            CommonErrors::fatal(COMMONERROR_MISSINGFIELDS, $this, 'Choose an interviewer and enter a role match or job ID.');
        }

        CATSUtility::transferRelativeURI('m=nesp&a=settings');
    }

    private function createCandidateGrant()
    {
        $interviewerProfileID = isset($_POST['interviewerProfileID']) ? (int) $_POST['interviewerProfileID'] : 0;
        $candidateID = isset($_POST['candidateID']) ? (int) $_POST['candidateID'] : 0;
        $jobOrderID = isset($_POST['jobOrderID']) ? (int) $_POST['jobOrderID'] : 0;

        if ($this->_workflow->createCandidateGrant($interviewerProfileID, $candidateID, $jobOrderID, $this->_userID) === false)
        {
            CommonErrors::fatal(COMMONERROR_MISSINGFIELDS, $this, 'Choose an interviewer, candidate ID, and job ID.');
        }

        CATSUtility::transferRelativeURI('m=nesp&a=settings');
    }

    private function createInterviewerAvailability()
    {
        $interviewerProfileID = isset($_POST['interviewerProfileID']) ? (int) $_POST['interviewerProfileID'] : 0;
        $weekdayKey = isset($_POST['weekdayKey']) ? $_POST['weekdayKey'] : '';
        $startTime = isset($_POST['startTime']) ? $_POST['startTime'] : '';
        $endTime = isset($_POST['endTime']) ? $_POST['endTime'] : '';
        $timezone = isset($_POST['timezone']) ? $_POST['timezone'] : '';
        $slotMinutes = isset($_POST['slotMinutes']) ? (int) $_POST['slotMinutes'] : 30;
        $bufferMinutes = isset($_POST['bufferMinutes']) ? (int) $_POST['bufferMinutes'] : 10;
        $notes = isset($_POST['notes']) ? $_POST['notes'] : '';

        if ($this->_workflow->createInterviewerAvailability($interviewerProfileID, $weekdayKey, $startTime, $endTime, $timezone, $slotMinutes, $bufferMinutes, $notes, $this->_userID) === false)
        {
            CommonErrors::fatal(COMMONERROR_MISSINGFIELDS, $this, 'Choose an interviewer, weekday, and valid start/end time.');
        }

        CATSUtility::transferRelativeURI('m=nesp&a=settings');
    }

    private function interviewerAccess()
    {
        $this->_template->assign('active', $this);
        $this->_template->assign('subActive', 'Settings');
        $this->_template->assign('summary', $this->_workflow->getInterviewerAccessSummary());
        $this->_template->assign('interviewerProfiles', $this->_workflow->getInterviewerProfiles());
        $this->_template->assign('interviewerAccountability', $this->_workflow->getInterviewerAccountability());
        $this->_template->display('./modules/nesp/InterviewerAccess.tpl');
    }

    private function auditLog()
    {
        $this->_template->assign('active', $this);
        $this->_template->assign('subActive', 'Audit Log');
        $this->_template->assign('auditEvents', $this->_workflow->getRecentAuditEvents(50));
        $this->_template->display('./modules/nesp/AuditLog.tpl');
    }

    private function assignedCandidates()
    {
        $this->_template->assign('active', $this);
        $this->_template->assign('subActive', 'Interviews');
        $this->_template->assign('assignedCandidates', $this->_workflow->getAssignedCandidatesForUser($this->_userID));
        $this->_template->display('./modules/nesp/AssignedCandidates.tpl');
    }

    private function assignedCandidate()
    {
        $candidateID = isset($_GET['candidateID']) ? (int) $_GET['candidateID'] : 0;
        $jobOrderID = isset($_GET['jobOrderID']) ? (int) $_GET['jobOrderID'] : 0;

        if ($candidateID <= 0 || $jobOrderID <= 0)
        {
            CommonErrors::fatal(COMMONERROR_BADINDEX, $this, 'Invalid assigned candidate.');
        }

        $detail = $this->_workflow->getAssignedCandidateDetail($this->_userID, $candidateID, $jobOrderID);
        if (empty($detail))
        {
            CommonErrors::fatal(COMMONERROR_PERMISSION, $this, 'This candidate is not assigned to you.');
        }

        $this->_template->assign('active', $this);
        $this->_template->assign('subActive', 'Interviews');
        $this->_template->assign('candidate', $detail);
        $this->_template->assign('scorecardQuestions', NESPWorkflow::getDefaultScorecardQuestions());
        $this->_template->display('./modules/nesp/AssignedCandidate.tpl');
    }

    private function submitScorecard()
    {
        $candidateID = isset($_POST['candidateID']) ? (int) $_POST['candidateID'] : 0;
        $jobOrderID = isset($_POST['jobOrderID']) ? (int) $_POST['jobOrderID'] : 0;
        $answers = isset($_POST['answers']) && is_array($_POST['answers']) ? $_POST['answers'] : array();
        $recommendation = isset($_POST['overallRecommendation']) ? $_POST['overallRecommendation'] : '';

        if ($candidateID <= 0 || $jobOrderID <= 0)
        {
            CommonErrors::fatal(COMMONERROR_BADINDEX, $this, 'Invalid scorecard candidate.');
        }

        $action = isset($_POST['scorecardAction']) ? $_POST['scorecardAction'] : 'submit';
        if ($action === 'saveDraft')
        {
            $result = $this->_workflow->saveScorecardDraft($this->_userID, $candidateID, $jobOrderID, $answers, $recommendation);
        }
        else
        {
            $result = $this->_workflow->submitScorecard($this->_userID, $candidateID, $jobOrderID, $answers, $recommendation);
        }

        if ($result === false)
        {
            CommonErrors::fatal(COMMONERROR_PERMISSION, $this, 'You cannot submit a scorecard for this candidate.');
        }

        CATSUtility::transferRelativeURI('m=nesp&a=assignedCandidate&candidateID=' . $candidateID . '&jobOrderID=' . $jobOrderID);
    }

    private function unlockScorecard()
    {
        $scorecardResponseID = isset($_POST['scorecardResponseID']) ? (int) $_POST['scorecardResponseID'] : 0;
        $candidateID = isset($_POST['candidateID']) ? (int) $_POST['candidateID'] : 0;
        $jobOrderID = isset($_POST['jobOrderID']) ? (int) $_POST['jobOrderID'] : 0;
        if ($scorecardResponseID <= 0)
        {
            CommonErrors::fatal(COMMONERROR_BADINDEX, $this, 'Invalid scorecard.');
        }

        $this->_workflow->unlockScorecard($this->_userID, $scorecardResponseID);
        CATSUtility::transferRelativeURI('m=nesp&a=assignedCandidate&candidateID=' . $candidateID . '&jobOrderID=' . $jobOrderID);
    }

    private function createStaffingRecommendation()
    {
        $forecast = $this->_workflow->getStaffingForecast();
        $this->_workflow->createDraftStaffingRecommendation(
            $this->_userID,
            'NESP staffing recommendation ' . date('Y-m-d'),
            array(
                'metrics' => $forecast['metrics'],
                'source_status' => $forecast['sourceStatus'],
                'created_from' => 'staffing_forecast_screen'
            )
        );
        CATSUtility::transferRelativeURI('m=nesp&a=staffingForecast');
    }

    private function staffingForecast()
    {
        $this->_template->assign('active', $this);
        $this->_template->assign('subActive', 'Staffing Forecast');
        $this->_template->assign('viewKey', 'staffingForecast');
        $this->_template->assign('dashboardNavigation', NESPWorkflow::getDashboardNavigation());
        $this->_template->assign('forecast', $this->_workflow->getStaffingForecast());
        $this->_template->display('./modules/nesp/StaffingForecast.tpl');
    }

    private function schemaMissing()
    {
        $this->_template->assign('active', $this);
        $this->_template->assign('subActive', 'Dashboard');
        $this->_template->display('./modules/nesp/SchemaMissing.tpl');
    }

    private function adminOnly()
    {
        if ($this->getUserAccessLevel('settings.administration') < ACCESS_LEVEL_SA)
        {
            CommonErrors::fatal(COMMONERROR_PERMISSION, $this, 'Administrator access is required.');
        }
    }

    private function requirePostCSRF()
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST')
        {
            CommonErrors::fatal(COMMONERROR_BADFIELDS, $this, 'Invalid request method.');
        }

        $token = isset($_POST['csrfToken']) ? $_POST['csrfToken'] : null;
        if (!isset($_SESSION['CATS']) || !$_SESSION['CATS']->isCSRFTokenValid($token))
        {
            CommonErrors::fatal(COMMONERROR_PERMISSION, $this, 'Invalid request token.');
        }
    }

    private function subTabFromView($viewKey)
    {
        if ($viewKey === 'waiting')
        {
            return 'Waiting';
        }
        if ($viewKey === 'interviews')
        {
            return 'Interviews';
        }
        if ($viewKey === 'completed')
        {
            return 'Completed';
        }
        return 'Needs Craig';
    }
}

?>
