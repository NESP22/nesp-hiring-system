<?php
/*
 * NESP Vapi phone-screen safety helpers.
 *
 * Keep this file mostly pure/static so webhook validation and unit tests can
 * exercise the safety rules without a browser session or live provider access.
 */

class NESPVapiIntegration
{
    const WEBHOOK_MAX_BYTES = 262144;
    const WEBHOOK_ALLOWED_SKEW_SECONDS = 300;

    public static function getConsentOpeningScript()
    {
        return 'Hi, this is the automated New England Sports Photo hiring assistant regarding your application.' . "\n\n"
            . 'This call will not be audio recorded, but it will be transcribed into text so the NESP hiring team can review your job-related responses. A person makes every hiring decision.' . "\n\n"
            . 'Do you consent to continue with this transcribed phone screening?';
    }

    public static function getAllowedWebhookEventTypes()
    {
        return array(
            'status-update',
            'end-of-call-report',
            'conversation-update',
            'transcript',
            'hang',
            'call.created',
            'call.ringing',
            'call.answered',
            'call.completed',
            'call.no-answer',
            'call.failed',
            'call.cancelled',
            'structured-result'
        );
    }

    public static function getRoleScript($jobOrderID, $roleTitle = '')
    {
        $jobOrderID = (int) $jobOrderID;
        $roleTitle = strtolower($roleTitle);
        $scripts = array(
            41001 => array(
                'role' => 'Customer Service',
                'questions' => array(
                    'Are you still interested in the Customer Service role?',
                    'Do you understand the role is expected to pay $22-$25 per hour?',
                    'Can you work in-office in Methuen on weekday daytime shifts?',
                    'Are you comfortable with phone and email support?',
                    'What customer-service experience would you like us to know about?',
                    'Please share a brief example of resolving a customer issue.',
                    'What questions do you have for a person on the NESP hiring team?'
                )
            ),
            41002 => array(
                'role' => 'Staff Photographer',
                'questions' => array(
                    'Are you still interested in the Staff Photographer role?',
                    'Do you understand the role is expected to pay $22-$25 per hour?',
                    'Can you work early weekend mornings during the season?',
                    'Do you have a valid license and reliable transportation?',
                    'Are you comfortable traveling to assigned sports-photo events?',
                    'Are you comfortable working with children, families, and coaches?',
                    'Are you willing to learn NESP equipment and workflow?',
                    'What questions do you have for a person on the NESP hiring team?'
                )
            ),
            41003 => array(
                'role' => 'Freelance Photographer',
                'questions' => array(
                    'Are you still interested in the Freelance Photographer role?',
                    'Do you understand the role is expected to pay $22-$27 per hour?',
                    'Do you understand this role is contractor classified?',
                    'Can you work early weekend mornings during the season?',
                    'Are you comfortable traveling 60-90 minutes to events?',
                    'Do you have reliable transportation?',
                    'Do you have a camera body, portrait lens, and manual or TTL speedlight?',
                    'Are you comfortable using manual camera and flash settings?',
                    'What relevant photography experience would you like us to know about?',
                    'What questions do you have for a person on the NESP hiring team?'
                )
            ),
            41005 => array(
                'role' => 'Field Assistant',
                'questions' => array(
                    'Are you still interested in the Field Assistant role?',
                    'Do you understand the role is expected to pay $18 per hour?',
                    'Can you work early weekend mornings during the season?',
                    'Do you have reliable transportation?',
                    'Are you comfortable with outdoor work?',
                    'Are you comfortable helping direct groups during photo events?',
                    'Are you comfortable with extended standing and occasional lifting?',
                    'What questions do you have for a person on the NESP hiring team?'
                )
            )
        );

        if (isset($scripts[$jobOrderID]))
        {
            return $scripts[$jobOrderID];
        }

        if (strpos($roleTitle, 'customer service') !== false)
        {
            return $scripts[41001];
        }
        if (strpos($roleTitle, 'freelance') !== false)
        {
            return $scripts[41003];
        }
        if (strpos($roleTitle, 'assistant') !== false)
        {
            return $scripts[41005];
        }
        return $scripts[41002];
    }

