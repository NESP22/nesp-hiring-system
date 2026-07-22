<?php
use PHPUnit\Framework\TestCase;

include_once(LEGACY_ROOT . '/constants.php');
include_once(LEGACY_ROOT . '/config.php');
include_once(LEGACY_ROOT . '/lib/NESPWorkflow.php');
include_once(LEGACY_ROOT . '/lib/NESPVapiIntegration.php');
include_once(LEGACY_ROOT . '/lib/NESPRecruitingAds.php');
include_once(LEGACY_ROOT . '/lib/NESPGoogleCalendarFreeBusy.php');

class NESPWorkflowTest extends TestCase
{
    public function testDefaultFeatureFlagsAreDisabled()
    {
        $flags = NESPWorkflow::getDefaultFeatureFlags();
        $keys = array();

        $this->assertCount(14, $flags);
        foreach ($flags as $flag)
        {
            $keys[] = $flag[0];
            $this->assertSame(0, $flag[3]);
        }

        $this->assertSame(NESPWorkflow::getRequiredFeatureFlagKeys(), $keys);
    }

    public function testDefaultIntegrationsAreDisabled()
    {
        $statuses = NESPWorkflow::getDefaultIntegrationStatuses();

        $this->assertCount(5, $statuses);
        foreach ($statuses as $status)
        {
            $this->assertSame('disabled', $status[2]);
        }
    }

    public function testIntegrationFlagLookupDefaultsToFalse()
    {
        $flags = array(
            array('flag_key' => 'NESP_AI_REVIEW_ENABLED', 'is_enabled' => 0),
            array('flag_key' => 'NESP_ZOOM_ENABLED', 'is_enabled' => 1)
        );

        $this->assertFalse(NESPWorkflow::isIntegrationEnabledFromFlags($flags, 'NESP_AI_REVIEW_ENABLED'));
        $this->assertTrue(NESPWorkflow::isIntegrationEnabledFromFlags($flags, 'NESP_ZOOM_ENABLED'));
        $this->assertFalse(NESPWorkflow::isIntegrationEnabledFromFlags($flags, 'NESP_VAPI_ENABLED'));
    }

    public function testInterviewerZoomLinksEnvDefaultIsDisabled()
    {
        putenv('NESP_INTERVIEWER_ZOOM_LINKS_ENABLED');
        $this->assertFalse(NESPWorkflow::isInterviewerZoomLinksEnabledByDefault());

        putenv('NESP_INTERVIEWER_ZOOM_LINKS_ENABLED=true');
        $this->assertTrue(NESPWorkflow::isInterviewerZoomLinksEnabledByDefault());

        putenv('NESP_INTERVIEWER_ZOOM_LINKS_ENABLED');
    }

    public function testApplicantQuestionnaireEmailRequiresFlagAndConfiguredSender()
    {
        $this->assertFalse(NESPWorkflow::isApplicantEmailDeliveryReady(false, '1', 'hiring@nesportsphoto.com'));
        $this->assertFalse(NESPWorkflow::isApplicantEmailDeliveryReady(true, '0', 'hiring@nesportsphoto.com'));
        $this->assertFalse(NESPWorkflow::isApplicantEmailDeliveryReady(true, '1', 'not-an-email'));
        $this->assertTrue(NESPWorkflow::isApplicantEmailDeliveryReady(true, '1', 'hiring@nesportsphoto.com'));
    }

    public function testCronQuestionnaireEmailRequiresItsOwnEnabledMailRuntime()
    {
        $this->assertFalse(NESPWorkflow::isApplicantEmailRuntimeReady('cron', ''));
        $this->assertFalse(NESPWorkflow::isApplicantEmailRuntimeReady('cron', '0'));
        $this->assertTrue(NESPWorkflow::isApplicantEmailRuntimeReady('cron', '1'));
        $this->assertTrue(NESPWorkflow::isApplicantEmailRuntimeReady('', ''));
        $this->assertTrue(NESPWorkflow::isApplicantEmailRuntimeReady('web', '0'));
    }

    public function testApplicantQuestionnaireEmailFirstEnablementRequiresExplicitConfirmation()
    {
        $this->assertTrue(NESPWorkflow::canEnableApplicantEmail(false, false, ''));
        $this->assertFalse(NESPWorkflow::canEnableApplicantEmail(false, true, ''));
        $this->assertFalse(NESPWorkflow::canEnableApplicantEmail(false, true, 'yes'));
        $this->assertTrue(NESPWorkflow::canEnableApplicantEmail(false, true, 'confirm'));
        $this->assertTrue(NESPWorkflow::canEnableApplicantEmail(true, true, ''));
    }

    public function testApplicantQuestionnaireEmailDescriptionStatesAutomaticBehavior()
    {
        $description = NESPWorkflow::getFeatureFlagDescription(
            'NESP_APPLICANT_EMAIL_ENABLED',
            'stale description'
        );

        $this->assertStringContainsString('automatically sends one secure role-specific questionnaire email', $description);
        $this->assertSame('stored description', NESPWorkflow::getFeatureFlagDescription('NESP_WORKFLOW_ENABLED', 'stored description'));
    }

    public function testQuestionnaireEmailDeliveryErrorsAreSafeAndActionable()
    {
        $this->assertStringContainsString(
            'No duplicate email was sent',
            NESPWorkflow::questionnaireEmailDeliveryErrorMessage('already_attempted')
        );
        $this->assertStringContainsString(
            'No automatic retry will occur',
            NESPWorkflow::questionnaireEmailDeliveryErrorMessage('delivery_failed')
        );
        $this->assertStringContainsString(
            'failed safely',
            NESPWorkflow::questionnaireEmailDeliveryErrorMessage('unknown_reason')
        );
    }

    public function testManualQuestionnaireEmailActionIsAdminCsrfProtectedAndKeepsCopyFallback()
    {
        $ui = file_get_contents(LEGACY_ROOT . '/modules/nesp/NESPUI.php');
        $confirm = file_get_contents(LEGACY_ROOT . '/modules/nesp/QuestionnaireConfirm.tpl');
        $review = file_get_contents(LEGACY_ROOT . '/modules/nesp/QuestionnaireReview.tpl');
        $actionStart = strpos($ui, "case 'sendQuestionnaireEmail':");
        $actionEnd = strpos($ui, "case 'reviewQuestionnaire':", $actionStart);
        $action = substr($ui, $actionStart, $actionEnd - $actionStart);

        $this->assertStringContainsString('$this->adminOnly();', $action);
        $this->assertStringContainsString('$this->requirePostCSRF();', $action);
        $this->assertStringContainsString('Send Questionnaire Email', $confirm);
        $this->assertStringContainsString('Generate Copy Instead', $confirm);
        $this->assertStringContainsString('a=sendQuestionnaireEmail', $review);
        $this->assertStringContainsString('name="csrfToken"', $review);
        $this->assertStringContainsString('name="confirmSend"', $confirm);
        $this->assertStringContainsString('name="confirmSend"', $review);
        $this->assertStringContainsString('name="reviewedEmailFingerprint"', $confirm);
        $this->assertStringContainsString('name="reviewedEmailFingerprint"', $review);
        $this->assertStringContainsString("\$_POST['confirmSend'] !== 'confirm'", $ui);
        $this->assertStringContainsString('NESP_QUESTIONNAIRE_INVITATION_COPY', $ui);
        $this->assertStringContainsString('transferRelativeURI', $ui);
        $this->assertStringContainsString('Copy Invitation', $review);
        $this->assertStringContainsString('Duplicate sends are blocked', $review);
    }

    public function testBulkQuestionnaireEmailRequiresAdminReviewCsrfAndSingleUseSnapshot()
    {
        $ui = file_get_contents(LEGACY_ROOT . '/modules/nesp/NESPUI.php');
        $dashboard = file_get_contents(LEGACY_ROOT . '/modules/nesp/Dashboard.tpl');
        $confirm = file_get_contents(LEGACY_ROOT . '/modules/nesp/BulkQuestionnaireConfirm.tpl');
        $actionStart = strpos($ui, "case 'sendBulkQuestionnaireEmails':");
        $actionEnd = strpos($ui, "case 'reviewQuestionnaire':", $actionStart);
        $action = substr($ui, $actionStart, $actionEnd - $actionStart);

        $this->assertStringContainsString('$this->adminOnly();', $action);
        $this->assertStringContainsString('$this->requirePostCSRF();', $action);
        $this->assertStringContainsString('NESP_BULK_QUESTIONNAIRE_SEND', $ui);
        $this->assertStringContainsString('unset($_SESSION[\'NESP_BULK_QUESTIONNAIRE_SEND\'])', $ui);
        $this->assertStringContainsString("<= 900", $ui);
        $this->assertStringContainsString('email_fingerprint', $ui);
        $this->assertStringContainsString('questionnaire_fingerprint', $ui);
        $this->assertStringContainsString('NESP_BULK_QUESTIONNAIRE_ITEMS', $ui);
        $this->assertStringContainsString('Review and Send All Ready', $dashboard);
        $this->assertStringContainsString('Questionnaire Batch Follow-Up', $dashboard);
        $this->assertStringContainsString('name="csrfToken"', $confirm);
        $this->assertStringContainsString('name="confirmSend"', $confirm);
        $this->assertStringContainsString('Duplicate sends are blocked', $confirm);
    }

    public function testApplicantContactEmailValidationNormalizesAndRejectsInvalidValues()
    {
        $missing = NESPWorkflow::validateApplicantContactEmail('   ');
        $invalid = NESPWorkflow::validateApplicantContactEmail('not-an-email');
        $valid = NESPWorkflow::validateApplicantContactEmail(' Applicant@Example.COM ');

        $this->assertFalse($missing['ok']);
        $this->assertFalse($invalid['ok']);
        $this->assertTrue($valid['ok']);
        $this->assertSame('applicant@example.com', $valid['email']);
        $this->assertSame(
            NESPWorkflow::applicantEmailFingerprint('applicant@example.com'),
            NESPWorkflow::applicantEmailFingerprint(' Applicant@Example.COM ')
        );
        $this->assertFalse(NESPWorkflow::validateApplicantContactEmail(str_repeat('a', 117) . '@example.com')['ok']);
    }

    public function testBulkQuestionnaireReviewFingerprintChangesWithRoleVersionOrQuestions()
    {
        $questions = array(array(
            'key' => 'availability',
            'label' => 'When are you available?',
            'type' => 'textarea',
            'required' => true
        ));
        $base = NESPWorkflow::questionnaireReviewFingerprint('Photographer', 'photographer', 4, 2, $questions);

        $this->assertSame($base, NESPWorkflow::questionnaireReviewFingerprint('Photographer', 'photographer', 4, 2, $questions));
        $this->assertNotSame($base, NESPWorkflow::questionnaireReviewFingerprint('Field Assistant', 'photographer', 4, 2, $questions));
        $this->assertNotSame($base, NESPWorkflow::questionnaireReviewFingerprint('Photographer', 'photographer', 5, 3, $questions));
        $questions[0]['label'] = 'What days are you available?';
        $this->assertNotSame($base, NESPWorkflow::questionnaireReviewFingerprint('Photographer', 'photographer', 4, 2, $questions));
    }

    public function testSavedApplicantEmailAdvancesLegacyContactActionToQuestionnaire()
    {
        $this->assertSame(
            'Send questionnaire',
            NESPWorkflow::resolveContactNextAction('Collect contact details', 'applicant@example.com')
        );
        $this->assertSame(
            'Collect contact details',
            NESPWorkflow::resolveContactNextAction('Collect contact details', '')
        );
        $this->assertSame(
            'Collect contact details',
            NESPWorkflow::resolveContactNextAction('Collect contact details', 'not-an-email')
        );
        $this->assertSame(
            'Review application',
            NESPWorkflow::resolveContactNextAction('Review application', 'applicant@example.com')
        );
        $this->assertSame(
            'Collect contact details',
            NESPWorkflow::resolveContactNextAction('Collect contact details', 'applicant@example.com', 'hired')
        );
    }

