<?php
/*
 * Read-only Missive helpers for the NESP board application inbox.
 *
 * This class validates and classifies inbound notifications only. It never
 * scrapes board pages, creates candidates, or sends messages.
 */

class NESPBoardInboxIntegration
{
    const API_MESSAGE_ENDPOINT = 'https://public.missiveapp.com/v1/messages/';
    const API_CONVERSATION_ENDPOINT = 'https://public.missiveapp.com/v1/conversations';
    const VERIFICATION_APPROVED_RULE_HMAC = 'missive_approved_rule_hmac_v1';
    const VERIFICATION_SHARED_LABEL_ONLY = 'missive_shared_label_only_v1';
    const MAX_RATE_LIMIT_RETRIES = 3;
    const MAX_RATE_LIMIT_DELAY_SECONDS = 5;
    const DEFAULT_RATE_LIMIT_BACKOFF_SECONDS = 60;
    const MAX_RATE_LIMIT_BACKOFF_SECONDS = 86400;
    const WEBHOOK_MAX_BYTES = 262144;
    const API_RESPONSE_MAX_BYTES = 2097152;
    const CONNECT_TIMEOUT_SECONDS = 5;
    const REQUEST_TIMEOUT_SECONDS = 15;

    public static function getConfigurationStatus()
    {
        $apiTokenConfigured = self::getEnvValue('NESP_BOARD_INTAKE_MISSIVE_API_TOKEN') !== '';
        $webhookSecretConfigured = self::getEnvValue('NESP_BOARD_INTAKE_MISSIVE_WEBHOOK_SECRET') !== '';
        $ruleIDConfigured = self::getEnvValue('NESP_BOARD_INTAKE_MISSIVE_RULE_ID') !== '';
        $sharedLabelConfigured = self::getEnvValue('NESP_BOARD_INTAKE_MISSIVE_SHARED_LABEL_ID') !== '';
        $configured = $apiTokenConfigured && $webhookSecretConfigured && $ruleIDConfigured
            && $sharedLabelConfigured;

        return array(
            'api_token_configured' => $apiTokenConfigured,
            'webhook_secret_configured' => $webhookSecretConfigured,
            'approved_rule_configured' => $ruleIDConfigured,
            'approved_shared_label_configured' => $sharedLabelConfigured,
            'configured' => $configured,
            'ready' => $configured,
            'status_key' => $configured ? 'ready' : 'missing_configuration',
            'read_only' => true,
            'scraping_enabled' => false,
            'outbound_messages_enabled' => false
        );
    }

    public static function allowedPlatforms()
    {
        return array('indeed', 'linkedin', 'craigslist', 'masshire', 'handshake');
    }

    public static function allowedJobOrders()
    {
        return array(
            41001 => 'Part-Time Customer Service Representative',
            41002 => 'Staff Photographer',
            41003 => 'Freelance/Contract Youth Sports Photographer',
            41005 => 'Weekend Table Greeter / Field Assistant'
        );
    }

    public static function isConfigured()
    {
        $status = self::getConfigurationStatus();
        return !empty($status['configured']);
    }

    public static function isAllowedWebhookMethod($method)
    {
        return strtoupper(trim((string) $method)) === 'POST';
    }

    public static function isSecureWebhookTransport($isHTTPS)
    {
        if ($isHTTPS === true || $isHTTPS === 1)
        {
            return true;
        }

        $value = strtolower(trim((string) $isHTTPS));
        return in_array($value, array('1', 'on', 'https'), true);
    }

    public static function isSecureWebhookRequest($isHTTPS)
    {
        return self::isSecureWebhookTransport($isHTTPS);
    }

    public static function isJSONContentType($contentType)
    {
        $parts = explode(';', strtolower(trim((string) $contentType)), 2);
        return trim($parts[0]) === 'application/json';
    }

    public static function isAllowedWebhookSize($rawBody, $contentLength = null)
    {
        if (!is_string($rawBody) || strlen($rawBody) > self::WEBHOOK_MAX_BYTES)
        {
            return false;
        }

        if ($contentLength === null || $contentLength === '')
        {
            return true;
        }
        if (is_int($contentLength))
        {
            $length = $contentLength;
        }
        elseif (is_string($contentLength) && preg_match('/^\d+$/D', $contentLength))
        {
            $length = (int) $contentLength;
        }
        else
        {
            return false;
        }

        return $length >= 0
            && $length <= self::WEBHOOK_MAX_BYTES
            && $length === strlen($rawBody);
    }

    public static function isAllowedWebhookPayloadSize($rawBody, $contentLength = null)
    {
        return self::isAllowedWebhookSize($rawBody, $contentLength);
    }

    public static function validateRequestEnvelope($method, $isHTTPS, $contentType, $rawBody, $contentLength = null)
    {
        if (!self::isAllowedWebhookMethod($method))
        {
            return self::validationError(405, 'method_not_allowed');
        }
        if (!self::isSecureWebhookTransport($isHTTPS))
        {
            return self::validationError(400, 'https_required');
        }
        if (!self::isJSONContentType($contentType))
        {
            return self::validationError(415, 'unsupported_content_type');
        }
        if (!is_string($rawBody) || strlen($rawBody) > self::WEBHOOK_MAX_BYTES
            || self::numericContentLengthExceedsLimit($contentLength))
        {
            return self::validationError(413, 'payload_too_large');
        }
        if (!self::isAllowedWebhookSize($rawBody, $contentLength))
        {
            return self::validationError(400, 'invalid_content_length');
        }

        return array('ok' => true, 'status' => 200, 'error' => '');
    }

    public static function validateWebhookSignature($rawBody, $providedSignature, $webhookSecret)
    {
        if (!is_string($rawBody) || !is_string($providedSignature) || !is_string($webhookSecret)
            || $webhookSecret === '')
        {
            return false;
        }

        $providedSignature = trim($providedSignature);
        if (!preg_match('/^sha256=([a-f0-9]{64})$/iD', $providedSignature, $matches))
        {
            return false;
        }

        $expected = 'sha256=' . hash_hmac('sha256', $rawBody, $webhookSecret);
        $provided = 'sha256=' . strtolower($matches[1]);
        return hash_equals($expected, $provided);
    }

    public static function isValidWebhookSignature($rawBody, $providedSignature, $webhookSecret)
    {
        return self::validateWebhookSignature($rawBody, $providedSignature, $webhookSecret);
    }

