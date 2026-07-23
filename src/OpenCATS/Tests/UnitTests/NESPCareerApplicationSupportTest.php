<?php
use PHPUnit\Framework\TestCase;

include_once(LEGACY_ROOT . '/lib/FileUtility.php');
include_once(LEGACY_ROOT . '/lib/NESPCareerApplicationSupport.php');

class NESPCareerApplicationSupportTest extends TestCase
{
    public function testInactiveEmailMatchIsExplicitAndNotReused()
    {
        $candidates = new class {
            public function getIDByEmail($email)
            {
                return 41;
            }

            public function get($candidateID)
            {
                return array('candidateID' => $candidateID, 'isActive' => 0);
            }
        };

        $match = NESPCareerApplicationSupport::resolveCandidateEmailMatch(
            $candidates,
            'inactive@example.test'
        );

        $this->assertSame('inactive', $match['status']);
        $this->assertSame(41, $match['candidateID']);
    }

    public function testActiveEmailMatchCanBeReusedWithoutDuplication()
    {
        $candidates = new class {
            public function getIDByEmail($email)
            {
                return 42;
            }

            public function get($candidateID)
            {
                return array('candidateID' => $candidateID, 'isActive' => 1);
            }
        };

        $match = NESPCareerApplicationSupport::resolveCandidateEmailMatch(
            $candidates,
            'active@example.test'
        );

        $this->assertSame('active', $match['status']);
        $this->assertSame(42, $match['candidateID']);
    }

    public function testFailedPipelineWriteCannotBecomeApplicationSuccess()
    {
        $pipelines = new class {
            public function get($candidateID, $jobOrderID)
            {
                return array();
            }

            public function add($candidateID, $jobOrderID, $actorUserID)
            {
                return false;
            }
        };

        $result = NESPCareerApplicationSupport::ensureCandidateJobOrderLink(
            $pipelines,
            21,
            41002,
            1
        );

        $this->assertFalse($result['success']);
        $this->assertSame(0, $result['candidateJobOrderID']);
    }

    public function testPipelineWriteMustBeReadableBeforeApplicationContinues()
    {
        $pipelines = new class {
            public $reads = 0;

            public function get($candidateID, $jobOrderID)
            {
                $this->reads++;
                return array();
            }

            public function add($candidateID, $jobOrderID, $actorUserID)
            {
                return true;
            }
        };

        $result = NESPCareerApplicationSupport::ensureCandidateJobOrderLink(
            $pipelines,
            22,
            41003,
            1
        );

        $this->assertFalse($result['success']);
        $this->assertTrue($result['newApplication']);
        $this->assertSame(2, $pipelines->reads);
    }

    public function testMissingResumeIsOptional()
    {
        $this->assertSame(
            array('hasUpload' => false, 'warning' => ''),
            NESPCareerApplicationSupport::inspectResumeUpload(array())
        );
        $this->assertSame(
            array('hasUpload' => false, 'warning' => ''),
            NESPCareerApplicationSupport::inspectResumeUpload(array(
                'file' => array('name' => '', 'error' => UPLOAD_ERR_NO_FILE)
            ))
        );
    }

    public function testSuccessfulResumeIsDetected()
    {
        $this->assertSame(
            array('hasUpload' => true, 'warning' => ''),
            NESPCareerApplicationSupport::inspectResumeUpload(array(
                'file' => array('name' => 'resume.pdf', 'error' => UPLOAD_ERR_OK)
            ))
        );
    }

    public function testUploadFailureDoesNotBecomeAnApplicationFailure()
    {
        $result = NESPCareerApplicationSupport::inspectResumeUpload(array(
            'file' => array('name' => 'resume.pdf', 'error' => UPLOAD_ERR_INI_SIZE)
        ));

        $this->assertFalse($result['hasUpload']);
        $this->assertNotSame('', $result['warning']);
    }

    public function testResumeInputExplainsAcceptedFormats()
    {
        $html = NESPCareerApplicationSupport::resumeInputHTML();

        $this->assertStringContainsString('accept=".pdf,.doc,.docx,.rtf,.odt,.txt,.pages"', $html);
        $this->assertStringContainsString('Optional.', $html);
    }

    public function testSuccessPageConfirmsReceiptAndNextSteps()
    {
        $html = NESPCareerApplicationSupport::renderSuccessPage(
            'Staff Photographer <script>',
            'unsupported'
        );

        $this->assertStringContainsString('Thank you for applying', $html);
        $this->assertStringContainsString('was received', $html);
        $this->assertStringContainsString('contact you about next steps', $html);
        $this->assertStringContainsString('resume could not be attached', $html);
        $this->assertStringNotContainsString('<script>', $html);
    }
}