    public function testCollectContactDetailsIsAdminCsrfProtectedAndDoesNotSend()
    {
        $ui = file_get_contents(LEGACY_ROOT . '/modules/nesp/NESPUI.php');
        $workflow = file_get_contents(LEGACY_ROOT . '/lib/NESPWorkflow.php');
        $dashboard = file_get_contents(LEGACY_ROOT . '/modules/nesp/Dashboard.tpl');
        $template = file_get_contents(LEGACY_ROOT . '/modules/nesp/ContactDetails.tpl');
        $saveActionStart = strpos($ui, "case 'saveContactDetails':");
        $saveActionEnd = strpos($ui, "case 'requestQuestionnaire':", $saveActionStart);
        $saveAction = substr($ui, $saveActionStart, $saveActionEnd - $saveActionStart);

        $this->assertStringContainsString("case 'collectContactDetails':", $ui);
        $this->assertStringContainsString("case 'saveContactDetails':", $ui);
        $this->assertStringContainsString('$this->adminOnly();', $saveAction);
        $this->assertStringContainsString('$this->requirePostCSRF();', $saveAction);
        $this->assertStringContainsString('getCandidateContactDetailsContext($workflowID, $candidateID, $jobOrderID, true)', $workflow);
        $this->assertStringContainsString('candidate_workflow_id = %s', $workflow);
        $this->assertStringContainsString('candidate_contact_email_saved', $workflow);
        $this->assertStringContainsString('c.email1 AS candidate_email', $workflow);
        $this->assertStringContainsString('resolveContactNextAction($nextAction, $candidateEmail,', $workflow);
        $this->assertStringContainsString('candidateCanPrepareQuestionnaire($candidateID, $jobOrderID)', $workflow . $ui);
        $this->assertStringContainsString('maxlength="128"', $template);
        $this->assertStringContainsString("validateApplicantContactEmail(\$row['email1'])['ok']", $workflow);
        $this->assertStringContainsString('a=collectContactDetails', $workflow . $dashboard);
        $this->assertStringContainsString('a=confirmQuestionnaire', $workflow);
        $this->assertStringContainsString("if (!empty(\$card['can_prepare_questionnaire']))", $dashboard);
        $this->assertStringContainsString('name="csrfToken"', $template);
        $this->assertStringContainsString('name="workflowID"', $template);
        $this->assertStringContainsString('Save Email and Continue to Questionnaire', $template);
        $this->assertStringContainsString('It does not send email', $template);
        $this->assertStringNotContainsString('requestQuestionnaire(', substr(
            $ui,
            strpos($ui, 'private function saveContactDetails()'),
            strpos($ui, 'private function requestQuestionnaire()') - strpos($ui, 'private function saveContactDetails()')
        ));
    }

    public function testInterviewerProfileEmailIdentityIsNormalizedBeforeDuplicateCheck()
    {
        $this->assertSame('', NESPWorkflow::normalizeInterviewerProfileEmail('   '));
        $this->assertSame('suthir@nesportsphoto.com', NESPWorkflow::normalizeInterviewerProfileEmail(' SUTHIR@NESPORTSPHOTO.COM '));

        $workflow = file_get_contents(LEGACY_ROOT . '/lib/NESPWorkflow.php');
        $this->assertStringContainsString('interviewerProfileEmailIsInUse($email)', $workflow);
        $this->assertStringContainsString('interviewerProfileEmailIsInUse($requestedEmail, $interviewerProfileID)', $workflow);
        $this->assertStringContainsString('LOWER(TRIM(email))', $workflow);
    }

    public function testZoomParticipantLinkValidationRejectsHostLinks()
    {
        $valid = NESPWorkflow::validateZoomApplicantJoinURL('https://nesp.zoom.us/j/123456789?pwd=abc');
        $http = NESPWorkflow::validateZoomApplicantJoinURL('http://nesp.zoom.us/j/123456789');
        $startPath = NESPWorkflow::validateZoomApplicantJoinURL('https://nesp.zoom.us/start/123456789');
        $startURL = NESPWorkflow::validateZoomApplicantJoinURL('https://nesp.zoom.us/j/123456789?start_url=https%3A%2F%2Fexample.test');
        $zak = NESPWorkflow::validateZoomApplicantJoinURL('https://nesp.zoom.us/j/123456789?zak=secret');

        $this->assertTrue($valid['ok']);
        $this->assertFalse($http['ok']);
        $this->assertFalse($startPath['ok']);
        $this->assertFalse($startURL['ok']);
        $this->assertFalse($zak['ok']);
    }

    public function testKoalendarBookingLinkValidationAcceptsOnlyPublicBookingPages()
    {
        $valid = NESPWorkflow::validateKoalendarBookingURL('https://koalendar.com/e/nesp-photographer-interview');
        $empty = NESPWorkflow::validateKoalendarBookingURL('');
        $http = NESPWorkflow::validateKoalendarBookingURL('http://koalendar.com/e/nesp-interview');
        $login = NESPWorkflow::validateKoalendarBookingURL('https://koalendar.com/login');
        $workspace = NESPWorkflow::validateKoalendarBookingURL('https://koalendar.com/join/fixture');
        $otherHost = NESPWorkflow::validateKoalendarBookingURL('https://example.test/e/nesp-interview');

        $this->assertTrue($valid['ok']);
        $this->assertSame('https://koalendar.com/e/nesp-photographer-interview', $valid['url']);
        $this->assertTrue($empty['ok']);
        $this->assertSame('', $empty['url']);
        $this->assertFalse($http['ok']);
        $this->assertFalse($login['ok']);
        $this->assertFalse($workspace['ok']);
        $this->assertFalse($otherHost['ok']);
    }

    public function testKoalendarHandoffIsInterviewerOwnedReviewedAndManualOnly()
    {
        $workflow = file_get_contents(LEGACY_ROOT . '/lib/NESPWorkflow.php');
        $ui = file_get_contents(LEGACY_ROOT . '/modules/nesp/NESPUI.php');
        $dashboard = file_get_contents(LEGACY_ROOT . '/modules/nesp/Dashboard.tpl');
        $questionnaire = file_get_contents(LEGACY_ROOT . '/modules/nesp/QuestionnaireReview.tpl');
        $availability = file_get_contents(LEGACY_ROOT . '/modules/nesp/MyAvailability.tpl');
        $assignments = file_get_contents(LEGACY_ROOT . '/modules/nesp/AssignedCandidates.tpl');
        $candidate = file_get_contents(LEGACY_ROOT . '/modules/nesp/AssignedCandidate.tpl');

        $this->assertStringContainsString('canonicalBookingOwnerJoinSQL', $workflow);
        $this->assertStringContainsString('booking_questionnaire.review_status_key = "complete"', $workflow);
        $this->assertStringContainsString('current_grant.interviewer_profile_id = booking_questionnaire.reviewer_profile_id', $workflow);
        $this->assertStringContainsString('current_grant.date_revoked IS NULL', $workflow);
        $this->assertStringContainsString('booking_owner_profile.is_active = 1', $workflow);
        $this->assertStringContainsString("case 'updateInterviewerKoalendarLink':", $ui);
        $this->assertStringContainsString('$this->requirePostCSRF();', $ui);
        $this->assertStringContainsString('You can edit only your own Koalendar booking link.', $ui);
        $this->assertStringContainsString('Reviewed next action', $dashboard);
        $this->assertStringContainsString("questionnaire_review_status_key'] === 'complete'", $dashboard);
        $this->assertStringContainsString("booking_owner_grant_id']", $dashboard);
        $this->assertStringContainsString("review_status_key'] === 'complete'", $questionnaire);
        $this->assertStringContainsString("booking_owner_grant_id']", $questionnaire);
        $this->assertStringContainsString('it does not send it or create a booking', $availability);
        $this->assertStringContainsString('questionnaire_review_completed_at', $assignments);
        $this->assertStringContainsString("questionnaire_review_status_key'] === 'complete'", $assignments);
        $this->assertStringContainsString("candidate['koalendar_booking_url']", $assignments);
        $this->assertStringContainsString('Open My Booking Page', $candidate);
        $this->assertStringContainsString("questionnaire_review_status_key'] === 'complete'", $candidate);
        $this->assertStringNotContainsString('sendKoalendar', $ui);
        $this->assertStringNotContainsString('globalKoalendar', $workflow);
    }

    public function testTrackedInterviewLinkUsesOpaqueTokenAndInvitationDoesNotExposeZoomURL()
    {
        $token = NESPWorkflow::generateQuestionnaireToken();
        $link = NESPWorkflow::getInterviewParticipantLink($token);
        $copy = NESPWorkflow::buildManualInterviewInvitationCopy(
            'Alex',
            'Photographer',
            '2026-07-30 10:00:00',
            30,
            'America/New_York',
            $link
        );

        $this->assertStringContainsString('interviewParticipantLink.php?t=', $link);
        $this->assertSame(64, strlen(NESPWorkflow::interviewParticipantTokenHash($token)));
        $this->assertStringContainsString($link, $copy);
        $this->assertStringNotContainsString('zoom.us', $copy);
        $storedCopy = NESPWorkflow::buildManualInterviewStoredInvitationCopy(
            'Alex',
            'Photographer',
            '2026-07-30 10:00:00',
            30,
            'America/New_York'
        );
        $this->assertStringNotContainsString($token, $storedCopy);
        $this->assertStringContainsString('Generate a fresh secure interview link', $storedCopy);
    }

    public function testTrackedInterviewLinkEndpointIsSessionlessAndProtectsTokenReferrers()
    {
        $endpoint = file_get_contents(LEGACY_ROOT . '/modules/nesp/interviewParticipantLink.php');

        $this->assertStringContainsString('openInterviewParticipantLink($token)', $endpoint);
        $this->assertStringContainsString("header('Referrer-Policy: no-referrer')", $endpoint);
        $this->assertStringContainsString("header('Location: ' . \$result['destination_url'], true, 302)", $endpoint);
        $this->assertStringNotContainsString('session_start()', $endpoint);
    }

    public function testFeatureGateMappingKeepsSettingsOpen()
    {
        $this->assertSame('', NESPWorkflow::getFeatureFlagForAction('settings'));
        $this->assertSame('', NESPWorkflow::getFeatureFlagForAction('saveFeatureFlags'));
        $this->assertSame('NESP_WORKFLOW_ENABLED', NESPWorkflow::getFeatureFlagForAction('dashboard'));
        $this->assertSame('NESP_WORKFLOW_ENABLED', NESPWorkflow::getFeatureFlagForAction('waiting'));
        $this->assertSame('NESP_INTERVIEWER_POOL_ENABLED', NESPWorkflow::getFeatureFlagForAction('assignedCandidate'));
        $this->assertSame('NESP_INTERVIEWER_POOL_ENABLED', NESPWorkflow::getFeatureFlagForAction('submitScorecard'));
        $this->assertSame('NESP_STAFFING_FORECAST_ENABLED', NESPWorkflow::getFeatureFlagForAction('staffingForecast'));
        $this->assertSame('NESP_STAFFING_FORECAST_ENABLED', NESPWorkflow::getFeatureFlagForAction('dryRunStaffingImport'));
        $this->assertSame('NESP_STAFFING_FORECAST_ENABLED', NESPWorkflow::getFeatureFlagForAction('importApprovedStaffingRows'));
        $this->assertSame('NESP_WORKFLOW_ENABLED', NESPWorkflow::getFeatureFlagForAction('phoneScreens'));
        $this->assertSame('NESP_WORKFLOW_ENABLED', NESPWorkflow::getFeatureFlagForAction('questionSets'));
        $this->assertSame('NESP_WORKFLOW_ENABLED', NESPWorkflow::getFeatureFlagForAction('publishQuestionSetDraft'));
        $this->assertSame('NESP_WORKFLOW_ENABLED', NESPWorkflow::getFeatureFlagForAction('phoneScreenAvailability'));
        $this->assertSame('NESP_WORKFLOW_ENABLED', NESPWorkflow::getFeatureFlagForAction('markPhoneScreenInvitationCopied'));
        $this->assertSame('NESP_WORKFLOW_ENABLED', NESPWorkflow::getFeatureFlagForAction('allowPhoneScreenReschedule'));
        $this->assertSame('NESP_INTERVIEWER_POOL_ENABLED', NESPWorkflow::getFeatureFlagForAction('updateInterviewerZoomLink'));
        $this->assertSame('NESP_INTERVIEWER_POOL_ENABLED', NESPWorkflow::getFeatureFlagForAction('activateInterviewerLogin'));
        $this->assertSame('NESP_INTERVIEWER_POOL_ENABLED', NESPWorkflow::getFeatureFlagForAction('revokeCandidateGrant'));
        $this->assertSame('NESP_INTERVIEWER_AVAILABILITY_ENABLED', NESPWorkflow::getFeatureFlagForAction('myAvailability'));
        $this->assertSame('NESP_INTERVIEWER_AVAILABILITY_ENABLED', NESPWorkflow::getFeatureFlagForAction('createInterviewerBlackout'));
        $this->assertSame('', NESPWorkflow::getFeatureFlagForAction('googleCalendarConnect'));
        $this->assertSame('', NESPWorkflow::getFeatureFlagForAction('googleCalendarDisconnect'));
        $this->assertSame('NESP_WORKFLOW_ENABLED', NESPWorkflow::getFeatureFlagForAction('unexpectedAction'));
    }

