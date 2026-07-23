<?php

use PHPUnit\Framework\TestCase;

include_once(LEGACY_ROOT . '/lib/NESPApplicationQuestions.php');
include_once(LEGACY_ROOT . '/lib/NESPCareerPortalProtection.php');

class NESPCareerPortalProtectionTest extends TestCase
{
    public function testProtectionIsLimitedToRecognizedNESPJobs()
    {
        foreach (array(41001, 41002, 41003, 41005) as $jobOrderID)
        {
            $this->assertTrue(NESPCareerPortalProtection::protectsJob($jobOrderID));
        }

        $this->assertFalse(NESPCareerPortalProtection::protectsJob(41004));
        $this->assertFalse(NESPCareerPortalProtection::protectsJob(99999));
    }

    public function testRenderedFieldsStoreOnlyTokenHashAndIncludeHoneypot()
    {
        $session = array();
        $html = NESPCareerPortalProtection::renderFields(41002, $session, 1000);

        $this->assertMatchesRegularExpression('/name="nesp_application_token" value="([a-f0-9]{64})"/', $html);
        preg_match('/name="nesp_application_token" value="([a-f0-9]{64})"/', $html, $matches);
        $token = $matches[1];

        $this->assertSame(
            hash('sha256', $token),
            $session[NESPCareerPortalProtection::FORM_SESSION_KEY][41002]['token_hash']
        );
        $this->assertStringNotContainsString(
            $token,
            $session[NESPCareerPortalProtection::FORM_SESSION_KEY][41002]['token_hash']
        );
        $this->assertStringContainsString('name="nesp_company_website"', $html);
        $this->assertSame('', NESPCareerPortalProtection::renderFields(99999, $session, 1000));
    }

    public function testOpeningAnotherFormForTheSameJobDoesNotInvalidateTheFirstForm()
    {
        $session = array();
        $firstHTML = NESPCareerPortalProtection::renderFields(41002, $session, 1000);
        $secondHTML = NESPCareerPortalProtection::renderFields(41002, $session, 1001);
        preg_match('/name="nesp_application_token" value="([a-f0-9]{64})"/', $firstHTML, $firstMatches);
        preg_match('/name="nesp_application_token" value="([a-f0-9]{64})"/', $secondHTML, $secondMatches);

        $firstResult = NESPCareerPortalProtection::validateSubmission(41002, array(
            NESPCareerPortalProtection::TOKEN_FIELD => $firstMatches[1],
            NESPCareerPortalProtection::HONEYPOT_FIELD => ''
        ), $session, '192.0.2.10', 1003);
        $secondResult = NESPCareerPortalProtection::validateSubmission(41002, array(
            NESPCareerPortalProtection::TOKEN_FIELD => $secondMatches[1],
            NESPCareerPortalProtection::HONEYPOT_FIELD => ''
        ), $session, '192.0.2.10', 1004);

        $this->assertTrue($firstResult['valid']);
        $this->assertTrue($secondResult['valid']);
        $this->assertCount(2, $session[NESPCareerPortalProtection::FORM_SESSION_KEY][41002]['tokens']);
    }

    public function testActiveFormsAreBoundedAndExpiredFormsArePruned()
    {
        $session = array();

        for ($index = 0; $index < NESPCareerPortalProtection::MAXIMUM_ACTIVE_FORMS_PER_JOB + 3; $index++)
        {
            NESPCareerPortalProtection::renderFields(41002, $session, 1000 + $index);
        }

        $this->assertCount(
            NESPCareerPortalProtection::MAXIMUM_ACTIVE_FORMS_PER_JOB,
            $session[NESPCareerPortalProtection::FORM_SESSION_KEY][41002]['tokens']
        );

        NESPCareerPortalProtection::renderFields(
            41002,
            $session,
            1000 + NESPCareerPortalProtection::MAXIMUM_FORM_AGE_SECONDS + 20
        );

        $this->assertCount(1, $session[NESPCareerPortalProtection::FORM_SESSION_KEY][41002]['tokens']);
    }

    public function testValidSubmissionPassesServerSideChecksForEveryNESPJob()
    {
        foreach (array(41001, 41002, 41003, 41005) as $jobOrderID)
        {
            $session = array();
            $html = NESPCareerPortalProtection::renderFields($jobOrderID, $session, 1000);
            preg_match('/name="nesp_application_token" value="([a-f0-9]{64})"/', $html, $matches);

            $result = NESPCareerPortalProtection::validateSubmission($jobOrderID, array(
                NESPCareerPortalProtection::TOKEN_FIELD => $matches[1],
                NESPCareerPortalProtection::HONEYPOT_FIELD => ''
            ), $session, '192.0.2.10', 1003);

            $this->assertTrue($result['valid'], 'Expected job ' . $jobOrderID . ' to accept a valid form token.');
            $this->assertSame('valid', $result['reason']);
        }
    }

