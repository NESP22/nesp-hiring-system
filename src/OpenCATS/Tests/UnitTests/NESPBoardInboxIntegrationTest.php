<?php

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;

include_once(LEGACY_ROOT . '/lib/NESPBoardInboxIntegration.php');

class NESPBoardInboxIntegrationTest extends TestCase
{
    private $originalEnvironment = array();

    protected function setUp(): void
    {
        foreach (array(
            'NESP_BOARD_INTAKE_MISSIVE_API_TOKEN',
            'NESP_BOARD_INTAKE_MISSIVE_WEBHOOK_SECRET',
            'NESP_BOARD_INTAKE_MISSIVE_RULE_ID',
            'NESP_BOARD_INTAKE_MISSIVE_SHARED_LABEL_ID'
        ) as $key)
        {
            $this->originalEnvironment[$key] = getenv($key);
        }
    }

    protected function tearDown(): void
    {
        foreach ($this->originalEnvironment as $key => $value)
        {
            if ($value === false)
            {
                putenv($key);
            }
            else
            {
                putenv($key . '=' . $value);
            }
        }
    }

    public function testConfigurationStatusExposesOnlyPresenceAndSafetyState()
    {
        $token = 'missive-secret-token-value';
        $secret = 'missive-webhook-secret-value';
        putenv('NESP_BOARD_INTAKE_MISSIVE_API_TOKEN=' . $token);
        putenv('NESP_BOARD_INTAKE_MISSIVE_WEBHOOK_SECRET=' . $secret);
        putenv('NESP_BOARD_INTAKE_MISSIVE_RULE_ID=rule-1');
        putenv('NESP_BOARD_INTAKE_MISSIVE_SHARED_LABEL_ID=label-1');

        $status = NESPBoardInboxIntegration::getConfigurationStatus();

        $this->assertTrue($status['api_token_configured']);
        $this->assertTrue($status['webhook_secret_configured']);
        $this->assertTrue($status['approved_rule_configured']);
        $this->assertTrue($status['approved_shared_label_configured']);
        $this->assertTrue($status['configured']);
        $this->assertTrue($status['read_only']);
        $this->assertFalse($status['scraping_enabled']);
        $this->assertFalse($status['outbound_messages_enabled']);
        $this->assertStringNotContainsString($token, json_encode($status));
        $this->assertStringNotContainsString($secret, json_encode($status));
    }

    public function testConfigurationStatusFailsClosedWhenEitherSecretIsMissing()
    {
        putenv('NESP_BOARD_INTAKE_MISSIVE_API_TOKEN=token');
        putenv('NESP_BOARD_INTAKE_MISSIVE_WEBHOOK_SECRET');
        putenv('NESP_BOARD_INTAKE_MISSIVE_RULE_ID=rule-1');
        putenv('NESP_BOARD_INTAKE_MISSIVE_SHARED_LABEL_ID=label-1');

        $status = NESPBoardInboxIntegration::getConfigurationStatus();

        $this->assertTrue($status['api_token_configured']);
        $this->assertFalse($status['webhook_secret_configured']);
        $this->assertFalse($status['configured']);
    }

    public function testPureRequestEnvelopeChecksRequirePostHttpsJsonAndExactBoundedLength()
    {
        $body = '{}';

        $this->assertTrue(NESPBoardInboxIntegration::isAllowedWebhookMethod('POST'));
        $this->assertFalse(NESPBoardInboxIntegration::isAllowedWebhookMethod('GET'));
        $this->assertTrue(NESPBoardInboxIntegration::isSecureWebhookTransport('on'));
        $this->assertFalse(NESPBoardInboxIntegration::isSecureWebhookTransport(false));
        $this->assertTrue(NESPBoardInboxIntegration::isJSONContentType('application/json; charset=utf-8'));
        $this->assertFalse(NESPBoardInboxIntegration::isJSONContentType('text/json'));
        $this->assertTrue(NESPBoardInboxIntegration::isAllowedWebhookSize($body, strlen($body)));
        $this->assertFalse(NESPBoardInboxIntegration::isAllowedWebhookSize($body, strlen($body) + 1));

        $this->assertSame(
            'method_not_allowed',
            NESPBoardInboxIntegration::validateRequestEnvelope('GET', true, 'application/json', $body)['error']
        );
        $this->assertSame(
            'https_required',
            NESPBoardInboxIntegration::validateRequestEnvelope('POST', false, 'application/json', $body)['error']
        );
        $this->assertSame(
            'unsupported_content_type',
            NESPBoardInboxIntegration::validateRequestEnvelope('POST', true, 'text/plain', $body)['error']
        );
        $this->assertSame(
            'payload_too_large',
            NESPBoardInboxIntegration::validateRequestEnvelope(
                'POST',
                true,
                'application/json',
                str_repeat('a', NESPBoardInboxIntegration::WEBHOOK_MAX_BYTES + 1)
            )['error']
        );
    }