    public function testAssignInterviewerUsesInterviewerPoolFeatureGate()
    {
        $this->assertSame('NESP_INTERVIEWER_POOL_ENABLED', NESPWorkflow::getFeatureFlagForAction('assignInterviewer'));
    }

    public function testAssignInterviewerIsAdminCsrfProtectedAndUsesExistingGrantGuard()
    {
        $ui = file_get_contents(LEGACY_ROOT . '/modules/nesp/NESPUI.php');
        $workflow = file_get_contents(LEGACY_ROOT . '/lib/NESPWorkflow.php');
        $template = file_get_contents(LEGACY_ROOT . '/modules/nesp/Dashboard.tpl');

        $this->assertStringContainsString("case 'assignInterviewer':", $ui);
        $this->assertStringContainsString('$this->adminOnly();', $ui);
        $this->assertStringContainsString('$this->requirePostCSRF();', $ui);
        $this->assertStringContainsString('$this->_workflow->createCandidateGrant($interviewerProfileID, $candidateID, $jobOrderID, $this->_userID)', $ui);
        $this->assertStringContainsString("date_revoked IS NULL", $workflow);
        $this->assertStringContainsString("interviewer_candidate_grant_duplicate", $workflow);
        $this->assertStringContainsString('assigned_interviewer_names', $workflow);
        $this->assertStringContainsString('name="csrfToken"', $template);
        $this->assertStringContainsString('Assigned to:', $template);
        $this->assertTrue(
            strpos($template, 'name="interviewerProfileID"') !== false
            && strpos($template, '>Assign Interviewer</button>') !== false
        );
    }

    public function testEligibleInterviewerQueryRequiresActiveOpenRoleApprovedProfiles()
    {
        $workflow = file_get_contents(LEGACY_ROOT . '/lib/NESPWorkflow.php');

        $this->assertStringContainsString('public function getEligibleInterviewersForAssignment($jobOrderID)', $workflow);
        $this->assertStringContainsString('ip.is_active = 1', $workflow);
        $this->assertStringContainsString('ip.account_state_key = "active"', $workflow);
        $this->assertStringContainsString('ip.availability_status_key = "open"', $workflow);
        $this->assertStringContainsString('ijr.joborder_id = %s', $workflow);
    }

    public function testQuestionnaireReviewerPickerUsesTheSameEligibilityRulesAsAssignment()
    {
        $ui = file_get_contents(LEGACY_ROOT . '/modules/nesp/NESPUI.php');
        $template = file_get_contents(LEGACY_ROOT . '/modules/nesp/QuestionnaireReview.tpl');

        $this->assertStringContainsString('getEligibleInterviewersForAssignment((int) $detail[\'joborder_id\'])', $ui);
        $this->assertStringContainsString('Customer Service questionnaires stay with Craig', $ui);
        $this->assertStringContainsString('Choose an active, open interviewer approved for this role.', $ui);
        $this->assertStringContainsString('eligibleReviewerProfiles', $template);
        $this->assertStringContainsString('No active, open interviewer is approved for this role yet.', $template);
        $this->assertStringNotContainsString('interviewerProfiles as $profile', $template);
    }

    public function testAdminsReceiveAnAllAssignmentsOverviewWithoutBroadeningInterviewerAccess()
    {
        $ui = file_get_contents(LEGACY_ROOT . '/modules/nesp/NESPUI.php');
        $workflow = file_get_contents(LEGACY_ROOT . '/lib/NESPWorkflow.php');
        $template = file_get_contents(LEGACY_ROOT . '/modules/nesp/AssignedCandidates.tpl');

        $this->assertStringContainsString('getAllAssignedCandidatesForAdmin()', $ui);
        $this->assertStringContainsString('getAssignedCandidatesForUser($this->_userID)', $ui);
        $this->assertStringContainsString('public function getAllAssignedCandidatesForAdmin()', $workflow);
        $this->assertStringContainsString('All Interviewer Assignments', $template);
        $this->assertStringContainsString('Interviewers continue to see only their own assignments.', $template);
        $this->assertStringContainsString('Open candidate', $template);
    }

    public function testGoogleCalendarFreeBusyDefaultsAreReadOnlyAndDisabled()
    {
        $this->assertSame('NESP_GOOGLE_CALENDAR_FREEBUSY_ENABLED', NESPGoogleCalendarFreeBusy::FEATURE_FLAG);
        $this->assertSame(
            array('https://www.googleapis.com/auth/calendar.freebusy'),
            NESPGoogleCalendarFreeBusy::getRequiredOAuthScopes()
        );

        $status = NESPGoogleCalendarFreeBusy::getConfigurationStatus(false);
        $this->assertFalse($status['feature_enabled']);
        $this->assertFalse($status['event_creation_enabled']);
        $this->assertSame('disabled', $status['status_key']);

        $this->assertSame(array(
            'disconnected' => 'Not Connected',
            'connected' => 'Connected',
            'reauthorize_required' => 'Reauthorization Required',
            'error' => 'Error'
        ), NESPGoogleCalendarFreeBusy::getConnectionStateLabels());
    }

    public function testGoogleCalendarFreeBusyAdapterFailsClosedWhenDisabled()
    {
        $adapter = new NESPGoogleCalendarFreeBusy(null, false);
        $result = $adapter->queryFreeBusy(
            'access-token',
            array('primary'),
            '2026-07-16T12:00:00Z',
            '2026-07-16T13:00:00Z',
            'UTC',
            function () {
                return array('status_code' => 200, 'body' => array());
            }
        );

        $this->assertSame('disabled', $result['status_key']);
        $this->assertSame(array(), $result['busy']);
    }

    public function testGoogleCalendarFreeBusyAdapterReturnsOnlyBusyWindows()
    {
        $adapter = new NESPGoogleCalendarFreeBusy(null, true);
        $result = $adapter->queryFreeBusy(
            'access-token',
            array('primary'),
            '2026-07-16T12:00:00Z',
            '2026-07-16T13:00:00Z',
            'UTC',
            function () {
                return array(
                    'status_code' => 200,
                    'body' => array(
                        'calendars' => array(
                            'primary' => array(
                                'busy' => array(
                                    array(
                                        'start' => '2026-07-16T12:15:00Z',
                                        'end' => '2026-07-16T12:45:00Z',
                                        'summary' => 'Private Interview'
                                    )
                                )
                            )
                        )
                    )
                );
            }
        );

        $this->assertSame('busy', $result['status_key']);
        $this->assertSame(array(
            array(
                'start' => '2026-07-16T12:15:00Z',
                'end' => '2026-07-16T12:45:00Z',
                'source_key' => 'google_calendar_freebusy'
            )
        ), $result['busy']);
        $this->assertStringNotContainsString('Private Interview', json_encode($result));
        $this->assertArrayHasKey(hash('sha256', 'primary'), $result['calendars']);
        $this->assertArrayNotHasKey('primary', $result['calendars']);
    }

    public function testGoogleCalendarFreeBusyAdapterHandlesRevokedAuth()
    {
        $adapter = new NESPGoogleCalendarFreeBusy(null, true);
        $result = $adapter->queryFreeBusy(
            'revoked-token',
            array('primary'),
            '2026-07-16T12:00:00Z',
            '2026-07-16T13:00:00Z',
            'UTC',
            function () {
                return array('status_code' => 401, 'body' => array());
            }
        );

        $this->assertSame('reauthorize_required', $result['status_key']);
        $this->assertContains('google_auth_revoked', $result['errors']);
    }

    public function testGoogleCalendarTokenEncryptionDoesNotExposeTokenValue()
    {
        putenv('NESP_GOOGLE_CALENDAR_TOKEN_ENCRYPTION_KEY=unit-test-google-calendar-token-key');
        $token = 'unit-test-google-calendar-token';
        $encrypted = NESPGoogleCalendarFreeBusy::encryptToken($token);

        if ($encrypted === false)
        {
            $this->markTestSkipped('OpenSSL is required for Google Calendar token encryption.');
        }

        $this->assertStringNotContainsString($token, $encrypted);
        $this->assertSame($token, NESPGoogleCalendarFreeBusy::decryptToken($encrypted));
        $this->assertSame(hash('sha256', $token), NESPGoogleCalendarFreeBusy::tokenFingerprint($token));
        putenv('NESP_GOOGLE_CALENDAR_TOKEN_ENCRYPTION_KEY');
    }

    public function testDashboardNavigationIncludesTaskViews()
    {
        $labels = array_map(
            function ($item) {
                return $item['label'];
            },
            NESPWorkflow::getDashboardNavigation()
        );

        $this->assertSame(
            array('Needs Craig', 'Waiting', 'Interviews', 'Questionnaires', 'Manage Question Sets', 'Phone Screens', 'Job Ads', 'Completed', 'Staffing Forecast', 'Interviewer Settings'),
            $labels
        );
    }

    public function testQuestionnaireSnapshotNormalizationKeepsImmutableQuestionShape()
    {
        $questions = NESPWorkflow::normalizeQuestionnaireSnapshotQuestions(array(
            array('key' => 'Availability!', 'label' => 'Availability?', 'type' => 'text', 'required' => true, 'choices' => array('A')),
            array('key' => 'Availability!', 'label' => 'Duplicate ignored', 'type' => 'textarea', 'required' => true),
            array('key' => '', 'label' => 'Missing key')
        ));

        $this->assertSame(1, count($questions));
        $this->assertSame('availability_', $questions[0]['key']);
        $this->assertSame('Availability?', $questions[0]['label']);
        $this->assertTrue($questions[0]['required']);
    }

    public function testNespInterviewerAclMapDeniesLegacyGlobalModules()
    {
        $this->assertTrue(class_exists('ACL_SETUP'));
        $this->assertArrayHasKey('nesp_interviewer', ACL_SETUP::$USER_ROLES);
        $this->assertArrayHasKey('nesp_interviewer', ACL_SETUP::$ACCESS_LEVEL_MAP);

        $map = ACL_SETUP::$ACCESS_LEVEL_MAP['nesp_interviewer'];
        $this->assertSame(ACCESS_LEVEL_READ, $map['']);
        $this->assertSame(ACCESS_LEVEL_READ, $map['nesp']);
        $this->assertSame(ACCESS_LEVEL_DISABLED, $map['candidates']);
        $this->assertSame(ACCESS_LEVEL_DISABLED, $map['joborders']);
        $this->assertSame(ACCESS_LEVEL_DISABLED, $map['settings']);
        $this->assertSame(ACCESS_LEVEL_DISABLED, $map['pipelines']);
        $this->assertSame(ACCESS_LEVEL_DISABLED, $map['reports']);
    }