    public static function getConfigurationStatus($featureEnabled)
    {
        $apiKey = self::getEnvValue('VAPI_API_KEY');
        $phoneID = self::getEnvValue('VAPI_PHONE_NUMBER_ID');
        $assistantID = self::getEnvValue('VAPI_HIRING_ASSISTANT_ID');
        $webhookSecret = self::getEnvValue('VAPI_WEBHOOK_SECRET');

        return array(
            'api_configured' => $apiKey !== '',
            'hiring_phone_configured' => $phoneID !== '',
            'hiring_assistant_configured' => $assistantID !== '',
            'webhook_secret_configured' => $webhookSecret !== '',
            'recording_disabled' => self::isRecordingDisabled(),
            'feature_enabled' => (bool) $featureEnabled,
            'phone_id_preview' => self::redactIdentifier($phoneID),
            'assistant_id_preview' => self::redactIdentifier($assistantID),
            'webhook_url' => self::getWebhookURL()
        );
    }

    public static function isReadyForOutboundCalls($featureEnabled)
    {
        $status = self::getConfigurationStatus($featureEnabled);
        return $status['api_configured']
            && $status['hiring_phone_configured']
            && $status['hiring_assistant_configured']
            && $status['webhook_secret_configured']
            && $status['recording_disabled']
            && $status['feature_enabled'];
    }

    public static function getWebhookURL()
    {
        $baseURL = rtrim(self::getEnvValue('NESP_PUBLIC_BASE_URL'), '/');
        if ($baseURL === '')
        {
            $baseURL = 'https://careers.nesportsphoto.com';
        }
        return $baseURL . '/modules/nesp/vapiWebhook.php';
    }

    public static function buildOutboundCallPayload($destinationPhone, $candidate, $job, $callRequestKey)
    {
        $roleScript = self::getRoleScript(isset($job['joborder_id']) ? $job['joborder_id'] : 0, isset($job['title']) ? $job['title'] : '');
        return array(
            'assistantId' => self::getEnvValue('VAPI_HIRING_ASSISTANT_ID'),
            'phoneNumberId' => self::getEnvValue('VAPI_PHONE_NUMBER_ID'),
            'customer' => array(
                'number' => self::normalizePhoneForDial($destinationPhone)
            ),
            'metadata' => array(
                'nesp_call_request_key' => $callRequestKey,
                'candidate_id' => isset($candidate['candidate_id']) ? (int) $candidate['candidate_id'] : 0,
                'joborder_id' => isset($job['joborder_id']) ? (int) $job['joborder_id'] : 0,
                'role' => $roleScript['role'],
                'consent_required' => true,
                'audio_recording' => 'off'
            )
        );
    }

    public static function normalizePhoneForDial($phone)
    {
        $phone = trim($phone);
        if ($phone === '')
        {
            return '';
        }
        if (substr($phone, 0, 1) === '+')
        {
            return '+' . preg_replace('/\D+/', '', substr($phone, 1));
        }
        $digits = preg_replace('/\D+/', '', $phone);
        if (strlen($digits) === 10)
        {
            return '+1' . $digits;
        }
        if (strlen($digits) === 11 && substr($digits, 0, 1) === '1')
        {
            return '+' . $digits;
        }
        return $phone;
    }

    public static function redactPhone($phone)
    {
        $digits = preg_replace('/\D+/', '', $phone);
        if ($digits === '')
        {
            return '';
        }
        $last4 = substr($digits, -4);
        return '***-***-' . $last4;
    }

    public static function phoneLast4($phone)
    {
        $digits = preg_replace('/\D+/', '', $phone);
        return $digits === '' ? '' : substr($digits, -4);
    }

    public static function phoneHash($phone)
    {
        return hash('sha256', self::normalizePhoneForDial($phone));
    }

    public static function redactIdentifier($value)
    {
        $value = trim($value);
        if ($value === '')
        {
            return '';
        }
        if (strlen($value) <= 8)
        {
            return 'configured';
        }
        return substr($value, 0, 4) . '...' . substr($value, -4);
    }