    public function testWebhookSignatureUsesMissiveSha256FormatAndRejectsTampering()
    {
        $body = '{"latest_message":{"id":"message-1","type":"email"}}';
        $secret = 'hook-secret';
        $signature = 'sha256=' . hash_hmac('sha256', $body, $secret);

        $this->assertTrue(NESPBoardInboxIntegration::validateWebhookSignature($body, $signature, $secret));
        $this->assertFalse(NESPBoardInboxIntegration::validateWebhookSignature($body . ' ', $signature, $secret));
        $this->assertFalse(NESPBoardInboxIntegration::validateWebhookSignature($body, substr($signature, 7), $secret));
        $this->assertFalse(NESPBoardInboxIntegration::validateWebhookSignature($body, 'sha256=bad', $secret));
    }

    public function testValidWebhookRequestReturnsOnlySafeMessageMetadata()
    {
        $payload = array(
            'rule' => array('id' => 'rule-1'),
            'latest_message' => array(
                'id' => '86ef8bb8-269c-4959-a4f0-213db4e67844',
                'type' => 'email',
                'email_message_id' => '<application-42@alerts.indeed.com>',
                'subject' => "New application\r\nInjected: ignored",
                'from_field' => array(
                    'name' => 'Indeed Alerts',
                    'address' => 'Alerts@Indeed.com'
                )
            )
        );
        $body = json_encode($payload);
        $secret = 'hook-secret';
        $signature = 'sha256=' . hash_hmac('sha256', $body, $secret);

        $result = NESPBoardInboxIntegration::validateWebhookRequest(
            'POST',
            true,
            'application/json; charset=utf-8',
            array('x-hook-signature' => $signature),
            $body,
            $secret,
            strlen($body),
            'rule-1'
        );

        $this->assertTrue($result['ok']);
        $this->assertSame('86ef8bb8-269c-4959-a4f0-213db4e67844', $result['message_id']);
        $this->assertSame('<application-42@alerts.indeed.com>', $result['email_message_id']);
        $this->assertSame('New application Injected: ignored', $result['subject']);
        $this->assertSame('Indeed Alerts', $result['from_name']);
        $this->assertSame('alerts@indeed.com', $result['from_address']);
        $this->assertSame(hash('sha256', $body), $result['event']['payload_hash']);
        $this->assertNotSame('', $result['event']['signature_verified_at']);
        $this->assertNotSame('', $result['event']['approved_rule_verified_at']);
        $this->assertSame(
            NESPBoardInboxIntegration::VERIFICATION_APPROVED_RULE_HMAC,
            $result['event']['verification_key']
        );
        $this->assertSame(hash('sha256', 'rule-1'), $result['event']['approved_rule_hash']);
        $this->assertMatchesRegularExpression('/^[a-f0-9]{64}$/D', $result['event']['verification_proof']);
        $this->assertTrue(NESPBoardInboxIntegration::hasVerifiedApprovedRuleMetadata(
            $result['event'],
            'rule-1',
            $secret
        ));
        $this->assertArrayNotHasKey('payload', $result);
    }

