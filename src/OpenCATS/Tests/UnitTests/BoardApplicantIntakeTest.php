<?php

use PHPUnit\Framework\TestCase;

include_once(LEGACY_ROOT . '/lib/BoardApplicantIntake.php');

class BoardApplicantIntakeTest extends TestCase
{
    public function testCsvPreviewRequiresExplicitFieldsAndDoesNotAcceptAttachmentUrls()
    {
        $result = BoardApplicantIntake::parseCsv(
            "external_id,first_name,last_name,email,resume_url\nA-1,Alex,Applicant,alex@example.test,https://example.test/resume.pdf\n",
            'indeed', 41001, 'NESP Ad: Indeed'
        );

        $this->assertCount(0, $result['rows']);
        $this->assertStringContainsString('resume_url', $result['errors'][0]);
    }

    public function testCsvPreviewNormalizesEmailAndCreatesExternalIdempotencyKey()
    {
        $result = BoardApplicantIntake::parseCsv(
            "external_id,first_name,last_name,email,phone\nA-1,Alex,Applicant, Alex@Example.Test ,555-0100\n",
            'indeed', 41001, 'NESP Ad: Indeed'
        );

        $this->assertSame(array(), $result['errors']);
        $this->assertCount(1, $result['rows']);
        $this->assertSame('alex@example.test', $result['rows'][0]['email']);
        $this->assertSame('indeed:A-1', $result['rows'][0]['idempotency_key']);
        $this->assertSame('valid', $result['rows'][0]['validation_status']);
    }

    public function testCsvPreviewRequiresExternalIDForExactlyOnceImport()
    {
        $result = BoardApplicantIntake::parseCsv(
            "first_name,last_name,email\nAlex,Applicant,alex@example.test\n",
            'indeed', 41001, 'NESP Ad: Indeed'
        );

        $this->assertSame(array(), $result['rows']);
        $this->assertStringContainsString('external_id', strtolower(implode(' ', $result['errors'])));
    }

    public function testNonLinkedInPreviewStillRequiresEmail()
    {
        $result = BoardApplicantIntake::parseCsv(
            "external_id,first_name,last_name\nA-2,Alex,Applicant\n",
            'indeed', 41001, 'NESP Ad: Indeed'
        );

        $this->assertSame(array(), $result['rows']);
        $this->assertStringContainsString('email', strtolower(implode(' ', $result['errors'])));
    }

    public function testLinkedInPreviewAllowsMissingEmailWithExternalID()
    {
        $result = BoardApplicantIntake::parseCsv(
            "external_id,first_name,last_name\nLI-100,Alex,Applicant\n",
            'linkedin', 41002, 'NESP Ad: LinkedIn'
        );

        $this->assertSame(array(), $result['errors']);
        $this->assertCount(1, $result['rows']);
        $this->assertSame('', $result['rows'][0]['email']);
        $this->assertSame('linkedin:LI-100', $result['rows'][0]['idempotency_key']);
        $this->assertSame('valid', $result['rows'][0]['validation_status']);
    }

    public function testLinkedInPreviewStillRequiresExternalIDWhenEmailIsMissing()
    {
        $result = BoardApplicantIntake::parseCsv(
            "first_name,last_name\nAlex,Applicant\n",
            'linkedin', 41002, 'NESP Ad: LinkedIn'
        );

        $this->assertSame(array(), $result['rows']);
        $this->assertStringContainsString('external_id', strtolower(implode(' ', $result['errors'])));
    }

    public function testCsvPreviewRejectsMissingIdentityAndInvalidEmail()
    {
        $result = BoardApplicantIntake::parseCsv(
            "external_id,first_name,last_name,email\n,,Applicant,not-an-email\n",
            'indeed', 41001, 'NESP Ad: Indeed'
        );

        $this->assertCount(1, $result['rows']);
        $this->assertSame('invalid', $result['rows'][0]['validation_status']);
        $this->assertCount(3, $result['rows'][0]['validation_errors']);
    }

    public function testSourceLabelAndJobOrderAreBoundToApprovedValues()
    {
        $this->assertSame('NESP Ad: Indeed', BoardApplicantIntake::canonicalSourceLabel('indeed', 'nesp ad: indeed'));
        $this->assertSame('', BoardApplicantIntake::canonicalSourceLabel('indeed', 'Indeed'));
        $this->assertSame('', BoardApplicantIntake::canonicalSourceLabel('unknown', 'NESP Ad: Unknown'));
        $this->assertArrayHasKey(41001, BoardApplicantIntake::allowedJobOrders());
        $this->assertSame('Staff Photographer', BoardApplicantIntake::allowedJobOrders()[41002]);
    }

