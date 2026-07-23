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
    const MAXIMUM_ACTIVE_FORMS_PER_JOB = 8;
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

        $existingState = isset($session[self::FORM_SESSION_KEY][$jobOrderID])
            && is_array($session[self::FORM_SESSION_KEY][$jobOrderID])
            ? $session[self::FORM_SESSION_KEY][$jobOrderID]
            : array();
        $activeTokens = self::activeTokenStates($existingState, $now);
        $tokenHash = hash('sha256', $token);
        $activeTokens[$tokenHash] = $now;

        if (count($activeTokens) > self::MAXIMUM_ACTIVE_FORMS_PER_JOB)
        {
            asort($activeTokens, SORT_NUMERIC);
            $activeTokens = array_slice(
                $activeTokens,
                -self::MAXIMUM_ACTIVE_FORMS_PER_JOB,
                null,
                true
            );
        }

        $session[self::FORM_SESSION_KEY][$jobOrderID] = array(
            'token_hash' => $tokenHash,
            'issued_at' => $now,
            'tokens' => $activeTokens
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
        $submittedTokenHash = $submittedToken === '' ? '' : hash('sha256', $submittedToken);
        $activeTokens = self::activeTokenStates($formState, $now, false);
        $issuedAt = isset($activeTokens[$submittedTokenHash])
            ? (int) $activeTokens[$submittedTokenHash]
            : 0;

        if ($submittedToken === ''
            || $issuedAt <= 0)
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

    private static function activeTokenStates($formState, $now, $pruneExpired = true)
    {
        $tokens = array();

        if (isset($formState['tokens']) && is_array($formState['tokens']))
        {
            foreach ($formState['tokens'] as $tokenHash => $issuedAt)
            {
                $issuedAt = (int) $issuedAt;
                if ($issuedAt > 0
                    && (!$pruneExpired || ($now - $issuedAt) <= self::MAXIMUM_FORM_AGE_SECONDS))
                {
                    $tokens[(string) $tokenHash] = $issuedAt;
                }
            }
        }

        if (isset($formState['token_hash']) && isset($formState['issued_at']))
        {
            $issuedAt = (int) $formState['issued_at'];
            if ($issuedAt > 0
                && (!$pruneExpired || ($now - $issuedAt) <= self::MAXIMUM_FORM_AGE_SECONDS))
            {
                $tokens[(string) $formState['token_hash']] = $issuedAt;
            }
        }

        return $tokens;
    }
}