    public function testRecruitingSourceParametersAreSafeAndTracked()
    {
        $this->assertSame('Indeed', NESPRecruitingAds::getSourceLabel('indeed'));
        $this->assertSame('NESP Ad: Facebook', NESPRecruitingAds::sourceFromRequest(array('utm_source' => 'facebook')));
        $this->assertSame('', NESPRecruitingAds::sourceFromRequest(array('nesp_source' => 'not a platform')));
        $this->assertSame('MassHire', NESPRecruitingAds::getSourceLabel('masshire'));
        $this->assertSame('NESP Ad: Handshake', NESPRecruitingAds::sourceFromRequest(array('nesp_source' => 'handshake')));
        $this->assertStringContainsString('nesp_source=masshire', NESPRecruitingAds::trackedApplicationURL(41002, 'masshire'));

        $link = NESPRecruitingAds::trackedApplicationURL(41002, 'craigslist');
        $this->assertStringContainsString('ID=41002', $link);
        $this->assertStringContainsString('nesp_source=craigslist', $link);

        $destinations = NESPRecruitingAds::getCentralApplicationDestinations();
        $this->assertNotEmpty($destinations);
        $this->assertSame(41001, $destinations[0]['joborder_id']);
        $this->assertStringContainsString('nesp_source=craigslist', $destinations[0]['tracked_link']);
        $this->assertTrue($destinations[7]['native_review_only']);

        $templates = NESPRecruitingAds::getRequestedRoleAdTemplates();
        $this->assertStringContainsString('IMPORTANT: To make sure our hiring team receives your application', $templates[0]['platform_versions'][0]['copy']);
        $this->assertStringContainsString('Apply through NESP:', $templates[0]['platform_versions'][0]['copy']);
    }

    public function testEnsureCandidateWorkflowRowIsIdempotentAndDoesNotOverwriteExistingStage()
    {
        $db = new class {
            public $workflowRows = array();
            public $queries = array();
            private $nextID = 91;

            public function makeQueryInteger($value)
            {
                return (string) (int) $value;
            }

            public function makeQueryString($value)
            {
                return "'" . addslashes((string) $value) . "'";
            }

            public function getAssoc($sql)
            {
                if (stripos($sql, 'SHOW COLUMNS') !== false)
                {
                    return array();
                }
                if (stripos($sql, 'FROM nesp_workflow_stage') !== false)
                {
                    return array('workflow_stage_id' => 1);
                }
                if (stripos($sql, 'FROM nesp_candidate_workflow') !== false)
                {
                    return $this->workflowRows;
                }

                return array();
            }

            public function query($sql, $ignoreErrors = false)
            {
                $this->queries[] = $sql;
                if (stripos($sql, 'INSERT INTO nesp_candidate_workflow') !== false)
                {
                    $this->workflowRows = array('candidate_workflow_id' => $this->nextID++);
                }

                return true;
            }

            public function getLastInsertID()
            {
                return $this->workflowRows['candidate_workflow_id'];
            }
        };

        $workflow = new NESPWorkflow($db);
        $firstID = $workflow->ensureCandidateWorkflowRow(123, 41002, 7, 'NESP Ad: Indeed');
        $secondID = $workflow->ensureCandidateWorkflowRow(123, 41002, 7, 'NESP Ad: Indeed');

        $this->assertSame(91, $firstID);
        $this->assertSame(91, $secondID);
        $this->assertSame(1, count(array_filter($db->queries, function ($sql) {
            return stripos($sql, 'INSERT INTO nesp_candidate_workflow') !== false;
        })));
        $this->assertSame(1, count(array_filter($db->queries, function ($sql) {
            return stripos($sql, 'INSERT INTO nesp_audit_event') !== false;
        })));
        $this->assertSame(0, count(array_filter($db->queries, function ($sql) {
            return stripos($sql, 'UPDATE nesp_candidate_workflow') !== false;
        })));

        $db->workflowRows = array();
        $customID = $workflow->ensureCandidateWorkflowRow(
            124,
            41002,
            7,
            'NESP Ad: LinkedIn',
            'Contact details required before any questionnaire or outreach.',
            'Collect contact details'
        );
        $this->assertSame(92, $customID);
    }

    public function testRecruitingAdTemplatesFlagMissingUnapprovedRoles()
    {
        $templates = NESPRecruitingAds::getRequestedRoleAdTemplates();
        $byRole = array();
        foreach ($templates as $template)
        {
            $byRole[$template['role_key']] = $template;
        }

        $this->assertSame('Prepared draft', $byRole['weekend_sports_photographer']['status']);
        $this->assertStringContainsString('nesp_source=nesp_website', $byRole['weekend_sports_photographer']['application_link']);
        $this->assertSame('Missing Craig-approved fields', $byRole['school_photographer']['status']);
        $this->assertSame('Missing Craig-approved fields', $byRole['sales_representative']['status']);
    }

    public function testQueueDefinitionsCoverRequestedDashboardSections()
    {
        $queues = NESPWorkflow::getQueueDefinitions();

        $this->assertArrayHasKey('needsCraig', $queues);
        $this->assertArrayHasKey('waitingApplicant', $queues);
        $this->assertArrayHasKey('waitingInterviewer', $queues);
        $this->assertArrayHasKey('upcomingInterviews', $queues);
        $this->assertArrayHasKey('recentlyCompleted', $queues);
        $this->assertContains('scorecard_complete', $queues['needsCraig']['stageKeys']);
        $this->assertContains('applicant_clarification_requested', $queues['waitingApplicant']['stageKeys']);
        $this->assertContains('scorecard_pending', $queues['waitingInterviewer']['stageKeys']);
    }

    public function testDefaultScorecardQuestionsAreFactual()
    {
        $questions = NESPWorkflow::getDefaultScorecardQuestions();

        $this->assertCount(4, $questions);
        $this->assertSame('notes', $questions[3]['key']);
        $this->assertSame('textarea', $questions[3]['type']);
    }

    public function testQuestionnaireTokenStateAcceptsValidToken()
    {
        $token = 'questionnaire-token';
        $row = array(
            'token_hash' => NESPWorkflow::questionnaireTokenHash($token),
            'token_expires_at' => '2026-07-16 12:00:00',
            'token_revoked_at' => null,
            'submitted_at' => null,
            'status_key' => 'waiting'
        );

        $this->assertSame('valid', NESPWorkflow::evaluateQuestionnaireTokenState($token, $row, strtotime('2026-07-15 12:00:00')));
    }

    public function testQuestionnaireTokenStateRejectsInvalidExpiredRevokedAndSubmittedTokens()
    {
        $token = 'questionnaire-token';

        $this->assertSame('invalid', NESPWorkflow::evaluateQuestionnaireTokenState('wrong', array(
            'token_hash' => NESPWorkflow::questionnaireTokenHash($token),
            'token_expires_at' => '2026-07-16 12:00:00',
            'token_revoked_at' => null,
            'submitted_at' => null,
            'status_key' => 'waiting'
        ), strtotime('2026-07-15 12:00:00')));

        $this->assertSame('expired', NESPWorkflow::evaluateQuestionnaireTokenState($token, array(
            'token_hash' => NESPWorkflow::questionnaireTokenHash($token),
            'token_expires_at' => '2026-07-14 12:00:00',
            'token_revoked_at' => null,
            'submitted_at' => null,
            'status_key' => 'waiting'
        ), strtotime('2026-07-15 12:00:00')));

        $this->assertSame('revoked', NESPWorkflow::evaluateQuestionnaireTokenState($token, array(
            'token_hash' => NESPWorkflow::questionnaireTokenHash($token),
            'token_expires_at' => '2026-07-16 12:00:00',
            'token_revoked_at' => '2026-07-15 11:00:00',
            'submitted_at' => null,
            'status_key' => 'waiting'
        ), strtotime('2026-07-15 12:00:00')));

        $this->assertSame('submitted', NESPWorkflow::evaluateQuestionnaireTokenState($token, array(
            'token_hash' => NESPWorkflow::questionnaireTokenHash($token),
            'token_expires_at' => '2026-07-16 12:00:00',
            'token_revoked_at' => null,
            'submitted_at' => '2026-07-15 11:30:00',
            'status_key' => 'completed'
        ), strtotime('2026-07-15 12:00:00')));
    }

    public function testQuestionnaireInvitationCopyIsCopyOnlyAndHumanReviewed()
    {
        $copy = NESPWorkflow::buildQuestionnaireInvitationCopy('Avery', 'Weekend Sports Photographer', 'https://example.test/q?t=abc');

        $this->assertStringContainsString('Hi Avery', $copy);
        $this->assertStringContainsString('5-10 minutes', $copy);
        $this->assertStringContainsString('no automated hiring decision will be made', $copy);
        $this->assertStringContainsString('https://example.test/q?t=abc', $copy);
    }

    public function testQuestionnaireRoleQuestionsAvoidProtectedCharacteristics()
    {
        $questions = NESPWorkflow::getQuestionnaireQuestionsForSet('weekend_sports_photographer');
        $labels = strtolower(json_encode($questions));

        $this->assertStringContainsString('early on weekends', $labels);
        $this->assertStringContainsString('anything else', $labels);
        foreach (array('race', 'religion', 'marital', 'medical history', 'disability') as $forbidden)
        {
            $this->assertStringNotContainsString($forbidden, $labels);
        }
    }

    public function testPhotographerQuestionnaireCoversFreelanceAndStaffPreInterviewWording()
    {
        $staff = NESPWorkflow::getQuestionnaireSetForRole('Weekend Staff Portrait & Team Photographer - Youth Sports');
        $freelance = NESPWorkflow::getQuestionnaireSetForRole('Freelance/Contract Youth Sports Photographer');

        $this->assertSame('weekend_sports_photographer', $staff['key']);
        $this->assertSame('weekend_sports_photographer', $freelance['key']);
        $this->assertSame('Photographer Pre-Interview', $staff['label']);

        $intro = NESPWorkflow::getQuestionnaireIntroForSet('weekend_sports_photographer');
        $questions = NESPWorkflow::getQuestionnaireQuestionsForSet('weekend_sports_photographer');
        $labels = strtolower(json_encode($questions));

        $this->assertStringContainsString('photographer pre-interview', strtolower($intro));
        $this->assertStringContainsString('staff or freelance', strtolower($intro));
        $this->assertStringContainsString('fall 2026 and spring 2027 pre-interview information and survey', strtolower($intro));
        $this->assertStringContainsString('massachusetts, new hampshire, rhode island, connecticut, and vermont', strtolower($intro));
        $this->assertStringContainsString('last 5 photography events', $labels);
        $this->assertStringContainsString('online portfolio', $labels);
        $this->assertStringContainsString('linkedin', $labels);
        $this->assertStringContainsString('how many years have you been freelancing', $labels);
        $this->assertStringContainsString('camera body', $labels);
        $this->assertStringContainsString('monolights', $labels);
        $this->assertStringContainsString('45-75 minutes', $labels);
        $this->assertStringContainsString('7:30 am', $labels);
        $this->assertStringContainsString('youth sports team and portrait photographer', $labels);

        $byKey = array();
        foreach ($questions as $question)
        {
            $byKey[$question['key']] = $question;
        }
        $this->assertSame('single_choice', $byKey['position_for_zoom']['type']);
        $this->assertSame(array('Photographer'), $byKey['position_for_zoom']['choices']);
        $this->assertTrue($byKey['last_five_photography_events']['required']);
        $this->assertSame('yes_no', $byKey['owns_flash']['type']);
        $this->assertSame('yes_no', $byKey['indoor_lighting_experience']['type']);
        $this->assertSame('multiple_choice', $byKey['travel_distance']['type']);
        $this->assertCount(3, $byKey['travel_distance']['choices']);
        $this->assertSame('single_choice', $byKey['early_weekend_mornings']['type']);
    }

