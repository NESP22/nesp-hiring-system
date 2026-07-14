<?php
use PHPUnit\Framework\TestCase;

include_once(LEGACY_ROOT . '/lib/NESPWorkflow.php');
include_once(LEGACY_ROOT . '/lib/NESPVapiIntegration.php');

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
            array('Needs Craig', 'Waiting', 'Interviews', 'Phone Screens', 'Completed', 'Staffing Forecast', 'Settings'),
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
        $this->assertSame(30, $template['slot_minutes']);
        $this->assertStringContainsString('Zoom creation remain disabled', $template['notes']);
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
        $this->assertFalse($payload['assistantOverrides']['artifactPlan']['recordingEnabled']);
        $this->assertFalse($payload['assistantOverrides']['artifactPlan']['videoRecordingEnabled']);
        $this->assertTrue($payload['assistantOverrides']['artifactPlan']['transcriptPlan']['enabled']);
        $this->assertSame('Freelance Photographer', $payload['assistantOverrides']['variableValues']['role']);
        $this->assertSame('off', $payload['assistantOverrides']['variableValues']['audio_recording']);
        $this->assertSame('request_fixture', $payload['assistantOverrides']['metadata']['nesp_call_request_key']);

        putenv('VAPI_HIRING_ASSISTANT_ID');
        putenv('VAPI_PHONE_NUMBER_ID');
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
        $this->assertStringContainsString('Audio will not be recorded', $copy);
        $this->assertStringContainsString('Every hiring decision is made by a person', $copy);
    }

    public function testDuplicateBookingIsRejected()
    {
        $settings = NESPVapiIntegration::getDefaultPhoneScreenAvailabilitySettings();
        $appointments = array(
            array('scheduled_start_at_utc' => '2026-07-14 14:00:00')
        );

        $this->assertTrue(NESPVapiIntegration::slotConflictsWithAppointments('2026-07-14 14:00:00', $appointments, $settings));
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
