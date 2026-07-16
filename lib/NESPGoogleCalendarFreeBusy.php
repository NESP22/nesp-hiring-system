<?php
/*
 * Google Calendar free/busy scaffolding for NESP interviewer scheduling.
 *
 * This integration is intentionally read-only and availability-only. It never
 * creates, updates, deletes, or invites calendar events.
 */

class NESPGoogleCalendarFreeBusy
{
    const FEATURE_FLAG = 'NESP_GOOGLE_CALENDAR_FREEBUSY_ENABLED';
    const MINIMUM_SCOPE = 'https://www.googleapis.com/auth/calendar.freebusy';
    const TOKEN_ENDPOINT = 'https://oauth2.googleapis.com/token';
    const AUTH_ENDPOINT = 'https://accounts.google.com/o/oauth2/v2/auth';
    const FREEBUSY_ENDPOINT = 'https://www.googleapis.com/calendar/v3/freeBusy';

    private $_db;
    private $_featureEnabled;

    public function __construct($db = null, $featureEnabled = false)
    {
        $this->_db = $db;
        $this->_featureEnabled = ((int) $featureEnabled) === 1;
    }

    public static function getDefaultFeatureFlag()
    {
        return array(
            self::FEATURE_FLAG,
            'Google Calendar Free/Busy',
            'Optional interviewer availability lookup using only Google Calendar free/busy scope. No event details are read and no events are created.',
            0
        );
    }

    public static function getDefaultIntegrationStatus()
    {
        return array(
            'google_calendar_freebusy',
            'Google Calendar Free/Busy',
            'disabled',
            'Optional interviewer availability lookup. Uses only free/busy scope and never creates calendar events.'
        );
    }

    public static function getRequiredOAuthScopes()
    {
        return array(self::MINIMUM_SCOPE);
    }

    public static function getConnectionStateLabels()
    {
        return array(
            'disconnected' => 'Not Connected',
            'connected' => 'Connected',
            'reauthorize_required' => 'Reauthorization Required',
            'error' => 'Error'
        );
    }

    public static function getConfigurationStatus($featureEnabled = false)
    {
        $clientID = trim((string) getenv('NESP_GOOGLE_CALENDAR_CLIENT_ID'));
        $clientSecret = trim((string) getenv('NESP_GOOGLE_CALENDAR_CLIENT_SECRET'));
        $redirectURI = trim((string) getenv('NESP_GOOGLE_CALENDAR_REDIRECT_URI'));
        $encryptionKey = trim((string) getenv('NESP_GOOGLE_CALENDAR_TOKEN_ENCRYPTION_KEY'));

        return array(
            'feature_enabled' => ((int) $featureEnabled) === 1,
            'client_configured' => $clientID !== '' && $clientSecret !== '' && $redirectURI !== '',
            'redirect_uri_configured' => $redirectURI !== '',
            'token_encryption_configured' => $encryptionKey !== '',
            'minimum_scope' => self::MINIMUM_SCOPE,
            'event_creation_enabled' => false,
            'status_key' => ((int) $featureEnabled) === 1 ? 'ready_for_test_configuration' : 'disabled'
        );
    }

    public static function buildAuthorizationURL($state, $loginHint = '')
    {
        $params = array(
            'client_id' => trim((string) getenv('NESP_GOOGLE_CALENDAR_CLIENT_ID')),
            'redirect_uri' => trim((string) getenv('NESP_GOOGLE_CALENDAR_REDIRECT_URI')),
            'response_type' => 'code',
            'scope' => self::MINIMUM_SCOPE,
            'access_type' => 'offline',
            'prompt' => 'consent',
            'state' => $state
        );
        if (trim($loginHint) !== '')
        {
            $params['login_hint'] = trim($loginHint);
        }

        return self::AUTH_ENDPOINT . '?' . http_build_query($params, '', '&');
    }