    public function testFieldStaffQuestionnaireIsFirstAndUsesCraigPreInterviewWording()
    {
        $sets = NESPWorkflow::getQuestionnaireQuestionSets();
        $this->assertSame('photography_assistant_poser', key($sets));
        $this->assertSame('Field Staff Pre-Interview', $sets['photography_assistant_poser']['label']);

        $matched = NESPWorkflow::getQuestionnaireSetForRole('Weekend Table Greeter / Field Assistant');
        $this->assertSame('photography_assistant_poser', $matched['key']);

        $intro = NESPWorkflow::getQuestionnaireIntroForSet('photography_assistant_poser');
        $questions = NESPWorkflow::getQuestionnaireQuestionsForSet('photography_assistant_poser');
        $labels = strtolower(json_encode($questions));

        $this->assertStringContainsString('field staff first', strtolower($intro));
        $this->assertStringContainsString('table/field assistant pre-interview', strtolower($intro));
        $this->assertStringContainsString('fall 2026 and spring 2027 pre-interview information and survey', strtolower($intro));
        $this->assertStringContainsString('massachusetts, new hampshire, rhode island, connecticut, and vermont', strtolower($intro));
        $this->assertStringContainsString('email address', $labels);
        $this->assertStringContainsString('your name', $labels);
        $this->assertStringContainsString('valid driver', $labels);
        $this->assertStringContainsString('personal vehicle', $labels);
        $this->assertStringContainsString('45-60 minutes', $labels);
        $this->assertStringContainsString('kindergarten through high school', $labels);
        $this->assertStringContainsString('picture day events', $labels);

        $byKey = array();
        foreach ($questions as $question)
        {
            $byKey[$question['key']] = $question;
        }
        $this->assertSame('single_choice', $byKey['position_for_zoom']['type']);
        $this->assertSame(array('Table Greeter / Field Assistant'), $byKey['position_for_zoom']['choices']);
        $this->assertSame('single_choice', $byKey['driver_license_vehicle']['type']);
        $this->assertSame(array('Yes', 'No', 'Other'), $byKey['driver_license_vehicle']['choices']);
        $this->assertSame('multiple_choice', $byKey['travel_distance']['type']);
        $this->assertCount(3, $byKey['travel_distance']['choices']);
    }

    public function testQuestionnaireAnswerValidationRequiresCurrentServerQuestions()
    {
        $questions = array(
            array('key' => 'availability', 'label' => 'Availability', 'required' => true),
            array('key' => 'anything_else', 'label' => 'Anything else?', 'required' => false)
        );

        $missing = NESPWorkflow::validateQuestionnaireAnswers($questions, array('anything_else' => 'No.'));
        $this->assertFalse($missing['ok']);
        $this->assertSame(array('availability'), $missing['missing']);

        $valid = NESPWorkflow::validateQuestionnaireAnswers($questions, array('availability' => 'Weekends', 'tampered' => 'ignored'));
        $this->assertTrue($valid['ok']);
        $this->assertArrayHasKey('availability', $valid['answers']);
        $this->assertArrayNotHasKey('tampered', $valid['answers']);
    }

    public function testAssignmentRuleExamplesAreSuggestOnly()
    {
        $examples = NESPWorkflow::getDefaultAssignmentRuleExamples();

        $this->assertCount(3, $examples);
        foreach ($examples as $example)
        {
            $this->assertSame('suggest_only', $example['assignment_mode']);
        }
    }

    public function testAssignmentRuleMatchingUsesRoleText()
    {
        $rules = array(
            array('role_match_text' => 'customer service', 'interviewer_name' => 'Craig Fixture', 'is_active' => 1),
            array('role_match_text' => 'photographer', 'interviewer_name' => 'Suthir Fixture', 'is_active' => 1)
        );

        $match = NESPWorkflow::matchAssignmentRuleForRole('Freelance/Contract Youth Sports Photographer', $rules);

        $this->assertSame('Suthir Fixture', $match['interviewer_name']);
        $this->assertSame(array(), NESPWorkflow::matchAssignmentRuleForRole('Weekend Table Greeter', $rules));
    }

    public function testAvailabilityTemplateDoesNotEnableZoom()
    {
        $template = NESPWorkflow::getDefaultAvailabilityTemplate();

        $this->assertSame('America/New_York', $template['timezone']);
        $this->assertSame('Eastern Time', $template['timezone_label']);
        $this->assertSame(30, $template['slot_minutes']);
        $this->assertSame(15, $template['buffer_minutes']);
        $this->assertStringContainsString('Zoom creation remain disabled', $template['notes']);
    }

    public function testManualInterviewStatusesCoverFullTrackingLifecycle()
    {
        $statuses = NESPWorkflow::getManualInterviewStatusLabels();
        $outcomes = NESPWorkflow::getManualInterviewOutcomeLabels();

        $this->assertSame('Interview Requested', $statuses['requested']);
        $this->assertSame('Reschedule Needed', $statuses['reschedule_needed']);
        $this->assertSame('No Show', $statuses['no_show']);
        $this->assertSame('Advance to Next Step', $outcomes['advance_to_next_step']);
        $this->assertSame('Not Moving Forward', $outcomes['not_moving_forward']);
    }

    public function testManualZoomJoinURLValidationRejectsHostLinks()
    {
        $valid = NESPWorkflow::validateZoomApplicantJoinURL('https://us06web.zoom.us/j/12345678901?pwd=safe');
        $hostPath = NESPWorkflow::validateZoomApplicantJoinURL('https://us06web.zoom.us/start/12345678901?zak=secret');
        $hostQuery = NESPWorkflow::validateZoomApplicantJoinURL('https://us06web.zoom.us/j/12345678901?start_url=https%3A%2F%2Fzoom.us%2Fs%2Fsecret');
        $nonZoom = NESPWorkflow::validateZoomApplicantJoinURL('https://example.test/j/12345678901');

        $this->assertTrue($valid['ok']);
        $this->assertFalse($hostPath['ok']);
        $this->assertFalse($hostQuery['ok']);
        $this->assertFalse($nonZoom['ok']);
    }

    public function testManualInterviewInvitationCopyUsesApplicantJoinLinkOnly()
    {
        $copy = NESPWorkflow::buildManualInterviewInvitationCopy(
            'Craig',
            'Weekend Sports Photographer',
            '2026-09-12 10:30:00',
            30,
            'America/New_York',
            'https://us06web.zoom.us/j/12345678901?pwd=safe'
        );

        $this->assertStringContainsString('Hi Craig', $copy);
        $this->assertStringContainsString('Weekend Sports Photographer', $copy);
        $this->assertStringContainsString('Saturday, September 12, 2026', $copy);
        $this->assertStringContainsString('https://us06web.zoom.us/j/12345678901?pwd=safe', $copy);
        $this->assertStringContainsString('no automated hiring decision', $copy);
        $this->assertStringNotContainsString('start_url', $copy);
        $this->assertStringNotContainsString('zak=', $copy);
    }

    public function testApprovedRealInterviewerSeedsKeepBrandonInactiveAndUnconfirmed()
    {
        $profiles = NESPWorkflow::getApprovedRealInterviewerSeedProfiles();
        $byName = array();
        foreach ($profiles as $profile)
        {
            $byName[$profile['display_name']] = $profile;
            $this->assertSame(0, $profile['is_active']);
        }

        $this->assertSame(array(41002, 41003), $byName['Suthir']['approved_joborder_ids']);
        $this->assertSame('brandon@nesportsphoto.com', $byName['Brandon']['email']);
        $this->assertStringNotContainsString('brandon@sportsphoto.com', $byName['Brandon']['email']);
        $this->assertStringNotContainsString('brandon@sportsphoto.com', $byName['Brandon']['email_warning']);
        $this->assertSame(array(41005), $byName['Brandon']['approved_joborder_ids']);
        $this->assertSame('email_needs_confirmation', $byName['Brandon']['account_state_key']);
        $this->assertStringContainsString('Please confirm', $byName['Brandon']['email_warning']);
        $this->assertSame('profile_created', $byName['Nate']['account_state_key']);
        $this->assertSame(array(), $byName['Nate']['approved_joborder_ids']);
    }

    public function testApprovedInterviewerJobRoleOptionsKeepCustomerServiceCraigOnly()
    {
        $roleOptions = NESPWorkflow::getInterviewerJobRoleOptions();
        $roleKeysByJob = array();
        foreach ($roleOptions as $option)
        {
            $roleKeysByJob[(int) $option['joborder_id']] = $option['role_key'];
        }

        $this->assertSame('customer_service', $roleKeysByJob[41001]);

        foreach (NESPWorkflow::getApprovedRealInterviewerSeedProfiles() as $profile)
        {
            $this->assertNotContains(41001, $profile['approved_joborder_ids']);
        }
    }

    public function testSchedulingConflictsRejectForbiddenCustomerServiceForNate()
    {
        $interviewer = array(
            'is_active' => 1,
            'availability_status_key' => 'open',
            'max_interviews_per_day' => 3,
            'max_interviews_per_week' => 12,
            'buffer_minutes' => 15
        );
        $blocks = array(
            array('weekday_key' => 'Tuesday', 'start_time' => '09:00:00', 'end_time' => '17:00:00', 'is_active' => 1)
        );

        $conflicts = NESPWorkflow::findSchedulingConflicts(
            $interviewer,
            array(41002, 41003, 41005),
            $blocks,
            array(),
            array(),
            41001,
            '2026-07-14 10:00:00',
            '2026-07-14 10:30:00'
        );

        $this->assertContains('Interviewer is not approved for this job role.', $conflicts);
    }

    public function testSchedulingConflictsDetectClosedBlackoutOverlapAndLimits()
    {
        $interviewer = array(
            'is_active' => 1,
            'availability_status_key' => 'closed',
            'max_interviews_per_day' => 1,
            'max_interviews_per_week' => 1,
            'buffer_minutes' => 15
        );
        $blocks = array(
            array('weekday_key' => 'Tuesday', 'start_time' => '09:00:00', 'end_time' => '17:00:00', 'is_active' => 1)
        );
        $blackouts = array(
            array('starts_at' => '2026-07-14 09:30:00', 'ends_at' => '2026-07-14 12:00:00')
        );
        $existing = array(
            array('scheduled_start' => '2026-07-14 12:30:00', 'scheduled_end' => '2026-07-14 13:00:00')
        );

        $conflicts = NESPWorkflow::findSchedulingConflicts(
            $interviewer,
            array(41002),
            $blocks,
            $blackouts,
            $existing,
            41002,
            '2026-07-14 10:00:00',
            '2026-07-14 10:30:00'
        );

        $this->assertContains('Interviewer is closed for interviews.', $conflicts);
        $this->assertContains('Requested time overlaps blocked time.', $conflicts);
        $this->assertContains('Maximum daily interviews would be exceeded.', $conflicts);
        $this->assertContains('Maximum weekly interviews would be exceeded.', $conflicts);
    }

    public function testSchedulingConflictsRespectDSTAndTimezone()
    {
        $interviewer = array(
            'is_active' => 1,
            'availability_status_key' => 'open',
            'timezone' => 'America/New_York',
            'max_interviews_per_day' => 3,
            'max_interviews_per_week' => 12,
            'buffer_minutes' => 15
        );
        $blocks = array(
            array('weekday_key' => 'Sunday', 'start_time' => '01:00:00', 'end_time' => '03:00:00', 'timezone' => 'America/New_York', 'is_active' => 1)
        );

        $fallBack = NESPWorkflow::findSchedulingConflicts($interviewer, array(41002), $blocks, array(), array(), 41002, '2026-11-01 01:30:00', '2026-11-01 02:00:00');
        $springForward = NESPWorkflow::findSchedulingConflicts($interviewer, array(41002), $blocks, array(), array(), 41002, '2026-03-08 02:30:00', '2026-03-08 03:00:00');

        $this->assertSame(array(), $fallBack);
        $this->assertSame(array('Invalid interview time.'), $springForward);
    }

    public function testSchedulingConflictsDetectBuffersAndOutsideAvailability()
    {
        $interviewer = array(
            'is_active' => 1,
            'availability_status_key' => 'open',
            'timezone' => 'America/New_York',
            'max_interviews_per_day' => 3,
            'max_interviews_per_week' => 12,
            'buffer_minutes' => 15
        );
        $blocks = array(
            array('weekday_key' => 'Tuesday', 'start_time' => '09:00:00', 'end_time' => '17:00:00', 'timezone' => 'America/New_York', 'is_active' => 1)
        );
        $existing = array(
            array('scheduled_start' => '2026-07-14 10:00:00', 'scheduled_end' => '2026-07-14 10:30:00', 'timezone' => 'America/New_York')
        );

        $bufferConflicts = NESPWorkflow::findSchedulingConflicts($interviewer, array(41002), $blocks, array(), $existing, 41002, '2026-07-14 10:40:00', '2026-07-14 11:00:00');
        $outsideConflicts = NESPWorkflow::findSchedulingConflicts($interviewer, array(41002), $blocks, array(), array(), 41002, '2026-07-14 08:30:00', '2026-07-14 09:00:00');

        $this->assertContains('Requested time overlaps an existing interview or buffer.', $bufferConflicts);
        $this->assertContains('Requested time is outside available blocks.', $outsideConflicts);
    }

