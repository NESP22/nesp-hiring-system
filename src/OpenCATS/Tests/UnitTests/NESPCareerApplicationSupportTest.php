<?php
use PHPUnit\Framework\TestCase;

include_once(LEGACY_ROOT . '/lib/FileUtility.php');
include_once(LEGACY_ROOT . '/lib/NESPCareerApplicationSupport.php');

class NESPCareerApplicationSupportTest extends TestCase
{
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
