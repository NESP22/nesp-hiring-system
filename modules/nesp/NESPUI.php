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
            'My Assignments' => CATSUtility::getIndexName() . '?m=nesp&amp;a=assignedCandidates*al=' . ACCESS_LEVEL_READ,
            'My Availability' => CATSUtility::getIndexName() . '?m=nesp&amp;a=myAvailability*al=' . ACCESS_LEVEL_READ,
            'Needs Craig' => CATSUtility::getIndexName() . '?m=nesp*al=' . ACCESS_LEVEL_SA,
            'Waiting' => CATSUtility::getIndexName() . '?m=nesp&amp;a=waiting*al=' . ACCESS_LEVEL_SA,
            'Interviews' => CATSUtility::getIndexName() . '?m=nesp&amp;a=interviews*al=' . ACCESS_LEVEL_SA,
            'Questionnaires' => CATSUtility::getIndexName() . '?m=nesp&amp;a=questionnaires*al=' . ACCESS_LEVEL_SA,
            'Manage Question Sets' => CATSUtility::getIndexName() . '?m=nesp&amp;a=questionSets*al=' . ACCESS_LEVEL_SA,
            'Phone Screens' => CATSUtility::getIndexName() . '?m=nesp&amp;a=phoneScreens*al=' . ACCESS_LEVEL_SA,
            'Job Ads' => CATSUtility::getIndexName() . '?m=nesp&amp;a=jobAds*al=' . ACCESS_LEVEL_SA,
            'Completed' => CATSUtility::getIndexName() . '?m=nesp&amp;a=completed*al=' . ACCESS_LEVEL_SA,
            'Staffing Forecast' => CATSUtility::getIndexName() . '?m=nesp&amp;a=staffingForecast*al=' . ACCESS_LEVEL_SA,
            'Interviewer Settings' => CATSUtility::getIndexName() . '?m=nesp&amp;a=settings*al=' . ACCESS_LEVEL_SA
        );

        $this->_workflow = new NESPWorkflow();
    }

    public function handleRequest()
    {
        $action = $this->getAction();

        if (($action === null || $action === '' || $action === 'dashboard') &&
            $this->getUserAccessLevel('settings.administration') < ACCESS_LEVEL_SA)
        {
            $action = 'assignedCandidates';
        }

        if (!$this->_workflow->isSchemaInstalled())
        {
            $this->schemaMissing();
            return;
        }

        $featureFlagKey = NESPWorkflow::getFeatureFlagForAction($action);
        if ($featureFlagKey !== '' && !$this->_workflow->isFeatureFlagEnabled($featureFlagKey))
        {
            if ($_SERVER['REQUEST_METHOD'] === 'POST')
            {
                $this->requirePostCSRF();
            }
            $this->featureDisabled($featureFlagKey);
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

            case 'googleCalendarConnect':
            case 'googleCalendarReauthorize':
                $this->requirePostCSRF();
                $this->googleCalendarConnect();
                break;

            case 'googleCalendarDisconnect':
                $this->requirePostCSRF();
                $this->googleCalendarDisconnect();
                break;

            case 'createInterviewer':
                $this->adminOnly();
                $this->requirePostCSRF();
                $this->createInterviewer();
                break;

            case 'updateInterviewerSettings':
                $this->adminOnly();
                $this->requirePostCSRF();
                $this->updateInterviewerSettings();
                break;

            case 'prepareInterviewerLogin':
            case 'activateInterviewerLogin':
            case 'suspendInterviewerLogin':
            case 'reactivateInterviewerLogin':
            case 'resetInterviewerTempPassword':
            case 'disableInterviewerLogin':
                $this->adminOnly();
                $this->requirePostCSRF();
                $this->interviewerLoginAction($action);
                break;

            case 'createInterviewerRoleRule':
                $this->adminOnly();
                $this->requirePostCSRF();
                $this->createInterviewerRoleRule();
                break;

            case 'deactivateInterviewerRoleRule':
                $this->adminOnly();
                $this->requirePostCSRF();
                $this->deactivateInterviewerRoleRule();
                break;

            case 'createCandidateGrant':
                $this->adminOnly();
                $this->requirePostCSRF();
                $this->createCandidateGrant();
                break;

            case 'assignInterviewer':
                $this->adminOnly();
                $this->requirePostCSRF();
                $this->assignInterviewer();
                break;

            case 'revokeCandidateGrant':
                $this->adminOnly();
                $this->requirePostCSRF();
                $this->revokeCandidateGrant();
                break;

            case 'createInterviewerAvailability':
                $this->requirePostCSRF();
                $this->createInterviewerAvailability();
                break;

            case 'myAvailability':
                $this->myAvailability();
                break;

            case 'setInterviewerAvailabilityStatus':
                $this->requirePostCSRF();
                $this->setInterviewerAvailabilityStatus();
                break;

            case 'updateInterviewerZoomLink':
                $this->requirePostCSRF();
                $this->updateInterviewerZoomLink();
                break;

            case 'updateInterviewerKoalendarLink':
                $this->requirePostCSRF();
                $this->updateInterviewerKoalendarLink();
                break;

            case 'createInterviewerAvailabilityOverride':
                $this->requirePostCSRF();
                $this->createInterviewerAvailabilityOverride();
                break;

            case 'createInterviewerBlackout':
                $this->requirePostCSRF();
                $this->createInterviewerBlackout();
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

            case 'phoneScreens':
                $this->adminOnly();
                $this->phoneScreens();
                break;

            case 'questionnaires':
                $this->adminOnly();
                $this->questionnaires();
                break;

            case 'questionSets':
                $this->adminOnly();
                $this->questionSets();
                break;

            case 'duplicateQuestionSetDraft':
                $this->adminOnly();
                $this->requirePostCSRF();
                $this->duplicateQuestionSetDraft();
                break;

            case 'saveQuestionSetDraft':
                $this->adminOnly();
                $this->requirePostCSRF();
                $this->saveQuestionSetDraft();
                break;

            case 'publishQuestionSetDraft':
                $this->adminOnly();
                $this->requirePostCSRF();
                $this->publishQuestionSetDraft();
                break;

            case 'archiveQuestionSet':
                $this->adminOnly();
                $this->requirePostCSRF();
                $this->archiveQuestionSet();
                break;

            case 'confirmQuestionnaire':
                $this->adminOnly();
                $this->confirmQuestionnaire();
                break;

            case 'collectContactDetails':
                $this->adminOnly();
                $this->collectContactDetails();
                break;

            case 'saveContactDetails':
                $this->adminOnly();
                $this->requirePostCSRF();
                $this->saveContactDetails();
                break;

            case 'requestQuestionnaire':
                $this->adminOnly();
                $this->requirePostCSRF();
                $this->requestQuestionnaire();
                break;

            case 'sendQuestionnaireEmail':
                $this->adminOnly();
                $this->requirePostCSRF();
                $this->sendQuestionnaireEmail();
                break;

            case 'confirmBulkQuestionnaireEmails':
                $this->adminOnly();
                $this->confirmBulkQuestionnaireEmails();
                break;

            case 'sendBulkQuestionnaireEmails':
                $this->adminOnly();
                $this->requirePostCSRF();
                $this->sendBulkQuestionnaireEmails();
                break;

            case 'confirmQuestionnaireNonresponseClosure':
                $this->adminOnly();
                $this->confirmQuestionnaireNonresponseClosure();
                break;

            case 'closeQuestionnaireNonresponse':
                $this->adminOnly();
                $this->requirePostCSRF();
                $this->closeQuestionnaireNonresponse();
                break;

            case 'confirmQuestionnaireReminderReview':
                $this->adminOnly();
                $this->confirmQuestionnaireReminderReview();
                break;

            case 'resolveQuestionnaireReminderReview':
                $this->adminOnly();
                $this->requirePostCSRF();
                $this->resolveQuestionnaireReminderReview();
                break;

            case 'reviewQuestionnaire':
                $this->reviewQuestionnaire();
                break;

            case 'markQuestionnaireInvitationCopied':
                $this->adminOnly();
                $this->requirePostCSRF();
                $this->markQuestionnaireInvitationCopied();
                break;

            case 'revokeQuestionnaireLink':
                $this->adminOnly();
                $this->requirePostCSRF();
                $this->revokeQuestionnaireLink();
                break;

            case 'regenerateQuestionnaireLink':
                $this->adminOnly();
                $this->requirePostCSRF();
                $this->regenerateQuestionnaireLink();
                break;

            case 'assignQuestionnaireReviewer':
                $this->adminOnly();
                $this->requirePostCSRF();
                $this->assignQuestionnaireReviewer();
                break;

            case 'saveQuestionnaireReview':
                $this->requirePostCSRF();
                $this->saveQuestionnaireReview();
                break;

            case 'scheduleInterview':
                $this->adminOnly();
                $this->scheduleInterview();
                break;

            case 'saveManualInterview':
                $this->adminOnly();
                $this->requirePostCSRF();
                $this->saveManualInterview();
                break;

            case 'cancelInterview':
                $this->adminOnly();
                $this->cancelInterview();
                break;

            case 'confirmCancelInterview':
                $this->adminOnly();
                $this->requirePostCSRF();
                $this->confirmCancelInterview();
                break;

            case 'recordInterviewOutcome':
                $this->adminOnly();
                $this->recordInterviewOutcome();
                break;

            case 'saveInterviewOutcome':
                $this->adminOnly();
                $this->requirePostCSRF();
                $this->saveInterviewOutcome();
                break;

            case 'markManualInterviewInvitationSent':
                $this->adminOnly();
                $this->requirePostCSRF();
                $this->markManualInterviewInvitationSent();
                break;

            case 'regenerateTrackedInterviewLink':
                $this->adminOnly();
                $this->requirePostCSRF();
                $this->regenerateTrackedInterviewLink();
                break;

            case 'jobAds':
                $this->adminOnly();
                $this->jobAds();
                break;

            case 'saveRecruitingCampaignControl':
                $this->adminOnly();
                $this->requirePostCSRF();
                $this->saveRecruitingCampaignControl();
                break;

            case 'phoneScreenAvailability':
                $this->adminOnly();
                $this->phoneScreenAvailability();
                break;

            case 'savePhoneScreenAvailability':
                $this->adminOnly();
                $this->requirePostCSRF();
                $this->savePhoneScreenAvailability();
                break;

            case 'createPhoneScreenAvailabilityBlock':
                $this->adminOnly();
                $this->requirePostCSRF();
                $this->createPhoneScreenAvailabilityBlock();
                break;

            case 'deletePhoneScreenAvailabilityBlock':
                $this->adminOnly();
                $this->requirePostCSRF();
                $this->deletePhoneScreenAvailabilityBlock();
                break;

            case 'createPhoneScreenBlackout':
                $this->adminOnly();
                $this->requirePostCSRF();
                $this->createPhoneScreenBlackout();
                break;

            case 'deletePhoneScreenBlackout':
                $this->adminOnly();
                $this->requirePostCSRF();
                $this->deletePhoneScreenBlackout();
                break;

            case 'confirmPhoneScreen':
                $this->adminOnly();
                $this->confirmPhoneScreen();
                break;

            case 'reviewPhoneScreen':
                $this->adminOnly();
                $this->reviewPhoneScreen();
                break;

            case 'requestPhoneScreen':
                $this->adminOnly();
                $this->requirePostCSRF();
                $this->requestPhoneScreen();
                break;

            case 'markPhoneScreenInvitationCopied':
                $this->adminOnly();
                $this->requirePostCSRF();
                $this->markPhoneScreenInvitationCopied();
                break;

            case 'cancelPhoneScreen':
                $this->adminOnly();
                $this->requirePostCSRF();
                $this->cancelPhoneScreen();
                break;

            case 'revokePhoneScreenSchedulingLink':
                $this->adminOnly();
                $this->requirePostCSRF();
                $this->revokePhoneScreenSchedulingLink();
                break;

            case 'allowPhoneScreenReschedule':
                $this->adminOnly();
                $this->requirePostCSRF();
                $this->allowPhoneScreenReschedule();
                break;

            case 'savePhoneScreenReview':
                $this->adminOnly();
                $this->requirePostCSRF();
                $this->savePhoneScreenReview();
                break;

            case 'createStaffingRecommendation':
                $this->adminOnly();
                $this->requirePostCSRF();
                $this->createStaffingRecommendation();
                break;

            case 'dryRunStaffingImport':
                $this->adminOnly();
                $this->requirePostCSRF();
                $this->dryRunStaffingImport();
                break;

            case 'importApprovedStaffingRows':
                $this->adminOnly();
                $this->requirePostCSRF();
                $this->importApprovedStaffingRows();
                break;

            case 'staffingForecast':
                $this->adminOnly();
                $this->staffingForecast();
                break;

            case 'waiting':
            case 'interviews':
            case 'completed':
            case 'dashboard':
            default:
                $this->adminOnly();
                $this->dashboard($action);
                break;
        }
    }

    private function dashboard($viewKey = 'dashboard')
    {
        if (!in_array($viewKey, array('dashboard', 'waiting', 'interviews', 'completed')))
        {
            $viewKey = 'dashboard';
        }

        $queues = $this->_workflow->getDashboardQueues();
        $this->_template->assign('active', $this);
        $this->_template->assign('subActive', $this->subTabFromView($viewKey));
        $this->_template->assign('viewKey', $viewKey);
        $this->_template->assign('dashboardNavigation', NESPWorkflow::getDashboardNavigation());
        $this->_template->assign('summary', $this->_workflow->getDashboardSummary());
        $this->_template->assign('queueCounts', $this->_workflow->getDashboardQueueCounts());
        $this->_template->assign('queueDefinitions', NESPWorkflow::getQueueDefinitions());
        $this->_template->assign('queues', $queues);
        $this->_template->assign('upcomingInterviews', $this->_workflow->getUpcomingInterviews(20));
        $this->_template->assign('integrationStatuses', $this->_workflow->getIntegrationStatuses());
        $this->_template->assign('workflowStages', $this->_workflow->getWorkflowStages());
        $this->_template->assign('assignmentSuggestions', $this->_workflow->getAssignmentSuggestions(50));
        $this->_template->assign('interviewerAccountability', $this->_workflow->getInterviewerAccountability());
        $this->_template->assign('canAssignInterviewers', $this->_workflow->isFeatureFlagEnabled('NESP_INTERVIEWER_POOL_ENABLED'));
        $this->_template->assign('bulkQuestionnairePreview', $this->_workflow->getBulkQuestionnaireEmailPreview(200));
        $bulkQuestionnaireMessage = isset($_SESSION['NESP_BULK_QUESTIONNAIRE_MESSAGE'])
            ? $_SESSION['NESP_BULK_QUESTIONNAIRE_MESSAGE'] : '';
        $bulkQuestionnaireItems = isset($_SESSION['NESP_BULK_QUESTIONNAIRE_ITEMS'])
            ? $_SESSION['NESP_BULK_QUESTIONNAIRE_ITEMS'] : array();
        unset($_SESSION['NESP_BULK_QUESTIONNAIRE_MESSAGE'], $_SESSION['NESP_BULK_QUESTIONNAIRE_ITEMS']);
        $this->_template->assign('bulkQuestionnaireMessage', $bulkQuestionnaireMessage);
        $this->_template->assign('bulkQuestionnaireItems', $bulkQuestionnaireItems);
        $assignmentMessage = isset($_SESSION['NESP_ASSIGNMENT_MESSAGE']) ? $_SESSION['NESP_ASSIGNMENT_MESSAGE'] : '';
        unset($_SESSION['NESP_ASSIGNMENT_MESSAGE']);
        $this->_template->assign('assignmentMessage', $assignmentMessage);
        $this->_template->display('./modules/nesp/Dashboard.tpl');
    }

    private function settings($oneTimeLoginDetails = array())
    {
        $this->_template->assign('active', $this);
        $this->_template->assign('subActive', 'Interviewer Settings');
        $this->_template->assign('viewKey', 'settings');
        $temporaryLoginMessage = isset($_SESSION['NESP_INTERVIEWER_TEMP_LOGIN_MESSAGE'])
            ? $_SESSION['NESP_INTERVIEWER_TEMP_LOGIN_MESSAGE'] : '';
        unset($_SESSION['NESP_INTERVIEWER_TEMP_LOGIN_MESSAGE']);
        $this->_template->assign('temporaryLoginMessage', $temporaryLoginMessage);
        $this->_template->assign('oneTimeLoginDetails', $oneTimeLoginDetails);
        $this->_template->assign('dashboardNavigation', NESPWorkflow::getDashboardNavigation());
        $this->_template->assign('featureFlags', $this->_workflow->getFeatureFlags());
        $this->_template->assign('interviewerProfiles', $this->_workflow->getInterviewerProfiles());
        $this->_template->assign('candidateGrants', $this->_workflow->getActiveCandidateGrants());
        $this->_template->assign('jobRoleOptions', NESPWorkflow::getInterviewerJobRoleOptions());
        $this->_template->assign('accountStates', NESPWorkflow::getInterviewerAccountStates());
        $this->_template->assign('seedProfiles', NESPWorkflow::getApprovedRealInterviewerSeedProfiles());
        $this->_template->assign('assignmentRules', $this->_workflow->getInterviewerRoleRules());
        $this->_template->assign('assignmentRuleExamples', NESPWorkflow::getDefaultAssignmentRuleExamples());
        $this->_template->assign('availabilityTemplate', NESPWorkflow::getDefaultAvailabilityTemplate());
        $this->_template->assign('interviewerAvailability', $this->_workflow->getInterviewerAvailability());
        $this->_template->assign('availabilityOverrides', $this->_workflow->getInterviewerAvailabilityOverrides());
        $this->_template->assign('interviewerBlackouts', $this->_workflow->getInterviewerBlackouts());
        $this->_template->assign('scorecards', $this->_workflow->getScorecardSummaries(50));
        $this->_template->assign('summary', $this->_workflow->getInterviewerAccessSummary());
        $this->_template->assign('vapiConfiguration', $this->_workflow->getVapiConfigurationStatus());
        $googleCalendarMessage = isset($_SESSION['NESP_GOOGLE_CALENDAR_MESSAGE'])
            ? $_SESSION['NESP_GOOGLE_CALENDAR_MESSAGE'] : '';
        unset($_SESSION['NESP_GOOGLE_CALENDAR_MESSAGE']);
        $this->_template->assign('googleCalendarMessage', $googleCalendarMessage);
        $this->_template->assign('googleCalendarConfiguration', $this->_workflow->getGoogleCalendarConfigurationStatus());
        $this->_template->assign('googleCalendarConnections', $this->_workflow->getGoogleCalendarConnections());
        $this->_template->display('./modules/nesp/Settings.tpl');
    }

    private function saveFeatureFlags()
    {
        $enabledFlags = isset($_POST['featureFlags']) && is_array($_POST['featureFlags'])
            ? $_POST['featureFlags'] : array();

        $applicantEmailRequested = in_array('NESP_APPLICANT_EMAIL_ENABLED', $enabledFlags);
        $applicantEmailWasEnabled = $this->_workflow->isFeatureFlagEnabled('NESP_APPLICANT_EMAIL_ENABLED');
        $applicantEmailConfirmation = isset($_POST['confirmApplicantQuestionnaireEmail'])
            ? $_POST['confirmApplicantQuestionnaireEmail'] : '';
        if (!NESPWorkflow::canEnableApplicantEmail(
            $applicantEmailWasEnabled,
            $applicantEmailRequested,
            $applicantEmailConfirmation
        ))
        {
            CommonErrors::fatal(
                COMMONERROR_BADFIELDS,
                $this,
                'Confirm Applicant Questionnaire Email before enabling automatic questionnaire delivery.'
            );
        }

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

    private function googleCalendarConnect()
    {
        $interviewerProfileID = isset($_POST['interviewerProfileID']) ? (int) $_POST['interviewerProfileID'] : 0;
        if ($this->getUserAccessLevel('settings.administration') < ACCESS_LEVEL_SA)
        {
            $profile = $this->_workflow->getInterviewerProfileForUser($this->_userID);
            if (empty($profile) || (int) $profile['interviewer_profile_id'] !== $interviewerProfileID)
            {
                CommonErrors::fatal(COMMONERROR_PERMISSION, $this, 'You can manage only your own Google Calendar free/busy connection.');
            }
        }

        $result = $this->_workflow->requestGoogleCalendarAuthorization($interviewerProfileID, $this->_userID);
        if ($result === false)
        {
            CommonErrors::fatal(COMMONERROR_BADFIELDS, $this, 'Choose an interviewer before preparing Google Calendar authorization.');
        }

        $_SESSION['NESP_GOOGLE_CALENDAR_MESSAGE'] =
            'Authorization prepared for ' . $result['display_name'] . '. Use the Google consent URL only in an approved test environment: ' . $result['authorization_url'];

        CATSUtility::transferRelativeURI($this->getUserAccessLevel('settings.administration') >= ACCESS_LEVEL_SA ? 'm=nesp&a=settings' : 'm=nesp&a=myAvailability');
    }

    private function googleCalendarDisconnect()
    {
        $interviewerProfileID = isset($_POST['interviewerProfileID']) ? (int) $_POST['interviewerProfileID'] : 0;
        if ($this->getUserAccessLevel('settings.administration') < ACCESS_LEVEL_SA)
        {
            $profile = $this->_workflow->getInterviewerProfileForUser($this->_userID);
            if (empty($profile) || (int) $profile['interviewer_profile_id'] !== $interviewerProfileID)
            {
                CommonErrors::fatal(COMMONERROR_PERMISSION, $this, 'You can manage only your own Google Calendar free/busy connection.');
            }
        }

        if ($interviewerProfileID <= 0 || !$this->_workflow->disconnectGoogleCalendar($interviewerProfileID, $this->_userID))
        {
            CommonErrors::fatal(COMMONERROR_BADFIELDS, $this, 'Choose an interviewer calendar connection to disconnect.');
        }

        $_SESSION['NESP_GOOGLE_CALENDAR_MESSAGE'] = 'Google Calendar free/busy connection disconnected and stored tokens removed.';
        CATSUtility::transferRelativeURI($this->getUserAccessLevel('settings.administration') >= ACCESS_LEVEL_SA ? 'm=nesp&a=settings' : 'm=nesp&a=myAvailability');
    }

    private function createInterviewer()
    {
        $displayName = isset($_POST['displayName']) ? $_POST['displayName'] : '';
        $email = isset($_POST['email']) ? $_POST['email'] : '';
        $roleKey = isset($_POST['roleKey']) ? $_POST['roleKey'] : 'interviewer';
        $approvedJobs = isset($_POST['approvedJobOrderIDs']) && is_array($_POST['approvedJobOrderIDs']) ? $_POST['approvedJobOrderIDs'] : array();
        $options = array(
            'account_state_key' => 'profile_created',
            'approved_joborder_ids' => $approvedJobs,
            'timezone' => isset($_POST['timezone']) ? $_POST['timezone'] : 'America/New_York',
            'max_interviews_per_day' => isset($_POST['maxInterviewsPerDay']) ? $_POST['maxInterviewsPerDay'] : 3,
            'max_interviews_per_week' => isset($_POST['maxInterviewsPerWeek']) ? $_POST['maxInterviewsPerWeek'] : 12,
            'min_notice_minutes' => isset($_POST['minNoticeMinutes']) ? $_POST['minNoticeMinutes'] : 1440,
            'default_interview_minutes' => isset($_POST['defaultInterviewMinutes']) ? $_POST['defaultInterviewMinutes'] : 30,
            'buffer_minutes' => isset($_POST['bufferMinutes']) ? $_POST['bufferMinutes'] : 15,
            'earliest_time' => isset($_POST['earliestTime']) ? $_POST['earliestTime'] : '09:00',
            'latest_time' => isset($_POST['latestTime']) ? $_POST['latestTime'] : '17:00',
            'craig_must_attend' => isset($_POST['craigMustAttend']) ? 1 : 0,
            'may_recommend' => isset($_POST['mayRecommend']) ? 1 : 0,
            'private_admin_notes' => isset($_POST['privateAdminNotes']) ? $_POST['privateAdminNotes'] : '',
            'email_warning' => isset($_POST['emailWarning']) ? $_POST['emailWarning'] : '',
            'default_zoom_join_url' => isset($_POST['defaultZoomJoinURL']) ? $_POST['defaultZoomJoinURL'] : '',
            'koalendar_booking_url' => isset($_POST['koalendarBookingURL']) ? $_POST['koalendarBookingURL'] : '',
            'temporary_password' => isset($_POST['temporaryPassword']) ? $_POST['temporaryPassword'] : ''
        );

        $result = $this->_workflow->createInactiveInterviewerProfile($displayName, $email, $roleKey, $this->_userID, $options);
        if ($result === false)
        {
            CommonErrors::fatal(COMMONERROR_MISSINGFIELDS, $this, 'Interviewer display name is required.');
        }
        if (is_array($result) && isset($result['ok']) && empty($result['ok']))
        {
            CommonErrors::fatal(COMMONERROR_BADFIELDS, $this, isset($result['error']) ? $result['error'] : 'Unable to create interviewer profile.');
        }
        if (is_array($result) && !empty($result['temporary_login_message']))
        {
            $this->settings(isset($result['one_time_login_details']) ? $result['one_time_login_details'] : array());
            return;
        }

        CATSUtility::transferRelativeURI('m=nesp&a=settings');
    }

    private function updateInterviewerSettings()
    {
        $interviewerProfileID = isset($_POST['interviewerProfileID']) ? (int) $_POST['interviewerProfileID'] : 0;
        $settings = array(
            'display_name' => isset($_POST['displayName']) ? $_POST['displayName'] : '',
            'email' => isset($_POST['email']) ? $_POST['email'] : '',
            'role_key' => isset($_POST['roleKey']) ? $_POST['roleKey'] : 'interviewer',
            'availability_status_key' => isset($_POST['availabilityStatusKey']) ? $_POST['availabilityStatusKey'] : 'open',
            'availability_closed_until' => isset($_POST['availabilityClosedUntil']) ? $_POST['availabilityClosedUntil'] : '',
            'availability_close_reason' => isset($_POST['availabilityCloseReason']) ? $_POST['availabilityCloseReason'] : '',
            'timezone' => isset($_POST['timezone']) ? $_POST['timezone'] : 'America/New_York',
            'max_interviews_per_day' => isset($_POST['maxInterviewsPerDay']) ? $_POST['maxInterviewsPerDay'] : 3,
            'max_interviews_per_week' => isset($_POST['maxInterviewsPerWeek']) ? $_POST['maxInterviewsPerWeek'] : 12,
            'min_notice_minutes' => isset($_POST['minNoticeMinutes']) ? $_POST['minNoticeMinutes'] : 1440,
            'default_interview_minutes' => isset($_POST['defaultInterviewMinutes']) ? $_POST['defaultInterviewMinutes'] : 30,
            'buffer_minutes' => isset($_POST['bufferMinutes']) ? $_POST['bufferMinutes'] : 15,
            'earliest_time' => isset($_POST['earliestTime']) ? $_POST['earliestTime'] : '09:00',
            'latest_time' => isset($_POST['latestTime']) ? $_POST['latestTime'] : '17:00',
            'craig_must_attend' => isset($_POST['craigMustAttend']) ? 1 : 0,
            'may_recommend' => isset($_POST['mayRecommend']) ? 1 : 0,
            'private_admin_notes' => isset($_POST['privateAdminNotes']) ? $_POST['privateAdminNotes'] : '',
            'email_warning' => isset($_POST['emailWarning']) ? $_POST['emailWarning'] : '',
            'default_zoom_join_url' => isset($_POST['defaultZoomJoinURL']) ? $_POST['defaultZoomJoinURL'] : '',
            'koalendar_booking_url' => isset($_POST['koalendarBookingURL']) ? $_POST['koalendarBookingURL'] : '',
            'approved_joborder_ids' => isset($_POST['approvedJobOrderIDs']) && is_array($_POST['approvedJobOrderIDs']) ? $_POST['approvedJobOrderIDs'] : array()
        );

        $result = $this->_workflow->updateInterviewerSettings($interviewerProfileID, $settings, $this->_userID);
        if ($result === false)
        {
            CommonErrors::fatal(COMMONERROR_BADFIELDS, $this, 'Unable to update interviewer settings.');
        }
        if (is_array($result) && isset($result['ok']) && empty($result['ok']))
        {
            CommonErrors::fatal(COMMONERROR_BADFIELDS, $this, isset($result['error']) ? $result['error'] : 'Unable to update interviewer settings.');
        }
        CATSUtility::transferRelativeURI('m=nesp&a=settings');
    }

    private function interviewerLoginAction($action)
    {
        $interviewerProfileID = isset($_POST['interviewerProfileID']) ? (int) $_POST['interviewerProfileID'] : 0;
        $temporaryPassword = isset($_POST['temporaryPassword']) ? $_POST['temporaryPassword'] : '';
        $result = $this->_workflow->interviewerLoginLifecycleAction($interviewerProfileID, $action, $temporaryPassword, $this->_userID);
        if (empty($result['ok']))
        {
            CommonErrors::fatal(COMMONERROR_BADFIELDS, $this, isset($result['error']) ? $result['error'] : 'Unable to update interviewer login.');
        }
        if (!empty($result['one_time_login_details']))
        {
            $this->settings($result['one_time_login_details']);
            return;
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

    private function deactivateInterviewerRoleRule()
    {
        $ruleID = isset($_POST['roleRuleID']) ? (int) $_POST['roleRuleID'] : 0;
        if ($this->_workflow->deactivateInterviewerRoleRule($ruleID, $this->_userID) === false)
        {
            CommonErrors::fatal(COMMONERROR_BADFIELDS, $this, 'Choose an active routing rule to remove.');
        }

        CATSUtility::transferRelativeURI('m=nesp&a=settings');
    }

    private function myAvailability()
    {
        $profile = $this->_workflow->getInterviewerProfileForUser($this->_userID);
        if (empty($profile))
        {
            $this->_template->assign('active', $this);
            $this->_template->assign('subActive', 'My Availability');
            $this->_template->assign('canManageInterviewerProfiles', $this->getUserAccessLevel('settings.administration') >= ACCESS_LEVEL_SA);
            $this->_template->display('./modules/nesp/MyAvailabilitySetupRequired.tpl');
            return;
        }

        $this->_template->assign('active', $this);
        $this->_template->assign('subActive', 'Interviews');
        $this->_template->assign('profile', $profile);
        $this->_template->assign('availability', $this->_workflow->getAvailabilityForProfile($profile['interviewer_profile_id']));
        $this->_template->assign('availabilityTemplate', NESPWorkflow::getDefaultAvailabilityTemplate());
        $this->_template->assign('googleCalendarConfiguration', $this->_workflow->getGoogleCalendarConfigurationStatus());
        $this->_template->assign('googleCalendarConnection', $this->_workflow->getGoogleCalendarConnectionForInterviewer($profile['interviewer_profile_id']));
        $this->_template->display('./modules/nesp/MyAvailability.tpl');
    }

    private function setInterviewerAvailabilityStatus()
    {
        $interviewerProfileID = isset($_POST['interviewerProfileID']) ? (int) $_POST['interviewerProfileID'] : 0;
        if ($this->getUserAccessLevel('settings.administration') < ACCESS_LEVEL_SA)
        {
            $profile = $this->_workflow->getInterviewerProfileForUser($this->_userID);
            if (empty($profile) || (int) $profile['interviewer_profile_id'] !== $interviewerProfileID)
            {
                CommonErrors::fatal(COMMONERROR_PERMISSION, $this, 'You can edit only your own availability.');
            }
        }

        $statusKey = isset($_POST['availabilityStatusKey']) ? $_POST['availabilityStatusKey'] : 'open';
        $reason = isset($_POST['availabilityCloseReason']) ? $_POST['availabilityCloseReason'] : '';
        $closedUntil = isset($_POST['availabilityClosedUntil']) ? $_POST['availabilityClosedUntil'] : '';
        if ($this->_workflow->setInterviewerAvailabilityStatus($interviewerProfileID, $statusKey, $reason, $closedUntil, $this->_userID) === false)
        {
            CommonErrors::fatal(COMMONERROR_BADFIELDS, $this, 'Unable to update availability status.');
        }

        CATSUtility::transferRelativeURI($this->getUserAccessLevel('settings.administration') >= ACCESS_LEVEL_SA ? 'm=nesp&a=settings' : 'm=nesp&a=myAvailability');
    }

    private function updateInterviewerZoomLink()
    {
        $interviewerProfileID = isset($_POST['interviewerProfileID']) ? (int) $_POST['interviewerProfileID'] : 0;
        if ($this->getUserAccessLevel('settings.administration') < ACCESS_LEVEL_SA)
        {
            $profile = $this->_workflow->getInterviewerProfileForUser($this->_userID);
            if (empty($profile) || (int) $profile['interviewer_profile_id'] !== $interviewerProfileID)
            {
                CommonErrors::fatal(COMMONERROR_PERMISSION, $this, 'You can edit only your own Zoom participant link.');
            }
        }

        $joinURL = isset($_POST['defaultZoomJoinURL']) ? $_POST['defaultZoomJoinURL'] : '';
        $result = $this->_workflow->updateInterviewerDefaultZoomJoinURL($interviewerProfileID, $joinURL, $this->_userID);
        if (empty($result['ok']))
        {
            CommonErrors::fatal(COMMONERROR_BADFIELDS, $this, isset($result['error']) ? $result['error'] : 'Unable to update Zoom participant link.');
        }

        CATSUtility::transferRelativeURI($this->getUserAccessLevel('settings.administration') >= ACCESS_LEVEL_SA ? 'm=nesp&a=settings' : 'm=nesp&a=myAvailability');
    }

    private function updateInterviewerKoalendarLink()
    {
        $interviewerProfileID = isset($_POST['interviewerProfileID']) ? (int) $_POST['interviewerProfileID'] : 0;
        if ($this->getUserAccessLevel('settings.administration') < ACCESS_LEVEL_SA)
        {
            $profile = $this->_workflow->getInterviewerProfileForUser($this->_userID);
            if (empty($profile) || (int) $profile['interviewer_profile_id'] !== $interviewerProfileID)
            {
                CommonErrors::fatal(COMMONERROR_PERMISSION, $this, 'You can edit only your own Koalendar booking link.');
            }
        }

        $bookingURL = isset($_POST['koalendarBookingURL']) ? $_POST['koalendarBookingURL'] : '';
        $result = $this->_workflow->updateInterviewerKoalendarBookingURL($interviewerProfileID, $bookingURL, $this->_userID);
        if (empty($result['ok']))
        {
            CommonErrors::fatal(COMMONERROR_BADFIELDS, $this, isset($result['error']) ? $result['error'] : 'Unable to update Koalendar booking link.');
        }

        CATSUtility::transferRelativeURI($this->getUserAccessLevel('settings.administration') >= ACCESS_LEVEL_SA ? 'm=nesp&a=settings' : 'm=nesp&a=myAvailability');
    }

    private function createInterviewerAvailabilityOverride()
    {
        $interviewerProfileID = isset($_POST['interviewerProfileID']) ? (int) $_POST['interviewerProfileID'] : 0;
        if ($this->getUserAccessLevel('settings.administration') < ACCESS_LEVEL_SA)
        {
            $profile = $this->_workflow->getInterviewerProfileForUser($this->_userID);
            if (empty($profile) || (int) $profile['interviewer_profile_id'] !== $interviewerProfileID)
            {
                CommonErrors::fatal(COMMONERROR_PERMISSION, $this, 'You can edit only your own availability.');
            }
        }

        $result = $this->_workflow->createInterviewerAvailabilityOverride(
            $interviewerProfileID,
            isset($_POST['overrideDate']) ? $_POST['overrideDate'] : '',
            isset($_POST['overrideTypeKey']) ? $_POST['overrideTypeKey'] : 'available',
            isset($_POST['startTime']) ? $_POST['startTime'] : '',
            isset($_POST['endTime']) ? $_POST['endTime'] : '',
            isset($_POST['timezone']) ? $_POST['timezone'] : 'America/New_York',
            isset($_POST['privateReason']) ? $_POST['privateReason'] : '',
            $this->_userID
        );
        if ($result === false)
        {
            CommonErrors::fatal(COMMONERROR_BADFIELDS, $this, 'Unable to save date override.');
        }

        CATSUtility::transferRelativeURI($this->getUserAccessLevel('settings.administration') >= ACCESS_LEVEL_SA ? 'm=nesp&a=settings' : 'm=nesp&a=myAvailability');
    }

    private function createInterviewerBlackout()
    {
        $interviewerProfileID = isset($_POST['interviewerProfileID']) ? (int) $_POST['interviewerProfileID'] : 0;
        if ($this->getUserAccessLevel('settings.administration') < ACCESS_LEVEL_SA)
        {
            $profile = $this->_workflow->getInterviewerProfileForUser($this->_userID);
            if (empty($profile) || (int) $profile['interviewer_profile_id'] !== $interviewerProfileID)
            {
                CommonErrors::fatal(COMMONERROR_PERMISSION, $this, 'You can edit only your own availability.');
            }
        }

        $result = $this->_workflow->createInterviewerBlackout(
            $interviewerProfileID,
            isset($_POST['startsAt']) ? $_POST['startsAt'] : '',
            isset($_POST['endsAt']) ? $_POST['endsAt'] : '',
            isset($_POST['isAllDay']) ? 1 : 0,
            isset($_POST['timezone']) ? $_POST['timezone'] : 'America/New_York',
            isset($_POST['privateReason']) ? $_POST['privateReason'] : '',
            $this->_userID
        );
        if ($result === false)
        {
            CommonErrors::fatal(COMMONERROR_BADFIELDS, $this, 'Unable to save blackout.');
        }

        CATSUtility::transferRelativeURI($this->getUserAccessLevel('settings.administration') >= ACCESS_LEVEL_SA ? 'm=nesp&a=settings' : 'm=nesp&a=myAvailability');
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

    private function assignInterviewer()
    {
        $interviewerProfileID = isset($_POST['interviewerProfileID']) ? (int) $_POST['interviewerProfileID'] : 0;
        $candidateID = isset($_POST['candidateID']) ? (int) $_POST['candidateID'] : 0;
        $jobOrderID = isset($_POST['jobOrderID']) ? (int) $_POST['jobOrderID'] : 0;

        if ($this->_workflow->createCandidateGrant($interviewerProfileID, $candidateID, $jobOrderID, $this->_userID) === false)
        {
            CommonErrors::fatal(COMMONERROR_BADFIELDS, $this, 'Choose an active interviewer approved for this role and an eligible candidate.');
        }

        $_SESSION['NESP_ASSIGNMENT_MESSAGE'] = 'Interviewer assignment saved. The interviewer can now see the assigned candidate.';
        CATSUtility::transferRelativeURI('m=nesp');
    }

    private function revokeCandidateGrant()
    {
        $grantID = isset($_POST['grantID']) ? (int) $_POST['grantID'] : 0;
        if ($this->_workflow->revokeCandidateGrant($grantID, $this->_userID) === false)
        {
            CommonErrors::fatal(COMMONERROR_BADFIELDS, $this, 'Choose an active candidate grant to revoke.');
        }

        CATSUtility::transferRelativeURI('m=nesp&a=settings');
    }

    private function createInterviewerAvailability()
    {
        $interviewerProfileID = isset($_POST['interviewerProfileID']) ? (int) $_POST['interviewerProfileID'] : 0;
        if ($this->getUserAccessLevel('settings.administration') < ACCESS_LEVEL_SA)
        {
            $profile = $this->_workflow->getInterviewerProfileForUser($this->_userID);
            if (empty($profile) || (int) $profile['interviewer_profile_id'] !== $interviewerProfileID)
            {
                CommonErrors::fatal(COMMONERROR_PERMISSION, $this, 'You can edit only your own availability.');
            }
        }
        $weekdayKey = isset($_POST['weekdayKey']) ? $_POST['weekdayKey'] : '';
        $startTime = isset($_POST['startTime']) ? $_POST['startTime'] : '';
        $endTime = isset($_POST['endTime']) ? $_POST['endTime'] : '';
        $timezone = isset($_POST['timezone']) ? $_POST['timezone'] : '';
        $slotMinutes = isset($_POST['slotMinutes']) ? (int) $_POST['slotMinutes'] : 30;
        $bufferMinutes = isset($_POST['bufferMinutes']) ? (int) $_POST['bufferMinutes'] : 15;
        $notes = isset($_POST['notes']) ? $_POST['notes'] : '';

        if ($this->_workflow->createInterviewerAvailability($interviewerProfileID, $weekdayKey, $startTime, $endTime, $timezone, $slotMinutes, $bufferMinutes, $notes, $this->_userID) === false)
        {
            CommonErrors::fatal(COMMONERROR_MISSINGFIELDS, $this, 'Choose an interviewer, weekday, and valid start/end time.');
        }

        CATSUtility::transferRelativeURI($this->getUserAccessLevel('settings.administration') >= ACCESS_LEVEL_SA ? 'm=nesp&a=settings' : 'm=nesp&a=myAvailability');
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
        $isAdmin = $this->getUserAccessLevel('settings.administration') >= ACCESS_LEVEL_SA;
        $this->_template->assign('active', $this);
        $this->_template->assign('subActive', 'Interviews');
        $this->_template->assign('isAdminAssignmentOverview', $isAdmin);
        $this->_template->assign('assignedCandidates', $isAdmin
            ? $this->_workflow->getAllAssignedCandidatesForAdmin()
            : $this->_workflow->getAssignedCandidatesForUser($this->_userID));
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

    private function questionnaires()
    {
        $this->_template->assign('active', $this);
        $this->_template->assign('subActive', 'Questionnaires');
        $this->_template->assign('viewKey', 'questionnaires');
        $this->_template->assign('dashboardNavigation', NESPWorkflow::getDashboardNavigation());
        $this->_template->assign('questionnaireQueues', $this->_workflow->getQuestionnaireQueues());
        $this->_template->assign('questionnaires', $this->_workflow->getQuestionnaireSummaries(100));
        $closureMessage = isset($_SESSION['NESP_QUESTIONNAIRE_CLOSURE_MESSAGE'])
            ? $_SESSION['NESP_QUESTIONNAIRE_CLOSURE_MESSAGE'] : '';
        unset($_SESSION['NESP_QUESTIONNAIRE_CLOSURE_MESSAGE']);
        $this->_template->assign('closureMessage', $closureMessage);
        $this->_template->display('./modules/nesp/Questionnaires.tpl');
    }

    private function questionSets($selectedVersionID = 0)
    {
        $this->_workflow->ensureDefaultQuestionSetsSeeded($this->_userID);
        $selectedVersionID = $selectedVersionID > 0
            ? $selectedVersionID
            : (isset($_GET['versionID']) ? (int) $_GET['versionID'] : 0);

        $this->_template->assign('active', $this);
        $this->_template->assign('subActive', 'Questionnaires');
        $this->_template->assign('viewKey', 'questionSets');
        $this->_template->assign('dashboardNavigation', NESPWorkflow::getDashboardNavigation());
        $this->_template->assign('questionSets', $this->_workflow->getQuestionSetAdminRows());
        $this->_template->assign('selectedVersion', $selectedVersionID > 0 ? $this->_workflow->getQuestionSetVersionDetail($selectedVersionID) : array());
        $this->_template->display('./modules/nesp/QuestionSets.tpl');
    }

    private function duplicateQuestionSetDraft()
    {
        $questionSetID = isset($_POST['questionSetID']) ? (int) $_POST['questionSetID'] : 0;
        $sourceVersionID = isset($_POST['sourceVersionID']) ? (int) $_POST['sourceVersionID'] : 0;
        $draftID = $this->_workflow->createQuestionSetDraftFromVersion($questionSetID, $sourceVersionID, $this->_userID);
        if ($draftID === false)
        {
            CommonErrors::fatal(COMMONERROR_BADFIELDS, $this, 'Choose a published question set to duplicate.');
        }

        CATSUtility::transferRelativeURI('m=nesp&a=questionSets&versionID=' . (int) $draftID);
    }

    private function saveQuestionSetDraft()
    {
        $versionID = isset($_POST['versionID']) ? (int) $_POST['versionID'] : 0;
        $roleMatches = array();
        $matchTexts = isset($_POST['roleMatchText']) && is_array($_POST['roleMatchText']) ? $_POST['roleMatchText'] : array();
        foreach ($matchTexts as $index => $matchText)
        {
            $roleMatches[] = array(
                'match_text' => $matchText,
                'joborder_id' => isset($_POST['roleMatchJobOrderID'][$index]) ? (int) $_POST['roleMatchJobOrderID'][$index] : 0
            );
        }
        $_POST['roleMatches'] = $roleMatches;

        $result = $this->_workflow->saveQuestionSetDraft($versionID, $_POST, $this->_userID);
        if (empty($result['ok']))
        {
            CommonErrors::fatal(COMMONERROR_BADFIELDS, $this, isset($result['error']) ? $result['error'] : 'Unable to save question-set draft.');
        }
        CATSUtility::transferRelativeURI('m=nesp&a=questionSets&versionID=' . (int) $versionID);
    }

    private function publishQuestionSetDraft()
    {
        $versionID = isset($_POST['versionID']) ? (int) $_POST['versionID'] : 0;
        if ($this->_workflow->publishQuestionSetDraft($versionID, $this->_userID) === false)
        {
            CommonErrors::fatal(COMMONERROR_BADFIELDS, $this, 'Choose a draft question-set version to publish.');
        }
        CATSUtility::transferRelativeURI('m=nesp&a=questionSets');
    }

    private function archiveQuestionSet()
    {
        $questionSetID = isset($_POST['questionSetID']) ? (int) $_POST['questionSetID'] : 0;
        if ($this->_workflow->archiveQuestionSet($questionSetID, $this->_userID) === false)
        {
            CommonErrors::fatal(COMMONERROR_BADFIELDS, $this, 'Choose a question set to archive.');
        }
        CATSUtility::transferRelativeURI('m=nesp&a=questionSets');
    }

    private function confirmQuestionnaire()
    {
        $candidateID = isset($_GET['candidateID']) ? (int) $_GET['candidateID'] : 0;
        $jobOrderID = isset($_GET['jobOrderID']) ? (int) $_GET['jobOrderID'] : 0;
        if (!$this->_workflow->candidateCanPrepareQuestionnaire($candidateID, $jobOrderID))
        {
            CommonErrors::fatal(COMMONERROR_BADINDEX, $this, 'Collect and verify the applicant email before preparing a questionnaire.');
        }
        $preview = $this->_workflow->getCandidateQuestionnairePreview($candidateID, $jobOrderID);
        if (empty($preview))
        {
            CommonErrors::fatal(COMMONERROR_BADINDEX, $this, 'Invalid questionnaire candidate.');
        }

        $this->_template->assign('active', $this);
        $this->_template->assign('subActive', 'Questionnaires');
        $this->_template->assign('preview', $preview);
        $this->_template->assign('reviewedEmailFingerprint', NESPWorkflow::applicantEmailFingerprint($preview['email1']));
        $this->_template->assign('applicantEmailDelivery', $this->_workflow->getApplicantEmailDeliveryStatus());
        $contactDetailsMessage = isset($_SESSION['NESP_CONTACT_DETAILS_MESSAGE'])
            ? $_SESSION['NESP_CONTACT_DETAILS_MESSAGE'] : '';
        unset($_SESSION['NESP_CONTACT_DETAILS_MESSAGE']);
        $this->_template->assign('contactDetailsMessage', $contactDetailsMessage);
        $this->_template->display('./modules/nesp/QuestionnaireConfirm.tpl');
    }

    private function collectContactDetails()
    {
        $workflowID = isset($_GET['workflowID']) ? (int) $_GET['workflowID'] : 0;
        $candidateID = isset($_GET['candidateID']) ? (int) $_GET['candidateID'] : 0;
        $jobOrderID = isset($_GET['jobOrderID']) ? (int) $_GET['jobOrderID'] : 0;
        $context = $this->_workflow->getCandidateContactDetailsContext($workflowID, $candidateID, $jobOrderID);
        if (empty($context))
        {
            CommonErrors::fatal(COMMONERROR_BADINDEX, $this, 'Invalid applicant contact-details request.');
        }

        $this->_template->assign('active', $this);
        $this->_template->assign('subActive', 'Needs Craig');
        $this->_template->assign('contact', $context);
        $this->_template->display('./modules/nesp/ContactDetails.tpl');
    }

    private function saveContactDetails()
    {
        $workflowID = isset($_POST['workflowID']) ? (int) $_POST['workflowID'] : 0;
        $candidateID = isset($_POST['candidateID']) ? (int) $_POST['candidateID'] : 0;
        $jobOrderID = isset($_POST['jobOrderID']) ? (int) $_POST['jobOrderID'] : 0;
        $email = isset($_POST['email']) ? $_POST['email'] : '';
        $result = $this->_workflow->saveCandidateContactDetails(
            $workflowID,
            $candidateID,
            $jobOrderID,
            $email,
            $this->_userID
        );
        if (empty($result['ok']))
        {
            CommonErrors::fatal(COMMONERROR_BADFIELDS, $this, isset($result['error']) ? $result['error'] : 'Unable to save applicant contact details.');
        }

        $_SESSION['NESP_CONTACT_DETAILS_MESSAGE'] = 'Email saved. Review the role-specific questionnaire below; nothing has been sent.';
        CATSUtility::transferRelativeURI(
            'm=nesp&a=confirmQuestionnaire&candidateID=' . $candidateID . '&jobOrderID=' . $jobOrderID
        );
    }

    private function requestQuestionnaire()
    {
        $candidateID = isset($_POST['candidateID']) ? (int) $_POST['candidateID'] : 0;
        $jobOrderID = isset($_POST['jobOrderID']) ? (int) $_POST['jobOrderID'] : 0;
        $deliveryMode = isset($_POST['deliveryMode']) ? trim((string) $_POST['deliveryMode']) : 'copy';
        $reviewedEmailFingerprint = isset($_POST['reviewedEmailFingerprint'])
            ? trim((string) $_POST['reviewedEmailFingerprint']) : '';
        if ($deliveryMode === 'email'
            && (!isset($_POST['confirmSend']) || $_POST['confirmSend'] !== 'confirm'))
        {
            CommonErrors::fatal(COMMONERROR_BADFIELDS, $this, 'Confirm the applicant and role before sending the questionnaire email.');
        }
        $questionnaireID = $this->_workflow->requestQuestionnaire($candidateID, $jobOrderID, $this->_userID, true);
        if ($questionnaireID === false)
        {
            CommonErrors::fatal(COMMONERROR_BADFIELDS, $this, 'Unable to prepare questionnaire link.');
        }
        if (is_array($questionnaireID))
        {
            $result = $questionnaireID;
            $questionnaireID = (int) $result['questionnaire_id'];
            if ($deliveryMode === 'email')
            {
                $delivery = $this->_workflow->sendQuestionnaireEmailForReview(
                    $questionnaireID,
                    $this->_userID,
                    $result,
                    $reviewedEmailFingerprint
                );
                $_SESSION['NESP_QUESTIONNAIRE_DELIVERY_MESSAGE'] = !empty($delivery['sent'])
                    ? $delivery['message'] : $delivery['error'];
                $_SESSION['NESP_QUESTIONNAIRE_DELIVERY_OK'] = !empty($delivery['ok']) ? 1 : 0;
                $_SESSION['NESP_QUESTIONNAIRE_DELIVERY_SEVERITY'] = !empty($delivery['ok'])
                    ? 'success' : (!empty($delivery['sent']) ? 'warning' : 'error');
                $_SESSION['NESP_QUESTIONNAIRE_INVITATION_COPY'] = isset($delivery['one_time_invitation_copy'])
                    ? $delivery['one_time_invitation_copy'] : '';
                CATSUtility::transferRelativeURI('m=nesp&a=reviewQuestionnaire&questionnaireID=' . $questionnaireID);
            }
            if (!empty($result['one_time_invitation_copy']))
            {
                $_SESSION['NESP_QUESTIONNAIRE_INVITATION_COPY'] = $result['one_time_invitation_copy'];
                CATSUtility::transferRelativeURI('m=nesp&a=reviewQuestionnaire&questionnaireID=' . $questionnaireID);
            }
        }

        CATSUtility::transferRelativeURI('m=nesp&a=reviewQuestionnaire&questionnaireID=' . (int) $questionnaireID);
    }

    private function sendQuestionnaireEmail()
    {
        $questionnaireID = isset($_POST['questionnaireID']) ? (int) $_POST['questionnaireID'] : 0;
        $reviewedEmailFingerprint = isset($_POST['reviewedEmailFingerprint'])
            ? trim((string) $_POST['reviewedEmailFingerprint']) : '';
        if (!isset($_POST['confirmSend']) || $_POST['confirmSend'] !== 'confirm')
        {
            CommonErrors::fatal(COMMONERROR_BADFIELDS, $this, 'Confirm the applicant and role before sending the questionnaire email.');
        }
        $delivery = $this->_workflow->sendQuestionnaireEmailForReview(
            $questionnaireID,
            $this->_userID,
            array(),
            $reviewedEmailFingerprint
        );
        $_SESSION['NESP_QUESTIONNAIRE_DELIVERY_MESSAGE'] = !empty($delivery['sent'])
            ? $delivery['message'] : $delivery['error'];
        $_SESSION['NESP_QUESTIONNAIRE_DELIVERY_OK'] = !empty($delivery['ok']) ? 1 : 0;
        $_SESSION['NESP_QUESTIONNAIRE_DELIVERY_SEVERITY'] = !empty($delivery['ok'])
            ? 'success' : (!empty($delivery['sent']) ? 'warning' : 'error');
        $_SESSION['NESP_QUESTIONNAIRE_INVITATION_COPY'] = isset($delivery['one_time_invitation_copy'])
            ? $delivery['one_time_invitation_copy'] : '';
        CATSUtility::transferRelativeURI('m=nesp&a=reviewQuestionnaire&questionnaireID=' . $questionnaireID);
    }

    private function confirmBulkQuestionnaireEmails()
    {
        $preview = $this->_workflow->getBulkQuestionnaireEmailPreview(200);
        $token = NESPWorkflow::generateQuestionnaireToken();
        $snapshot = array();
        foreach ($preview['ready'] as $row)
        {
            $snapshot[] = array(
                'candidate_id' => (int) $row['candidate_id'],
                'joborder_id' => (int) $row['joborder_id'],
                'candidate_name' => (string) $row['candidate_name'],
                'role_title' => (string) $row['role_title'],
                'email_fingerprint' => (string) $row['email_fingerprint'],
                'questionnaire_fingerprint' => (string) $row['questionnaire_fingerprint']
            );
        }
        $_SESSION['NESP_BULK_QUESTIONNAIRE_SEND'] = array(
            'token_hash' => hash('sha256', $token),
            'created_at' => time(),
            'rows' => $snapshot
        );

        $this->_template->assign('active', $this);
        $this->_template->assign('subActive', 'Needs Craig');
        $this->_template->assign('preview', $preview);
        $this->_template->assign('bulkToken', $token);
        $this->_template->display('./modules/nesp/BulkQuestionnaireConfirm.tpl');
    }

    private function sendBulkQuestionnaireEmails()
    {
        $token = isset($_POST['bulkToken']) ? trim((string) $_POST['bulkToken']) : '';
        $state = isset($_SESSION['NESP_BULK_QUESTIONNAIRE_SEND'])
            ? $_SESSION['NESP_BULK_QUESTIONNAIRE_SEND'] : array();
        unset($_SESSION['NESP_BULK_QUESTIONNAIRE_SEND']);

        $valid = $token !== ''
            && !empty($state['token_hash'])
            && !empty($state['created_at'])
            && (time() - (int) $state['created_at']) <= 900
            && hash_equals((string) $state['token_hash'], hash('sha256', $token));
        if (!$valid || !isset($_POST['confirmSend']) || $_POST['confirmSend'] !== 'confirm')
        {
            CommonErrors::fatal(COMMONERROR_BADFIELDS, $this, 'Review and confirm the exact questionnaire batch before sending. No email was sent.');
        }

        $result = $this->_workflow->sendBulkQuestionnaireEmailsForReview(
            isset($state['rows']) ? $state['rows'] : array(),
            $this->_userID
        );
        $_SESSION['NESP_BULK_QUESTIONNAIRE_MESSAGE'] = sprintf(
            'Questionnaire batch finished: %d sent, %d skipped, %d failed%s.',
            (int) $result['sent'],
            (int) $result['skipped'],
            (int) $result['failed'],
            (int) $result['warnings'] > 0 ? ', ' . (int) $result['warnings'] . ' need audit review' : ''
        );
        $_SESSION['NESP_BULK_QUESTIONNAIRE_ITEMS'] = isset($result['items']) ? $result['items'] : array();
        CATSUtility::transferRelativeURI('m=nesp');
    }

    private function confirmQuestionnaireNonresponseClosure()
    {
        $questionnaireID = isset($_GET['questionnaireID']) ? (int) $_GET['questionnaireID'] : 0;
        $context = $this->_workflow->getQuestionnaireCloseReviewContext($questionnaireID);
        if (empty($context))
        {
            CommonErrors::fatal(COMMONERROR_BADINDEX, $this, 'This questionnaire is not due for no-response closure review.');
        }

        $this->_template->assign('active', $this);
        $this->_template->assign('subActive', 'Questionnaires');
        $this->_template->assign('questionnaire', $context);
        $this->_template->display('./modules/nesp/QuestionnaireNonresponseClosure.tpl');
    }

    private function closeQuestionnaireNonresponse()
    {
        $questionnaireID = isset($_POST['questionnaireID']) ? (int) $_POST['questionnaireID'] : 0;
        $reason = isset($_POST['reason']) ? trim((string) $_POST['reason']) : '';
        if (!isset($_POST['confirmClose']) || $_POST['confirmClose'] !== 'confirm')
        {
            CommonErrors::fatal(COMMONERROR_BADFIELDS, $this, 'Confirm the human-reviewed no-response closure.');
        }
        $result = $this->_workflow->closeQuestionnaireForNonresponse(
            $questionnaireID,
            $this->_userID,
            $reason
        );
        if (empty($result['ok']))
        {
            CommonErrors::fatal(
                COMMONERROR_BADFIELDS,
                $this,
                isset($result['error']) ? $result['error'] : 'Unable to close the review.'
            );
        }

        $_SESSION['NESP_QUESTIONNAIRE_CLOSURE_MESSAGE'] = 'Review closed by a person after no questionnaire response. No message was sent.';
        CATSUtility::transferRelativeURI('m=nesp&a=questionnaires');
    }

    private function confirmQuestionnaireReminderReview()
    {
        $questionnaireID = isset($_GET['questionnaireID']) ? (int) $_GET['questionnaireID'] : 0;
        $context = $this->_workflow->getQuestionnaireReminderReviewContext($questionnaireID);
        if (empty($context))
        {
            CommonErrors::fatal(COMMONERROR_BADINDEX, $this, 'This questionnaire does not need reminder delivery review.');
        }

        $this->_template->assign('active', $this);
        $this->_template->assign('subActive', 'Questionnaires');
        $this->_template->assign('questionnaire', $context);
        $this->_template->display('./modules/nesp/QuestionnaireReminderReview.tpl');
    }

    private function resolveQuestionnaireReminderReview()
    {
        $questionnaireID = isset($_POST['questionnaireID']) ? (int) $_POST['questionnaireID'] : 0;
        $decision = isset($_POST['decision']) ? trim((string) $_POST['decision']) : '';
        $reason = isset($_POST['reason']) ? trim((string) $_POST['reason']) : '';
        if (!isset($_POST['confirmReview']) || $_POST['confirmReview'] !== 'confirm')
        {
            CommonErrors::fatal(COMMONERROR_BADFIELDS, $this, 'Confirm the human-reviewed reminder delivery decision.');
        }
        $result = $this->_workflow->resolveQuestionnaireReminderReview(
            $questionnaireID,
            $this->_userID,
            $decision,
            $reason
        );
        if (empty($result['ok']))
        {
            CommonErrors::fatal(
                COMMONERROR_BADFIELDS,
                $this,
                isset($result['error']) ? $result['error'] : 'Unable to resolve the reminder review.'
            );
        }

        $_SESSION['NESP_QUESTIONNAIRE_CLOSURE_MESSAGE'] = $decision === 'confirm_sent'
            ? 'Reminder delivery confirmed by a person. No message was sent by this action.'
            : 'Reminder delivery was left unconfirmed and the applicant remains active. No message was sent.';
        CATSUtility::transferRelativeURI('m=nesp&a=questionnaires');
    }

    private function reviewQuestionnaire()
    {
        $questionnaireID = isset($_GET['questionnaireID']) ? (int) $_GET['questionnaireID'] : 0;
        $this->displayQuestionnaireReview($questionnaireID);
    }

    private function displayQuestionnaireReview($questionnaireID, $oneTimeInvitationCopy = '')
    {
        $isAdmin = $this->getUserAccessLevel('settings.administration') >= ACCESS_LEVEL_SA;
        $detail = $this->_workflow->getQuestionnaireDetail($questionnaireID, $isAdmin ? null : $this->_userID);
        if (empty($detail))
        {
            CommonErrors::fatal(COMMONERROR_PERMISSION, $this, 'Invalid or unauthorized questionnaire.');
        }

        $this->_template->assign('active', $this);
        $this->_template->assign('subActive', 'Questionnaires');
        $this->_template->assign('isAdmin', $isAdmin);
        if ($oneTimeInvitationCopy === '' && isset($_SESSION['NESP_QUESTIONNAIRE_INVITATION_COPY']))
        {
            $oneTimeInvitationCopy = (string) $_SESSION['NESP_QUESTIONNAIRE_INVITATION_COPY'];
        }
        unset($_SESSION['NESP_QUESTIONNAIRE_INVITATION_COPY']);
        $this->_template->assign('questionnaire', $detail);
        $this->_template->assign('oneTimeInvitationCopy', $oneTimeInvitationCopy);
        $this->_template->assign('reviewedEmailFingerprint', NESPWorkflow::applicantEmailFingerprint($detail['email1']));
        $this->_template->assign('applicantEmailDelivery', $this->_workflow->getApplicantEmailDeliveryStatus());
        $questionnaireDeliveryMessage = isset($_SESSION['NESP_QUESTIONNAIRE_DELIVERY_MESSAGE'])
            ? $_SESSION['NESP_QUESTIONNAIRE_DELIVERY_MESSAGE'] : '';
        $questionnaireDeliveryOK = isset($_SESSION['NESP_QUESTIONNAIRE_DELIVERY_OK'])
            ? (int) $_SESSION['NESP_QUESTIONNAIRE_DELIVERY_OK'] : 0;
        $questionnaireDeliverySeverity = isset($_SESSION['NESP_QUESTIONNAIRE_DELIVERY_SEVERITY'])
            ? (string) $_SESSION['NESP_QUESTIONNAIRE_DELIVERY_SEVERITY'] : '';
        unset(
            $_SESSION['NESP_QUESTIONNAIRE_DELIVERY_MESSAGE'],
            $_SESSION['NESP_QUESTIONNAIRE_DELIVERY_OK'],
            $_SESSION['NESP_QUESTIONNAIRE_DELIVERY_SEVERITY']
        );
        $this->_template->assign('questionnaireDeliveryMessage', $questionnaireDeliveryMessage);
        $this->_template->assign('questionnaireDeliveryOK', $questionnaireDeliveryOK);
        $this->_template->assign('questionnaireDeliverySeverity', $questionnaireDeliverySeverity);
        // The reviewer picker must use the same eligibility rules enforced on save.
        $this->_template->assign('eligibleReviewerProfiles', $isAdmin
            ? $this->_workflow->getEligibleInterviewersForAssignment((int) $detail['joborder_id'])
            : array());
        $this->_template->display('./modules/nesp/QuestionnaireReview.tpl');
    }

    private function markQuestionnaireInvitationCopied()
    {
        $questionnaireID = isset($_POST['questionnaireID']) ? (int) $_POST['questionnaireID'] : 0;
        $this->_workflow->markQuestionnaireInvitationCopied($questionnaireID, $this->_userID);
        CATSUtility::transferRelativeURI('m=nesp&a=reviewQuestionnaire&questionnaireID=' . $questionnaireID);
    }

    private function revokeQuestionnaireLink()
    {
        $questionnaireID = isset($_POST['questionnaireID']) ? (int) $_POST['questionnaireID'] : 0;
        $this->_workflow->revokeQuestionnaireLink($questionnaireID, $this->_userID);
        CATSUtility::transferRelativeURI('m=nesp&a=reviewQuestionnaire&questionnaireID=' . $questionnaireID);
    }

    private function regenerateQuestionnaireLink()
    {
        $questionnaireID = isset($_POST['questionnaireID']) ? (int) $_POST['questionnaireID'] : 0;
        $result = $this->_workflow->regenerateQuestionnaireLink($questionnaireID, $this->_userID);
        if (is_array($result) && !empty($result['one_time_invitation_copy']))
        {
            $this->displayQuestionnaireReview($questionnaireID, $result['one_time_invitation_copy']);
            return;
        }
        CATSUtility::transferRelativeURI('m=nesp&a=reviewQuestionnaire&questionnaireID=' . $questionnaireID);
    }

    private function assignQuestionnaireReviewer()
    {
        $questionnaireID = isset($_POST['questionnaireID']) ? (int) $_POST['questionnaireID'] : 0;
        $interviewerProfileID = isset($_POST['interviewerProfileID']) ? (int) $_POST['interviewerProfileID'] : 0;

        $detail = $this->_workflow->getQuestionnaireDetail($questionnaireID);
        if (empty($detail))
        {
            CommonErrors::fatal(COMMONERROR_BADFIELDS, $this, 'Choose a valid questionnaire.');
        }
        if ((int) $detail['joborder_id'] === 41001)
        {
            CommonErrors::fatal(COMMONERROR_BADFIELDS, $this, 'Customer Service questionnaires stay with Craig and do not need an interviewer assignment.');
        }
        if ($this->_workflow->assignQuestionnaireReviewer($questionnaireID, $interviewerProfileID, $this->_userID) === false)
        {
            CommonErrors::fatal(COMMONERROR_BADFIELDS, $this, 'Choose an active, open interviewer approved for this role.');
        }
        CATSUtility::transferRelativeURI('m=nesp&a=reviewQuestionnaire&questionnaireID=' . $questionnaireID);
    }

    private function saveQuestionnaireReview()
    {
        $questionnaireID = isset($_POST['questionnaireID']) ? (int) $_POST['questionnaireID'] : 0;
        $reviewNote = isset($_POST['reviewNote']) ? $_POST['reviewNote'] : '';
        $markComplete = isset($_POST['markComplete']) ? 1 : 0;
        if ($this->_workflow->saveQuestionnaireReview($questionnaireID, $this->_userID, $reviewNote, $markComplete) === false)
        {
            CommonErrors::fatal(COMMONERROR_PERMISSION, $this, 'You cannot review this questionnaire.');
        }
        CATSUtility::transferRelativeURI('m=nesp&a=reviewQuestionnaire&questionnaireID=' . $questionnaireID);
    }

    private function unlockScorecard()
    {
        $scorecardResponseID = isset($_POST['scorecardResponseID']) ? (int) $_POST['scorecardResponseID'] : 0;
        $candidateID = isset($_POST['candidateID']) ? (int) $_POST['candidateID'] : 0;
        $jobOrderID = isset($_POST['jobOrderID']) ? (int) $_POST['jobOrderID'] : 0;
        $redirectTo = isset($_POST['redirectTo']) ? $_POST['redirectTo'] : '';
        if ($scorecardResponseID <= 0)
        {
            CommonErrors::fatal(COMMONERROR_BADINDEX, $this, 'Invalid scorecard.');
        }

        $this->_workflow->unlockScorecard($this->_userID, $scorecardResponseID);
        if ($redirectTo === 'settings')
        {
            CATSUtility::transferRelativeURI('m=nesp&a=settings');
            return;
        }
        CATSUtility::transferRelativeURI('m=nesp&a=assignedCandidate&candidateID=' . $candidateID . '&jobOrderID=' . $jobOrderID);
    }

    private function phoneScreens()
    {
        $this->_template->assign('active', $this);
        $this->_template->assign('subActive', 'Phone Screens');
        $this->_template->assign('viewKey', 'phoneScreens');
        $this->_template->assign('dashboardNavigation', NESPWorkflow::getDashboardNavigation());
        $this->_template->assign('vapiConfiguration', $this->_workflow->getVapiConfigurationStatus());
        $this->_template->assign('phoneScreens', $this->_workflow->getVapiPhoneScreenSummaries(75));
        $this->_template->assign('phoneScreenQueues', $this->_workflow->getVapiPhoneScreenQueues());
        $this->_template->display('./modules/nesp/PhoneScreens.tpl');
    }

    private function jobAds()
    {
        $this->_template->assign('active', $this);
        $this->_template->assign('subActive', 'Job Ads');
        $this->_template->assign('viewKey', 'jobAds');
        $this->_template->assign('dashboardNavigation', NESPWorkflow::getDashboardNavigation());
        $this->_template->assign('platformMatrix', $this->_workflow->getRecruitingPlatformMatrix());
        $this->_template->assign('campaignControls', $this->_workflow->getRecruitingCampaignControls());
        $this->_template->assign('sourceReport', $this->_workflow->getRecruitingSourceReport());
        $this->_template->assign('adTemplates', $this->_workflow->getRecruitingAdTemplates());
        $this->_template->assign('centralApplicationDestinations', $this->_workflow->getCentralApplicationDestinations());
        $this->_template->display('./modules/nesp/JobAds.tpl');
    }

    private function saveRecruitingCampaignControl()
    {
        if ($this->_workflow->saveRecruitingCampaignControl($_POST, $this->_userID) === false)
        {
            CommonErrors::fatal(COMMONERROR_BADFIELDS, $this, 'Choose a valid recruiting platform.');
        }

        CATSUtility::transferRelativeURI('m=nesp&a=jobAds');
    }

    private function phoneScreenAvailability()
    {
        $this->_template->assign('active', $this);
        $this->_template->assign('subActive', 'Phone Screens');
        $this->_template->assign('viewKey', 'phoneScreens');
        $this->_template->assign('settings', $this->_workflow->getPhoneScreenAvailabilitySettings());
        $this->_template->assign('availabilityBlocks', $this->_workflow->getPhoneScreenAvailabilityBlocks());
        $this->_template->assign('blackoutDates', $this->_workflow->getPhoneScreenBlackoutDates());
        $this->_template->display('./modules/nesp/PhoneScreenAvailability.tpl');
    }

    private function savePhoneScreenAvailability()
    {
        $this->_workflow->savePhoneScreenAvailabilitySettings($_POST, $this->_userID);
        CATSUtility::transferRelativeURI('m=nesp&a=phoneScreenAvailability');
    }

    private function createPhoneScreenAvailabilityBlock()
    {
        $weekday = isset($_POST['weekday']) ? (int) $_POST['weekday'] : 0;
        $startTime = isset($_POST['startTime']) ? $_POST['startTime'] : '';
        $endTime = isset($_POST['endTime']) ? $_POST['endTime'] : '';
        if ($this->_workflow->createPhoneScreenAvailabilityBlock($weekday, $startTime, $endTime, $this->_userID) === false)
        {
            CommonErrors::fatal(COMMONERROR_BADFIELDS, $this, 'Choose a valid day and time block.');
        }
        CATSUtility::transferRelativeURI('m=nesp&a=phoneScreenAvailability');
    }

    private function deletePhoneScreenAvailabilityBlock()
    {
        $availabilityBlockID = isset($_POST['availabilityBlockID']) ? (int) $_POST['availabilityBlockID'] : 0;
        $this->_workflow->deletePhoneScreenAvailabilityBlock($availabilityBlockID, $this->_userID);
        CATSUtility::transferRelativeURI('m=nesp&a=phoneScreenAvailability');
    }

    private function createPhoneScreenBlackout()
    {
        $blackoutDate = isset($_POST['blackoutDate']) ? $_POST['blackoutDate'] : '';
        $label = isset($_POST['label']) ? $_POST['label'] : '';
        if ($this->_workflow->createPhoneScreenBlackout($blackoutDate, $label, $this->_userID) === false)
        {
            CommonErrors::fatal(COMMONERROR_BADFIELDS, $this, 'Choose a valid blackout date.');
        }
        CATSUtility::transferRelativeURI('m=nesp&a=phoneScreenAvailability');
    }

    private function deletePhoneScreenBlackout()
    {
        $blackoutDateID = isset($_POST['blackoutDateID']) ? (int) $_POST['blackoutDateID'] : 0;
        $this->_workflow->deletePhoneScreenBlackout($blackoutDateID, $this->_userID);
        CATSUtility::transferRelativeURI('m=nesp&a=phoneScreenAvailability');
    }

    private function confirmPhoneScreen()
    {
        $candidateID = isset($_GET['candidateID']) ? (int) $_GET['candidateID'] : 0;
        $jobOrderID = isset($_GET['jobOrderID']) ? (int) $_GET['jobOrderID'] : 0;
        $preview = $this->_workflow->getCandidatePhoneScreenPreview($candidateID, $jobOrderID);
        if (empty($preview))
        {
            CommonErrors::fatal(COMMONERROR_BADINDEX, $this, 'Invalid phone-screen candidate.');
        }

        $this->_template->assign('active', $this);
        $this->_template->assign('subActive', 'Phone Screens');
        $this->_template->assign('preview', $preview);
        $this->_template->display('./modules/nesp/PhoneScreenConfirm.tpl');
    }

    private function reviewPhoneScreen()
    {
        $phoneScreenID = isset($_GET['phoneScreenID']) ? (int) $_GET['phoneScreenID'] : 0;
        $detail = $this->_workflow->getVapiPhoneScreenDetail($phoneScreenID);
        if (empty($detail))
        {
            CommonErrors::fatal(COMMONERROR_BADINDEX, $this, 'Invalid phone screen.');
        }

        $this->_template->assign('active', $this);
        $this->_template->assign('subActive', 'Phone Screens');
        $this->_template->assign('screen', $detail);
        $this->_template->display('./modules/nesp/PhoneScreenReview.tpl');
    }

    private function requestPhoneScreen()
    {
        $candidateID = isset($_POST['candidateID']) ? (int) $_POST['candidateID'] : 0;
        $jobOrderID = isset($_POST['jobOrderID']) ? (int) $_POST['jobOrderID'] : 0;
        $phoneScreenID = $this->_workflow->requestPhoneScreen($candidateID, $jobOrderID, $this->_userID);
        if ($phoneScreenID === false)
        {
            CommonErrors::fatal(COMMONERROR_BADFIELDS, $this, 'A destination phone number is required before a phone screen can be prepared.');
        }

        CATSUtility::transferRelativeURI('m=nesp&a=reviewPhoneScreen&phoneScreenID=' . (int) $phoneScreenID);
    }

    private function markPhoneScreenInvitationCopied()
    {
        $phoneScreenID = isset($_POST['phoneScreenID']) ? (int) $_POST['phoneScreenID'] : 0;
        $this->_workflow->markPhoneScreenInvitationCopied($phoneScreenID, $this->_userID);

        CATSUtility::transferRelativeURI('m=nesp&a=reviewPhoneScreen&phoneScreenID=' . $phoneScreenID);
    }

    private function revokePhoneScreenSchedulingLink()
    {
        $phoneScreenID = isset($_POST['phoneScreenID']) ? (int) $_POST['phoneScreenID'] : 0;
        $this->_workflow->revokePhoneScreenSchedulingLink($phoneScreenID, $this->_userID);
        CATSUtility::transferRelativeURI('m=nesp&a=reviewPhoneScreen&phoneScreenID=' . $phoneScreenID);
    }

    private function allowPhoneScreenReschedule()
    {
        $phoneScreenID = isset($_POST['phoneScreenID']) ? (int) $_POST['phoneScreenID'] : 0;
        $this->_workflow->allowPhoneScreenReschedule($phoneScreenID, $this->_userID);
        CATSUtility::transferRelativeURI('m=nesp&a=reviewPhoneScreen&phoneScreenID=' . $phoneScreenID);
    }

    private function cancelPhoneScreen()
    {
        $phoneScreenID = isset($_POST['phoneScreenID']) ? (int) $_POST['phoneScreenID'] : 0;
        $this->_workflow->cancelPhoneScreen($phoneScreenID, $this->_userID);
        CATSUtility::transferRelativeURI('m=nesp&a=reviewPhoneScreen&phoneScreenID=' . $phoneScreenID);
    }

    private function savePhoneScreenReview()
    {
        $phoneScreenID = isset($_POST['phoneScreenID']) ? (int) $_POST['phoneScreenID'] : 0;
        $reviewNote = isset($_POST['reviewNote']) ? $_POST['reviewNote'] : '';
        if ($this->_workflow->savePhoneScreenReview($phoneScreenID, $this->_userID, $reviewNote) === false)
        {
            CommonErrors::fatal(COMMONERROR_BADFIELDS, $this, 'Enter a review note before saving.');
        }

        CATSUtility::transferRelativeURI('m=nesp&a=reviewPhoneScreen&phoneScreenID=' . $phoneScreenID);
    }

    private function scheduleInterview()
    {
        $interviewID = isset($_GET['interviewID']) ? (int) $_GET['interviewID'] : 0;
        if ($interviewID > 0)
        {
            $interview = $this->_workflow->getInterviewDetail($interviewID);
            if (empty($interview))
            {
                CommonErrors::fatal(COMMONERROR_BADFIELDS, $this, 'Interview not found.');
            }
            $candidateID = (int) $interview['candidate_id'];
            $jobOrderID = (int) $interview['joborder_id'];
        }
        else
        {
            $candidateID = isset($_GET['candidateID']) ? (int) $_GET['candidateID'] : 0;
            $jobOrderID = isset($_GET['jobOrderID']) ? (int) $_GET['jobOrderID'] : 0;
            $interview = array();
        }

        $preview = $this->_workflow->getCandidateInterviewPreview($candidateID, $jobOrderID, $interviewID);
        if (empty($preview))
        {
            CommonErrors::fatal(COMMONERROR_BADFIELDS, $this, 'Candidate is not active or not attached to this role.');
        }

        $this->_template->assign('active', $this);
        $this->_template->assign('subActive', 'Interviews');
        $this->_template->assign('viewKey', 'interviews');
        $this->_template->assign('dashboardNavigation', NESPWorkflow::getDashboardNavigation());
        $this->_template->assign('preview', $preview);
        $this->_template->assign('interview', $interview);
        $this->_template->display('./modules/nesp/ScheduleInterview.tpl');
    }

    private function saveManualInterview()
    {
        $interviewID = isset($_POST['interviewID']) ? (int) $_POST['interviewID'] : 0;
        $result = $interviewID > 0
            ? $this->_workflow->updateManualInterview($interviewID, $_POST, $this->_userID)
            : $this->_workflow->createManualInterview($_POST, $this->_userID);

        if (empty($result['ok']))
        {
            CommonErrors::fatal(COMMONERROR_BADFIELDS, $this, isset($result['error']) ? $result['error'] : 'Unable to save interview.');
        }

        $this->storeOneTimeInterviewInvitationCopy((int) $result['interview_id'], isset($result['one_time_invitation_copy']) ? $result['one_time_invitation_copy'] : '');

        CATSUtility::transferRelativeURI('m=nesp&a=recordInterviewOutcome&interviewID=' . (int) $result['interview_id']);
    }

    private function cancelInterview()
    {
        $interviewID = isset($_GET['interviewID']) ? (int) $_GET['interviewID'] : 0;
        $interview = $this->_workflow->getInterviewDetail($interviewID);
        if (empty($interview))
        {
            CommonErrors::fatal(COMMONERROR_BADFIELDS, $this, 'Interview not found.');
        }

        $this->_template->assign('active', $this);
        $this->_template->assign('subActive', 'Interviews');
        $this->_template->assign('viewKey', 'interviews');
        $this->_template->assign('dashboardNavigation', NESPWorkflow::getDashboardNavigation());
        $this->_template->assign('interview', $interview);
        $this->_template->display('./modules/nesp/CancelInterview.tpl');
    }

    private function confirmCancelInterview()
    {
        $interviewID = isset($_POST['interviewID']) ? (int) $_POST['interviewID'] : 0;
        $cancelReason = isset($_POST['cancelReason']) ? $_POST['cancelReason'] : '';
        $result = $this->_workflow->cancelManualInterview($interviewID, $this->_userID, $cancelReason);
        if (empty($result['ok']))
        {
            CommonErrors::fatal(COMMONERROR_BADFIELDS, $this, isset($result['error']) ? $result['error'] : 'Unable to cancel interview.');
        }

        CATSUtility::transferRelativeURI('m=nesp&a=interviews');
    }

    private function recordInterviewOutcome()
    {
        $interviewID = isset($_GET['interviewID']) ? (int) $_GET['interviewID'] : 0;
        $interview = $this->_workflow->getInterviewDetail($interviewID);
        if (empty($interview))
        {
            CommonErrors::fatal(COMMONERROR_BADFIELDS, $this, 'Interview not found.');
        }

        $this->_template->assign('active', $this);
        $this->_template->assign('subActive', 'Interviews');
        $this->_template->assign('viewKey', 'interviews');
        $this->_template->assign('dashboardNavigation', NESPWorkflow::getDashboardNavigation());
        $this->_template->assign('interview', $interview);
        $this->_template->assign('invitationPreview', $this->getOneTimeInterviewInvitationCopy($interviewID, $interview['invitation_preview_text']));
        $this->_template->assign('outcomeLabels', NESPWorkflow::getManualInterviewOutcomeLabels());
        $this->_template->display('./modules/nesp/InterviewOutcome.tpl');
    }

    private function saveInterviewOutcome()
    {
        $interviewID = isset($_POST['interviewID']) ? (int) $_POST['interviewID'] : 0;
        $outcomeKey = isset($_POST['outcomeKey']) ? $_POST['outcomeKey'] : '';
        $outcomeNotes = isset($_POST['outcomeNotes']) ? $_POST['outcomeNotes'] : '';
        $result = $this->_workflow->saveInterviewOutcome($interviewID, $this->_userID, $outcomeKey, $outcomeNotes);
        if (empty($result['ok']))
        {
            CommonErrors::fatal(COMMONERROR_BADFIELDS, $this, isset($result['error']) ? $result['error'] : 'Unable to save interview outcome.');
        }

        CATSUtility::transferRelativeURI('m=nesp&a=completed');
    }

    private function markManualInterviewInvitationSent()
    {
        $interviewID = isset($_POST['interviewID']) ? (int) $_POST['interviewID'] : 0;
        if ($this->_workflow->markManualInterviewInvitationSent($interviewID, $this->_userID) === false)
        {
            CommonErrors::fatal(COMMONERROR_BADFIELDS, $this, 'Unable to mark the interview invitation sent.');
        }

        $this->clearOneTimeInterviewInvitationCopy($interviewID);

        CATSUtility::transferRelativeURI('m=nesp&a=recordInterviewOutcome&interviewID=' . $interviewID);
    }

    private function regenerateTrackedInterviewLink()
    {
        $interviewID = isset($_POST['interviewID']) ? (int) $_POST['interviewID'] : 0;
        $result = $this->_workflow->regenerateInterviewParticipantLink($interviewID, $this->_userID);
        if (empty($result['ok']))
        {
            CommonErrors::fatal(COMMONERROR_BADFIELDS, $this, isset($result['error']) ? $result['error'] : 'Unable to prepare a new tracked interview link.');
        }

        $this->storeOneTimeInterviewInvitationCopy($interviewID, isset($result['one_time_invitation_copy']) ? $result['one_time_invitation_copy'] : '');

        CATSUtility::transferRelativeURI('m=nesp&a=recordInterviewOutcome&interviewID=' . $interviewID);
    }

    private function storeOneTimeInterviewInvitationCopy($interviewID, $copy)
    {
        $copy = trim((string) $copy);
        if ($interviewID <= 0 || $copy === '')
        {
            return;
        }
        if (!isset($_SESSION['nesp_one_time_interview_invitation_copy']) || !is_array($_SESSION['nesp_one_time_interview_invitation_copy']))
        {
            $_SESSION['nesp_one_time_interview_invitation_copy'] = array();
        }
        $_SESSION['nesp_one_time_interview_invitation_copy'][(int) $interviewID] = $copy;
    }

    private function getOneTimeInterviewInvitationCopy($interviewID, $fallback)
    {
        if (isset($_SESSION['nesp_one_time_interview_invitation_copy'])
            && is_array($_SESSION['nesp_one_time_interview_invitation_copy'])
            && isset($_SESSION['nesp_one_time_interview_invitation_copy'][(int) $interviewID]))
        {
            return $_SESSION['nesp_one_time_interview_invitation_copy'][(int) $interviewID];
        }
        return (string) $fallback;
    }

    private function clearOneTimeInterviewInvitationCopy($interviewID)
    {
        if (isset($_SESSION['nesp_one_time_interview_invitation_copy']) && is_array($_SESSION['nesp_one_time_interview_invitation_copy']))
        {
            unset($_SESSION['nesp_one_time_interview_invitation_copy'][(int) $interviewID]);
        }
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
        $this->_template->assign('dryRunResult', null);
        $this->_template->assign('importResult', null);
        $this->_template->display('./modules/nesp/StaffingForecast.tpl');
    }

    private function dryRunStaffingImport()
    {
        $dryRunResult = array(
            'error' => '',
            'result' => null
        );

        if (!isset($_FILES['staffingWorkbook']) || !is_uploaded_file($_FILES['staffingWorkbook']['tmp_name']))
        {
            $dryRunResult['error'] = 'Choose an exported Fall schedule workbook before running the dry-run.';
        }
        else
        {
            $fileName = isset($_FILES['staffingWorkbook']['name']) ? $_FILES['staffingWorkbook']['name'] : 'uploaded workbook';
            $extension = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
            if (!in_array($extension, array('xlsx', 'csv'), true))
            {
                $dryRunResult['error'] = 'Use an exported .xlsx or .csv file for the dry-run.';
            }
            else if ($extension === 'xlsx')
            {
                $dryRunResult['result'] = NESPWorkflow::parseFallStaffingWorkbookXLSXFile(
                    $_FILES['staffingWorkbook']['tmp_name'],
                    $fileName
                );
            }
            else
            {
                $csv = file_get_contents($_FILES['staffingWorkbook']['tmp_name']);
                $dryRunResult['result'] = NESPWorkflow::parseStaffingCSVText($csv === false ? '' : $csv, $fileName);
            }

            if ($dryRunResult['result'] !== null)
            {
                $batchID = $this->storeStaffingDryRunBatch($dryRunResult['result'], $fileName);
                $reviewRows = NESPWorkflow::buildStaffingDryRunReviewRows($dryRunResult['result']);
                $dryRunResult['batch_id'] = $batchID;
                $dryRunResult['review_rows'] = $this->_workflow->markStaffingReviewRowsWithExistingDuplicates($reviewRows);
                $dryRunResult['expires_at'] = date('Y-m-d H:i:s', $_SESSION['NESP_STAFFING_DRY_RUNS'][$batchID]['expires_at']);
            }
        }

        $this->_template->assign('active', $this);
        $this->_template->assign('subActive', 'Staffing Forecast');
        $this->_template->assign('viewKey', 'staffingForecast');
        $this->_template->assign('dashboardNavigation', NESPWorkflow::getDashboardNavigation());
        $this->_template->assign('forecast', $this->_workflow->getStaffingForecast());
        $this->_template->assign('dryRunResult', $dryRunResult);
        $this->_template->assign('importResult', null);
        $this->_template->display('./modules/nesp/StaffingForecast.tpl');
    }

    private function importApprovedStaffingRows()
    {
        $importResult = array(
            'ok' => false,
            'status' => 'not_run',
            'error' => ''
        );
        $batchID = isset($_POST['dryRunBatchID']) ? trim($_POST['dryRunBatchID']) : '';
        $approvedRows = isset($_POST['approvedRows']) && is_array($_POST['approvedRows']) ? $_POST['approvedRows'] : array();
        $backupReference = isset($_POST['backupReference']) ? trim($_POST['backupReference']) : '';
        $backupConfirmed = isset($_POST['backupVerified']) && $_POST['backupVerified'] === '1';

        $dryRunBatch = $this->getStaffingDryRunBatch($batchID);
        if (empty($dryRunBatch))
        {
            $importResult['status'] = 'stale_or_missing_batch';
            $importResult['error'] = 'Run a fresh dry-run before importing approved staffing rows.';
        }
        else if (!$backupConfirmed || $backupReference === '')
        {
            $importResult['status'] = 'backup_required';
            $importResult['error'] = 'Verify the encrypted production backup and enter its reference before importing.';
        }
        else
        {
            $parseResult = $dryRunBatch['result'];
            $sourceType = isset($parseResult['source_type']) ? $parseResult['source_type'] : 'fall_schedule_workbook';
            $sourceLabel = isset($dryRunBatch['source_label']) ? $dryRunBatch['source_label'] : 'Fall schedule workbook';
            $sourceIdentifier = hash('sha256', $sourceLabel . '|' . (isset($parseResult['checksum']) ? $parseResult['checksum'] : '') . '|' . $dryRunBatch['created_at']);
            $importResult = $this->_workflow->saveApprovedStaffingImport(
                $this->_userID,
                $sourceType,
                $sourceIdentifier,
                $sourceLabel,
                $parseResult,
                $approvedRows,
                $backupReference
            );

            if (!empty($importResult['ok']))
            {
                unset($_SESSION['NESP_STAFFING_DRY_RUNS'][$batchID]);
            }
        }

        $this->_template->assign('active', $this);
        $this->_template->assign('subActive', 'Staffing Forecast');
        $this->_template->assign('viewKey', 'staffingForecast');
        $this->_template->assign('dashboardNavigation', NESPWorkflow::getDashboardNavigation());
        $this->_template->assign('forecast', $this->_workflow->getStaffingForecast());
        $this->_template->assign('dryRunResult', null);
        $this->_template->assign('importResult', $importResult);
        $this->_template->display('./modules/nesp/StaffingForecast.tpl');
    }

    private function storeStaffingDryRunBatch($parseResult, $sourceLabel)
    {
        if (!isset($_SESSION['NESP_STAFFING_DRY_RUNS']) || !is_array($_SESSION['NESP_STAFFING_DRY_RUNS']))
        {
            $_SESSION['NESP_STAFFING_DRY_RUNS'] = array();
        }

        $createdAt = time();
        $batchID = hash('sha256', session_id() . '|' . $sourceLabel . '|' . $createdAt . '|' . (isset($parseResult['checksum']) ? $parseResult['checksum'] : ''));
        $_SESSION['NESP_STAFFING_DRY_RUNS'][$batchID] = array(
            'created_at' => $createdAt,
            'expires_at' => $createdAt + 7200,
            'source_label' => $sourceLabel,
            'result' => $parseResult
        );

        return $batchID;
    }

    private function getStaffingDryRunBatch($batchID)
    {
        if ($batchID === '' || !isset($_SESSION['NESP_STAFFING_DRY_RUNS'][$batchID]))
        {
            return array();
        }

        $batch = $_SESSION['NESP_STAFFING_DRY_RUNS'][$batchID];
        if (!isset($batch['expires_at']) || (int) $batch['expires_at'] < time())
        {
            unset($_SESSION['NESP_STAFFING_DRY_RUNS'][$batchID]);
            return array();
        }

        return $batch;
    }

    private function schemaMissing()
    {
        $this->_template->assign('active', $this);
        $this->_template->assign('subActive', 'Dashboard');
        $this->_template->display('./modules/nesp/SchemaMissing.tpl');
    }

    private function featureDisabled($featureFlagKey)
    {
        $this->_template->assign('active', $this);
        $this->_template->assign('subActive', 'Settings');
        $this->_template->assign('featureFlagKey', $featureFlagKey);
        $this->_template->display('./modules/nesp/FeatureDisabled.tpl');
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
        if ($viewKey === 'questionnaires')
        {
            return 'Questionnaires';
        }
        if ($viewKey === 'phoneScreens')
        {
            return 'Phone Screens';
        }
        if ($viewKey === 'completed')
        {
            return 'Completed';
        }
        return 'Needs Craig';
    }
}

?>