    public function testSchedulingConflictsUseOverridesAndMinimumNotice()
    {
        $interviewer = array(
            'is_active' => 1,
            'availability_status_key' => 'open',
            'timezone' => 'America/New_York',
            'max_interviews_per_day' => 3,
            'max_interviews_per_week' => 12,
            'min_notice_minutes' => 120,
            'buffer_minutes' => 15
        );
        $overrides = array(
            array('override_date' => '2026-07-14', 'override_type_key' => 'available', 'start_time' => '12:00:00', 'end_time' => '13:00:00', 'timezone' => 'America/New_York', 'is_active' => 1)
        );
        $unavailable = array(
            array('override_date' => '2026-07-14', 'override_type_key' => 'unavailable_all_day', 'start_time' => null, 'end_time' => null, 'timezone' => 'America/New_York', 'is_active' => 1)
        );

        $available = NESPWorkflow::findSchedulingConflicts($interviewer, array(41002), array(), array(), array(), 41002, '2026-07-14 12:00:00', '2026-07-14 12:30:00', $overrides, '2026-07-14 09:00:00');
        $tooSoon = NESPWorkflow::findSchedulingConflicts($interviewer, array(41002), array(), array(), array(), 41002, '2026-07-14 12:00:00', '2026-07-14 12:30:00', $overrides, '2026-07-14 11:00:00');
        $blocked = NESPWorkflow::findSchedulingConflicts($interviewer, array(41002), array(), array(), array(), 41002, '2026-07-14 12:00:00', '2026-07-14 12:30:00', $unavailable, '2026-07-14 09:00:00');

        $this->assertSame(array(), $available);
        $this->assertContains('Requested time is inside the minimum notice window.', $tooSoon);
        $this->assertContains('Requested date is marked unavailable.', $blocked);
    }

    public function testSchedulingConflictsAcceptPerInterviewerExternalBusyWindows()
    {
        $interviewer = array(
            'is_active' => 1,
            'availability_status_key' => 'open',
            'timezone' => 'America/New_York',
            'max_interviews_per_day' => 3,
            'max_interviews_per_week' => 12,
            'buffer_minutes' => 15
        );
        $blocks = array(
            array('weekday_key' => 'Tuesday', 'start_time' => '09:00:00', 'end_time' => '17:00:00', 'timezone' => 'America/New_York', 'is_active' => 1)
        );
        $externalBusy = array(
            array('interviewer_profile_id' => 88, 'starts_at' => '2026-07-14 13:00:00', 'ends_at' => '2026-07-14 13:30:00', 'timezone' => 'America/New_York', 'source_key' => 'external_calendar')
        );

        $conflicts = NESPWorkflow::findSchedulingConflicts($interviewer, array(41002), $blocks, array(), array(), 41002, '2026-07-14 13:15:00', '2026-07-14 13:45:00', array(), null, $externalBusy);

        $this->assertContains('Requested time overlaps interviewer external busy time.', $conflicts);
    }

    public function testManualInterviewAvailabilityOverrideRequiresAuditAndProtectedPost()
    {
        $workflowSource = file_get_contents(LEGACY_ROOT . '/lib/NESPWorkflow.php');
        $uiSource = file_get_contents(LEGACY_ROOT . '/modules/nesp/NESPUI.php');
        $templateSource = file_get_contents(LEGACY_ROOT . '/modules/nesp/ScheduleInterview.tpl');

        $this->assertStringContainsString('manual_interview_availability_override_used', $workflowSource);
        $this->assertStringContainsString("case 'saveManualInterview'", $uiSource);
        $this->assertStringContainsString('$this->adminOnly();', $uiSource);
        $this->assertStringContainsString('$this->requirePostCSRF();', $uiSource);
        $this->assertStringContainsString('name="adminOverrideAvailability"', $templateSource);
        $this->assertStringContainsString('name="availabilityOverrideReason"', $templateSource);
        $this->assertStringContainsString('unavailable_all_day', $workflowSource);
        $this->assertStringContainsString('getExternalBusyWindowsForInterviewer', $workflowSource);
        $this->assertStringContainsString('getDefaultParticipantJoinURLForInterviewer', $workflowSource);
    }

    public function testAvailabilityTimeValidationRejectsImpossibleTimes()
    {
        $this->assertTrue(NESPWorkflow::isValidAvailabilityTime('00:00'));
        $this->assertTrue(NESPWorkflow::isValidAvailabilityTime('09:30'));
        $this->assertTrue(NESPWorkflow::isValidAvailabilityTime('23:59'));
        $this->assertFalse(NESPWorkflow::isValidAvailabilityTime('24:00'));
        $this->assertFalse(NESPWorkflow::isValidAvailabilityTime('12:75'));
        $this->assertFalse(NESPWorkflow::isValidAvailabilityTime('9:30'));
        $this->assertFalse(NESPWorkflow::isValidAvailabilityTime('soon'));
    }

    public function testStaffingCSVParserHandlesDatesInRows()
    {
        $csv = "Date,Start,End,State,Sport,Event,Role,Staff\n"
            . "2024-04-20,08:00,12:00,MA,Soccer,Fixture League,Photographer,Alex Fixture; Sam Fixture\n"
            . "bad-date,08:00,10:00,NH,Baseball,Review Row,Assistant,\n";

        $result = NESPWorkflow::parseStaffingCSVText($csv, 'unit fixture');

        $this->assertCount(2, $result['rows']);
        $this->assertSame('2024-04-20', $result['rows'][0]['event_date']);
        $this->assertSame(2, $result['rows'][0]['staff_count']);
        $this->assertSame('needs_review', $result['rows'][1]['status_key']);
        $this->assertGreaterThanOrEqual(2, count($result['issues']));
    }

    public function testStaffingCSVParserBuildsDryRunSummaryForReviewUI()
    {
        $csv = "Date,Start,End,State,Sport,Event,Role,Staff\n"
            . "2026-09-19,,,RI,Soccer,Fixture Youth Soccer,Photographer,Photographer 1\n"
            . "2026-09-19,,,RI,Soccer,Fixture Youth Soccer,Table Staff,Table Staff 1\n"
            . "2026-09-19,,,RI,Soccer,Fixture Youth Soccer,Assistant,Assistant 1\n";

        $result = NESPWorkflow::parseStaffingCSVText($csv, 'fall 2026 review csv');
        $reviewRows = NESPWorkflow::buildStaffingDryRunReviewRows($result);

        $this->assertArrayHasKey('dry_run', $result);
        $this->assertSame(array('2026'), $result['dry_run']['source_summary']['years_found']);
        $this->assertSame(1, $result['dry_run']['quality']['recognized_job_rows']);
        $this->assertSame(3, $result['dry_run']['quality']['normalized_role_rows']);
        $this->assertSame(0, $result['dry_run']['quality']['ambiguous_rows']);
        $this->assertCount(1, $reviewRows);
        $this->assertTrue($reviewRows[0]['is_valid']);
        $this->assertSame(1, $reviewRows[0]['photographers']);
        $this->assertSame(1, $reviewRows[0]['table_staff']);
        $this->assertSame(1, $reviewRows[0]['assistants']);
        $this->assertSame(3, $reviewRows[0]['total_required_staff']);
        $this->assertSame('1P/1T/1A', $reviewRows[0]['staffing_text_original']);
    }

    public function testStaffingCSVParserHandlesDatesInColumns()
    {
        $csv = "Event,State,Sport,4/20/2024,4/21/2024\n"
            . "Fixture League,MA,Lacrosse,Alex Fixture; Sam Fixture,Jordan Fixture\n";

        $result = NESPWorkflow::parseStaffingCSVText($csv, 'column fixture');

        $this->assertCount(2, $result['rows']);
        $this->assertSame('2024-04-20', $result['rows'][0]['event_date']);
        $this->assertSame('2024-04-21', $result['rows'][1]['event_date']);
    }

    public function testStaffingForecastMetricsAreExplainable()
    {
        $rows = array(
            array('event_date' => '2024-04-20', 'event_name' => 'A', 'state' => 'MA', 'sport' => 'Soccer', 'role_key' => 'photographer', 'staff_name' => 'Alex', 'staff_count' => 2, 'staff_hours' => 8, 'issue_count' => 0),
            array('event_date' => '2024-04-21', 'event_name' => 'B', 'state' => 'NH', 'sport' => 'Baseball', 'role_key' => 'assistant', 'staff_name' => 'Sam', 'staff_count' => 1, 'staff_hours' => 4, 'issue_count' => 0),
            array('event_date' => '2025-05-10', 'event_name' => 'C', 'state' => 'MA', 'sport' => 'Soccer', 'role_key' => 'table_staff', 'staff_name' => 'Taylor', 'staff_count' => 3, 'staff_hours' => 9, 'issue_count' => 1)
        );

        $metrics = NESPWorkflow::calculateStaffingForecastMetrics($rows, array('active_staff' => 1, 'expected_returning_staff' => 1, 'confirmed_available_staff' => 1));

        $this->assertSame(3, $metrics['total_events']);
        $this->assertSame(3, $metrics['peak_day_staffing']);
        $this->assertSame('Medium', $metrics['confidence']);
        $this->assertArrayHasKey('recommended_pool', $metrics['formulas']);
        $this->assertGreaterThanOrEqual(0, $metrics['hiring_gap']);
    }

    public function testStaffingForecastMetricsCountMultiRoleEventsOnce()
    {
        $rows = array(
            array('event_date' => '2024-04-20', 'event_name' => 'Fixture Opener', 'state' => 'MA', 'sport' => 'Soccer', 'role_key' => 'photographer', 'staff_name' => 'Alex Fixture; Sam Fixture', 'staff_count' => 2, 'staff_hours' => 8, 'issue_count' => 0),
            array('event_date' => '2024-04-20', 'event_name' => 'Fixture Opener', 'state' => 'MA', 'sport' => 'Soccer', 'role_key' => 'assistant', 'staff_name' => 'Taylor Fixture', 'staff_count' => 1, 'staff_hours' => 4, 'issue_count' => 0),
            array('event_date' => '2024-04-20', 'event_name' => 'Fixture Opener', 'state' => 'MA', 'sport' => 'Soccer', 'role_key' => 'table_staff', 'staff_name' => 'Jordan Fixture', 'staff_count' => 1, 'staff_hours' => 4, 'issue_count' => 0)
        );

        $metrics = NESPWorkflow::calculateStaffingForecastMetrics($rows);

        $this->assertSame(1, $metrics['total_events']);
        $this->assertSame(1, $metrics['events_by_season']['2024']);
        $this->assertSame(1, $metrics['events_by_state']['MA']);
        $this->assertSame(4, $metrics['unique_staff_by_season']['2024']);
        $this->assertSame(4, $metrics['peak_day_staffing']);
    }