    public function testSenderAndLabelMetadataCannotSubstituteForSignedApprovedRuleProof()
    {
        $spoofable = array(
            'provider_message_id' => 'spoofable-message',
            'payload_hash' => hash('sha256', 'spoofable-payload'),
            'from_field' => array('address' => 'notifications@indeed.com'),
            'verification_key' => NESPBoardInboxIntegration::VERIFICATION_SHARED_LABEL_ONLY,
            'approved_rule_hash' => hash('sha256', 'rule-1'),
            'signature_verified_at' => '2026-07-22 12:00:00',
            'approved_rule_verified_at' => '2026-07-22 12:00:00'
        );

        $this->assertFalse(NESPBoardInboxIntegration::hasVerifiedApprovedRuleMetadata(
            $spoofable,
            'rule-1',
            'hook-secret'
        ));

        $spoofable['verification_key'] = NESPBoardInboxIntegration::VERIFICATION_APPROVED_RULE_HMAC;
        $this->assertFalse(NESPBoardInboxIntegration::hasVerifiedApprovedRuleMetadata(
            $spoofable,
            'rule-1',
            'hook-secret'
        ));
        $spoofable['verification_proof'] = NESPBoardInboxIntegration::buildApprovedRuleVerificationProof(
            $spoofable['provider_message_id'],
            $spoofable['payload_hash'],
            'rule-1',
            'hook-secret'
        );
        $this->assertTrue(NESPBoardInboxIntegration::hasVerifiedApprovedRuleMetadata(
            $spoofable,
            'rule-1',
            'hook-secret'
        ));
        $this->assertFalse(NESPBoardInboxIntegration::hasVerifiedApprovedRuleMetadata(
            $spoofable,
            'different-rule',
            'hook-secret'
        ));
    }

    public function testWebhookRequestRejectsMissingOrUnapprovedMissiveRule()
    {
        $payload = array(
            'rule' => array('id' => 'rule-other'),
            'latest_message' => array('id' => 'message-1', 'type' => 'email')
        );
        $body = json_encode($payload);
        $secret = 'hook-secret';
        $headers = array('X-Hook-Signature' => 'sha256=' . hash_hmac('sha256', $body, $secret));

        $wrong = NESPBoardInboxIntegration::validateWebhookRequest(
            'POST', true, 'application/json', $headers, $body, $secret, strlen($body), 'rule-approved'
        );
        $this->assertSame('unapproved_rule', $wrong['error']);

        $missing = NESPBoardInboxIntegration::validateWebhookRequest(
            'POST', true, 'application/json', $headers, $body, $secret, strlen($body), ''
        );
        $this->assertSame('approved_rule_missing', $missing['error']);
    }

    public function testWebhookParsingRejectsMalformedNestedAndNonEmailMessages()
    {
        $this->assertSame('malformed_json', NESPBoardInboxIntegration::parseWebhookPayload('{')['error']);
        $this->assertSame('missing_message', NESPBoardInboxIntegration::parseWebhookPayload('{}')['error']);
        $this->assertSame(
            'missing_message_id',
            NESPBoardInboxIntegration::parseWebhookPayload(json_encode(array(
                'latest_message' => array('id' => array('not-a-string'))
            )))['error']
        );
        $this->assertSame(
            'unsupported_message_type',
            NESPBoardInboxIntegration::parseWebhookPayload(json_encode(array(
                'message' => array('id' => 'message-1', 'type' => 'sms')
            )))['error']
        );
    }

    public function testFetchMessageUsesOneFixedHttpsResourceAndBearerToken()
    {
        $messageID = '86ef8bb8-269c-4959-a4f0-213db4e67844';
        $seen = array();
        $client = function ($url, $headers, $options) use (&$seen, $messageID) {
            $seen = compact('url', 'headers', 'options');
            return array(
                'status_code' => 200,
                'body' => json_encode(array(
                    'messages' => array(
                        'id' => $messageID,
                        'type' => 'email',
                        'email_message_id' => '<mail-42@joinhandshake.com>',
                        'subject' => 'New applicant for Staff Photographer',
                        'preview' => 'Application received',
                        'body' => '<p>Applicant Name: Alex Applicant</p>',
                        'from_field' => array(
                            'name' => 'Handshake',
                            'address' => 'notifications@joinhandshake.com'
                        ),
                        'attachments' => array(array('url' => 'https://untrusted.example/resume'))
                    )
                ))
            );
        };

        $result = NESPBoardInboxIntegration::fetchMessage($messageID, 'api-token', $client);

        $this->assertTrue($result['ok']);
        $this->assertSame(
            'https://public.missiveapp.com/v1/messages/' . $messageID,
            $seen['url']
        );
        $this->assertTrue(in_array('Authorization: Bearer api-token', $seen['headers'], true));
        $this->assertSame('GET', $seen['options']['method']);
        $this->assertSame(5, $seen['options']['connect_timeout']);
        $this->assertSame(15, $seen['options']['timeout']);
        $this->assertFalse($seen['options']['follow_redirects']);
        $this->assertSame($messageID, $result['message']['id']);
        $this->assertSame('notifications@joinhandshake.com', $result['message']['from_field']['address']);
        $this->assertArrayNotHasKey('attachments', $result['message']);
    }