    public function testCsvPreviewAllowsStaffPhotographerJobOrder()
    {
        $result = BoardApplicantIntake::parseCsv(
            "external_id,first_name,last_name,email\nA-41002,Alex,Applicant,alex@example.test\n",
            'indeed', 41002, 'NESP Ad: Indeed'
        );

        $this->assertSame(array(), $result['errors']);
        $this->assertCount(1, $result['rows']);
        $this->assertSame('valid', $result['rows'][0]['validation_status']);
    }

    public function testCsvPreviewRejectsUnsupportedJobOrder()
    {
        $result = BoardApplicantIntake::parseCsv(
            "external_id,first_name,last_name,email\nA-41003,Alex,Applicant,alex@example.test\n",
            'indeed', 41003, 'NESP Ad: Indeed'
        );

        $this->assertStringContainsString('approved job order', strtolower(implode(' ', $result['errors'])));
    }

    public function testPublicAndBoardApplicantSourcesUseTheCentralWorkflowEnsureHelper()
    {
        $careers = file_get_contents(LEGACY_ROOT . '/modules/careers/CareersUI.php');
        $intake = file_get_contents(LEGACY_ROOT . '/lib/BoardApplicantIntake.php');

        $this->assertStringContainsString('ensureCandidateWorkflowRow', $careers);
        $this->assertStringContainsString('ensureCandidateWorkflowRow', $intake);
        $this->assertStringContainsString('Contact details required before any questionnaire or outreach.', $intake);
    }

    public function testDuplicateReviewFlagsRepeatedEmailOrNameRows()
    {
        $rows = array(
            array('intake_row_id' => 1, 'validation_status' => 'valid', 'email' => 'same@example.test', 'first_name' => 'Alex', 'last_name' => 'Applicant'),
            array('intake_row_id' => 2, 'validation_status' => 'valid', 'email' => 'other@example.test', 'first_name' => 'Alex', 'last_name' => 'Applicant'),
            array('intake_row_id' => 3, 'validation_status' => 'valid', 'email' => 'same@example.test', 'first_name' => 'Jordan', 'last_name' => 'Candidate'),
            array('intake_row_id' => 4, 'validation_status' => 'invalid', 'email' => 'same@example.test', 'first_name' => 'Invalid', 'last_name' => 'Row'),
        );

        $this->assertSame(array(1, 2, 3), BoardApplicantIntake::batchDuplicateRowIDs($rows));
    }

    public function testDuplicateReviewFlagsRepeatedExternalIDs()
    {
        $rows = array(
            array('intake_row_id' => 1, 'validation_status' => 'valid', 'external_id' => 'indeed-1', 'email' => 'one@example.test', 'first_name' => 'Alex', 'last_name' => 'Applicant'),
            array('intake_row_id' => 2, 'validation_status' => 'valid', 'external_id' => 'indeed-1', 'email' => 'two@example.test', 'first_name' => 'Jordan', 'last_name' => 'Candidate'),
        );

        $this->assertSame(array(1, 2), BoardApplicantIntake::batchDuplicateRowIDs($rows));
    }

    public function testExpiredStagingCleanupFailsClosedWhenDeletionFails()
    {
        $db = new class {
            public $calls = array();
            private $results = array(true, false);

            public function query($sql, $ignoreErrors = false)
            {
                $this->calls[] = array('sql' => $sql, 'ignoreErrors' => $ignoreErrors);
                return array_shift($this->results);
            }
        };

        $intake = new BoardApplicantIntake($db);

        try
        {
            $intake->purgeExpiredStaging();
            $this->fail('Expired staging cleanup should stop when a delete fails.');
        }
        catch (RuntimeException $e)
        {
            $this->assertStringContainsString('retention cleanup failed', strtolower($e->getMessage()));
            $this->assertStringNotContainsString('delete from', strtolower($e->getMessage()));
        }

        $this->assertCount(2, $db->calls);
        $this->assertTrue($db->calls[0]['ignoreErrors']);
        $this->assertTrue($db->calls[1]['ignoreErrors']);
    }
}
