<?php
use PHPUnit\Framework\TestCase;

include_once(LEGACY_ROOT . '/lib/NESPWorkflow.php');

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
            array('Needs Craig', 'Waiting', 'Interviews', 'Completed', 'Staffing Forecast', 'Settings'),
            $labels
        );
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
}