    public static function encryptToken($plainText)
    {
        $key = self::normalizedEncryptionKey();
        if ($key === false || !function_exists('openssl_encrypt'))
        {
            return false;
        }

        $iv = openssl_random_pseudo_bytes(12);
        $tag = '';
        $cipherText = openssl_encrypt((string) $plainText, 'aes-256-gcm', $key, OPENSSL_RAW_DATA, $iv, $tag);
        if ($cipherText === false)
        {
            return false;
        }

        return 'v1:' . base64_encode($iv . $tag . $cipherText);
    }

    public static function decryptToken($encryptedText)
    {
        $key = self::normalizedEncryptionKey();
        if ($key === false || !function_exists('openssl_decrypt'))
        {
            return false;
        }

        $encryptedText = (string) $encryptedText;
        if (strpos($encryptedText, 'v1:') !== 0)
        {
            return false;
        }

        $raw = base64_decode(substr($encryptedText, 3), true);
        if ($raw === false || strlen($raw) < 29)
        {
            return false;
        }

        $iv = substr($raw, 0, 12);
        $tag = substr($raw, 12, 16);
        $cipherText = substr($raw, 28);

        return openssl_decrypt($cipherText, 'aes-256-gcm', $key, OPENSSL_RAW_DATA, $iv, $tag);
    }

    public static function tokenFingerprint($token)
    {
        return hash('sha256', (string) $token);
    }

    public static function sanitizeFreeBusyResponse($response)
    {
        $sanitized = array(
            'status_key' => 'available',
            'calendars' => array(),
            'busy' => array(),
            'errors' => array()
        );

        if (!is_array($response) || !isset($response['calendars']) || !is_array($response['calendars']))
        {
            $sanitized['status_key'] = 'error';
            $sanitized['errors'][] = 'invalid_freebusy_response';
            return $sanitized;
        }

        foreach ($response['calendars'] as $calendarID => $calendar)
        {
            $calendarHash = hash('sha256', (string) $calendarID);
            $sanitized['calendars'][$calendarHash] = array('busy' => array(), 'errors' => array());

            if (isset($calendar['errors']) && is_array($calendar['errors']))
            {
                foreach ($calendar['errors'] as $error)
                {
                    $reason = isset($error['reason']) ? (string) $error['reason'] : 'calendar_error';
                    $sanitized['calendars'][$calendarHash]['errors'][] = $reason;
                    $sanitized['errors'][] = $reason;
                }
            }

            if (!isset($calendar['busy']) || !is_array($calendar['busy']))
            {
                continue;
            }

            foreach ($calendar['busy'] as $busyWindow)
            {
                if (!isset($busyWindow['start']) || !isset($busyWindow['end']))
                {
                    continue;
                }
                $window = array(
                    'start' => (string) $busyWindow['start'],
                    'end' => (string) $busyWindow['end'],
                    'source_key' => 'google_calendar_freebusy'
                );
                $sanitized['busy'][] = $window;
                $sanitized['calendars'][$calendarHash]['busy'][] = $window;
            }
        }

        if (!empty($sanitized['errors']))
        {
            $sanitized['status_key'] = 'partial_error';
        }
        elseif (!empty($sanitized['busy']))
        {
            $sanitized['status_key'] = 'busy';
        }

        return $sanitized;
    }