    public static function validateWebhookRequest($headers, $contentType, $rawBody, $nowTimestamp, $webhookSecret)
    {
        $webhookSecret = trim($webhookSecret);
        if ($webhookSecret === '')
        {
            return self::validationError(503, 'webhook_secret_missing');
        }

        if (stripos($contentType, 'application/json') === false)
        {
            return self::validationError(415, 'unsupported_content_type');
        }

        if (strlen($rawBody) > self::WEBHOOK_MAX_BYTES)
        {
            return self::validationError(413, 'payload_too_large');
        }

        $providedSecret = self::headerValue($headers, 'X-Vapi-Secret');
        $authorization = self::headerValue($headers, 'Authorization');
        if ($providedSecret === '' && stripos($authorization, 'Bearer ') === 0)
        {
            $providedSecret = trim(substr($authorization, 7));
        }
        if ($providedSecret === '' || !hash_equals($webhookSecret, $providedSecret))
        {
            return self::validationError(401, 'invalid_secret');
        }

        $timestamp = self::headerValue($headers, 'X-Vapi-Timestamp');
        if ($timestamp === '')
        {
            $timestamp = self::headerValue($headers, 'X-NESP-Webhook-Timestamp');
        }
        $timestampValue = self::parseTimestamp($timestamp);
        if ($timestampValue === false || abs((int) $nowTimestamp - $timestampValue) > self::WEBHOOK_ALLOWED_SKEW_SECONDS)
        {
            return self::validationError(401, 'expired_timestamp');
        }

        $payload = json_decode($rawBody, true);
        if (!is_array($payload))
        {
            return self::validationError(400, 'malformed_json');
        }

        $message = isset($payload['message']) && is_array($payload['message']) ? $payload['message'] : $payload;
        $eventType = isset($message['type']) ? (string) $message['type'] : '';
        if ($eventType === '' || !in_array($eventType, self::getAllowedWebhookEventTypes()))
        {
            return self::validationError(400, 'unsupported_event_type');
        }

        $providerCallID = self::extractProviderCallID($message);
        if ($providerCallID === '')
        {
            return self::validationError(400, 'missing_call_id');
        }

        $eventID = self::extractEventID($headers, $message, $rawBody);
        return array(
            'ok' => true,
            'status' => 200,
            'error' => '',
            'payload' => $payload,
            'message' => $message,
            'event_type' => $eventType,
            'event_id' => $eventID,
            'provider_call_id' => $providerCallID,
            'event_timestamp' => date('Y-m-d H:i:s', $timestampValue),
            'payload_sha256' => hash('sha256', $rawBody)
        );
    }

    public static function buildScreenUpdateFromWebhookMessage($message)
    {
        $eventType = isset($message['type']) ? (string) $message['type'] : '';
        $status = isset($message['status']) ? (string) $message['status'] : '';
        $statusKey = self::mapWebhookStatus($eventType, $status, isset($message['endedReason']) ? $message['endedReason'] : '');
        $transcript = self::extractTranscript($message);
        $consent = self::detectConsent($message, $transcript);

        return array(
            'status_key' => $statusKey,
            'consent_status' => $consent['status'],
            'consent_accepted' => $consent['accepted'],
            'consent_response_raw' => $consent['raw_response'],
            'transcript_text' => $consent['accepted'] ? $transcript : '',
            'structured_result_json' => self::extractStructuredResultJSON($message),
            'provider_end_reason' => isset($message['endedReason']) ? (string) $message['endedReason'] : ''
        );
    }

    public static function mapWebhookStatus($eventType, $providerStatus, $endedReason = '')
    {
        if ($eventType === 'status-update')
        {
            if ($providerStatus === 'ringing')
            {
                return 'ringing';
            }
            if ($providerStatus === 'in-progress')
            {
                return 'in_progress';
            }
            if ($providerStatus === 'ended')
            {
                return $endedReason === 'customer-did-not-answer' ? 'no_answer' : 'completed';
            }
            if ($providerStatus === 'queued' || $providerStatus === 'scheduled')
            {
                return 'call_requested';
            }
        }
        if ($eventType === 'end-of-call-report')
        {
            return $endedReason === 'customer-did-not-answer' ? 'no_answer' : 'completed';
        }
        if ($eventType === 'transcript' || $eventType === 'conversation-update')
        {
            return 'in_progress';
        }
        if ($eventType === 'hang')
        {
            return 'needs_clarification';
        }
        if ($eventType === 'call.ringing')
        {
            return 'ringing';
        }
        if ($eventType === 'call.answered')
        {
            return 'in_progress';
        }
        if ($eventType === 'call.failed')
        {
            return 'provider_error';
        }
        if ($eventType === 'call.cancelled')
        {
            return 'cancelled';
        }
        if ($eventType === 'call.no-answer')
        {
            return 'no_answer';
        }
        if ($eventType === 'call.completed' || $eventType === 'structured-result')
        {
            return 'completed';
        }
        return 'provider_error';
    }