    public function testStaffingForecastPeakConcurrentStaffUsesIntervalOverlap()
    {
        $rows = array(
            array('event_date' => '2024-04-20', 'event_start_time' => '08:00:00', 'event_end_time' => '10:00:00', 'event_name' => 'Early Event', 'state' => 'MA', 'role_key' => 'photographer', 'staff_count' => 2, 'staff_hours' => 4, 'issue_count' => 0),
            array('event_date' => '2024-04-20', 'event_start_time' => '08:00:00', 'event_end_time' => '10:00:00', 'event_name' => 'Early Event', 'state' => 'MA', 'role_key' => 'assistant', 'staff_count' => 1, 'staff_hours' => 2, 'issue_count' => 0),
            array('event_date' => '2024-04-20', 'event_start_time' => '09:00:00', 'event_end_time' => '11:00:00', 'event_name' => 'Overlap Event', 'state' => 'MA', 'role_key' => 'assistant', 'staff_count' => 1, 'staff_hours' => 2, 'issue_count' => 0),
            array('event_date' => '2024-04-20', 'event_start_time' => '10:00:00', 'event_end_time' => '12:00:00', 'event_name' => 'Late Event', 'state' => 'MA', 'role_key' => 'table_staff', 'staff_count' => 3, 'staff_hours' => 6, 'issue_count' => 0)
        );

        $metrics = NESPWorkflow::calculateStaffingForecastMetrics($rows);

        $this->assertSame(7, $metrics['peak_day_staffing']);
        $this->assertSame(4, $metrics['peak_concurrent_staff']);
        $this->assertSame(4, $metrics['recommendation_staffing']);
        $this->assertSame('peak_concurrent_staff', $metrics['recommendation_staffing_basis']);
        $this->assertSame(5, $metrics['recommended_pool']);
        $this->assertSame(7, $metrics['hiring_gap']);
        $this->assertSame('Exact', $metrics['peak_concurrent_staff_confidence']);
        $this->assertSame('', $metrics['peak_concurrent_staff_uncertainty']);
    }

    public function testStaffingForecastRecommendationsUseNonOverlappingPeak()
    {
        $rows = array(
            array('event_date' => '2024-04-20', 'event_start_time' => '08:00:00', 'event_end_time' => '10:00:00', 'event_name' => 'Early Event', 'state' => 'MA', 'role_key' => 'photographer', 'staff_count' => 2, 'staff_hours' => 4, 'issue_count' => 0),
            array('event_date' => '2024-04-20', 'event_start_time' => '10:00:00', 'event_end_time' => '12:00:00', 'event_name' => 'Late Event', 'state' => 'MA', 'role_key' => 'table_staff', 'staff_count' => 3, 'staff_hours' => 6, 'issue_count' => 0)
        );

        $metrics = NESPWorkflow::calculateStaffingForecastMetrics($rows, array('buffer_percent' => 0));

        $this->assertSame(5, $metrics['peak_day_staffing']);
        $this->assertSame(3, $metrics['peak_concurrent_staff']);
        $this->assertSame(3, $metrics['recommended_pool']);
        $this->assertSame(3, $metrics['hiring_gap']);
    }

    public function testStaffingForecastPeakConcurrentStaffIsUnknownWhenEventTimesAreMissing()
    {
        $rows = array(
            array('event_date' => '2024-04-20', 'event_start_time' => '08:00:00', 'event_end_time' => '10:00:00', 'event_name' => 'Timed Event', 'state' => 'MA', 'role_key' => 'photographer', 'staff_count' => 2, 'staff_hours' => 4, 'issue_count' => 0),
            array('event_date' => '2024-04-20', 'event_start_time' => null, 'event_end_time' => null, 'event_name' => 'Untimed Event', 'state' => 'MA', 'role_key' => 'assistant', 'staff_count' => 3, 'staff_hours' => 0, 'issue_count' => 1)
        );

        $metrics = NESPWorkflow::calculateStaffingForecastMetrics($rows, array('buffer_percent' => 0));

        $this->assertSame(5, $metrics['peak_day_staffing']);
        $this->assertNull($metrics['peak_concurrent_staff']);
        $this->assertSame('Unknown', $metrics['peak_concurrent_staff_confidence']);
        $this->assertSame('One or more dated events have missing, conflicting, or invalid start/end times.', $metrics['peak_concurrent_staff_uncertainty']);
        $this->assertSame(5, $metrics['recommendation_staffing']);
        $this->assertSame('peak_day_staffing_fallback', $metrics['recommendation_staffing_basis']);
        $this->assertSame(5, $metrics['recommended_pool']);
        $this->assertSame(5, $metrics['hiring_gap']);
    }

    public function testStaffingCSVParserFlagsDuplicateSourceRows()
    {
        $csv = "Date,Start,End,State,Sport,Event,Role,Staff\n"
            . "2024-05-18,08:00,12:00,MA,Soccer,Fixture Duplicate,Photographer,Sam Fixture\n"
            . "2024-05-18,08:00,12:00,MA,Soccer,Fixture Duplicate,Photographer,Sam Fixture\n";

        $result = NESPWorkflow::parseStaffingCSVText($csv, 'duplicate fixture');
        $issueKeys = array_map(
            function ($issue) {
                return $issue['issue_key'];
            },
            $result['issues']
        );

        $this->assertContains('duplicate_source_row', $issueKeys);
        $this->assertSame('needs_review', $result['rows'][1]['status_key']);
    }

    public function testFallStaffingWorkbookParserHandlesDateRowsAndStaffingText()
    {
        $sheets = array(
            '9/19-9/25' => array(
                $this->fallScheduleHeader(),
                array('Saturday 9/19/2026'),
                $this->fallScheduleRow('Fixture Soccer League', '1P/1T/1A', 'OUT', 'TRADITIONAL', 'Fixture Field, Boston MA', '08:00', '12:00')
            )
        );

        $result = NESPWorkflow::parseFallStaffingWorkbookRows($sheets, 'unit fall workbook');

        $this->assertCount(3, $result['rows']);
        $this->assertSame('2026-09-19', $result['rows'][0]['event_date']);
        $this->assertSame('photographer', $result['rows'][0]['role_key']);
        $this->assertSame(1, $result['rows'][0]['staff_count']);
        $this->assertSame('table_staff', $result['rows'][1]['role_key']);
        $this->assertSame('assistant', $result['rows'][2]['role_key']);
        $this->assertSame(array('2026'), $result['dry_run']['source_summary']['years_found']);
        $this->assertTrue($result['dry_run']['source_summary']['requires_additional_historical_workbooks']);
    }

    public function testStaffingDryRunReviewRowsGroupRolesAndApproveOnlyValidRows()
    {
        $sheets = array(
            '9/19-9/25' => array(
                $this->fallScheduleHeader(),
                array('Saturday 9/19/2026'),
                $this->fallScheduleRow('Fixture Soccer League', '2P/1T/1A', 'OUT', 'TRADITIONAL', 'Fixture Field, Boston MA', '08:00', '12:00'),
                $this->fallScheduleRow('Fixture Missing Details', '', 'OUT', 'TRADITIONAL', '', '', '')
            )
        );

        $result = NESPWorkflow::parseFallStaffingWorkbookRows($sheets, 'unit fall workbook');
        $reviewRows = NESPWorkflow::buildStaffingDryRunReviewRows($result);

        $this->assertCount(2, $reviewRows);
        $this->assertTrue($reviewRows[0]['is_valid']);
        $this->assertSame(2, $reviewRows[0]['photographers']);
        $this->assertSame(1, $reviewRows[0]['table_staff']);
        $this->assertSame(1, $reviewRows[0]['assistants']);
        $this->assertSame(4, $reviewRows[0]['total_required_staff']);
        $this->assertFalse($reviewRows[1]['is_valid']);

        $plan = NESPWorkflow::buildApprovedStaffingImportPlan($result, array($reviewRows[0]['review_key']));
        $this->assertTrue($plan['ok']);
        $this->assertCount(3, $plan['rows']);

        $rejected = NESPWorkflow::buildApprovedStaffingImportPlan($result, array($reviewRows[1]['review_key']));
        $this->assertFalse($rejected['ok']);
        $this->assertStringContainsString('Ambiguous', $rejected['error']);
    }

    public function testApprovedStaffingImportPlanRejectsZeroAndAlteredSelections()
    {
        $sheets = array(
            '9/19-9/25' => array(
                $this->fallScheduleHeader(),
                array('Saturday 9/19/2026'),
                $this->fallScheduleRow('Fixture Soccer League', '1P/1T/1A', 'OUT', 'TRADITIONAL', 'Fixture Field, Boston MA', '08:00', '12:00')
            )
        );

        $result = NESPWorkflow::parseFallStaffingWorkbookRows($sheets, 'unit fall workbook');

        $zero = NESPWorkflow::buildApprovedStaffingImportPlan($result, array());
        $this->assertFalse($zero['ok']);

        $altered = NESPWorkflow::buildApprovedStaffingImportPlan($result, array('not-from-this-batch'));
        $this->assertFalse($altered['ok']);
        $this->assertStringContainsString('do not match', $altered['error']);
    }

    public function testApprovedStaffingRowsRemovePersonalAssignmentData()
    {
        $sheets = array(
            '9/19-9/25' => array(
                $this->fallScheduleHeader(),
                array('Saturday 9/19/2026'),
                $this->fallScheduleRowWithAssignments('Fixture Soccer League', '1P/1T/1A', 'OUT', 'TRADITIONAL', 'Fixture Field, Boston MA', '08:00', '12:00')
            )
        );

        $result = NESPWorkflow::parseFallStaffingWorkbookRows($sheets, 'unit fall workbook');
        $reviewRows = NESPWorkflow::buildStaffingDryRunReviewRows($result);
        $plan = NESPWorkflow::buildApprovedStaffingImportPlan($result, array($reviewRows[0]['review_key']));

        $this->assertTrue($plan['ok']);
        foreach ($plan['rows'] as $row)
        {
            $this->assertSame('', $row['staff_name']);
            $this->assertStringNotContainsString('Alex Fixture', $row['raw_source_text']);
            $this->assertStringNotContainsString('Sam Fixture', $row['unresolved_json']);
            $this->assertStringContainsString('Fixture Soccer League', $row['raw_source_text']);
        }
    }

    public function testFallStaffingWorkbookParserFlagsIncompleteRows()
    {
        $sheets = array(
            '10/3-10/9' => array(
                $this->fallScheduleHeader(),
                array('Saturday 10/3/2026'),
                $this->fallScheduleRow('Fixture Missing Details', '', 'OUT', 'TRADITIONAL', '', '', '')
            )
        );

        $result = NESPWorkflow::parseFallStaffingWorkbookRows($sheets, 'unit fall workbook');
        $issueKeys = array_map(
            function ($issue) {
                return $issue['issue_key'];
            },
            $result['issues']
        );

        $this->assertContains('missing_location', $issueKeys);
        $this->assertContains('missing_start_or_end_time', $issueKeys);
        $this->assertContains('missing_or_invalid_staffing', $issueKeys);
        $this->assertSame(1, $result['dry_run']['quality']['ambiguous_rows']);
        $this->assertSame('needs_review', $result['rows'][0]['status_key']);
        $this->assertSame('unresolved', $result['rows'][0]['role_key']);
    }

    public function testFallStaffingWorkbookParserDetectsPriorHistoricalYears()
    {
        $sheets = array(
            '9/21-9/27 2024' => array(
                $this->fallScheduleHeader(),
                array('Saturday 9/21/2024'),
                $this->fallScheduleRow('Fixture Older League', '2P/1T/2A', 'OUT', 'TRADITIONAL', 'Older Field, Providence RI', '09:00', '13:00')
            ),
            '9/19-9/25 2026' => array(
                $this->fallScheduleHeader(),
                array('Saturday 9/19/2026'),
                $this->fallScheduleRow('Fixture Current League', '1P/1T/1A', 'OUT', 'TRADITIONAL', 'Current Field, Boston MA', '08:00', '12:00')
            )
        );

        $result = NESPWorkflow::parseFallStaffingWorkbookRows($sheets, 'unit fall workbook');

        $this->assertSame(array('2024', '2026'), $result['dry_run']['source_summary']['years_found']);
        $this->assertTrue($result['dry_run']['source_summary']['prior_fall_years_present']);
        $this->assertFalse($result['dry_run']['source_summary']['requires_additional_historical_workbooks']);
    }

    private function fallScheduleHeader()
    {
        $row = array_fill(0, 35, '');
        $row[0] = 'Column 1';
        $row[2] = 'IMP';
        $row[3] = 'STAFFING';
        $row[4] = 'IN/OUT';
        $row[5] = 'Column 1';
        $row[9] = 'Lead (or No lead)';
        $row[11] = 'Photog1';
        $row[13] = 'Photog2';
        $row[21] = 'Table';
        $row[23] = 'TABLE 2';
        $row[25] = 'Train';
        $row[26] = 'LOCATION';
        $row[27] = 'START';
        $row[28] = 'END';
        $row[31] = 'SCHED';
        $row[32] = 'FORM';
        $row[33] = 'OL';
        $row[34] = 'Notes';

        return $row;
    }