    public static function validateWebhookRequest(
        $method,
        $isHTTPS,
        $contentType,
        $headers,
        $rawBody,
        $webhookSecret = null,
        $contentLength = null,
        $expectedRuleID = null
    ) {
        $envelope = self::validateRequestEnvelope($method, $isHTTPS, $contentType, $rawBody, $contentLength);
        if (!$envelope['ok'])
        {
            return $envelope;
        }

        if ($webhookSecret === null)
        {
            $webhookSecret = self::getEnvValue('NESP_BOARD_INTAKE_MISSIVE_WEBHOOK_SECRET');
        }
        if (!is_string($webhookSecret) || $webhookSecret === '')
        {
            return self::validationError(503, 'webhook_secret_missing');
        }

        $signature = self::headerValue($headers, 'X-Hook-Signature');
        if (!self::validateWebhookSignature($rawBody, $signature, $webhookSecret))
        {
            return self::validationError(401, 'invalid_signature');
        }

        $parsed = self::parseWebhookPayload($rawBody);
        if (!$parsed['ok'])
        {
            return $parsed;
        }
        if ($expectedRuleID === null)
        {
            $expectedRuleID = self::getEnvValue('NESP_BOARD_INTAKE_MISSIVE_RULE_ID');
        }
        $expectedRuleID = self::safeMessageID($expectedRuleID);
        if ($expectedRuleID === '')
        {
            return self::validationError(503, 'approved_rule_missing');
        }
        if ($parsed['rule_id'] === '' || !hash_equals($expectedRuleID, $parsed['rule_id']))
        {
            return self::validationError(403, 'unapproved_rule');
        }
        $verifiedAt = gmdate('Y-m-d H:i:s');
        $parsed['payload_sha256'] = hash('sha256', $rawBody);
        $parsed['message'] = array(
            'message_id' => $parsed['message_id'],
            'email_message_id' => $parsed['email_message_id'],
            'subject' => $parsed['subject'],
            'from' => array(
                'name' => $parsed['from_name'],
                'address' => $parsed['from_address']
            )
        );
        $parsed['event'] = array(
            'provider_message_id' => $parsed['message_id'],
            'email_message_id' => $parsed['email_message_id'],
            'payload_hash' => $parsed['payload_sha256'],
            'subject_hash' => hash('sha256', $parsed['subject']),
            'sender_hash' => hash('sha256', strtolower($parsed['from_address'])),
            'verification_key' => self::VERIFICATION_APPROVED_RULE_HMAC,
            'approved_rule_hash' => hash('sha256', $expectedRuleID),
            'verification_proof' => self::buildApprovedRuleVerificationProof(
                $parsed['message_id'],
                $parsed['payload_sha256'],
                $expectedRuleID,
                $webhookSecret
            ),
            'signature_verified_at' => $verifiedAt,
            'approved_rule_verified_at' => $verifiedAt,
            'received_at' => $verifiedAt
        );
        return $parsed;
    }

    public static function buildApprovedRuleVerificationProof(
        $providerMessageID,
        $payloadHash,
        $ruleID,
        $webhookSecret
    ) {
        $providerMessageID = self::safeMessageID($providerMessageID);
        $payloadHash = strtolower(trim((string) $payloadHash));
        $ruleID = self::safeMessageID($ruleID);
        $webhookSecret = is_string($webhookSecret) ? $webhookSecret : '';
        if ($providerMessageID === ''
            || !preg_match('/^[a-f0-9]{64}$/D', $payloadHash)
            || $ruleID === ''
            || $webhookSecret === '')
        {
            return '';
        }

        return hash_hmac(
            'sha256',
            'missive-approved-rule-v1|' . $providerMessageID . '|' . $payloadHash . '|' . $ruleID,
            $webhookSecret
        );
    }

    public static function hasVerifiedApprovedRuleMetadata(
        $event,
        $expectedRuleID = null,
        $webhookSecret = null
    ) {
        if (!is_array($event)
            || !isset($event['verification_key'])
            || !hash_equals(self::VERIFICATION_APPROVED_RULE_HMAC, (string) $event['verification_key'])
            || empty($event['signature_verified_at'])
            || empty($event['approved_rule_verified_at']))
        {
            return false;
        }

        if ($expectedRuleID === null)
        {
            $expectedRuleID = self::getEnvValue('NESP_BOARD_INTAKE_MISSIVE_RULE_ID');
        }
        if ($webhookSecret === null)
        {
            $webhookSecret = self::getEnvValue('NESP_BOARD_INTAKE_MISSIVE_WEBHOOK_SECRET');
        }
        $expectedRuleID = self::safeMessageID($expectedRuleID);
        $storedHash = isset($event['approved_rule_hash']) ? strtolower(trim((string) $event['approved_rule_hash'])) : '';
        $storedProof = isset($event['verification_proof'])
            ? strtolower(trim((string) $event['verification_proof']))
            : '';
        if ($expectedRuleID === ''
            || !preg_match('/^[a-f0-9]{64}$/D', $storedHash)
            || !preg_match('/^[a-f0-9]{64}$/D', $storedProof))
        {
            return false;
        }

        $expectedProof = self::buildApprovedRuleVerificationProof(
            isset($event['provider_message_id']) ? $event['provider_message_id'] : '',
            isset($event['payload_hash']) ? $event['payload_hash'] : '',
            $expectedRuleID,
            $webhookSecret
        );
        return $expectedProof !== ''
            && hash_equals(hash('sha256', $expectedRuleID), $storedHash)
            && hash_equals($expectedProof, $storedProof);
    }

    public static function parseWebhookPayload($rawBody)
    {
        if (!is_string($rawBody) || $rawBody === '' || strlen($rawBody) > self::WEBHOOK_MAX_BYTES)
        {
            return self::validationError(400, 'malformed_json');
        }

        $payload = json_decode($rawBody, true, 32, JSON_BIGINT_AS_STRING);
        if (!is_array($payload) || json_last_error() !== JSON_ERROR_NONE)
        {
            return self::validationError(400, 'malformed_json');
        }

        if (isset($payload['latest_message']) && is_array($payload['latest_message']))
        {
            $message = $payload['latest_message'];
        }
        elseif (isset($payload['message']) && is_array($payload['message']))
        {
            $message = $payload['message'];
        }
        else
        {
            return self::validationError(400, 'missing_message');
        }

        $messageID = self::safeMessageID(isset($message['id']) ? $message['id'] : null);
        if ($messageID === '')
        {
            return self::validationError(400, 'missing_message_id');
        }

        $type = self::safeSingleLine(isset($message['type']) ? $message['type'] : '', 32);
        if ($type !== '' && strtolower($type) !== 'email')
        {
            return self::validationError(400, 'unsupported_message_type');
        }

        $fromField = isset($message['from_field']) && is_array($message['from_field'])
            ? $message['from_field']
            : array();
        $rule = isset($payload['rule']) && is_array($payload['rule']) ? $payload['rule'] : array();
        $ruleID = self::safeMessageID(isset($rule['id']) ? $rule['id'] : null);
        $metadata = array(
            'message_id' => $messageID,
            'email_message_id' => self::safeEmailMessageID(
                isset($message['email_message_id']) ? $message['email_message_id'] : null
            ),
            'subject' => self::safeSingleLine(isset($message['subject']) ? $message['subject'] : '', 998),
            'from_name' => self::safeSingleLine(isset($fromField['name']) ? $fromField['name'] : '', 200),
            'from_address' => self::safeEmailAddress(isset($fromField['address']) ? $fromField['address'] : '')
        );

        return array(
            'ok' => true,
            'status' => 200,
            'error' => '',
            'message_id' => $metadata['message_id'],
            'email_message_id' => $metadata['email_message_id'],
            'subject' => $metadata['subject'],
            'from_name' => $metadata['from_name'],
            'from_address' => $metadata['from_address'],
            'rule_id' => $ruleID,
            'metadata' => $metadata
        );
    }