    public function testFetchMessageRejectsMultipleOrMismatchedMessageIDsAndHidesProviderDetails()
    {
        $this->assertSame(
            'invalid_message_id',
            NESPBoardInboxIntegration::fetchMessage('first,second', 'token', function () {
                return array();
            })['error']
        );

        $result = NESPBoardInboxIntegration::fetchMessage('requested-id', 'top-secret-token', function () {
            return array(
                'status_code' => 200,
                'body' => array('messages' => array('id' => 'different-id', 'type' => 'email'))
            );
        });
        $this->assertSame('message_id_mismatch', $result['error']);
        $this->assertStringNotContainsString('top-secret-token', json_encode($result));

        $authFailure = NESPBoardInboxIntegration::fetchMessage('requested-id', 'top-secret-token', function () {
            return array('status_code' => 401, 'body' => '{"error":"token was top-secret-token"}');
        });
        $this->assertSame('missive_auth_failed', $authFailure['error']);
        $this->assertStringNotContainsString('top-secret-token', json_encode($authFailure));
    }

    public function testReconciliationUsesOnlyTheApprovedSharedLabelAndReturnsSafeMessageEvents()
    {
        $seenURLs = array();
        $client = function ($url, $headers, $options) use (&$seenURLs) {
            $seenURLs[] = $url;
            if (str_contains($url, '/conversations?'))
            {
                return array(
                    'status_code' => 200,
                    'body' => array('conversations' => array(array(
                        'id' => 'conversation-1',
                        'last_activity_at' => 2000000000
                    )))
                );
            }
            return array(
                'status_code' => 200,
                'body' => array('messages' => array(array(
                    'id' => 'message-1',
                    'type' => 'email',
                    'delivered_at' => 2000000000,
                    'email_message_id' => '<application-1@alerts.indeed.com>',
                    'subject' => 'New application for Staff Photographer',
                    'from_field' => array('address' => 'alerts@indeed.com')
                )))
            );
        };

        $result = NESPBoardInboxIntegration::discoverRecentMessages(
            1900000000,
            'api-token',
            'approved-label',
            $client
        );

        $this->assertTrue($result['ok']);
        $this->assertCount(1, $result['events']);
        $this->assertSame('message-1', $result['events'][0]['provider_message_id']);
        $this->assertSame('', $result['events'][0]['signature_verified_at']);
        $this->assertSame(
            NESPBoardInboxIntegration::VERIFICATION_SHARED_LABEL_ONLY,
            $result['events'][0]['verification_key']
        );
        $this->assertFalse(NESPBoardInboxIntegration::hasVerifiedApprovedRuleMetadata(
            $result['events'][0],
            'rule-1'
        ));
        $this->assertStringContainsString('shared_label=approved-label', $seenURLs[0]);
        $this->assertStringContainsString('/conversations/conversation-1/messages?limit=10', $seenURLs[1]);
    }

    public function testReconciliationFailsClosedWithoutApprovedSharedLabel()
    {
        $result = NESPBoardInboxIntegration::discoverRecentMessages(
            0,
            'api-token',
            '',
            function () {
                throw new RuntimeException('must not be called');
            }
        );

        $this->assertSame('approved_label_missing', $result['error']);
    }

    public function testReconciliationHonorsBoundedRateLimitRetries()
    {
        $attempts = 0;
        $result = NESPBoardInboxIntegration::discoverRecentMessages(
            0,
            'api-token',
            'approved-label',
            function () use (&$attempts) {
                $attempts++;
                if ($attempts === 1)
                {
                    return array('status_code' => 429, 'body' => '', 'retry_after' => '1');
                }
                return array('status_code' => 200, 'body' => array('conversations' => array()));
            }
        );

        $this->assertTrue($result['ok']);
        $this->assertSame(2, $attempts);
    }

    public function testReconciliationStopsAfterRateLimitRetryBudget()
    {
        $attempts = 0;
        $result = NESPBoardInboxIntegration::discoverRecentMessages(
            0,
            'api-token',
            'approved-label',
            function () use (&$attempts) {
                $attempts++;
                return array('status_code' => 429, 'body' => '', 'retry_after' => '1');
            }
        );

        $this->assertFalse($result['ok']);
        $this->assertSame('missive_rate_limited', $result['error']);
        $this->assertSame(NESPBoardInboxIntegration::MAX_RATE_LIMIT_RETRIES + 1, $attempts);
    }