    private function fallScheduleRow($eventName, $staffing, $indoorOutdoor, $jobType, $location, $start, $end)
    {
        $row = array_fill(0, 35, '');
        $row[0] = $eventName;
        $row[2] = '1';
        $row[3] = $staffing;
        $row[4] = $indoorOutdoor;
        $row[5] = $jobType;
        $row[26] = $location;
        $row[27] = $start;
        $row[28] = $end;
        $row[31] = 'https://docs.google.com/spreadsheets/d/fixture/edit';
        $row[32] = '10MH';
        $row[33] = 'F2600';

        return $row;
    }

    private function fallScheduleRowWithAssignments($eventName, $staffing, $indoorOutdoor, $jobType, $location, $start, $end)
    {
        $row = $this->fallScheduleRow($eventName, $staffing, $indoorOutdoor, $jobType, $location, $start, $end);
        $row[11] = 'Alex Fixture';
        $row[21] = 'Sam Fixture';
        $row[25] = 'Jordan Fixture';

        return $row;
    }

    public function testVapiWebhookRejectsMissingSecret()
    {
        $result = NESPVapiIntegration::validateWebhookRequest(
            array(),
            'application/json',
            '{}',
            1000,
            ''
        );

        $this->assertFalse($result['ok']);
        $this->assertSame('webhook_secret_missing', $result['error']);
    }

    public function testVapiWebhookRejectsExpiredTimestamp()
    {
        $body = json_encode(array('message' => array('type' => 'status-update', 'status' => 'ringing', 'call' => array('id' => 'call_fixture'))));
        $result = NESPVapiIntegration::validateWebhookRequest(
            array('X-Vapi-Secret' => 'secret', 'X-Vapi-Timestamp' => '1000'),
            'application/json',
            $body,
            2000,
            'secret'
        );

        $this->assertFalse($result['ok']);
        $this->assertSame('expired_timestamp', $result['error']);
    }

    public function testVapiWebhookAcceptsValidStatusUpdate()
    {
        $body = json_encode(array('message' => array('type' => 'status-update', 'status' => 'ringing', 'call' => array('id' => 'call_fixture'))));
        $result = NESPVapiIntegration::validateWebhookRequest(
            array('Authorization' => 'Bearer secret', 'X-Vapi-Timestamp' => '1000', 'X-Vapi-Event-Id' => 'evt_fixture'),
            'application/json',
            $body,
            1000,
            'secret'
        );

        $this->assertTrue($result['ok']);
        $this->assertSame('status-update', $result['event_type']);
        $this->assertSame('evt_fixture', $result['event_id']);
        $this->assertSame('call_fixture', $result['provider_call_id']);
    }

    public function testVapiConsentRefusalDoesNotRetainTranscript()
    {
        $message = array(
            'type' => 'end-of-call-report',
            'endedReason' => 'hangup',
            'call' => array('id' => 'call_fixture'),
            'artifact' => array(
                'transcript' => 'Assistant: Do you consent to continue? User: I do not consent.'
            )
        );

        $update = NESPVapiIntegration::buildScreenUpdateFromWebhookMessage($message);

        $this->assertSame('refused', $update['consent_status']);
        $this->assertSame('', $update['transcript_text']);
    }

    public function testVapiWebhookRedactedPayloadDoesNotStoreTranscript()
    {
        $payload = array(
            'message' => array(
                'type' => 'end-of-call-report',
                'endedReason' => 'hangup',
                'call' => array('id' => 'call_fixture'),
                'artifact' => array(
                    'transcript' => 'Assistant: consent prompt. User: yes. User: private applicant answer.'
                ),
                'analysis' => array(
                    'structuredData' => array(
                        'experience_summary' => 'private applicant details'
                    )
                )
            )
        );

        $redacted = NESPVapiIntegration::redactedPayloadForStorage($payload);

        $this->assertStringNotContainsString('private applicant answer', $redacted);
        $this->assertStringNotContainsString('private applicant details', $redacted);
        $this->assertStringContainsString('has_transcript', $redacted);
        $this->assertStringContainsString('has_structured_result', $redacted);
    }

    public function testVapiOutboundPayloadUsesDedicatedConfiguredResources()
    {
        putenv('VAPI_HIRING_ASSISTANT_ID=assistant_fixture');
        putenv('VAPI_PHONE_NUMBER_ID=phone_fixture');

        $payload = NESPVapiIntegration::buildOutboundCallPayload(
            '(555) 111-2222',
            array('candidate_id' => 123),
            array('joborder_id' => 41003, 'title' => 'Freelance Photographer'),
            'request_fixture'
        );

        $this->assertSame('assistant_fixture', $payload['assistantId']);
        $this->assertSame('phone_fixture', $payload['phoneNumberId']);
        $this->assertSame('+15551112222', $payload['customer']['number']);
        $this->assertArrayNotHasKey('metadata', $payload);
        $this->assertFalse($payload['artifactPlan']['recordingEnabled']);
        $this->assertFalse($payload['artifactPlan']['videoRecordingEnabled']);
        $this->assertFalse($payload['artifactPlan']['loggingEnabled']);
        $this->assertFalse($payload['artifactPlan']['pcapEnabled']);
        $this->assertFalse($payload['artifactPlan']['fullMessageHistoryEnabled']);
        $this->assertTrue($payload['artifactPlan']['transcriptPlan']['enabled']);
        $this->assertFalse($payload['assistantOverrides']['artifactPlan']['recordingEnabled']);
        $this->assertFalse($payload['assistantOverrides']['artifactPlan']['videoRecordingEnabled']);
        $this->assertTrue($payload['assistantOverrides']['artifactPlan']['transcriptPlan']['enabled']);
        $this->assertStringNotContainsString('recordingPath', json_encode($payload));
        $this->assertStringNotContainsString('recordingUrl', json_encode($payload));
        $this->assertSame('Freelance Photographer', $payload['assistantOverrides']['variableValues']['role']);
        $this->assertSame('off', $payload['assistantOverrides']['variableValues']['audio_recording']);
        $this->assertSame('request_fixture', $payload['assistantOverrides']['metadata']['nesp_call_request_key']);

        putenv('VAPI_HIRING_ASSISTANT_ID');
        putenv('VAPI_PHONE_NUMBER_ID');
    }

    public function testVapiStructuredResultsDropRecordingArtifacts()
    {
        $message = array(
            'type' => 'end-of-call-report',
            'endedReason' => 'hangup',
            'call' => array('id' => 'call_fixture'),
            'transcript' => 'Assistant: consent prompt. User: yes.',
            'analysis' => array(
                'structuredData' => array(
                    'consent_accepted' => true,
                    'experience_summary' => 'Safe summary',
                    'recording_url' => 'https://provider.example/recording.wav',
                    'artifact' => array('recordingUrl' => 'https://provider.example/recording.wav')
                )
            )
        );

        $update = NESPVapiIntegration::buildScreenUpdateFromWebhookMessage($message);
        $structured = json_decode($update['structured_result_json'], true);

        $this->assertSame('Safe summary', $structured['experience_summary']);
        $this->assertArrayNotHasKey('recording_url', $structured);
        $this->assertArrayNotHasKey('artifact', $structured);
        $this->assertStringNotContainsString('recording.wav', $update['structured_result_json']);
    }

    public function testSchedulingTokenStateAcceptsValidToken()
    {
        $token = 'fixture-token';
        $row = array(
            'scheduling_token_hash' => NESPVapiIntegration::schedulingTokenHash($token),
            'scheduling_token_expires_at' => '2026-07-15 12:00:00',
            'scheduling_token_revoked_at' => null
        );

        $this->assertSame('valid', NESPVapiIntegration::evaluateSchedulingTokenState($token, $row, strtotime('2026-07-14 12:00:00')));
    }

    public function testSchedulingTokenStateRejectsInvalidToken()
    {
        $row = array(
            'scheduling_token_hash' => NESPVapiIntegration::schedulingTokenHash('expected-token'),
            'scheduling_token_expires_at' => '2026-07-15 12:00:00',
            'scheduling_token_revoked_at' => null
        );

        $this->assertSame('invalid', NESPVapiIntegration::evaluateSchedulingTokenState('wrong-token', $row, strtotime('2026-07-14 12:00:00')));
    }

    public function testSchedulingTokenStateRejectsExpiredToken()
    {
        $token = 'fixture-token';
        $row = array(
            'scheduling_token_hash' => NESPVapiIntegration::schedulingTokenHash($token),
            'scheduling_token_expires_at' => '2026-07-14 10:00:00',
            'scheduling_token_revoked_at' => null
        );

        $this->assertSame('expired', NESPVapiIntegration::evaluateSchedulingTokenState($token, $row, strtotime('2026-07-14 12:00:00')));
    }

    public function testSchedulingTokenStateRejectsRevokedToken()
    {
        $token = 'fixture-token';
        $row = array(
            'scheduling_token_hash' => NESPVapiIntegration::schedulingTokenHash($token),
            'scheduling_token_expires_at' => '2026-07-15 12:00:00',
            'scheduling_token_revoked_at' => '2026-07-14 09:00:00'
        );

        $this->assertSame('revoked', NESPVapiIntegration::evaluateSchedulingTokenState($token, $row, strtotime('2026-07-14 12:00:00')));
    }

    public function testSchedulingInvitationCopyIsCopyOnlyAndSafe()
    {
        $copy = NESPVapiIntegration::buildSchedulingInvitationCopy('Avery', 'Staff Photographer', 'https://example.test/schedule?t=abc');

        $this->assertStringContainsString('Hi Avery', $copy);
        $this->assertStringContainsString('Staff Photographer', $copy);
        $this->assertStringContainsString('brief 7–10 minute automated phone screen', $copy);
        $this->assertStringContainsString('Audio will not be recorded', $copy);
        $this->assertStringContainsString('Every hiring decision is made by a person', $copy);
    }

    public function testVapiQueuedStatusMapsToCallStartedWorkflowState()
    {
        $this->assertSame('call_started', NESPVapiIntegration::mapWebhookStatus('status-update', 'queued'));
        $this->assertSame('call_started', NESPVapiIntegration::mapWebhookStatus('status-update', 'scheduled'));
    }

    public function testDuplicateBookingIsRejected()
    {
        $settings = NESPVapiIntegration::getDefaultPhoneScreenAvailabilitySettings();
        $appointments = array(
            array('scheduled_start_at_utc' => '2026-07-14 14:00:00')
        );

        $this->assertTrue(NESPVapiIntegration::slotConflictsWithAppointments('2026-07-14 14:00:00', $appointments, $settings));
    }

    public function testSubmittedSlotMustBeGeneratedAvailabilityOption()
    {
        $availableSlots = array(
            array('value' => '2026-07-14 14:00:00', 'label' => 'Tue, Jul 14, 2026 10:00 AM ET')
        );

        $this->assertTrue(NESPVapiIntegration::slotValueIsInAvailableSlots('2026-07-14 14:00:00', $availableSlots));
        $this->assertFalse(NESPVapiIntegration::slotValueIsInAvailableSlots('2026-07-14 03:00:00', $availableSlots));
    }

    public function testCancelAndHumanFollowUpClearStaleAppointmentState()
    {
        $source = file_get_contents(LEGACY_ROOT . '/lib/NESPWorkflow.php');

        $this->assertStringContainsString('scheduled_start_at_utc = NULL', $source);
        $this->assertStringContainsString('scheduled_end_at_utc = NULL', $source);
        $this->assertStringContainsString('scheduled_start_et = NULL', $source);
        $this->assertStringContainsString('human_follow_up_requested', $source);
    }

    public function testRescheduleCanUseDifferentOpenSlot()
    {
        $settings = NESPVapiIntegration::getDefaultPhoneScreenAvailabilitySettings();
        $appointments = array(
            array('scheduled_start_at_utc' => '2026-07-14 14:00:00')
        );

        $this->assertFalse(NESPVapiIntegration::slotConflictsWithAppointments('2026-07-14 15:00:00', $appointments, $settings));
    }
}
