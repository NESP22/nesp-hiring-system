<?php

use PHPUnit\Framework\TestCase;

include_once(LEGACY_ROOT . '/lib/BoardApplicantIntake.php');

if (!defined('DATA_ITEM_CANDIDATE'))
{
    define('DATA_ITEM_CANDIDATE', 100);
}

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

    public function testBoardPreviewAllowsMissingEmailWithStableExternalID()
    {
        $result = BoardApplicantIntake::parseCsv(
            "external_id,first_name,last_name\nA-2,Alex,Applicant\n",
            'indeed', 41001, 'NESP Ad: Indeed'
        );

        $this->assertSame(array(), $result['errors']);
        $this->assertCount(1, $result['rows']);
        $this->assertSame('', $result['rows'][0]['email']);
        $this->assertSame('indeed:A-2', $result['rows'][0]['idempotency_key']);
        $this->assertSame('valid', $result['rows'][0]['validation_status']);
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

    public function testNativeBoardNotificationAllowsMissingEmailWithStableExternalID()
    {
        $result = BoardApplicantIntake::parseInboxNotification(
            "External ID: MH-100\nFirst Name: Alex\nLast Name: Applicant",
            'masshire', 41002, 'NESP Ad: MassHire'
        );

        $this->assertSame(array(), $result['errors']);
        $this->assertCount(1, $result['rows']);
        $this->assertSame('', $result['rows'][0]['email']);
        $this->assertSame('valid', $result['rows'][0]['validation_status']);
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
        $this->assertSame(
            'Freelance/Contract Youth Sports Photographer',
            BoardApplicantIntake::allowedJobOrders()[41003]
        );
        $this->assertSame(
            'Weekend Table Greeter / Field Assistant',
            BoardApplicantIntake::allowedJobOrders()[41005]
        );
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

    public function testCsvPreviewAllowsFreelancePhotographerAndFieldAssistantJobOrders()
    {
        foreach (array(41003, 41005) as $jobOrderID)
        {
            $result = BoardApplicantIntake::parseCsv(
                "external_id,first_name,last_name,email\nA-{$jobOrderID},Alex,Applicant,alex@example.test\n",
                'indeed', $jobOrderID, 'NESP Ad: Indeed'
            );

            $this->assertSame(array(), $result['errors'], 'Job order ' . $jobOrderID . ' should be supported.');
            $this->assertCount(1, $result['rows']);
            $this->assertSame('valid', $result['rows'][0]['validation_status']);
        }
    }

    public function testInboxNotificationCreatesReviewOnlyRowWithRequiredIdentity()
    {
        $result = BoardApplicantIntake::parseInboxNotification(
            "External ID: IN-123\nFirst Name: Alex\nLast Name: Applicant\nEmail: alex@example.test\nPhone: 555-0100",
            'indeed', 41002, 'NESP Ad: Indeed'
        );

        $this->assertSame(array(), $result['errors']);
        $this->assertCount(1, $result['rows']);
        $this->assertSame('indeed:IN-123', $result['rows'][0]['idempotency_key']);
        $this->assertSame('valid', $result['rows'][0]['validation_status']);
    }

    public function testInboxNotificationRejectsUnidentifiedApplicant()
    {
        $result = BoardApplicantIntake::parseInboxNotification(
            "Applicant: Alex Applicant\nEmail: alex@example.test",
            'indeed', 41002, 'NESP Ad: Indeed'
        );

        $this->assertCount(1, $result['rows']);
        $this->assertSame('invalid', $result['rows'][0]['validation_status']);
        $this->assertStringContainsString('external_id', strtolower(implode(' ', $result['rows'][0]['validation_errors'])));
    }

    public function testCsvPreviewRejectsUnsupportedJobOrder()
    {
        $result = BoardApplicantIntake::parseCsv(
            "external_id,first_name,last_name,email\nA-41004,Alex,Applicant,alex@example.test\n",
            'indeed', 41004, 'NESP Ad: Indeed'
        );

        $this->assertStringContainsString('approved job order', strtolower(implode(' ', $result['errors'])));
    }

    public function testPublicAndBoardApplicantSourcesPrepareHumanReviewedQuestionnaires()
    {
        $careers = file_get_contents(LEGACY_ROOT . '/modules/careers/CareersUI.php');
        $intake = file_get_contents(LEGACY_ROOT . '/lib/BoardApplicantIntake.php');

        $this->assertStringContainsString('routeCareerPortalApplicationToNeedsCraig', $careers);
        $this->assertStringContainsString('ensureCandidateWorkflowRow', $intake);
        $this->assertStringContainsString('prepareQuestionnaireForHumanReview', $intake);
        $this->assertStringContainsString('ready for human sending', $intake);
        $this->assertStringContainsString('Contact details required before any questionnaire or outreach.', $intake);
    }

    public function testBulkImportKeepsReviewAndDuplicateSafeguards()
    {
        $intake = file_get_contents(LEGACY_ROOT . '/lib/BoardApplicantIntake.php');
        $ui = file_get_contents(LEGACY_ROOT . '/modules/boardintake/BoardIntakeUI.php');
        $template = file_get_contents(LEGACY_ROOT . '/modules/boardintake/Review.tpl');

        $this->assertStringContainsString('importAllApprovedRows', $intake);
        $this->assertStringContainsString('applyDuplicateChecks($batchID)', $intake);
        $this->assertStringContainsString('importApprovedRows($actorUserID, $batchID)', $intake);
        $this->assertStringContainsString("case 'importAllApproved'", $ui);
        $this->assertStringContainsString("case 'uploadInboxNotification'", $ui);
        $this->assertStringContainsString('parseInboxNotification', $intake);
        $this->assertStringContainsString('Three simple steps:', $template);
        $this->assertStringContainsString('Create Inbox Review Batch', $template);
        $this->assertStringContainsString('Import All Reviewed Applicants', $template);
        $this->assertStringContainsString('Import Approved Applicants to Needs Craig', $template);
        $this->assertStringContainsString('reviewed and approved', $template);
    }

    public function testImportsVerifyTheCandidateJobOrderLinkBeforeWorkflowRouting()
    {
        $intake = file_get_contents(LEGACY_ROOT . '/lib/BoardApplicantIntake.php');

        $this->assertStringContainsString('ensureCandidateJobOrderLink(', $intake);
        $this->assertStringContainsString('Candidate job-order attachment could not be verified.', $intake);
        $this->assertStringContainsString('FROM candidate_joborder', $intake);
        $this->assertStringContainsString('More than one candidate/job-order link was found.', $intake);
        $this->assertStringContainsString('ensureCandidateWorkflowRow(', $intake);
        $this->assertLessThan(
            strpos($intake, 'ensureCandidateWorkflowRow('),
            strpos($intake, 'ensureCandidateJobOrderLink(')
        );
    }

    public function testImportedBatchRepairIsBoundedIdempotentAndDoesNotContactApplicants()
    {
        $intake = file_get_contents(LEGACY_ROOT . '/lib/BoardApplicantIntake.php');
        $ui = file_get_contents(LEGACY_ROOT . '/modules/boardintake/BoardIntakeUI.php');
        $template = file_get_contents(LEGACY_ROOT . '/modules/boardintake/Review.tpl');

        $this->assertStringContainsString('repairImportedCandidateJobOrderLinks', $intake);
        $this->assertStringContainsString('intake_row.review_status = "imported"', $intake);
        $this->assertStringContainsString('intake_identity.candidate_id = intake_row.candidate_id', $intake);
        $this->assertStringContainsString("case 'repairImportedJobOrderLinks'", $ui);
        $this->assertStringContainsString('requirePostCSRF()', $ui);
        $this->assertStringContainsString('Verify and Repair Job-Order Links', $template);
        $this->assertStringContainsString('never creates candidates, changes contact details, or sends a questionnaire', $template);
    }

    public function testCandidatePipelineKeepsInternalJobPostingLinksVisibleWithoutCompanyRecord()
    {
        $pipelines = file_get_contents(LEGACY_ROOT . '/lib/Pipelines.php');

        $this->assertStringContainsString(
            'Legacy job orders can legitimately use the internal posting',
            $pipelines
        );
        $this->assertSame(
            0,
            substr_count(
                $pipelines,
                "INNER JOIN company\n                ON company.company_id = joborder.company_id"
            ),
            'Candidate pipeline rendering must not hide valid job links when a legacy company record is absent.'
        );
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

    public function testBlankEmailsDoNotCauseUnrelatedBoardRowsToBeDuplicates()
    {
        $rows = array(
            array('intake_row_id' => 1, 'validation_status' => 'valid', 'external_id' => 'board-1', 'email' => '', 'first_name' => 'Alex', 'last_name' => 'Applicant'),
            array('intake_row_id' => 2, 'validation_status' => 'valid', 'external_id' => 'board-2', 'email' => '', 'first_name' => 'Jordan', 'last_name' => 'Candidate'),
        );

        $this->assertSame(array(), BoardApplicantIntake::batchDuplicateRowIDs($rows));
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

    public function testResumeUploadValidationAllowsOnlyBoundedResumeFiles()
    {
        $valid = array(
            'name' => 'resume.PDF',
            'tmp_name' => '/tmp/php-upload',
            'size' => 1024,
            'error' => UPLOAD_ERR_OK
        );

        $this->assertSame('', BoardApplicantIntake::validateResumeUpload($valid));

        $invalidType = $valid;
        $invalidType['name'] = 'resume.html';
        $this->assertStringContainsString('PDF', BoardApplicantIntake::validateResumeUpload($invalidType));

        $tooLarge = $valid;
        $tooLarge['size'] = BoardApplicantIntake::MAX_RESUME_BYTES + 1;
        $this->assertStringContainsString('10 MB', BoardApplicantIntake::validateResumeUpload($tooLarge));

        $empty = $valid;
        $empty['size'] = 0;
        $this->assertStringContainsString('empty', BoardApplicantIntake::validateResumeUpload($empty));

        $nested = $valid;
        $nested['tmp_name'] = array('/tmp/php-upload');
        $this->assertStringContainsString('local resume', BoardApplicantIntake::validateResumeUpload($nested));
    }

    public function testResumeTargetRequiresImportedIdentityAndJobMapping()
    {
        $db = new class {
            public $sql = '';

            public function makeQueryInteger($value)
            {
                return (string) (int) $value;
            }

            public function getAssoc($sql)
            {
                $this->sql = $sql;
                return array('candidate_id' => 91, 'joborder_id' => 41002);
            }
        };

        $target = (new BoardApplicantIntake($db))->getConfirmedResumeTarget(12, 34);

        $this->assertSame(91, $target['candidate_id']);
        $this->assertSame(41002, $target['joborder_id']);
        $this->assertStringContainsString('intake_identity.candidate_id = intake_row.candidate_id', $db->sql);
        $this->assertStringContainsString('candidate_joborder.joborder_id = intake_batch.joborder_id', $db->sql);
        $this->assertStringContainsString('intake_row.review_status = "imported"', $db->sql);
        $this->assertStringContainsString('intake_batch.status_key = "imported"', $db->sql);
    }

    public function testResumeUploadUsesConfirmedCandidateAndExistingAttachmentCreator()
    {
        $db = new class {
            public function makeQueryInteger($value)
            {
                return (string) (int) $value;
            }

            public function getAssoc($sql)
            {
                return array('candidate_id' => 91, 'joborder_id' => 41002);
            }
        };
        $creator = new class {
            public $call = array();

            public function createFromUpload($dataItemType, $dataItemID, $fileField, $isProfileImage, $extractText)
            {
                $this->call = func_get_args();
                return true;
            }

            public function duplicatesOccurred()
            {
                return false;
            }

            public function getAttachmentID()
            {
                return 73;
            }
        };
        $originalFiles = $_FILES;
        $_FILES['resume'] = array(
            'name' => 'candidate-resume.pdf',
            'tmp_name' => '/tmp/php-upload',
            'type' => 'application/pdf',
            'size' => 2048,
            'error' => UPLOAD_ERR_OK
        );

        try
        {
            $result = (new BoardApplicantIntake($db))->attachResumeUpload(
                12,
                34,
                'resume',
                $creator,
                function ($path) { return $path === '/tmp/php-upload'; }
            );
        }
        finally
        {
            $_FILES = $originalFiles;
        }

        $this->assertSame(array(DATA_ITEM_CANDIDATE, 91, 'resume', false, true), $creator->call);
        $this->assertSame(73, $result['attachment_id']);
        $this->assertSame(41002, $result['joborder_id']);
    }

    public function testResumeUploadFailsBeforeAttachmentWhenMappingIsMissing()
    {
        $db = new class {
            public function makeQueryInteger($value)
            {
                return (string) (int) $value;
            }

            public function getAssoc($sql)
            {
                return array();
            }
        };
        $creator = new class {
            public $called = false;

            public function createFromUpload()
            {
                $this->called = true;
                return true;
            }
        };

        try
        {
            (new BoardApplicantIntake($db))->attachResumeUpload(12, 34, 'resume', $creator);
            $this->fail('An unconfirmed candidate mapping must stop the upload.');
        }
        catch (RuntimeException $e)
        {
            $this->assertStringContainsString('identity and job mapping', $e->getMessage());
        }

        $this->assertFalse($creator->called);
    }

    public function testResumeUploadPathHasNoUrlRetrievalOrCandidateContactMutation()
    {
        $method = new ReflectionMethod(BoardApplicantIntake::class, 'attachResumeUpload');
        $lines = file($method->getFileName());
        $source = implode('', array_slice(
            $lines,
            $method->getStartLine() - 1,
            $method->getEndLine() - $method->getStartLine() + 1
        ));
        $ui = file_get_contents(LEGACY_ROOT . '/modules/boardintake/BoardIntakeUI.php');
        $template = file_get_contents(LEGACY_ROOT . '/modules/boardintake/Review.tpl');

        $this->assertStringContainsString('createFromUpload', $source);
        $this->assertStringContainsString('is_uploaded_file', $source);
        $this->assertStringNotContainsString('createFromFile', $source);
        $this->assertStringNotContainsString('file_get_contents', $source);
        $this->assertStringNotContainsString('Candidates', $source);
        $this->assertStringContainsString("case 'uploadResume'", $ui);
        $this->assertStringContainsString('type="file" name="resume"', $template);
        $this->assertStringNotContainsString('name="resumeURL"', $template);
    }
}