    public function testReconciliationDoesNotSleepThroughLongRateLimitWindow()
    {
        $attempts = 0;
        $result = NESPBoardInboxIntegration::discoverRecentMessages(
            0,
            'api-token',
            'approved-label',
            function () use (&$attempts) {
                $attempts++;
                return array('status_code' => 429, 'body' => '', 'retry_after' => '60');
            }
        );

        $this->assertFalse($result['ok']);
        $this->assertSame('missive_rate_limited', $result['error']);
        $this->assertSame(1, $attempts);
    }

    public function testReconciliationPaginatesConversationsPastTheFormerPageLimit()
    {
        $page = 0;
        $result = NESPBoardInboxIntegration::discoverRecentMessages(
            1000,
            'api-token',
            'approved-label',
            function ($url) use (&$page) {
                if (str_contains($url, '/conversations?'))
                {
                    $page++;
                    if ($page > 11)
                    {
                        return array('status_code' => 200, 'body' => array('conversations' => array()));
                    }
                    $base = 100000 - (($page - 1) * 100);
                    $conversations = array();
                    for ($offset = 0; $offset < 50; $offset++)
                    {
                        $conversations[] = array(
                            'id' => 'conversation-' . $page . '-' . $offset,
                            'last_activity_at' => $base - $offset
                        );
                    }
                    return array('status_code' => 200, 'body' => array('conversations' => $conversations));
                }

                if (str_contains($url, '/conversation-11-49/messages?'))
                {
                    return array('status_code' => 200, 'body' => array('messages' => array(array(
                        'id' => 'application-after-former-conversation-limit',
                        'type' => 'email',
                        'delivered_at' => 98951,
                        'email_message_id' => '<after-limit@example.invalid>',
                        'subject' => 'New application for Staff Photographer',
                        'from_field' => array('address' => 'alerts@indeed.com')
                    ))));
                }
                return array('status_code' => 200, 'body' => array('messages' => array()));
            }
        );

        $this->assertTrue($result['ok']);
        $this->assertSame(12, $page);
        $this->assertSame(
            'application-after-former-conversation-limit',
            $result['events'][0]['provider_message_id']
        );
    }

    public function testReconciliationPaginatesMessagesPastTheFormerPageLimit()
    {
        $messagePage = 0;
        $result = NESPBoardInboxIntegration::discoverRecentMessages(
            1000,
            'api-token',
            'approved-label',
            function ($url) use (&$messagePage) {
                if (str_contains($url, '/conversations?'))
                {
                    return array(
                        'status_code' => 200,
                        'body' => array('conversations' => array(array(
                            'id' => 'conversation-with-many-messages',
                            'last_activity_at' => 5000
                        )))
                    );
                }

                $messagePage++;
                $base = 50000 - (($messagePage - 1) * 20);
                $messages = array();
                $pageSize = $messagePage <= 21 ? 10 : 1;
                for ($offset = 0; $offset < $pageSize; $offset++)
                {
                    $messages[] = array(
                        'id' => 'message-' . $messagePage . '-' . $offset,
                        'type' => $messagePage === 22 ? 'email' : 'sms',
                        'delivered_at' => $base - $offset,
                        'email_message_id' => '<message-' . $messagePage . '-' . $offset . '@example.invalid>',
                        'subject' => 'New application for Staff Photographer',
                        'from_field' => array('address' => 'alerts@indeed.com')
                    );
                }
                return array('status_code' => 200, 'body' => array('messages' => $messages));
            }
        );

        $this->assertTrue($result['ok']);
        $this->assertSame(22, $messagePage);
        $this->assertCount(1, $result['events']);
        $this->assertSame('message-22-0', $result['events'][0]['provider_message_id']);
    }

    public function testReconciliationFailsVisiblyWhenProviderCursorStalls()
    {
        $pages = 0;
        $result = NESPBoardInboxIntegration::discoverRecentMessages(
            1000,
            'api-token',
            'approved-label',
            function () use (&$pages) {
                $pages++;
                $conversations = array();
                for ($offset = 0; $offset < 50; $offset++)
                {
                    $conversations[] = array(
                        'id' => 'stalled-' . $offset,
                        'last_activity_at' => 5000 - $offset
                    );
                }
                return array('status_code' => 200, 'body' => array('conversations' => $conversations));
            }
        );

        $this->assertFalse($result['ok']);
        $this->assertSame('reconciliation_cursor_stalled', $result['error']);
        $this->assertSame(2, $pages);
    }