    public static function buildMessageURL($messageID)
    {
        $messageID = self::safeMessageID($messageID);
        return $messageID === '' ? '' : self::API_MESSAGE_ENDPOINT . rawurlencode($messageID);
    }

    public static function fetchMessage($messageID, $apiToken = null, $httpClient = null)
    {
        $url = self::buildMessageURL($messageID);
        if ($url === '')
        {
            return self::fetchError(400, 'invalid_message_id');
        }

        if ($apiToken === null)
        {
            $apiToken = self::getEnvValue('NESP_BOARD_INTAKE_MISSIVE_API_TOKEN');
        }
        $apiToken = is_string($apiToken) ? trim($apiToken) : '';
        if ($apiToken === '')
        {
            return self::fetchError(503, 'api_token_missing');
        }

        try
        {
            $response = $httpClient === null
                ? self::performMessageRequest($url, $apiToken)
                : call_user_func(
                    $httpClient,
                    $url,
                    array('Authorization: Bearer ' . $apiToken, 'Accept: application/json'),
                    array(
                        'method' => 'GET',
                        'connect_timeout' => self::CONNECT_TIMEOUT_SECONDS,
                        'timeout' => self::REQUEST_TIMEOUT_SECONDS,
                        'follow_redirects' => false
                    )
                );
        }
        catch (Throwable $exception)
        {
            return self::fetchError(502, 'missive_request_failed');
        }

        if (!is_array($response))
        {
            return self::fetchError(502, 'missive_request_failed');
        }
        if (!empty($response['response_too_large']))
        {
            return self::fetchError(502, 'missive_response_too_large');
        }

        $statusCode = isset($response['status_code']) ? (int) $response['status_code'] : 0;
        if ($statusCode === 401 || $statusCode === 403)
        {
            return self::fetchError(502, 'missive_auth_failed');
        }
        if ($statusCode !== 200 || !array_key_exists('body', $response))
        {
            return self::fetchError(502, 'missive_request_failed');
        }

        $body = $response['body'];
        if (is_string($body))
        {
            if (strlen($body) > self::API_RESPONSE_MAX_BYTES)
            {
                return self::fetchError(502, 'missive_response_too_large');
            }
            $decoded = json_decode($body, true, 32, JSON_BIGINT_AS_STRING);
            if (!is_array($decoded) || json_last_error() !== JSON_ERROR_NONE)
            {
                return self::fetchError(502, 'invalid_missive_response');
            }
        }
        elseif (is_array($body))
        {
            $decoded = $body;
        }
        else
        {
            return self::fetchError(502, 'invalid_missive_response');
        }

        $message = self::oneMessageFromResponse($decoded);
        if ($message === null)
        {
            return self::fetchError(502, 'invalid_missive_response');
        }

        $message = self::normalizeFetchedMessage($message);
        if ($message === null || !hash_equals($messageID, $message['id']))
        {
            return self::fetchError(502, 'message_id_mismatch');
        }

        return array(
            'ok' => true,
            'status' => 200,
            'status_code' => 200,
            'error' => '',
            'message' => $message
        );
    }

    public static function fetchMissiveMessage($messageID, $apiToken = null, $httpClient = null)
    {
        return self::fetchMessage($messageID, $apiToken, $httpClient);
    }

    public static function discoverRecentMessages(
        $sinceEpoch = null,
        $apiToken = null,
        $sharedLabelID = null,
        $httpClient = null
    ) {
        $sinceEpoch = is_numeric($sinceEpoch) ? max(0, (int) $sinceEpoch) : 0;
        $events = array();
        $conversationIDs = array();
        $conversationUntil = null;
        while (true)
        {
            $conversationPage = self::discoverConversationPage(
                $sinceEpoch,
                $conversationUntil,
                $apiToken,
                $sharedLabelID,
                $httpClient
            );
            if (empty($conversationPage['ok']))
            {
                return $conversationPage;
            }

            foreach ($conversationPage['conversation_ids'] as $conversationID)
            {
                $conversationIDs[$conversationID] = true;
            }

            if (!empty($conversationPage['complete']))
            {
                break;
            }
            $nextConversationUntil = isset($conversationPage['next_until'])
                ? (int) $conversationPage['next_until']
                : 0;
            if ($nextConversationUntil <= 0
                || ($conversationUntil !== null && $nextConversationUntil >= $conversationUntil))
            {
                return self::fetchError(502, 'reconciliation_cursor_stalled');
            }
            $conversationUntil = $nextConversationUntil;
        }

        foreach (array_keys($conversationIDs) as $conversationID)
        {
            $messageUntil = null;
            while (true)
            {
                $messagePage = self::discoverConversationMessagePage(
                    $conversationID,
                    $sinceEpoch,
                    $messageUntil,
                    $apiToken,
                    $sharedLabelID,
                    $httpClient
                );
                if (empty($messagePage['ok']))
                {
                    return $messagePage;
                }
                foreach ($messagePage['events'] as $event)
                {
                    $events[$event['provider_message_id']] = $event;
                }
                if (!empty($messagePage['complete']))
                {
                    break;
                }
                $nextMessageUntil = isset($messagePage['next_until'])
                    ? (int) $messagePage['next_until']
                    : 0;
                if ($nextMessageUntil <= 0
                    || ($messageUntil !== null && $nextMessageUntil >= $messageUntil))
                {
                    return self::fetchError(502, 'reconciliation_cursor_stalled');
                }
                $messageUntil = $nextMessageUntil;
            }
        }

        return array('ok' => true, 'status' => 200, 'error' => '', 'events' => array_values($events));
    }

