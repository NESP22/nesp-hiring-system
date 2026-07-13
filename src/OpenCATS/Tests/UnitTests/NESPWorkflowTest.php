<?php
use PHPUnit\Framework\TestCase;

include_once(LEGACY_ROOT . '/lib/NESPWorkflow.php');

class NESPWorkflowTest extends TestCase
{
    public function testDefaultFeatureFlagsAreDisabled()
    {
        $flags = NESPWorkflow::getDefaultFeatureFlags();
        $keys = array();

        $this->assertCount(6, $flags);
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
}