    public function testInvalidTokenHoneypotTimingAndExpiryAreRejected()
    {
        $session = array();
        $html = NESPCareerPortalProtection::renderFields(41002, $session, 1000);
        preg_match('/name="nesp_application_token" value="([a-f0-9]{64})"/', $html, $matches);
        $token = $matches[1];

        $invalidToken = NESPCareerPortalProtection::validateSubmission(41002, array(
            NESPCareerPortalProtection::TOKEN_FIELD => str_repeat('0', 64)
        ), $session, '192.0.2.10', 1003);
        $this->assertSame('csrf', $invalidToken['reason']);

        $honeypot = NESPCareerPortalProtection::validateSubmission(41002, array(
            NESPCareerPortalProtection::TOKEN_FIELD => $token,
            NESPCareerPortalProtection::HONEYPOT_FIELD => 'spam.example'
        ), $session, '192.0.2.10', 1003);
        $this->assertSame('honeypot', $honeypot['reason']);

        $tooFast = NESPCareerPortalProtection::validateSubmission(41002, array(
            NESPCareerPortalProtection::TOKEN_FIELD => $token
        ), $session, '192.0.2.10', 1001);
        $this->assertSame('too_fast', $tooFast['reason']);

        $expired = NESPCareerPortalProtection::validateSubmission(41002, array(
            NESPCareerPortalProtection::TOKEN_FIELD => $token
        ), $session, '192.0.2.10', 1000 + NESPCareerPortalProtection::MAXIMUM_FORM_AGE_SECONDS + 1);
        $this->assertSame('expired', $expired['reason']);
    }

    public function testRateLimitRejectsExcessSubmissions()
    {
        $session = array();
        $html = NESPCareerPortalProtection::renderFields(41002, $session, 1000);
        preg_match('/name="nesp_application_token" value="([a-f0-9]{64})"/', $html, $matches);
        $post = array(NESPCareerPortalProtection::TOKEN_FIELD => $matches[1]);

        for ($attempt = 0; $attempt < NESPCareerPortalProtection::RATE_LIMIT_ATTEMPTS; $attempt++)
        {
            $result = NESPCareerPortalProtection::validateSubmission(
                41002,
                $post,
                $session,
                '192.0.2.10',
                1003 + $attempt
            );
            $this->assertTrue($result['valid']);
        }

        $blocked = NESPCareerPortalProtection::validateSubmission(
            41002,
            $post,
            $session,
            '192.0.2.10',
            1015
        );
        $this->assertFalse($blocked['valid']);
        $this->assertSame('rate_limited', $blocked['reason']);
    }

    public function testCareersHandlerKeepsLegacyCaptchaForOtherJobsAndExistingDuplicateChecks()
    {
        $source = file_get_contents(LEGACY_ROOT . '/modules/careers/CareersUI.php');

        $this->assertStringContainsString('NESPCareerPortalProtection::renderFields($jobID, $_SESSION)', $source);
        $this->assertStringContainsString('NESPCareerPortalProtection::protectsJob($jobID)', $source);
        $this->assertStringContainsString('NESPCareerPortalProtection::validateSubmission(', $source);
        $this->assertStringContainsString('else if (NESPApplicationQuestions::requiresLegacyCaptcha', $source);
        $this->assertStringContainsString('resolveCandidateEmailMatch($candidates, $email)', $source);
        $this->assertStringContainsString("\$candidateMatch['status'] === 'inactive'", $source);
        $this->assertStringContainsString('ensureCandidateJobOrderLink(', $source);
        $this->assertStringContainsString('routeCareerPortalApplicationToNeedsCraigResult(', $source);
        $this->assertStringNotContainsString('&& !$workflow->ensureCandidateWorkflowRow(', $source);
        $this->assertStringContainsString('NESPApplicationQuestions::validatePost($jobOrderID, $_POST)', $source);
        $this->assertGreaterThan(
            strpos($source, 'routeCareerPortalApplicationToNeedsCraigResult('),
            strrpos($source, "\$result['success'] = true;"),
            'The public success result must only be set after verified workflow routing.'
        );
    }
}
