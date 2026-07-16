<?php
use PHPUnit\Framework\TestCase;

include_once(LEGACY_ROOT . '/constants.php');
include_once(LEGACY_ROOT . '/config.php');
include_once(LEGACY_ROOT . '/lib/NESPWorkflow.php');
include_once(LEGACY_ROOT . '/lib/NESPVapiIntegration.php');
include_once(LEGACY_ROOT . '/lib/NESPRecruitingAds.php');

class NESPWorkflowTest extends TestCase
{
    public function testDefaultFeatureFlagsAreDisabled()
    {
        $flags = NESPWorkflow::getDefaultFeatureFlags();
        $keys = array();

        $this->assertCount(8, $flags);
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

        $this->assertCount(4, $statuses);
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

    public function testFeatureGateMappingKeepsSettingsOpen()
    {
        $this->assertSame('', NESPWorkflow::getFeatureFlagForAction('settings'));
        $this->assertSame('', NESPWorkflow::getFeatureFlagForAction('saveFeatureFlags'));
        $this->assertSame('NESP_WORKFLOW_ENABLED', NESPWorkflow::getFeatureFlagForAction('dashboard'));
        $this->assertSame('NESP_WORKFLOW_ENABLED', NESPWorkflow::getFeatureFlagForAction('waiting'));
        $this->assertSame('NESP_INTERVIEWER_POOL_ENABLED', NESPWorkflow::getFeatureFlagForAction('assignedCandidate'));
        $this->assertSame('NESP_INTERVIEWER_POOL_ENABLED', NESPWorkflow::getFeatureFlagForAction('submitScorecard'));
        $this->assertSame('NESP_STAFFING_FORECAST_ENABLED', NESPWorkflow::getFeatureFlagForAction('staffingForecast'));
        $this->assertSame('NESP_WORKFLOW_ENABLED', NESPWorkflow::getFeatureFlagForAction('phoneScreens'));
        $this->assertSame('NESP_WORKFLOW_ENABLED', NESPWorkflow::getFeatureFlagForAction('phoneScreenAvailability'));
        $this->assertSame('NESP_WORKFLOW_ENABLED', NESPWorkflow::getFeatureFlagForAction('markPhoneScreenInvitationCopied'));
        $this->assertSame('NESP_WORKFLOW_ENABLED', NESPWorkflow::getFeatureFlagForAction('allowPhoneScreenReschedule'));
        $this->assertSame('NESP_WORKFLOW_ENABLED', NESPWorkflow::getFeatureFlagForAction('unexpectedAction'));
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
            array('Needs Craig', 'Waiting', 'Interviews', 'Questionnaires', 'Phone Screens', 'Job Ads', 'Completed', 'Staffing Forecast', 'Interviewer Settings'),
            $labels
        );
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

        $link = NESPRecruitingAds::trackedApplicationURL(41002, 'craigslist');
        $this->assertStringContainsString('ID=41002', $link);
        $this->assertStringContainsString('nesp_source=craigslist', $link);
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

        $this->assertStringContainsString('saturdays and sundays', $labels);
        $this->assertStringContainsString('anything else', $labels);
        foreach (array('race', 'religion', 'marital', 'medical history', 'disability') as $forbidden)
        {
            $this->assertStringNotContainsString($forbidden, $labels);
        }
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
        $this->assertSame(array(41005), $byName['Brandon']['approved_joborder_ids']);
        $this->assertSame('email_needs_confirmation', $byName['Brandon']['account_state_key']);
        $this->assertStringContainsString('Please confirm', $byName['Brandon']['email_warning']);
        $this->assertSame(array(41002, 41003, 41005), $byName['Nate']['approved_joborder_ids']);
        $this->assertNotContains(41001, $byName['Nate']['approved_joborder_ids']);
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
        $this->assertContains('Requested time overlaps a blackout date.', $conflicts);
        $this->assertContains('Maximum daily interviews would be exceeded.', $conflicts);
        $this->assertContains('Maximum weekly interviews would be exceeded.', $conflicts);
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
