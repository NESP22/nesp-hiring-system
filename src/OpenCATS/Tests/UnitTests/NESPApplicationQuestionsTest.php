<?php
use PHPUnit\Framework\TestCase;

include_once(LEGACY_ROOT . '/lib/NESPApplicationQuestions.php');

class NESPApplicationQuestionsTest extends TestCase
{
    public function testApprovedJobsHavePrescreenQuestions()
    {
        foreach (array(41001, 41002, 41003, 41005) as $jobOrderID)
        {
            $this->assertTrue(NESPApplicationQuestions::hasQuestionsForJob($jobOrderID));
            $this->assertNotEmpty(NESPApplicationQuestions::getRoleTitle($jobOrderID));
            $this->assertNotEmpty(NESPApplicationQuestions::getQuestionsForJob($jobOrderID));
        }

        $this->assertFalse(NESPApplicationQuestions::hasQuestionsForJob(41004));
    }

    public function testRequiredQuestionsAreValidated()
    {
        $errors = NESPApplicationQuestions::validatePost(41001, array(
            'nesp_prescreen' => array(
                'methuen_office' => 'Yes',
                'weekday_schedule' => 'Yes',
                'experience_years' => '1-2 years',
                'calls_and_email' => 'Yes'
            )
        ));

        $this->assertContains('Are you comfortable using email, spreadsheets, support systems, and web applications?', $errors);
    }

    public function testInvalidOptionIsRejected()
    {
        $errors = NESPApplicationQuestions::validatePost(41001, array(
            'nesp_prescreen' => array(
                'methuen_office' => 'Yes',
                'weekday_schedule' => 'Yes',
                'experience_years' => '10 years',
                'calls_and_email' => 'Yes',
                'computer_systems' => 'Yes'
            )
        ));

        $this->assertContains('How many years of customer-service or administrative experience do you have?', $errors);
    }

    public function testAnswersAreExtractedForBackendQuestionnaireHistory()
    {
        $answers = NESPApplicationQuestions::extractAnswers(41003, array(
            'nesp_prescreen' => array(
                'camera_body' => 'Yes',
                'external_flash' => 'Yes',
                'portrait_lens' => 'Yes',
                'manual_settings' => 'Yes',
                'early_weekends' => 'Yes',
                'travel' => 'No',
                'transportation' => 'Yes',
                'equipment_list' => 'Canon R6, Canon 600EX-RT, 24-105mm lens',
                'photo_experience' => 'Portrait and event photography'
            )
        ));

        $this->assertSame('Do you own a professional or advanced camera body suitable for portrait work?', $answers[0]['question']);
        $this->assertSame('Yes', $answers[0]['answer']);
        $this->assertSame('List the camera body, flash, and primary portrait lens you would use.', $answers[7]['question']);
        $this->assertSame('Canon R6, Canon 600EX-RT, 24-105mm lens', $answers[7]['answer']);
    }

    public function testRenderedQuestionsIncludeHumanReviewStatement()
    {
        $html = NESPApplicationQuestions::renderForJob(41005);

        $this->assertStringContainsString('Every application is reviewed by a person.', $html);
        $this->assertStringContainsString('Can you lift up to 25 pounds?', $html);
        $this->assertStringContainsString('name="nesp_prescreen[lift_25]"', $html);
    }
}
