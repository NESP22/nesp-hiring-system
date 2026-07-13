<?php
use PHPUnit\Framework\TestCase;

include_once(LEGACY_ROOT . '/lib/NESPWorkflow.php');

class NESPWorkflowTest extends TestCase
{
    public function testDefaultFeatureFlagsAreDisabled()
    {
        $flags = NESPWorkflow::getDefaultFeatureFlags();

        $this->assertCount(6, $flags);
        foreach ($flags as $flag)
        {
            $this->assertSame(0, $flag[3]);
        }
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
            array('flag_key' => 'ai_candidate_review_enabled', 'is_enabled' => 0),
            array('flag_key' => 'zoom_scheduling_enabled', 'is_enabled' => 1)
        );

        $this->assertFalse(NESPWorkflow::isIntegrationEnabledFromFlags($flags, 'ai_candidate_review_enabled'));
        $this->assertTrue(NESPWorkflow::isIntegrationEnabledFromFlags($flags, 'zoom_scheduling_enabled'));
        $this->assertFalse(NESPWorkflow::isIntegrationEnabledFromFlags($flags, 'vapi_phone_screening_enabled'));
    }
}