    public function queryFreeBusy($accessToken, $calendarIDs, $timeMin, $timeMax, $timeZone = 'UTC', $httpClient = null)
    {
        if (!$this->_featureEnabled)
        {
            return array('status_key' => 'disabled', 'busy' => array(), 'errors' => array());
        }
        if (trim((string) $accessToken) === '')
        {
            return array('status_key' => 'reauthorize_required', 'busy' => array(), 'errors' => array('missing_access_token'));
        }

        $items = array();
        foreach ((array) $calendarIDs as $calendarID)
        {
            $calendarID = trim((string) $calendarID);
            if ($calendarID !== '')
            {
                $items[] = array('id' => $calendarID);
            }
        }
        if (empty($items))
        {
            $items[] = array('id' => 'primary');
        }

        $payload = array(
            'timeMin' => (string) $timeMin,
            'timeMax' => (string) $timeMax,
            'timeZone' => (string) $timeZone,
            'items' => $items
        );

        if ($httpClient === null)
        {
            $httpClient = array($this, 'postFreeBusyRequest');
        }

        $response = call_user_func($httpClient, self::FREEBUSY_ENDPOINT, $accessToken, $payload);
        $statusCode = isset($response['status_code']) ? (int) $response['status_code'] : 0;
        if ($statusCode === 401 || $statusCode === 403)
        {
            return array('status_key' => 'reauthorize_required', 'busy' => array(), 'errors' => array('google_auth_revoked'));
        }
        if ($statusCode < 200 || $statusCode >= 300 || !isset($response['body']))
        {
            return array('status_key' => 'error', 'busy' => array(), 'errors' => array('freebusy_request_failed'));
        }

        $body = is_array($response['body']) ? $response['body'] : json_decode((string) $response['body'], true);
        return self::sanitizeFreeBusyResponse($body);
    }

