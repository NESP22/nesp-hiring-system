<?php
use PHPUnit\Framework\TestCase;

include_once(LEGACY_ROOT . '/constants.php');
include_once(LEGACY_ROOT . '/config.php');
include_once(LEGACY_ROOT . '/lib/NESPJobDescriptionFormatter.php');

class NESPJobDescriptionFormatterTest extends TestCase
{
    public function testCustomerServiceCopyUsesYearRoundLanguage()
    {
        $description = "Quick Facts\n\nPay: $22-$25 per hour\n\nWhy This Role May Be a Good Fit\n\n- Consistent weekday work during the busy spring and fall seasons\n\nSchedule and Work Expectations\n\nThis role is based in the Methuen office. The schedule is set by agreement and is generally daytime weekday work during peak seasons.";

        $html = NESPJobDescriptionFormatter::formatHTML($description, 41001);
        $feed = NESPJobDescriptionFormatter::formatIndeed($description, 41001);

        $this->assertStringContainsString('Year-round weekday schedule with approximately 20-30 hours per week', $html);
        $this->assertStringContainsString('daytime weekday work throughout the year', $html);
        $this->assertStringNotContainsString('spring and fall seasons', $html);
        $this->assertStringNotContainsString('peak seasons', $html);
        $this->assertStringContainsString('Year-round weekday schedule with approximately 20-30 hours per week', $feed);
        $this->assertStringNotContainsString('spring and fall seasons', $feed);
    }
}