    #[DataProvider('officialPlatformProvider')]
    public function testOfficialBoardSendersClassifyOnlyWithApplicationSignals($platform, $address, $subject)
    {
        $message = $this->applicationMessage($address, $subject, 'Staff Photographer');

        $result = NESPBoardInboxIntegration::classifyMessage($message);

        $this->assertSame('ready_for_review', $result['status']);
        $this->assertSame($platform, $result['platform_key']);
        $this->assertSame(41002, $result['job_order_id']);
        $this->assertFalse($result['review_required']);
        $this->assertFalse($result['human_review_required']);
    }

    public static function officialPlatformProvider()
    {
        return array(
            'Indeed' => array('indeed', 'notifications@alerts.indeed.com', 'New application for Staff Photographer'),
            'LinkedIn' => array('linkedin', 'jobs-noreply@linkedin.com', 'Alex Applicant has applied for Staff Photographer'),
            'Craigslist' => array('craigslist', 'candidate@reply.craigslist.org', 'Response to your job posting: Staff Photographer'),
            'MassHire' => array('masshire', 'jobquest@detma.mass.gov', 'New MassHire application for Staff Photographer'),
            'Handshake' => array('handshake', 'notifications@joinhandshake.com', 'New applicant for Staff Photographer')
        );
    }

    #[DataProvider('approvedJobOrderProvider')]
    public function testOnlyApprovedJobOrderTitlesAreClassified($jobOrderID, $title)
    {
        $result = NESPBoardInboxIntegration::classifyMessage(
            $this->applicationMessage('notifications@indeed.com', 'New application for ' . $title, $title)
        );

        $this->assertSame($jobOrderID, $result['job_order_id']);
        $this->assertSame('ready_for_review', $result['status']);
    }

    public static function approvedJobOrderProvider()
    {
        return array(
            array(41001, 'Part-Time Customer Service Representative'),
            array(41002, 'Staff Photographer'),
            array(41003, 'Freelance/Contract Youth Sports Photographer'),
            array(41005, 'Weekend Table Greeter / Field Assistant')
        );
    }

    public function testLabeledJobOrderCanConfirmTheMatchingApprovedRole()
    {
        $message = $this->applicationMessage(
            'notifications@indeed.com',
            'New application received',
            'NESP Job Order: 41003'
        );

        $result = NESPBoardInboxIntegration::classifyMessage($message);

        $this->assertSame(41003, $result['job_order_id']);
        $this->assertSame('ready_for_review', $result['status']);
    }

    public function testExplicitUnapprovedJobOrderCannotFallBackToAnAllowedTitle()
    {
        $message = $this->applicationMessage(
            'notifications@indeed.com',
            'New application for Staff Photographer',
            "NESP Job Order: 49999\nStaff Photographer"
        );

        $result = NESPBoardInboxIntegration::classifyMessage($message);

        $this->assertSame('review_required', $result['status']);
        $this->assertNull($result['job_order_id']);
        $this->assertContains('job_order_unrecognized', $result['review_reasons']);
    }

    public function testUntrustedSenderCannotSelfDeclareAnAllowedPlatform()
    {
        $message = $this->applicationMessage(
            'alerts@not-indeed.example',
            'New Indeed application for Staff Photographer',
            'Staff Photographer'
        );

        $result = NESPBoardInboxIntegration::classifyMessage($message);

        $this->assertSame('review_required', $result['status']);
        $this->assertNull($result['platform_key']);
        $this->assertContains('platform_unverified', $result['review_reasons']);
    }