    public static function discoverConversationPage(
        $sinceEpoch = null,
        $untilEpoch = null,
        $apiToken = null,
        $sharedLabelID = null,
        $httpClient = null
    ) {
        $configuration = self::reconciliationConfiguration($apiToken, $sharedLabelID);
        if (empty($configuration['ok']))
        {
            return $configuration;
        }

        $sinceEpoch = is_numeric($sinceEpoch) ? max(0, (int) $sinceEpoch) : 0;
        $untilEpoch = is_numeric($untilEpoch) && (int) $untilEpoch > 0
            ? (int) $untilEpoch
            : null;
        $query = array('shared_label' => $configuration['shared_label_id'], 'limit' => 50);
        if ($untilEpoch !== null)
        {
            $query['until'] = $untilEpoch;
        }
        $url = self::API_CONVERSATION_ENDPOINT . '?' . http_build_query($query, '', '&', PHP_QUERY_RFC3986);
        $response = self::performReadRequest($url, $configuration['api_token'], $httpClient);
        if (empty($response['ok']))
        {
            return $response;
        }
        $conversations = isset($response['data']['conversations']) && is_array($response['data']['conversations'])
            ? $response['data']['conversations']
            : null;
        if ($conversations === null || !array_is_list($conversations))
        {
            return self::fetchError(502, 'invalid_missive_response');
        }
        if (!$conversations)
        {
            return array(
                'ok' => true,
                'status' => 200,
                'error' => '',
                'conversation_ids' => array(),
                'next_until' => null,
                'complete' => true
            );
        }

        $conversationIDs = array();
        $oldest = null;
        $boundaryReached = false;
        foreach ($conversations as $conversation)
        {
            if (!is_array($conversation))
            {
                return self::fetchError(502, 'invalid_missive_response');
            }
            $conversationID = self::safeMessageID(isset($conversation['id']) ? $conversation['id'] : null);
            $activity = isset($conversation['last_activity_at']) && is_numeric($conversation['last_activity_at'])
                ? (int) $conversation['last_activity_at']
                : 0;
            if ($conversationID === '' || $activity <= 0)
            {
                return self::fetchError(502, 'reconciliation_cursor_missing');
            }
            $oldest = $oldest === null ? $activity : min($oldest, $activity);
            if ($activity < $sinceEpoch)
            {
                $boundaryReached = true;
                continue;
            }
            $conversationIDs[$conversationID] = true;
        }

        $complete = $boundaryReached || count($conversations) < 50;
        if (!$complete && $untilEpoch !== null && $oldest >= $untilEpoch)
        {
            return self::fetchError(502, 'reconciliation_cursor_stalled');
        }
        return array(
            'ok' => true,
            'status' => 200,
            'error' => '',
            'conversation_ids' => array_keys($conversationIDs),
            'next_until' => $complete ? null : $oldest,
            'complete' => $complete
        );
    }

    public static function discoverConversationMessagePage(
        $conversationID,
        $sinceEpoch = null,
        $untilEpoch = null,
        $apiToken = null,
        $sharedLabelID = null,
        $httpClient = null
    ) {
        $configuration = self::reconciliationConfiguration($apiToken, $sharedLabelID);
        if (empty($configuration['ok']))
        {
            return $configuration;
        }
        $conversationID = self::safeMessageID($conversationID);
        if ($conversationID === '')
        {
            return self::fetchError(502, 'reconciliation_cursor_missing');
        }

        $sinceEpoch = is_numeric($sinceEpoch) ? max(0, (int) $sinceEpoch) : 0;
        $untilEpoch = is_numeric($untilEpoch) && (int) $untilEpoch > 0
            ? (int) $untilEpoch
            : null;
        $query = array('limit' => 10);
        if ($untilEpoch !== null)
        {
            $query['until'] = $untilEpoch;
        }
        $url = self::API_CONVERSATION_ENDPOINT . '/' . rawurlencode($conversationID)
            . '/messages?' . http_build_query($query, '', '&', PHP_QUERY_RFC3986);
        $response = self::performReadRequest($url, $configuration['api_token'], $httpClient);
        if (empty($response['ok']))
        {
            return $response;
        }
        $messages = isset($response['data']['messages']) && is_array($response['data']['messages'])
            ? $response['data']['messages']
            : null;
        if ($messages === null || !array_is_list($messages))
        {
            return self::fetchError(502, 'invalid_missive_response');
        }
        if (!$messages)
        {
            return array(
                'ok' => true,
                'status' => 200,
                'error' => '',
                'events' => array(),
                'next_until' => null,
                'complete' => true
            );
        }

        $events = array();
        $oldestDeliveredAt = null;
        $boundaryReached = false;
        foreach ($messages as $rawMessage)
        {
            $deliveredAt = is_array($rawMessage) && isset($rawMessage['delivered_at'])
                && is_numeric($rawMessage['delivered_at'])
                ? (int) $rawMessage['delivered_at']
                : 0;
            if (!is_array($rawMessage) || $deliveredAt <= 0)
            {
                return self::fetchError(502, 'reconciliation_cursor_missing');
            }
            $oldestDeliveredAt = $oldestDeliveredAt === null
                ? $deliveredAt
                : min($oldestDeliveredAt, $deliveredAt);
            if ($deliveredAt < $sinceEpoch)
            {
                $boundaryReached = true;
                continue;
            }
            $type = strtolower(self::safeSingleLine(
                isset($rawMessage['type']) ? $rawMessage['type'] : '',
                32
            ));
            if ($type !== '' && $type !== 'email')
            {
                continue;
            }
            $message = self::normalizeFetchedMessage($rawMessage);
            if ($message === null)
            {
                return self::fetchError(502, 'invalid_missive_response');
            }
            $events[$message['id']] = array(
                'provider_message_id' => $message['id'],
                'email_message_id' => $message['email_message_id'],
                'payload_hash' => hash(
                    'sha256',
                    'missive-reconcile|' . $configuration['shared_label_id'] . '|' . $message['id']
                ),
                'subject_hash' => hash('sha256', $message['subject']),
                'sender_hash' => hash('sha256', strtolower($message['from_field']['address'])),
                'verification_key' => self::VERIFICATION_SHARED_LABEL_ONLY,
                'approved_rule_hash' => '',
                'verification_proof' => '',
                'signature_verified_at' => '',
                'approved_rule_verified_at' => '',
                'received_at' => gmdate('Y-m-d H:i:s', $deliveredAt)
            );
        }

        $complete = $boundaryReached || count($messages) < 10;
        if (!$complete && $untilEpoch !== null && $oldestDeliveredAt >= $untilEpoch)
        {
            return self::fetchError(502, 'reconciliation_cursor_stalled');
        }
        return array(
            'ok' => true,
            'status' => 200,
            'error' => '',
            'events' => array_values($events),
            'next_until' => $complete ? null : $oldestDeliveredAt,
            'complete' => $complete
        );
    }

    private static function reconciliationConfiguration($apiToken, $sharedLabelID)
    {
        $apiToken = $apiToken === null
            ? self::getEnvValue('NESP_BOARD_INTAKE_MISSIVE_API_TOKEN')
            : trim((string) $apiToken);
        $sharedLabelID = $sharedLabelID === null
            ? self::getEnvValue('NESP_BOARD_INTAKE_MISSIVE_SHARED_LABEL_ID')
            : trim((string) $sharedLabelID);
        $sharedLabelID = self::safeMessageID($sharedLabelID);
        if ($apiToken === '')
        {
            return self::fetchError(503, 'api_token_missing');
        }
        if ($sharedLabelID === '')
        {
            return self::fetchError(503, 'approved_label_missing');
        }
        return array(
            'ok' => true,
            'api_token' => $apiToken,
            'shared_label_id' => $sharedLabelID
        );
    }