    public function postFreeBusyRequest($endpoint, $accessToken, $payload)
    {
        if (!function_exists('curl_init'))
        {
            return array('status_code' => 0, 'body' => null);
        }

        $ch = curl_init($endpoint);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array(
            'Authorization: Bearer ' . $accessToken,
            'Content-Type: application/json'
        ));
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
        $body = curl_exec($ch);
        $statusCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        return array('status_code' => $statusCode, 'body' => $body);
    }

    public function getConnectionSummaries()
    {
        if ($this->_db === null)
        {
            return array();
        }

        return $this->_db->getAllAssoc(
            'SELECT
                gc.google_calendar_connection_id,
                gc.interviewer_profile_id,
                gc.user_id,
                ip.display_name,
                ip.email,
                gc.status_key,
                gc.token_scope,
                gc.calendar_id_hash,
                gc.connected_at,
                gc.disconnected_at,
                gc.reauthorize_required_at,
                gc.last_error,
                gc.date_modified
             FROM nesp_google_calendar_connection gc
             LEFT JOIN nesp_interviewer_profile ip
                ON ip.interviewer_profile_id = gc.interviewer_profile_id
             ORDER BY ip.display_name, gc.date_modified DESC'
        );
    }

    public function queryFreeBusyForInterviewer($interviewerProfileID, $timeMin, $timeMax, $timeZone = 'UTC', $httpClient = null)
    {
        if (!$this->_featureEnabled)
        {
            return array('status_key' => 'disabled', 'busy' => array(), 'errors' => array());
        }
        if ($this->_db === null)
        {
            return array('status_key' => 'error', 'busy' => array(), 'errors' => array('missing_database_connection'));
        }

        $connection = $this->_db->getAssoc(sprintf(
            'SELECT
                interviewer_profile_id,
                user_id,
                status_key,
                encrypted_access_token,
                encrypted_calendar_id
             FROM nesp_google_calendar_connection
             WHERE interviewer_profile_id = %s
             LIMIT 1',
            $this->_db->makeQueryInteger($interviewerProfileID)
        ));
        if (empty($connection) || $connection['status_key'] === 'disconnected')
        {
            return array('status_key' => 'not_connected', 'busy' => array(), 'errors' => array());
        }
        if ($connection['status_key'] === 'reauthorize_required')
        {
            return array('status_key' => 'reauthorize_required', 'busy' => array(), 'errors' => array('reauthorize_required'));
        }
        if ($connection['status_key'] === 'error')
        {
            return array('status_key' => 'error', 'busy' => array(), 'errors' => array('connection_error'));
        }

        $accessToken = self::decryptToken($connection['encrypted_access_token']);
        if ($accessToken === false || trim((string) $accessToken) === '')
        {
            return array('status_key' => 'reauthorize_required', 'busy' => array(), 'errors' => array('missing_access_token'));
        }

        $calendarID = 'primary';
        if (trim((string) $connection['encrypted_calendar_id']) !== '')
        {
            $decryptedCalendarID = self::decryptToken($connection['encrypted_calendar_id']);
            if ($decryptedCalendarID !== false && trim((string) $decryptedCalendarID) !== '')
            {
                $calendarID = trim((string) $decryptedCalendarID);
            }
        }

        $result = $this->queryFreeBusy($accessToken, array($calendarID), $timeMin, $timeMax, $timeZone, $httpClient);
        if ($result['status_key'] === 'reauthorize_required')
        {
            $this->_db->query(sprintf(
                'UPDATE nesp_google_calendar_connection
                 SET status_key = %s,
                     reauthorize_required_at = NOW(),
                     last_error = %s,
                     date_modified = NOW()
                 WHERE interviewer_profile_id = %s',
                $this->_db->makeQueryString('reauthorize_required'),
                $this->_db->makeQueryString(implode(',', isset($result['errors']) ? $result['errors'] : array())),
                $this->_db->makeQueryInteger($interviewerProfileID)
            ));
        }

        return $result;
    }

    public function markAuthorizationRequested($interviewerProfileID, $actorUserID, $interviewerUserID = null)
    {
        if ($this->_db === null)
        {
            return false;
        }

        $interviewerProfileID = (int) $interviewerProfileID;
        if ($interviewerProfileID <= 0)
        {
            return false;
        }

        $sql = sprintf(
            'INSERT INTO nesp_google_calendar_connection
                (interviewer_profile_id, user_id, status_key, token_scope, reauthorize_required_at, created_by_user_id, modified_by_user_id, date_created, date_modified)
             VALUES
                (%s, %s, %s, %s, NOW(), %s, %s, NOW(), NOW())
             ON DUPLICATE KEY UPDATE
                user_id = VALUES(user_id),
                status_key = VALUES(status_key),
                token_scope = VALUES(token_scope),
                reauthorize_required_at = NOW(),
                disconnected_at = NULL,
                last_error = "",
                modified_by_user_id = VALUES(modified_by_user_id),
                date_modified = NOW()',
            $this->_db->makeQueryInteger($interviewerProfileID),
            $interviewerUserID === null ? 'NULL' : $this->_db->makeQueryInteger($interviewerUserID),
            $this->_db->makeQueryString('reauthorize_required'),
            $this->_db->makeQueryString(self::MINIMUM_SCOPE),
            $this->_db->makeQueryInteger($actorUserID),
            $this->_db->makeQueryInteger($actorUserID)
        );
        $this->_db->query($sql);

        return true;
    }

    public function disconnect($interviewerProfileID, $actorUserID)
    {
        if ($this->_db === null)
        {
            return false;
        }

        $sql = sprintf(
            'UPDATE nesp_google_calendar_connection
             SET status_key = "disconnected",
                 encrypted_access_token = "",
                 encrypted_refresh_token = "",
                 access_token_fingerprint = "",
                 refresh_token_fingerprint = "",
                 disconnected_at = NOW(),
                 modified_by_user_id = %s,
                 date_modified = NOW()
             WHERE interviewer_profile_id = %s',
            $this->_db->makeQueryInteger($actorUserID),
            $this->_db->makeQueryInteger($interviewerProfileID)
        );
        $this->_db->query($sql);

        return true;
    }

    private static function normalizedEncryptionKey()
    {
        $configured = trim((string) getenv('NESP_GOOGLE_CALENDAR_TOKEN_ENCRYPTION_KEY'));
        if ($configured === '')
        {
            return false;
        }

        $decoded = base64_decode($configured, true);
        if ($decoded !== false && strlen($decoded) >= 32)
        {
            return substr($decoded, 0, 32);
        }

        return substr(hash('sha256', $configured, true), 0, 32);
    }
}

?>
