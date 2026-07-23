<?php

use PHPUnit\Framework\TestCase;

include_once(LEGACY_ROOT . '/lib/NESPWorkflow.php');
include_once(LEGACY_ROOT . '/lib/BoardApplicantIntake.php');
include_once(LEGACY_ROOT . '/modules/nespSimple/public/NESPSimplePublicApplication.php');

class NESPSimplePublicApplicationTest extends TestCase
{
    private $signingKey = 'unit-test-signing-key-that-is-longer-than-32-characters';

    public function testOnlyTheFourApprovedJobsAreAvailable()
    {
        $this->assertSame(
            array(41001, 41002, 41003, 41005),
            array_keys(NESPSimplePublicApplication::allowedJobs())
        );
        $this->assertSame(
            'customer_service',
            NESPSimplePublicApplication::questionnaireDefinitionForJob(41001)['questionSetKey']
        );
        $this->assertSame(
            'weekend_sports_photographer',
            NESPSimplePublicApplication::questionnaireDefinitionForJob(41002)['questionSetKey']
        );
        $this->assertSame(
            'weekend_sports_photographer',
            NESPSimplePublicApplication::questionnaireDefinitionForJob(41003)['questionSetKey']
        );
        $this->assertSame(
            'photography_assistant_poser',
            NESPSimplePublicApplication::questionnaireDefinitionForJob(41005)['questionSetKey']
        );
        $this->assertSame(array(), NESPSimplePublicApplication::questionnaireDefinitionForJob(99999));
    }

    public function testSignedTokensAreStatelessRetrySafeAndMultiTabSafe()
    {
        $first = NESPSimplePublicApplication::createFormToken(
            41002,
            $this->signingKey,
            1000,
            'first-browser-tab-nonce'
        );
        $second = NESPSimplePublicApplication::createFormToken(
            41002,
            $this->signingKey,
            1001,
            'second-browser-tab-nonce'
        );

        $this->assertNotSame($first, $second);
        $this->assertTrue(NESPSimplePublicApplication::validateFormToken($first, 41002, $this->signingKey, 1010)['ok']);
        $this->assertTrue(NESPSimplePublicApplication::validateFormToken($second, 41002, $this->signingKey, 1010)['ok']);
        $this->assertTrue(NESPSimplePublicApplication::validateFormToken($first, 41002, $this->signingKey, 1011)['ok']);
    }

    public function testTamperedWrongJobFastExpiredAndMissingKeyTokensFailClosed()
    {
        $token = NESPSimplePublicApplication::createFormToken(
            41002,
            $this->signingKey,
            1000,
            'stable-test-nonce-value'
        );
        $tampered = substr($token, 0, -1) . (substr($token, -1) === 'a' ? 'b' : 'a');

        $this->assertSame('invalid', NESPSimplePublicApplication::validateFormToken($tampered, 41002, $this->signingKey, 1010)['reason']);
        $this->assertSame('invalid', NESPSimplePublicApplication::validateFormToken($token, 41003, $this->signingKey, 1010)['reason']);
        $this->assertSame('too_fast', NESPSimplePublicApplication::validateFormToken($token, 41002, $this->signingKey, 1001)['reason']);
        $this->assertSame(
            'expired',
            NESPSimplePublicApplication::validateFormToken(
                $token,
                41002,
                $this->signingKey,
                1000 + NESPSimplePublicApplication::FORM_TOKEN_TTL_SECONDS + 1
            )['reason']
        );
        $this->assertSame('', NESPSimplePublicApplication::createFormToken(41002, 'short', 1000));
    }

    public function testTokenIsBoundToThePublishedQuestionnaireFingerprint()
    {
        $token = NESPSimplePublicApplication::createFormToken(
            41002,
            $this->signingKey,
            1000,
            'published-questionnaire-nonce',
            'published-version-a'
        );

        $this->assertTrue(
            NESPSimplePublicApplication::validateFormToken(
                $token,
                41002,
                $this->signingKey,
                1010,
                'published-version-a'
            )['ok']
        );
        $this->assertSame(
            'invalid',
            NESPSimplePublicApplication::validateFormToken(
                $token,
                41002,
                $this->signingKey,
                1010,
                'published-version-b'
            )['reason']
        );
    }