    public static function classifyMessage($message)
    {
        $message = self::normalizeInputMessage($message);
        $platform = self::detectPlatform($message);
        $jobOrder = self::detectJobOrder($message);
        $identity = self::deriveExternalIdentity($message);
        $applicant = self::extractApplicant($message);

        $reviewReasons = array_merge(
            $platform['review_reasons'],
            $jobOrder['review_reasons'],
            $identity['review_reasons'],
            $applicant['review_reasons']
        );
        $reviewReasons = array_values(array_unique($reviewReasons));
        $status = $reviewReasons ? 'review_required' : 'ready_for_review';
        $platformKey = $platform['platform'];
        $sourceLabels = array(
            'indeed' => 'NESP Ad: Indeed',
            'linkedin' => 'NESP Ad: LinkedIn',
            'craigslist' => 'NESP Ad: Craigslist',
            'masshire' => 'NESP Ad: MassHire',
            'handshake' => 'NESP Ad: Handshake'
        );

        return array(
            'status' => $status,
            'status_key' => $status,
            'review_required' => $status === 'review_required',
            'human_review_required' => $status === 'review_required',
            'platform' => $platformKey,
            'platform_key' => $platformKey,
            'job_order_id' => $jobOrder['job_order_id'],
            'joborder_id' => $jobOrder['job_order_id'],
            'source_label' => $platformKey !== null && isset($sourceLabels[$platformKey])
                ? $sourceLabels[$platformKey]
                : '',
            'external_id' => $identity['external_id'],
            'external_id_source' => $identity['source'],
            'first_name' => $applicant['first_name'],
            'last_name' => $applicant['last_name'],
            'email' => $applicant['email'],
            'phone' => $applicant['phone'],
            'review_reasons' => $reviewReasons
        );
    }

    public static function classifyBoardNotification($message)
    {
        return self::classifyMessage($message);
    }

    public static function classifyNotification($message)
    {
        return self::classifyMessage($message);
    }

    public static function deriveExternalID($message)
    {
        $identity = self::deriveExternalIdentity(self::normalizeInputMessage($message));
        return $identity['external_id'];
    }

    public static function deriveExternalIdentity($message)
    {
        $message = self::normalizeInputMessage($message);
        $text = self::searchableText($message);
        $values = self::extractLabeledValues(
            $text,
            array('application id', 'applicant id', 'candidate id', 'external id')
        );

        $applicationIDs = array();
        $invalidLabeledID = false;
        foreach ($values as $value)
        {
            $value = trim($value);
            if (!preg_match('/^[A-Za-z0-9][A-Za-z0-9._:@-]{0,127}$/D', $value))
            {
                $invalidLabeledID = true;
                continue;
            }
            if (!in_array($value, $applicationIDs, true))
            {
                $applicationIDs[] = $value;
            }
        }

        if ($invalidLabeledID || count($applicationIDs) > 1)
        {
            return array(
                'external_id' => null,
                'source' => '',
                'review_reasons' => array('application_id_ambiguous')
            );
        }
        if (count($applicationIDs) === 1)
        {
            return array(
                'external_id' => $applicationIDs[0],
                'source' => 'labeled_application_id',
                'review_reasons' => array()
            );
        }

        $emailMessageID = self::safeEmailMessageID(
            isset($message['email_message_id']) ? $message['email_message_id'] : ''
        );
        if ($emailMessageID !== '')
        {
            return array(
                'external_id' => 'email-message:' . hash('sha256', $emailMessageID),
                'source' => 'email_message_id',
                'review_reasons' => array()
            );
        }

        return array(
            'external_id' => null,
            'source' => '',
            'review_reasons' => array('external_id_missing')
        );
    }

    public static function extractApplicant($message)
    {
        $message = self::normalizeInputMessage($message);
        $text = self::searchableText($message);
        $reviewReasons = array();

        $firstNames = self::uniqueValues(self::extractLabeledValues(
            $text,
            array('applicant first name', 'candidate first name', 'first name', 'given name')
        ));
        $lastNames = self::uniqueValues(self::extractLabeledValues(
            $text,
            array('applicant last name', 'candidate last name', 'last name', 'family name', 'surname')
        ));

        $firstName = count($firstNames) === 1 ? $firstNames[0] : '';
        $lastName = count($lastNames) === 1 ? $lastNames[0] : '';
        if (count($firstNames) > 1 || count($lastNames) > 1)
        {
            $reviewReasons[] = 'applicant_name_ambiguous';
            $firstName = '';
            $lastName = '';
        }

        $fullNames = self::uniqueValues(self::extractLabeledValues(
            $text,
            array('applicant name', 'candidate name', 'applicant', 'candidate', 'name')
        ));
        if (count($fullNames) > 1)
        {
            $reviewReasons[] = 'applicant_name_ambiguous';
            $firstName = '';
            $lastName = '';
        }
        elseif (count($fullNames) === 1)
        {
            $parts = preg_split('/\s+/u', trim($fullNames[0]));
            if (count($parts) !== 2)
            {
                $reviewReasons[] = 'applicant_name_ambiguous';
                $firstName = '';
                $lastName = '';
            }
            else
            {
                if (($firstName !== '' && strcasecmp($firstName, $parts[0]) !== 0)
                    || ($lastName !== '' && strcasecmp($lastName, $parts[1]) !== 0))
                {
                    $reviewReasons[] = 'applicant_name_ambiguous';
                    $firstName = '';
                    $lastName = '';
                }
                else
                {
                    $firstName = $firstName !== '' ? $firstName : $parts[0];
                    $lastName = $lastName !== '' ? $lastName : $parts[1];
                }
            }
        }

        if ($firstName === '' || $lastName === '')
        {
            if (!in_array('applicant_name_ambiguous', $reviewReasons, true))
            {
                $reviewReasons[] = 'applicant_name_missing';
            }
        }
        elseif (!self::isSafeNamePart($firstName) || !self::isSafeNamePart($lastName))
        {
            $reviewReasons[] = 'applicant_name_ambiguous';
            $firstName = '';
            $lastName = '';
        }

        $emailValues = self::uniqueValues(self::extractLabeledValues(
            $text,
            array('applicant email', 'candidate email', 'email address', 'email')
        ), true);
        $emails = array();
        $invalidEmail = false;
        foreach ($emailValues as $emailValue)
        {
            $email = self::emailFromLabeledValue($emailValue);
            if ($email === '')
            {
                $invalidEmail = true;
                continue;
            }
            if (!in_array($email, $emails, true))
            {
                $emails[] = $email;
            }
        }

        $email = count($emails) === 1 ? $emails[0] : '';
        if (count($emails) > 1)
        {
            $reviewReasons[] = 'applicant_email_ambiguous';
            $email = '';
        }
        elseif ($invalidEmail)
        {
            $reviewReasons[] = 'applicant_email_invalid';
            $email = '';
        }

        $phoneValues = self::uniqueValues(self::extractLabeledValues(
            $text,
            array('phone', 'phone number', 'mobile', 'cell phone')
        ));
        $phone = '';
        if (count($phoneValues) === 1)
        {
            $candidatePhone = trim($phoneValues[0]);
            if (preg_match('/^[0-9+(). x-]{7,32}$/D', $candidatePhone))
            {
                $phone = $candidatePhone;
            }
        }
        elseif (count($phoneValues) > 1)
        {
            $reviewReasons[] = 'applicant_phone_ambiguous';
        }

        return array(
            'first_name' => $firstName,
            'last_name' => $lastName,
            'email' => $email,
            'phone' => $phone,
            'review_reasons' => array_values(array_unique($reviewReasons))
        );
    }

