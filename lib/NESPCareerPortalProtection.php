<?php

include_once(LEGACY_ROOT . '/lib/NESPApplicationQuestions.php');

class NESPCareerPortalProtection
{
    const TOKEN_FIELD = 'nesp_application_token';
    const HONEYPOT_FIELD = 'nesp_company_website';
    const FORM_SESSION_KEY = 'nespCareerPortalForms';
    const RATE_SESSION_KEY = 'nespCareerPortalRate';
    const MINIMUM_FILL_SECONDS = 2;
    const MAXIMUM_FORM_AGE_SECONDS = 7200;
    const RATE_LIMIT_WINDOW_SECONDS = 600;
    const RATE_LIMIT_ATTEMPTS = 8;

    public static function protectsJob($jobOrderID)
    {
        return NESPApplicationQuestions::hasQuestionsForJob((int) $jobOrderID);
    }

    public static function renderFields($jobOrderID, &$session, $now = null)
    {
        if (!self::protectsJob($jobOrderID))
        {
            return '';
        }

        $now = $now === null ? time() : (int) $now;
        $token = bin2hex(random_bytes(32));
        $jobOrderID = (int) $jobOrderID;

        if (!isset($session[self::FORM_SESSION_KEY]) || !is_array($session[self::FORM_SESSION_KEY]))
        {
            $session[self::FORM_SESSION_KEY] = array();
        }

        $session[self::FORM_SESSION_KEY][$jobOrderID] = array(
            'token_hash' => hash('sha256', $token),
            'issued_at' => $now
        );

        return '<input type="hidden" name="' . self::TOKEN_FIELD . '" value="'
            . htmlspecialchars($token, ENT_QUOTES | ENT_SUBSTITUTE, HTML_ENCODING) . '" />'
            . '<div aria-hidden="true" style="position:absolute;left:-10000px;width:1px;height:1px;overflow:hidden;">'
            . '<label for="' . self::HONEYPOT_FIELD . '">Leave this field empty</label>'
            . '<input type="text" name="' . self::HONEYPOT_FIELD . '" id="' . self::HONEYPOT_FIELD
            . '" value="" tabindex="-1" autocomplete="off" />'
            . '</div>';
    }

    public static function validateSubmission($jobOrderID, $postData, &$session, $clientKey, $now = null)
    {
        if (!self::protectsJob($jobOrderID))
        {
            return array('valid' => false, 'reason' => 'not_nesp_job');
        }

        $now = $now === null ? time() : (int) $now;
        $jobOrderID = (int) $jobOrderID;
        $formState = isset($session[self::FORM_SESSION_KEY][$jobOrderID])
            && is_array($session[self::FORM_SESSION_KEY][$jobOrderID])
            ? $session[self::FORM_SESSION_KEY][$jobOrderID]
            : array();
        $submittedToken = isset($postData[self::TOKEN_FIELD])
            ? trim((string) $postData[self::TOKEN_FIELD])
            : '';
        $storedTokenHash = isset($formState['token_hash']) ? (string) $formState['token_hash'] : '';

        if ($submittedToken === ''
            || $storedTokenHash === ''
            || !hash_equals($storedTokenHash, hash('sha256', $submittedToken)))
        {
            return array('valid' => false, 'reason' => 'csrf');
        }

        $honeypot = isset($postData[self::HONEYPOT_FIELD])
            ? trim((string) $postData[self::HONEYPOT_FIELD])
            : '';
        if ($honeypot !== '')
        {
            return array('valid' => false, 'reason' => 'honeypot');
        }

        $issuedAt = isset($formState['issued_at']) ? (int) $formState['issued_at'] : 0;
        $elapsed = $now - $issuedAt;
        if ($issuedAt <= 0 || $elapsed < self::MINIMUM_FILL_SECONDS)
        {
            return array('valid' => false, 'reason' => 'too_fast');
        }
        if ($elapsed > self::MAXIMUM_FORM_AGE_SECONDS)
        {
            return array('valid' => false, 'reason' => 'expired');
        }

        $rateKey = hash('sha256', trim((string) $clientKey));
        if (!isset($session[self::RATE_SESSION_KEY]) || !is_array($session[self::RATE_SESSION_KEY]))
        {
            $session[self::RATE_SESSION_KEY] = array();
        }

        $attempts = isset($session[self::RATE_SESSION_KEY][$rateKey])
            && is_array($session[self::RATE_SESSION_KEY][$rateKey])
            ? $session[self::RATE_SESSION_KEY][$rateKey]
            : array();
        $windowStart = $now - self::RATE_LIMIT_WINDOW_SECONDS;
        $attempts = array_values(array_filter($attempts, function ($timestamp) use ($windowStart) {
            return (int) $timestamp >= $windowStart;
        }));

        if (count($attempts) >= self::RATE_LIMIT_ATTEMPTS)
        {
            $session[self::RATE_SESSION_KEY][$rateKey] = $attempts;
            return array('valid' => false, 'reason' => 'rate_limited');
        }

        $attempts[] = $now;
        $session[self::RATE_SESSION_KEY][$rateKey] = $attempts;

        return array('valid' => true, 'reason' => 'valid');
    }
}