    public function testHoneypotFailsWithoutSessionOrCookieState()
    {
        $token = NESPSimplePublicApplication::createFormToken(
            41005,
            $this->signingKey,
            1000,
            'honeypot-test-nonce-value'
        );
        $result = NESPSimplePublicApplication::validatePublicPost(array(
            'jobOrderID' => 41005,
            'formToken' => $token,
            'companyWebsite' => 'spam.example'
        ), $this->signingKey, 1010);

        $this->assertFalse($result['ok']);
        $this->assertSame('unavailable', $result['reason']);
    }

    public function testContactAndOptionalResumeValidationReuseExistingRules()
    {
        $valid = NESPSimplePublicApplication::validateContact(array(
            'firstName' => 'Alex',
            'lastName' => 'Applicant',
            'email' => 'ALEX@example.com',
            'phone' => '555-0100'
        ));
        $this->assertTrue($valid['ok']);
        $this->assertSame('alex@example.com', $valid['contact']['email']);

        $invalid = NESPSimplePublicApplication::validateContact(array(
            'firstName' => '',
            'lastName' => '',
            'email' => 'not-an-email'
        ));
        $this->assertFalse($invalid['ok']);
        $this->assertArrayHasKey('firstName', $invalid['errors']);
        $this->assertArrayHasKey('lastName', $invalid['errors']);
        $this->assertArrayHasKey('email', $invalid['errors']);

        $this->assertSame(
            array('hasUpload' => false, 'error' => ''),
            NESPSimplePublicApplication::inspectOptionalResume(array())
        );
        $badResume = NESPSimplePublicApplication::inspectOptionalResume(array(
            'resume' => array(
                'error' => UPLOAD_ERR_OK,
                'tmp_name' => '/tmp/example.exe',
                'name' => 'example.exe',
                'size' => 10
            )
        ));
        $this->assertFalse($badResume['hasUpload']);
        $this->assertStringContainsString('PDF', $badResume['error']);
    }

    public function testQuestionnaireTokenCanBeRecoveredOnlyFromGeneratedInvitation()
    {
        $token = str_repeat('A', 48);
        $invitation = 'Complete: https://careers.example/modules/nesp/screeningQuestionnaire.php?t=' . $token;

        $this->assertSame($token, NESPSimplePublicApplication::extractQuestionnaireToken($invitation));
        $this->assertSame(
            '/modules/nesp/screeningQuestionnaire.php?t=' . $token,
            NESPSimplePublicApplication::questionnairePath($token)
        );
        $this->assertSame('', NESPSimplePublicApplication::extractQuestionnaireToken('No link was generated.'));
    }

    public function testDefinitionFingerprintChangesWhenPublishedQuestionsChange()
    {
        $definition = NESPSimplePublicApplication::questionnaireDefinitionForJob(41002);
        $first = $definition['fingerprint'];
        $definition['questions'][0]['label'] .= ' Changed';
        $second = NESPSimplePublicApplication::questionnaireDefinitionFingerprint($definition);

        $this->assertNotSame('', $first);
        $this->assertNotSame($first, $second);
    }

    public function testEndpointDoesNotUseFragileSessionsOrMessagingPaths()
    {
        $endpoint = file_get_contents(LEGACY_ROOT . '/modules/nespSimple/public/index.php');
        $support = file_get_contents(LEGACY_ROOT . '/modules/nespSimple/public/NESPSimplePublicApplication.php');
        $combined = $endpoint . "\n" . $support;

        $this->assertStringNotContainsString('session_start', $combined);
        $this->assertStringNotContainsString('$_SESSION', $combined);
        $this->assertStringNotContainsString('sendQuestionnaire', $combined);
        $this->assertStringNotContainsString('deliverPreparedQuestionnaireForHumanReview', $combined);
        $this->assertStringNotContainsString('routeCareerPortalApplicationToNeedsCraigResult', $combined);
        $this->assertStringContainsString('prepareQuestionnaireRecordForHumanReview', $support);
        $this->assertStringContainsString('getPublishedQuestionSetVersionForRole', $support);
        $this->assertStringContainsString('getQuestionnaireDetail', $support);
        $this->assertStringContainsString('/modules/nesp/screeningQuestionnaire.php?t=', $support);
        $this->assertStringContainsString('ensureCandidateJobOrderLink', $support);
        $this->assertStringContainsString('BoardApplicantIntake::validateResumeUpload', $support);
        $this->assertStringContainsString('GET_LOCK', $support);
        $this->assertStringContainsString('Thank you for applying', $endpoint);
    }
}