    private static function detectPlatform($message)
    {
        $fromAddress = isset($message['from_address']) ? $message['from_address'] : '';
        if ($fromAddress === '' && isset($message['from_field']['address']))
        {
            $fromAddress = self::safeEmailAddress($message['from_field']['address']);
        }
        $domain = '';
        if ($fromAddress !== '' && strpos($fromAddress, '@') !== false)
        {
            $domain = substr(strrchr($fromAddress, '@'), 1);
        }

        $rules = array(
            'indeed' => array('indeed.com', 'indeedemail.com', 'indeedmail.com'),
            'linkedin' => array('linkedin.com'),
            'craigslist' => array('craigslist.org'),
            'masshire' => array('mass.gov', 'massmail.state.ma.us'),
            'handshake' => array('joinhandshake.com')
        );
        $domainMatches = array();
        foreach ($rules as $platform => $domains)
        {
            foreach ($domains as $allowedDomain)
            {
                if (self::domainMatches($domain, $allowedDomain))
                {
                    $domainMatches[] = $platform;
                    break;
                }
            }
        }
        $domainMatches = array_values(array_unique($domainMatches));

        $text = self::searchableText($message);
        $notificationPatterns = array(
            '/\b(?:new|received)\s+(?:job\s+)?application\b/iu',
            '/\b(?:has|have)\s+applied\b/iu',
            '/\bnew\s+applicant\b/iu',
            '/\bapplication\s+(?:for|to)\b/iu',
            '/\bcandidate\s+(?:for|application)\b/iu',
            '/\b(?:job|candidate)\s+referral\b/iu',
            '/\bresume\s+(?:for|from)\b/iu',
            '/\b(?:reply|response)\s+to\s+your\s+(?:job\s+)?post(?:ing)?\b/iu'
        );
        $isNotification = self::matchesAny($text, $notificationPatterns);

        if (count($domainMatches) !== 1 || !$isNotification)
        {
            return array(
                'platform' => null,
                'review_reasons' => array(count($domainMatches) > 1
                    ? 'platform_ambiguous'
                    : 'platform_unverified')
            );
        }

        $platform = $domainMatches[0];
        if ($platform === 'masshire' && !preg_match('/\b(?:masshire|jobquest)\b/iu', $text))
        {
            return array('platform' => null, 'review_reasons' => array('platform_unverified'));
        }

        $subject = isset($message['subject']) ? $message['subject'] : '';
        $subjectHints = array();
        $hintPatterns = array(
            'indeed' => '/\bindeed\b/iu',
            'linkedin' => '/\blinkedin\b/iu',
            'craigslist' => '/\bcraigslist\b/iu',
            'masshire' => '/\b(?:masshire|jobquest)\b/iu',
            'handshake' => '/\bhandshake\b/iu'
        );
        foreach ($hintPatterns as $hintPlatform => $pattern)
        {
            if (preg_match($pattern, $subject))
            {
                $subjectHints[] = $hintPlatform;
            }
        }
        if (count($subjectHints) > 1 || (count($subjectHints) === 1 && $subjectHints[0] !== $platform))
        {
            return array('platform' => null, 'review_reasons' => array('platform_ambiguous'));
        }

        return array('platform' => $platform, 'review_reasons' => array());
    }

    private static function detectJobOrder($message)
    {
        $text = self::searchableText($message);
        $matches = array();
        $allowedJobOrders = self::allowedJobOrders();

        if (preg_match_all(
            '/\b(?:nesp\s+)?(?:job\s+order|joborder(?:_id)?|job\s+id|requisition(?:\s+id)?|position\s+id)\s*(?:#|:|=|-)?\s*(\d{4,10})\b/iu',
            $text,
            $numericMatches
        ))
        {
            foreach ($numericMatches[1] as $jobOrderID)
            {
                if (!isset($allowedJobOrders[(int) $jobOrderID]))
                {
                    return array(
                        'job_order_id' => null,
                        'review_reasons' => array('job_order_unrecognized')
                    );
                }
                $matches[] = (int) $jobOrderID;
            }
        }
        if (preg_match_all('/[?&](?:id|joborder_id)=(\d{4,10})\b/iu', $text, $urlMatches))
        {
            foreach ($urlMatches[1] as $jobOrderID)
            {
                if (!isset($allowedJobOrders[(int) $jobOrderID]))
                {
                    return array(
                        'job_order_id' => null,
                        'review_reasons' => array('job_order_unrecognized')
                    );
                }
                $matches[] = (int) $jobOrderID;
            }
        }

        $titlePatterns = array(
            41001 => '/\bpart[ -]time\s+customer\s+service\s+representative\b/iu',
            41002 => '/\bstaff\s+photographer\b/iu',
            41003 => '/\b(?:freelance(?:\s*\/\s*contract)?|contract)\s+youth\s+sports\s+photographer\b/iu',
            41005 => '/\bweekend\s+table\s+greeter\s*(?:\/|and)\s*field\s+assistant\b/iu'
        );
        foreach ($titlePatterns as $jobOrderID => $pattern)
        {
            if (preg_match($pattern, $text))
            {
                $matches[] = $jobOrderID;
            }
        }

        $matches = array_values(array_unique($matches));
        if (count($matches) !== 1)
        {
            return array(
                'job_order_id' => null,
                'review_reasons' => array(count($matches) > 1
                    ? 'job_order_ambiguous'
                    : 'job_order_unrecognized')
            );
        }

        return array('job_order_id' => $matches[0], 'review_reasons' => array());
    }