    public static function detectConsent($message, $transcript)
    {
        if (isset($message['analysis']) && is_array($message['analysis'])
            && isset($message['analysis']['structuredData'])
            && is_array($message['analysis']['structuredData']))
        {
            $structured = $message['analysis']['structuredData'];
            if (isset($structured['consent_accepted']))
            {
                return array(
                    'status' => $structured['consent_accepted'] ? 'accepted' : 'refused',
                    'accepted' => (bool) $structured['consent_accepted'],
                    'raw_response' => isset($structured['consent_response_raw']) ? (string) $structured['consent_response_raw'] : ''
                );
            }
        }

        $haystack = strtolower($transcript);
        $refusalWords = array('do not consent', "don't consent", 'no consent', 'i do not agree', "don't agree", 'no thank');
        foreach ($refusalWords as $word)
        {
            if (strpos($haystack, $word) !== false)
            {
                return array('status' => 'refused', 'accepted' => false, 'raw_response' => $word);
            }
        }

        $affirmativeWords = array('i consent', 'yes i consent', 'yes, i consent', 'i agree', 'yes');
        foreach ($affirmativeWords as $word)
        {
            if (strpos($haystack, $word) !== false)
            {
                return array('status' => 'accepted', 'accepted' => true, 'raw_response' => $word);
            }
        }

        return array('status' => 'unknown', 'accepted' => false, 'raw_response' => '');
    }

    public static function redactedPayloadForStorage($payload)
    {
        $json = json_encode($payload);
        if ($json === false)
        {
            return '{}';
        }
        $json = preg_replace('/\+?1?\d[\d\-\.\s\(\)]{8,}\d/', '[redacted-phone]', $json);
        $json = preg_replace('/Bearer\s+[A-Za-z0-9\._\-]+/', 'Bearer [redacted]', $json);
        return $json;
    }

    private static function extractTranscript($message)
    {
        if (isset($message['transcript']) && is_string($message['transcript']))
        {
            return $message['transcript'];
        }
        if (isset($message['artifact']) && is_array($message['artifact'])
            && isset($message['artifact']['transcript'])
            && is_string($message['artifact']['transcript']))
        {
            return $message['artifact']['transcript'];
        }
        if (isset($message['messages']) && is_array($message['messages']))
        {
            $lines = array();
            foreach ($message['messages'] as $conversationMessage)
            {
                if (!is_array($conversationMessage))
                {
                    continue;
                }
                $role = isset($conversationMessage['role']) ? $conversationMessage['role'] : 'speaker';
                $text = isset($conversationMessage['message']) ? $conversationMessage['message'] : '';
                if ($text !== '')
                {
                    $lines[] = $role . ': ' . $text;
                }
            }
            return implode("\n", $lines);
        }
        return '';
    }

    private static function extractStructuredResultJSON($message)
    {
        if (isset($message['analysis']) && is_array($message['analysis'])
            && isset($message['analysis']['structuredData']))
        {
            $json = json_encode($message['analysis']['structuredData']);
            return $json === false ? '{}' : $json;
        }
        if (isset($message['structuredResult']) && is_array($message['structuredResult']))
        {
            $json = json_encode($message['structuredResult']);
            return $json === false ? '{}' : $json;
        }
        return '{}';
    }

    private static function extractProviderCallID($message)
    {
        if (isset($message['call']) && is_array($message['call']) && isset($message['call']['id']))
        {
            return (string) $message['call']['id'];
        }
        if (isset($message['callId']))
        {
            return (string) $message['callId'];
        }
        return '';
    }

    private static function extractEventID($headers, $message, $rawBody)
    {
        $eventID = self::headerValue($headers, 'X-Vapi-Event-Id');
        if ($eventID !== '')
        {
            return $eventID;
        }
        foreach (array('eventId', 'id') as $key)
        {
            if (isset($message[$key]) && (string) $message[$key] !== '')
            {
                return (string) $message[$key];
            }
        }
        return hash('sha256', self::extractProviderCallID($message) . '|' . (isset($message['type']) ? $message['type'] : '') . '|' . $rawBody);
    }

    private static function parseTimestamp($timestamp)
    {
        $timestamp = trim($timestamp);
        if ($timestamp === '')
        {
            return false;
        }
        if (ctype_digit($timestamp))
        {
            return (int) $timestamp;
        }
        $parsed = strtotime($timestamp);
        return $parsed === false ? false : $parsed;
    }

    private static function headerValue($headers, $name)
    {
        foreach ($headers as $key => $value)
        {
            if (strtolower($key) === strtolower($name))
            {
                return is_array($value) ? trim(reset($value)) : trim($value);
            }
        }
        return '';
    }

    private static function validationError($status, $error)
    {
        return array(
            'ok' => false,
            'status' => (int) $status,
            'error' => $error
        );
    }

    private static function getEnvValue($key)
    {
        $value = getenv($key);
        return $value === false ? '' : trim($value);
    }

    private static function isRecordingDisabled()
    {
        $recordingFlag = strtolower(self::getEnvValue('VAPI_RECORDING_ENABLED'));
        return !in_array($recordingFlag, array('1', 'true', 'yes', 'on'));
    }
}

?>