    public function testConflictingPlatformOrJobSignalsReturnReviewRequiredWithoutGuessing()
    {
        $platformConflict = $this->applicationMessage(
            'notifications@indeed.com',
            'New Indeed and LinkedIn application for Staff Photographer',
            'Staff Photographer'
        );
        $jobConflict = $this->applicationMessage(
            'notifications@indeed.com',
            'New application received',
            "NESP Job Order: 41002\nPosition: Freelance/Contract Youth Sports Photographer"
        );

        $platformResult = NESPBoardInboxIntegration::classifyMessage($platformConflict);
        $jobResult = NESPBoardInboxIntegration::classifyMessage($jobConflict);

        $this->assertNull($platformResult['platform_key']);
        $this->assertContains('platform_ambiguous', $platformResult['review_reasons']);
        $this->assertNull($jobResult['job_order_id']);
        $this->assertContains('job_order_ambiguous', $jobResult['review_reasons']);
    }

    public function testExternalIDPrefersOneLabeledApplicationIDAndHashedEmailMessageFallbackIsStable()
    {
        $labeled = array(
            'body' => "Application ID: APP-100\nApplicant Name: Alex Applicant",
            'email_message_id' => '<fallback@example.test>'
        );
        $fallback = array(
            'body' => 'Applicant Name: Alex Applicant',
            'email_message_id' => '<fallback@example.test>'
        );

        $this->assertSame('APP-100', NESPBoardInboxIntegration::deriveExternalID($labeled));
        $this->assertSame(
            'email-message:' . hash('sha256', '<fallback@example.test>'),
            NESPBoardInboxIntegration::deriveExternalID($fallback)
        );
        $this->assertSame(
            NESPBoardInboxIntegration::deriveExternalID($fallback),
            NESPBoardInboxIntegration::deriveExternalID($fallback)
        );
    }

    public function testConflictingApplicationIDsDoNotFallBackToEmailMessageID()
    {
        $identity = NESPBoardInboxIntegration::deriveExternalIdentity(array(
            'body' => "Application ID: APP-100\nApplicant ID: APP-200",
            'email_message_id' => '<fallback@example.test>'
        ));

        $this->assertNull($identity['external_id']);
        $this->assertContains('application_id_ambiguous', $identity['review_reasons']);
    }

    public function testApplicantFieldsAreExtractedFromHTMLWithoutUsingBoardSenderAsApplicant()
    {
        $applicant = NESPBoardInboxIntegration::extractApplicant(array(
            'body' => '<p>First Name: Anne-Marie</p><p>Last Name: O\'Neil</p>'
                . '<p>Applicant Email: Anne.ONeil@Example.test</p>',
            'from_field' => array('name' => 'Indeed', 'address' => 'notifications@indeed.com')
        ));

        $this->assertSame('Anne-Marie', $applicant['first_name']);
        $this->assertSame('O\'Neil', $applicant['last_name']);
        $this->assertSame('anne.oneil@example.test', $applicant['email']);
        $this->assertSame(array(), $applicant['review_reasons']);
    }

    public function testAmbiguousNameReturnsReviewRequiredInsteadOfGuessing()
    {
        $message = $this->applicationMessage(
            'notifications@indeed.com',
            'New application for Staff Photographer',
            'Staff Photographer',
            'Mary Jane Applicant'
        );

        $result = NESPBoardInboxIntegration::classifyMessage($message);

        $this->assertSame('review_required', $result['status']);
        $this->assertSame('', $result['first_name']);
        $this->assertSame('', $result['last_name']);
        $this->assertContains('applicant_name_ambiguous', $result['review_reasons']);
    }

    public function testImplementationContainsNoScrapingLoggingOrOutboundRequestPath()
    {
        $source = file_get_contents(LEGACY_ROOT . '/lib/NESPBoardInboxIntegration.php');

        $this->assertStringContainsString('CURLOPT_HTTPGET', $source);
        $this->assertStringContainsString('CURLOPT_CONNECTTIMEOUT', $source);
        $this->assertStringContainsString('CURLOPT_TIMEOUT', $source);
        $this->assertStringNotContainsString('CURLOPT_POST', $source);
        $this->assertStringNotContainsString('error_log(', $source);
        $this->assertStringNotContainsString('curl_error(', $source);
        $this->assertStringNotContainsString('mail(', $source);
    }

    private function applicationMessage($fromAddress, $subject, $roleText, $applicantName = 'Alex Applicant')
    {
        return array(
            'email_message_id' => '<message-42@example.test>',
            'subject' => $subject,
            'body' => $roleText . "\nApplication ID: APP-42\nApplicant Name: " . $applicantName
                . "\nApplicant Email: alex@example.test",
            'from_field' => array(
                'name' => 'Board Notifications',
                'address' => $fromAddress
            )
        );
    }
}