    private static function performMessageRequest($url, $apiToken)
    {
        if (!function_exists('curl_init'))
        {
            return array('status_code' => 0, 'body' => null);
        }

        $responseBody = '';
        $responseTooLarge = false;
        $retryAfter = '';
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_HTTPGET, true);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, false);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, false);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, self::CONNECT_TIMEOUT_SECONDS);
        curl_setopt($ch, CURLOPT_TIMEOUT, self::REQUEST_TIMEOUT_SECONDS);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
        if (defined('CURLOPT_PROTOCOLS') && defined('CURLPROTO_HTTPS'))
        {
            curl_setopt($ch, CURLOPT_PROTOCOLS, CURLPROTO_HTTPS);
        }
        if (defined('CURLOPT_REDIR_PROTOCOLS') && defined('CURLPROTO_HTTPS'))
        {
            curl_setopt($ch, CURLOPT_REDIR_PROTOCOLS, CURLPROTO_HTTPS);
        }
        curl_setopt($ch, CURLOPT_HTTPHEADER, array(
            'Authorization: Bearer ' . $apiToken,
            'Accept: application/json'
        ));
        curl_setopt($ch, CURLOPT_HEADERFUNCTION, function ($handle, $headerLine) use (&$retryAfter) {
            if (stripos($headerLine, 'Retry-After:') === 0)
            {
                $retryAfter = trim(substr($headerLine, strlen('Retry-After:')));
            }
            return strlen($headerLine);
        });
        curl_setopt($ch, CURLOPT_WRITEFUNCTION, function ($handle, $chunk) use (&$responseBody, &$responseTooLarge) {
            if (strlen($responseBody) + strlen($chunk) > self::API_RESPONSE_MAX_BYTES)
            {
                $responseTooLarge = true;
                return 0;
            }
            $responseBody .= $chunk;
            return strlen($chunk);
        });

        $success = curl_exec($ch);
        $statusCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($responseTooLarge)
        {
            return array(
                'status_code' => $statusCode,
                'body' => null,
                'response_too_large' => true,
                'retry_after' => $retryAfter
            );
        }
        if ($success === false)
        {
            return array('status_code' => $statusCode, 'body' => null, 'retry_after' => $retryAfter);
        }

        return array('status_code' => $statusCode, 'body' => $responseBody, 'retry_after' => $retryAfter);
    }

    private static function performReadRequest($url, $apiToken, $httpClient = null)
    {
        $response = null;
        for ($attempt = 0; $attempt <= self::MAX_RATE_LIMIT_RETRIES; $attempt++)
        {
            try
            {
                $response = $httpClient === null
                    ? self::performMessageRequest($url, $apiToken)
                    : call_user_func(
                        $httpClient,
                        $url,
                        array('Authorization: Bearer ' . $apiToken, 'Accept: application/json'),
                        array(
                            'method' => 'GET',
                            'connect_timeout' => self::CONNECT_TIMEOUT_SECONDS,
                            'timeout' => self::REQUEST_TIMEOUT_SECONDS,
                            'follow_redirects' => false
                        )
                    );
            }
            catch (Throwable $exception)
            {
                return self::fetchError(502, 'missive_request_failed');
            }

            $statusCode = is_array($response) && isset($response['status_code'])
                ? (int) $response['status_code']
                : 0;
            if ($statusCode !== 429)
            {
                break;
            }
            $retryDelay = self::retryAfterDelaySeconds($response);
            if ($retryDelay < 1)
            {
                $retryDelay = self::DEFAULT_RATE_LIMIT_BACKOFF_SECONDS;
            }
            $retryDelay = min(self::MAX_RATE_LIMIT_BACKOFF_SECONDS, $retryDelay);
            if ($attempt >= self::MAX_RATE_LIMIT_RETRIES)
            {
                return self::fetchError(503, 'missive_rate_limited', array(
                    'retry_after_seconds' => $retryDelay
                ));
            }

            if ($retryDelay > self::MAX_RATE_LIMIT_DELAY_SECONDS)
            {
                return self::fetchError(503, 'missive_rate_limited', array(
                    'retry_after_seconds' => $retryDelay
                ));
            }
            // Injected test clients advance immediately. Production honors a
            // short provider delay and stops instead of sleeping without limit.
            if ($httpClient === null)
            {
                sleep($retryDelay);
            }
        }
        if (!is_array($response) || !empty($response['response_too_large']))
        {
            return self::fetchError(502, !empty($response['response_too_large'])
                ? 'missive_response_too_large'
                : 'missive_request_failed');
        }
        $statusCode = isset($response['status_code']) ? (int) $response['status_code'] : 0;
        if ($statusCode === 401 || $statusCode === 403)
        {
            return self::fetchError(502, 'missive_auth_failed');
        }
        if ($statusCode !== 200 || !array_key_exists('body', $response))
        {
            return self::fetchError(502, 'missive_request_failed');
        }
        $body = $response['body'];
        $decodeFailed = false;
        if (is_string($body))
        {
            if (strlen($body) > self::API_RESPONSE_MAX_BYTES)
            {
                return self::fetchError(502, 'missive_response_too_large');
            }
            $body = json_decode($body, true, 32, JSON_BIGINT_AS_STRING);
            $decodeFailed = json_last_error() !== JSON_ERROR_NONE;
        }
        if (!is_array($body) || $decodeFailed)
        {
            return self::fetchError(502, 'invalid_missive_response');
        }
        return array('ok' => true, 'status' => 200, 'error' => '', 'data' => $body);
    }

    private static function retryAfterDelaySeconds($response)
    {
        $value = isset($response['retry_after']) ? trim((string) $response['retry_after']) : '';
        if ($value === '' && isset($response['headers']) && is_array($response['headers']))
        {
            foreach ($response['headers'] as $name => $headerValue)
            {
                if (is_string($name) && strcasecmp($name, 'Retry-After') === 0)
                {
                    $value = trim((string) $headerValue);
                    break;
                }
                if (is_int($name) && is_string($headerValue)
                    && stripos($headerValue, 'Retry-After:') === 0)
                {
                    $value = trim(substr($headerValue, strlen('Retry-After:')));
                    break;
                }
            }
        }
        if ($value === '')
        {
            return 1;
        }
        if (preg_match('/^\d+$/D', $value))
        {
            return max(1, (int) $value);
        }

        $timestamp = strtotime($value);
        return $timestamp === false ? 0 : max(1, $timestamp - time());
    }

    private static function oneMessageFromResponse($response)
    {
        if (!isset($response['messages']) || !is_array($response['messages']))
        {
            return null;
        }

        $messages = $response['messages'];
        if (isset($messages['id']))
        {
            return $messages;
        }
        if (array_is_list($messages) && count($messages) === 1 && is_array($messages[0]))
        {
            return $messages[0];
        }

        return null;
    }

    private static function normalizeFetchedMessage($message)
    {
        if (!is_array($message))
        {
            return null;
        }

        $id = self::safeMessageID(isset($message['id']) ? $message['id'] : null);
        $type = strtolower(self::safeSingleLine(isset($message['type']) ? $message['type'] : '', 32));
        if ($id === '' || ($type !== '' && $type !== 'email'))
        {
            return null;
        }
        $fromField = isset($message['from_field']) && is_array($message['from_field'])
            ? $message['from_field']
            : array();

        return array(
            'id' => $id,
            'type' => $type === '' ? 'email' : $type,
            'email_message_id' => self::safeEmailMessageID(
                isset($message['email_message_id']) ? $message['email_message_id'] : null
            ),
            'subject' => self::safeSingleLine(isset($message['subject']) ? $message['subject'] : '', 998),
            'preview' => self::safeSingleLine(isset($message['preview']) ? $message['preview'] : '', 2000),
            'body' => self::safeBody(isset($message['body']) ? $message['body'] : ''),
            'from_field' => array(
                'name' => self::safeSingleLine(isset($fromField['name']) ? $fromField['name'] : '', 200),
                'address' => self::safeEmailAddress(isset($fromField['address']) ? $fromField['address'] : '')
            )
        );
    }

    private static function normalizeInputMessage($message)
    {
        if (!is_array($message))
        {
            return array();
        }
        if (isset($message['message']) && is_array($message['message']))
        {
            $message = $message['message'];
        }

        $fromField = isset($message['from_field']) && is_array($message['from_field'])
            ? $message['from_field']
            : array();
        $fromAddress = isset($message['from_address'])
            ? self::safeEmailAddress($message['from_address'])
            : self::safeEmailAddress(isset($fromField['address']) ? $fromField['address'] : '');

        return array(
            'id' => self::safeMessageID(isset($message['id']) ? $message['id'] : ''),
            'email_message_id' => self::safeEmailMessageID(
                isset($message['email_message_id']) ? $message['email_message_id'] : ''
            ),
            'subject' => self::safeSingleLine(isset($message['subject']) ? $message['subject'] : '', 998),
            'preview' => self::safeSingleLine(isset($message['preview']) ? $message['preview'] : '', 2000),
            'body' => self::safeBody(isset($message['body']) ? $message['body'] : ''),
            'from_name' => self::safeSingleLine(
                isset($message['from_name']) ? $message['from_name'] : (isset($fromField['name']) ? $fromField['name'] : ''),
                200
            ),
            'from_address' => $fromAddress,
            'from_field' => array(
                'name' => self::safeSingleLine(isset($fromField['name']) ? $fromField['name'] : '', 200),
                'address' => $fromAddress
            )
        );
    }

    private static function searchableText($message)
    {
        $parts = array(
            isset($message['subject']) ? $message['subject'] : '',
            isset($message['preview']) ? $message['preview'] : '',
            self::bodyToText(isset($message['body']) ? $message['body'] : '')
        );
        return substr(implode("\n", $parts), 0, self::WEBHOOK_MAX_BYTES);
    }

    private static function bodyToText($body)
    {
        $body = self::safeBody($body);
        $body = preg_replace('/<(?:script|style)\b[^>]*>.*?<\/(?:script|style)>/isu', ' ', $body);
        $body = preg_replace('/<\s*(?:br|\/p|\/div|\/li)\s*\/?>/iu', "\n", $body);
        $body = strip_tags($body);
        $body = html_entity_decode($body, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        return preg_replace('/[\t ]+/u', ' ', $body);
    }

    private static function extractLabeledValues($text, $labels)
    {
        $quotedLabels = array_map(function ($label) {
            return preg_quote($label, '/');
        }, $labels);
        $pattern = '/^\s*(?:' . implode('|', $quotedLabels) . ')\s*(?:[:#-])\s*([^\r\n]{1,300})\s*$/imu';
        if (!preg_match_all($pattern, $text, $matches))
        {
            return array();
        }

        return array_map(function ($value) {
            return trim($value, " \t\n\r\0\x0B\"'");
        }, $matches[1]);
    }

    private static function uniqueValues($values, $lowercase = false)
    {
        $unique = array();
        $seen = array();
        foreach ($values as $value)
        {
            $value = trim((string) $value);
            if ($value === '')
            {
                continue;
            }
            $key = $lowercase ? strtolower($value) : $value;
            if (!isset($seen[$key]))
            {
                $seen[$key] = true;
                $unique[] = $value;
            }
        }
        return $unique;
    }

    private static function emailFromLabeledValue($value)
    {
        $value = trim((string) $value);
        if (preg_match('/<([^<>]+)>/', $value, $matches))
        {
            $value = trim($matches[1]);
        }
        return self::safeEmailAddress($value);
    }

    private static function isSafeNamePart($value)
    {
        $value = trim((string) $value);
        return $value !== ''
            && strlen($value) <= 100
            && preg_match("/^[\\p{L}\\p{M}][\\p{L}\\p{M}' .-]*$/uD", $value) === 1;
    }

    private static function safeMessageID($value)
    {
        if (!is_string($value))
        {
            return '';
        }
        $value = trim($value);
        return preg_match('/^[A-Za-z0-9][A-Za-z0-9_-]{0,127}$/D', $value) ? $value : '';
    }

    private static function safeEmailMessageID($value)
    {
        if (!is_string($value))
        {
            return '';
        }
        $value = trim($value);
        if ($value === '' || strlen($value) > 998 || preg_match('/[\x00-\x20\x7f]/', $value))
        {
            return '';
        }
        return $value;
    }

    private static function safeEmailAddress($value)
    {
        if (!is_string($value))
        {
            return '';
        }
        $value = strtolower(trim($value));
        return filter_var($value, FILTER_VALIDATE_EMAIL) ? $value : '';
    }

    private static function safeSingleLine($value, $maxBytes)
    {
        if (!is_string($value))
        {
            return '';
        }
        $value = preg_replace('/[\x00-\x1f\x7f]+/', ' ', $value);
        $value = trim(preg_replace('/\s+/u', ' ', $value));
        return substr($value, 0, $maxBytes);
    }

    private static function safeBody($value)
    {
        if (!is_string($value))
        {
            return '';
        }
        $value = preg_replace('/[\x00\x08\x0B\x0C\x0E-\x1F\x7F]/', '', $value);
        return substr($value, 0, self::API_RESPONSE_MAX_BYTES);
    }

    private static function domainMatches($domain, $allowedDomain)
    {
        $domain = strtolower(trim((string) $domain));
        $allowedDomain = strtolower(trim((string) $allowedDomain));
        if ($domain === '' || $allowedDomain === '')
        {
            return false;
        }
        return $domain === $allowedDomain
            || substr($domain, -(strlen($allowedDomain) + 1)) === '.' . $allowedDomain;
    }

    private static function matchesAny($value, $patterns)
    {
        foreach ($patterns as $pattern)
        {
            if (preg_match($pattern, $value))
            {
                return true;
            }
        }
        return false;
    }

    private static function numericContentLengthExceedsLimit($contentLength)
    {
        if (is_int($contentLength))
        {
            return $contentLength > self::WEBHOOK_MAX_BYTES;
        }
        if (is_string($contentLength) && preg_match('/^\d+$/D', $contentLength))
        {
            return (int) $contentLength > self::WEBHOOK_MAX_BYTES;
        }
        return false;
    }

    private static function headerValue($headers, $name)
    {
        if (!is_array($headers))
        {
            return '';
        }
        foreach ($headers as $headerName => $value)
        {
            if (is_string($headerName) && strcasecmp($headerName, $name) === 0 && is_string($value))
            {
                return trim($value);
            }
        }
        return '';
    }

    private static function validationError($status, $error)
    {
        return array('ok' => false, 'status' => (int) $status, 'error' => $error);
    }

    private static function fetchError($status, $error, $metadata = array())
    {
        return array_merge(array(
            'ok' => false,
            'status' => (int) $status,
            'status_code' => (int) $status,
            'error' => $error,
            'message' => null
        ), is_array($metadata) ? $metadata : array());
    }

    private static function getEnvValue($key)
    {
        $value = getenv($key);
        return is_string($value) ? trim($value) : '';
    }
}

?>
