<?php
/*
 * New England Sports Photo hosted hiring workflow foundation.
 *
 * Phase 2 keeps the hosted workflow human-reviewed and feature-flagged while
 * adding task queues, scoped interviewer views, scorecards, and staffing
 * forecast helpers. External integrations remain disabled by default.
 */

include_once(LEGACY_ROOT . '/lib/NESPVapiIntegration.php');
include_once(LEGACY_ROOT . '/lib/NESPRecruitingAds.php');
include_once(LEGACY_ROOT . '/lib/NESPGoogleCalendarFreeBusy.php');
include_once(LEGACY_ROOT . '/lib/Mailer.php');

class NESPWorkflow
{
    private $_db;

    const APPLICANT_EMAIL_FEATURE_DESCRIPTION = 'When deliberately enabled with a configured sender, automatically sends one secure role-specific questionnaire email after a new applicant has a valid email and linked job.';

    public function __construct($db = null)
    {
        $this->_db = ($db === null) ? DatabaseConnection::getInstance() : $db;
    }

    public static function getDefaultFeatureFlags()
    {
        return array(
            array('NESP_WORKFLOW_ENABLED', 'NESP Workflow', 'Craig-reviewed hiring workflow dashboard and task queues.', 0),
            array('NESP_INTERVIEWER_POOL_ENABLED', 'Interviewer Pool', 'Scoped interviewer access to assigned candidates and interviews.', 0),
            array('NESP_INTERVIEWER_AVAILABILITY_ENABLED', 'Interviewer Availability', 'Interviewer availability windows, block time, and schedule conflict checks.', 0),
            array('NESP_PRESCREEN_ENABLED', 'Prescreen Workflow', 'Craig-approved phone-screen workflow status and results.', 0),
            array('NESP_VAPI_ENABLED', 'Vapi Phone Screens', 'Disabled integration flag. No calls are placed by this module.', 0),
            array('NESP_ZOOM_ENABLED', 'Zoom Scheduling', 'Disabled integration flag. No meetings are created by this module.', 0),
            array('NESP_INTERVIEWER_ZOOM_LINKS_ENABLED', 'Interviewer Zoom Links', 'Disabled participant-link helper. No Zoom API, OAuth, meeting creation, cancellation, rescheduling, or invitations are sent.', 0),
            array('NESP_AI_REVIEW_ENABLED', 'AI Candidate Review', 'Disabled integration flag. No model calls are made by this module.', 0),
            array('NESP_STAFFING_FORECAST_ENABLED', 'Staffing Forecast', 'Seasonal staffing forecast screen and internal draft recommendations.', 0),
            array('NESP_STAFFING_DRIVE_IMPORT_ENABLED', 'Staffing Drive Import', 'Google Drive staffing schedule discovery and import controls.', 0),
            array('NESP_APPLICANT_EMAIL_ENABLED', 'Applicant Questionnaire Email', self::APPLICANT_EMAIL_FEATURE_DESCRIPTION, 0),
            NESPGoogleCalendarFreeBusy::getDefaultFeatureFlag()
        );
    }

    public static function getRequiredFeatureFlagKeys()
    {
        return array(
            'NESP_WORKFLOW_ENABLED',
            'NESP_INTERVIEWER_POOL_ENABLED',
            'NESP_INTERVIEWER_AVAILABILITY_ENABLED',
            'NESP_PRESCREEN_ENABLED',
            'NESP_VAPI_ENABLED',
            'NESP_ZOOM_ENABLED',
            'NESP_INTERVIEWER_ZOOM_LINKS_ENABLED',
            'NESP_AI_REVIEW_ENABLED',
            'NESP_STAFFING_FORECAST_ENABLED',
            'NESP_STAFFING_DRIVE_IMPORT_ENABLED',
            'NESP_APPLICANT_EMAIL_ENABLED',
            NESPGoogleCalendarFreeBusy::FEATURE_FLAG
        );
    }

    public static function getDashboardNavigation()
    {
        return array(
            array('key' => 'needsCraig', 'label' => 'Needs Craig', 'action' => 'dashboard'),
            array('key' => 'waiting', 'label' => 'Waiting', 'action' => 'waiting'),
            array('key' => 'interviews', 'label' => 'Interviews', 'action' => 'interviews'),
            array('key' => 'questionnaires', 'label' => 'Questionnaires', 'action' => 'questionnaires'),
            array('key' => 'questionSets', 'label' => 'Manage Question Sets', 'action' => 'questionSets'),
            array('key' => 'phoneScreens', 'label' => 'Phone Screens', 'action' => 'phoneScreens'),
            array('key' => 'jobAds', 'label' => 'Job Ads', 'action' => 'jobAds'),
            array('key' => 'completed', 'label' => 'Completed', 'action' => 'completed'),
            array('key' => 'staffingForecast', 'label' => 'Staffing Forecast', 'action' => 'staffingForecast'),
            array('key' => 'settings', 'label' => 'Interviewer Settings', 'action' => 'settings')
        );
    }

    public static function getDefaultWorkflowStages()
    {
        return array(
            array('new', 'New', 'New application awaiting human review.', 10, 0),
            array('needs_review', 'Needs Review', 'Craig or an authorized reviewer needs to inspect the application.', 20, 0),
            array('follow_up_needed', 'Follow Up Needed', 'Missing information or clarification is needed.', 30, 0),
            array('applicant_clarification_requested', 'Applicant Clarification Requested', 'Waiting on the applicant to clarify an application detail.', 35, 0),
            array('phone_screen_pending', 'Phone Screen Pending', 'A phone screen is approved but not completed.', 40, 0),
            array('phone_screen_complete', 'Phone Screen Complete', 'Phone-screen results are ready for human review.', 50, 0),
            array('interview_requested', 'Interview Requested', 'Craig wants an interview scheduled.', 60, 0),
            array('interview_confirmation_pending', 'Interview Confirmation Pending', 'Waiting for applicant confirmation or reschedule response.', 65, 0),
            array('interview_scheduled', 'Interview Scheduled', 'A human interview has been scheduled.', 70, 0),
            array('scorecard_pending', 'Scorecard Pending', 'An interviewer scorecard is expected.', 80, 0),
            array('scorecard_complete', 'Scorecard Complete', 'Completed scorecard is ready for Craig decision.', 85, 0),
            array('offer_review', 'Offer Review', 'Craig is reviewing a possible offer.', 90, 0),
            array('hired', 'Hired', 'Final human hiring decision recorded.', 100, 1),
            array('hold', 'Hold', 'Candidate is intentionally paused for future seasonal review.', 105, 1),
            array('not_selected', 'Not Selected', 'Final human decline decision recorded.', 110, 1),
            array('withdrawn', 'Withdrawn', 'Candidate withdrew or stopped the process.', 120, 1),
            array('declined', 'Declined', 'Legacy final human decline decision recorded.', 130, 1)
        );
    }

    public static function getDefaultIntegrationStatuses()
    {
        return array(
            array('vapi', 'Vapi Phone Screening', 'disabled', 'Optional automated phone screen — currently disabled pending final test.'),
            array('zoom', 'Manual Zoom Tracking', 'disabled', 'Manual interview tracking only. No Zoom meetings are created, updated, cancelled, or synced by this app.'),
            NESPGoogleCalendarFreeBusy::getDefaultIntegrationStatus(),
            array('ai_review', 'AI Candidate Review', 'disabled', 'Disabled in Phase 2. No model calls can run.'),
            array('email', 'Applicant Email', 'disabled', 'Disabled in Phase 2. No outbound applicant email can be sent.')
        );
    }

    public static function isIntegrationEnabledFromFlags($featureFlags, $flagKey)
    {
        foreach ($featureFlags as $flag)
        {
            if (isset($flag['flag_key']) && $flag['flag_key'] === $flagKey)
            {
                return ((int) $flag['is_enabled']) === 1;
            }
        }

        return false;
    }

    public static function getFeatureFlagForAction($action)
    {
        if ($action === null || $action === '' || $action === 'dashboard')
        {
            return 'NESP_WORKFLOW_ENABLED';
        }

        if (in_array($action, array(
            'waiting',
            'interviews',
            'completed',
            'auditLog',
            'jobAds',
            'scheduleInterview',
            'saveManualInterview',
            'cancelInterview',
            'confirmCancelInterview',
            'recordInterviewOutcome',
            'saveInterviewOutcome',
            'markManualInterviewInvitationSent'
        )))
        {
            return 'NESP_WORKFLOW_ENABLED';
        }

        if (in_array($action, array(
            'assignedCandidates',
            'assignedCandidate',
            'submitScorecard',
            'unlockScorecard',
            'interviewerAccess',
            'createInterviewer',
            'updateInterviewerSettings',
            'prepareInterviewerLogin',
            'activateInterviewerLogin',
            'suspendInterviewerLogin',
            'reactivateInterviewerLogin',
            'resetInterviewerTempPassword',
            'disableInterviewerLogin',
            'createInterviewerRoleRule',
            'deactivateInterviewerRoleRule',
            'createCandidateGrant',
            'assignInterviewer',
            'revokeCandidateGrant',
            'updateInterviewerZoomLink'
        )))
        {
            return 'NESP_INTERVIEWER_POOL_ENABLED';
        }

        if (in_array($action, array(
            'createInterviewerAvailability',
            'myAvailability',
            'setInterviewerAvailabilityStatus',
            'createInterviewerAvailabilityOverride',
            'createInterviewerBlackout'
        )))
        {
            return 'NESP_INTERVIEWER_AVAILABILITY_ENABLED';
        }

        if (in_array($action, array('staffingForecast', 'dryRunStaffingImport', 'importApprovedStaffingRows', 'createStaffingRecommendation')))
        {
            return 'NESP_STAFFING_FORECAST_ENABLED';
        }

        if (in_array($action, array(
            'questionnaires',
            'questionSets',
            'duplicateQuestionSetDraft',
            'saveQuestionSetDraft',
            'publishQuestionSetDraft',
            'archiveQuestionSet',
            'confirmQuestionnaire',
            'collectContactDetails',
            'saveContactDetails',
            'requestQuestionnaire',
            'reviewQuestionnaire',
            'markQuestionnaireInvitationCopied',
            'revokeQuestionnaireLink',
            'regenerateQuestionnaireLink',
            'assignQuestionnaireReviewer',
            'saveQuestionnaireReview'
        )))
        {
            return 'NESP_WORKFLOW_ENABLED';
        }

        if (in_array($action, array(
            'phoneScreens',
            'confirmPhoneScreen',
            'reviewPhoneScreen',
            'requestPhoneScreen',
            'cancelPhoneScreen',
            'markPhoneScreenInvitationCopied',
            'revokePhoneScreenSchedulingLink',
            'allowPhoneScreenReschedule',
            'phoneScreenAvailability',
            'savePhoneScreenAvailability',
            'createPhoneScreenAvailabilityBlock',
            'deletePhoneScreenAvailabilityBlock',
            'createPhoneScreenBlackout',
            'deletePhoneScreenBlackout',
            'savePhoneScreenReview'
        )))
        {
            return 'NESP_WORKFLOW_ENABLED';
        }

        if (in_array($action, array('saveRecruitingCampaignControl')))
        {
            return 'NESP_WORKFLOW_ENABLED';
        }

        if (in_array($action, array(
            'settings',
            'featureFlags',
            'saveFeatureFlags',
            'googleCalendarConnect',
            'googleCalendarDisconnect',
            'googleCalendarReauthorize'
        )))
        {
            return '';
        }

        return 'NESP_WORKFLOW_ENABLED';
    }

    public static function getQuestionnaireStatusLabels()
    {
        return array(
            'not_invited' => 'Questionnaire Not Invited',
            'link_ready' => 'Questionnaire Link Ready',
            'waiting' => 'Waiting for Questionnaire',
            'in_progress' => 'Questionnaire In Progress',
            'completed' => 'Questionnaire Completed',
            'expired' => 'Questionnaire Expired',
            'human_follow_up_requested' => 'Human Follow-Up Requested',
            'revoked' => 'Questionnaire Revoked'
        );
    }

    public static function getQuestionnaireDefaultExpirationHours()
    {
        return 168;
    }

    public static function getManualInterviewStatusLabels()
    {
        return array(
            'requested' => 'Interview Requested',
            'scheduled' => 'Scheduled',
            'invitation_pending' => 'Invitation Pending',
            'invitation_sent' => 'Invitation Sent',
            'confirmed' => 'Confirmed',
            'completed' => 'Completed',
            'cancelled' => 'Cancelled',
            'reschedule_needed' => 'Reschedule Needed',
            'no_show' => 'No Show'
        );
    }

    public static function getManualInterviewOutcomeLabels()
    {
        return array(
            'completed' => 'Completed',
            'no_show' => 'No Show',
            'follow_up_needed' => 'Follow-up Needed',
            'advance_to_next_step' => 'Advance to Next Step',
            'declined_by_applicant' => 'Declined by Applicant',
            'not_moving_forward' => 'Not Moving Forward'
        );
    }

    public static function validateZoomApplicantJoinURL($url)
    {
        $url = trim((string) $url);
        if ($url === '' || strlen($url) > 1000)
        {
            return array('ok' => false, 'error' => 'Enter the applicant Zoom join link.');
        }

        $parts = parse_url($url);
        if ($parts === false || empty($parts['scheme']) || strtolower($parts['scheme']) !== 'https' || empty($parts['host']))
        {
            return array('ok' => false, 'error' => 'Use a secure https Zoom join link.');
        }

        $host = strtolower($parts['host']);
        if (!preg_match('/(^|\.)zoom\.(us|com)$/', $host))
        {
            return array('ok' => false, 'error' => 'Use an official Zoom meeting link.');
        }

        $path = isset($parts['path']) ? strtolower($parts['path']) : '';
        $query = isset($parts['query']) ? strtolower($parts['query']) : '';
        if (preg_match('#(^|/)start(/|$)#', $path) || strpos($query, 'start_url=') !== false || strpos($query, 'zak=') !== false || preg_match('/(^|[?&])zak(&|=|$)/', '?' . $query))
        {
            return array('ok' => false, 'error' => 'Paste the applicant join link, not the Zoom host/start link.');
        }

        if (!preg_match('#/(j|my)/#', $path))
        {
            return array('ok' => false, 'error' => 'The Zoom link should look like an applicant meeting join URL.');
        }

        return array('ok' => true, 'url' => $url);
    }

    public static function maskZoomURLForAudit($url)
    {
        $parts = parse_url(trim((string) $url));
        if ($parts === false || empty($parts['host']))
        {
            return '';
        }

        $path = isset($parts['path']) ? $parts['path'] : '';
        if (preg_match('#/j/([0-9]{3})([0-9]+)([0-9]{2})#', $path, $matches))
        {
            $path = str_replace($matches[1] . $matches[2] . $matches[3], $matches[1] . '...' . $matches[3], $path);
        }

        return strtolower($parts['host']) . $path;
    }

    public static function isInterviewerZoomLinksEnabledByDefault()
    {
        $value = getenv('NESP_INTERVIEWER_ZOOM_LINKS_ENABLED');
        if ($value === false)
        {
            return false;
        }

        return in_array(strtolower(trim((string) $value)), array('1', 'true', 'yes', 'on'), true);
    }

    public static function isApplicantEmailDeliveryReady($featureEnabled, $mailerConfigured, $fromAddress)
    {
        return (bool) $featureEnabled
            && (string) $mailerConfigured === '1'
            && defined('MAIL_MAILER')
            && MAIL_MAILER !== MAILER_MODE_DISABLED
            && filter_var(trim((string) $fromAddress), FILTER_VALIDATE_EMAIL) !== false;
    }

    /**
     * The first enablement of applicant email requires a distinct confirmation
     * in addition to the feature-flag checkbox. Existing enabled settings may
     * still be saved with the rest of the feature flags.
     */
    public static function canEnableApplicantEmail($wasEnabled, $requestedEnabled, $confirmed)
    {
        if (!(bool) $requestedEnabled || (bool) $wasEnabled)
        {
            return true;
        }

        return (string) $confirmed === 'confirm';
    }

    public static function getFeatureFlagDescription($flagKey, $storedDescription)
    {
        if ((string) $flagKey === 'NESP_APPLICANT_EMAIL_ENABLED')
        {
            return self::APPLICANT_EMAIL_FEATURE_DESCRIPTION;
        }

        return (string) $storedDescription;
    }

    public static function buildManualInterviewInvitationCopy($firstName, $roleTitle, $scheduledStart, $durationMinutes, $timezone, $joinURL)
    {
        $firstName = trim((string) $firstName);
        $roleTitle = trim((string) $roleTitle);
        $timezone = trim((string) $timezone);
        if ($firstName === '')
        {
            $firstName = '[First Name]';
        }
        if ($roleTitle === '')
        {
            $roleTitle = '[Role]';
        }
        if ($timezone === '')
        {
            $timezone = 'America/New_York';
        }

        $timestamp = strtotime($scheduledStart);
        $dateText = $timestamp === false ? '[Date]' : date('l, F j, Y', $timestamp);
        $timeText = $timestamp === false ? '[Time]' : date('g:i A', $timestamp);
        $durationMinutes = max(5, min(240, (int) $durationMinutes));

        return 'Hi ' . $firstName . ', we would like to schedule your interview for the ' . $roleTitle . ' position with New England Sports Photo.' . "\n\n"
            . 'Date: ' . $dateText . "\n"
            . 'Time: ' . $timeText . "\n"
            . 'Timezone: ' . $timezone . "\n"
            . 'Duration: ' . $durationMinutes . ' minutes' . "\n"
            . 'Interview link: ' . trim((string) $joinURL) . "\n\n"
            . 'If you need to reschedule, reply to this message and a member of the NESP team will help; no automated hiring decision will be made from this interview.';
    }

    public static function buildManualInterviewStoredInvitationCopy($firstName, $roleTitle, $scheduledStart, $durationMinutes, $timezone)
    {
        return self::buildManualInterviewInvitationCopy(
            $firstName,
            $roleTitle,
            $scheduledStart,
            $durationMinutes,
            $timezone,
            '[Generate a fresh secure interview link in the NESP dashboard before copying this invitation.]'
        );
    }

    public static function generateQuestionnaireToken()
    {
        if (function_exists('random_bytes'))
        {
            return rtrim(strtr(base64_encode(random_bytes(32)), '+/', '-_'), '=');
        }

        return hash('sha256', uniqid('', true) . mt_rand());
    }

    public static function questionnaireTokenHash($token)
    {
        return hash('sha256', self::normalizeQuestionnaireToken($token));
    }

    public static function interviewParticipantTokenHash($token)
    {
        return hash('sha256', self::normalizeQuestionnaireToken($token));
    }

    public static function getInterviewParticipantLink($token)
    {
        $baseURL = defined('NESP_PUBLIC_BASE_URL') ? trim((string) NESP_PUBLIC_BASE_URL) : '';
        if ($baseURL === '')
        {
            $baseURL = 'https://careers.nesportsphoto.com';
        }

        return rtrim($baseURL, '/') . '/modules/nesp/interviewParticipantLink.php?t=' . rawurlencode(self::normalizeQuestionnaireToken($token));
    }

    /**
     * Some mail clients preserve an escaped terminal newline when opening a
     * plain-text link. The opaque token format never contains backslashes, so
     * removing only those terminal escape sequences is safe and idempotent.
     */
    public static function normalizeQuestionnaireToken($token)
    {
        $token = trim((string) $token);
        while (substr($token, -2) === '\\n' || substr($token, -2) === '\\r')
        {
            $token = substr($token, 0, -2);
        }

        return trim($token);
    }

    /**
     * This is intentionally a one-way value: it is the unique database lock
     * for one active questionnaire per candidate/job pair.
     */
    public static function questionnaireActiveCandidateJobKey($candidateID, $jobOrderID)
    {
        return hash('sha256', 'nesp-questionnaire-active:' . (int) $candidateID . ':' . (int) $jobOrderID);
    }

    public static function getQuestionnaireLink($token)
    {
        $baseURLValue = getenv('NESP_PUBLIC_BASE_URL');
        $baseURL = rtrim($baseURLValue === false ? '' : trim($baseURLValue), '/');
        if ($baseURL === '')
        {
            $baseURL = 'https://careers.nesportsphoto.com';
        }

        return $baseURL . '/modules/nesp/screeningQuestionnaire.php?t=' . rawurlencode($token);
    }

    public static function buildQuestionnaireInvitationCopy($firstName, $roleTitle, $link)
    {
        $firstName = trim($firstName);
        $roleTitle = trim($roleTitle);
        if ($firstName === '')
        {
            $firstName = '[First Name]';
        }
        if ($roleTitle === '')
        {
            $roleTitle = '[Role]';
        }

        return 'Hi ' . $firstName . ', thank you for applying for the ' . $roleTitle . ' position with New England Sports Photo. Please complete this brief screening questionnaire using the secure link below. It should take approximately 5-10 minutes. Your answers will be reviewed by a member of our hiring team, and no automated hiring decision will be made.' . "\n\n" . $link;
    }

    public static function evaluateQuestionnaireTokenState($token, $row, $nowTimestamp)
    {
        if (trim((string) $token) === '' || empty($row))
        {
            return 'invalid';
        }
        if (!hash_equals((string) $row['token_hash'], self::questionnaireTokenHash($token)))
        {
            return 'invalid';
        }
        if (!empty($row['token_revoked_at']))
        {
            return 'revoked';
        }
        if (!empty($row['token_expires_at']) && strtotime($row['token_expires_at']) < (int) $nowTimestamp)
        {
            return 'expired';
        }
        if (!empty($row['submitted_at']) || (isset($row['status_key']) && $row['status_key'] === 'completed'))
        {
            return 'submitted';
        }
        return 'valid';
    }

    public static function getQuestionnaireQuestionSets()
    {
        return array(
            'photography_assistant_poser' => array(
                'label' => 'Field Staff Pre-Interview',
                'match' => array('table greeter', 'field assistant', 'field staff', 'assistant', 'poser'),
                'intro' => 'Field Staff First - Table/Field Assistant Pre-Interview. Fall 2026 and Spring 2027 Pre-Interview Information and Survey. Please complete this Field Staff questionnaire before your Zoom meeting. Answer each question as accurately as you can, then select Submit so your responses are recorded. Assignments take place throughout New England, including Massachusetts, New Hampshire, Rhode Island, Connecticut, and Vermont.',
                'questions' => array(
                    array('key' => 'confirmed_email', 'label' => 'Email address.', 'type' => 'text', 'required' => true),
                    array('key' => 'confirmed_name', 'label' => 'Your name.', 'type' => 'text', 'required' => true),
                    array('key' => 'new_england_spring_availability', 'label' => 'Are you available for seasonal assignments in New England during September-November and April-June? Events are in Massachusetts, New Hampshire, Rhode Island, Connecticut, and Vermont.', 'type' => 'textarea', 'required' => true),
                    array('key' => 'position_for_zoom', 'label' => 'Which position are you scheduling a Zoom meeting for?', 'type' => 'single_choice', 'choices' => array('Table Greeter / Field Assistant'), 'required' => true),
                    array('key' => 'current_date_time', 'label' => 'Current date and time.', 'type' => 'text', 'required' => true),
                    array('key' => 'primary_work', 'label' => 'Primary work, if any.', 'type' => 'textarea', 'required' => true),
                    array('key' => 'talking_with_families', 'label' => 'Are you comfortable talking to and answering questions from kids and adults?', 'type' => 'textarea', 'required' => true),
                    array('key' => 'organization_and_instructions', 'label' => 'Are you comfortable staying organized, checking in paperwork, and giving players, coaches, and parents the instructions they need at events?', 'type' => 'textarea', 'required' => true),
                    array('key' => 'youth_age_comfort', 'label' => 'Do you have experience working with, or are you comfortable working with, kids from kindergarten through high school age?', 'type' => 'textarea', 'required' => true),
                    array('key' => 'posing_and_gear_direction', 'label' => 'Are you comfortable talking to, giving directions to, and helping players from kindergarten through high school get into the correct picture positions while making sure gear, equipment, and uniforms look good?', 'type' => 'textarea', 'required' => true),
                    array('key' => 'driver_license_vehicle', 'label' => 'This role requires driving to youth sports league locations. Do you have a valid driver\'s license and reliable access to a personal vehicle for these assignments?', 'type' => 'single_choice', 'choices' => array('Yes', 'No', 'Other'), 'required' => true),
                    array('key' => 'travel_distance', 'label' => 'Events are around New England. Most events average 45-60 minutes of travel time depending on location, but some available events could be further away. Select all travel options you are willing to accept.', 'type' => 'multiple_choice', 'choices' => array('I can do at least 60 minutes of travel time.', 'I can also do around 90 minutes of travel time.', 'I\'d be willing to travel further than 90 minutes.'), 'required' => true),
                    array('key' => 'field_staff_interest', 'label' => 'Briefly write what interested you in looking into work at picture day events with NESP.', 'type' => 'textarea', 'required' => true)
                )
            ),
            'weekend_sports_photographer' => array(
                'label' => 'Photographer Pre-Interview',
                'match' => array('weekend sports photographer', 'staff photographer', 'freelance photographer', 'sports photographer', 'photographer'),
                'intro' => 'Photographer Pre-Interview - Staff or Freelance. Fall 2026 and Spring 2027 Pre-Interview Information and Survey. Please complete this Photographer questionnaire before your Zoom meeting. Answer each question as accurately as you can, then select Submit so your responses are recorded. Assignments take place throughout New England, including Massachusetts, New Hampshire, Rhode Island, Connecticut, and Vermont.',
                'questions' => array(
                    array('key' => 'confirmed_email', 'label' => 'Email address.', 'type' => 'text', 'required' => true),
                    array('key' => 'confirmed_name', 'label' => 'Your name.', 'type' => 'text', 'required' => true),
                    array('key' => 'new_england_spring_availability', 'label' => 'Are you available for seasonal assignments in New England during September-November and April-June? Events are in Massachusetts, New Hampshire, Rhode Island, Connecticut, and Vermont.', 'type' => 'textarea', 'required' => true),
                    array('key' => 'position_for_zoom', 'label' => 'Which position are you scheduling a Zoom meeting for?', 'type' => 'single_choice', 'choices' => array('Photographer'), 'required' => true),
                    array('key' => 'current_date_time', 'label' => 'Current date and time.', 'type' => 'text', 'required' => true),
                    array('key' => 'primary_work', 'label' => 'Primary work, if any.', 'type' => 'textarea', 'required' => true),
                    array('key' => 'last_five_photography_events', 'label' => 'Last 5 photography events. If fewer than 5, list what you have been on.', 'type' => 'textarea', 'required' => true),
                    array('key' => 'portfolio_link', 'label' => 'Your online portfolio or website link, if applicable.', 'type' => 'text', 'required' => false),
                    array('key' => 'linkedin_link', 'label' => 'Your LinkedIn link, if applicable.', 'type' => 'text', 'required' => false),
                    array('key' => 'years_freelancing', 'label' => 'How many years have you been freelancing?', 'type' => 'text', 'required' => true),
                    array('key' => 'camera_bodies', 'label' => 'List your camera body or bodies make and model. Example: Canon 5D Mark IV.', 'type' => 'textarea', 'required' => true),
                    array('key' => 'lenses', 'label' => 'List your lens or lenses make, focal length, and aperture range. Example: Tamron 28-75 f2.8.', 'type' => 'textarea', 'required' => true),
                    array('key' => 'owns_flash', 'label' => 'Do you own a flash?', 'type' => 'yes_no', 'required' => true),
                    array('key' => 'indoor_lighting_experience', 'label' => 'Do you have experience with indoor photography lighting using 2 or more monolights?', 'type' => 'yes_no', 'required' => true),
                    array('key' => 'driver_license_vehicle', 'label' => 'This role requires driving to youth sports league locations. Do you have a valid driver\'s license and reliable access to a personal vehicle for these assignments?', 'type' => 'single_choice', 'choices' => array('Yes', 'No', 'Other'), 'required' => true),
                    array('key' => 'travel_distance', 'label' => 'Most events involve 45-75 minutes of travel time depending on location, though some available events may be farther away. Select all travel ranges you would be comfortable with.', 'type' => 'multiple_choice', 'choices' => array('I can do at least 60 minutes of travel time.', 'I can also do around 90 minutes of travel time.', 'I\'d be willing to travel further than 90 minutes.'), 'required' => true),
                    array('key' => 'early_weekend_mornings', 'label' => 'Picture days start early on weekends, including travel time. Which statement best describes your availability?', 'type' => 'single_choice', 'choices' => array('I understand that picture days can start early and am willing to get up early to arrive on time.', 'I would rather not have to get up early on weekends.', 'I do not wake up before lunchtime on weekends.'), 'required' => true),
                    array('key' => 'youth_age_comfort', 'label' => 'Do you have experience working with, or are you comfortable working with, kids from kindergarten through high school age?', 'type' => 'textarea', 'required' => true),
                    array('key' => 'early_arrival_plan', 'label' => 'If your first scheduled group begins at 7:30 AM, what time would you plan to arrive at the event location and why?', 'type' => 'textarea', 'required' => true),
                    array('key' => 'photographer_interest', 'label' => 'So, what about being a youth sports team and portrait photographer interested you?', 'type' => 'textarea', 'required' => true)
                )
            ),
            'school_photographer' => array(
                'label' => 'School Photographer',
                'match' => array('school photographer'),
                'questions' => array(
                    array('key' => 'weekday_morning_availability', 'label' => 'What weekday morning availability do you have?', 'type' => 'textarea', 'required' => true),
                    array('key' => 'transportation', 'label' => 'Do you have reliable transportation?', 'type' => 'textarea', 'required' => true),
                    array('key' => 'camera_experience', 'label' => 'Describe your camera experience.', 'type' => 'textarea', 'required' => true),
                    array('key' => 'children_experience', 'label' => 'Describe your experience working with children.', 'type' => 'textarea', 'required' => true),
                    array('key' => 'high_volume_comfort', 'label' => 'Are you comfortable with repetitive posing and high-volume photography?', 'type' => 'textarea', 'required' => true),
                    array('key' => 'travel_range', 'label' => 'What travel range can you reliably cover?', 'type' => 'textarea', 'required' => true),
                    array('key' => 'start_date', 'label' => 'What is your earliest available start date?', 'type' => 'text', 'required' => true),
                    array('key' => 'pay_expectations', 'label' => 'What hourly pay range are you seeking?', 'type' => 'text', 'required' => true)
                )
            ),
            'customer_service' => array(
                'label' => 'Customer Service',
                'match' => array('customer service'),
                'questions' => array(
                    array('key' => 'weekday_availability', 'label' => 'What weekday availability do you have?', 'type' => 'textarea', 'required' => true),
                    array('key' => 'phone_email_support', 'label' => 'Describe your phone or email support experience.', 'type' => 'textarea', 'required' => true),
                    array('key' => 'conflict_resolution', 'label' => 'Describe your conflict-resolution experience.', 'type' => 'textarea', 'required' => true),
                    array('key' => 'computer_comfort', 'label' => 'How comfortable are you using computers and office systems?', 'type' => 'textarea', 'required' => true),
                    array('key' => 'work_preference', 'label' => 'Do you prefer remote or in-office work?', 'type' => 'textarea', 'required' => true),
                    array('key' => 'start_date', 'label' => 'What is your earliest available start date?', 'type' => 'text', 'required' => true),
                    array('key' => 'pay_expectations', 'label' => 'What hourly pay range are you seeking?', 'type' => 'text', 'required' => true)
                )
            ),
            'packing_production' => array(
                'label' => 'Packing / Production',
                'match' => array('packing', 'production'),
                'questions' => array(
                    array('key' => 'weekday_availability', 'label' => 'What weekday availability do you have?', 'type' => 'textarea', 'required' => true),
                    array('key' => 'standing_repetitive_work', 'label' => 'Are you able to stand and perform repetitive work?', 'type' => 'textarea', 'required' => true),
                    array('key' => 'attention_to_detail', 'label' => 'Describe your attention to detail.', 'type' => 'textarea', 'required' => true),
                    array('key' => 'lifting_ability', 'label' => 'What lifting ability should we know about for packing or production work?', 'type' => 'textarea', 'required' => true),
                    array('key' => 'methuen_transportation', 'label' => 'Do you have transportation to Methuen?', 'type' => 'textarea', 'required' => true),
                    array('key' => 'start_date', 'label' => 'What is your earliest available start date?', 'type' => 'text', 'required' => true),
                    array('key' => 'pay_expectations', 'label' => 'What hourly pay range are you seeking?', 'type' => 'text', 'required' => true)
                )
            ),
            'scheduler_office_support' => array(
                'label' => 'Scheduler / Office Support',
                'match' => array('scheduler', 'office support', 'administrative'),
                'questions' => array(
                    array('key' => 'weekday_availability', 'label' => 'What weekday availability do you have?', 'type' => 'textarea', 'required' => true),
                    array('key' => 'scheduling_admin', 'label' => 'Describe your scheduling or administrative experience.', 'type' => 'textarea', 'required' => true),
                    array('key' => 'phone_email', 'label' => 'Describe your phone and email experience.', 'type' => 'textarea', 'required' => true),
                    array('key' => 'spreadsheet_computer', 'label' => 'Describe your spreadsheet and computer experience.', 'type' => 'textarea', 'required' => true),
                    array('key' => 'independent_work', 'label' => 'Are you comfortable working independently?', 'type' => 'textarea', 'required' => true),
                    array('key' => 'start_date', 'label' => 'What is your earliest available start date?', 'type' => 'text', 'required' => true),
                    array('key' => 'pay_expectations', 'label' => 'What hourly pay range are you seeking?', 'type' => 'text', 'required' => true)
                )
            ),
            'sales_representative' => array(
                'label' => 'Sales Representative',
                'match' => array('sales representative', 'sales'),
                'questions' => array(
                    array('key' => 'sales_experience', 'label' => 'Describe your sales experience.', 'type' => 'textarea', 'required' => true),
                    array('key' => 'sports_school_contacts', 'label' => 'Do you have youth sports or school contacts relevant to this role?', 'type' => 'textarea', 'required' => true),
                    array('key' => 'territory_travel', 'label' => 'What territory or travel availability do you have?', 'type' => 'textarea', 'required' => true),
                    array('key' => 'commission_comfort', 'label' => 'Are you comfortable with commission-based compensation structures?', 'type' => 'textarea', 'required' => true),
                    array('key' => 'start_date', 'label' => 'What is your earliest available start date?', 'type' => 'text', 'required' => true),
                    array('key' => 'pay_expectations', 'label' => 'What pay range are you seeking?', 'type' => 'text', 'required' => true)
                )
            )
        );
    }

    public static function getQuestionnaireSetForRole($roleTitle)
    {
        $roleTitle = strtolower((string) $roleTitle);
        $sets = self::getQuestionnaireQuestionSets();
        foreach ($sets as $key => $set)
        {
            foreach ($set['match'] as $match)
            {
                if (strpos($roleTitle, $match) !== false)
                {
                    $set['key'] = $key;
                    return $set;
                }
            }
        }

        $sets['weekend_sports_photographer']['key'] = 'weekend_sports_photographer';
        return $sets['weekend_sports_photographer'];
    }

    public static function getQuestionnaireQuestionsForSet($questionSetKey)
    {
        $sets = self::getQuestionnaireQuestionSets();
        if (!isset($sets[$questionSetKey]))
        {
            $questionSetKey = 'weekend_sports_photographer';
        }

        $questions = $sets[$questionSetKey]['questions'];
        $questions[] = array(
            'key' => 'anything_else',
            'label' => 'Anything else you would like us to know?',
            'type' => 'textarea',
            'required' => false
        );
        return $questions;
    }

    public static function getQuestionnaireIntroForSet($questionSetKey)
    {
        $sets = self::getQuestionnaireQuestionSets();
        if (!isset($sets[$questionSetKey]))
        {
            $questionSetKey = 'weekend_sports_photographer';
        }

        return isset($sets[$questionSetKey]['intro']) ? $sets[$questionSetKey]['intro'] : '';
    }

    public static function normalizeQuestionnaireSnapshotQuestions($questions)
    {
        $clean = array();
        $seen = array();
        $sortOrder = 10;
        foreach ((array) $questions as $question)
        {
            $key = isset($question['key']) ? preg_replace('/[^a-z0-9_]+/', '_', strtolower(trim((string) $question['key']))) : '';
            $label = isset($question['label']) ? trim((string) $question['label']) : '';
            if ($key === '' || $label === '' || isset($seen[$key]))
            {
                continue;
            }
            $type = isset($question['type']) ? trim((string) $question['type']) : 'textarea';
            if ($type === 'select')
            {
                $type = 'single_choice';
            }
            if ($type === 'checkbox')
            {
                $type = 'multiple_choice';
            }
            if (!in_array($type, array('text', 'textarea', 'yes_no', 'single_choice', 'multiple_choice', 'number'), true))
            {
                $type = 'textarea';
            }
            $choices = isset($question['choices']) && is_array($question['choices']) ? array_values($question['choices']) : array();
            $clean[] = array(
                'key' => substr($key, 0, 96),
                'label' => substr($label, 0, 255),
                'help' => isset($question['help']) ? substr(trim((string) $question['help']), 0, 1000) : '',
                'type' => $type,
                'required' => !empty($question['required']),
                'choices' => $choices,
                'sort_order' => isset($question['sort_order']) ? (int) $question['sort_order'] : $sortOrder
            );
            $seen[$key] = true;
            $sortOrder += 10;
        }

        return $clean;
    }

    public static function validateQuestionnaireAnswers($questions, $answers)
    {
        $clean = array();
        $errors = array();
        foreach ($questions as $question)
        {
            $key = $question['key'];
            $value = isset($answers[$key]) ? $answers[$key] : '';
            if (is_array($value))
            {
                $value = implode(', ', array_map('trim', $value));
            }
            $value = trim((string) $value);
            if (!empty($question['required']) && $value === '')
            {
                $errors[] = $key;
            }
            $clean[$key] = substr($value, 0, 4000);
        }

        return array('ok' => count($errors) === 0, 'answers' => $clean, 'missing' => $errors);
    }

    public static function getQueueDefinitions()
    {
        return array(
            'needsCraig' => array(
                'title' => 'Needs Craig Now',
                'empty' => 'No Craig decisions are waiting.',
                'stageKeys' => array(
                    'new',
                    'needs_review',
                    'phone_screen_complete',
                    'interview_requested',
                    'scorecard_complete',
                    'offer_review'
                )
            ),
            'waitingApplicant' => array(
                'title' => 'Waiting on Applicant',
                'empty' => 'No applicant follow-up is waiting.',
                'stageKeys' => array(
                    'follow_up_needed',
                    'applicant_clarification_requested',
                    'phone_screen_pending',
                    'interview_confirmation_pending'
                )
            ),
            'waitingInterviewer' => array(
                'title' => 'Waiting on Interviewer',
                'empty' => 'No interviewer tasks are waiting.',
                'stageKeys' => array(
                    'interview_scheduled',
                    'scorecard_pending'
                )
            ),
            'upcomingInterviews' => array(
                'title' => 'Upcoming Interviews',
                'empty' => 'No upcoming interviews are scheduled.'
            ),
            'recentlyCompleted' => array(
                'title' => 'Recently Completed',
                'empty' => 'No recent completed decisions.',
                'stageKeys' => array(
                    'scorecard_complete',
                    'hired',
                    'hold',
                    'not_selected',
                    'withdrawn',
                    'declined'
                )
            )
        );
    }

    public static function getDefaultScorecardQuestions()
    {
        return array(
            array('key' => 'reliability', 'label' => 'Reliability and schedule fit', 'type' => 'rating'),
            array('key' => 'people_skills', 'label' => 'Comfort with athletes, families, coaches, and staff', 'type' => 'rating'),
            array('key' => 'role_fit', 'label' => 'Role-specific skills or trainability', 'type' => 'rating'),
            array('key' => 'notes', 'label' => 'Factual notes from the conversation', 'type' => 'textarea')
        );
    }

    public static function getDefaultAssignmentRuleExamples()
    {
        return array(
            array(
                'role_match_text' => 'photographer',
                'assignment_mode' => 'suggest_only',
                'notes' => 'Suggested routing for freelance and staff photographer applicants. Craig still approves any real interviewer grant.'
            ),
            array(
                'role_match_text' => 'customer service',
                'assignment_mode' => 'suggest_only',
                'notes' => 'Use for customer-service applicants; no automatic email or status change.'
            ),
            array(
                'role_match_text' => 'table greeter',
                'assignment_mode' => 'suggest_only',
                'notes' => 'Use for on-site table and field assistant applicants.'
            )
        );
    }

    public static function getDefaultAvailabilityTemplate()
    {
        return array(
            'timezone' => 'America/New_York',
            'timezone_label' => 'Eastern Time',
            'slot_minutes' => 30,
            'buffer_minutes' => 15,
            'notes' => 'Internal availability only. Applicant self-booking and Zoom creation remain disabled until separately approved.'
        );
    }

    public static function getInterviewerJobRoleOptions()
    {
        return array(
            array('joborder_id' => 41001, 'role_key' => 'customer_service', 'label' => 'Customer Service', 'default_duration_minutes' => 30),
            array('joborder_id' => 41002, 'role_key' => 'staff_photographer', 'label' => 'Staff Photographer', 'default_duration_minutes' => 25),
            array('joborder_id' => 41003, 'role_key' => 'freelance_photographer', 'label' => 'Freelance Photographer', 'default_duration_minutes' => 30),
            array('joborder_id' => 41005, 'role_key' => 'field_assistant', 'label' => 'Field Assistant', 'default_duration_minutes' => 20)
        );
    }

    public static function getInterviewerAccountStates()
    {
        return array(
            'profile_created' => 'Profile Created',
            'email_needs_confirmation' => 'Email Needs Confirmation',
            'ready_for_account_creation' => 'Ready for Account Creation',
            'account_prepared' => 'Account Prepared',
            'temporary_password_set' => 'Temporary Password Set',
            'awaiting_craig_activation' => 'Awaiting Craig Activation',
            'active' => 'Active',
            'suspended' => 'Suspended',
            'deactivated' => 'Deactivated',
            'permanently_disabled' => 'Permanently Disabled'
        );
    }

    public static function getApprovedRealInterviewerSeedProfiles()
    {
        return array(
            array(
                'display_name' => 'Suthir',
                'email' => 'suthir@nesportsphoto.com',
                'role_group' => 'Photographer interviews',
                'account_state_key' => 'ready_for_account_creation',
                'is_active' => 0,
                'approved_joborder_ids' => array(41002, 41003),
                'email_warning' => ''
            ),
            array(
                'display_name' => 'Brandon',
                'email' => 'brandon@nesportsphoto.com',
                'role_group' => 'Table, greeter, and field-support interviews',
                'account_state_key' => 'email_needs_confirmation',
                'is_active' => 0,
                'approved_joborder_ids' => array(41005),
                'email_warning' => 'Please confirm that brandon@nesportsphoto.com is the correct email address.'
            ),
            array(
                'display_name' => 'Nate',
                'email' => 'nate@nesportsphoto.com',
                'role_group' => 'Profile only until Craig assigns approved job roles',
                'account_state_key' => 'profile_created',
                'is_active' => 0,
                'approved_joborder_ids' => array(),
                'email_warning' => ''
            )
        );
    }

    public static function findSchedulingConflicts($interviewer, $approvedJobIDs, $availabilityBlocks, $blackouts, $existingInterviews, $jobOrderID, $startTime, $endTime, $availabilityOverrides = array(), $now = null, $externalBusyWindows = array())
    {
        $conflicts = array();
        $jobOrderID = (int) $jobOrderID;
        $timezoneName = isset($interviewer['timezone']) && trim($interviewer['timezone']) !== ''
            ? trim($interviewer['timezone'])
            : 'America/New_York';
        $timezone = self::safeDateTimeZone($timezoneName);
        $startDateTime = self::localDateTimeFromScheduleValue($startTime, $timezone);
        $endDateTime = self::localDateTimeFromScheduleValue($endTime, $timezone);
        if ($startDateTime === null || $endDateTime === null || $startDateTime->getTimestamp() >= $endDateTime->getTimestamp())
        {
            return array('Invalid interview time.');
        }
        $start = $startDateTime->getTimestamp();
        $end = $endDateTime->getTimestamp();

        if (empty($interviewer) || (int) $interviewer['is_active'] !== 1)
        {
            $conflicts[] = 'Interviewer account is not active.';
        }
        if (isset($interviewer['availability_status_key']) && $interviewer['availability_status_key'] === 'closed')
        {
            $conflicts[] = 'Interviewer is closed for interviews.';
        }
        if (!in_array($jobOrderID, array_map('intval', $approvedJobIDs)))
        {
            $conflicts[] = 'Interviewer is not approved for this job role.';
        }
        $minNotice = isset($interviewer['min_notice_minutes']) ? max(0, (int) $interviewer['min_notice_minutes']) : 0;
        if ($minNotice > 0)
        {
            $nowDateTime = self::scheduleNowDateTime($now, $timezone);
            if (($startDateTime->getTimestamp() - $nowDateTime->getTimestamp()) < ($minNotice * 60))
            {
                $conflicts[] = 'Requested time is inside the minimum notice window.';
            }
        }

        $insideBlock = false;
        $hasDateSpecificAvailability = false;
        $hasAllDayAvailability = false;
        foreach ($availabilityOverrides as $override)
        {
            if (isset($override['is_active']) && (int) $override['is_active'] !== 1)
            {
                continue;
            }
            $overrideTimezone = self::safeDateTimeZone(isset($override['timezone']) ? $override['timezone'] : $timezoneName);
            $overrideStart = $startDateTime->setTimezone($overrideTimezone);
            if (!isset($override['override_date']) || $overrideStart->format('Y-m-d') !== $override['override_date'])
            {
                continue;
            }
            $overrideType = isset($override['override_type_key']) ? $override['override_type_key'] : '';
            if ($overrideType === 'unavailable' || $overrideType === 'unavailable_all_day')
            {
                $conflicts[] = 'Requested date is marked unavailable.';
                break;
            }
            if ($overrideType === 'available_all_day')
            {
                $insideBlock = true;
                $hasAllDayAvailability = true;
                continue;
            }
            if ($overrideType === 'available')
            {
                $hasDateSpecificAvailability = true;
                if (self::scheduleWindowContains($startDateTime, $endDateTime, $override['override_date'], $override['start_time'], $override['end_time'], $overrideTimezone))
                {
                    $insideBlock = true;
                }
            }
        }

        if (!$insideBlock && !$hasDateSpecificAvailability && !$hasAllDayAvailability)
        {
            foreach ($availabilityBlocks as $block)
            {
                if ((int) $block['is_active'] !== 1)
                {
                    continue;
                }
                $blockTimezone = self::safeDateTimeZone(isset($block['timezone']) ? $block['timezone'] : $timezoneName);
                $blockStart = $startDateTime->setTimezone($blockTimezone);
                if ($block['weekday_key'] !== $blockStart->format('l'))
                {
                    continue;
                }
                if (self::scheduleWindowContains($startDateTime, $endDateTime, $blockStart->format('Y-m-d'), $block['start_time'], $block['end_time'], $blockTimezone))
                {
                    $insideBlock = true;
                    break;
                }
            }
        }
        if (!$insideBlock)
        {
            $conflicts[] = 'Requested time is outside available blocks.';
        }

        foreach ($blackouts as $blackout)
        {
            $blackoutTimezone = self::safeDateTimeZone(isset($blackout['timezone']) ? $blackout['timezone'] : $timezoneName);
            if (isset($blackout['is_all_day']) && (int) $blackout['is_all_day'] === 1)
            {
                $blackoutStartDate = self::localDateTimeFromScheduleValue(substr($blackout['starts_at'], 0, 10) . ' 00:00:00', $blackoutTimezone);
                $blackoutEndDate = self::localDateTimeFromScheduleValue(substr($blackout['ends_at'], 0, 10) . ' 23:59:59', $blackoutTimezone);
                if ($blackoutStartDate !== null && $blackoutEndDate !== null
                    && $startDateTime->setTimezone($blackoutTimezone)->getTimestamp() <= $blackoutEndDate->getTimestamp()
                    && $endDateTime->setTimezone($blackoutTimezone)->getTimestamp() >= $blackoutStartDate->getTimestamp())
                {
                    $conflicts[] = 'Requested time overlaps blocked time.';
                    break;
                }
                continue;
            }
            $blackoutStart = self::localDateTimeFromScheduleValue($blackout['starts_at'], $blackoutTimezone);
            $blackoutEnd = self::localDateTimeFromScheduleValue($blackout['ends_at'], $blackoutTimezone);
            if ($blackoutStart !== null && $blackoutEnd !== null && $start < $blackoutEnd->getTimestamp() && $end > $blackoutStart->getTimestamp())
            {
                $conflicts[] = 'Requested time overlaps blocked time.';
                break;
            }
        }

        foreach ($externalBusyWindows as $busyWindow)
        {
            $busyTimezone = self::safeDateTimeZone(isset($busyWindow['timezone']) ? $busyWindow['timezone'] : $timezoneName);
            $busyStartValue = isset($busyWindow['starts_at']) ? $busyWindow['starts_at'] : (isset($busyWindow['busy_start']) ? $busyWindow['busy_start'] : '');
            $busyEndValue = isset($busyWindow['ends_at']) ? $busyWindow['ends_at'] : (isset($busyWindow['busy_end']) ? $busyWindow['busy_end'] : '');
            $busyStart = self::localDateTimeFromScheduleValue($busyStartValue, $busyTimezone);
            $busyEnd = self::localDateTimeFromScheduleValue($busyEndValue, $busyTimezone);
            if ($busyStart !== null && $busyEnd !== null && $start < $busyEnd->getTimestamp() && $end > $busyStart->getTimestamp())
            {
                $conflicts[] = 'Requested time overlaps interviewer external busy time.';
                break;
            }
        }

        $bufferMinutes = isset($interviewer['buffer_minutes']) ? (int) $interviewer['buffer_minutes'] : 15;
        foreach ($existingInterviews as $interview)
        {
            $existingTimezone = self::safeDateTimeZone(isset($interview['timezone']) ? $interview['timezone'] : $timezoneName);
            $existingStartDateTime = self::localDateTimeFromScheduleValue($interview['scheduled_start'], $existingTimezone);
            $existingEndDateTime = self::localDateTimeFromScheduleValue($interview['scheduled_end'], $existingTimezone);
            if ($existingStartDateTime === null || $existingEndDateTime === null)
            {
                continue;
            }
            $existingStart = $existingStartDateTime->getTimestamp() - ($bufferMinutes * 60);
            $existingEnd = $existingEndDateTime->getTimestamp() + ($bufferMinutes * 60);
            if ($start < $existingEnd && $end > $existingStart)
            {
                $conflicts[] = 'Requested time overlaps an existing interview or buffer.';
                break;
            }
        }

        $dailyLimit = isset($interviewer['max_interviews_per_day']) ? (int) $interviewer['max_interviews_per_day'] : 3;
        $weeklyLimit = isset($interviewer['max_interviews_per_week']) ? (int) $interviewer['max_interviews_per_week'] : 12;
        $dayCount = 0;
        $weekCount = 0;
        $targetDay = $startDateTime->format('Y-m-d');
        $targetWeek = $startDateTime->format('o-W');
        foreach ($existingInterviews as $interview)
        {
            $existingTimezone = self::safeDateTimeZone(isset($interview['timezone']) ? $interview['timezone'] : $timezoneName);
            $existingStart = self::localDateTimeFromScheduleValue($interview['scheduled_start'], $existingTimezone);
            if ($existingStart === null)
            {
                continue;
            }
            $existingInTargetTimezone = $existingStart->setTimezone($timezone);
            if ($existingInTargetTimezone->format('Y-m-d') === $targetDay)
            {
                $dayCount++;
            }
            if ($existingInTargetTimezone->format('o-W') === $targetWeek)
            {
                $weekCount++;
            }
        }
        if ($dailyLimit > 0 && $dayCount >= $dailyLimit)
        {
            $conflicts[] = 'Maximum daily interviews would be exceeded.';
        }
        if ($weeklyLimit > 0 && $weekCount >= $weeklyLimit)
        {
            $conflicts[] = 'Maximum weekly interviews would be exceeded.';
        }

        return $conflicts;
    }

    private static function safeDateTimeZone($timezone)
    {
        try
        {
            return new DateTimeZone(trim((string) $timezone) === '' ? 'America/New_York' : trim((string) $timezone));
        }
        catch (Exception $e)
        {
            return new DateTimeZone('America/New_York');
        }
    }

    private static function localDateTimeFromScheduleValue($value, $timezone)
    {
        $value = trim((string) $value);
        if (preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}$/', $value))
        {
            $value .= ':00';
        }
        if (!preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', $value))
        {
            return null;
        }

        $dateTime = DateTimeImmutable::createFromFormat('!Y-m-d H:i:s', $value, $timezone);
        $errors = DateTimeImmutable::getLastErrors();
        if ($dateTime === false || (is_array($errors) && ($errors['warning_count'] > 0 || $errors['error_count'] > 0)))
        {
            return null;
        }
        if ($dateTime->format('Y-m-d H:i:s') !== $value)
        {
            return null;
        }

        return $dateTime;
    }

    private static function scheduleNowDateTime($now, $timezone)
    {
        if ($now instanceof DateTimeInterface)
        {
            return DateTimeImmutable::createFromFormat('U', $now->format('U'))->setTimezone($timezone);
        }
        if (is_int($now))
        {
            return DateTimeImmutable::createFromFormat('U', (string) $now)->setTimezone($timezone);
        }
        if (is_string($now) && trim($now) !== '')
        {
            $parsed = self::localDateTimeFromScheduleValue($now, $timezone);
            if ($parsed !== null)
            {
                return $parsed;
            }
        }

        return new DateTimeImmutable('now', $timezone);
    }

    private static function scheduleWindowContains($startDateTime, $endDateTime, $date, $windowStart, $windowEnd, $timezone)
    {
        $start = self::localDateTimeFromScheduleValue($date . ' ' . self::normalizeScheduleTimeForDateTime($windowStart), $timezone);
        $end = self::localDateTimeFromScheduleValue($date . ' ' . self::normalizeScheduleTimeForDateTime($windowEnd), $timezone);
        if ($start === null || $end === null || $start->getTimestamp() >= $end->getTimestamp())
        {
            return false;
        }

        return $startDateTime->getTimestamp() >= $start->getTimestamp()
            && $endDateTime->getTimestamp() <= $end->getTimestamp();
    }

    private static function normalizeScheduleTimeForDateTime($time)
    {
        $time = trim((string) $time);
        if (preg_match('/^\d{2}:\d{2}$/', $time))
        {
            return $time . ':00';
        }
        if (preg_match('/^\d{2}:\d{2}:\d{2}$/', $time))
        {
            return $time;
        }

        return '';
    }

    public static function isValidAvailabilityTime($time)
    {
        $time = trim($time);
        if (!preg_match('/^(\d{2}):(\d{2})$/', $time, $matches))
        {
            return false;
        }

        return (int) $matches[1] >= 0
            && (int) $matches[1] <= 23
            && (int) $matches[2] >= 0
            && (int) $matches[2] <= 59;
    }

    public static function matchAssignmentRuleForRole($roleTitle, $rules)
    {
        $roleTitle = strtolower(trim($roleTitle));
        if ($roleTitle === '')
        {
            return array();
        }

        foreach ($rules as $rule)
        {
            if (isset($rule['is_active']) && ((int) $rule['is_active']) !== 1)
            {
                continue;
            }

            $matchText = isset($rule['role_match_text']) ? strtolower(trim($rule['role_match_text'])) : '';
            if ($matchText !== '' && strpos($roleTitle, $matchText) !== false)
            {
                return $rule;
            }
        }

        return array();
    }

    public static function getDefaultStaffingForecastConfig()
    {
        return array(
            'photographer_ratio' => 1.0,
            'assistant_ratio' => 0.35,
            'table_staff_ratio' => 0.25,
            'buffer_percent' => 25,
            'expected_returning_staff' => 0,
            'confirmed_available_staff' => 0,
            'active_staff' => 0
        );
    }

    public static function parseStaffingCSVText($csvText, $sourceLabel = 'uploaded CSV')
    {
        $lines = preg_split('/\r\n|\n|\r/', $csvText);
        $rows = array();
        foreach ($lines as $line)
        {
            if (trim($line) === '')
            {
                continue;
            }
            $rows[] = str_getcsv($line, ',', '"', '\\');
        }

        return self::normalizeStaffingRows($rows, $sourceLabel, 'CSV');
    }

    public static function parseStaffingXLSXFile($filePath, $sourceLabel = 'uploaded XLSX')
    {
        if (!class_exists('ZipArchive'))
        {
            return array(
                'rows' => array(),
                'issues' => array(
                    array('row_number' => 0, 'issue_key' => 'xlsx_unavailable', 'message' => 'XLSX parsing requires the PHP ZipArchive extension.')
                ),
                'checksum' => is_file($filePath) ? hash_file('sha256', $filePath) : '',
                'source_label' => $sourceLabel
            );
        }

        $zip = new ZipArchive();
        if ($zip->open($filePath) !== true)
        {
            return array(
                'rows' => array(),
                'issues' => array(
                    array('row_number' => 0, 'issue_key' => 'xlsx_open_failed', 'message' => 'The XLSX file could not be opened.')
                ),
                'checksum' => is_file($filePath) ? hash_file('sha256', $filePath) : '',
                'source_label' => $sourceLabel
            );
        }

        $sharedStrings = array();
        $sharedXML = $zip->getFromName('xl/sharedStrings.xml');
        if ($sharedXML !== false)
        {
            $xml = @simplexml_load_string($sharedXML);
            if ($xml !== false)
            {
                foreach ($xml->si as $stringItem)
                {
                    $sharedStrings[] = (string) $stringItem->t;
                }
            }
        }

        $sheetXML = $zip->getFromName('xl/worksheets/sheet1.xml');
        $rows = array();
        if ($sheetXML !== false)
        {
            $xml = @simplexml_load_string($sheetXML);
            if ($xml !== false)
            {
                foreach ($xml->sheetData->row as $row)
                {
                    $values = array();
                    foreach ($row->c as $cell)
                    {
                        $value = (string) $cell->v;
                        if ((string) $cell['t'] === 's' && isset($sharedStrings[(int) $value]))
                        {
                            $value = $sharedStrings[(int) $value];
                        }
                        $values[] = $value;
                    }
                    $rows[] = $values;
                }
            }
        }
        $zip->close();

        $result = self::normalizeStaffingRows($rows, $sourceLabel, 'XLSX');
        $result['checksum'] = is_file($filePath) ? hash_file('sha256', $filePath) : '';
        return $result;
    }

    public static function parseFallStaffingWorkbookXLSXFile($filePath, $sourceLabel = 'Fall schedule workbook')
    {
        if (!class_exists('ZipArchive'))
        {
            return array(
                'rows' => array(),
                'issues' => array(
                    array('row_number' => 0, 'issue_key' => 'xlsx_unavailable', 'message' => 'XLSX parsing requires the PHP ZipArchive extension.')
                ),
                'checksum' => is_file($filePath) ? hash_file('sha256', $filePath) : '',
                'source_label' => $sourceLabel,
                'source_type' => 'fall_schedule_xlsx',
                'dry_run' => self::emptyFallStaffingDryRun()
            );
        }

        $zip = new ZipArchive();
        if ($zip->open($filePath) !== true)
        {
            return array(
                'rows' => array(),
                'issues' => array(
                    array('row_number' => 0, 'issue_key' => 'xlsx_open_failed', 'message' => 'The XLSX file could not be opened.')
                ),
                'checksum' => is_file($filePath) ? hash_file('sha256', $filePath) : '',
                'source_label' => $sourceLabel,
                'source_type' => 'fall_schedule_xlsx',
                'dry_run' => self::emptyFallStaffingDryRun()
            );
        }

        $sheets = self::readXLSXWorkbookSheets($zip);
        $zip->close();

        $result = self::parseFallStaffingWorkbookRows($sheets, $sourceLabel);
        $result['checksum'] = is_file($filePath) ? hash_file('sha256', $filePath) : hash('sha256', json_encode($sheets));
        return $result;
    }

    public static function parseFallStaffingWorkbookRows($sheets, $sourceLabel = 'Fall schedule workbook')
    {
        $dryRun = self::emptyFallStaffingDryRun();
        $dryRun['source_label'] = $sourceLabel;
        $rows = array();
        $issues = array();
        $seenContent = array();

        foreach ($sheets as $sheetName => $sheetRows)
        {
            $sheetSummary = array(
                'tab_name' => $sheetName,
                'source_rows' => count($sheetRows),
                'recognized_job_rows' => 0,
                'staffing_rows' => 0,
                'assignment_rows' => 0,
                'ambiguous_rows' => 0,
                'years_found' => array()
            );
            $dryRun['source_summary']['total_tabs']++;
            $dryRun['source_summary']['tabs'][] = $sheetName;

            $headers = isset($sheetRows[0]) ? self::normalizeHeader($sheetRows[0]) : array();
            if (!self::fallScheduleHeaderLooksUsable($headers))
            {
                $dryRun['quality']['skipped_non_schedule_tabs']++;
                $dryRun['tab_summaries'][] = $sheetSummary;
                continue;
            }

            $currentDate = '';
            for ($rowIndex = 1; $rowIndex < count($sheetRows); $rowIndex++)
            {
                $sourceRowNumber = $rowIndex + 1;
                $sourceRow = $sheetRows[$rowIndex];
                $rawText = implode(' | ', array_map('trim', $sourceRow));
                if (!self::fallScheduleRowHasMeaningfulValue($sourceRow))
                {
                    $dryRun['quality']['skipped_blank_rows']++;
                    continue;
                }

                if (self::fallScheduleRowIsHeader($sourceRow))
                {
                    $dryRun['quality']['skipped_header_rows']++;
                    continue;
                }

                $firstCell = self::fallScheduleCell($sourceRow, 0);
                $rowDate = self::parseStaffingDate($firstCell);
                if ($rowDate !== '' && !self::fallScheduleRowLooksLikeJob($sourceRow))
                {
                    $currentDate = $rowDate;
                    $dryRun['quality']['skipped_date_rows']++;
                    $year = substr($rowDate, 0, 4);
                    $sheetSummary['years_found'][$year] = true;
                    $dryRun['source_summary']['years_found'][$year] = true;
                    continue;
                }

                if (!self::fallScheduleRowLooksLikeJob($sourceRow))
                {
                    $dryRun['quality']['skipped_separator_rows']++;
                    continue;
                }

                $sheetSummary['recognized_job_rows']++;
                $dryRun['quality']['recognized_job_rows']++;
                $dryRun['quality']['total_source_rows']++;

                $eventName = $firstCell;
                $staffingText = self::fallScheduleCell($sourceRow, 3);
                $indoorOutdoor = self::fallScheduleCell($sourceRow, 4);
                $jobType = self::fallScheduleCell($sourceRow, 5);
                $importance = self::fallScheduleCell($sourceRow, 2);
                $location = self::fallScheduleCell($sourceRow, 26);
                $startTime = self::parseStaffingTime(self::fallScheduleCell($sourceRow, 27));
                $endTime = self::parseStaffingTime(self::fallScheduleCell($sourceRow, 28));
                $notes = trim(self::fallScheduleCell($sourceRow, 6) . ' ' . self::fallScheduleCell($sourceRow, 7) . ' ' . self::fallScheduleCell($sourceRow, 34));
                $rowIssues = array();

                if ($currentDate === '')
                {
                    $rowIssues[] = 'missing_or_malformed_date';
                    $dryRun['quality']['rows_missing_dates']++;
                }
                else
                {
                    $year = substr($currentDate, 0, 4);
                    $sheetSummary['years_found'][$year] = true;
                    $dryRun['source_summary']['years_found'][$year] = true;
                    if (!isset($dryRun['quality']['records_by_year'][$year]))
                    {
                        $dryRun['quality']['records_by_year'][$year] = 0;
                    }
                    $dryRun['quality']['records_by_year'][$year]++;
                }

                if ($eventName === '' || in_array(strtolower($eventName), array('true', 'false'), true))
                {
                    $rowIssues[] = 'missing_event_name';
                }
                if ($location === '')
                {
                    $rowIssues[] = 'missing_location';
                    $dryRun['quality']['rows_missing_location']++;
                }
                if ($startTime === null || $endTime === null)
                {
                    $rowIssues[] = 'missing_start_or_end_time';
                    $dryRun['quality']['rows_missing_start_or_end']++;
                }

                $requiredRoles = self::parseFallStaffingRequirementText($staffingText);
                if (empty($requiredRoles))
                {
                    $rowIssues[] = 'missing_or_invalid_staffing';
                    $dryRun['quality']['invalid_staffing_rows']++;
                }
                else
                {
                    $sheetSummary['staffing_rows']++;
                }

                $assignmentCounts = self::countFallScheduleAssignments($sourceRow);
                if (array_sum($assignmentCounts) > 0)
                {
                    $sheetSummary['assignment_rows']++;
                    $dryRun['quality']['rows_with_assignments']++;
                    $requiredTotal = array_sum($requiredRoles);
                    $assignedTotal = array_sum($assignmentCounts);
                    if ($requiredTotal > 0 && $assignedTotal !== $requiredTotal)
                    {
                        $rowIssues[] = 'assigned_required_conflict';
                        $dryRun['quality']['conflicting_assigned_vs_required_rows']++;
                    }
                }

                $contentKey = strtolower($currentDate . '|' . $eventName . '|' . $location . '|' . $staffingText);
                if ($contentKey !== '|||' && isset($seenContent[$contentKey]))
                {
                    $rowIssues[] = 'duplicate_source_row';
                    $dryRun['quality']['duplicate_rows']++;
                }
                $seenContent[$contentKey] = true;

                $statusKey = empty($rowIssues) ? 'normalized' : 'needs_review';
                if ($statusKey === 'needs_review')
                {
                    $sheetSummary['ambiguous_rows']++;
                    $dryRun['quality']['ambiguous_rows']++;
                }

                foreach ($rowIssues as $issueKey)
                {
                    $issues[] = array(
                        'row_number' => $sourceRowNumber,
                        'issue_key' => $issueKey,
                        'message' => self::fallScheduleIssueMessage($issueKey, $sheetName, $sourceRowNumber)
                    );
                }

                if (empty($requiredRoles))
                {
                    $requiredRoles = array('unresolved' => 0);
                }

                foreach ($requiredRoles as $roleKey => $staffCount)
                {
                    $unresolved = array(
                        'source_label' => $sourceLabel,
                        'source_year' => $currentDate === '' ? '' : substr($currentDate, 0, 4),
                        'source_tab_name' => $sheetName,
                        'indoor_outdoor' => $indoorOutdoor,
                        'job_type' => $jobType,
                        'importance' => $importance,
                        'location' => $location,
                        'staffing_text_original' => $staffingText,
                        'notes_sanitized' => self::sanitizeStaffingNote($notes),
                        'assignment_counts' => $assignmentCounts,
                        'quality_issues' => $rowIssues,
                        'dry_run_only' => true
                    );
                    $row = array(
                        'source_sheet_name' => $sheetName,
                        'source_row_number' => $sourceRowNumber,
                        'event_date' => $currentDate,
                        'event_start_time' => $startTime,
                        'event_end_time' => $endTime,
                        'state' => self::inferStateFromLocation($location),
                        'sport' => self::inferSportFromEventName($eventName),
                        'event_name' => $eventName,
                        'role_key' => $roleKey,
                        'staff_name' => '',
                        'staff_count' => (int) $staffCount,
                        'staff_hours' => self::calculateStaffHours($startTime, $endTime, max(0, (int) $staffCount)),
                        'raw_source_text' => $rawText,
                        'unresolved_json' => json_encode($unresolved),
                        'issue_count' => count($rowIssues),
                        'status_key' => $statusKey
                    );
                    $row['source_row_hash'] = hash('sha256', $sheetName . '|' . $sourceRowNumber . '|' . $currentDate . '|' . $eventName . '|' . $location . '|' . $roleKey . '|' . $staffingText . '|' . $rawText);
                    $rows[] = $row;
                }
            }

            $sheetSummary['years_found'] = array_map('strval', array_keys($sheetSummary['years_found']));
            sort($sheetSummary['years_found']);
            if ($sheetSummary['recognized_job_rows'] > 0)
            {
                $dryRun['source_summary']['tabs_with_jobs'][] = $sheetName;
            }
            if ($sheetSummary['assignment_rows'] > 0)
            {
                $dryRun['source_summary']['tabs_with_assignments'][] = $sheetName;
            }
            $dryRun['tab_summaries'][] = $sheetSummary;
        }

        $dryRun['source_summary']['years_found'] = array_map('strval', array_keys($dryRun['source_summary']['years_found']));
        sort($dryRun['source_summary']['years_found']);
        $dryRun['source_summary']['prior_fall_years_present'] = count(array_filter(
            $dryRun['source_summary']['years_found'],
            function ($year) {
                return (int) $year < 2026;
            }
        )) > 0;
        $dryRun['source_summary']['requires_additional_historical_workbooks'] = !$dryRun['source_summary']['prior_fall_years_present'];
        $dryRun['quality']['normalized_role_rows'] = count($rows);
        $dryRun['quality']['issue_count'] = count($issues);

        return array(
            'rows' => $rows,
            'issues' => $issues,
            'checksum' => hash('sha256', json_encode($sheets)),
            'source_label' => $sourceLabel,
            'source_type' => 'fall_schedule_workbook',
            'dry_run' => $dryRun
        );
    }

    public static function buildStaffingDryRunReviewRows($parseResult)
    {
        $groups = array();
        $issuesByRow = array();
        if (isset($parseResult['issues']) && is_array($parseResult['issues']))
        {
            foreach ($parseResult['issues'] as $issue)
            {
                $rowNumber = isset($issue['row_number']) ? (int) $issue['row_number'] : 0;
                if (!isset($issuesByRow[$rowNumber]))
                {
                    $issuesByRow[$rowNumber] = array();
                }
                $issuesByRow[$rowNumber][] = isset($issue['issue_key']) ? $issue['issue_key'] : 'review_required';
            }
        }

        $rows = isset($parseResult['rows']) && is_array($parseResult['rows']) ? $parseResult['rows'] : array();
        foreach ($rows as $row)
        {
            $unresolved = array();
            if (isset($row['unresolved_json']) && $row['unresolved_json'] !== '')
            {
                $decoded = json_decode($row['unresolved_json'], true);
                if (is_array($decoded))
                {
                    $unresolved = $decoded;
                }
            }

            $groupKey = self::staffingReviewRowKey($row);
            if (!isset($groups[$groupKey]))
            {
                $rowIssues = isset($issuesByRow[(int) $row['source_row_number']]) ? $issuesByRow[(int) $row['source_row_number']] : array();
                $groups[$groupKey] = array(
                    'review_key' => $groupKey,
                    'source_sheet_name' => $row['source_sheet_name'],
                    'source_row_number' => (int) $row['source_row_number'],
                    'event_date' => $row['event_date'],
                    'event_start_time' => $row['event_start_time'],
                    'event_end_time' => $row['event_end_time'],
                    'event_name' => $row['event_name'],
                    'location' => isset($unresolved['location']) ? $unresolved['location'] : '',
                    'indoor_outdoor' => isset($unresolved['indoor_outdoor']) ? $unresolved['indoor_outdoor'] : '',
                    'job_type' => isset($unresolved['job_type']) ? $unresolved['job_type'] : '',
                    'staffing_text_original' => isset($unresolved['staffing_text_original']) ? $unresolved['staffing_text_original'] : '',
                    'source_format' => isset($unresolved['source_format']) ? $unresolved['source_format'] : '',
                    'photographers' => 0,
                    'leads' => 0,
                    'table_staff' => 0,
                    'assistants' => 0,
                    'total_required_staff' => 0,
                    'warnings' => $rowIssues,
                    'duplicate_status' => in_array('duplicate_source_row', $rowIssues, true) ? 'possible duplicate in source' : 'new',
                    'is_valid' => empty($rowIssues) && (int) $row['issue_count'] === 0,
                    'role_hashes' => array()
                );
            }

            $staffCount = max(0, (int) $row['staff_count']);
            switch ($row['role_key'])
            {
                case 'photographer':
                    $groups[$groupKey]['photographers'] += $staffCount;
                    break;
                case 'lead':
                    $groups[$groupKey]['leads'] += $staffCount;
                    break;
                case 'table_staff':
                    $groups[$groupKey]['table_staff'] += $staffCount;
                    break;
                case 'assistant':
                    $groups[$groupKey]['assistants'] += $staffCount;
                    break;
                default:
                    $groups[$groupKey]['is_valid'] = false;
                    if (!in_array('unrecognized_role', $groups[$groupKey]['warnings'], true))
                    {
                        $groups[$groupKey]['warnings'][] = 'unrecognized_role';
                    }
                    break;
            }
            $groups[$groupKey]['total_required_staff'] += $staffCount;
            $groups[$groupKey]['role_hashes'][] = $row['source_row_hash'];
        }

        foreach ($groups as $groupKey => $group)
        {
            if ($group['source_format'] === 'normalized_csv')
            {
                $parts = array();
                if ($group['photographers'] > 0)
                {
                    $parts[] = $group['photographers'] . 'P';
                }
                if ($group['leads'] > 0)
                {
                    $parts[] = $group['leads'] . 'L';
                }
                if ($group['table_staff'] > 0)
                {
                    $parts[] = $group['table_staff'] . 'T';
                }
                if ($group['assistants'] > 0)
                {
                    $parts[] = $group['assistants'] . 'A';
                }
                $groups[$groupKey]['staffing_text_original'] = empty($parts) ? 'Role-expanded CSV' : implode('/', $parts);
            }
        }

        return array_values($groups);
    }

    public static function buildApprovedStaffingImportPlan($parseResult, $approvedReviewKeys)
    {
        $approved = array();
        foreach ((array) $approvedReviewKeys as $key)
        {
            $key = trim((string) $key);
            if ($key !== '')
            {
                $approved[$key] = true;
            }
        }

        if (empty($approved))
        {
            return array('ok' => false, 'error' => 'Select at least one valid staffing row before importing.', 'rows' => array(), 'review_rows' => array());
        }

        $reviewRows = self::buildStaffingDryRunReviewRows($parseResult);
        $validReviewRows = array();
        foreach ($reviewRows as $reviewRow)
        {
            $validReviewRows[$reviewRow['review_key']] = $reviewRow;
        }

        $unknownKeys = array_diff(array_keys($approved), array_keys($validReviewRows));
        if (!empty($unknownKeys))
        {
            return array('ok' => false, 'error' => 'The selected staffing rows do not match the reviewed dry-run batch.', 'rows' => array(), 'review_rows' => $reviewRows);
        }

        foreach (array_keys($approved) as $key)
        {
            if (empty($validReviewRows[$key]['is_valid']))
            {
                return array('ok' => false, 'error' => 'Ambiguous or invalid staffing rows must be corrected and re-reviewed before import.', 'rows' => array(), 'review_rows' => $reviewRows);
            }
        }

        $selectedRows = array();
        $rows = isset($parseResult['rows']) && is_array($parseResult['rows']) ? $parseResult['rows'] : array();
        foreach ($rows as $row)
        {
            if (isset($approved[self::staffingReviewRowKey($row)]) && (int) $row['issue_count'] === 0)
            {
                $selectedRows[] = self::sanitizeApprovedStaffingRowForImport($row);
            }
        }

        if (empty($selectedRows))
        {
            return array('ok' => false, 'error' => 'No importable role rows were found for the approved staffing rows.', 'rows' => array(), 'review_rows' => $reviewRows);
        }

        return array('ok' => true, 'error' => '', 'rows' => $selectedRows, 'review_rows' => $reviewRows);
    }

    private static function staffingReviewRowKey($row)
    {
        if (isset($row['review_group_key']) && trim((string) $row['review_group_key']) !== '')
        {
            return trim((string) $row['review_group_key']);
        }

        return hash(
            'sha256',
            implode('|', array(
                isset($row['source_sheet_name']) ? $row['source_sheet_name'] : '',
                isset($row['source_row_number']) ? $row['source_row_number'] : '',
                isset($row['event_date']) ? $row['event_date'] : '',
                isset($row['event_name']) ? $row['event_name'] : ''
            ))
        );
    }

    public static function sanitizeApprovedStaffingRowForImport($row)
    {
        $unresolved = array();
        if (isset($row['unresolved_json']) && $row['unresolved_json'] !== '')
        {
            $decoded = json_decode($row['unresolved_json'], true);
            if (is_array($decoded))
            {
                $unresolved = $decoded;
            }
        }

        unset($unresolved['assignment_counts']);
        unset($unresolved['dry_run_only']);
        $safeLocation = isset($unresolved['location']) ? $unresolved['location'] : '';
        $safeStaffing = isset($unresolved['staffing_text_original']) ? $unresolved['staffing_text_original'] : '';
        $safeRawText = trim(sprintf(
            'Tab %s row %s | %s | %s | %s',
            isset($row['source_sheet_name']) ? $row['source_sheet_name'] : '',
            isset($row['source_row_number']) ? $row['source_row_number'] : '',
            isset($row['event_name']) ? $row['event_name'] : '',
            $safeLocation,
            $safeStaffing
        ));

        $row['staff_name'] = '';
        $row['raw_source_text'] = $safeRawText;
        $row['unresolved_json'] = json_encode($unresolved);
        if ($row['unresolved_json'] === false)
        {
            $row['unresolved_json'] = '{}';
        }

        return $row;
    }

    public static function normalizeStaffingRows($rows, $sourceLabel, $sourceType = 'CSV')
    {
        $normalized = array();
        $issues = array();
        if (empty($rows))
        {
            $emptyIssues = array(array('row_number' => 0, 'issue_key' => 'empty_source', 'message' => 'No rows were found.'));
            return array(
                'rows' => $normalized,
                'issues' => $emptyIssues,
                'checksum' => hash('sha256', ''),
                'source_label' => $sourceLabel,
                'source_type' => $sourceType,
                'dry_run' => self::buildNormalizedStaffingDryRun($normalized, $emptyIssues, $sourceLabel, $sourceType)
            );
        }

        $headerRowIndex = self::findStaffingHeaderRow($rows);
        $header = self::normalizeHeader($rows[$headerRowIndex]);
        $dateColumns = array();
        foreach ($header as $index => $name)
        {
            $sourceHeader = isset($rows[$headerRowIndex][$index]) ? $rows[$headerRowIndex][$index] : $name;
            $date = self::parseStaffingDate($sourceHeader);
            if ($date !== '')
            {
                $dateColumns[$index] = $date;
            }
        }

        $seenHashes = array();
        for ($i = $headerRowIndex + 1; $i < count($rows); $i++)
        {
            $row = $rows[$i];
            $rawText = implode(' | ', $row);
            if (trim($rawText) === '')
            {
                continue;
            }

            $rowNumber = $i + 1;
            if (!empty($dateColumns))
            {
                foreach ($dateColumns as $columnIndex => $date)
                {
                    $staffText = isset($row[$columnIndex]) ? trim($row[$columnIndex]) : '';
                    if ($staffText === '')
                    {
                        continue;
                    }

                    $base = self::rowValueMap($header, $row);
                    $base['date'] = $date;
                    $base['staff'] = $staffText;
                    $result = self::normalizeStaffingRow($base, $rowNumber, $rawText, $sourceLabel);
                    if (isset($seenHashes[$result['row']['source_row_hash']]))
                    {
                        $result['issues'][] = array(
                            'row_number' => $rowNumber,
                            'issue_key' => 'duplicate_source_row',
                            'message' => 'This source row appears to duplicate an earlier normalized row.'
                        );
                        $result['row']['issue_count']++;
                        $result['row']['status_key'] = 'needs_review';
                        $result['row']['source_row_hash'] = hash('sha256', $result['row']['source_row_hash'] . '|' . $rowNumber);
                    }
                    $seenHashes[$result['row']['source_row_hash']] = true;
                    $normalized[] = $result['row'];
                    $issues = array_merge($issues, $result['issues']);
                }
                continue;
            }

            $mapped = self::rowValueMap($header, $row);
            $result = self::normalizeStaffingRow($mapped, $rowNumber, $rawText, $sourceLabel);
            if (isset($seenHashes[$result['row']['source_row_hash']]))
            {
                $result['issues'][] = array(
                    'row_number' => $rowNumber,
                    'issue_key' => 'duplicate_source_row',
                    'message' => 'This source row appears to duplicate an earlier normalized row.'
                );
                $result['row']['issue_count']++;
                $result['row']['status_key'] = 'needs_review';
                $result['row']['source_row_hash'] = hash('sha256', $result['row']['source_row_hash'] . '|' . $rowNumber);
            }
            $seenHashes[$result['row']['source_row_hash']] = true;
            $normalized[] = $result['row'];
            $issues = array_merge($issues, $result['issues']);
        }

        return array(
            'rows' => $normalized,
            'issues' => $issues,
            'checksum' => hash('sha256', json_encode($rows)),
            'source_label' => $sourceLabel,
            'source_type' => $sourceType,
            'dry_run' => self::buildNormalizedStaffingDryRun($normalized, $issues, $sourceLabel, $sourceType)
        );
    }

    public static function calculateStaffingForecastMetrics($rows, $config = array())
    {
        $config = array_merge(self::getDefaultStaffingForecastConfig(), $config);
        $metrics = array(
            'events_by_season' => array(),
            'events_by_week' => array(),
            'events_by_weekday' => array(),
            'events_by_state' => array(),
            'events_by_sport' => array(),
            'unique_staff_by_season' => array(),
            'staff_by_role' => array(),
            'staff_hours' => 0.0,
            'total_events' => 0,
            'total_staff_assignments' => 0,
            'peak_day_staffing' => 0,
            'peak_concurrent_staff' => 0,
            'peak_concurrent_staff_confidence' => 'Exact',
            'peak_concurrent_staff_uncertainty' => '',
            'recommendation_staffing' => 0,
            'recommendation_staffing_basis' => 'peak_concurrent_staff',
            'average_staff_per_event' => 0,
            'recommended_pool' => 0,
            'recommended_backup' => 0,
            'hiring_gap' => 0,
            'confidence' => 'Low',
            'formulas' => array(
                'recommended_pool' => 'ceil(recommendation_staffing * (1 + buffer_percent / 100))',
                'recommended_backup' => 'ceil(recommended_pool * buffer_percent / 100)',
                'hiring_gap' => 'max(0, recommended_pool + recommended_backup - active_staff - expected_returning_staff - confirmed_available_staff)',
                'peak_concurrent_staff' => 'maximum staffing across overlapping valid event intervals; unknown when a dated event has missing, conflicting, or invalid start/end times',
                'confidence' => 'High requires at least 3 usable seasons and no open import issues; Medium requires at least 2 usable seasons.'
            )
        );

        $events = array();
        $eventIntervals = array();
        $dayStaff = array();
        $staffBySeason = array();
        $openIssues = 0;
        foreach ($rows as $row)
        {
            if (!empty($row['issue_count']))
            {
                $openIssues += (int) $row['issue_count'];
            }

            if (empty($row['event_date']))
            {
                continue;
            }

            $season = substr($row['event_date'], 0, 4);
            $week = date('Y-m-d', strtotime('monday this week', strtotime($row['event_date'])));
            $weekday = date('l', strtotime($row['event_date']));
            $eventKey = $row['event_date'] . '|' . $row['event_name'] . '|' . $row['state'];
            $isNewEvent = !isset($events[$eventKey]);
            $events[$eventKey] = true;

            $rowStartTime = isset($row['event_start_time']) && $row['event_start_time'] !== null && trim((string) $row['event_start_time']) !== ''
                ? trim((string) $row['event_start_time'])
                : null;
            $rowEndTime = isset($row['event_end_time']) && $row['event_end_time'] !== null && trim((string) $row['event_end_time']) !== ''
                ? trim((string) $row['event_end_time'])
                : null;
            if (!isset($eventIntervals[$eventKey]))
            {
                $eventIntervals[$eventKey] = array(
                    'event_date' => $row['event_date'],
                    'event_start_time' => $rowStartTime,
                    'event_end_time' => $rowEndTime,
                    'staffing' => 0,
                    'has_consistent_times' => true
                );
            }
            else if ($eventIntervals[$eventKey]['event_start_time'] !== $rowStartTime || $eventIntervals[$eventKey]['event_end_time'] !== $rowEndTime)
            {
                $eventIntervals[$eventKey]['has_consistent_times'] = false;
            }

            if ($isNewEvent)
            {
                self::incrementMetric($metrics['events_by_season'], $season, 1);
                self::incrementMetric($metrics['events_by_week'], $week, 1);
                self::incrementMetric($metrics['events_by_weekday'], $weekday, 1);
                self::incrementMetric($metrics['events_by_state'], $row['state'] === '' ? 'Unknown' : $row['state'], 1);
                self::incrementMetric($metrics['events_by_sport'], $row['sport'] === '' ? 'Unknown' : $row['sport'], 1);
            }
            self::incrementMetric($metrics['staff_by_role'], $row['role_key'] === '' ? 'unknown' : $row['role_key'], max(1, (int) $row['staff_count']));

            if (!isset($staffBySeason[$season]))
            {
                $staffBySeason[$season] = array();
            }
            if ($row['staff_name'] !== '')
            {
                foreach (preg_split('/[;,]+/', $row['staff_name']) as $staffName)
                {
                    $staffName = trim($staffName);
                    if ($staffName !== '')
                    {
                        $staffBySeason[$season][$staffName] = true;
                    }
                }
            }

            if (!isset($dayStaff[$row['event_date']]))
            {
                $dayStaff[$row['event_date']] = 0;
            }
            $dayStaff[$row['event_date']] += max(1, (int) $row['staff_count']);
            $eventIntervals[$eventKey]['staffing'] += max(1, (int) $row['staff_count']);
            $metrics['staff_hours'] += (float) $row['staff_hours'];
            $metrics['total_staff_assignments'] += max(1, (int) $row['staff_count']);
        }

        foreach ($staffBySeason as $season => $staff)
        {
            $metrics['unique_staff_by_season'][$season] = count($staff);
        }

        $metrics['total_events'] = count($events);
        $metrics['peak_day_staffing'] = empty($dayStaff) ? 0 : max($dayStaff);
        $concurrencyPointsByDate = array();
        $hasIncompleteEventIntervals = false;
        foreach ($eventIntervals as $eventInterval)
        {
            $start = $eventInterval['event_start_time'] === null
                ? false
                : strtotime('2000-01-01 ' . $eventInterval['event_start_time']);
            $end = $eventInterval['event_end_time'] === null
                ? false
                : strtotime('2000-01-01 ' . $eventInterval['event_end_time']);
            if (!$eventInterval['has_consistent_times'] || $start === false || $end === false || $end <= $start)
            {
                $hasIncompleteEventIntervals = true;
                continue;
            }

            if (!isset($concurrencyPointsByDate[$eventInterval['event_date']]))
            {
                $concurrencyPointsByDate[$eventInterval['event_date']] = array();
            }
            if (!isset($concurrencyPointsByDate[$eventInterval['event_date']][$start]))
            {
                $concurrencyPointsByDate[$eventInterval['event_date']][$start] = 0;
            }
            if (!isset($concurrencyPointsByDate[$eventInterval['event_date']][$end]))
            {
                $concurrencyPointsByDate[$eventInterval['event_date']][$end] = 0;
            }
            $concurrencyPointsByDate[$eventInterval['event_date']][$start] += $eventInterval['staffing'];
            $concurrencyPointsByDate[$eventInterval['event_date']][$end] -= $eventInterval['staffing'];
        }

        $peakConcurrentStaff = 0;
        foreach ($concurrencyPointsByDate as $points)
        {
            ksort($points, SORT_NUMERIC);
            $currentStaff = 0;
            foreach ($points as $change)
            {
                $currentStaff += $change;
                $peakConcurrentStaff = max($peakConcurrentStaff, $currentStaff);
            }
        }
        if ($hasIncompleteEventIntervals)
        {
            $metrics['peak_concurrent_staff'] = null;
            $metrics['peak_concurrent_staff_confidence'] = 'Unknown';
            $metrics['peak_concurrent_staff_uncertainty'] = 'One or more dated events have missing, conflicting, or invalid start/end times.';
            $metrics['recommendation_staffing'] = $metrics['peak_day_staffing'];
            $metrics['recommendation_staffing_basis'] = 'peak_day_staffing_fallback';
        }
        else
        {
            $metrics['peak_concurrent_staff'] = $peakConcurrentStaff;
            $metrics['recommendation_staffing'] = $metrics['peak_concurrent_staff'];
        }
        $metrics['average_staff_per_event'] = $metrics['total_events'] > 0
            ? round($metrics['total_staff_assignments'] / $metrics['total_events'], 2)
            : 0;
        $metrics['staff_hours'] = round($metrics['staff_hours'], 2);
        $metrics['recommended_pool'] = (int) ceil($metrics['recommendation_staffing'] * (1 + ((float) $config['buffer_percent'] / 100)));
        $metrics['recommended_backup'] = (int) ceil($metrics['recommended_pool'] * ((float) $config['buffer_percent'] / 100));
        $available = (int) $config['active_staff'] + (int) $config['expected_returning_staff'] + (int) $config['confirmed_available_staff'];
        $metrics['hiring_gap'] = max(0, $metrics['recommended_pool'] + $metrics['recommended_backup'] - $available);

        $usableSeasons = count($metrics['events_by_season']);
        if ($usableSeasons >= 3 && $openIssues === 0)
        {
            $metrics['confidence'] = 'High';
        }
        else if ($usableSeasons >= 2)
        {
            $metrics['confidence'] = 'Medium';
        }

        ksort($metrics['events_by_season']);
        ksort($metrics['events_by_week']);
        return $metrics;
    }

    public function isSchemaInstalled()
    {
        $requiredTables = array(
            'nesp_feature_flag',
            'nesp_workflow_stage',
            'nesp_candidate_workflow',
            'nesp_interviewer_profile',
            'nesp_interviewer_candidate_grant',
            'nesp_interviewer_role_rule',
            'nesp_interviewer_availability',
            'nesp_interview_slot',
            'nesp_interview',
            'nesp_scorecard_template',
            'nesp_scorecard_response',
            'nesp_integration_status',
            'nesp_vapi_phone_screen',
            'nesp_vapi_phone_screen_setting',
            'nesp_vapi_availability_block',
            'nesp_vapi_blackout_date',
            'nesp_vapi_scheduling_activity',
            'nesp_vapi_webhook_event',
            'nesp_question_set',
            'nesp_question_set_version',
            'nesp_question_set_question',
            'nesp_question_set_role_match',
            'nesp_screening_questionnaire',
            'nesp_screening_questionnaire_answer',
            'nesp_screening_questionnaire_activity',
            'nesp_recruiting_campaign_control',
            'nesp_audit_event',
            'nesp_session_security_event',
            'nesp_staffing_schedule_history',
            'nesp_staffing_import_batch',
            'nesp_staffing_import_row',
            'nesp_staffing_import_issue',
            'nesp_historical_job_staffing',
            'nesp_staffing_forecast',
            'nesp_staffing_recommendation'
        );

        foreach ($requiredTables as $table)
        {
            $tableExists = $this->_db->getAssoc(
                sprintf("SHOW TABLES LIKE %s", $this->_db->makeQueryString($table))
            );
            if (empty($tableExists))
            {
                return false;
            }
        }

        $requiredColumns = array(
            array('nesp_candidate_workflow', 'summary'),
            array('nesp_candidate_workflow', 'next_action_label'),
            array('nesp_interviewer_profile', 'can_add_notes'),
            array('nesp_interviewer_profile', 'default_zoom_join_url'),
            array('nesp_interview', 'manual_zoom_join_url'),
            array('nesp_interview', 'timezone'),
            array('nesp_interview', 'invitation_status_key'),
            array('nesp_interview', 'outcome_key'),
            array('nesp_scorecard_response', 'locked_at'),
            array('nesp_vapi_phone_screen', 'call_request_key'),
            array('nesp_vapi_phone_screen', 'destination_phone_hash'),
            array('nesp_vapi_phone_screen', 'consent_status'),
            array('nesp_vapi_phone_screen', 'structured_result_json'),
            array('nesp_vapi_phone_screen', 'scheduling_token_hash'),
            array('nesp_vapi_phone_screen', 'scheduled_start_at_utc'),
            array('nesp_vapi_phone_screen', 'call_attempt_count'),
            array('nesp_vapi_phone_screen_setting', 'setting_key'),
            array('nesp_vapi_availability_block', 'weekday'),
            array('nesp_vapi_blackout_date', 'blackout_date'),
            array('nesp_vapi_scheduling_activity', 'activity_key'),
            array('nesp_vapi_webhook_event', 'provider_event_id'),
            array('nesp_screening_questionnaire', 'token_hash'),
            array('nesp_screening_questionnaire', 'question_set_key'),
            array('nesp_screening_questionnaire', 'question_set_version_id'),
            array('nesp_screening_questionnaire', 'question_snapshot_json'),
            array('nesp_screening_questionnaire', 'reviewer_profile_id'),
            array('nesp_screening_questionnaire_answer', 'answer_text'),
            array('nesp_screening_questionnaire_activity', 'activity_key'),
            array('nesp_recruiting_campaign_control', 'manual_spend'),
            array('nesp_staffing_import_batch', 'undone_at'),
            array('nesp_staffing_import_row', 'source_row_hash'),
            array('nesp_staffing_import_issue', 'status_key')
        );

        foreach ($requiredColumns as $column)
        {
            $columnExists = $this->_db->getAssoc(
                sprintf(
                    "SHOW COLUMNS FROM %s LIKE %s",
                    $column[0],
                    $this->_db->makeQueryString($column[1])
                )
            );
            if (empty($columnExists))
            {
                return false;
            }
        }

        return true;
    }

    public function getFeatureFlags()
    {
        $flags = $this->_db->getAllAssoc(
            'SELECT
                flag_key,
                display_name,
                description,
                is_enabled,
                requires_admin_approval,
                date_modified
            FROM
                nesp_feature_flag
            WHERE
                flag_key IN ("NESP_WORKFLOW_ENABLED", "NESP_INTERVIEWER_POOL_ENABLED", "NESP_INTERVIEWER_AVAILABILITY_ENABLED", "NESP_PRESCREEN_ENABLED", "NESP_VAPI_ENABLED", "NESP_ZOOM_ENABLED", "NESP_INTERVIEWER_ZOOM_LINKS_ENABLED", "NESP_AI_REVIEW_ENABLED", "NESP_STAFFING_FORECAST_ENABLED", "NESP_STAFFING_DRIVE_IMPORT_ENABLED", "NESP_APPLICANT_EMAIL_ENABLED", "NESP_GOOGLE_CALENDAR_FREEBUSY_ENABLED")
            ORDER BY
                display_name'
        );
        foreach ($flags as &$flag)
        {
            $flag['description'] = self::getFeatureFlagDescription(
                isset($flag['flag_key']) ? $flag['flag_key'] : '',
                isset($flag['description']) ? $flag['description'] : ''
            );
        }
        unset($flag);

        return $flags;
    }

    public function updateFeatureFlag($flagKey, $isEnabled, $actorUserID)
    {
        if (!in_array($flagKey, self::getRequiredFeatureFlagKeys()))
        {
            return false;
        }

        $sql = sprintf(
            'UPDATE
                nesp_feature_flag
             SET
                is_enabled = %s,
                date_modified = NOW()
             WHERE
                flag_key = %s',
            ((int) $isEnabled) === 1 ? '1' : '0',
            $this->_db->makeQueryString($flagKey)
        );

        $this->_db->query($sql);
        $this->logAuditEvent(
            $actorUserID,
            'feature_flag_updated',
            'feature_flag',
            null,
            array('flag_key' => $flagKey, 'is_enabled' => ((int) $isEnabled) === 1 ? 1 : 0)
        );

        return true;
    }

    public function isFeatureFlagEnabled($flagKey)
    {
        if (!in_array($flagKey, self::getRequiredFeatureFlagKeys()))
        {
            return false;
        }

        $row = $this->_db->getAssoc(
            sprintf(
                'SELECT is_enabled
                 FROM nesp_feature_flag
                 WHERE flag_key = %s
                 LIMIT 1',
                $this->_db->makeQueryString($flagKey)
            )
        );

        return !empty($row) && ((int) $row['is_enabled']) === 1;
    }

    public function getWorkflowStages()
    {
        return $this->_db->getAllAssoc(
            'SELECT
                stage_key,
                display_name,
                description,
                sort_order,
                is_terminal,
                is_enabled
            FROM
                nesp_workflow_stage
            ORDER BY
                sort_order'
        );
    }

    public function getIntegrationStatuses()
    {
        $statuses = $this->_db->getAllAssoc(
            'SELECT
                integration_key,
                display_name,
                status_key,
                message,
                last_checked_at,
                date_modified
            FROM
                nesp_integration_status
            ORDER BY
                display_name'
        );

        foreach ($statuses as &$status)
        {
            if ($status['integration_key'] === 'email')
            {
                $delivery = $this->getApplicantEmailDeliveryStatus();
                $status['status_key'] = $delivery['status_key'];
                $status['message'] = $delivery['message'];
            }
        }
        unset($status);

        return $statuses;
    }

    public function getApplicantEmailDeliveryStatus()
    {
        $settings = new MailerSettings();
        $mailer = $settings->getAll();
        $enabled = $this->isFeatureFlagEnabled('NESP_APPLICANT_EMAIL_ENABLED');
        $ready = self::isApplicantEmailDeliveryReady(
            $enabled,
            isset($mailer['configured']) ? $mailer['configured'] : '0',
            isset($mailer['fromAddress']) ? $mailer['fromAddress'] : ''
        );

        if (!$enabled)
        {
            return array(
                'status_key' => 'disabled',
                'message' => 'Disabled. New applicants receive no automatic questionnaire email.'
            );
        }
        if (!$ready)
        {
            return array(
                'status_key' => 'not_configured',
                'message' => 'Automatic questionnaire delivery is enabled, but the approved mail sender is not configured. No email will be sent.'
            );
        }

        return array(
            'status_key' => 'enabled',
            'Automatic delivery is active: new applicants with a valid email and linked job receive one role-specific secure questionnaire email. No reminders or other applicant messages are sent.'
        );
    }

    public function getGoogleCalendarConfigurationStatus()
    {
        return NESPGoogleCalendarFreeBusy::getConfigurationStatus(
            $this->isFeatureFlagEnabled(NESPGoogleCalendarFreeBusy::FEATURE_FLAG)
        );
    }

    public function getGoogleCalendarConnections()
    {
        if (!$this->isTableInstalled('nesp_google_calendar_connection'))
        {
            return array();
        }

        $freeBusy = new NESPGoogleCalendarFreeBusy(
            $this->_db,
            $this->isFeatureFlagEnabled(NESPGoogleCalendarFreeBusy::FEATURE_FLAG)
        );

        return $freeBusy->getConnectionSummaries();
    }

    public function getGoogleCalendarConnectionForInterviewer($interviewerProfileID)
    {
        $interviewerProfileID = (int) $interviewerProfileID;
        foreach ($this->getGoogleCalendarConnections() as $connection)
        {
            if ((int) $connection['interviewer_profile_id'] === $interviewerProfileID)
            {
                return $connection;
            }
        }

        return array(
            'interviewer_profile_id' => $interviewerProfileID,
            'status_key' => 'disconnected',
            'token_scope' => NESPGoogleCalendarFreeBusy::MINIMUM_SCOPE,
            'calendar_id_hash' => '',
            'last_error' => ''
        );
    }

    public function requestGoogleCalendarAuthorization($interviewerProfileID, $actorUserID)
    {
        if (!$this->isTableInstalled('nesp_google_calendar_connection'))
        {
            return false;
        }

        $interviewerProfileID = (int) $interviewerProfileID;
        $interviewer = $this->_db->getAssoc(sprintf(
            'SELECT display_name, email, user_id
             FROM nesp_interviewer_profile
             WHERE interviewer_profile_id = %s
             LIMIT 1',
            $this->_db->makeQueryInteger($interviewerProfileID)
        ));
        if (empty($interviewer))
        {
            return false;
        }

        $stateSecret = defined('SESSION_COOKIE') ? SESSION_COOKIE : 'nesp-google-calendar-freebusy';
        $state = hash_hmac(
            'sha256',
            $interviewerProfileID . ':' . $actorUserID . ':' . time(),
            $stateSecret
        );
        $freeBusy = new NESPGoogleCalendarFreeBusy(
            $this->_db,
            $this->isFeatureFlagEnabled(NESPGoogleCalendarFreeBusy::FEATURE_FLAG)
        );
        $freeBusy->markAuthorizationRequested(
            $interviewerProfileID,
            $actorUserID,
            isset($interviewer['user_id']) ? $interviewer['user_id'] : null
        );
        $this->logAuditEvent(
            $actorUserID,
            'google_calendar_authorization_requested',
            'interviewer_profile',
            $interviewerProfileID,
            array(
                'scope' => NESPGoogleCalendarFreeBusy::MINIMUM_SCOPE,
                'event_creation' => false
            )
        );

        return array(
            'display_name' => $interviewer['display_name'],
            'authorization_url' => NESPGoogleCalendarFreeBusy::buildAuthorizationURL($state, $interviewer['email'])
        );
    }

    public function disconnectGoogleCalendar($interviewerProfileID, $actorUserID)
    {
        if (!$this->isTableInstalled('nesp_google_calendar_connection'))
        {
            return false;
        }

        $freeBusy = new NESPGoogleCalendarFreeBusy(
            $this->_db,
            $this->isFeatureFlagEnabled(NESPGoogleCalendarFreeBusy::FEATURE_FLAG)
        );
        $result = $freeBusy->disconnect($interviewerProfileID, $actorUserID);
        if ($result)
        {
            $this->logAuditEvent(
                $actorUserID,
                'google_calendar_disconnected',
                'interviewer_profile',
                (int) $interviewerProfileID,
                array('tokens_removed' => true)
            );
        }

        return $result;
    }

    public function getGoogleCalendarBusyWindowsForInterviewer($interviewerProfileID, $timeMin, $timeMax, $timeZone = 'UTC')
    {
        if (!$this->isTableInstalled('nesp_google_calendar_connection'))
        {
            return array('status_key' => 'not_connected', 'busy' => array(), 'errors' => array());
        }

        $freeBusy = new NESPGoogleCalendarFreeBusy(
            $this->_db,
            $this->isFeatureFlagEnabled(NESPGoogleCalendarFreeBusy::FEATURE_FLAG)
        );

        return $freeBusy->queryFreeBusyForInterviewer($interviewerProfileID, $timeMin, $timeMax, $timeZone);
    }

    public function getInterviewerAccessSummary()
    {
        return array(
            'activeInterviewers' => $this->countRows(
                'SELECT COUNT(*) AS total FROM nesp_interviewer_profile WHERE is_active = 1'
            ),
            'candidateGrants' => $this->countRows(
                'SELECT COUNT(*) AS total FROM nesp_interviewer_candidate_grant WHERE date_revoked IS NULL'
            ),
            'scheduledInterviews' => $this->countRows(
                "SELECT COUNT(*) AS total FROM nesp_interview WHERE status_key IN ('scheduled', 'invitation_pending', 'invitation_sent', 'confirmed', 'reschedule_needed')"
            ),
            'pendingScorecards' => $this->countRows(
                "SELECT COUNT(*) AS total FROM nesp_scorecard_response WHERE status_key = 'draft'"
            ),
            'assignmentRules' => $this->countRows(
                'SELECT COUNT(*) AS total FROM nesp_interviewer_role_rule WHERE is_active = 1'
            ),
            'availabilityBlocks' => $this->countRows(
                'SELECT COUNT(*) AS total FROM nesp_interviewer_availability WHERE is_active = 1'
            ),
            'openInterviewSlots' => $this->countRows(
                "SELECT COUNT(*) AS total FROM nesp_interview_slot WHERE slot_status_key = 'open'"
            )
        );
    }

    public function getDashboardSummary()
    {
        return array(
            'publicJobs' => $this->countRows(
                "SELECT COUNT(*) AS total FROM joborder WHERE public = 1 AND status = 'Active'"
            ),
            'allCandidates' => $this->countRows(
                'SELECT COUNT(*) AS total FROM candidate WHERE is_active = 1'
            ),
            'workflowTrackedCandidates' => $this->countRows(
                'SELECT COUNT(*) AS total FROM nesp_candidate_workflow'
            ),
            'needsReview' => $this->countRows(
                "SELECT COUNT(*) AS total
                 FROM nesp_candidate_workflow cw
                 INNER JOIN nesp_workflow_stage ws
                    ON ws.workflow_stage_id = cw.workflow_stage_id
                 WHERE ws.stage_key IN ('new', 'needs_review', 'follow_up_needed')"
            ),
            'integrationsEnabled' => $this->countRows(
                'SELECT COUNT(*) AS total FROM nesp_feature_flag WHERE is_enabled = 1'
            ),
            'recentAuditEvents' => $this->countRows(
                'SELECT COUNT(*) AS total
                 FROM nesp_audit_event
                 WHERE date_created >= DATE_SUB(NOW(), INTERVAL 7 DAY)'
            ),
            'interviewsThisWeek' => $this->countRows(
                'SELECT COUNT(*) AS total
                 FROM nesp_interview
                 WHERE scheduled_start >= CURDATE()
                   AND scheduled_start < DATE_ADD(CURDATE(), INTERVAL 7 DAY)
                   AND status_key IN ("scheduled", "invitation_pending", "invitation_sent", "confirmed", "reschedule_needed")'
            ),
            'overdueItems' => $this->countRows(
                'SELECT COUNT(*) AS total
                 FROM nesp_candidate_workflow cw
                 INNER JOIN nesp_workflow_stage ws
                    ON ws.workflow_stage_id = cw.workflow_stage_id
                 WHERE cw.due_at IS NOT NULL
                   AND cw.due_at < NOW()
                   AND ws.is_terminal = 0'
            ),
            'assignmentRules' => $this->countRows(
                'SELECT COUNT(*) AS total FROM nesp_interviewer_role_rule WHERE is_active = 1'
            ),
            'availabilityBlocks' => $this->countRows(
                'SELECT COUNT(*) AS total FROM nesp_interviewer_availability WHERE is_active = 1'
            )
        );
    }

    public function getRecruitingPlatformMatrix()
    {
        return NESPRecruitingAds::getPlatformMatrix();
    }

    public function getRecruitingAdTemplates()
    {
        return NESPRecruitingAds::getRequestedRoleAdTemplates();
    }

    public function getCentralApplicationDestinations()
    {
        return NESPRecruitingAds::getCentralApplicationDestinations();
    }

    public function getRecruitingCampaignControls()
    {
        $controls = array();
        foreach (NESPRecruitingAds::getPlatformMatrix() as $platform)
        {
            $controls[$platform['platform_key']] = array(
                'platform_key' => $platform['platform_key'],
                'display_name' => $platform['platform'],
                'campaign_status' => 'draft',
                'renewal_date' => '',
                'manual_spend' => '0.00',
                'owner_approval_required' => 1,
                'notes' => '',
                'date_modified' => ''
            );
        }

        $rows = $this->_db->getAllAssoc(
            'SELECT
                platform_key,
                display_name,
                campaign_status,
                renewal_date,
                manual_spend,
                owner_approval_required,
                notes,
                date_modified
             FROM
                nesp_recruiting_campaign_control
             ORDER BY
                display_name'
        );

        foreach ($rows as $row)
        {
            $key = $row['platform_key'];
            if (isset($controls[$key]))
            {
                $controls[$key] = array_merge($controls[$key], $row);
            }
        }

        return array_values($controls);
    }

    public function saveRecruitingCampaignControl($input, $actorUserID)
    {
        $platformKey = isset($input['platformKey']) ? preg_replace('/[^a-z0-9_]/', '', strtolower((string) $input['platformKey'])) : '';

        $matrix = NESPRecruitingAds::getPlatformMatrix();
        $platform = null;
        foreach ($matrix as $row)
        {
            if ($row['platform_key'] === $platformKey)
            {
                $platform = $row;
                break;
            }
        }

        if ($platform === null)
        {
            return false;
        }

        $allowedStatuses = array('draft', 'ready_for_review', 'approved_to_publish_manually', 'published_manually', 'paused', 'expired', 'removed');
        $status = isset($input['campaignStatus']) ? strtolower(trim((string) $input['campaignStatus'])) : 'draft';
        if (!in_array($status, $allowedStatuses, true))
        {
            $status = 'draft';
        }

        $renewalDate = isset($input['renewalDate']) ? trim((string) $input['renewalDate']) : '';
        if ($renewalDate !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $renewalDate))
        {
            $renewalDate = '';
        }

        $manualSpend = isset($input['manualSpend']) ? preg_replace('/[^0-9.]/', '', (string) $input['manualSpend']) : '0';
        $manualSpend = number_format(max(0, (float) $manualSpend), 2, '.', '');
        $notes = isset($input['notes']) ? substr(trim((string) $input['notes']), 0, 1000) : '';

        $this->_db->query(
            sprintf(
                'INSERT INTO nesp_recruiting_campaign_control
                    (platform_key, display_name, campaign_status, renewal_date, manual_spend, owner_approval_required, notes, updated_by_user_id, date_created, date_modified)
                 VALUES
                    (%s, %s, %s, %s, %s, 1, %s, %s, NOW(), NOW())
                 ON DUPLICATE KEY UPDATE
                    display_name = VALUES(display_name),
                    campaign_status = VALUES(campaign_status),
                    renewal_date = VALUES(renewal_date),
                    manual_spend = VALUES(manual_spend),
                    owner_approval_required = 1,
                    notes = VALUES(notes),
                    updated_by_user_id = VALUES(updated_by_user_id),
                    date_modified = NOW()',
                $this->_db->makeQueryString($platformKey),
                $this->_db->makeQueryString($platform['platform']),
                $this->_db->makeQueryString($status),
                $renewalDate === '' ? 'NULL' : $this->_db->makeQueryString($renewalDate),
                $this->_db->makeQueryString($manualSpend),
                $this->_db->makeQueryString($notes),
                $this->_db->makeQueryInteger($actorUserID)
            )
        );

        $this->logAuditEvent($actorUserID, 'recruiting_campaign_control_saved', 'nesp_recruiting_campaign_control', 0, array('platform_key' => $platformKey, 'campaign_status' => $status));
        return true;
    }

    public function getRecruitingSourceReport()
    {
        $report = array();
        $spendByPlatform = array();
        foreach ($this->getRecruitingCampaignControls() as $control)
        {
            $spendByPlatform[$control['platform_key']] = (float) $control['manual_spend'];
        }

        foreach (NESPRecruitingAds::getSourceOptions() as $sourceKey => $label)
        {
            $sourceLabel = NESPRecruitingAds::getCandidateSourceLabel($sourceKey);
            $sourceSQL = $this->_db->makeQueryString($sourceLabel);
            $applications = $this->countRows(
                sprintf(
                    'SELECT COUNT(*) AS total
                     FROM candidate
                     WHERE is_active = 1
                       AND source = %s',
                    $sourceSQL
                )
            );
            $qualified = $this->countRows(
                sprintf(
                    'SELECT COUNT(DISTINCT c.candidate_id) AS total
                     FROM candidate c
                     INNER JOIN nesp_candidate_workflow cw
                        ON cw.candidate_id = c.candidate_id
                     INNER JOIN nesp_workflow_stage ws
                        ON ws.workflow_stage_id = cw.workflow_stage_id
                     WHERE c.is_active = 1
                       AND c.source = %s
                       AND ws.stage_key NOT IN ("new", "needs_review", "follow_up_needed")',
                    $sourceSQL
                )
            );
            $scheduled = $this->countRows(
                sprintf(
                    'SELECT COUNT(*) AS total
                     FROM nesp_vapi_phone_screen ps
                     INNER JOIN candidate c
                        ON c.candidate_id = ps.candidate_id
                     WHERE c.source = %s
                       AND ps.status_key IN ("phone_screen_scheduled", "call_due", "call_started", "ringing", "in_progress", "completed", "no_answer")',
                    $sourceSQL
                )
            );
            $completed = $this->countRows(
                sprintf(
                    'SELECT COUNT(*) AS total
                     FROM nesp_vapi_phone_screen ps
                     INNER JOIN candidate c
                        ON c.candidate_id = ps.candidate_id
                     WHERE c.source = %s
                       AND ps.status_key = "completed"',
                    $sourceSQL
                )
            );

            $spend = isset($spendByPlatform[$sourceKey]) ? $spendByPlatform[$sourceKey] : 0;
            $report[] = array(
                'source_key' => $sourceKey,
                'platform' => $label,
                'candidate_source_label' => $sourceLabel,
                'applications' => $applications,
                'qualified_applicants' => $qualified,
                'scheduled_phone_screens' => $scheduled,
                'completed_phone_screens' => $completed,
                'manual_spend' => number_format($spend, 2, '.', ''),
                'cost_per_applicant' => $applications > 0 ? number_format($spend / $applications, 2, '.', '') : ''
            );
        }

        return $report;
    }

    public function getDashboardQueues()
    {
        $rows = $this->getDashboardCandidateRows(120);
        $queues = $this->buildDashboardQueueSet($rows, true);

        foreach ($queues as $queueKey => $cards)
        {
            $queues[$queueKey] = array_slice($cards, 0, 12);
        }

        return $queues;
    }

    public function getDashboardQueueCounts()
    {
        $rows = $this->getDashboardCandidateRows(120);
        $queues = $this->buildDashboardQueueSet($rows, false);
        $counts = array();

        foreach ($queues as $queueKey => $cards)
        {
            $counts[$queueKey] = count($cards);
        }

        return $counts;
    }

    private function buildDashboardQueueSet($rows, $prioritizeOverdue)
    {
        $queues = array(
            'needsCraig' => array(),
            'waitingApplicant' => array(),
            'waitingInterviewer' => array(),
            'upcomingInterviews' => array(),
            'recentlyCompleted' => array()
        );
        $seen = array(
            'needsCraig' => array(),
            'waitingApplicant' => array(),
            'waitingInterviewer' => array(),
            'upcomingInterviews' => array(),
            'recentlyCompleted' => array()
        );
        $definitions = self::getQueueDefinitions();

        foreach ($rows as $row)
        {
            $card = $this->normalizeDashboardCard($row);
            $card['assignable_interviewers'] = array();
            $card['assignment_block_reason'] = '';
            if (in_array($row['stage_key'], array('new', 'interview_requested', 'needs_review', 'phone_screen_complete'), true))
            {
                $card['assignable_interviewers'] = $this->getEligibleInterviewersForAssignment((int) $row['joborder_id']);
                if (empty($card['assignable_interviewers']))
                {
                    $card['assignment_block_reason'] = ((int) $row['joborder_id'] === 41001)
                        ? 'Customer Service stays with Craig in Needs Craig.'
                        : 'No active interviewer is approved and open for this role yet.';
                }
            }
            $cardKey = $row['candidate_workflow_id'];
            foreach (array('needsCraig', 'waitingApplicant', 'waitingInterviewer', 'recentlyCompleted') as $queueKey)
            {
                if (in_array($row['stage_key'], $definitions[$queueKey]['stageKeys']) && !isset($seen[$queueKey][$cardKey]))
                {
                    $queues[$queueKey][] = $card;
                    $seen[$queueKey][$cardKey] = true;
                }
            }

            if ($row['scheduled_start'] !== null && $row['scheduled_start'] !== ''
                && strtotime($row['scheduled_start']) >= time()
                && in_array($row['interview_status_key'], array('scheduled', 'invitation_pending', 'invitation_sent', 'confirmed', 'reschedule_needed'))
                && !isset($seen['upcomingInterviews'][$cardKey]))
            {
                $queues['upcomingInterviews'][] = $card;
                $seen['upcomingInterviews'][$cardKey] = true;
            }

            if ($row['due_at'] !== null && $row['due_at'] !== ''
                && strtotime($row['due_at']) < time()
                && !in_array($row['stage_key'], array('hired', 'hold', 'not_selected', 'withdrawn', 'declined'))
                && !isset($seen['needsCraig'][$cardKey]))
            {
                $card['summary'] = 'Overdue item: ' . $card['summary'];
                if ($prioritizeOverdue)
                {
                    array_unshift($queues['needsCraig'], $card);
                }
                else
                {
                    $queues['needsCraig'][] = $card;
                }
                $seen['needsCraig'][$cardKey] = true;
            }
        }

        return $queues;
    }

    public function getDashboardCandidateRows($limit)
    {
        $limit = max(1, min(250, (int) $limit));

        return $this->_db->getAllAssoc(
            sprintf(
                'SELECT
                    cw.candidate_workflow_id,
                    cw.candidate_id,
                    cw.joborder_id,
                    cw.waiting_on_key,
                    cw.summary,
                    cw.next_action_label,
                    cw.due_at,
                    cw.date_modified,
                    c.first_name,
                    c.last_name,
                    c.email1 AS candidate_email,
                    jo.title AS role_title,
                    ws.stage_key,
                    ws.display_name AS stage_name,
                    i.interview_id,
                    i.scheduled_start,
                    i.scheduled_end,
                    i.status_key AS interview_status_key,
                    i.invitation_status_key,
                    i.outcome_key,
                    ip.display_name AS interviewer_name,
                    (
                        SELECT GROUP_CONCAT(DISTINCT assigned_ip.display_name ORDER BY assigned_ip.display_name SEPARATOR ", ")
                        FROM nesp_interviewer_candidate_grant assigned_grant
                        INNER JOIN nesp_interviewer_profile assigned_ip
                            ON assigned_ip.interviewer_profile_id = assigned_grant.interviewer_profile_id
                        WHERE assigned_grant.candidate_id = cw.candidate_id
                          AND assigned_grant.joborder_id = cw.joborder_id
                          AND assigned_grant.date_revoked IS NULL
                    ) AS assigned_interviewer_names,
                    sr.status_key AS scorecard_status_key,
                    sr.overall_recommendation
                FROM
                    nesp_candidate_workflow cw
                INNER JOIN candidate c
                    ON c.candidate_id = cw.candidate_id
                INNER JOIN joborder jo
                    ON jo.joborder_id = cw.joborder_id
                INNER JOIN nesp_workflow_stage ws
                    ON ws.workflow_stage_id = cw.workflow_stage_id
                LEFT JOIN nesp_interview i
                    ON i.interview_id = (
                        SELECT MAX(i2.interview_id)
                        FROM nesp_interview i2
                        WHERE i2.candidate_id = cw.candidate_id
                          AND i2.joborder_id = cw.joborder_id
                    )
                LEFT JOIN nesp_interviewer_profile ip
                    ON ip.interviewer_profile_id = i.interviewer_profile_id
                LEFT JOIN nesp_scorecard_response sr
                    ON sr.scorecard_response_id = (
                        SELECT MAX(sr2.scorecard_response_id)
                        FROM nesp_scorecard_response sr2
                        WHERE sr2.candidate_id = cw.candidate_id
                          AND sr2.joborder_id = cw.joborder_id
                    )
                WHERE
                    c.is_active = 1
                ORDER BY
                    CASE WHEN cw.due_at IS NULL THEN 1 ELSE 0 END,
                    cw.due_at ASC,
                    cw.date_modified DESC
                LIMIT %s',
                $this->_db->makeQueryInteger($limit)
            )
        );
    }

    public function getUpcomingInterviews($limit)
    {
        $limit = max(1, min(100, (int) $limit));

        $rows = $this->_db->getAllAssoc(
            sprintf(
                'SELECT
                    i.interview_id,
                    i.candidate_id,
                    i.joborder_id,
                    CONCAT(c.first_name, " ", c.last_name) AS candidate_name,
                    jo.title AS role_title,
                    ip.display_name AS interviewer_name,
                    i.scheduled_start,
                    i.scheduled_end,
                    TIMESTAMPDIFF(MINUTE, i.scheduled_start, i.scheduled_end) AS duration_minutes,
                    i.status_key,
                    i.invitation_status_key,
                    i.outcome_key
                FROM
                    nesp_interview i
                INNER JOIN candidate c
                    ON c.candidate_id = i.candidate_id
                INNER JOIN joborder jo
                    ON jo.joborder_id = i.joborder_id
                LEFT JOIN nesp_interviewer_profile ip
                    ON ip.interviewer_profile_id = i.interviewer_profile_id
                WHERE
                    i.scheduled_start IS NOT NULL
                    AND i.scheduled_start >= NOW()
                    AND i.status_key IN ("scheduled", "invitation_pending", "invitation_sent", "confirmed", "reschedule_needed")
                ORDER BY
                    i.scheduled_start ASC
                LIMIT %s',
                $this->_db->makeQueryInteger($limit)
            )
        );

        $labels = self::getManualInterviewStatusLabels();
        foreach ($rows as $index => $row)
        {
            $rows[$index]['status_label'] = isset($labels[$row['status_key']]) ? $labels[$row['status_key']] : $row['status_key'];
        }
        return $rows;
    }

    public function getInterviewerProfiles()
    {
        $profileSelect = array(
            'ip.interviewer_profile_id',
            'ip.user_id',
            'ip.display_name',
            'ip.email',
            'ip.role_key',
            'ip.is_active',
            'ip.can_view_resume',
            'ip.can_add_notes',
            'ip.can_submit_scorecard',
            'u.user_name AS username',
            'u.access_level AS user_access_level',
            'u.categories AS user_categories',
            'MAX(ul.date) AS login_last_seen_at',
            'ip.date_modified'
        );

        $optionalColumns = array(
            'account_state_key' => '"profile_created"',
            'timezone' => '"America/New_York"',
            'availability_status_key' => '"open"',
            'availability_closed_until' => 'NULL',
            'availability_close_reason' => '""',
            'max_interviews_per_day' => '3',
            'max_interviews_per_week' => '12',
            'min_notice_minutes' => '1440',
            'default_interview_minutes' => '30',
            'buffer_minutes' => '15',
            'earliest_time' => '"09:00:00"',
            'latest_time' => '"17:00:00"',
            'craig_must_attend' => '0',
            'may_recommend' => '1',
            'private_admin_notes' => '""',
            'last_login_at' => 'NULL',
            'email_warning' => '""',
            'default_zoom_join_url' => '""'
        );

        foreach ($optionalColumns as $column => $fallback)
        {
            $profileSelect[] = $this->selectOptionalColumn('nesp_interviewer_profile', 'ip', $column, $fallback) . ' AS ' . $column;
        }

        $jobRoleSelect = $this->isTableInstalled('nesp_interviewer_job_role')
            ? 'GROUP_CONCAT(DISTINCT ijr.joborder_id ORDER BY ijr.joborder_id SEPARATOR ",") AS approved_joborder_ids'
            : '"" AS approved_joborder_ids';
        $jobRoleJoin = $this->isTableInstalled('nesp_interviewer_job_role')
            ? 'LEFT JOIN nesp_interviewer_job_role ijr ON ijr.interviewer_profile_id = ip.interviewer_profile_id AND ijr.is_active = 1'
            : '';

        $rows = $this->_db->getAllAssoc(
            'SELECT
                ' . implode(",\n                ", $profileSelect) . ',
                COUNT(DISTINCT cg.grant_id) AS active_grants
                , ' . $jobRoleSelect . '
             FROM
                nesp_interviewer_profile ip
             LEFT JOIN user u
                ON u.user_id = ip.user_id
             LEFT JOIN user_login ul
                ON ul.user_id = ip.user_id
                AND ul.successful = 1
             LEFT JOIN nesp_interviewer_candidate_grant cg
                ON cg.interviewer_profile_id = ip.interviewer_profile_id
                AND cg.date_revoked IS NULL
             ' . $jobRoleJoin . '
             GROUP BY
                ip.interviewer_profile_id
             ORDER BY
                ip.is_active DESC,
                ip.display_name ASC'
        );

        foreach ($rows as $index => $row)
        {
            $state = isset($row['account_state_key']) ? $row['account_state_key'] : 'profile_created';
            $rows[$index]['last_login_display'] = empty($row['login_last_seen_at']) ? $row['last_login_at'] : $row['login_last_seen_at'];
            if ((int) $row['is_active'] === 1 && $state === 'active')
            {
                $rows[$index]['state_badge'] = 'Active';
            }
            else if ((int) $row['user_id'] > 0 && in_array($state, array('account_prepared', 'temporary_password_set', 'awaiting_craig_activation'), true))
            {
                $rows[$index]['state_badge'] = 'Prepared but not active';
            }
            else if (in_array($state, array('suspended', 'deactivated', 'permanently_disabled'), true))
            {
                $rows[$index]['state_badge'] = 'Suspended/deactivated';
            }
            else
            {
                $rows[$index]['state_badge'] = 'Profile only';
            }
            $rows[$index]['can_prepare_login'] = (int) $row['user_id'] <= 0;
            $rows[$index]['can_activate_login'] = (int) $row['user_id'] > 0 && in_array($state, array('account_prepared', 'temporary_password_set', 'awaiting_craig_activation'), true);
            $rows[$index]['can_suspend_login'] = (int) $row['is_active'] === 1 && $state === 'active';
            $rows[$index]['can_reactivate_login'] = (int) $row['user_id'] > 0 && in_array($state, array('suspended', 'deactivated'), true);
            $rows[$index]['can_reset_temp_password'] = (int) $row['user_id'] > 0 && $state !== 'permanently_disabled';
            $rows[$index]['can_disable_login'] = (int) $row['user_id'] > 0 && $state !== 'permanently_disabled';
            $rows[$index]['can_revoke_grants'] = (int) $row['active_grants'] > 0;
        }

        return $rows;
    }

    public function getInterviewerRoleRules()
    {
        return $this->_db->getAllAssoc(
            'SELECT
                rr.role_rule_id,
                rr.interviewer_profile_id,
                rr.joborder_id,
                rr.role_match_text,
                rr.assignment_mode,
                rr.priority,
                rr.is_active,
                rr.notes,
                rr.date_modified,
                ip.display_name AS interviewer_name,
                jo.title AS job_title
             FROM
                nesp_interviewer_role_rule rr
             LEFT JOIN nesp_interviewer_profile ip
                ON ip.interviewer_profile_id = rr.interviewer_profile_id
             LEFT JOIN joborder jo
                ON jo.joborder_id = rr.joborder_id
             ORDER BY
                rr.is_active DESC,
                rr.priority ASC,
                rr.role_match_text ASC'
        );
    }

    public function createInterviewerRoleRule($interviewerProfileID, $jobOrderID, $roleMatchText, $assignmentMode, $priority, $notes, $actorUserID)
    {
        $interviewerProfileID = (int) $interviewerProfileID;
        $jobOrderID = (int) $jobOrderID;
        $roleMatchText = trim($roleMatchText);
        $assignmentMode = in_array($assignmentMode, array('suggest_only', 'manual_review')) ? $assignmentMode : 'suggest_only';
        $priority = max(1, min(999, (int) $priority));
        $notes = trim($notes);

        if ($interviewerProfileID <= 0 || ($jobOrderID <= 0 && $roleMatchText === ''))
        {
            return false;
        }

        $sql = sprintf(
            'INSERT INTO nesp_interviewer_role_rule
                (interviewer_profile_id, joborder_id, role_match_text, assignment_mode, priority, is_active, notes, created_by_user_id, date_created, date_modified)
             VALUES
                (%s, %s, %s, %s, %s, 1, %s, %s, NOW(), NOW())',
            $this->_db->makeQueryInteger($interviewerProfileID),
            $jobOrderID <= 0 ? 'NULL' : $this->_db->makeQueryInteger($jobOrderID),
            $this->_db->makeQueryString($roleMatchText),
            $this->_db->makeQueryString($assignmentMode),
            $this->_db->makeQueryInteger($priority),
            $this->_db->makeQueryString($notes),
            $actorUserID === null ? 'NULL' : $this->_db->makeQueryInteger($actorUserID)
        );
        $this->_db->query($sql);
        $ruleID = $this->_db->getLastInsertID();

        $this->logAuditEvent(
            $actorUserID,
            'interviewer_role_rule_created',
            'interviewer_role_rule',
            $ruleID,
            array('interviewer_profile_id' => $interviewerProfileID, 'assignment_mode' => $assignmentMode)
        );

        return $ruleID;
    }

    public function deactivateInterviewerRoleRule($ruleID, $actorUserID)
    {
        $ruleID = (int) $ruleID;
        if ($ruleID <= 0)
        {
            return false;
        }

        $rule = $this->_db->getAssoc(sprintf(
            'SELECT role_rule_id, interviewer_profile_id, joborder_id
             FROM nesp_interviewer_role_rule
             WHERE role_rule_id = %s
               AND is_active = 1
             LIMIT 1',
            $this->_db->makeQueryInteger($ruleID)
        ));
        if (empty($rule))
        {
            return false;
        }

        $this->_db->query(sprintf(
            'UPDATE nesp_interviewer_role_rule
             SET is_active = 0,
                 date_modified = NOW()
             WHERE role_rule_id = %s
               AND is_active = 1',
            $this->_db->makeQueryInteger($ruleID)
        ));
        if ($this->_db->getAffectedRows() !== 1)
        {
            return false;
        }

        $this->logAuditEvent($actorUserID, 'interviewer_role_rule_deactivated', 'interviewer_role_rule', $ruleID, array(
            'interviewer_profile_id' => (int) $rule['interviewer_profile_id'],
            'joborder_id' => (int) $rule['joborder_id']
        ));

        return true;
    }

    public function createCandidateGrant($interviewerProfileID, $candidateID, $jobOrderID, $actorUserID)
    {
        $interviewerProfileID = (int) $interviewerProfileID;
        $candidateID = (int) $candidateID;
        $jobOrderID = (int) $jobOrderID;

        if ($interviewerProfileID <= 0 || $candidateID <= 0 || $jobOrderID <= 0)
        {
            return false;
        }

        if ($jobOrderID === 41001)
        {
            $this->logAuditEvent(
                $actorUserID,
                'interviewer_candidate_grant_rejected',
                'interviewer_profile',
                $interviewerProfileID,
                array('candidate_id' => $candidateID, 'joborder_id' => $jobOrderID, 'reason' => 'customer_service_craig_manual_only')
            );
            return false;
        }

        $candidateJobOrder = $this->_db->getAssoc(
            sprintf(
                'SELECT cjo.candidate_joborder_id
                 FROM candidate_joborder cjo
                 INNER JOIN candidate c
                    ON c.candidate_id = cjo.candidate_id
                    AND c.is_active = 1
                 INNER JOIN joborder jo
                    ON jo.joborder_id = cjo.joborder_id
                 WHERE cjo.candidate_id = %s
                   AND cjo.joborder_id = %s
                 LIMIT 1',
                $this->_db->makeQueryInteger($candidateID),
                $this->_db->makeQueryInteger($jobOrderID)
            )
        );
        if (empty($candidateJobOrder))
        {
            return false;
        }

        if (!$this->interviewerCanReceiveAssignment($interviewerProfileID, $jobOrderID))
        {
            $this->logAuditEvent(
                $actorUserID,
                'interviewer_candidate_grant_rejected',
                'interviewer_profile',
                $interviewerProfileID,
                array('candidate_id' => $candidateID, 'joborder_id' => $jobOrderID, 'reason' => 'inactive_closed_or_unapproved_role')
            );
            return false;
        }

        $existing = $this->_db->getAssoc(
            sprintf(
                'SELECT grant_id
                 FROM nesp_interviewer_candidate_grant
                 WHERE interviewer_profile_id = %s
                   AND candidate_id = %s
                   AND joborder_id = %s
                   AND date_revoked IS NULL
                 LIMIT 1',
                $this->_db->makeQueryInteger($interviewerProfileID),
                $this->_db->makeQueryInteger($candidateID),
                $this->_db->makeQueryInteger($jobOrderID)
            )
        );
        if (!empty($existing))
        {
            $this->logAuditEvent(
                $actorUserID,
                'interviewer_candidate_grant_duplicate',
                'interviewer_candidate_grant',
                (int) $existing['grant_id'],
                array('interviewer_profile_id' => $interviewerProfileID, 'candidate_id' => $candidateID, 'joborder_id' => $jobOrderID)
            );
            return (int) $existing['grant_id'];
        }

        $sql = sprintf(
            'INSERT INTO nesp_interviewer_candidate_grant
                (interviewer_profile_id, candidate_id, joborder_id, granted_by_user_id, access_level_key, can_view_resume, can_add_notes, can_submit_scorecard, date_granted, date_revoked)
             VALUES
                (%s, %s, %s, %s, "interview", 1, 1, 1, NOW(), NULL)',
            $this->_db->makeQueryInteger($interviewerProfileID),
            $this->_db->makeQueryInteger($candidateID),
            $this->_db->makeQueryInteger($jobOrderID),
            $actorUserID === null ? 'NULL' : $this->_db->makeQueryInteger($actorUserID)
        );
        $this->_db->query($sql);
        $grantID = $this->_db->getLastInsertID();

        $this->logAuditEvent(
            $actorUserID,
            'interviewer_candidate_grant_created',
            'interviewer_candidate_grant',
            $grantID,
            array('interviewer_profile_id' => $interviewerProfileID, 'candidate_id' => $candidateID, 'joborder_id' => $jobOrderID)
        );

        return $grantID;
    }

    public function getEligibleInterviewersForAssignment($jobOrderID)
    {
        $jobOrderID = (int) $jobOrderID;
        if ($jobOrderID <= 0 || $jobOrderID === 41001 || !$this->isTableInstalled('nesp_interviewer_job_role'))
        {
            return array();
        }

        $availabilityColumn = $this->isColumnInstalled('nesp_interviewer_profile', 'availability_status_key')
            ? 'AND ip.availability_status_key = "open"'
            : '';
        $accountStateColumn = $this->isColumnInstalled('nesp_interviewer_profile', 'account_state_key')
            ? 'AND ip.account_state_key = "active"'
            : '';

        return $this->_db->getAllAssoc(
            sprintf(
                'SELECT DISTINCT
                    ip.interviewer_profile_id,
                    ip.display_name,
                    ip.email,
                    ip.role_key
                 FROM nesp_interviewer_profile ip
                 INNER JOIN nesp_interviewer_job_role ijr
                    ON ijr.interviewer_profile_id = ip.interviewer_profile_id
                    AND ijr.joborder_id = %s
                    AND ijr.is_active = 1
                 WHERE ip.is_active = 1
                   ' . $availabilityColumn . '
                   ' . $accountStateColumn . '
                 ORDER BY ip.display_name ASC',
                $this->_db->makeQueryInteger($jobOrderID)
            )
        );
    }

    public function getActiveCandidateGrants()
    {
        return $this->_db->getAllAssoc(
            'SELECT
                cg.grant_id,
                cg.interviewer_profile_id,
                cg.candidate_id,
                cg.joborder_id,
                cg.date_granted,
                ip.display_name AS interviewer_name,
                ip.email AS interviewer_email,
                CONCAT(c.first_name, " ", c.last_name) AS candidate_name,
                c.email1 AS candidate_email,
                jo.title AS role_title
             FROM nesp_interviewer_candidate_grant cg
             INNER JOIN nesp_interviewer_profile ip
                ON ip.interviewer_profile_id = cg.interviewer_profile_id
             INNER JOIN candidate c
                ON c.candidate_id = cg.candidate_id
             INNER JOIN joborder jo
                ON jo.joborder_id = cg.joborder_id
             WHERE cg.date_revoked IS NULL
             ORDER BY ip.display_name ASC, c.last_name ASC, c.first_name ASC'
        );
    }

    public function revokeCandidateGrant($grantID, $actorUserID)
    {
        $grantID = (int) $grantID;
        if ($grantID <= 0)
        {
            return false;
        }
        $grant = $this->_db->getAssoc(sprintf(
            'SELECT *
             FROM nesp_interviewer_candidate_grant
             WHERE grant_id = %s
               AND date_revoked IS NULL
             LIMIT 1',
            $this->_db->makeQueryInteger($grantID)
        ));
        if (empty($grant))
        {
            return false;
        }
        $this->_db->query(sprintf(
            'UPDATE nesp_interviewer_candidate_grant
             SET date_revoked = NOW()
             WHERE grant_id = %s
               AND date_revoked IS NULL',
            $this->_db->makeQueryInteger($grantID)
        ));
        $this->logAuditEvent($actorUserID, 'interviewer_candidate_grant_revoked', 'interviewer_candidate_grant', $grantID, array(
            'interviewer_profile_id' => (int) $grant['interviewer_profile_id'],
            'candidate_id' => (int) $grant['candidate_id'],
            'joborder_id' => (int) $grant['joborder_id']
        ));
        return $this->_db->getAffectedRows() === 1;
    }

    public function getCandidateInterviewPreview($candidateID, $jobOrderID, $interviewID = 0)
    {
        $candidateID = (int) $candidateID;
        $jobOrderID = (int) $jobOrderID;
        $interviewID = (int) $interviewID;
        if ($candidateID <= 0 || $jobOrderID <= 0)
        {
            return array();
        }

        $row = $this->_db->getAssoc(
            sprintf(
                'SELECT
                    c.candidate_id,
                    c.first_name,
                    c.last_name,
                    jo.joborder_id,
                    jo.title AS role_title,
                    cw.candidate_workflow_id,
                    ws.stage_key,
                    ws.display_name AS stage_name
                 FROM candidate c
                 INNER JOIN candidate_joborder cjo
                    ON cjo.candidate_id = c.candidate_id
                    AND cjo.joborder_id = %s
                 INNER JOIN joborder jo
                    ON jo.joborder_id = cjo.joborder_id
                 LEFT JOIN nesp_candidate_workflow cw
                    ON cw.candidate_id = c.candidate_id
                    AND cw.joborder_id = jo.joborder_id
                 LEFT JOIN nesp_workflow_stage ws
                    ON ws.workflow_stage_id = cw.workflow_stage_id
                 WHERE c.candidate_id = %s
                   AND c.is_active = 1
                 LIMIT 1',
                $this->_db->makeQueryInteger($jobOrderID),
                $this->_db->makeQueryInteger($candidateID)
            )
        );
        if (empty($row))
        {
            return array();
        }
        $row['candidate_name'] = trim($row['first_name'] . ' ' . $row['last_name']);
        $row['interviewer_profiles'] = $this->getInterviewerProfiles();
        $row['existing_interview'] = $interviewID > 0 ? $this->getInterviewDetail($interviewID) : array();
        $row['active_interviews'] = $this->getActiveInterviewsForCandidateJob($candidateID, $jobOrderID, $interviewID);
        $row['default_timezone'] = 'America/New_York';
        $row['default_duration_minutes'] = 30;
        $row['interviewer_zoom_links_enabled'] = $this->isFeatureFlagEnabled('NESP_INTERVIEWER_ZOOM_LINKS_ENABLED');
        return $row;
    }

    public function routeCareerPortalApplicationToNeedsCraig($candidateID, $jobOrderID, $actorUserID = null, $isNewApplication = true)
    {
        $candidateID = (int) $candidateID;
        $jobOrderID = (int) $jobOrderID;
        if ($candidateID <= 0 || $jobOrderID <= 0)
        {
            return false;
        }

        if (!$this->isSchemaInstalled() || !$this->isFeatureFlagEnabled('NESP_WORKFLOW_ENABLED'))
        {
            return false;
        }

        $row = $this->_db->getAssoc(
            sprintf(
                'SELECT
                    c.candidate_id,
                    c.first_name,
                    c.last_name,
                    jo.joborder_id,
                    jo.title AS role_title,
                    cw.candidate_workflow_id,
                    ws.stage_key
                 FROM candidate c
                 INNER JOIN candidate_joborder cjo
                    ON cjo.candidate_id = c.candidate_id
                    AND cjo.joborder_id = %s
                 INNER JOIN joborder jo
                    ON jo.joborder_id = cjo.joborder_id
                 LEFT JOIN nesp_candidate_workflow cw
                    ON cw.candidate_id = c.candidate_id
                    AND cw.joborder_id = jo.joborder_id
                 LEFT JOIN nesp_workflow_stage ws
                    ON ws.workflow_stage_id = cw.workflow_stage_id
                 WHERE c.candidate_id = %s
                   AND c.is_active = 1
                   AND jo.public = 1
                 LIMIT 1',
                $this->_db->makeQueryInteger($jobOrderID),
                $this->_db->makeQueryInteger($candidateID)
            )
        );
        if (empty($row))
        {
            return false;
        }

        if (!empty($row['candidate_workflow_id']) && !in_array($row['stage_key'], array('new', 'needs_review', 'follow_up_needed', 'applicant_clarification_requested')))
        {
            $this->logAuditEvent($actorUserID, 'career_portal_workflow_route_skipped', 'candidate_workflow', $row['candidate_workflow_id'], array(
                'candidate_id' => $candidateID,
                'joborder_id' => $jobOrderID,
                'existing_stage_key' => $row['stage_key']
            ));
            return false;
        }

        $roleTitle = isset($row['role_title']) ? trim($row['role_title']) : 'the selected role';
        if ($roleTitle === '')
        {
            $roleTitle = 'the selected role';
        }

        $stageKey = $isNewApplication ? 'new' : 'needs_review';
        $summary = ($isNewApplication ? 'New public application submitted' : 'Applicant reapplied')
            . ' through the careers portal for ' . $roleTitle . '.';

        return $this->prepareQuestionnaireForHumanReview(
            $candidateID,
            $jobOrderID,
            $actorUserID,
            $summary,
            $stageKey
        );
    }

    /**
     * Prepare a role-specific questionnaire link. Delivery stays manual unless
     * the explicit applicant-email feature flag and sender configuration are ready.
     * This never makes an employment decision.
     */
    public function prepareQuestionnaireForHumanReview($candidateID, $jobOrderID, $actorUserID, $summary, $stageKey = 'new')
    {
        // requestQuestionnaire() stores only a hash and reuses an active row.
        // Delivery is intentionally deferred until after the candidate workflow
        // row has been written. A sender must never email a link for an applicant
        // who is not yet visible in the human-review queue.
        $questionnaire = $this->requestQuestionnaire($candidateID, $jobOrderID, $actorUserID);
        if (is_array($questionnaire) && !empty($questionnaire['questionnaire_id']))
        {
            $questionnaireDetail = $this->getQuestionnaireDetail((int) $questionnaire['questionnaire_id']);
            $questionnaireStatus = isset($questionnaireDetail['status_key']) ? $questionnaireDetail['status_key'] : '';
            if (in_array($questionnaireStatus, array('waiting', 'in_progress')))
            {
                return $this->setCandidateWorkflowStage(
                    $candidateID,
                    $jobOrderID,
                    'applicant_clarification_requested',
                    'Applicant',
                    $summary . ' The secure questionnaire link was shared and is awaiting completion.',
                    'Wait for questionnaire',
                    $actorUserID
                );
            }

            if (!$this->setCandidateWorkflowStage(
                $candidateID,
                $jobOrderID,
                $stageKey,
                'Craig',
                $summary . ' A role-specific secure questionnaire link is ready for human sending.',
                'Send questionnaire',
                $actorUserID
            ))
            {
                return false;
            }

            // The candidate, job link, workflow row, and questionnaire are now
            // durable. Only a newly generated link may attempt the opted-in send.
            $delivery = $this->sendNewQuestionnaireEmail(
                $questionnaireDetail,
                isset($questionnaire['one_time_invitation_copy']) ? $questionnaire['one_time_invitation_copy'] : '',
                !empty($questionnaire['link_generated']),
                $actorUserID
            );
            if (!empty($delivery['sent']))
            {
                return $this->setCandidateWorkflowStage(
                    $candidateID,
                    $jobOrderID,
                    'applicant_clarification_requested',
                    'Applicant',
                    $summary . ' The secure questionnaire link was emailed and is awaiting completion.',
                    'Wait for questionnaire',
                    $actorUserID
                );
            }

            return true;
        }

        return $this->setCandidateWorkflowStage(
            $candidateID,
            $jobOrderID,
            $stageKey,
            'Craig',
            $summary,
            'Review application',
            $actorUserID
        );
    }

    private function sendNewQuestionnaireEmail($questionnaireDetail, $invitationCopy, $linkGenerated, $actorUserID)
    {
        if (!$linkGenerated || empty($questionnaireDetail['screening_questionnaire_id']) || trim((string) $invitationCopy) === '')
        {
            return array('sent' => false, 'reason' => 'not_new');
        }

        // This is a second, fail-closed guard against delivery before routing.
        // The sender is allowed to act only after the exact candidate/job pair
        // has a durable workflow row for human review.
        $candidateID = isset($questionnaireDetail['candidate_id']) ? (int) $questionnaireDetail['candidate_id'] : 0;
        $jobOrderID = isset($questionnaireDetail['joborder_id']) ? (int) $questionnaireDetail['joborder_id'] : 0;
        $workflowRow = ($candidateID > 0 && $jobOrderID > 0) ? $this->_db->getAssoc(sprintf(
            'SELECT candidate_workflow_id
             FROM nesp_candidate_workflow
             WHERE candidate_id = %s
               AND joborder_id = %s
             LIMIT 1',
            $this->_db->makeQueryInteger($candidateID),
            $this->_db->makeQueryInteger($jobOrderID)
        )) : array();
        if (empty($workflowRow))
        {
            return array('sent' => false, 'reason' => 'workflow_not_ready');
        }

        // A partially deployed schema must never turn into an accidental send
        // attempt or a database error. The migration is required before this
        // feature can send anything.
        if (!$this->isColumnInstalled('nesp_screening_questionnaire', 'auto_email_status_key'))
        {
            return array('sent' => false, 'reason' => 'schema_not_ready');
        }

        $email = trim(isset($questionnaireDetail['email1']) ? (string) $questionnaireDetail['email1'] : '');
        $deliveryStatus = $this->getApplicantEmailDeliveryStatus();
        if ($deliveryStatus['status_key'] !== 'enabled' || filter_var($email, FILTER_VALIDATE_EMAIL) === false)
        {
            return array('sent' => false, 'reason' => 'not_ready');
        }

        $questionnaireID = (int) $questionnaireDetail['screening_questionnaire_id'];
        // Reserve the one delivery attempt before calling the mailer. This
        // fails closed after a crash rather than accidentally sending twice.
        $this->_db->query(sprintf(
            'UPDATE nesp_screening_questionnaire
             SET auto_email_status_key = "sending",
                 auto_email_attempted_at = UTC_TIMESTAMP(),
                 date_modified = NOW()
             WHERE screening_questionnaire_id = %s
               AND status_key = "link_ready"
               AND auto_email_status_key = "not_attempted"
               AND token_revoked_at IS NULL',
            $this->_db->makeQueryInteger($questionnaireID)
        ));
        if ($this->_db->getAffectedRows() !== 1)
        {
            return array('sent' => false, 'reason' => 'already_attempted');
        }

        $subject = 'Next step: NESP questionnaire for ' . trim((string) $questionnaireDetail['role_title']);
        $mailer = new Mailer($actorUserID === null ? -1 : $actorUserID);
        $sent = $mailer->sendToOne(
            array($email, trim((string) $questionnaireDetail['first_name'])),
            $subject,
            $invitationCopy,
            true,
            false
        );

        if (!$sent)
        {
            $this->_db->query(sprintf(
                'UPDATE nesp_screening_questionnaire
                 SET auto_email_status_key = "failed",
                     date_modified = NOW()
                 WHERE screening_questionnaire_id = %s
                   AND auto_email_status_key = "sending"',
                $this->_db->makeQueryInteger($questionnaireID)
            ));
            $this->logAuditEvent($actorUserID, 'screening_questionnaire_auto_email_failed', 'screening_questionnaire', $questionnaireID, array(
                'candidate_id' => $candidateID,
                'joborder_id' => $jobOrderID,
                'delivery' => 'automatic'
            ));
            return array('sent' => false, 'reason' => 'delivery_failed');
        }

        $this->_db->query(sprintf(
            'UPDATE nesp_screening_questionnaire
             SET status_key = "waiting",
                 auto_email_status_key = "sent",
                 auto_email_sent_at = UTC_TIMESTAMP(),
                 date_modified = NOW()
             WHERE screening_questionnaire_id = %s
               AND auto_email_status_key = "sending"',
            $this->_db->makeQueryInteger($questionnaireID)
        ));
        $this->logAuditEvent($actorUserID, 'screening_questionnaire_auto_email_sent', 'screening_questionnaire', $questionnaireID, array(
            'candidate_id' => $candidateID,
            'joborder_id' => $jobOrderID,
            'delivery' => 'automatic'
        ));
        return array('sent' => true, 'reason' => 'sent');
    }

    public function getInterviewDetail($interviewID)
    {
        $interviewID = (int) $interviewID;
        if ($interviewID <= 0)
        {
            return array();
        }

        $row = $this->_db->getAssoc(
            sprintf(
                'SELECT
                    i.*,
                    CONCAT(c.first_name, " ", c.last_name) AS candidate_name,
                    c.first_name,
                    jo.title AS role_title,
                    ip.display_name AS interviewer_name
                 FROM nesp_interview i
                 INNER JOIN candidate c
                    ON c.candidate_id = i.candidate_id
                 INNER JOIN joborder jo
                    ON jo.joborder_id = i.joborder_id
                 LEFT JOIN nesp_interviewer_profile ip
                    ON ip.interviewer_profile_id = i.interviewer_profile_id
                 WHERE i.interview_id = %s
                 LIMIT 1',
                $this->_db->makeQueryInteger($interviewID)
            )
        );

        return empty($row) ? array() : $this->decorateInterviewRow($row);
    }

    public function createManualInterview($input, $actorUserID)
    {
        $normalized = $this->normalizeManualInterviewInput($input);
        if (!$normalized['ok'])
        {
            return $normalized;
        }

        $active = $this->getActiveInterviewsForCandidateJob($normalized['candidate_id'], $normalized['joborder_id'], 0);
        if (!empty($active))
        {
            return array('ok' => false, 'error' => 'An active interview already exists for this candidate and role.');
        }

        $conflictResult = $this->checkManualInterviewAvailabilityConflicts($normalized, 0, $input, $actorUserID);
        if (empty($conflictResult['ok']))
        {
            return $conflictResult;
        }

        $participantToken = self::generateQuestionnaireToken();
        $participantTokenHash = self::interviewParticipantTokenHash($participantToken);
        $participantLink = self::getInterviewParticipantLink($participantToken);
        $oneTimeInvitationCopy = self::buildManualInterviewInvitationCopy(
            $normalized['candidate_first_name'],
            $normalized['role_title'],
            $normalized['scheduled_start'],
            $normalized['duration_minutes'],
            $normalized['timezone'],
            $participantLink
        );
        $invitationCopy = self::buildManualInterviewStoredInvitationCopy(
            $normalized['candidate_first_name'],
            $normalized['role_title'],
            $normalized['scheduled_start'],
            $normalized['duration_minutes'],
            $normalized['timezone']
        );

        $this->_db->query(
            sprintf(
                'INSERT INTO nesp_interview
                    (candidate_id, joborder_id, interviewer_profile_id, workflow_stage_id, scheduled_start, scheduled_end, status_key, manual_zoom_join_url, participant_link_token_hash, timezone, invitation_status_key, invitation_preview_text, internal_notes, scheduled_by_user_id, date_created, date_modified)
                 VALUES
                    (%s, %s, %s, NULL, %s, %s, "invitation_pending", %s, %s, %s, "pending_human_review", %s, %s, %s, NOW(), NOW())',
                $this->_db->makeQueryInteger($normalized['candidate_id']),
                $this->_db->makeQueryInteger($normalized['joborder_id']),
                $this->_db->makeQueryInteger($normalized['interviewer_profile_id']),
                $this->_db->makeQueryString($normalized['scheduled_start']),
                $this->_db->makeQueryString($normalized['scheduled_end']),
                $this->_db->makeQueryString($normalized['manual_zoom_join_url']),
                $this->_db->makeQueryString($participantTokenHash),
                $this->_db->makeQueryString($normalized['timezone']),
                $this->_db->makeQueryString($invitationCopy),
                $this->_db->makeQueryString($normalized['internal_notes']),
                $actorUserID === null ? 'NULL' : $this->_db->makeQueryInteger($actorUserID)
            )
        );
        $interviewID = (int) $this->_db->getLastInsertID();
        $this->setCandidateWorkflowStage($normalized['candidate_id'], $normalized['joborder_id'], 'interview_confirmation_pending', 'Applicant', 'Interview scheduled. Review and send invitation copy.', 'Check interview invitation', $actorUserID);
        $this->logAuditEvent($actorUserID, 'manual_interview_created', 'nesp_interview', $interviewID, array(
            'candidate_id' => $normalized['candidate_id'],
            'joborder_id' => $normalized['joborder_id'],
            'interviewer_profile_id' => $normalized['interviewer_profile_id'],
            'zoom_join_url_masked' => self::maskZoomURLForAudit($normalized['manual_zoom_join_url'])
        ));
        if (!empty($conflictResult['override']))
        {
            $this->logManualInterviewAvailabilityOverride($actorUserID, $interviewID, $normalized, $conflictResult);
        }

        return array('ok' => true, 'interview_id' => $interviewID, 'one_time_invitation_copy' => $oneTimeInvitationCopy);
    }

    public function updateManualInterview($interviewID, $input, $actorUserID)
    {
        $interview = $this->getInterviewDetail($interviewID);
        if (empty($interview) || !in_array($interview['status_key'], array('requested', 'scheduled', 'invitation_pending', 'invitation_sent', 'confirmed', 'reschedule_needed'), true))
        {
            return array('ok' => false, 'error' => 'Choose an active interview to reschedule.');
        }

        $input['candidateID'] = $interview['candidate_id'];
        $input['jobOrderID'] = $interview['joborder_id'];
        $normalized = $this->normalizeManualInterviewInput($input);
        if (!$normalized['ok'])
        {
            return $normalized;
        }

        $active = $this->getActiveInterviewsForCandidateJob($normalized['candidate_id'], $normalized['joborder_id'], (int) $interviewID);
        if (!empty($active))
        {
            return array('ok' => false, 'error' => 'Another active interview already exists for this candidate and role.');
        }

        $conflictResult = $this->checkManualInterviewAvailabilityConflicts($normalized, (int) $interviewID, $input, $actorUserID);
        if (empty($conflictResult['ok']))
        {
            return $conflictResult;
        }

        $participantToken = self::generateQuestionnaireToken();
        $participantTokenHash = self::interviewParticipantTokenHash($participantToken);
        $participantLink = self::getInterviewParticipantLink($participantToken);
        $oneTimeInvitationCopy = self::buildManualInterviewInvitationCopy(
            $normalized['candidate_first_name'],
            $normalized['role_title'],
            $normalized['scheduled_start'],
            $normalized['duration_minutes'],
            $normalized['timezone'],
            $participantLink
        );
        $invitationCopy = self::buildManualInterviewStoredInvitationCopy(
            $normalized['candidate_first_name'],
            $normalized['role_title'],
            $normalized['scheduled_start'],
            $normalized['duration_minutes'],
            $normalized['timezone']
        );

        $this->_db->query(
            sprintf(
                'UPDATE nesp_interview
                 SET interviewer_profile_id = %s,
                     scheduled_start = %s,
                     scheduled_end = %s,
                     status_key = "reschedule_needed",
                     manual_zoom_join_url = %s,
                     participant_link_token_hash = %s,
                     participant_link_opened_at = NULL,
                     participant_link_last_opened_at = NULL,
                     participant_link_open_count = 0,
                     participant_link_revoked_at = NULL,
                     timezone = %s,
                     invitation_status_key = "pending_human_review",
                     invitation_preview_text = %s,
                     internal_notes = %s,
                     reschedule_count = reschedule_count + 1,
                     date_modified = NOW()
                 WHERE interview_id = %s',
                $this->_db->makeQueryInteger($normalized['interviewer_profile_id']),
                $this->_db->makeQueryString($normalized['scheduled_start']),
                $this->_db->makeQueryString($normalized['scheduled_end']),
                $this->_db->makeQueryString($normalized['manual_zoom_join_url']),
                $this->_db->makeQueryString($participantTokenHash),
                $this->_db->makeQueryString($normalized['timezone']),
                $this->_db->makeQueryString($invitationCopy),
                $this->_db->makeQueryString($normalized['internal_notes']),
                $this->_db->makeQueryInteger($interviewID)
            )
        );
        $this->setCandidateWorkflowStage($normalized['candidate_id'], $normalized['joborder_id'], 'interview_confirmation_pending', 'Applicant', 'Interview was rescheduled. Review updated invitation copy.', 'Review updated interview', $actorUserID);
        $linkChanged = trim((string) $interview['manual_zoom_join_url']) !== trim((string) $normalized['manual_zoom_join_url']);
        $this->logAuditEvent($actorUserID, 'manual_interview_rescheduled', 'nesp_interview', $interviewID, array(
            'previous_start' => $interview['scheduled_start'],
            'new_start' => $normalized['scheduled_start'],
            'previous_zoom_join_url_masked' => self::maskZoomURLForAudit($interview['manual_zoom_join_url']),
            'new_zoom_join_url_masked' => self::maskZoomURLForAudit($normalized['manual_zoom_join_url']),
            'zoom_join_url_replaced' => $linkChanged ? 1 : 0
        ));
        if (!empty($conflictResult['override']))
        {
            $this->logManualInterviewAvailabilityOverride($actorUserID, $interviewID, $normalized, $conflictResult);
        }

        return array('ok' => true, 'interview_id' => (int) $interviewID, 'one_time_invitation_copy' => $oneTimeInvitationCopy);
    }

    public function regenerateInterviewParticipantLink($interviewID, $actorUserID)
    {
        $interview = $this->getInterviewDetail($interviewID);
        if (empty($interview) || !in_array($interview['status_key'], array('requested', 'scheduled', 'invitation_pending', 'invitation_sent', 'confirmed', 'reschedule_needed'), true))
        {
            return array('ok' => false, 'error' => 'Choose an active interview.');
        }

        $zoomValidation = self::validateZoomApplicantJoinURL($interview['manual_zoom_join_url']);
        if (empty($zoomValidation['ok']))
        {
            return array('ok' => false, 'error' => 'This interview needs a valid applicant Zoom join link before a tracked link can be prepared.');
        }

        $durationMinutes = max(5, (int) round((strtotime($interview['scheduled_end']) - strtotime($interview['scheduled_start'])) / 60));
        $token = self::generateQuestionnaireToken();
        $tokenHash = self::interviewParticipantTokenHash($token);
        $oneTimeInvitationCopy = self::buildManualInterviewInvitationCopy(
            $interview['first_name'],
            $interview['role_title'],
            $interview['scheduled_start'],
            $durationMinutes,
            $interview['timezone'],
            self::getInterviewParticipantLink($token)
        );
        $invitationCopy = self::buildManualInterviewStoredInvitationCopy(
            $interview['first_name'],
            $interview['role_title'],
            $interview['scheduled_start'],
            $durationMinutes,
            $interview['timezone']
        );

        $this->_db->query(sprintf(
            'UPDATE nesp_interview
             SET participant_link_token_hash = %s,
                 participant_link_opened_at = NULL,
                 participant_link_last_opened_at = NULL,
                 participant_link_open_count = 0,
                 participant_link_revoked_at = NULL,
                 invitation_status_key = "pending_human_review",
                 invitation_preview_text = %s,
                 date_modified = NOW()
             WHERE interview_id = %s',
            $this->_db->makeQueryString($tokenHash),
            $this->_db->makeQueryString($invitationCopy),
            $this->_db->makeQueryInteger($interviewID)
        ));
        $this->setCandidateWorkflowStage((int) $interview['candidate_id'], (int) $interview['joborder_id'], 'interview_confirmation_pending', 'Applicant', 'A refreshed interview invitation is ready for human review.', 'Review updated interview', $actorUserID);
        $this->logAuditEvent($actorUserID, 'manual_interview_tracking_link_regenerated', 'nesp_interview', (int) $interviewID, array(
            'candidate_id' => (int) $interview['candidate_id'],
            'joborder_id' => (int) $interview['joborder_id']
        ));

        return array('ok' => true, 'interview_id' => (int) $interviewID, 'one_time_invitation_copy' => $oneTimeInvitationCopy);
    }

    public function openInterviewParticipantLink($token)
    {
        $token = self::normalizeQuestionnaireToken($token);
        if ($token === '')
        {
            return array('ok' => false);
        }

        $tokenHash = self::interviewParticipantTokenHash($token);
        $interview = $this->_db->getAssoc(sprintf(
            'SELECT interview_id, candidate_id, joborder_id, status_key, manual_zoom_join_url,
                    participant_link_opened_at, participant_link_revoked_at
             FROM nesp_interview
             WHERE participant_link_token_hash = %s
             LIMIT 1',
            $this->_db->makeQueryString($tokenHash)
        ));
        if (empty($interview)
            || !empty($interview['participant_link_revoked_at'])
            || !in_array($interview['status_key'], array('requested', 'scheduled', 'invitation_pending', 'invitation_sent', 'confirmed', 'reschedule_needed'), true))
        {
            return array('ok' => false);
        }

        $zoomValidation = self::validateZoomApplicantJoinURL($interview['manual_zoom_join_url']);
        if (empty($zoomValidation['ok']))
        {
            return array('ok' => false);
        }

        $this->_db->query(sprintf(
            'UPDATE nesp_interview
             SET participant_link_open_count = participant_link_open_count + 1,
                 participant_link_opened_at = COALESCE(participant_link_opened_at, UTC_TIMESTAMP()),
                 participant_link_last_opened_at = UTC_TIMESTAMP(),
                 date_modified = NOW()
             WHERE interview_id = %s
               AND participant_link_token_hash = %s
               AND participant_link_revoked_at IS NULL
               AND status_key IN ("requested", "scheduled", "invitation_pending", "invitation_sent", "confirmed", "reschedule_needed")',
            $this->_db->makeQueryInteger($interview['interview_id']),
            $this->_db->makeQueryString($tokenHash)
        ));
        if ($this->_db->getAffectedRows() !== 1)
        {
            return array('ok' => false);
        }

        if (empty($interview['participant_link_opened_at']))
        {
            $this->logAuditEvent(null, 'manual_interview_participant_link_opened', 'nesp_interview', (int) $interview['interview_id'], array(
                'candidate_id' => (int) $interview['candidate_id'],
                'joborder_id' => (int) $interview['joborder_id']
            ));
        }

        return array('ok' => true, 'destination_url' => $zoomValidation['url']);
    }

    private function checkManualInterviewAvailabilityConflicts($normalized, $excludeInterviewID, $input, $actorUserID)
    {
        if (!$this->isFeatureFlagEnabled('NESP_INTERVIEWER_AVAILABILITY_ENABLED'))
        {
            return array('ok' => true, 'conflicts' => array(), 'override' => false);
        }

        $availability = $this->getAvailabilityForProfile($normalized['interviewer_profile_id']);
        $interviewer = $this->getInterviewerProfileForScheduling($normalized['interviewer_profile_id']);
        if (!empty($interviewer) && empty($interviewer['timezone']))
        {
            $interviewer['timezone'] = $normalized['timezone'];
        }

        $conflicts = self::findSchedulingConflicts(
            $interviewer,
            $this->getApprovedJobOrderIDsForInterviewer($normalized['interviewer_profile_id']),
            $availability['recurring'],
            $availability['blackouts'],
            $this->getActiveInterviewsForInterviewer($normalized['interviewer_profile_id'], $excludeInterviewID),
            $normalized['joborder_id'],
            $normalized['scheduled_start'],
            $normalized['scheduled_end'],
            $availability['overrides'],
            null,
            $this->getExternalBusyWindowsForInterviewer(
                $normalized['interviewer_profile_id'],
                $normalized['scheduled_start'],
                $normalized['scheduled_end']
            )
        );

        if (empty($conflicts))
        {
            return array('ok' => true, 'conflicts' => array(), 'override' => false);
        }

        $overrideRequested = isset($input['adminOverrideAvailability'])
            && in_array((string) $input['adminOverrideAvailability'], array('1', 'on', 'yes'), true);
        $overrideReason = isset($input['availabilityOverrideReason'])
            ? substr(trim((string) $input['availabilityOverrideReason']), 0, 1000)
            : '';

        if (!$overrideRequested)
        {
            return array(
                'ok' => false,
                'error' => 'Availability conflict: ' . implode(' ', $conflicts),
                'conflicts' => $conflicts,
                'override' => false
            );
        }
        if ($overrideReason === '')
        {
            return array(
                'ok' => false,
                'error' => 'Enter an admin override reason to save through availability conflicts.',
                'conflicts' => $conflicts,
                'override' => false
            );
        }

        return array(
            'ok' => true,
            'conflicts' => $conflicts,
            'override' => true,
            'override_reason' => $overrideReason,
            'actor_user_id' => $actorUserID
        );
    }

    private function getInterviewerProfileForScheduling($interviewerProfileID)
    {
        $interviewerProfileID = (int) $interviewerProfileID;
        if ($interviewerProfileID <= 0)
        {
            return array();
        }

        $row = $this->_db->getAssoc(
            sprintf(
                'SELECT *
                 FROM nesp_interviewer_profile
                 WHERE interviewer_profile_id = %s
                 LIMIT 1',
                $this->_db->makeQueryInteger($interviewerProfileID)
            )
        );

        return empty($row) ? array() : $row;
    }

    private function getApprovedJobOrderIDsForInterviewer($interviewerProfileID)
    {
        if (!$this->isTableInstalled('nesp_interviewer_job_role'))
        {
            return array();
        }

        $rows = $this->_db->getAllAssoc(
            sprintf(
                'SELECT joborder_id
                 FROM nesp_interviewer_job_role
                 WHERE interviewer_profile_id = %s
                   AND is_active = 1',
                $this->_db->makeQueryInteger((int) $interviewerProfileID)
            )
        );

        $jobOrderIDs = array();
        foreach ($rows as $row)
        {
            $jobOrderIDs[] = (int) $row['joborder_id'];
        }
        return $jobOrderIDs;
    }

    private function getActiveInterviewsForInterviewer($interviewerProfileID, $excludeInterviewID)
    {
        return $this->_db->getAllAssoc(
            sprintf(
                'SELECT interview_id, scheduled_start, scheduled_end, timezone, status_key
                 FROM nesp_interview
                 WHERE interviewer_profile_id = %s
                   AND interview_id <> %s
                   AND status_key IN ("requested", "scheduled", "invitation_pending", "invitation_sent", "confirmed", "reschedule_needed")
                 ORDER BY scheduled_start ASC',
                $this->_db->makeQueryInteger((int) $interviewerProfileID),
                $this->_db->makeQueryInteger((int) $excludeInterviewID)
            )
        );
    }

    protected function getExternalBusyWindowsForInterviewer($interviewerProfileID, $startTime, $endTime)
    {
        return array();
    }

    protected function getDefaultParticipantJoinURLForInterviewer($interviewerProfileID)
    {
        return '';
    }

    private function logManualInterviewAvailabilityOverride($actorUserID, $interviewID, $normalized, $conflictResult)
    {
        $this->logAuditEvent($actorUserID, 'manual_interview_availability_override_used', 'nesp_interview', $interviewID, array(
            'candidate_id' => (int) $normalized['candidate_id'],
            'joborder_id' => (int) $normalized['joborder_id'],
            'interviewer_profile_id' => (int) $normalized['interviewer_profile_id'],
            'scheduled_start' => $normalized['scheduled_start'],
            'scheduled_end' => $normalized['scheduled_end'],
            'timezone' => $normalized['timezone'],
            'conflicts' => isset($conflictResult['conflicts']) ? $conflictResult['conflicts'] : array(),
            'override_reason' => isset($conflictResult['override_reason']) ? $conflictResult['override_reason'] : ''
        ));
    }

    public function cancelManualInterview($interviewID, $actorUserID, $cancelReason)
    {
        $interview = $this->getInterviewDetail($interviewID);
        if (empty($interview) || $interview['status_key'] === 'cancelled')
        {
            return array('ok' => false, 'error' => 'Choose an active interview to cancel.');
        }

        $this->_db->query(
            sprintf(
                'UPDATE nesp_interview
                 SET status_key = "cancelled",
                     invitation_status_key = "cancellation_pending_human_review",
                     participant_link_revoked_at = UTC_TIMESTAMP(),
                     outcome_key = "cancelled",
                     outcome_notes = %s,
                     cancelled_at = NOW(),
                     date_modified = NOW()
                 WHERE interview_id = %s',
                $this->_db->makeQueryString(substr(trim((string) $cancelReason), 0, 1000)),
                $this->_db->makeQueryInteger($interviewID)
            )
        );
        $this->setCandidateWorkflowStage($interview['candidate_id'], $interview['joborder_id'], 'interview_requested', 'Craig', 'Interview was cancelled. Create a new interview or choose the next human step.', 'Choose next step', $actorUserID);
        $this->logAuditEvent($actorUserID, 'manual_interview_cancelled', 'nesp_interview', $interviewID, array(
            'candidate_id' => (int) $interview['candidate_id'],
            'joborder_id' => (int) $interview['joborder_id'],
            'manual_zoom_cancel_reminder' => true
        ));

        return array('ok' => true, 'interview_id' => (int) $interviewID);
    }

    public function saveInterviewOutcome($interviewID, $actorUserID, $outcomeKey, $outcomeNotes)
    {
        $interview = $this->getInterviewDetail($interviewID);
        $outcomes = self::getManualInterviewOutcomeLabels();
        if (empty($interview) || !isset($outcomes[$outcomeKey]))
        {
            return array('ok' => false, 'error' => 'Choose a valid interview outcome.');
        }

        $statusKey = $outcomeKey === 'no_show' ? 'no_show' : 'completed';
        $stageKey = in_array($outcomeKey, array('advance_to_next_step', 'follow_up_needed')) ? 'scorecard_complete' : 'needs_review';
        $summary = 'Interview outcome recorded: ' . $outcomes[$outcomeKey] . '.';

        $this->_db->query(
            sprintf(
                'UPDATE nesp_interview
                 SET status_key = %s,
                     outcome_key = %s,
                     outcome_notes = %s,
                     completed_at = NOW(),
                     date_modified = NOW()
                 WHERE interview_id = %s',
                $this->_db->makeQueryString($statusKey),
                $this->_db->makeQueryString($outcomeKey),
                $this->_db->makeQueryString(substr(trim((string) $outcomeNotes), 0, 4000)),
                $this->_db->makeQueryInteger($interviewID)
            )
        );
        $this->setCandidateWorkflowStage($interview['candidate_id'], $interview['joborder_id'], $stageKey, 'Craig', $summary, 'Review outcome', $actorUserID);
        $this->logAuditEvent($actorUserID, 'manual_interview_outcome_recorded', 'nesp_interview', $interviewID, array(
            'outcome_key' => $outcomeKey,
            'candidate_id' => (int) $interview['candidate_id'],
            'joborder_id' => (int) $interview['joborder_id']
        ));

        return array('ok' => true, 'interview_id' => (int) $interviewID);
    }

    public function markManualInterviewInvitationSent($interviewID, $actorUserID)
    {
        $interview = $this->getInterviewDetail($interviewID);
        if (empty($interview) || $interview['status_key'] === 'cancelled')
        {
            return false;
        }

        $this->_db->query(
            sprintf(
                'UPDATE nesp_interview
                 SET status_key = "invitation_sent",
                     invitation_status_key = "sent_manually",
                     date_modified = NOW()
                 WHERE interview_id = %s',
                $this->_db->makeQueryInteger($interviewID)
            )
        );
        $this->setCandidateWorkflowStage($interview['candidate_id'], $interview['joborder_id'], 'interview_confirmation_pending', 'Applicant', 'Interview invitation was marked sent manually. Waiting for applicant confirmation.', 'Check confirmation', $actorUserID);
        $this->logAuditEvent($actorUserID, 'manual_interview_invitation_marked_sent', 'nesp_interview', $interviewID, array(
            'candidate_id' => (int) $interview['candidate_id'],
            'joborder_id' => (int) $interview['joborder_id']
        ));

        return true;
    }

    public function getInterviewerAvailability()
    {
        return $this->_db->getAllAssoc(
            'SELECT
                ia.availability_id,
                ia.interviewer_profile_id,
                ip.display_name AS interviewer_name,
                ia.weekday_key,
                ia.start_time,
                ia.end_time,
                ia.timezone,
                ia.slot_minutes,
                ia.buffer_minutes,
                ia.is_active,
                ia.notes,
                ia.date_modified
             FROM
                nesp_interviewer_availability ia
             INNER JOIN nesp_interviewer_profile ip
                ON ip.interviewer_profile_id = ia.interviewer_profile_id
             ORDER BY
                ia.is_active DESC,
                ip.display_name ASC,
                FIELD(ia.weekday_key, "Monday", "Tuesday", "Wednesday", "Thursday", "Friday", "Saturday", "Sunday"),
                ia.start_time ASC'
        );
    }

    public function getInterviewerAvailabilityOverrides()
    {
        if (!$this->isTableInstalled('nesp_interviewer_availability_override'))
        {
            return array();
        }

        return $this->_db->getAllAssoc(
            'SELECT
                override_id,
                interviewer_profile_id,
                override_date,
                override_type_key,
                start_time,
                end_time,
                timezone,
                private_reason,
                date_modified
             FROM
                nesp_interviewer_availability_override
             WHERE
                is_active = 1
             ORDER BY
                override_date ASC,
                start_time ASC'
        );
    }

    public function getInterviewerBlackouts()
    {
        if (!$this->isTableInstalled('nesp_interviewer_blackout'))
        {
            return array();
        }

        return $this->_db->getAllAssoc(
            'SELECT
                blackout_id,
                interviewer_profile_id,
                starts_at,
                ends_at,
                is_all_day,
                timezone,
                private_reason,
                date_modified
             FROM
                nesp_interviewer_blackout
             WHERE
                is_active = 1
             ORDER BY
                starts_at ASC'
        );
    }

    public function getInterviewerProfileForUser($userID)
    {
        $row = $this->_db->getAssoc(
            sprintf(
                'SELECT *
                 FROM nesp_interviewer_profile
                 WHERE user_id = %s
                   AND is_active = 1
                 LIMIT 1',
                $this->_db->makeQueryInteger($userID)
            )
        );

        return empty($row) ? array() : $row;
    }

    public function getAvailabilityForProfile($interviewerProfileID)
    {
        $interviewerProfileID = (int) $interviewerProfileID;
        if ($interviewerProfileID <= 0)
        {
            return array('recurring' => array(), 'overrides' => array(), 'blackouts' => array());
        }

        $recurring = $this->_db->getAllAssoc(
            sprintf(
                'SELECT *
                 FROM nesp_interviewer_availability
                 WHERE interviewer_profile_id = %s
                   AND is_active = 1
                 ORDER BY FIELD(weekday_key, "Monday", "Tuesday", "Wednesday", "Thursday", "Friday", "Saturday", "Sunday"), start_time ASC',
                $this->_db->makeQueryInteger($interviewerProfileID)
            )
        );

        $overrides = array();
        if ($this->isTableInstalled('nesp_interviewer_availability_override'))
        {
            $overrides = $this->_db->getAllAssoc(
                sprintf(
                    'SELECT *
                     FROM nesp_interviewer_availability_override
                     WHERE interviewer_profile_id = %s
                       AND is_active = 1
                     ORDER BY override_date ASC, start_time ASC',
                    $this->_db->makeQueryInteger($interviewerProfileID)
                )
            );
        }

        $blackouts = array();
        if ($this->isTableInstalled('nesp_interviewer_blackout'))
        {
            $blackouts = $this->_db->getAllAssoc(
                sprintf(
                    'SELECT *
                     FROM nesp_interviewer_blackout
                     WHERE interviewer_profile_id = %s
                       AND is_active = 1
                     ORDER BY starts_at ASC',
                    $this->_db->makeQueryInteger($interviewerProfileID)
                )
            );
        }

        return array('recurring' => $recurring, 'overrides' => $overrides, 'blackouts' => $blackouts);
    }

    public function createInterviewerAvailability($interviewerProfileID, $weekdayKey, $startTime, $endTime, $timezone, $slotMinutes, $bufferMinutes, $notes, $actorUserID)
    {
        $interviewerProfileID = (int) $interviewerProfileID;
        $weekdayKey = trim($weekdayKey);
        $startTime = trim($startTime);
        $endTime = trim($endTime);
        $defaultAvailability = self::getDefaultAvailabilityTemplate();
        $timezone = trim($timezone) === '' ? $defaultAvailability['timezone'] : trim($timezone);
        $slotMinutes = max(15, min(180, (int) $slotMinutes));
        $bufferMinutes = max(0, min(60, (int) $bufferMinutes));
        $notes = trim($notes);

        if ($interviewerProfileID <= 0 || !in_array($weekdayKey, array('Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday')))
        {
            return false;
        }
        if (!self::isValidAvailabilityTime($startTime) || !self::isValidAvailabilityTime($endTime) || strcmp($startTime, $endTime) >= 0)
        {
            return false;
        }

        $sql = sprintf(
            'INSERT INTO nesp_interviewer_availability
                (interviewer_profile_id, weekday_key, start_time, end_time, timezone, slot_minutes, buffer_minutes, is_active, notes, created_by_user_id, date_created, date_modified)
             VALUES
                (%s, %s, %s, %s, %s, %s, %s, 1, %s, %s, NOW(), NOW())',
            $this->_db->makeQueryInteger($interviewerProfileID),
            $this->_db->makeQueryString($weekdayKey),
            $this->_db->makeQueryString($startTime),
            $this->_db->makeQueryString($endTime),
            $this->_db->makeQueryString($timezone),
            $this->_db->makeQueryInteger($slotMinutes),
            $this->_db->makeQueryInteger($bufferMinutes),
            $this->_db->makeQueryString($notes),
            $actorUserID === null ? 'NULL' : $this->_db->makeQueryInteger($actorUserID)
        );
        $this->_db->query($sql);
        $availabilityID = $this->_db->getLastInsertID();

        $this->logAuditEvent(
            $actorUserID,
            'interviewer_availability_created',
            'interviewer_availability',
            $availabilityID,
            array('interviewer_profile_id' => $interviewerProfileID, 'weekday_key' => $weekdayKey)
        );

        return $availabilityID;
    }

    public function getInterviewerAccountability()
    {
        $availabilityStatus = $this->selectOptionalColumn('nesp_interviewer_profile', 'ip', 'availability_status_key', '"open"');
        $maxDaily = $this->selectOptionalColumn('nesp_interviewer_profile', 'ip', 'max_interviews_per_day', '3');
        $maxWeekly = $this->selectOptionalColumn('nesp_interviewer_profile', 'ip', 'max_interviews_per_week', '12');
        $roleSelect = $this->isTableInstalled('nesp_interviewer_job_role')
            ? 'GROUP_CONCAT(DISTINCT jo_role.title ORDER BY jo_role.joborder_id SEPARATOR ", ") AS approved_roles,'
            : '"" AS approved_roles,';
        $roleJoin = $this->isTableInstalled('nesp_interviewer_job_role')
            ? 'LEFT JOIN nesp_interviewer_job_role ijr ON ijr.interviewer_profile_id = ip.interviewer_profile_id AND ijr.is_active = 1
             LEFT JOIN joborder jo_role ON jo_role.joborder_id = ijr.joborder_id'
            : '';

        return $this->_db->getAllAssoc(
            'SELECT
                ip.interviewer_profile_id,
                ip.display_name,
                ip.role_key,
                ip.is_active,
                ' . $availabilityStatus . ' AS availability_status_key,
                ' . $maxDaily . ' AS max_interviews_per_day,
                ' . $maxWeekly . ' AS max_interviews_per_week,
                ' . $roleSelect . '
                COUNT(DISTINCT cg.grant_id) AS active_grants,
                COUNT(DISTINCT CASE WHEN i.status_key IN ("scheduled", "invitation_pending", "invitation_sent", "confirmed", "reschedule_needed") THEN i.interview_id END) AS open_interviews,
                COUNT(DISTINCT CASE WHEN DATE(i.scheduled_start) = CURDATE() AND i.status_key IN ("scheduled", "invitation_pending", "invitation_sent", "confirmed", "reschedule_needed") THEN i.interview_id END) AS interviews_today,
                COUNT(DISTINCT CASE WHEN i.scheduled_start >= CURDATE() AND i.scheduled_start < DATE_ADD(CURDATE(), INTERVAL 7 DAY) AND i.status_key IN ("scheduled", "invitation_pending", "invitation_sent", "confirmed", "reschedule_needed") THEN i.interview_id END) AS interviews_this_week,
                COUNT(DISTINCT CASE WHEN cg.grant_id IS NOT NULL AND (sr.scorecard_response_id IS NULL OR sr.status_key = "draft") THEN cg.grant_id END) AS scorecards_due,
                COUNT(DISTINCT CASE WHEN cw.due_at IS NOT NULL AND cw.due_at < NOW() THEN cw.candidate_workflow_id END) AS overdue_items,
                COUNT(DISTINCT ia.availability_id) AS availability_blocks
             FROM
                nesp_interviewer_profile ip
             LEFT JOIN nesp_interviewer_candidate_grant cg
                ON cg.interviewer_profile_id = ip.interviewer_profile_id
                AND cg.date_revoked IS NULL
             LEFT JOIN nesp_interview i
                ON i.interviewer_profile_id = ip.interviewer_profile_id
             LEFT JOIN nesp_scorecard_response sr
                ON sr.interviewer_profile_id = ip.interviewer_profile_id
                AND sr.candidate_id = cg.candidate_id
                AND sr.joborder_id = cg.joborder_id
             LEFT JOIN nesp_candidate_workflow cw
                ON cw.candidate_id = cg.candidate_id
                AND cw.joborder_id = cg.joborder_id
             LEFT JOIN nesp_interviewer_availability ia
                ON ia.interviewer_profile_id = ip.interviewer_profile_id
                AND ia.is_active = 1
             ' . $roleJoin . '
             GROUP BY
                ip.interviewer_profile_id
             ORDER BY
                overdue_items DESC,
                scorecards_due DESC,
                open_interviews DESC,
                ip.display_name ASC'
        );
    }

    public function getAssignmentSuggestions($limit)
    {
        $limit = max(1, min(100, (int) $limit));
        $rows = $this->getDashboardCandidateRows($limit);
        $rules = $this->getInterviewerRoleRules();
        $suggestions = array();

        foreach ($rows as $row)
        {
            if (!in_array($row['stage_key'], array('interview_requested', 'needs_review', 'phone_screen_complete')))
            {
                continue;
            }

            $matchedRule = array();
            foreach ($rules as $rule)
            {
                if ((int) $rule['is_active'] !== 1)
                {
                    continue;
                }
                if (!empty($rule['joborder_id']) && (int) $rule['joborder_id'] === (int) $row['joborder_id'])
                {
                    $matchedRule = $rule;
                    break;
                }
            }
            if (empty($matchedRule))
            {
                $matchedRule = self::matchAssignmentRuleForRole($row['role_title'], $rules);
            }

            $card = $this->normalizeDashboardCard($row);
            $card['suggested_interviewer'] = empty($matchedRule) ? 'No rule yet' : $matchedRule['interviewer_name'];
            $card['assignment_rule'] = empty($matchedRule) ? 'Create routing rule' : $matchedRule['role_match_text'];
            $card['assignment_mode'] = empty($matchedRule) ? 'manual_review' : $matchedRule['assignment_mode'];
            $suggestions[] = $card;
        }

        return array_slice($suggestions, 0, 12);
    }

    public function getScorecardSummaries($limit)
    {
        $limit = max(1, min(200, (int) $limit));

        return $this->_db->getAllAssoc(
            sprintf(
                'SELECT
                    sr.scorecard_response_id,
                    sr.candidate_id,
                    sr.joborder_id,
                    CONCAT(c.first_name, " ", c.last_name) AS candidate_name,
                    jo.title AS role_title,
                    ip.display_name AS interviewer_name,
                    sr.status_key,
                    sr.overall_recommendation,
                    sr.submitted_at,
                    sr.locked_at,
                    sr.unlocked_at,
                    sr.date_modified
                 FROM
                    nesp_scorecard_response sr
                 INNER JOIN candidate c
                    ON c.candidate_id = sr.candidate_id
                 INNER JOIN joborder jo
                    ON jo.joborder_id = sr.joborder_id
                 LEFT JOIN nesp_interviewer_profile ip
                    ON ip.interviewer_profile_id = sr.interviewer_profile_id
                 ORDER BY
                    sr.date_modified DESC,
                    sr.scorecard_response_id DESC
                 LIMIT %s',
                $this->_db->makeQueryInteger($limit)
            )
        );
    }

    public function createInactiveInterviewerProfile($displayName, $email, $roleKey, $actorUserID, $options = array())
    {
        $displayName = trim($displayName);
        $email = trim($email);
        $roleKey = trim($roleKey) === '' ? 'interviewer' : trim($roleKey);

        if ($displayName === '')
        {
            return false;
        }

        if ($this->interviewerProfileEmailIsInUse($email))
        {
            return array('ok' => false, 'error' => 'An interviewer profile already uses this email address. Edit that profile instead of creating another one.');
        }

        $columns = array('user_id', 'display_name', 'email', 'role_key', 'is_active', 'can_view_resume', 'can_add_notes', 'can_submit_scorecard', 'date_created', 'date_modified');
        $values = array(
            'NULL',
            $this->_db->makeQueryString($displayName),
            $this->_db->makeQueryString($email),
            $this->_db->makeQueryString($roleKey),
            '0',
            '0',
            '1',
            '1',
            'NOW()',
            'NOW()'
        );

        $extraDefaults = $this->normalizeInterviewerSettingsOptions($options);
        if ($this->isColumnInstalled('nesp_interviewer_profile', 'default_zoom_join_url')
            && $extraDefaults['default_zoom_join_url'] !== '')
        {
            $zoomValidation = self::validateZoomApplicantJoinURL($extraDefaults['default_zoom_join_url']);
            if (empty($zoomValidation['ok']))
            {
                return array('ok' => false, 'error' => $zoomValidation['error']);
            }
            $extraDefaults['default_zoom_join_url'] = $zoomValidation['url'];
        }
        foreach ($extraDefaults as $column => $value)
        {
            if ($this->isColumnInstalled('nesp_interviewer_profile', $column))
            {
                $columns[] = $column;
                $values[] = $this->sqlValueForInterviewerSetting($column, $value);
            }
        }

        $sql = sprintf(
            'INSERT INTO nesp_interviewer_profile
                (%s)
             VALUES
                (%s)',
            implode(', ', $columns),
            implode(', ', $values)
        );

        $this->_db->query($sql);
        $profileID = $this->_db->getLastInsertID();
        if (!empty($options['approved_joborder_ids']))
        {
            $this->replaceInterviewerJobRoles($profileID, $options['approved_joborder_ids'], $actorUserID);
        }
        $accountResult = array();
        if (!empty($options['temporary_password']))
        {
            $accountResult = $this->createOrResetInterviewerUser(
                $profileID,
                $displayName,
                $email,
                $options['temporary_password'],
                false,
                $actorUserID
            );
        }
        $this->logAuditEvent(
            $actorUserID,
            'interviewer_profile_created_inactive',
            'interviewer_profile',
            $profileID,
            array('display_name' => $displayName, 'role_key' => $roleKey)
        );

        return array(
            'interviewer_profile_id' => $profileID,
            'temporary_login_message' => isset($accountResult['temporary_login_message']) ? $accountResult['temporary_login_message'] : ''
        );
    }

    public function updateInterviewerSettings($interviewerProfileID, $settings, $actorUserID)
    {
        $interviewerProfileID = (int) $interviewerProfileID;
        if ($interviewerProfileID <= 0)
        {
            return false;
        }

        $before = $this->_db->getAssoc(
            sprintf(
                'SELECT *
                 FROM nesp_interviewer_profile
                 WHERE interviewer_profile_id = %s
                 LIMIT 1',
                $this->_db->makeQueryInteger($interviewerProfileID)
            )
        );
        if (empty($before))
        {
            return false;
        }

        $normalized = $this->normalizeInterviewerSettingsOptions($settings);
        if ($this->isColumnInstalled('nesp_interviewer_profile', 'default_zoom_join_url')
            && $normalized['default_zoom_join_url'] !== '')
        {
            $zoomValidation = self::validateZoomApplicantJoinURL($normalized['default_zoom_join_url']);
            if (empty($zoomValidation['ok']))
            {
                return array('ok' => false, 'error' => $zoomValidation['error']);
            }
            $normalized['default_zoom_join_url'] = $zoomValidation['url'];
        }
        $sets = array();
        foreach ($normalized as $column => $value)
        {
            if ($this->isColumnInstalled('nesp_interviewer_profile', $column))
            {
                $sets[] = $column . ' = ' . $this->sqlValueForInterviewerSetting($column, $value);
            }
        }
        if (isset($settings['display_name']))
        {
            $sets[] = 'display_name = ' . $this->_db->makeQueryString(trim($settings['display_name']));
        }
        if (isset($settings['email']))
        {
            $requestedEmail = trim($settings['email']);
            if ($this->interviewerProfileEmailIsInUse($requestedEmail, $interviewerProfileID))
            {
                return array('ok' => false, 'error' => 'An interviewer profile already uses this email address.');
            }
            $sets[] = 'email = ' . $this->_db->makeQueryString($requestedEmail);
        }
        if (isset($settings['role_key']))
        {
            $sets[] = 'role_key = ' . $this->_db->makeQueryString(trim($settings['role_key']));
        }
        if (!empty($sets))
        {
            $sets[] = 'date_modified = NOW()';
            $this->_db->query(
                sprintf(
                    'UPDATE nesp_interviewer_profile
                     SET %s
                     WHERE interviewer_profile_id = %s',
                    implode(', ', $sets),
                    $this->_db->makeQueryInteger($interviewerProfileID)
                )
            );
        }

        if (isset($settings['approved_joborder_ids']) && is_array($settings['approved_joborder_ids']))
        {
            $this->replaceInterviewerJobRoles($interviewerProfileID, $settings['approved_joborder_ids'], $actorUserID);
        }

        $after = $this->_db->getAssoc(sprintf(
            'SELECT * FROM nesp_interviewer_profile WHERE interviewer_profile_id = %s LIMIT 1',
            $this->_db->makeQueryInteger($interviewerProfileID)
        ));

        $this->logAuditEvent(
            $actorUserID,
            $this->maskedInterviewerZoomLink($before) !== $this->maskedInterviewerZoomLink($after) ? 'interviewer_zoom_participant_link_updated' : 'interviewer_settings_updated',
            'interviewer_profile',
            $interviewerProfileID,
            array(
                'old' => $this->auditSafeInterviewerSettings($before),
                'new' => $this->auditSafeInterviewerSettings($after)
            )
        );

        return array(
            'ok' => true,
            'temporary_login_message' => isset($accountResult['temporary_login_message']) ? $accountResult['temporary_login_message'] : ''
        );
    }

    /**
     * Profiles are human-managed records. Prevent an accidental second profile
     * for the same mailbox while leaving blank legacy/profile-only addresses alone.
     */
    private function interviewerProfileEmailIsInUse($email, $excludeProfileID = 0)
    {
        $email = self::normalizeInterviewerProfileEmail($email);
        if ($email === '')
        {
            return false;
        }

        $sql = sprintf(
            'SELECT interviewer_profile_id
             FROM nesp_interviewer_profile
             WHERE LOWER(TRIM(email)) = %s',
            $this->_db->makeQueryString($email)
        );
        if ((int) $excludeProfileID > 0)
        {
            $sql .= sprintf(
                ' AND interviewer_profile_id <> %s',
                $this->_db->makeQueryInteger($excludeProfileID)
            );
        }
        $sql .= ' LIMIT 1';

        return !empty($this->_db->getAssoc($sql));
    }

    public static function normalizeInterviewerProfileEmail($email)
    {
        return strtolower(trim((string) $email));
    }

    public function interviewerLoginLifecycleAction($interviewerProfileID, $action, $temporaryPassword, $actorUserID)
    {
        $interviewerProfileID = (int) $interviewerProfileID;
        $profile = $this->getInterviewerLoginProfile($interviewerProfileID);
        if (empty($profile))
        {
            return array('ok' => false, 'error' => 'Choose an interviewer profile.');
        }

        switch ($action)
        {
            case 'prepareInterviewerLogin':
                if ((int) $profile['user_id'] > 0)
                {
                    return array('ok' => false, 'error' => 'This interviewer already has a prepared login.');
                }
                return $this->prepareInterviewerLoginWithPassword($profile, $temporaryPassword, false, $actorUserID, 'interviewer_login_prepared');

            case 'resetInterviewerTempPassword':
                if ((int) $profile['user_id'] <= 0)
                {
                    return array('ok' => false, 'error' => 'Prepare a login before resetting a temporary password.');
                }
                $validation = $this->validateInterviewerLinkedUser($profile, false);
                if (empty($validation['ok']))
                {
                    return $validation;
                }
                return $this->prepareInterviewerLoginWithPassword($profile, $temporaryPassword, false, $actorUserID, 'interviewer_temp_password_reset');

            case 'activateInterviewerLogin':
                if (!in_array($profile['account_state_key'], array('account_prepared', 'temporary_password_set', 'awaiting_craig_activation'), true))
                {
                    return array('ok' => false, 'error' => 'Only prepared interviewer logins can be activated.');
                }
                return $this->setInterviewerLoginActiveState($profile, true, 'active', $actorUserID, 'interviewer_login_activated');

            case 'reactivateInterviewerLogin':
                if (!in_array($profile['account_state_key'], array('suspended', 'deactivated'), true))
                {
                    return array('ok' => false, 'error' => 'Only suspended or deactivated interviewer logins can be reactivated.');
                }
                return $this->setInterviewerLoginActiveState($profile, true, 'active', $actorUserID, 'interviewer_login_reactivated');

            case 'suspendInterviewerLogin':
                if ((int) $profile['is_active'] !== 1)
                {
                    return array('ok' => false, 'error' => 'Only active interviewer logins can be suspended.');
                }
                return $this->setInterviewerLoginActiveState($profile, false, 'suspended', $actorUserID, 'interviewer_login_suspended');

            case 'disableInterviewerLogin':
                if ($profile['account_state_key'] === 'permanently_disabled')
                {
                    return array('ok' => false, 'error' => 'This interviewer login is already permanently disabled.');
                }
                return $this->setInterviewerLoginActiveState($profile, false, 'permanently_disabled', $actorUserID, 'interviewer_login_permanently_disabled');
        }

        return array('ok' => false, 'error' => 'Unknown interviewer login action.');
    }

    public function setInterviewerAvailabilityStatus($interviewerProfileID, $statusKey, $reason, $closedUntil, $actorUserID)
    {
        $interviewerProfileID = (int) $interviewerProfileID;
        $statusKey = $statusKey === 'closed' ? 'closed' : 'open';
        if ($interviewerProfileID <= 0 || !$this->isColumnInstalled('nesp_interviewer_profile', 'availability_status_key'))
        {
            return false;
        }

        $sql = sprintf(
            'UPDATE nesp_interviewer_profile
             SET availability_status_key = %s,
                 availability_close_reason = %s,
                 availability_closed_until = %s,
                 date_modified = NOW()
             WHERE interviewer_profile_id = %s',
            $this->_db->makeQueryString($statusKey),
            $this->_db->makeQueryString(trim($reason)),
            trim($closedUntil) === '' ? 'NULL' : $this->_db->makeQueryString(trim($closedUntil)),
            $this->_db->makeQueryInteger($interviewerProfileID)
        );
        $this->_db->query($sql);
        $this->logAuditEvent(
            $actorUserID,
            'interviewer_availability_status_updated',
            'interviewer_profile',
            $interviewerProfileID,
            array('status_key' => $statusKey, 'closed_until' => trim($closedUntil))
        );

        return true;
    }

    public function updateInterviewerDefaultZoomJoinURL($interviewerProfileID, $joinURL, $actorUserID)
    {
        $interviewerProfileID = (int) $interviewerProfileID;
        $joinURL = trim((string) $joinURL);
        if ($interviewerProfileID <= 0 || !$this->isColumnInstalled('nesp_interviewer_profile', 'default_zoom_join_url'))
        {
            return array('ok' => false, 'error' => 'Unable to update the interviewer Zoom participant link.');
        }

        $before = $this->_db->getAssoc(
            sprintf(
                'SELECT interviewer_profile_id, default_zoom_join_url
                 FROM nesp_interviewer_profile
                 WHERE interviewer_profile_id = %s
                 LIMIT 1',
                $this->_db->makeQueryInteger($interviewerProfileID)
            )
        );
        if (empty($before))
        {
            return array('ok' => false, 'error' => 'Interviewer profile not found.');
        }

        if ($joinURL !== '')
        {
            $zoomValidation = self::validateZoomApplicantJoinURL($joinURL);
            if (empty($zoomValidation['ok']))
            {
                return array('ok' => false, 'error' => $zoomValidation['error']);
            }
            $joinURL = $zoomValidation['url'];
        }

        $this->_db->query(
            sprintf(
                'UPDATE nesp_interviewer_profile
                 SET default_zoom_join_url = %s,
                     date_modified = NOW()
                 WHERE interviewer_profile_id = %s',
                $this->_db->makeQueryString($joinURL),
                $this->_db->makeQueryInteger($interviewerProfileID)
            )
        );

        $this->logAuditEvent(
            $actorUserID,
            'interviewer_zoom_participant_link_updated',
            'interviewer_profile',
            $interviewerProfileID,
            array(
                'old_zoom_join_url_masked' => self::maskZoomURLForAudit($before['default_zoom_join_url']),
                'new_zoom_join_url_masked' => self::maskZoomURLForAudit($joinURL)
            )
        );

        return array('ok' => true);
    }

    public function createInterviewerAvailabilityOverride($interviewerProfileID, $overrideDate, $overrideTypeKey, $startTime, $endTime, $timezone, $privateReason, $actorUserID)
    {
        if (!$this->isTableInstalled('nesp_interviewer_availability_override'))
        {
            return false;
        }
        $interviewerProfileID = (int) $interviewerProfileID;
        $overrideDate = trim($overrideDate);
        $overrideTypeKey = in_array($overrideTypeKey, array('available', 'available_all_day', 'unavailable', 'unavailable_all_day')) ? $overrideTypeKey : 'available';
        $timezone = trim($timezone) === '' ? 'America/New_York' : trim($timezone);
        if ($interviewerProfileID <= 0 || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $overrideDate))
        {
            return false;
        }
        if ($overrideTypeKey === 'available' && (!self::isValidAvailabilityTime($startTime) || !self::isValidAvailabilityTime($endTime) || strcmp($startTime, $endTime) >= 0))
        {
            return false;
        }

        $sql = sprintf(
            'INSERT INTO nesp_interviewer_availability_override
                (interviewer_profile_id, override_date, override_type_key, start_time, end_time, timezone, private_reason, is_active, created_by_user_id, date_created, date_modified)
             VALUES
                (%s, %s, %s, %s, %s, %s, %s, 1, %s, NOW(), NOW())',
            $this->_db->makeQueryInteger($interviewerProfileID),
            $this->_db->makeQueryString($overrideDate),
            $this->_db->makeQueryString($overrideTypeKey),
            $overrideTypeKey === 'available' ? $this->_db->makeQueryString($startTime) : 'NULL',
            $overrideTypeKey === 'available' ? $this->_db->makeQueryString($endTime) : 'NULL',
            $this->_db->makeQueryString($timezone),
            $this->_db->makeQueryString(trim($privateReason)),
            $actorUserID === null ? 'NULL' : $this->_db->makeQueryInteger($actorUserID)
        );
        $this->_db->query($sql);
        $overrideID = $this->_db->getLastInsertID();
        $this->logAuditEvent($actorUserID, 'interviewer_availability_override_created', 'interviewer_availability_override', $overrideID, array('interviewer_profile_id' => $interviewerProfileID, 'override_type_key' => $overrideTypeKey));

        return $overrideID;
    }

    public function createInterviewerBlackout($interviewerProfileID, $startsAt, $endsAt, $isAllDay, $timezone, $privateReason, $actorUserID)
    {
        if (!$this->isTableInstalled('nesp_interviewer_blackout'))
        {
            return false;
        }
        $interviewerProfileID = (int) $interviewerProfileID;
        $timezone = trim($timezone) === '' ? 'America/New_York' : trim($timezone);
        if ($interviewerProfileID <= 0 || trim($startsAt) === '' || trim($endsAt) === '' || strtotime($startsAt) === false || strtotime($endsAt) === false || strtotime($startsAt) >= strtotime($endsAt))
        {
            return false;
        }

        $sql = sprintf(
            'INSERT INTO nesp_interviewer_blackout
                (interviewer_profile_id, starts_at, ends_at, is_all_day, timezone, private_reason, is_active, created_by_user_id, date_created, date_modified)
             VALUES
                (%s, %s, %s, %s, %s, %s, 1, %s, NOW(), NOW())',
            $this->_db->makeQueryInteger($interviewerProfileID),
            $this->_db->makeQueryString(trim($startsAt)),
            $this->_db->makeQueryString(trim($endsAt)),
            ((int) $isAllDay) === 1 ? '1' : '0',
            $this->_db->makeQueryString($timezone),
            $this->_db->makeQueryString(trim($privateReason)),
            $actorUserID === null ? 'NULL' : $this->_db->makeQueryInteger($actorUserID)
        );
        $this->_db->query($sql);
        $blackoutID = $this->_db->getLastInsertID();
        $this->logAuditEvent($actorUserID, 'interviewer_blackout_created', 'interviewer_blackout', $blackoutID, array('interviewer_profile_id' => $interviewerProfileID));

        return $blackoutID;
    }

    public function getAssignedCandidatesForUser($userID)
    {
        return $this->_db->getAllAssoc(
            sprintf(
                'SELECT
                    cg.grant_id,
                    cg.candidate_id,
                    cg.joborder_id,
                    CONCAT(c.first_name, " ", c.last_name) AS candidate_name,
                    jo.title AS role_title,
                    ws.display_name AS stage_name,
                    ws.stage_key,
                    cw.summary,
                    cw.waiting_on_key,
                    cw.date_modified AS last_activity,
                    i.interview_id,
                    i.scheduled_start,
                    i.scheduled_end,
                    i.status_key AS interview_status_key,
                    sr.status_key AS scorecard_status_key
                FROM
                    nesp_interviewer_profile ip
                INNER JOIN nesp_interviewer_candidate_grant cg
                    ON cg.interviewer_profile_id = ip.interviewer_profile_id
                    AND cg.date_revoked IS NULL
                INNER JOIN candidate c
                    ON c.candidate_id = cg.candidate_id
                INNER JOIN joborder jo
                    ON jo.joborder_id = cg.joborder_id
                LEFT JOIN nesp_candidate_workflow cw
                    ON cw.candidate_id = cg.candidate_id
                    AND cw.joborder_id = cg.joborder_id
                LEFT JOIN nesp_workflow_stage ws
                    ON ws.workflow_stage_id = cw.workflow_stage_id
                LEFT JOIN nesp_interview i
                    ON i.interview_id = (
                        SELECT MAX(i2.interview_id)
                        FROM nesp_interview i2
                        WHERE i2.candidate_id = cg.candidate_id
                          AND i2.joborder_id = cg.joborder_id
                          AND i2.interviewer_profile_id = ip.interviewer_profile_id
                    )
                LEFT JOIN nesp_scorecard_response sr
                    ON sr.scorecard_response_id = (
                        SELECT MAX(sr2.scorecard_response_id)
                        FROM nesp_scorecard_response sr2
                        WHERE sr2.candidate_id = cg.candidate_id
                          AND sr2.joborder_id = cg.joborder_id
                          AND sr2.interviewer_profile_id = ip.interviewer_profile_id
                    )
                WHERE
                    ip.user_id = %s
                    AND ip.is_active = 1
                    AND c.is_active = 1
                ORDER BY
                    i.scheduled_start ASC,
                    cw.date_modified DESC',
                $this->_db->makeQueryInteger($userID)
            )
        );
    }

    public function getAllAssignedCandidatesForAdmin()
    {
        return $this->_db->getAllAssoc(
            'SELECT
                cg.grant_id,
                cg.candidate_id,
                cg.joborder_id,
                ip.display_name AS interviewer_name,
                CONCAT(c.first_name, " ", c.last_name) AS candidate_name,
                jo.title AS role_title,
                ws.display_name AS stage_name,
                cw.summary,
                cw.waiting_on_key,
                cw.date_modified AS last_activity,
                i.interview_id,
                i.scheduled_start,
                i.scheduled_end,
                i.status_key AS interview_status_key,
                sr.status_key AS scorecard_status_key
             FROM
                nesp_interviewer_candidate_grant cg
             INNER JOIN nesp_interviewer_profile ip
                ON ip.interviewer_profile_id = cg.interviewer_profile_id
             INNER JOIN candidate c
                ON c.candidate_id = cg.candidate_id
             INNER JOIN joborder jo
                ON jo.joborder_id = cg.joborder_id
             LEFT JOIN nesp_candidate_workflow cw
                ON cw.candidate_id = cg.candidate_id
                AND cw.joborder_id = cg.joborder_id
             LEFT JOIN nesp_workflow_stage ws
                ON ws.workflow_stage_id = cw.workflow_stage_id
             LEFT JOIN nesp_interview i
                ON i.interview_id = (
                    SELECT MAX(i2.interview_id)
                    FROM nesp_interview i2
                    WHERE i2.candidate_id = cg.candidate_id
                      AND i2.joborder_id = cg.joborder_id
                      AND i2.interviewer_profile_id = ip.interviewer_profile_id
                )
             LEFT JOIN nesp_scorecard_response sr
                ON sr.scorecard_response_id = (
                    SELECT MAX(sr2.scorecard_response_id)
                    FROM nesp_scorecard_response sr2
                    WHERE sr2.candidate_id = cg.candidate_id
                      AND sr2.joborder_id = cg.joborder_id
                      AND sr2.interviewer_profile_id = ip.interviewer_profile_id
                )
             WHERE
                cg.date_revoked IS NULL
                AND ip.is_active = 1
                AND c.is_active = 1
             ORDER BY
                ip.display_name ASC,
                i.scheduled_start ASC,
                cw.date_modified DESC'
        );
    }

    public function getAssignedCandidateDetail($userID, $candidateID, $jobOrderID)
    {
        if (!$this->userCanAccessCandidate($userID, $candidateID, $jobOrderID))
        {
            return array();
        }

        $rs = $this->_db->getAssoc(
            sprintf(
                'SELECT
                    cg.grant_id,
                    cg.candidate_id,
                    cg.joborder_id,
                    cg.can_view_resume,
                    cg.can_add_notes,
                    cg.can_submit_scorecard,
                    ip.interviewer_profile_id,
                    CONCAT(c.first_name, " ", c.last_name) AS candidate_name,
                    c.email1,
                    c.phone_cell,
                    c.key_skills,
                    c.notes,
                    jo.title AS role_title,
                    ws.display_name AS stage_name,
                    ws.stage_key,
                    cw.summary,
                    cw.waiting_on_key,
                    cw.date_modified AS last_activity
                FROM
                    nesp_interviewer_profile ip
                INNER JOIN nesp_interviewer_candidate_grant cg
                    ON cg.interviewer_profile_id = ip.interviewer_profile_id
                    AND cg.date_revoked IS NULL
                INNER JOIN candidate c
                    ON c.candidate_id = cg.candidate_id
                INNER JOIN joborder jo
                    ON jo.joborder_id = cg.joborder_id
                LEFT JOIN nesp_candidate_workflow cw
                    ON cw.candidate_id = cg.candidate_id
                    AND cw.joborder_id = cg.joborder_id
                LEFT JOIN nesp_workflow_stage ws
                    ON ws.workflow_stage_id = cw.workflow_stage_id
                WHERE
                    ip.user_id = %s
                    AND ip.is_active = 1
                    AND cg.candidate_id = %s
                    AND cg.joborder_id = %s
                LIMIT 1',
                $this->_db->makeQueryInteger($userID),
                $this->_db->makeQueryInteger($candidateID),
                $this->_db->makeQueryInteger($jobOrderID)
            )
        );

        if (empty($rs))
        {
            return array();
        }

        $rs['interviews'] = $this->_db->getAllAssoc(
            sprintf(
                'SELECT interview_id, scheduled_start, scheduled_end, status_key
                 FROM nesp_interview
                 WHERE candidate_id = %s
                   AND joborder_id = %s
                   AND interviewer_profile_id = %s
                 ORDER BY scheduled_start DESC',
                $this->_db->makeQueryInteger($candidateID),
                $this->_db->makeQueryInteger($jobOrderID),
                $this->_db->makeQueryInteger($rs['interviewer_profile_id'])
            )
        );

        $rs['scorecard'] = $this->_db->getAssoc(
            sprintf(
                'SELECT scorecard_response_id, status_key, overall_recommendation, answers_json, submitted_at, locked_at, unlocked_at, lock_reason
                 FROM nesp_scorecard_response
                 WHERE candidate_id = %s
                   AND joborder_id = %s
                   AND interviewer_profile_id = %s
                 ORDER BY scorecard_response_id DESC
                 LIMIT 1',
                $this->_db->makeQueryInteger($candidateID),
                $this->_db->makeQueryInteger($jobOrderID),
                $this->_db->makeQueryInteger($rs['interviewer_profile_id'])
            )
        );
        $rs['scorecard_answers'] = array();
        if (!empty($rs['scorecard']) && isset($rs['scorecard']['answers_json']))
        {
            $decodedAnswers = json_decode($rs['scorecard']['answers_json'], true);
            if (is_array($decodedAnswers))
            {
                $rs['scorecard_answers'] = $decodedAnswers;
            }
        }

        return $rs;
    }

    public function submitScorecard($userID, $candidateID, $jobOrderID, $answers, $recommendation)
    {
        return $this->persistScorecard($userID, $candidateID, $jobOrderID, $answers, $recommendation, 'submitted');
    }

    public function saveScorecardDraft($userID, $candidateID, $jobOrderID, $answers, $recommendation)
    {
        return $this->persistScorecard($userID, $candidateID, $jobOrderID, $answers, $recommendation, 'draft');
    }

    public function unlockScorecard($actorUserID, $scorecardResponseID)
    {
        $sql = sprintf(
            'UPDATE nesp_scorecard_response
             SET unlocked_at = NOW(),
                 unlocked_by_user_id = %s,
                 lock_reason = "Craig/admin reopened for correction",
                 date_modified = NOW()
             WHERE scorecard_response_id = %s
               AND locked_at IS NOT NULL',
            $actorUserID === null ? 'NULL' : $this->_db->makeQueryInteger($actorUserID),
            $this->_db->makeQueryInteger($scorecardResponseID)
        );
        $this->_db->query($sql);
        $this->logAuditEvent(
            $actorUserID,
            'scorecard_unlocked',
            'scorecard_response',
            $scorecardResponseID,
            array()
        );

        return true;
    }

    private function persistScorecard($userID, $candidateID, $jobOrderID, $answers, $recommendation, $statusKey)
    {
        $detail = $this->getAssignedCandidateDetail($userID, $candidateID, $jobOrderID);
        if (empty($detail) || ((int) $detail['can_submit_scorecard']) !== 1)
        {
            return false;
        }

        if (!empty($detail['scorecard'])
            && $detail['scorecard']['locked_at'] !== null
            && $detail['scorecard']['unlocked_at'] === null)
        {
            return false;
        }

        $template = $this->getEnabledScorecardTemplate();
        $answersJSON = json_encode($answers);
        if ($answersJSON === false)
        {
            $answersJSON = '{}';
        }

        $interviewID = 'NULL';
        if (!empty($detail['interviews']))
        {
            $interviewID = $this->_db->makeQueryInteger($detail['interviews'][0]['interview_id']);
        }

        $submittedAt = $statusKey === 'submitted' ? 'NOW()' : 'NULL';
        $lockedAt = $statusKey === 'submitted' ? 'NOW()' : 'NULL';
        $lockReason = $statusKey === 'submitted' ? 'Submitted by assigned interviewer' : '';
        if (!empty($detail['scorecard']) && $detail['scorecard']['status_key'] === 'draft')
        {
            $responseID = (int) $detail['scorecard']['scorecard_response_id'];
            $sql = sprintf(
                'UPDATE nesp_scorecard_response
                 SET answers_json = %s,
                     overall_recommendation = %s,
                     status_key = %s,
                     submitted_at = %s,
                     locked_at = %s,
                     unlocked_at = NULL,
                     unlocked_by_user_id = NULL,
                     lock_reason = %s,
                     date_modified = NOW()
                 WHERE scorecard_response_id = %s',
                $this->_db->makeQueryString($answersJSON),
                $this->_db->makeQueryString($recommendation),
                $this->_db->makeQueryString($statusKey),
                $submittedAt,
                $lockedAt,
                $this->_db->makeQueryString($lockReason),
                $this->_db->makeQueryInteger($responseID)
            );
            $this->_db->query($sql);
        }
        else
        {
            $sql = sprintf(
                'INSERT INTO nesp_scorecard_response
                    (scorecard_template_id, interview_id, candidate_id, joborder_id, interviewer_profile_id, answers_json, overall_recommendation, status_key, submitted_at, locked_at, lock_reason, date_created, date_modified)
                 VALUES
                    (%s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, NOW(), NOW())',
                empty($template) ? 'NULL' : $this->_db->makeQueryInteger($template['scorecard_template_id']),
                $interviewID,
                $this->_db->makeQueryInteger($candidateID),
                $this->_db->makeQueryInteger($jobOrderID),
                $this->_db->makeQueryInteger($detail['interviewer_profile_id']),
                $this->_db->makeQueryString($answersJSON),
                $this->_db->makeQueryString($recommendation),
                $this->_db->makeQueryString($statusKey),
                $submittedAt,
                $lockedAt,
                $this->_db->makeQueryString($lockReason)
            );
            $this->_db->query($sql);
            $responseID = $this->_db->getLastInsertID();
        }

        $this->logAuditEvent(
            $userID,
            $statusKey === 'submitted' ? 'scorecard_submitted' : 'scorecard_draft_saved',
            'scorecard_response',
            $responseID,
            array('candidate_id' => (int) $candidateID, 'joborder_id' => (int) $jobOrderID)
        );

        return $responseID;
    }

    private function markWorkflowScorecardComplete($actorUserID, $candidateID, $jobOrderID, $scorecardResponseID)
    {
        $targetStage = $this->_db->getAssoc(
            "SELECT workflow_stage_id, stage_key
             FROM nesp_workflow_stage
             WHERE stage_key = 'scorecard_complete'
             LIMIT 1"
        );
        if (empty($targetStage))
        {
            return false;
        }

        $workflow = $this->_db->getAssoc(
            sprintf(
                'SELECT
                    cw.candidate_workflow_id,
                    cw.workflow_stage_id,
                    ws.stage_key
                 FROM nesp_candidate_workflow cw
                 LEFT JOIN nesp_workflow_stage ws
                    ON ws.workflow_stage_id = cw.workflow_stage_id
                 WHERE cw.candidate_id = %s
                   AND cw.joborder_id = %s
                 LIMIT 1',
                $this->_db->makeQueryInteger($candidateID),
                $this->_db->makeQueryInteger($jobOrderID)
            )
        );
        if (empty($workflow))
        {
            return false;
        }

        $sql = sprintf(
            'UPDATE nesp_candidate_workflow
             SET workflow_stage_id = %s,
                 waiting_on_key = "Craig",
                 summary = "Scorecard submitted by interviewer and ready for Craig review.",
                 next_action_label = "Review scorecard",
                 due_at = NOW(),
                 date_modified = NOW()
             WHERE candidate_workflow_id = %s',
            $this->_db->makeQueryInteger($targetStage['workflow_stage_id']),
            $this->_db->makeQueryInteger($workflow['candidate_workflow_id'])
        );
        $this->_db->query($sql);

        $this->logAuditEvent(
            $actorUserID,
            'candidate_workflow_stage_changed',
            'candidate_workflow',
            $workflow['candidate_workflow_id'],
            array(
                'candidate_id' => (int) $candidateID,
                'joborder_id' => (int) $jobOrderID,
                'previous_stage' => isset($workflow['stage_key']) ? $workflow['stage_key'] : '',
                'new_stage' => 'scorecard_complete',
                'reason' => 'scorecard_submitted',
                'scorecard_response_id' => (int) $scorecardResponseID,
                'result' => 'ready_for_craig_review'
            )
        );

        return true;
    }

    public function getEnabledScorecardTemplate()
    {
        return $this->_db->getAssoc(
            "SELECT scorecard_template_id, template_key, display_name, questions_json
             FROM nesp_scorecard_template
             WHERE template_key = 'nesp_standard_interview'
             ORDER BY is_enabled DESC, scorecard_template_id ASC
             LIMIT 1"
        );
    }

    public function getStaffingForecast()
    {
        $sourceStatus = $this->getStaffingSourceStatus();
        $importRows = $this->getNormalizedStaffingRows();
        $metrics = self::calculateStaffingForecastMetrics($importRows, self::getDefaultStaffingForecastConfig());
        $history = $this->_db->getAllAssoc(
            'SELECT
                schedule_history_id,
                season_year,
                season_name,
                week_start,
                event_count,
                photographer_slots,
                photographer_hours,
                source_label,
                notes
             FROM
                nesp_staffing_schedule_history
             ORDER BY
                week_start ASC'
        );

        $months = array();
        foreach ($history as $row)
        {
            $monthKey = date('m', strtotime($row['week_start']));
            if (!isset($months[$monthKey]))
            {
                $months[$monthKey] = array(
                    'month' => date('F', strtotime($row['week_start'])),
                    'weeks' => 0,
                    'events' => 0,
                    'slots' => 0,
                    'hours' => 0
                );
            }

            $months[$monthKey]['weeks']++;
            $months[$monthKey]['events'] += (int) $row['event_count'];
            $months[$monthKey]['slots'] += (int) $row['photographer_slots'];
            $months[$monthKey]['hours'] += (float) $row['photographer_hours'];
        }

        foreach ($months as $monthKey => $month)
        {
            $weeks = max(1, (int) $month['weeks']);
            $avgSlots = $month['slots'] / $weeks;
            $months[$monthKey]['avg_events'] = round($month['events'] / $weeks, 1);
            $months[$monthKey]['avg_slots'] = round($avgSlots, 1);
            $months[$monthKey]['avg_hours'] = round($month['hours'] / $weeks, 1);
            $months[$monthKey]['recommended_pipeline'] = (int) ceil($avgSlots * 1.25);
            $months[$monthKey]['confidence'] = $weeks >= 6 ? 'medium' : 'low';
        }

        return array(
            'sourceStatus' => $sourceStatus,
            'history' => $history,
            'normalizedRows' => array_slice($importRows, 0, 100),
            'metrics' => $metrics,
            'months' => array_values($months),
            'importIssues' => $this->getOpenStaffingImportIssues(50),
            'assumptions' => array(
                'No real historical Drive schedule has been imported by this PR.',
                'Historical schedule rows are opt-in fixtures unless Craig imports verified schedule history through a controlled task.',
                'Pipeline target uses 125% of average weekly photographer slots to leave room for declines, conflicts, and weather movement.',
                'Forecast output is planning guidance only and does not publish jobs, contact applicants, edit job records, or change feature flags.'
            )
        );
    }

    public function getStaffingSourceStatus()
    {
        $summary = $this->_db->getAssoc(
            'SELECT
                COUNT(*) AS import_batches,
                COALESCE(SUM(discovered_file_count), 0) AS files_discovered,
                COALESCE(SUM(imported_file_count), 0) AS files_imported,
                COALESCE(SUM(rows_imported), 0) AS rows_imported,
                COALESCE(SUM(rows_requiring_review), 0) AS rows_requiring_review,
                MAX(last_imported_at) AS last_import_date
             FROM
                nesp_staffing_import_batch
             WHERE
                undone_at IS NULL'
        );

        if (empty($summary))
        {
            $summary = array(
                'import_batches' => 0,
                'files_discovered' => 0,
                'files_imported' => 0,
                'rows_imported' => 0,
                'rows_requiring_review' => 0,
                'last_import_date' => null
            );
        }

        $summary['status_label'] = ((int) $summary['rows_imported']) > 0
            ? 'Files imported'
            : 'No historical data imported';

        return $summary;
    }

    public function getNormalizedStaffingRows()
    {
        return $this->_db->getAllAssoc(
            'SELECT
                import_row_id,
                import_batch_id,
                event_date,
                event_start_time,
                event_end_time,
                state,
                sport,
                event_name,
                role_key,
                staff_name,
                staff_count,
                staff_hours,
                raw_source_text,
                issue_count,
                status_key
             FROM
                nesp_staffing_import_row
             WHERE
                import_batch_id IN (
                    SELECT import_batch_id
                    FROM nesp_staffing_import_batch
                    WHERE undone_at IS NULL
                )
             ORDER BY
                event_date ASC,
                event_start_time ASC,
                import_row_id ASC'
        );
    }

    public function getOpenStaffingImportIssues($limit)
    {
        $limit = max(1, min(200, (int) $limit));

        return $this->_db->getAllAssoc(
            sprintf(
                'SELECT
                    issue.import_issue_id,
                    issue.import_batch_id,
                    issue.import_row_id,
                    issue.issue_key,
                    issue.severity_key,
                    issue.message,
                    issue.date_created
                 FROM
                    nesp_staffing_import_issue issue
                 INNER JOIN nesp_staffing_import_batch batch
                    ON batch.import_batch_id = issue.import_batch_id
                    AND batch.undone_at IS NULL
                 WHERE
                    issue.status_key = "open"
                 ORDER BY
                    issue.date_created DESC,
                    issue.import_issue_id DESC
                 LIMIT %s',
                $this->_db->makeQueryInteger($limit)
            )
        );
    }

    public function createDraftStaffingRecommendation($actorUserID, $title, $recommendation)
    {
        $recommendationJSON = json_encode($recommendation);
        if ($recommendationJSON === false)
        {
            $recommendationJSON = '{}';
        }

        $sql = sprintf(
            'INSERT INTO nesp_staffing_recommendation
                (created_by_user_id, title, recommendation_json, status_key, date_created, date_modified)
             VALUES
                (%s, %s, %s, "draft", NOW(), NOW())',
            $actorUserID === null ? 'NULL' : $this->_db->makeQueryInteger($actorUserID),
            $this->_db->makeQueryString($title),
            $this->_db->makeQueryString($recommendationJSON)
        );

        $this->_db->query($sql);
        $recommendationID = $this->_db->getLastInsertID();
        $this->logAuditEvent(
            $actorUserID,
            'staffing_recommendation_draft_created',
            'staffing_recommendation',
            $recommendationID,
            array('title' => $title)
        );

        return $recommendationID;
    }

    public function saveStaffingImport($actorUserID, $sourceType, $sourceIdentifier, $sourceLabel, $parseResult)
    {
        $checksum = isset($parseResult['checksum']) ? $parseResult['checksum'] : '';
        $existing = $this->_db->getAssoc(
            sprintf(
                'SELECT import_batch_id
                 FROM nesp_staffing_import_batch
                 WHERE source_type = %s
                   AND source_identifier = %s
                   AND source_checksum = %s
                   AND undone_at IS NULL
                 LIMIT 1',
                $this->_db->makeQueryString($sourceType),
                $this->_db->makeQueryString($sourceIdentifier),
                $this->_db->makeQueryString($checksum)
            )
        );

        if (!empty($existing))
        {
            return array('import_batch_id' => (int) $existing['import_batch_id'], 'status' => 'duplicate_skipped');
        }

        $rows = isset($parseResult['rows']) ? $parseResult['rows'] : array();
        $issues = isset($parseResult['issues']) ? $parseResult['issues'] : array();
        $reviewRows = 0;
        foreach ($rows as $row)
        {
            if ((int) $row['issue_count'] > 0)
            {
                $reviewRows++;
            }
        }

        $transactionStarted = $this->_db->beginTransaction();

        $sql = sprintf(
            'INSERT INTO nesp_staffing_import_batch
                (source_type, source_identifier, source_checksum, source_label, status_key, discovered_file_count, imported_file_count, rows_imported, rows_requiring_review, created_by_user_id, last_imported_at, date_created, date_modified)
             VALUES
                (%s, %s, %s, %s, "imported", 1, 1, %s, %s, %s, NOW(), NOW(), NOW())',
            $this->_db->makeQueryString($sourceType),
            $this->_db->makeQueryString($sourceIdentifier),
            $this->_db->makeQueryString($checksum),
            $this->_db->makeQueryString($sourceLabel),
            $this->_db->makeQueryInteger(count($rows)),
            $this->_db->makeQueryInteger($reviewRows),
            $actorUserID === null ? 'NULL' : $this->_db->makeQueryInteger($actorUserID)
        );
        $this->_db->query($sql);
        $batchID = $this->_db->getLastInsertID();

        $rowIDBySourceNumber = array();
        foreach ($rows as $row)
        {
            $rowSQL = sprintf(
                'INSERT INTO nesp_staffing_import_row
                    (import_batch_id, source_row_hash, source_sheet_name, source_row_number, event_date, event_start_time, event_end_time, state, sport, event_name, role_key, staff_name, staff_count, staff_hours, raw_source_text, unresolved_json, issue_count, status_key, date_created)
                 VALUES
                    (%s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, NOW())',
                $this->_db->makeQueryInteger($batchID),
                $this->_db->makeQueryString($row['source_row_hash']),
                $this->_db->makeQueryString($row['source_sheet_name']),
                $this->_db->makeQueryInteger($row['source_row_number']),
                $row['event_date'] === '' ? 'NULL' : $this->_db->makeQueryString($row['event_date']),
                $row['event_start_time'] === null ? 'NULL' : $this->_db->makeQueryString($row['event_start_time']),
                $row['event_end_time'] === null ? 'NULL' : $this->_db->makeQueryString($row['event_end_time']),
                $this->_db->makeQueryString($row['state']),
                $this->_db->makeQueryString($row['sport']),
                $this->_db->makeQueryString($row['event_name']),
                $this->_db->makeQueryString($row['role_key']),
                $this->_db->makeQueryString($row['staff_name']),
                $this->_db->makeQueryInteger($row['staff_count']),
                $this->_db->makeQueryString($row['staff_hours']),
                $this->_db->makeQueryString($row['raw_source_text']),
                $this->_db->makeQueryString($row['unresolved_json']),
                $this->_db->makeQueryInteger($row['issue_count']),
                $this->_db->makeQueryString($row['status_key'])
            );
            $this->_db->query($rowSQL);
            $rowIDBySourceNumber[$row['source_row_number']] = $this->_db->getLastInsertID();
        }

        foreach ($issues as $issue)
        {
            $rowID = isset($rowIDBySourceNumber[$issue['row_number']]) ? $rowIDBySourceNumber[$issue['row_number']] : null;
            $issueSQL = sprintf(
                'INSERT INTO nesp_staffing_import_issue
                    (import_batch_id, import_row_id, issue_key, severity_key, message, status_key, date_created)
                 VALUES
                    (%s, %s, %s, "review", %s, "open", NOW())',
                $this->_db->makeQueryInteger($batchID),
                $rowID === null ? 'NULL' : $this->_db->makeQueryInteger($rowID),
                $this->_db->makeQueryString($issue['issue_key']),
                $this->_db->makeQueryString($issue['message'])
            );
            $this->_db->query($issueSQL);
        }

        if ($transactionStarted)
        {
            $this->_db->commitTransaction();
        }

        $this->logAuditEvent(
            $actorUserID,
            'staffing_import_saved',
            'staffing_import_batch',
            $batchID,
            array('source_type' => $sourceType, 'rows_imported' => count($rows), 'rows_requiring_review' => $reviewRows)
        );

        return array('import_batch_id' => (int) $batchID, 'status' => 'imported');
    }

    public function saveApprovedStaffingImport($actorUserID, $sourceType, $sourceIdentifier, $sourceLabel, $parseResult, $approvedReviewKeys, $backupReference)
    {
        $backupReference = trim((string) $backupReference);
        if ($backupReference === '')
        {
            return array('ok' => false, 'status' => 'backup_required', 'error' => 'Verify the encrypted production backup before importing approved staffing rows.');
        }

        $plan = self::buildApprovedStaffingImportPlan($parseResult, $approvedReviewKeys);
        if (empty($plan['ok']))
        {
            return array('ok' => false, 'status' => 'invalid_selection', 'error' => $plan['error']);
        }

        $rows = $plan['rows'];
        $hashes = array();
        foreach ($rows as $row)
        {
            $hashes[] = $row['source_row_hash'];
        }
        $existingHashes = $this->getExistingStaffingRowHashes($hashes);

        $rowsToImport = array();
        $alreadyImported = 0;
        foreach ($rows as $row)
        {
            if (isset($existingHashes[$row['source_row_hash']]))
            {
                $alreadyImported++;
                continue;
            }
            $rowsToImport[] = $row;
        }

        if (empty($rowsToImport))
        {
            return array(
                'ok' => true,
                'status' => 'duplicate_skipped',
                'import_batch_id' => null,
                'rows_imported' => 0,
                'already_imported' => $alreadyImported,
                'skipped' => $alreadyImported,
                'forecast' => $this->getStaffingForecast()
            );
        }

        $checksum = isset($parseResult['checksum']) ? $parseResult['checksum'] : '';
        $transactionStarted = $this->_db->beginTransaction();

        try
        {
            $sql = sprintf(
                'INSERT INTO nesp_staffing_import_batch
                    (source_type, source_identifier, source_checksum, source_label, status_key, discovered_file_count, imported_file_count, rows_imported, rows_requiring_review, created_by_user_id, last_imported_at, date_created, date_modified)
                 VALUES
                    (%s, %s, %s, %s, "imported", 1, 1, %s, 0, %s, NOW(), NOW(), NOW())',
                $this->_db->makeQueryString($sourceType),
                $this->_db->makeQueryString($sourceIdentifier),
                $this->_db->makeQueryString($checksum),
                $this->_db->makeQueryString($sourceLabel),
                $this->_db->makeQueryInteger(count($rowsToImport)),
                $actorUserID === null ? 'NULL' : $this->_db->makeQueryInteger($actorUserID)
            );
            $this->_db->query($sql);
            $batchID = $this->_db->getLastInsertID();

            foreach ($rowsToImport as $row)
            {
                $rowSQL = sprintf(
                    'INSERT INTO nesp_staffing_import_row
                        (import_batch_id, source_row_hash, source_sheet_name, source_row_number, event_date, event_start_time, event_end_time, state, sport, event_name, role_key, staff_name, staff_count, staff_hours, raw_source_text, unresolved_json, issue_count, status_key, date_created)
                     VALUES
                        (%s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, 0, "imported", NOW())',
                    $this->_db->makeQueryInteger($batchID),
                    $this->_db->makeQueryString($row['source_row_hash']),
                    $this->_db->makeQueryString($row['source_sheet_name']),
                    $this->_db->makeQueryInteger($row['source_row_number']),
                    $row['event_date'] === '' ? 'NULL' : $this->_db->makeQueryString($row['event_date']),
                    $row['event_start_time'] === null ? 'NULL' : $this->_db->makeQueryString($row['event_start_time']),
                    $row['event_end_time'] === null ? 'NULL' : $this->_db->makeQueryString($row['event_end_time']),
                    $this->_db->makeQueryString($row['state']),
                    $this->_db->makeQueryString($row['sport']),
                    $this->_db->makeQueryString($row['event_name']),
                    $this->_db->makeQueryString($row['role_key']),
                    $this->_db->makeQueryString($row['staff_name']),
                    $this->_db->makeQueryInteger($row['staff_count']),
                    $this->_db->makeQueryString($row['staff_hours']),
                    $this->_db->makeQueryString($row['raw_source_text']),
                    $this->_db->makeQueryString($row['unresolved_json'])
                );
                $this->_db->query($rowSQL);
            }

            $this->logAuditEvent(
                $actorUserID,
                'staffing_import_approved_rows_completed',
                'staffing_import_batch',
                $batchID,
                array(
                    'source_type' => $sourceType,
                    'rows_imported' => count($rowsToImport),
                    'already_imported' => $alreadyImported,
                    'backup_verified' => true,
                    'backup_reference' => self::redactBackupReferenceForAudit($backupReference),
                    'forecast_recalculated' => true
                )
            );

            if ($transactionStarted)
            {
                $this->_db->commitTransaction();
            }
        }
        catch (Exception $e)
        {
            if ($transactionStarted)
            {
                $this->_db->rollbackTransaction();
            }
            return array('ok' => false, 'status' => 'failed', 'error' => 'Approved staffing import failed and was rolled back.');
        }

        return array(
            'ok' => true,
            'status' => 'imported',
            'import_batch_id' => (int) $batchID,
            'rows_imported' => count($rowsToImport),
            'already_imported' => $alreadyImported,
            'skipped' => $alreadyImported,
            'forecast' => $this->getStaffingForecast()
        );
    }

    public function markStaffingReviewRowsWithExistingDuplicates($reviewRows)
    {
        $hashes = array();
        foreach ($reviewRows as $reviewRow)
        {
            foreach ($reviewRow['role_hashes'] as $hash)
            {
                $hashes[] = $hash;
            }
        }
        $existingHashes = $this->getExistingStaffingRowHashes($hashes);
        foreach ($reviewRows as $index => $reviewRow)
        {
            $allImported = true;
            foreach ($reviewRow['role_hashes'] as $hash)
            {
                if (!isset($existingHashes[$hash]))
                {
                    $allImported = false;
                    break;
                }
            }
            if ($allImported && !empty($reviewRow['role_hashes']))
            {
                $reviewRows[$index]['duplicate_status'] = 'already imported';
                $reviewRows[$index]['is_valid'] = false;
            }
        }

        return $reviewRows;
    }

    private function getExistingStaffingRowHashes($hashes)
    {
        $hashes = array_values(array_unique(array_filter($hashes)));
        if (empty($hashes))
        {
            return array();
        }

        $quoted = array();
        foreach ($hashes as $hash)
        {
            $quoted[] = $this->_db->makeQueryString($hash);
        }

        $rows = $this->_db->getAllAssoc(
            sprintf(
                'SELECT source_row_hash
                 FROM nesp_staffing_import_row
                 WHERE source_row_hash IN (%s)
                   AND import_batch_id IN (
                       SELECT import_batch_id
                       FROM nesp_staffing_import_batch
                       WHERE undone_at IS NULL
                   )',
                implode(',', $quoted)
            )
        );

        $existing = array();
        foreach ($rows as $row)
        {
            $existing[$row['source_row_hash']] = true;
        }

        return $existing;
    }

    public static function redactBackupReferenceForAudit($backupReference)
    {
        $backupReference = trim((string) $backupReference);
        if ($backupReference === '')
        {
            return '';
        }

        $backupReference = preg_replace('/[A-Za-z0-9+\/=]{24,}/', '[redacted]', $backupReference);
        if (strlen($backupReference) > 160)
        {
            $backupReference = substr($backupReference, 0, 157) . '...';
        }

        return $backupReference;
    }

    public function undoStaffingImport($actorUserID, $importBatchID)
    {
        $sql = sprintf(
            'UPDATE nesp_staffing_import_batch
             SET undone_at = NOW(),
                 undone_by_user_id = %s,
                 status_key = "undone",
                 date_modified = NOW()
             WHERE import_batch_id = %s
               AND undone_at IS NULL',
            $actorUserID === null ? 'NULL' : $this->_db->makeQueryInteger($actorUserID),
            $this->_db->makeQueryInteger($importBatchID)
        );
        $this->_db->query($sql);
        $this->logAuditEvent(
            $actorUserID,
            'staffing_import_undone',
            'staffing_import_batch',
            $importBatchID,
            array()
        );

        return true;
    }

    public function getVapiConfigurationStatus()
    {
        return NESPVapiIntegration::getConfigurationStatus($this->isFeatureFlagEnabled('NESP_VAPI_ENABLED'));
    }

    public function candidateCanPrepareQuestionnaire($candidateID, $jobOrderID, $lockForUpdate = false)
    {
        $candidateID = (int) $candidateID;
        $jobOrderID = (int) $jobOrderID;
        if ($candidateID <= 0 || $jobOrderID <= 0)
        {
            return false;
        }

        $row = $this->_db->getAssoc(sprintf(
            'SELECT c.email1
             FROM nesp_candidate_workflow cw
             INNER JOIN nesp_workflow_stage ws
                ON ws.workflow_stage_id = cw.workflow_stage_id
             INNER JOIN candidate c
                ON c.candidate_id = cw.candidate_id
               AND c.is_active = 1
             INNER JOIN candidate_joborder cjo
                ON cjo.candidate_id = cw.candidate_id
               AND cjo.joborder_id = cw.joborder_id
             WHERE cw.candidate_id = %s
               AND cw.joborder_id = %s
               AND ws.stage_key = %s
             LIMIT 1%s',
            $this->_db->makeQueryInteger($candidateID),
            $this->_db->makeQueryInteger($jobOrderID),
            $this->_db->makeQueryString('new'),
            $lockForUpdate ? ' FOR UPDATE' : ''
        ));

        return !empty($row) && self::validateApplicantContactEmail($row['email1'])['ok'];
    }

    public function getCandidateQuestionnairePreview($candidateID, $jobOrderID)
    {
        $candidateID = (int) $candidateID;
        $jobOrderID = (int) $jobOrderID;
        if ($candidateID <= 0 || $jobOrderID <= 0)
        {
            return array();
        }

        $row = $this->_db->getAssoc(
            sprintf(
                'SELECT
                    c.candidate_id,
                    c.first_name,
                    c.last_name,
                    c.email1,
                    jo.joborder_id,
                    jo.title
                 FROM candidate c
                 INNER JOIN candidate_joborder cjo
                    ON cjo.candidate_id = c.candidate_id
                 INNER JOIN joborder jo
                    ON jo.joborder_id = cjo.joborder_id
                 WHERE c.candidate_id = %s
                   AND jo.joborder_id = %s
                   AND c.is_active = 1
                 LIMIT 1',
                $this->_db->makeQueryInteger($candidateID),
                $this->_db->makeQueryInteger($jobOrderID)
            )
        );
        if (empty($row))
        {
            return array();
        }
        if (!self::validateApplicantContactEmail($row['email1'])['ok'])
        {
            return array();
        }

        $row['candidate_name'] = trim($row['first_name'] . ' ' . $row['last_name']);
        $version = $this->getPublishedQuestionSetVersionForRole($row['title'], $jobOrderID);
        $row['question_set_key'] = $version['set_key'];
        $row['question_set_label'] = $version['display_name'];
        $row['question_set_intro'] = isset($version['description']) && trim((string) $version['description']) !== ''
            ? (string) $version['description']
            : self::getQuestionnaireIntroForSet($version['set_key']);
        $row['question_set_version'] = (int) $version['version_number'];
        $row['question_set_version_id'] = (int) $version['question_set_version_id'];
        $row['questions'] = $version['questions'];
        $row['question_snapshot_json'] = json_encode($row['questions']);
        $row['estimated_minutes'] = '5-10 minutes';
        return $row;
    }

    public static function validateApplicantContactEmail($email)
    {
        $email = strtolower(trim((string) $email));
        if ($email === '')
        {
            return array('ok' => false, 'email' => '', 'error' => 'Enter the applicant email address.');
        }
        if (strlen($email) > 128 || filter_var($email, FILTER_VALIDATE_EMAIL) === false)
        {
            return array('ok' => false, 'email' => '', 'error' => 'Enter a valid applicant email address.');
        }

        return array('ok' => true, 'email' => $email, 'error' => '');
    }

    public static function resolveContactNextAction($nextAction, $candidateEmail, $stageKey = 'new')
    {
        $nextAction = trim((string) $nextAction);
        if (strcasecmp($nextAction, 'Collect contact details') === 0
            && (string) $stageKey === 'new'
            && self::validateApplicantContactEmail($candidateEmail)['ok'])
        {
            return 'Send questionnaire';
        }

        return $nextAction;
    }

    public function getCandidateContactDetailsContext($workflowID, $candidateID, $jobOrderID, $lockForUpdate = false)
    {
        $workflowID = (int) $workflowID;
        $candidateID = (int) $candidateID;
        $jobOrderID = (int) $jobOrderID;
        if ($workflowID <= 0 || $candidateID <= 0 || $jobOrderID <= 0)
        {
            return array();
        }

        $row = $this->_db->getAssoc(sprintf(
            'SELECT
                cw.candidate_workflow_id,
                c.candidate_id,
                c.first_name,
                c.last_name,
                c.email1,
                c.source,
                jo.joborder_id,
                jo.title AS role_title
             FROM nesp_candidate_workflow cw
             INNER JOIN candidate c
                ON c.candidate_id = cw.candidate_id
               AND c.is_active = 1
             INNER JOIN candidate_joborder cjo
                ON cjo.candidate_id = cw.candidate_id
               AND cjo.joborder_id = cw.joborder_id
             INNER JOIN joborder jo
                ON jo.joborder_id = cw.joborder_id
             INNER JOIN nesp_workflow_stage ws
                ON ws.workflow_stage_id = cw.workflow_stage_id
             WHERE cw.candidate_workflow_id = %s
               AND cw.candidate_id = %s
               AND cw.joborder_id = %s
               AND ws.stage_key = %s
               AND cw.next_action_label = %s
             LIMIT 1%s',
            $this->_db->makeQueryInteger($workflowID),
            $this->_db->makeQueryInteger($candidateID),
            $this->_db->makeQueryInteger($jobOrderID),
            $this->_db->makeQueryString('new'),
            $this->_db->makeQueryString('Collect contact details'),
            $lockForUpdate ? ' FOR UPDATE' : ''
        ));
        if (empty($row))
        {
            return array();
        }

        $row['candidate_name'] = trim($row['first_name'] . ' ' . $row['last_name']);
        return $row;
    }

    public function saveCandidateContactDetails($workflowID, $candidateID, $jobOrderID, $email, $actorUserID)
    {
        $workflowID = (int) $workflowID;
        $candidateID = (int) $candidateID;
        $jobOrderID = (int) $jobOrderID;
        $validated = self::validateApplicantContactEmail($email);
        if (!$validated['ok'])
        {
            return $validated;
        }

        $transactionStarted = $this->_db->beginTransaction();
        if (!$transactionStarted)
        {
            return array('ok' => false, 'error' => 'Applicant contact details could not be safely locked. Try again.');
        }
        $candidateRowsLocked = $this->_db->query(
            'SELECT candidate_id FROM candidate WHERE is_active = 1 ORDER BY candidate_id FOR UPDATE'
        );
        if (!$candidateRowsLocked)
        {
            if ($transactionStarted)
            {
                $this->_db->rollbackTransaction();
            }
            return array('ok' => false, 'error' => 'Applicant contact details are temporarily busy. Try again.');
        }

        $context = $this->getCandidateContactDetailsContext($workflowID, $candidateID, $jobOrderID, true);
        if (empty($context))
        {
            if ($transactionStarted)
            {
                $this->_db->rollbackTransaction();
            }
            return array('ok' => false, 'error' => 'The selected applicant and role no longer match. Return to Needs Craig and try again.');
        }

        $duplicate = $this->_db->getAssoc(sprintf(
            'SELECT candidate_id
             FROM candidate
             WHERE candidate_id <> %s
               AND is_active = 1
               AND (
                    LOWER(TRIM(email1)) = %s
                    OR LOWER(TRIM(email2)) = %s
               )
             LIMIT 1',
            $this->_db->makeQueryInteger($candidateID),
            $this->_db->makeQueryString($validated['email']),
            $this->_db->makeQueryString($validated['email'])
        ));
        if (!empty($duplicate))
        {
            if ($transactionStarted)
            {
                $this->_db->rollbackTransaction();
            }
            return array('ok' => false, 'error' => 'That email is already attached to another active candidate. Review the possible duplicate before continuing.');
        }

        $emailChanged = strtolower(trim((string) $context['email1'])) !== $validated['email'];
        $candidateUpdated = $this->_db->query(sprintf(
            'UPDATE candidate
             SET email1 = %s,
                 date_modified = NOW()
             WHERE candidate_id = %s
               AND is_active = 1',
            $this->_db->makeQueryString($validated['email']),
            $this->_db->makeQueryInteger($candidateID)
        ));
        $workflowUpdated = $this->_db->query(sprintf(
            'UPDATE nesp_candidate_workflow cw
             INNER JOIN nesp_workflow_stage ws
                ON ws.workflow_stage_id = cw.workflow_stage_id
             SET cw.waiting_on_key = %s,
                 summary = %s,
                 cw.next_action_label = %s,
                 cw.date_modified = NOW()
             WHERE cw.candidate_workflow_id = %s
               AND cw.candidate_id = %s
               AND cw.joborder_id = %s
               AND ws.stage_key = %s
               AND cw.next_action_label = %s',
            $this->_db->makeQueryString('Craig'),
            $this->_db->makeQueryString('Applicant contact email verified. Review and prepare the role-specific questionnaire.'),
            $this->_db->makeQueryString('Send questionnaire'),
            $this->_db->makeQueryInteger($workflowID),
            $this->_db->makeQueryInteger($candidateID),
            $this->_db->makeQueryInteger($jobOrderID),
            $this->_db->makeQueryString('new'),
            $this->_db->makeQueryString('Collect contact details')
        ));
        $workflowUpdatedExactlyOnce = $workflowUpdated && $this->_db->getAffectedRows() === 1;
        if (!$candidateUpdated || !$workflowUpdatedExactlyOnce)
        {
            if ($transactionStarted)
            {
                $this->_db->rollbackTransaction();
            }
            return array('ok' => false, 'error' => 'The contact details could not be saved. No changes were committed.');
        }

        $auditLogged = $this->logAuditEvent($actorUserID, 'candidate_contact_email_saved', 'candidate_workflow', $workflowID, array(
            'candidate_id' => $candidateID,
            'joborder_id' => $jobOrderID,
            'email_changed' => $emailChanged,
            'previous_email_present' => trim((string) $context['email1']) !== ''
        ));
        if (!$auditLogged)
        {
            if ($transactionStarted)
            {
                $this->_db->rollbackTransaction();
            }
            return array('ok' => false, 'error' => 'The contact details could not be audited. No changes were committed.');
        }
        if ($transactionStarted)
        {
            $this->_db->commitTransaction();
        }

        return array('ok' => true, 'email' => $validated['email'], 'error' => '');
    }

    public function ensureDefaultQuestionSetsSeeded($actorUserID = null)
    {
        if (!$this->isTableInstalled('nesp_question_set')
            || !$this->isTableInstalled('nesp_question_set_version')
            || !$this->isTableInstalled('nesp_question_set_question')
            || !$this->isTableInstalled('nesp_question_set_role_match'))
        {
            return false;
        }

        $defaults = self::getQuestionnaireQuestionSets();
        foreach ($defaults as $setKey => $set)
        {
            $setRow = $this->_db->getAssoc(sprintf(
                'SELECT question_set_id FROM nesp_question_set WHERE set_key = %s LIMIT 1',
                $this->_db->makeQueryString($setKey)
            ));
            if (empty($setRow))
            {
                $this->_db->query(sprintf(
                    'INSERT INTO nesp_question_set
                        (set_key, display_name, description, status_key, created_by_user_id, date_created, date_modified)
                     VALUES
                        (%s, %s, %s, "active", %s, NOW(), NOW())',
                    $this->_db->makeQueryString($setKey),
                    $this->_db->makeQueryString($set['label']),
                    $this->_db->makeQueryString(isset($set['intro']) ? $set['intro'] : 'Seeded default NESP question set.'),
                    $actorUserID === null ? 'NULL' : $this->_db->makeQueryInteger($actorUserID)
                ));
                $questionSetID = (int) $this->_db->getLastInsertID();
            }
            else
            {
                $questionSetID = (int) $setRow['question_set_id'];
            }

            $versionRow = $this->_db->getAssoc(sprintf(
                'SELECT question_set_version_id
                 FROM nesp_question_set_version
                 WHERE question_set_id = %s
                   AND version_number = 1
                 LIMIT 1',
                $this->_db->makeQueryInteger($questionSetID)
            ));
            if (empty($versionRow))
            {
                $questions = self::normalizeQuestionnaireSnapshotQuestions(self::getQuestionnaireQuestionsForSet($setKey));
                $snapshotJSON = json_encode($questions);
                $roleMatches = array();
                $priority = 10;
                foreach ((array) $set['match'] as $matchText)
                {
                    $roleMatches[] = array('match_text' => $matchText, 'joborder_id' => null, 'priority' => $priority, 'is_active' => 1);
                    $priority += 10;
                }
                $this->_db->query(sprintf(
                    'INSERT INTO nesp_question_set_version
                        (question_set_id, version_number, status_key, display_name, description, role_match_snapshot_json, snapshot_json, created_by_user_id, published_by_user_id, published_at, date_created, date_modified)
                     VALUES
                        (%s, 1, "published", %s, %s, %s, %s, %s, %s, UTC_TIMESTAMP(), NOW(), NOW())',
                    $this->_db->makeQueryInteger($questionSetID),
                    $this->_db->makeQueryString($set['label']),
                    $this->_db->makeQueryString(isset($set['intro']) ? $set['intro'] : ''),
                    $this->_db->makeQueryString(json_encode($roleMatches)),
                    $this->_db->makeQueryString($snapshotJSON),
                    $actorUserID === null ? 'NULL' : $this->_db->makeQueryInteger($actorUserID),
                    $actorUserID === null ? 'NULL' : $this->_db->makeQueryInteger($actorUserID)
                ));
                $versionID = (int) $this->_db->getLastInsertID();
                $this->replaceQuestionSetVersionQuestions($versionID, $questions);
                $this->_db->query(sprintf(
                    'UPDATE nesp_question_set
                     SET current_version_id = %s,
                         date_modified = NOW()
                     WHERE question_set_id = %s',
                    $this->_db->makeQueryInteger($versionID),
                    $this->_db->makeQueryInteger($questionSetID)
                ));
            }

            $priority = 10;
            foreach ((array) $set['match'] as $matchText)
            {
                $matchText = trim((string) $matchText);
                if ($matchText === '')
                {
                    continue;
                }
                $existingMatch = $this->_db->getAssoc(sprintf(
                    'SELECT question_set_role_match_id
                     FROM nesp_question_set_role_match
                     WHERE question_set_id = %s
                       AND match_text = %s
                       AND joborder_id IS NULL
                     LIMIT 1',
                    $this->_db->makeQueryInteger($questionSetID),
                    $this->_db->makeQueryString($matchText)
                ));
                if (empty($existingMatch))
                {
                    $this->_db->query(sprintf(
                        'INSERT INTO nesp_question_set_role_match
                            (question_set_id, match_text, joborder_id, priority, is_active, date_created, date_modified)
                         VALUES
                            (%s, %s, NULL, %s, 1, NOW(), NOW())',
                        $this->_db->makeQueryInteger($questionSetID),
                        $this->_db->makeQueryString($matchText),
                        $this->_db->makeQueryInteger($priority)
                    ));
                }
                $priority += 10;
            }

            $this->reconcileRequestedQuestionnaireSetRelease($setKey, $set, $questionSetID, $actorUserID);
        }

        return true;
    }

    /**
     * The two applicant-facing pre-interview sets are managed releases.  This
     * upgrades existing installations by publishing an immutable next version,
     * never changing a snapshot already issued to an applicant.
     */
    private function reconcileRequestedQuestionnaireSetRelease($setKey, $set, $questionSetID, $actorUserID)
    {
        if (!in_array($setKey, array('photography_assistant_poser', 'weekend_sports_photographer'), true)
            || !$this->isTableInstalled('nesp_question_set_builtin_release'))
        {
            return;
        }

        $questions = self::normalizeQuestionnaireSnapshotQuestions(self::getQuestionnaireQuestionsForSet($setKey));
        $roleMatches = array();
        $priority = 10;
        foreach ((array) $set['match'] as $matchText)
        {
            $roleMatches[] = array('match_text' => trim((string) $matchText), 'joborder_id' => null, 'priority' => $priority, 'is_active' => 1);
            $priority += 10;
        }
        $releaseHash = self::questionnaireSetReleaseHash($set['label'], isset($set['intro']) ? $set['intro'] : '', $questions, $roleMatches);
        $release = $this->_db->getAssoc(sprintf(
            'SELECT release_hash, question_set_version_id
             FROM nesp_question_set_builtin_release
             WHERE set_key = %s
             LIMIT 1',
            $this->_db->makeQueryString($setKey)
        ));
        $current = $this->_db->getAssoc(sprintf(
            'SELECT current_version_id
             FROM nesp_question_set
             WHERE question_set_id = %s
             LIMIT 1',
            $this->_db->makeQueryInteger($questionSetID)
        ));
        $currentVersionID = empty($current) ? 0 : (int) $current['current_version_id'];
        $currentDetail = $currentVersionID > 0 ? $this->getQuestionSetVersionDetail($currentVersionID) : array();
        $currentHash = empty($currentDetail)
            ? ''
            : self::questionnaireSetReleaseHash(
                (string) $currentDetail['display_name'],
                isset($currentDetail['description']) ? (string) $currentDetail['description'] : '',
                $currentDetail['questions'],
                $currentDetail['role_matches']
            );
        if (!empty($release)
            && hash_equals((string) $release['release_hash'], $releaseHash)
            && (int) $release['question_set_version_id'] === $currentVersionID
            && $currentHash === $releaseHash)
        {
            return;
        }
        if ($currentHash === $releaseHash)
        {
            $this->_db->query(sprintf(
                'INSERT INTO nesp_question_set_builtin_release
                    (set_key, release_hash, question_set_version_id, published_by_user_id, date_created, date_modified)
                 VALUES (%s, %s, %s, %s, NOW(), NOW())
                 ON DUPLICATE KEY UPDATE
                    release_hash = VALUES(release_hash),
                    question_set_version_id = VALUES(question_set_version_id),
                    published_by_user_id = VALUES(published_by_user_id),
                    date_modified = NOW()',
                $this->_db->makeQueryString($setKey),
                $this->_db->makeQueryString($releaseHash),
                $this->_db->makeQueryInteger($currentVersionID),
                $actorUserID === null ? 'NULL' : $this->_db->makeQueryInteger($actorUserID)
            ));
            return;
        }

        $nextVersionRow = $this->_db->getColumn(sprintf(
            'SELECT COALESCE(MAX(version_number), 0) + 1
             FROM nesp_question_set_version
             WHERE question_set_id = %s',
            $this->_db->makeQueryInteger($questionSetID)
        ), 0, 0);
        $nextVersion = is_array($nextVersionRow) && isset($nextVersionRow[0])
            ? (int) $nextVersionRow[0]
            : 1;
        $this->_db->query(sprintf(
            'INSERT INTO nesp_question_set_version
                (question_set_id, version_number, status_key, display_name, description, role_match_snapshot_json, snapshot_json, created_by_user_id, published_by_user_id, published_at, date_created, date_modified)
             VALUES
                (%s, %s, "published", %s, %s, %s, %s, %s, %s, UTC_TIMESTAMP(), NOW(), NOW())',
            $this->_db->makeQueryInteger($questionSetID),
            $this->_db->makeQueryInteger($nextVersion),
            $this->_db->makeQueryString($set['label']),
            $this->_db->makeQueryString(isset($set['intro']) ? $set['intro'] : ''),
            $this->_db->makeQueryString(json_encode($roleMatches)),
            $this->_db->makeQueryString(json_encode($questions)),
            $actorUserID === null ? 'NULL' : $this->_db->makeQueryInteger($actorUserID),
            $actorUserID === null ? 'NULL' : $this->_db->makeQueryInteger($actorUserID)
        ));
        $versionID = (int) $this->_db->getLastInsertID();
        if ($versionID <= 0)
        {
            return;
        }
        $this->replaceQuestionSetVersionQuestions($versionID, $questions);
        $this->replaceQuestionSetRoleMatches($questionSetID, $roleMatches);
        $this->_db->query(sprintf(
            'UPDATE nesp_question_set
             SET display_name = %s,
                 description = %s,
                 current_version_id = %s,
                 status_key = "active",
                 date_modified = NOW()
             WHERE question_set_id = %s',
            $this->_db->makeQueryString($set['label']),
            $this->_db->makeQueryString(isset($set['intro']) ? $set['intro'] : ''),
            $this->_db->makeQueryInteger($versionID),
            $this->_db->makeQueryInteger($questionSetID)
        ));
        $this->_db->query(sprintf(
            'INSERT INTO nesp_question_set_builtin_release
                (set_key, release_hash, question_set_version_id, published_by_user_id, date_created, date_modified)
             VALUES (%s, %s, %s, %s, NOW(), NOW())
             ON DUPLICATE KEY UPDATE
                release_hash = VALUES(release_hash),
                question_set_version_id = VALUES(question_set_version_id),
                published_by_user_id = VALUES(published_by_user_id),
                date_modified = NOW()',
            $this->_db->makeQueryString($setKey),
            $this->_db->makeQueryString($releaseHash),
            $this->_db->makeQueryInteger($versionID),
            $actorUserID === null ? 'NULL' : $this->_db->makeQueryInteger($actorUserID)
        ));
        $this->logAuditEvent(
            $actorUserID,
            'question_set_builtin_content_published',
            'question_set_version',
            $versionID,
            array('set_key' => $setKey, 'release_hash' => $releaseHash, 'previous_version_id' => $currentVersionID)
        );
    }

    private static function questionnaireSetReleaseHash($displayName, $description, $questions, $roleMatches)
    {
        return hash('sha256', json_encode(array(
            'display_name' => trim((string) $displayName),
            'description' => trim((string) $description),
            'questions' => self::normalizeQuestionnaireSnapshotQuestions($questions),
            'role_matches' => $roleMatches
        )));
    }

    public function getQuestionSetAdminRows()
    {
        $this->ensureDefaultQuestionSetsSeeded();
        if (!$this->isTableInstalled('nesp_question_set'))
        {
            return array();
        }

        $rows = $this->_db->getAllAssoc(
            'SELECT
                qs.question_set_id,
                qs.set_key,
                qs.display_name,
                qs.description,
                qs.status_key,
                qs.current_version_id,
                qsv.version_number AS current_version_number,
                qsv.status_key AS current_version_status,
                COUNT(DISTINCT draft.question_set_version_id) AS draft_count,
                COUNT(DISTINCT issued.screening_questionnaire_id) AS issued_count,
                GROUP_CONCAT(DISTINCT
                    CASE
                        WHEN rm.joborder_id IS NOT NULL THEN CONCAT("job ", rm.joborder_id)
                        ELSE rm.match_text
                    END
                    ORDER BY rm.priority ASC SEPARATOR ", "
                ) AS role_matches
             FROM nesp_question_set qs
             LEFT JOIN nesp_question_set_version qsv
                ON qsv.question_set_version_id = qs.current_version_id
             LEFT JOIN nesp_question_set_version draft
                ON draft.question_set_id = qs.question_set_id
               AND draft.status_key = "draft"
             LEFT JOIN nesp_question_set_role_match rm
                ON rm.question_set_id = qs.question_set_id
               AND rm.is_active = 1
             LEFT JOIN nesp_screening_questionnaire issued
                ON issued.question_set_version_id = qs.current_version_id
             GROUP BY qs.question_set_id
             ORDER BY qs.status_key ASC, qs.display_name ASC'
        );

        return $rows;
    }

    public function getQuestionSetVersionDetail($versionID)
    {
        $versionID = (int) $versionID;
        if ($versionID <= 0 || !$this->isTableInstalled('nesp_question_set_version'))
        {
            return array();
        }
        $detail = $this->_db->getAssoc(sprintf(
            'SELECT qsv.*,
                    qs.set_key,
                    COALESCE(NULLIF(qsv.display_name, ""), qs.display_name) AS display_name,
                    COALESCE(qsv.description, qs.description) AS description,
                    qs.status_key AS set_status_key
             FROM nesp_question_set_version qsv
             INNER JOIN nesp_question_set qs
                ON qs.question_set_id = qsv.question_set_id
             WHERE qsv.question_set_version_id = %s
             LIMIT 1',
            $this->_db->makeQueryInteger($versionID)
        ));
        if (empty($detail))
        {
            return array();
        }
        $detail['questions'] = $this->getQuestionsForQuestionSetVersion($detail);
        $draftMatches = !empty($detail['role_match_snapshot_json']) ? json_decode((string) $detail['role_match_snapshot_json'], true) : null;
        $detail['role_matches'] = is_array($draftMatches) ? $this->normalizeQuestionSetRoleMatches($draftMatches) : $this->getQuestionSetRoleMatches((int) $detail['question_set_id']);
        return $detail;
    }

    public function createQuestionSetDraftFromVersion($questionSetID, $sourceVersionID, $actorUserID)
    {
        $this->ensureDefaultQuestionSetsSeeded($actorUserID);
        $questionSetID = (int) $questionSetID;
        $sourceVersionID = (int) $sourceVersionID;
        if ($sourceVersionID <= 0 && $questionSetID > 0)
        {
            $source = $this->_db->getAssoc(sprintf(
                'SELECT current_version_id FROM nesp_question_set WHERE question_set_id = %s LIMIT 1',
                $this->_db->makeQueryInteger($questionSetID)
            ));
            $sourceVersionID = empty($source) ? 0 : (int) $source['current_version_id'];
        }
        $sourceDetail = $this->getQuestionSetVersionDetail($sourceVersionID);
        if (empty($sourceDetail))
        {
            return false;
        }
        $existingDraft = $this->_db->getAssoc(sprintf(
            'SELECT question_set_version_id
             FROM nesp_question_set_version
             WHERE question_set_id = %s
               AND status_key = "draft"
             ORDER BY question_set_version_id DESC
             LIMIT 1',
            $this->_db->makeQueryInteger((int) $sourceDetail['question_set_id'])
        ));
        if (!empty($existingDraft))
        {
            return (int) $existingDraft['question_set_version_id'];
        }

        $nextVersionRow = $this->_db->getColumn(sprintf(
            'SELECT COALESCE(MAX(version_number), 0) + 1
             FROM nesp_question_set_version
             WHERE question_set_id = %s',
            $this->_db->makeQueryInteger((int) $sourceDetail['question_set_id'])
        ), 0, 0);
        $nextVersion = is_array($nextVersionRow) && isset($nextVersionRow[0])
            ? (int) $nextVersionRow[0]
            : 1;
        $questions = self::normalizeQuestionnaireSnapshotQuestions($sourceDetail['questions']);
        $roleMatches = $this->normalizeQuestionSetRoleMatches($sourceDetail['role_matches']);
        $this->_db->query(sprintf(
            'INSERT INTO nesp_question_set_version
                (question_set_id, version_number, status_key, display_name, description, role_match_snapshot_json, snapshot_json, draft_source_version_id, created_by_user_id, date_created, date_modified)
             VALUES
                (%s, %s, "draft", %s, %s, %s, %s, %s, %s, NOW(), NOW())',
            $this->_db->makeQueryInteger((int) $sourceDetail['question_set_id']),
            $this->_db->makeQueryInteger($nextVersion),
            $this->_db->makeQueryString((string) $sourceDetail['display_name']),
            $this->_db->makeQueryString((string) $sourceDetail['description']),
            $this->_db->makeQueryString(json_encode($roleMatches)),
            $this->_db->makeQueryString(json_encode($questions)),
            $this->_db->makeQueryInteger($sourceVersionID),
            $actorUserID === null ? 'NULL' : $this->_db->makeQueryInteger($actorUserID)
        ));
        $draftID = (int) $this->_db->getLastInsertID();
        $this->replaceQuestionSetVersionQuestions($draftID, $questions);
        $this->logAuditEvent($actorUserID, 'question_set_draft_created', 'question_set_version', $draftID, array('source_version_id' => $sourceVersionID));
        return $draftID;
    }

    public function saveQuestionSetDraft($versionID, $input, $actorUserID)
    {
        $detail = $this->getQuestionSetVersionDetail($versionID);
        if (empty($detail) || $detail['status_key'] !== 'draft')
        {
            return array('ok' => false, 'error' => 'Only draft question-set versions can be edited.');
        }

        $displayName = isset($input['displayName']) ? trim((string) $input['displayName']) : $detail['display_name'];
        if ($displayName === '')
        {
            return array('ok' => false, 'error' => 'Question set name is required.');
        }
        $description = isset($input['description']) ? trim((string) $input['description']) : '';
        $questions = $this->normalizeQuestionSetEditorQuestions($input);
        if (empty($questions))
        {
            return array('ok' => false, 'error' => 'At least one question is required.');
        }

        $roleMatches = $this->normalizeQuestionSetRoleMatches(isset($input['roleMatches']) ? $input['roleMatches'] : array());
        $this->_db->query(sprintf(
            'UPDATE nesp_question_set_version
             SET display_name = %s,
                 description = %s,
                 role_match_snapshot_json = %s,
                 snapshot_json = %s,
                 date_modified = NOW()
             WHERE question_set_version_id = %s
               AND status_key = "draft"',
            $this->_db->makeQueryString($displayName),
            $this->_db->makeQueryString($description),
            $this->_db->makeQueryString(json_encode($roleMatches)),
            $this->_db->makeQueryString(json_encode($questions)),
            $this->_db->makeQueryInteger((int) $versionID)
        ));
        $this->replaceQuestionSetVersionQuestions((int) $versionID, $questions);
        $this->logAuditEvent($actorUserID, 'question_set_draft_saved', 'question_set_version', (int) $versionID, array('question_count' => count($questions)));
        return array('ok' => true, 'version_id' => (int) $versionID);
    }

    public function publishQuestionSetDraft($versionID, $actorUserID)
    {
        $detail = $this->getQuestionSetVersionDetail($versionID);
        if (empty($detail) || $detail['status_key'] !== 'draft')
        {
            return false;
        }
        $questions = self::normalizeQuestionnaireSnapshotQuestions($detail['questions']);
        if (empty($questions))
        {
            return false;
        }
        $this->_db->query(sprintf(
            'UPDATE nesp_question_set_version
             SET status_key = "published",
                 snapshot_json = %s,
                 published_by_user_id = %s,
                 published_at = UTC_TIMESTAMP(),
                 date_modified = NOW()
             WHERE question_set_version_id = %s
               AND status_key = "draft"',
            $this->_db->makeQueryString(json_encode($questions)),
            $actorUserID === null ? 'NULL' : $this->_db->makeQueryInteger($actorUserID),
            $this->_db->makeQueryInteger((int) $versionID)
        ));
        if ($this->_db->getAffectedRows() !== 1)
        {
            return false;
        }
        $this->_db->query(sprintf(
            'UPDATE nesp_question_set
             SET display_name = %s,
                 description = %s,
                 current_version_id = %s,
                 status_key = "active",
                 date_modified = NOW()
             WHERE question_set_id = %s',
            $this->_db->makeQueryString((string) $detail['display_name']),
            $this->_db->makeQueryString((string) $detail['description']),
            $this->_db->makeQueryInteger((int) $versionID),
            $this->_db->makeQueryInteger((int) $detail['question_set_id'])
        ));
        $this->replaceQuestionSetRoleMatches((int) $detail['question_set_id'], $detail['role_matches']);
        $this->logAuditEvent($actorUserID, 'question_set_version_published', 'question_set_version', (int) $versionID, array('version_number' => (int) $detail['version_number']));
        return true;
    }

    public function archiveQuestionSet($questionSetID, $actorUserID)
    {
        $questionSetID = (int) $questionSetID;
        if ($questionSetID <= 0)
        {
            return false;
        }
        $this->_db->query(sprintf(
            'UPDATE nesp_question_set
             SET status_key = "archived",
                 date_modified = NOW()
             WHERE question_set_id = %s',
            $this->_db->makeQueryInteger($questionSetID)
        ));
        $this->logAuditEvent($actorUserID, 'question_set_archived', 'question_set', $questionSetID, array());
        return $this->_db->getAffectedRows() === 1;
    }

    private function getPublishedQuestionSetVersionForRole($roleTitle, $jobOrderID)
    {
        $this->ensureDefaultQuestionSetsSeeded();
        if ($this->isTableInstalled('nesp_question_set_version'))
        {
            $matchSQL = sprintf(
                'SELECT qsv.*,
                        qs.set_key,
                        COALESCE(NULLIF(qsv.display_name, ""), qs.display_name) AS display_name,
                        COALESCE(qsv.description, qs.description) AS description
                 FROM nesp_question_set_role_match rm
                 INNER JOIN nesp_question_set qs
                    ON qs.question_set_id = rm.question_set_id
                   AND qs.status_key = "active"
                 INNER JOIN nesp_question_set_version qsv
                    ON qsv.question_set_version_id = qs.current_version_id
                   AND qsv.status_key = "published"
                 WHERE rm.is_active = 1
                   AND (rm.joborder_id = %s OR (rm.joborder_id IS NULL AND %s LIKE CONCAT("%%", rm.match_text, "%%")))
                 ORDER BY CASE WHEN rm.joborder_id = %s THEN 0 ELSE 1 END, rm.priority ASC
                 LIMIT 1',
                $this->_db->makeQueryInteger((int) $jobOrderID),
                $this->_db->makeQueryString(strtolower((string) $roleTitle)),
                $this->_db->makeQueryInteger((int) $jobOrderID)
            );
            $row = $this->_db->getAssoc($matchSQL);
            if (empty($row))
            {
                $row = $this->_db->getAssoc(
                    'SELECT qsv.*,
                            qs.set_key,
                            COALESCE(NULLIF(qsv.display_name, ""), qs.display_name) AS display_name,
                            COALESCE(qsv.description, qs.description) AS description
                     FROM nesp_question_set qs
                     INNER JOIN nesp_question_set_version qsv
                        ON qsv.question_set_version_id = qs.current_version_id
                       AND qsv.status_key = "published"
                     WHERE qs.set_key = "weekend_sports_photographer"
                       AND qs.status_key = "active"
                     LIMIT 1'
                );
            }
            if (!empty($row))
            {
                $row['questions'] = $this->getQuestionsForQuestionSetVersion($row);
                return $row;
            }
        }

        $fallback = self::getQuestionnaireSetForRole($roleTitle);
        return array(
            'question_set_version_id' => 0,
            'set_key' => $fallback['key'],
            'display_name' => $fallback['label'],
            'description' => self::getQuestionnaireIntroForSet($fallback['key']),
            'version_number' => 1,
            'questions' => self::getQuestionnaireQuestionsForSet($fallback['key'])
        );
    }

    private function getQuestionsForQuestionSetVersion($versionRow)
    {
        if (!empty($versionRow['snapshot_json']))
        {
            $decoded = json_decode($versionRow['snapshot_json'], true);
            if (is_array($decoded))
            {
                return self::normalizeQuestionnaireSnapshotQuestions($decoded);
            }
        }
        if (!empty($versionRow['question_set_version_id']) && $this->isTableInstalled('nesp_question_set_question'))
        {
            $rows = $this->_db->getAllAssoc(sprintf(
                'SELECT question_key, question_label, help_text, question_type, is_required, choices_json, sort_order
                 FROM nesp_question_set_question
                 WHERE question_set_version_id = %s
                 ORDER BY sort_order ASC, question_set_question_id ASC',
                $this->_db->makeQueryInteger((int) $versionRow['question_set_version_id'])
            ));
            $questions = array();
            foreach ($rows as $row)
            {
                $choices = json_decode((string) $row['choices_json'], true);
                $questions[] = array(
                    'key' => $row['question_key'],
                    'label' => $row['question_label'],
                    'help' => $row['help_text'],
                    'type' => $row['question_type'],
                    'required' => ((int) $row['is_required']) === 1,
                    'choices' => is_array($choices) ? $choices : array(),
                    'sort_order' => (int) $row['sort_order']
                );
            }
            return self::normalizeQuestionnaireSnapshotQuestions($questions);
        }
        return self::getQuestionnaireQuestionsForSet(isset($versionRow['set_key']) ? $versionRow['set_key'] : '');
    }

    private function questionnaireQuestionsForIssuedRow($row)
    {
        if (!empty($row['question_snapshot_json']))
        {
            $decoded = json_decode($row['question_snapshot_json'], true);
            if (is_array($decoded))
            {
                return self::normalizeQuestionnaireSnapshotQuestions($decoded);
            }
        }
        if (!empty($row['question_set_version_id']))
        {
            $detail = $this->getQuestionSetVersionDetail((int) $row['question_set_version_id']);
            if (!empty($detail))
            {
                return $detail['questions'];
            }
        }
        return self::getQuestionnaireQuestionsForSet($row['question_set_key']);
    }

    private function replaceQuestionSetVersionQuestions($versionID, $questions)
    {
        $versionID = (int) $versionID;
        $this->_db->query(sprintf(
            'DELETE FROM nesp_question_set_question WHERE question_set_version_id = %s',
            $this->_db->makeQueryInteger($versionID)
        ));
        foreach (self::normalizeQuestionnaireSnapshotQuestions($questions) as $question)
        {
            $this->_db->query(sprintf(
                'INSERT INTO nesp_question_set_question
                    (question_set_version_id, question_key, question_label, help_text, question_type, is_required, choices_json, sort_order, date_created, date_modified)
                 VALUES
                    (%s, %s, %s, %s, %s, %s, %s, %s, NOW(), NOW())',
                $this->_db->makeQueryInteger($versionID),
                $this->_db->makeQueryString($question['key']),
                $this->_db->makeQueryString($question['label']),
                $this->_db->makeQueryString($question['help']),
                $this->_db->makeQueryString($question['type']),
                !empty($question['required']) ? '1' : '0',
                $this->_db->makeQueryString(json_encode($question['choices'])),
                $this->_db->makeQueryInteger((int) $question['sort_order'])
            ));
        }
    }

    private function normalizeQuestionSetEditorQuestions($input)
    {
        $rows = array();
        $keys = isset($input['questionKey']) && is_array($input['questionKey']) ? $input['questionKey'] : array();
        foreach ($keys as $index => $key)
        {
            $choicesText = isset($input['questionChoices'][$index]) ? trim((string) $input['questionChoices'][$index]) : '';
            $choices = array();
            if ($choicesText !== '')
            {
                foreach (preg_split('/\r\n|\n|\r/', $choicesText) as $choice)
                {
                    $choice = trim($choice);
                    if ($choice !== '')
                    {
                        $choices[] = $choice;
                    }
                }
            }
            $rows[] = array(
                'key' => $key,
                'label' => isset($input['questionLabel'][$index]) ? $input['questionLabel'][$index] : '',
                'help' => isset($input['questionHelp'][$index]) ? $input['questionHelp'][$index] : '',
                'type' => isset($input['questionType'][$index]) ? $input['questionType'][$index] : 'textarea',
                'required' => isset($input['questionRequired'][$index]) && (string) $input['questionRequired'][$index] === '1',
                'choices' => $choices,
                'sort_order' => isset($input['questionSortOrder'][$index]) ? (int) $input['questionSortOrder'][$index] : (($index + 1) * 10)
            );
        }
        return self::normalizeQuestionnaireSnapshotQuestions($rows);
    }

    private function getQuestionSetRoleMatches($questionSetID)
    {
        if (!$this->isTableInstalled('nesp_question_set_role_match'))
        {
            return array();
        }
        return $this->_db->getAllAssoc(sprintf(
            'SELECT question_set_role_match_id, match_text, joborder_id, priority, is_active
             FROM nesp_question_set_role_match
             WHERE question_set_id = %s
             ORDER BY is_active DESC, priority ASC, question_set_role_match_id ASC',
            $this->_db->makeQueryInteger((int) $questionSetID)
        ));
    }

    private function normalizeQuestionSetRoleMatches($matches)
    {
        $clean = array();
        $priority = 10;
        foreach ((array) $matches as $match)
        {
            $matchText = isset($match['match_text']) ? trim((string) $match['match_text']) : '';
            $jobOrderID = isset($match['joborder_id']) ? (int) $match['joborder_id'] : 0;
            if ($matchText === '' && $jobOrderID <= 0)
            {
                continue;
            }
            $clean[] = array(
                'match_text' => substr($matchText, 0, 160),
                'joborder_id' => $jobOrderID > 0 ? $jobOrderID : null,
                'priority' => isset($match['priority']) ? (int) $match['priority'] : $priority,
                'is_active' => isset($match['is_active']) ? (int) $match['is_active'] : 1
            );
            $priority += 10;
        }
        return $clean;
    }

    private function replaceQuestionSetRoleMatches($questionSetID, $matches)
    {
        if (!$this->isTableInstalled('nesp_question_set_role_match'))
        {
            return;
        }
        $this->_db->query(sprintf(
            'UPDATE nesp_question_set_role_match
             SET is_active = 0,
                 date_modified = NOW()
             WHERE question_set_id = %s',
            $this->_db->makeQueryInteger((int) $questionSetID)
        ));
        $priority = 10;
        foreach ((array) $matches as $match)
        {
            $matchText = isset($match['match_text']) ? trim((string) $match['match_text']) : '';
            $jobOrderID = isset($match['joborder_id']) ? (int) $match['joborder_id'] : 0;
            if ($matchText === '' && $jobOrderID <= 0)
            {
                continue;
            }
            $this->_db->query(sprintf(
                'INSERT INTO nesp_question_set_role_match
                    (question_set_id, match_text, joborder_id, priority, is_active, date_created, date_modified)
                 VALUES
                    (%s, %s, %s, %s, 1, NOW(), NOW())',
                $this->_db->makeQueryInteger((int) $questionSetID),
                $this->_db->makeQueryString($matchText),
                $jobOrderID > 0 ? $this->_db->makeQueryInteger($jobOrderID) : 'NULL',
                $this->_db->makeQueryInteger($priority)
            ));
            $priority += 10;
        }
    }

    public function requestQuestionnaire($candidateID, $jobOrderID, $actorUserID, $requireEligibleWorkflow = false)
    {
        $transactionStarted = $this->_db->beginTransaction();
        if ($requireEligibleWorkflow && !$transactionStarted)
        {
            return false;
        }
        if ($requireEligibleWorkflow && !$this->candidateCanPrepareQuestionnaire($candidateID, $jobOrderID, true))
        {
            $this->_db->rollbackTransaction();
            return false;
        }
        $preview = $this->getCandidateQuestionnairePreview($candidateID, $jobOrderID);
        if (empty($preview))
        {
            if ($transactionStarted)
            {
                $this->_db->rollbackTransaction();
            }
            return false;
        }

        $activeCandidateJobKey = self::questionnaireActiveCandidateJobKey($candidateID, $jobOrderID);
        $hasActiveCandidateJobKey = $this->isColumnInstalled('nesp_screening_questionnaire', 'active_candidate_job_key');

        // Expired links are terminal. Reapplications receive one fresh link;
        // all other active states reuse their existing questionnaire snapshot.
        if ($hasActiveCandidateJobKey)
        {
            $this->_db->query(sprintf(
                'UPDATE nesp_screening_questionnaire
                 SET status_key = "expired",
                     active_candidate_job_key = NULL,
                     date_modified = NOW()
                 WHERE active_candidate_job_key = %s
                   AND status_key IN ("link_ready", "waiting", "in_progress")
                   AND token_expires_at IS NOT NULL
                   AND token_expires_at < UTC_TIMESTAMP()',
                $this->_db->makeQueryString($activeCandidateJobKey)
            ));
        }

        $existing = $this->_db->getAssoc(sprintf(
            'SELECT screening_questionnaire_id
             FROM nesp_screening_questionnaire
             WHERE %s
             ORDER BY screening_questionnaire_id DESC
             LIMIT 1%s',
            $hasActiveCandidateJobKey
                ? 'active_candidate_job_key = ' . $this->_db->makeQueryString($activeCandidateJobKey)
                : 'candidate_id = ' . $this->_db->makeQueryInteger($candidateID)
                    . ' AND joborder_id = ' . $this->_db->makeQueryInteger($jobOrderID)
                    . ' AND status_key IN ("link_ready", "waiting", "in_progress", "human_follow_up_requested")',
            $transactionStarted ? ' FOR UPDATE' : ''
        ));
        if (!empty($existing))
        {
            if ($transactionStarted)
            {
                $this->_db->commitTransaction();
            }
            return array(
                'questionnaire_id' => (int) $existing['screening_questionnaire_id'],
                'one_time_invitation_copy' => '',
                'link_generated' => false
            );
        }

        $token = self::generateQuestionnaireToken();
        $tokenHash = self::questionnaireTokenHash($token);
        $link = self::getQuestionnaireLink($token);
        $invitation = self::buildQuestionnaireInvitationCopy($preview['first_name'], $preview['title'], $link);

        $columns = array('candidate_id', 'joborder_id', 'status_key', 'question_set_key', 'question_set_version');
        $values = array(
            $this->_db->makeQueryInteger($candidateID),
            $this->_db->makeQueryInteger($jobOrderID),
            '"link_ready"',
            $this->_db->makeQueryString($preview['question_set_key']),
            $this->_db->makeQueryInteger((int) $preview['question_set_version'])
        );
        if ($hasActiveCandidateJobKey)
        {
            $columns[] = 'active_candidate_job_key';
            $values[] = $this->_db->makeQueryString($activeCandidateJobKey);
        }
        if ($this->isColumnInstalled('nesp_screening_questionnaire', 'question_set_version_id'))
        {
            $columns[] = 'question_set_version_id';
            $values[] = ((int) $preview['question_set_version_id']) > 0 ? $this->_db->makeQueryInteger((int) $preview['question_set_version_id']) : 'NULL';
        }
        if ($this->isColumnInstalled('nesp_screening_questionnaire', 'question_snapshot_json'))
        {
            $columns[] = 'question_snapshot_json';
            $values[] = $this->_db->makeQueryString(json_encode(self::normalizeQuestionnaireSnapshotQuestions($preview['questions'])));
        }
        $columns = array_merge($columns, array('token_hash', 'token_expires_at', 'link_created_at', 'requested_by_user_id', 'review_status_key', 'date_created', 'date_modified'));
        $values = array_merge($values, array(
            $this->_db->makeQueryString($tokenHash),
            'DATE_ADD(UTC_TIMESTAMP(), INTERVAL ' . $this->_db->makeQueryInteger(self::getQuestionnaireDefaultExpirationHours()) . ' HOUR)',
            'UTC_TIMESTAMP()',
            $actorUserID === null ? 'NULL' : $this->_db->makeQueryInteger($actorUserID),
            '"not_started"',
            'NOW()',
            'NOW()'
        ));

        $this->_db->query(sprintf(
            'INSERT INTO nesp_screening_questionnaire
                (%s)
             VALUES
                (%s)
             ON DUPLICATE KEY UPDATE
                screening_questionnaire_id = LAST_INSERT_ID(screening_questionnaire_id),
                date_modified = date_modified',
            implode(', ', $columns),
            implode(', ', $values)
        ));

        $questionnaireID = (int) $this->_db->getLastInsertID();
        $created = !empty($this->_db->getAssoc(sprintf(
            'SELECT screening_questionnaire_id
             FROM nesp_screening_questionnaire
             WHERE screening_questionnaire_id = %s
               AND token_hash = %s
             LIMIT 1',
            $this->_db->makeQueryInteger($questionnaireID),
            $this->_db->makeQueryString($tokenHash)
        )));
        if (!$created && $hasActiveCandidateJobKey)
        {
            $existing = $this->_db->getAssoc(sprintf(
                'SELECT screening_questionnaire_id
                 FROM nesp_screening_questionnaire
                 WHERE active_candidate_job_key = %s
                 LIMIT 1',
                $this->_db->makeQueryString($activeCandidateJobKey)
            ));
            $questionnaireID = empty($existing) ? 0 : (int) $existing['screening_questionnaire_id'];
        }
        if ($questionnaireID <= 0)
        {
            if ($transactionStarted)
            {
                $this->_db->rollbackTransaction();
            }
            return false;
        }
        if ($transactionStarted)
        {
            $this->_db->commitTransaction();
        }
        if (!$created)
        {
            return array(
                'questionnaire_id' => $questionnaireID,
                'one_time_invitation_copy' => '',
                'link_generated' => false
            );
        }
        $this->logQuestionnaireActivity($questionnaireID, $tokenHash, 'link_created', array('expires_at_hours' => self::getQuestionnaireDefaultExpirationHours()));
        $this->logAuditEvent($actorUserID, 'screening_questionnaire_link_created', 'screening_questionnaire', $questionnaireID, array('candidate_id' => (int) $candidateID, 'joborder_id' => (int) $jobOrderID, 'question_set_key' => $preview['question_set_key'], 'question_set_version_id' => (int) $preview['question_set_version_id']));

        return array(
            'questionnaire_id' => $questionnaireID,
            'one_time_invitation_copy' => $invitation,
            'link_generated' => true
        );
    }

    public function getQuestionnaireSummaries($limit)
    {
        $limit = max(1, min(200, (int) $limit));
        $rows = $this->_db->getAllAssoc(
            sprintf(
                'SELECT
                    q.screening_questionnaire_id,
                    q.candidate_id,
                    q.joborder_id,
                    CONCAT(c.first_name, " ", c.last_name) AS candidate_name,
                    jo.title AS role_title,
                    q.status_key,
                    q.question_set_key,
                    q.token_expires_at,
                    q.token_revoked_at,
                    q.link_created_at,
                    q.invitation_copied_at,
                    q.started_at,
                    q.submitted_at,
                    q.review_status_key,
                    q.reviewer_profile_id,
                    ip.display_name AS reviewer_name,
                    q.date_created,
                    q.date_modified
                 FROM nesp_screening_questionnaire q
                 INNER JOIN candidate c ON c.candidate_id = q.candidate_id
                 INNER JOIN joborder jo ON jo.joborder_id = q.joborder_id
                 LEFT JOIN nesp_interviewer_profile ip ON ip.interviewer_profile_id = q.reviewer_profile_id
                 ORDER BY q.date_modified DESC, q.screening_questionnaire_id DESC
                 LIMIT %s',
                $this->_db->makeQueryInteger($limit)
            )
        );
        return $this->decorateQuestionnaireRows($rows);
    }

    public function getQuestionnaireQueues()
    {
        $rows = $this->getQuestionnaireSummaries(200);
        $queues = array(
            'ready' => array(),
            'waiting' => array(),
            'completed' => array(),
            'human_follow_up' => array(),
            'revoked_expired' => array()
        );
        foreach ($rows as $row)
        {
            if ($row['status_key'] === 'link_ready')
            {
                $queues['ready'][] = $row;
            }
            if ($row['status_key'] === 'waiting' || $row['status_key'] === 'in_progress')
            {
                $queues['waiting'][] = $row;
            }
            if ($row['status_key'] === 'completed')
            {
                $queues['completed'][] = $row;
            }
            if ($row['status_key'] === 'human_follow_up_requested')
            {
                $queues['human_follow_up'][] = $row;
            }
            if ($row['status_key'] === 'revoked' || $row['status_key'] === 'expired')
            {
                $queues['revoked_expired'][] = $row;
            }
        }
        return $queues;
    }

    public function getQuestionnaireDetail($questionnaireID, $viewerUserID = null)
    {
        $detail = $this->_db->getAssoc(
            sprintf(
                'SELECT
                    q.*,
                    c.first_name,
                    c.last_name,
                    CONCAT(c.first_name, " ", c.last_name) AS candidate_name,
                    c.email1,
                    jo.title AS role_title,
                    ip.display_name AS reviewer_name
                 FROM nesp_screening_questionnaire q
                 INNER JOIN candidate c ON c.candidate_id = q.candidate_id
                 INNER JOIN joborder jo ON jo.joborder_id = q.joborder_id
                 LEFT JOIN nesp_interviewer_profile ip ON ip.interviewer_profile_id = q.reviewer_profile_id
                 WHERE q.screening_questionnaire_id = %s
                 LIMIT 1',
                $this->_db->makeQueryInteger($questionnaireID)
            )
        );
        if (empty($detail))
        {
            return array();
        }

        if ($viewerUserID !== null && !$this->userCanReviewQuestionnaire($viewerUserID, $detail))
        {
            return array();
        }

        $detail = $this->decorateQuestionnaireRow($detail);
        $detail['questions'] = $this->questionnaireQuestionsForIssuedRow($detail);
        $answers = $this->_db->getAllAssoc(
            sprintf(
                'SELECT question_key, question_label, answer_text, sort_order
                 FROM nesp_screening_questionnaire_answer
                 WHERE screening_questionnaire_id = %s
                 ORDER BY sort_order ASC, questionnaire_answer_id ASC',
                $this->_db->makeQueryInteger($questionnaireID)
            )
        );
        $detail['answers'] = $answers;
        $detail['answer_map'] = array();
        foreach ($answers as $answer)
        {
            $detail['answer_map'][$answer['question_key']] = $answer['answer_text'];
        }
        return $detail;
    }

    public function markQuestionnaireInvitationCopied($questionnaireID, $actorUserID)
    {
        $this->_db->query(
            sprintf(
                'UPDATE nesp_screening_questionnaire
                 SET status_key = "waiting",
                     invitation_copied_at = UTC_TIMESTAMP(),
                     date_modified = NOW()
                 WHERE screening_questionnaire_id = %s
                   AND status_key = "link_ready"
                   AND token_revoked_at IS NULL',
                $this->_db->makeQueryInteger($questionnaireID)
            )
        );
        $this->logAuditEvent($actorUserID, 'screening_questionnaire_invitation_copied', 'screening_questionnaire', $questionnaireID, array());
        return $this->_db->getAffectedRows() === 1;
    }

    public function revokeQuestionnaireLink($questionnaireID, $actorUserID)
    {
        $this->_db->query(
            sprintf(
                'UPDATE nesp_screening_questionnaire
                 SET status_key = "revoked",
                     token_revoked_at = UTC_TIMESTAMP(),
                     active_candidate_job_key = NULL,
                     date_modified = NOW()
                 WHERE screening_questionnaire_id = %s
                   AND status_key IN ("link_ready", "waiting", "in_progress")',
                $this->_db->makeQueryInteger($questionnaireID)
            )
        );
        $this->logAuditEvent($actorUserID, 'screening_questionnaire_link_revoked', 'screening_questionnaire', $questionnaireID, array());
        return $this->_db->getAffectedRows() === 1;
    }

    public function regenerateQuestionnaireLink($questionnaireID, $actorUserID)
    {
        $detail = $this->getQuestionnaireDetail($questionnaireID);
        if (empty($detail) || $detail['status_key'] === 'completed')
        {
            return false;
        }

        $token = self::generateQuestionnaireToken();
        $tokenHash = self::questionnaireTokenHash($token);
        $link = self::getQuestionnaireLink($token);
        $invitation = self::buildQuestionnaireInvitationCopy($detail['first_name'], $detail['role_title'], $link);
        $this->_db->query(
            sprintf(
                'UPDATE nesp_screening_questionnaire
                 SET status_key = "link_ready",
                     token_hash = %s,
                     token_expires_at = DATE_ADD(UTC_TIMESTAMP(), INTERVAL %s HOUR),
                     token_revoked_at = NULL,
                     token_used_at = NULL,
                     link_created_at = UTC_TIMESTAMP(),
                     invitation_copied_at = NULL,
                     date_modified = NOW()
                 WHERE screening_questionnaire_id = %s
                   AND submitted_at IS NULL',
                $this->_db->makeQueryString($tokenHash),
                $this->_db->makeQueryInteger(self::getQuestionnaireDefaultExpirationHours()),
                $this->_db->makeQueryInteger($questionnaireID)
            )
        );
        $this->logQuestionnaireActivity($questionnaireID, $tokenHash, 'link_regenerated', array());
        $this->logAuditEvent($actorUserID, 'screening_questionnaire_link_regenerated', 'screening_questionnaire', $questionnaireID, array());
        if ($this->_db->getAffectedRows() !== 1)
        {
            return false;
        }

        return array(
            'questionnaire_id' => (int) $questionnaireID,
            'one_time_invitation_copy' => $invitation,
            'link_generated' => true
        );
    }

    public function getQuestionnairePageByToken($token)
    {
        $tokenHash = self::questionnaireTokenHash($token);
        $row = $this->_db->getAssoc(
            sprintf(
                'SELECT
                    q.*,
                    c.first_name,
                    CONCAT(c.first_name, " ", c.last_name) AS candidate_name,
                    jo.title AS role_title
                 FROM nesp_screening_questionnaire q
                 INNER JOIN candidate c ON c.candidate_id = q.candidate_id
                 INNER JOIN joborder jo ON jo.joborder_id = q.joborder_id
                 WHERE q.token_hash = %s
                 LIMIT 1',
                $this->_db->makeQueryString($tokenHash)
            )
        );

        $state = self::evaluateQuestionnaireTokenState($token, $row, time());
        if ($state !== 'valid')
        {
            $this->logQuestionnaireActivity(empty($row) ? null : $row['screening_questionnaire_id'], $tokenHash, 'token_' . $state, array());
            return array('ok' => false, 'state' => $state);
        }
        if ($this->isQuestionnaireRateLimited(empty($row) ? null : $row['screening_questionnaire_id'], $tokenHash))
        {
            $this->logQuestionnaireActivity($row['screening_questionnaire_id'], $tokenHash, 'rate_limited', array());
            return array('ok' => false, 'state' => 'rate_limited');
        }
        if (!in_array($row['status_key'], array('link_ready', 'waiting', 'in_progress')))
        {
            $this->logQuestionnaireActivity($row['screening_questionnaire_id'], $tokenHash, 'token_not_active', array('status_key' => $row['status_key']));
            return array('ok' => false, 'state' => 'not_active');
        }

        $this->_db->query(
            sprintf(
                'UPDATE nesp_screening_questionnaire
                 SET status_key = CASE WHEN status_key = "link_ready" OR status_key = "waiting" THEN "in_progress" ELSE status_key END,
                     started_at = CASE WHEN started_at IS NULL THEN UTC_TIMESTAMP() ELSE started_at END,
                     date_modified = NOW()
                 WHERE screening_questionnaire_id = %s
                   AND submitted_at IS NULL',
                $this->_db->makeQueryInteger($row['screening_questionnaire_id'])
            )
        );
        $this->logQuestionnaireActivity($row['screening_questionnaire_id'], $tokenHash, 'page_viewed', array());
        $row = $this->decorateQuestionnaireRow($row);
        $row['questions'] = $this->questionnaireQuestionsForIssuedRow($row);
        return array('ok' => true, 'state' => 'valid', 'questionnaire' => $row);
    }

    public function submitQuestionnaireFromToken($token, $answers)
    {
        $page = $this->getQuestionnairePageByToken($token);
        if (empty($page['ok']))
        {
            return $page;
        }

        $questionnaire = $page['questionnaire'];
        $tokenHash = self::questionnaireTokenHash($token);
        $questions = $this->questionnaireQuestionsForIssuedRow($questionnaire);
        $validation = self::validateQuestionnaireAnswers($questions, $answers);
        if (empty($validation['ok']))
        {
            $this->logQuestionnaireActivity($questionnaire['screening_questionnaire_id'], $tokenHash, 'validation_failed', array('missing_count' => count($validation['missing'])));
            return array('ok' => false, 'state' => 'validation_failed', 'missing' => $validation['missing']);
        }

        $transactionStarted = $this->_db->beginTransaction();

        $this->_db->query(
            sprintf(
                'UPDATE nesp_screening_questionnaire
                 SET status_key = "completed",
                     submitted_at = UTC_TIMESTAMP(),
                     token_used_at = UTC_TIMESTAMP(),
                     active_candidate_job_key = NULL,
                     date_modified = NOW()
                 WHERE screening_questionnaire_id = %s
                   AND token_hash = %s
                   AND token_revoked_at IS NULL
                   AND submitted_at IS NULL
                   AND status_key IN ("link_ready", "waiting", "in_progress")',
                $this->_db->makeQueryInteger($questionnaire['screening_questionnaire_id']),
                $this->_db->makeQueryString($tokenHash)
            )
        );
        if ($this->_db->getAffectedRows() !== 1)
        {
            if ($transactionStarted)
            {
                $this->_db->rollbackTransaction();
            }
            $this->logQuestionnaireActivity($questionnaire['screening_questionnaire_id'], $tokenHash, 'duplicate_submit_blocked', array());
            return array('ok' => false, 'state' => 'already_submitted');
        }

        $sortOrder = 10;
        foreach ($questions as $question)
        {
            $this->_db->query(
                sprintf(
                    'INSERT INTO nesp_screening_questionnaire_answer
                        (screening_questionnaire_id, question_key, question_label, answer_text, sort_order, date_created, date_modified)
                     VALUES
                        (%s, %s, %s, %s, %s, NOW(), NOW())',
                    $this->_db->makeQueryInteger($questionnaire['screening_questionnaire_id']),
                    $this->_db->makeQueryString($question['key']),
                    $this->_db->makeQueryString($question['label']),
                    $this->_db->makeQueryString($validation['answers'][$question['key']]),
                    $this->_db->makeQueryInteger($sortOrder)
                )
            );
            $sortOrder += 10;
        }

        $this->logQuestionnaireActivity($questionnaire['screening_questionnaire_id'], $tokenHash, 'submitted', array('answer_count' => count($questions)));
        $this->logAuditEvent(null, 'screening_questionnaire_submitted', 'screening_questionnaire', $questionnaire['screening_questionnaire_id'], array('question_set_key' => $questionnaire['question_set_key']));
        if ($transactionStarted)
        {
            $this->_db->commitTransaction();
        }
        return array('ok' => true, 'state' => 'completed');
    }

    public function requestQuestionnaireHumanFollowUpFromToken($token)
    {
        $page = $this->getQuestionnairePageByToken($token);
        if (empty($page['ok']))
        {
            return $page;
        }
        $questionnaire = $page['questionnaire'];
        $tokenHash = self::questionnaireTokenHash($token);
        $this->_db->query(
            sprintf(
                'UPDATE nesp_screening_questionnaire
                 SET status_key = "human_follow_up_requested",
                     human_follow_up_requested_at = UTC_TIMESTAMP(),
                     token_revoked_at = UTC_TIMESTAMP(),
                     date_modified = NOW()
                 WHERE screening_questionnaire_id = %s
                   AND token_hash = %s
                   AND submitted_at IS NULL',
                $this->_db->makeQueryInteger($questionnaire['screening_questionnaire_id']),
                $this->_db->makeQueryString($tokenHash)
            )
        );
        if ($this->_db->getAffectedRows() !== 1)
        {
            $this->logQuestionnaireActivity($questionnaire['screening_questionnaire_id'], $tokenHash, 'human_follow_up_not_updated', array());
            return array('ok' => false, 'state' => 'unavailable');
        }

        $this->logQuestionnaireActivity($questionnaire['screening_questionnaire_id'], $tokenHash, 'human_follow_up_requested', array());
        return array('ok' => true, 'state' => 'human_follow_up_requested');
    }

    public function assignQuestionnaireReviewer($questionnaireID, $interviewerProfileID, $actorUserID)
    {
        $interviewerProfileID = (int) $interviewerProfileID;
        if ($interviewerProfileID <= 0)
        {
            return false;
        }
        $detail = $this->getQuestionnaireDetail($questionnaireID);
        if (empty($detail))
        {
            return false;
        }
        if ($this->createCandidateGrant($interviewerProfileID, $detail['candidate_id'], $detail['joborder_id'], $actorUserID) === false)
        {
            return false;
        }
        $this->_db->query(
            sprintf(
                'UPDATE nesp_screening_questionnaire
                 SET reviewer_profile_id = %s,
                     review_status_key = "assigned",
                     date_modified = NOW()
                 WHERE screening_questionnaire_id = %s',
                $this->_db->makeQueryInteger($interviewerProfileID),
                $this->_db->makeQueryInteger($questionnaireID)
            )
        );
        $this->logAuditEvent($actorUserID, 'screening_questionnaire_reviewer_assigned', 'screening_questionnaire', $questionnaireID, array('interviewer_profile_id' => $interviewerProfileID));
        return $this->_db->getAffectedRows() === 1;
    }

    public function saveQuestionnaireReview($questionnaireID, $actorUserID, $reviewNote, $markComplete)
    {
        $detail = $this->getQuestionnaireDetail($questionnaireID, $actorUserID);
        if (empty($detail))
        {
            return false;
        }
        $reviewNote = trim($reviewNote);
        $statusSQL = $markComplete ? '"complete"' : '"in_review"';
        $completedBySQL = $markComplete ? $this->_db->makeQueryInteger($actorUserID) : 'review_completed_by_user_id';
        $completedAtSQL = $markComplete ? 'UTC_TIMESTAMP()' : 'review_completed_at';
        $this->_db->query(
            sprintf(
                'UPDATE nesp_screening_questionnaire
                 SET review_notes = %s,
                     review_status_key = %s,
                     review_completed_by_user_id = %s,
                     review_completed_at = %s,
                     date_modified = NOW()
                 WHERE screening_questionnaire_id = %s',
                $this->_db->makeQueryString($reviewNote),
                $statusSQL,
                $completedBySQL,
                $completedAtSQL,
                $this->_db->makeQueryInteger($questionnaireID)
            )
        );
        $this->logAuditEvent($actorUserID, $markComplete ? 'screening_questionnaire_review_completed' : 'screening_questionnaire_review_saved', 'screening_questionnaire', $questionnaireID, array('review_note_length' => strlen($reviewNote)));
        return true;
    }

    public function getPhoneScreenStatusLabels()
    {
        return NESPVapiIntegration::getPhoneScreenStatusLabels();
    }

    public function getPhoneScreenAvailabilitySettings()
    {
        $settings = NESPVapiIntegration::getDefaultPhoneScreenAvailabilitySettings();
        $rows = $this->_db->getAllAssoc(
            'SELECT setting_key, setting_value
             FROM nesp_vapi_phone_screen_setting'
        );
        foreach ($rows as $row)
        {
            if (array_key_exists($row['setting_key'], $settings))
            {
                $settings[$row['setting_key']] = $row['setting_value'];
            }
        }

        foreach (array('slot_minutes', 'call_duration_minutes', 'buffer_minutes', 'min_booking_notice_minutes', 'link_expiration_hours', 'max_screens_per_hour', 'max_screens_per_day', 'booking_horizon_days') as $key)
        {
            $settings[$key] = max(1, (int) $settings[$key]);
        }
        if (!self::isValidAvailabilityTime($settings['earliest_call_time']))
        {
            $settings['earliest_call_time'] = '09:00';
        }
        if (!self::isValidAvailabilityTime($settings['latest_call_time']))
        {
            $settings['latest_call_time'] = '18:00';
        }
        if (!in_array($settings['timezone'], timezone_identifiers_list()))
        {
            $settings['timezone'] = NESPVapiIntegration::DEFAULT_SCHEDULING_TIMEZONE;
        }

        return $settings;
    }

    public function getPhoneScreenAvailabilityBlocks()
    {
        return $this->_db->getAllAssoc(
            'SELECT availability_block_id, weekday, TIME_FORMAT(start_time, "%H:%i") AS start_time,
                    TIME_FORMAT(end_time, "%H:%i") AS end_time, is_available
             FROM nesp_vapi_availability_block
             ORDER BY weekday, start_time'
        );
    }

    public function getPhoneScreenBlackoutDates()
    {
        return $this->_db->getAllAssoc(
            'SELECT blackout_date_id, blackout_date, label
             FROM nesp_vapi_blackout_date
             ORDER BY blackout_date'
        );
    }

    public function savePhoneScreenAvailabilitySettings($input, $actorUserID)
    {
        $defaults = NESPVapiIntegration::getDefaultPhoneScreenAvailabilitySettings();
        $settings = array();
        foreach ($defaults as $key => $defaultValue)
        {
            $value = isset($input[$key]) ? trim((string) $input[$key]) : (string) $defaultValue;
            if (in_array($key, array('slot_minutes', 'call_duration_minutes', 'buffer_minutes', 'min_booking_notice_minutes', 'link_expiration_hours', 'max_screens_per_hour', 'max_screens_per_day', 'booking_horizon_days')))
            {
                $value = (string) max(1, (int) $value);
            }
            if (in_array($key, array('earliest_call_time', 'latest_call_time')) && !self::isValidAvailabilityTime($value))
            {
                $value = (string) $defaultValue;
            }
            if ($key === 'timezone' && !in_array($value, timezone_identifiers_list()))
            {
                $value = NESPVapiIntegration::DEFAULT_SCHEDULING_TIMEZONE;
            }
            $settings[$key] = $value;
            $this->_db->query(
                sprintf(
                    'INSERT INTO nesp_vapi_phone_screen_setting
                        (setting_key, setting_value, date_created, date_modified)
                     VALUES
                        (%s, %s, NOW(), NOW())
                     ON DUPLICATE KEY UPDATE
                        setting_value = VALUES(setting_value),
                        date_modified = NOW()',
                    $this->_db->makeQueryString($key),
                    $this->_db->makeQueryString($value)
                )
            );
        }

        $this->logAuditEvent($actorUserID, 'vapi_phone_screen_availability_settings_saved', 'vapi_phone_screen_setting', null, array('settings' => $settings));
        return true;
    }

    public function createPhoneScreenAvailabilityBlock($weekday, $startTime, $endTime, $actorUserID)
    {
        $weekday = (int) $weekday;
        $startTime = trim($startTime);
        $endTime = trim($endTime);
        if ($weekday < 0 || $weekday > 6 || !self::isValidAvailabilityTime($startTime) || !self::isValidAvailabilityTime($endTime) || $startTime >= $endTime)
        {
            return false;
        }

        $this->_db->query(
            sprintf(
                'INSERT INTO nesp_vapi_availability_block
                    (weekday, start_time, end_time, is_available, date_created, date_modified)
                 VALUES
                    (%s, %s, %s, 1, NOW(), NOW())',
                $this->_db->makeQueryInteger($weekday),
                $this->_db->makeQueryString($startTime . ':00'),
                $this->_db->makeQueryString($endTime . ':00')
            )
        );
        $blockID = $this->_db->getLastInsertID();
        $this->logAuditEvent($actorUserID, 'vapi_phone_screen_availability_block_created', 'vapi_availability_block', $blockID, array('weekday' => $weekday, 'start_time' => $startTime, 'end_time' => $endTime));
        return true;
    }

    public function deletePhoneScreenAvailabilityBlock($availabilityBlockID, $actorUserID)
    {
        $this->_db->query(
            sprintf(
                'DELETE FROM nesp_vapi_availability_block
                 WHERE availability_block_id = %s',
                $this->_db->makeQueryInteger($availabilityBlockID)
            )
        );
        $this->logAuditEvent($actorUserID, 'vapi_phone_screen_availability_block_deleted', 'vapi_availability_block', $availabilityBlockID, array());
        return true;
    }

    public function createPhoneScreenBlackout($blackoutDate, $label, $actorUserID)
    {
        $blackoutDate = trim($blackoutDate);
        $label = trim($label);
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $blackoutDate))
        {
            return false;
        }

        $this->_db->query(
            sprintf(
                'INSERT INTO nesp_vapi_blackout_date
                    (blackout_date, label, date_created, date_modified)
                 VALUES
                    (%s, %s, NOW(), NOW())
                 ON DUPLICATE KEY UPDATE
                    label = VALUES(label),
                    date_modified = NOW()',
                $this->_db->makeQueryString($blackoutDate),
                $this->_db->makeQueryString($label)
            )
        );
        $this->logAuditEvent($actorUserID, 'vapi_phone_screen_blackout_saved', 'vapi_blackout_date', null, array('blackout_date' => $blackoutDate));
        return true;
    }

    public function deletePhoneScreenBlackout($blackoutDateID, $actorUserID)
    {
        $this->_db->query(
            sprintf(
                'DELETE FROM nesp_vapi_blackout_date
                 WHERE blackout_date_id = %s',
                $this->_db->makeQueryInteger($blackoutDateID)
            )
        );
        $this->logAuditEvent($actorUserID, 'vapi_phone_screen_blackout_deleted', 'vapi_blackout_date', $blackoutDateID, array());
        return true;
    }

    public function getVapiPhoneScreenSummaries($limit)
    {
        $limit = max(1, min(200, (int) $limit));

        $rows = $this->_db->getAllAssoc(
            sprintf(
                'SELECT
                    ps.vapi_phone_screen_id,
                    ps.call_request_key,
                    ps.candidate_id,
                    ps.joborder_id,
                    CONCAT(c.first_name, " ", c.last_name) AS candidate_name,
                    jo.title AS role_title,
                    ps.status_key,
                    ps.consent_status,
                    ps.destination_phone_last4,
                    ps.provider_call_id,
                    ps.provider_end_reason,
                    ps.scheduling_link_created_at,
                    ps.scheduling_invitation_copied_at,
                    ps.scheduling_token_expires_at,
                    ps.scheduling_token_revoked_at,
                    ps.scheduled_start_et,
                    ps.scheduled_start_at_utc,
                    ps.scheduled_timezone,
                    ps.call_attempt_count,
                    ps.requested_by_user_id,
                    ps.approved_by_user_id,
                    ps.date_created,
                    ps.date_modified
                 FROM
                    nesp_vapi_phone_screen ps
                 INNER JOIN candidate c
                    ON c.candidate_id = ps.candidate_id
                 INNER JOIN joborder jo
                    ON jo.joborder_id = ps.joborder_id
                 ORDER BY
                    ps.date_modified DESC,
                    ps.vapi_phone_screen_id DESC
                 LIMIT %s',
                $this->_db->makeQueryInteger($limit)
            )
        );

        return $this->decoratePhoneScreenRows($rows);
    }

    public function getVapiPhoneScreenQueues()
    {
        $rows = $this->getVapiPhoneScreenSummaries(200);
        $queues = array(
            'ready' => array(),
            'waiting' => array(),
            'today' => array(),
            'upcoming' => array(),
            'reschedule' => array(),
            'completed' => array()
        );
        $today = new DateTime('now', new DateTimeZone(NESPVapiIntegration::DEFAULT_SCHEDULING_TIMEZONE));
        $todayKey = $today->format('Y-m-d');

        foreach ($rows as $row)
        {
            if ($row['status_key'] === 'scheduling_link_ready')
            {
                $queues['ready'][] = $row;
            }
            if ($row['status_key'] === 'waiting_for_candidate_to_schedule')
            {
                $queues['waiting'][] = $row;
            }
            if ($row['status_key'] === 'phone_screen_scheduled' && substr($row['scheduled_start_et'], 0, 10) === $todayKey)
            {
                $queues['today'][] = $row;
            }
            if (in_array($row['status_key'], array('phone_screen_scheduled', 'call_due', 'call_started')) && substr($row['scheduled_start_et'], 0, 10) > $todayKey)
            {
                $queues['upcoming'][] = $row;
            }
            if (in_array($row['status_key'], array('no_answer', 'reschedule_requested', 'cancelled')))
            {
                $queues['reschedule'][] = $row;
            }
            if ($row['status_key'] === 'completed')
            {
                $queues['completed'][] = $row;
            }
        }

        return $queues;
    }

    public function getVapiPhoneScreenDetail($phoneScreenID)
    {
        $detail = $this->_db->getAssoc(
            sprintf(
                'SELECT
                    ps.*,
                    CONCAT(c.first_name, " ", c.last_name) AS candidate_name,
                    c.email1,
                    jo.title AS role_title
                 FROM
                    nesp_vapi_phone_screen ps
                 INNER JOIN candidate c
                    ON c.candidate_id = ps.candidate_id
                 INNER JOIN joborder jo
                    ON jo.joborder_id = ps.joborder_id
                 WHERE
                    ps.vapi_phone_screen_id = %s
                 LIMIT 1',
                $this->_db->makeQueryInteger($phoneScreenID)
            )
        );
        if (empty($detail))
        {
            return array();
        }

        $detail['role_script'] = NESPVapiIntegration::getRoleScript($detail['joborder_id'], $detail['role_title']);
        $detail = $this->decoratePhoneScreenRow($detail);
        $detail['webhook_events'] = $this->_db->getAllAssoc(
            sprintf(
                'SELECT
                    provider_event_id,
                    provider_call_id,
                    event_type,
                    event_timestamp,
                    processed_at,
                    date_created
                 FROM
                    nesp_vapi_webhook_event
                 WHERE
                    provider_call_id = %s
                 ORDER BY
                    date_created DESC,
                    vapi_webhook_event_id DESC
                 LIMIT 25',
                $this->_db->makeQueryString($detail['provider_call_id'])
            )
        );

        $structured = json_decode($detail['structured_result_json'], true);
        $detail['structured_result'] = is_array($structured) ? $structured : array();
        return $detail;
    }

    public function getCandidatePhoneScreenPreview($candidateID, $jobOrderID)
    {
        $candidateID = (int) $candidateID;
        $jobOrderID = (int) $jobOrderID;
        if ($candidateID <= 0 || $jobOrderID <= 0)
        {
            return array();
        }

        $row = $this->_db->getAssoc(
            sprintf(
                'SELECT
                    c.candidate_id,
                    c.first_name,
                    c.last_name,
                    c.phone_cell,
                    c.phone_home,
                    c.phone_work,
                    jo.joborder_id,
                    jo.title
                 FROM
                    candidate c
                 INNER JOIN candidate_joborder cjo
                    ON cjo.candidate_id = c.candidate_id
                 INNER JOIN joborder jo
                    ON jo.joborder_id = cjo.joborder_id
                 WHERE
                    c.candidate_id = %s
                    AND jo.joborder_id = %s
                    AND c.is_active = 1
                 LIMIT 1',
                $this->_db->makeQueryInteger($candidateID),
                $this->_db->makeQueryInteger($jobOrderID)
            )
        );

        if (empty($row))
        {
            return array();
        }

        $destinationPhone = trim($row['phone_cell']) !== '' ? $row['phone_cell'] : (trim($row['phone_home']) !== '' ? $row['phone_home'] : $row['phone_work']);
        $row['candidate_name'] = trim($row['first_name'] . ' ' . $row['last_name']);
        $row['destination_phone_redacted'] = NESPVapiIntegration::redactPhone($destinationPhone);
        $row['has_destination_phone'] = NESPVapiIntegration::normalizePhoneForDial($destinationPhone) !== '';
        $row['role_script'] = NESPVapiIntegration::getRoleScript($jobOrderID, $row['title']);
        $row['consent_notice'] = NESPVapiIntegration::getConsentOpeningScript();
        $row['configuration_status'] = $this->getVapiConfigurationStatus();
        return $row;
    }

    public function requestPhoneScreen($candidateID, $jobOrderID, $actorUserID)
    {
        $preview = $this->getCandidatePhoneScreenPreview($candidateID, $jobOrderID);
        if (empty($preview) || !$preview['has_destination_phone'])
        {
            return false;
        }

        $destinationPhone = trim($preview['phone_cell']) !== '' ? $preview['phone_cell'] : (trim($preview['phone_home']) !== '' ? $preview['phone_home'] : $preview['phone_work']);
        $callRequestKey = hash('sha256', (int) $candidateID . '|' . (int) $jobOrderID . '|' . NESPVapiIntegration::phoneHash($destinationPhone));
        $existing = $this->_db->getAssoc(
            sprintf(
                'SELECT vapi_phone_screen_id
                 FROM nesp_vapi_phone_screen
                 WHERE call_request_key = %s
                   AND status_key IN ("scheduling_link_ready", "waiting_for_candidate_to_schedule", "phone_screen_scheduled", "reschedule_requested", "call_due", "call_started", "ringing", "in_progress")
                 LIMIT 1',
                $this->_db->makeQueryString($callRequestKey)
            )
        );
        if (!empty($existing))
        {
            return (int) $existing['vapi_phone_screen_id'];
        }

        $token = NESPVapiIntegration::generateSchedulingToken();
        $tokenHash = NESPVapiIntegration::schedulingTokenHash($token);
        $settings = $this->getPhoneScreenAvailabilitySettings();
        $link = NESPVapiIntegration::getSchedulingLink($token);
        $invitation = NESPVapiIntegration::buildSchedulingInvitationCopy($preview['first_name'], $preview['title'], $link);
        $expiresSQL = sprintf(
            'DATE_ADD(UTC_TIMESTAMP(), INTERVAL %s HOUR)',
            $this->_db->makeQueryInteger($settings['link_expiration_hours'])
        );

        $sql = sprintf(
            'INSERT INTO nesp_vapi_phone_screen
                (call_request_key, candidate_id, joborder_id, destination_phone_hash, destination_phone_last4, status_key, consent_status, requested_by_user_id, caller_label, assistant_label, scheduling_token_hash, scheduling_token_expires_at, scheduling_link_created_at, scheduling_link_url, invitation_copy_text, scheduled_timezone, date_created, date_modified)
             VALUES
                (%s, %s, %s, %s, %s, "scheduling_link_ready", "not_requested", %s, "NESP Hiring", "NESP Hiring Phone Screen", %s, %s, UTC_TIMESTAMP(), %s, %s, %s, NOW(), NOW())',
            $this->_db->makeQueryString($callRequestKey),
            $this->_db->makeQueryInteger($candidateID),
            $this->_db->makeQueryInteger($jobOrderID),
            $this->_db->makeQueryString(NESPVapiIntegration::phoneHash($destinationPhone)),
            $this->_db->makeQueryString(NESPVapiIntegration::phoneLast4($destinationPhone)),
            $actorUserID === null ? 'NULL' : $this->_db->makeQueryInteger($actorUserID),
            $this->_db->makeQueryString($tokenHash),
            $expiresSQL,
            $this->_db->makeQueryString($link),
            $this->_db->makeQueryString($invitation),
            $this->_db->makeQueryString($settings['timezone'])
        );
        $this->_db->query($sql);
        $phoneScreenID = $this->_db->getLastInsertID();
        $this->logSchedulingActivity($phoneScreenID, $tokenHash, 'link_created', array('expires_at_hours' => (int) $settings['link_expiration_hours']));
        $this->logAuditEvent(
            $actorUserID,
            'vapi_phone_screen_scheduling_link_created',
            'vapi_phone_screen',
            $phoneScreenID,
            array('candidate_id' => (int) $candidateID, 'joborder_id' => (int) $jobOrderID, 'destination_phone' => NESPVapiIntegration::redactPhone($destinationPhone))
        );

        return (int) $phoneScreenID;
    }

    public function markPhoneScreenInvitationCopied($phoneScreenID, $actorUserID)
    {
        $this->_db->query(
            sprintf(
                'UPDATE nesp_vapi_phone_screen
                 SET status_key = "waiting_for_candidate_to_schedule",
                     scheduling_invitation_copied_at = UTC_TIMESTAMP(),
                     date_modified = NOW()
                 WHERE vapi_phone_screen_id = %s
                   AND status_key = "scheduling_link_ready"
                   AND scheduling_token_revoked_at IS NULL',
                $this->_db->makeQueryInteger($phoneScreenID)
            )
        );
        $this->logAuditEvent($actorUserID, 'vapi_phone_screen_invitation_copied', 'vapi_phone_screen', $phoneScreenID, array());
        return $this->_db->getAffectedRows() === 1;
    }

    public function revokePhoneScreenSchedulingLink($phoneScreenID, $actorUserID)
    {
        $this->_db->query(
            sprintf(
                'UPDATE nesp_vapi_phone_screen
                 SET status_key = "cancelled",
                     scheduling_token_revoked_at = UTC_TIMESTAMP(),
                     scheduled_start_at_utc = NULL,
                     scheduled_end_at_utc = NULL,
                     scheduled_start_et = NULL,
                     cancelled_at = NOW(),
                     date_modified = NOW()
                 WHERE vapi_phone_screen_id = %s
                   AND status_key IN ("scheduling_link_ready", "waiting_for_candidate_to_schedule", "phone_screen_scheduled", "reschedule_requested")',
                $this->_db->makeQueryInteger($phoneScreenID)
            )
        );
        $this->logAuditEvent($actorUserID, 'vapi_phone_screen_scheduling_link_revoked', 'vapi_phone_screen', $phoneScreenID, array());
        return $this->_db->getAffectedRows() === 1;
    }

    public function allowPhoneScreenReschedule($phoneScreenID, $actorUserID)
    {
        $detail = $this->getVapiPhoneScreenDetail($phoneScreenID);
        if (empty($detail) || !in_array($detail['status_key'], array('no_answer', 'cancelled', 'reschedule_requested')))
        {
            return false;
        }

        $token = NESPVapiIntegration::generateSchedulingToken();
        $tokenHash = NESPVapiIntegration::schedulingTokenHash($token);
        $settings = $this->getPhoneScreenAvailabilitySettings();
        $link = NESPVapiIntegration::getSchedulingLink($token);
        $firstName = strtok($detail['candidate_name'], ' ');
        $invitation = NESPVapiIntegration::buildSchedulingInvitationCopy($firstName, $detail['role_title'], $link);
        $this->_db->query(
            sprintf(
                'UPDATE nesp_vapi_phone_screen
                 SET status_key = "reschedule_requested",
                     scheduling_token_hash = %s,
                     scheduling_token_expires_at = DATE_ADD(UTC_TIMESTAMP(), INTERVAL %s HOUR),
                     scheduling_token_revoked_at = NULL,
                     scheduling_link_created_at = UTC_TIMESTAMP(),
                     scheduling_link_url = %s,
                     invitation_copy_text = %s,
                     scheduled_start_at_utc = NULL,
                     scheduled_end_at_utc = NULL,
                     scheduled_start_et = NULL,
                     provider_call_id = NULL,
                     provider_end_reason = "",
                     call_claimed_at = NULL,
                     call_attempted_at = NULL,
                     call_attempt_count = 0,
                     scheduler_claim_key = "",
                     last_scheduler_error = "",
                     date_modified = NOW()
                 WHERE vapi_phone_screen_id = %s',
                $this->_db->makeQueryString($tokenHash),
                $this->_db->makeQueryInteger($settings['link_expiration_hours']),
                $this->_db->makeQueryString($link),
                $this->_db->makeQueryString($invitation),
                $this->_db->makeQueryInteger($phoneScreenID)
            )
        );
        $this->logSchedulingActivity($phoneScreenID, $tokenHash, 'reschedule_link_created', array());
        $this->logAuditEvent($actorUserID, 'vapi_phone_screen_reschedule_allowed', 'vapi_phone_screen', $phoneScreenID, array());
        return true;
    }

    public function cancelPhoneScreen($phoneScreenID, $actorUserID)
    {
        $sql = sprintf(
            'UPDATE nesp_vapi_phone_screen
             SET status_key = "cancelled",
                 scheduling_token_revoked_at = UTC_TIMESTAMP(),
                 scheduled_start_at_utc = NULL,
                 scheduled_end_at_utc = NULL,
                 scheduled_start_et = NULL,
                 cancelled_at = NOW(),
                 date_modified = NOW()
             WHERE vapi_phone_screen_id = %s
               AND status_key IN ("scheduling_link_ready", "waiting_for_candidate_to_schedule", "phone_screen_scheduled", "call_due", "call_started", "ringing")',
            $this->_db->makeQueryInteger($phoneScreenID)
        );
        $this->_db->query($sql);
        $this->logAuditEvent($actorUserID, 'vapi_phone_screen_cancelled', 'vapi_phone_screen', $phoneScreenID, array());
        return true;
    }

    public function getSchedulingPageByToken($token)
    {
        $tokenHash = NESPVapiIntegration::schedulingTokenHash($token);
        $row = $this->_db->getAssoc(
            sprintf(
                'SELECT
                    ps.*,
                    c.first_name,
                    CONCAT(c.first_name, " ", c.last_name) AS candidate_name,
                    jo.title AS role_title
                 FROM nesp_vapi_phone_screen ps
                 INNER JOIN candidate c ON c.candidate_id = ps.candidate_id
                 INNER JOIN joborder jo ON jo.joborder_id = ps.joborder_id
                 WHERE ps.scheduling_token_hash = %s
                 LIMIT 1',
                $this->_db->makeQueryString($tokenHash)
            )
        );
        $state = NESPVapiIntegration::evaluateSchedulingTokenState($token, $row, time());
        if ($state !== 'valid')
        {
            $this->logSchedulingActivity(null, $tokenHash, 'token_' . $state, array());
            return array('ok' => false, 'state' => $state);
        }
        if ($this->isSchedulingRateLimited($row['vapi_phone_screen_id'], $tokenHash))
        {
            $this->logSchedulingActivity($row['vapi_phone_screen_id'], $tokenHash, 'rate_limited', array());
            return array('ok' => false, 'state' => 'rate_limited');
        }
        if (!in_array($row['status_key'], array('scheduling_link_ready', 'waiting_for_candidate_to_schedule', 'phone_screen_scheduled', 'reschedule_requested')))
        {
            $this->logSchedulingActivity($row['vapi_phone_screen_id'], $tokenHash, 'token_not_schedulable', array('status_key' => $row['status_key']));
            return array('ok' => false, 'state' => 'not_schedulable');
        }

        $this->logSchedulingActivity($row['vapi_phone_screen_id'], $tokenHash, 'page_viewed', array());
        $row = $this->decoratePhoneScreenRow($row);
        $row['available_slots'] = $this->getAvailablePhoneScreenSlots($row['vapi_phone_screen_id']);
        return array('ok' => true, 'state' => 'valid', 'screen' => $row);
    }

    public function schedulePhoneScreenFromToken($token, $slotStartUTC)
    {
        $page = $this->getSchedulingPageByToken($token);
        if (empty($page['ok']))
        {
            return $page;
        }

        $screen = $page['screen'];
        $tokenHash = NESPVapiIntegration::schedulingTokenHash($token);
        if (!in_array($screen['status_key'], array('scheduling_link_ready', 'waiting_for_candidate_to_schedule', 'phone_screen_scheduled', 'reschedule_requested')))
        {
            return array('ok' => false, 'state' => 'not_schedulable');
        }
        if (!NESPVapiIntegration::slotValueIsInAvailableSlots($slotStartUTC, $screen['available_slots']))
        {
            $this->logSchedulingActivity($screen['vapi_phone_screen_id'], $tokenHash, 'unoffered_slot_rejected', array('slot_start_utc' => $slotStartUTC));
            return array('ok' => false, 'state' => 'slot_unavailable');
        }
        if (!$this->isSlotAvailable($slotStartUTC, $screen['vapi_phone_screen_id']))
        {
            $this->logSchedulingActivity($screen['vapi_phone_screen_id'], $tokenHash, 'duplicate_booking_blocked', array('slot_start_utc' => $slotStartUTC));
            return array('ok' => false, 'state' => 'slot_unavailable');
        }

        $settings = $this->getPhoneScreenAvailabilitySettings();
        $tz = new DateTimeZone($settings['timezone']);
        $startUTC = new DateTime($slotStartUTC, new DateTimeZone('UTC'));
        $endUTC = clone $startUTC;
        $endUTC->modify('+' . (int) $settings['call_duration_minutes'] . ' minutes');
        $startLocal = clone $startUTC;
        $startLocal->setTimezone($tz);
        $status = $screen['status_key'] === 'phone_screen_scheduled' ? 'phone_screen_scheduled' : 'phone_screen_scheduled';
        $rescheduleIncrement = $screen['status_key'] === 'phone_screen_scheduled' ? ', reschedule_count = reschedule_count + 1' : '';

        $this->_db->query(
            sprintf(
                'UPDATE nesp_vapi_phone_screen
                 SET status_key = %s,
                     scheduled_start_at_utc = %s,
                     scheduled_end_at_utc = %s,
                     scheduled_start_et = %s,
                     scheduled_timezone = %s,
                     scheduling_token_used_at = UTC_TIMESTAMP(),
                     date_modified = NOW()
                     %s
                 WHERE vapi_phone_screen_id = %s
                   AND scheduling_token_hash = %s
                   AND scheduling_token_revoked_at IS NULL',
                $this->_db->makeQueryString($status),
                $this->_db->makeQueryString($startUTC->format('Y-m-d H:i:s')),
                $this->_db->makeQueryString($endUTC->format('Y-m-d H:i:s')),
                $this->_db->makeQueryString($startLocal->format('Y-m-d H:i:s')),
                $this->_db->makeQueryString($settings['timezone']),
                $rescheduleIncrement,
                $this->_db->makeQueryInteger($screen['vapi_phone_screen_id']),
                $this->_db->makeQueryString($tokenHash)
            )
        );
        if ($this->_db->getAffectedRows() !== 1)
        {
            $this->logSchedulingActivity($screen['vapi_phone_screen_id'], $tokenHash, 'appointment_schedule_lost_race', array('slot_start_utc' => $startUTC->format('Y-m-d H:i:s')));
            return array('ok' => false, 'state' => 'slot_unavailable');
        }
        $this->logSchedulingActivity($screen['vapi_phone_screen_id'], $tokenHash, 'appointment_scheduled', array('slot_start_utc' => $startUTC->format('Y-m-d H:i:s'), 'timezone' => $settings['timezone']));
        $this->logAuditEvent(null, 'vapi_phone_screen_candidate_scheduled', 'vapi_phone_screen', $screen['vapi_phone_screen_id'], array('scheduled_start_et' => $startLocal->format('Y-m-d H:i:s'), 'timezone' => $settings['timezone']));
        return array('ok' => true, 'state' => 'scheduled');
    }

    public function cancelPhoneScreenFromToken($token)
    {
        return $this->candidateSchedulingStatusChange($token, 'cancelled', 'candidate_cancelled');
    }

    public function requestHumanFollowUpFromToken($token)
    {
        return $this->candidateSchedulingStatusChange($token, 'human_follow_up_requested', 'human_follow_up_requested');
    }

    public function startPhoneScreenCall($phoneScreenID, $actorUserID)
    {
        if (!NESPVapiIntegration::isReadyForOutboundCalls($this->isFeatureFlagEnabled('NESP_VAPI_ENABLED')))
        {
            $this->logAuditEvent($actorUserID, 'vapi_phone_screen_start_blocked', 'vapi_phone_screen', $phoneScreenID, array('reason' => 'configuration_or_feature_flag_not_ready'));
            return array('ok' => false, 'error' => 'vapi_not_ready');
        }

        $screen = $this->getVapiPhoneScreenDetail($phoneScreenID);
        if (empty($screen) || !in_array($screen['status_key'], array('call_due', 'phone_screen_scheduled')))
        {
            return array('ok' => false, 'error' => 'invalid_screen_status');
        }
        if (!$this->claimPhoneScreenForStart($phoneScreenID))
        {
            $this->logAuditEvent($actorUserID, 'vapi_phone_screen_duplicate_start_blocked', 'vapi_phone_screen', $phoneScreenID, array());
            return array('ok' => false, 'error' => 'duplicate_start_blocked');
        }

        $candidate = array('candidate_id' => (int) $screen['candidate_id']);
        $job = array('joborder_id' => (int) $screen['joborder_id'], 'title' => $screen['role_title']);
        $destinationPhone = $this->getDestinationPhoneForScreen($screen['candidate_id']);
        $payload = NESPVapiIntegration::buildOutboundCallPayload($destinationPhone, $candidate, $job, $screen['call_request_key']);
        $response = $this->postVapiCall($payload);
        if (!$response['ok'])
        {
            $this->updatePhoneScreenStatus($phoneScreenID, 'provider_error', '', $response['error']);
            $this->logAuditEvent($actorUserID, 'vapi_phone_screen_provider_error', 'vapi_phone_screen', $phoneScreenID, array('error' => $response['error']));
            return $response;
        }

        $providerCallID = isset($response['body']['id']) ? (string) $response['body']['id'] : '';
        if ($providerCallID === '')
        {
            $this->updatePhoneScreenStatus($phoneScreenID, 'provider_error', '', 'provider_missing_call_id');
            $this->logAuditEvent($actorUserID, 'vapi_phone_screen_provider_error', 'vapi_phone_screen', $phoneScreenID, array('error' => 'provider_missing_call_id'));
            return array('ok' => false, 'error' => 'provider_missing_call_id');
        }
        $this->updatePhoneScreenStatus($phoneScreenID, 'call_started', $providerCallID, '');
        $this->logAuditEvent($actorUserID, 'vapi_phone_screen_call_started', 'vapi_phone_screen', $phoneScreenID, array('provider_call_id_present' => $providerCallID !== ''));
        return array('ok' => true, 'provider_call_id' => $providerCallID);
    }

    public function runDueScheduledPhoneScreens($limit = 10)
    {
        $limit = max(1, min(50, (int) $limit));
        $this->_db->query(
            'UPDATE nesp_vapi_phone_screen
             SET status_key = "call_due",
                 date_modified = NOW()
             WHERE status_key = "phone_screen_scheduled"
               AND scheduled_start_at_utc <= UTC_TIMESTAMP()
               AND provider_call_id IS NULL
               AND call_attempt_count = 0
               AND scheduling_token_revoked_at IS NULL'
        );

        $rows = $this->_db->getAllAssoc(
            sprintf(
                'SELECT vapi_phone_screen_id
                 FROM nesp_vapi_phone_screen
                 WHERE status_key = "call_due"
                   AND scheduled_start_at_utc <= UTC_TIMESTAMP()
                   AND provider_call_id IS NULL
                   AND call_attempt_count = 0
                   AND scheduling_token_revoked_at IS NULL
                 ORDER BY scheduled_start_at_utc ASC
                 LIMIT %s',
                $this->_db->makeQueryInteger($limit)
            )
        );

        $results = array();
        foreach ($rows as $row)
        {
            $results[] = $this->startPhoneScreenCall((int) $row['vapi_phone_screen_id'], null);
        }
        return $results;
    }

    public function processVapiWebhook($validation)
    {
        if (empty($validation['ok']))
        {
            return $validation;
        }

        $existing = $this->_db->getAssoc(
            sprintf(
                'SELECT vapi_webhook_event_id
                 FROM nesp_vapi_webhook_event
                 WHERE provider_event_id = %s
                 LIMIT 1',
                $this->_db->makeQueryString($validation['event_id'])
            )
        );
        if (!empty($existing))
        {
            return array('ok' => true, 'duplicate' => true, 'status' => 200);
        }

        $redactedPayload = NESPVapiIntegration::redactedPayloadForStorage($validation['payload']);
        $sql = sprintf(
            'INSERT INTO nesp_vapi_webhook_event
                (provider_event_id, provider_call_id, event_type, event_timestamp, payload_sha256, redacted_payload_json, processed_at, date_created)
             VALUES
                (%s, %s, %s, %s, %s, %s, NOW(), NOW())',
            $this->_db->makeQueryString($validation['event_id']),
            $this->_db->makeQueryString($validation['provider_call_id']),
            $this->_db->makeQueryString($validation['event_type']),
            $this->_db->makeQueryString($validation['event_timestamp']),
            $this->_db->makeQueryString($validation['payload_sha256']),
            $this->_db->makeQueryString($redactedPayload)
        );
        $this->_db->query($sql);

        $screen = $this->_db->getAssoc(
            sprintf(
                'SELECT vapi_phone_screen_id
                 FROM nesp_vapi_phone_screen
                 WHERE provider_call_id = %s
                 LIMIT 1',
                $this->_db->makeQueryString($validation['provider_call_id'])
            )
        );
        if (!empty($screen))
        {
            $update = NESPVapiIntegration::buildScreenUpdateFromWebhookMessage($validation['message']);
            $this->applyPhoneScreenWebhookUpdate($screen['vapi_phone_screen_id'], $validation, $update);
        }

        return array('ok' => true, 'duplicate' => false, 'status' => 200);
    }

    public function savePhoneScreenReview($phoneScreenID, $actorUserID, $reviewNote)
    {
        $reviewNote = trim($reviewNote);
        if ($reviewNote === '')
        {
            return false;
        }

        $this->logAuditEvent(
            $actorUserID,
            'vapi_phone_screen_review_saved',
            'vapi_phone_screen',
            $phoneScreenID,
            array('review_note_sha256' => hash('sha256', $reviewNote), 'review_note_length' => strlen($reviewNote))
        );
        return true;
    }

    public function getRecentAuditEvents($limit)
    {
        $limit = max(1, min(100, (int) $limit));

        return $this->_db->getAllAssoc(
            sprintf(
                'SELECT
                    audit_event_id,
                    actor_user_id,
                    event_type,
                    entity_type,
                    entity_id,
                    metadata_json,
                    date_created
                FROM
                    nesp_audit_event
                ORDER BY
                    date_created DESC,
                    audit_event_id DESC
                LIMIT %s',
                $this->_db->makeQueryInteger($limit)
            )
        );
    }

    public function userCanAccessCandidate($userID, $candidateID, $jobOrderID)
    {
        $sql = sprintf(
            'SELECT
                grant_id
            FROM
                nesp_interviewer_profile ip
            INNER JOIN nesp_interviewer_candidate_grant cg
                ON cg.interviewer_profile_id = ip.interviewer_profile_id
            WHERE
                ip.user_id = %s
                AND ip.is_active = 1
                AND cg.candidate_id = %s
                AND cg.joborder_id = %s
                AND cg.date_revoked IS NULL
            LIMIT 1',
            $this->_db->makeQueryInteger($userID),
            $this->_db->makeQueryInteger($candidateID),
            $this->_db->makeQueryInteger($jobOrderID)
        );

        $rs = $this->_db->getAssoc($sql);
        return !empty($rs);
    }

    private function getActiveInterviewsForCandidateJob($candidateID, $jobOrderID, $excludeInterviewID)
    {
        return $this->_db->getAllAssoc(
            sprintf(
                'SELECT interview_id, scheduled_start, scheduled_end, status_key
                 FROM nesp_interview
                 WHERE candidate_id = %s
                   AND joborder_id = %s
                   AND interview_id <> %s
                   AND status_key IN ("requested", "scheduled", "invitation_pending", "invitation_sent", "confirmed", "reschedule_needed")
                 ORDER BY scheduled_start ASC',
                $this->_db->makeQueryInteger($candidateID),
                $this->_db->makeQueryInteger($jobOrderID),
                $this->_db->makeQueryInteger($excludeInterviewID)
            )
        );
    }

    private function getDefaultZoomJoinURLForInterviewer($interviewerProfileID)
    {
        if (!$this->isColumnInstalled('nesp_interviewer_profile', 'default_zoom_join_url'))
        {
            return '';
        }

        $row = $this->_db->getAssoc(
            sprintf(
                'SELECT default_zoom_join_url
                 FROM nesp_interviewer_profile
                 WHERE interviewer_profile_id = %s
                 LIMIT 1',
                $this->_db->makeQueryInteger($interviewerProfileID)
            )
        );

        return empty($row) ? '' : trim((string) $row['default_zoom_join_url']);
    }

    private function normalizeManualInterviewInput($input)
    {
        $candidateID = isset($input['candidateID']) ? (int) $input['candidateID'] : 0;
        $jobOrderID = isset($input['jobOrderID']) ? (int) $input['jobOrderID'] : 0;
        $interviewerProfileID = isset($input['interviewerProfileID']) ? (int) $input['interviewerProfileID'] : 0;
        $date = isset($input['interviewDate']) ? trim((string) $input['interviewDate']) : '';
        $time = isset($input['interviewTime']) ? trim((string) $input['interviewTime']) : '';
        $durationMinutes = isset($input['durationMinutes']) ? (int) $input['durationMinutes'] : 30;
        $timezone = isset($input['timezone']) ? trim((string) $input['timezone']) : 'America/New_York';
        $joinURL = isset($input['zoomJoinURL']) ? trim((string) $input['zoomJoinURL']) : '';
        $internalNotes = isset($input['internalNotes']) ? substr(trim((string) $input['internalNotes']), 0, 2000) : '';

        if ($candidateID <= 0 || $jobOrderID <= 0 || $interviewerProfileID <= 0)
        {
            return array('ok' => false, 'error' => 'Choose a candidate, role, and interviewer.');
        }
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) || !self::isValidAvailabilityTime($time))
        {
            return array('ok' => false, 'error' => 'Choose a valid interview date and time.');
        }
        $durationMinutes = max(5, min(240, $durationMinutes));
        if ($timezone === '')
        {
            $timezone = 'America/New_York';
        }

        if ($joinURL === '' && $this->isFeatureFlagEnabled('NESP_INTERVIEWER_ZOOM_LINKS_ENABLED'))
        {
            $joinURL = $this->getDefaultZoomJoinURLForInterviewer($interviewerProfileID);
        }

        $zoomValidation = self::validateZoomApplicantJoinURL($joinURL);
        if (empty($zoomValidation['ok']))
        {
            return $zoomValidation;
        }

        $preview = $this->getCandidateInterviewPreview($candidateID, $jobOrderID);
        if (empty($preview))
        {
            return array('ok' => false, 'error' => 'Candidate is not active or not attached to this role.');
        }
        if (!$this->interviewerCanReceiveAssignment($interviewerProfileID, $jobOrderID))
        {
            return array('ok' => false, 'error' => 'Interviewer is inactive, closed, or not approved for this role.');
        }

        $scheduledStart = $date . ' ' . $time . ':00';
        $scheduledEnd = date('Y-m-d H:i:s', strtotime($scheduledStart) + ($durationMinutes * 60));

        return array(
            'ok' => true,
            'candidate_id' => $candidateID,
            'joborder_id' => $jobOrderID,
            'interviewer_profile_id' => $interviewerProfileID,
            'scheduled_start' => $scheduledStart,
            'scheduled_end' => $scheduledEnd,
            'duration_minutes' => $durationMinutes,
            'timezone' => $timezone,
            'manual_zoom_join_url' => $zoomValidation['url'],
            'internal_notes' => $internalNotes,
            'candidate_first_name' => $preview['first_name'],
            'role_title' => $preview['role_title']
        );
    }

    private function decorateInterviewRow($row)
    {
        $statusLabels = self::getManualInterviewStatusLabels();
        $outcomeLabels = self::getManualInterviewOutcomeLabels();
        $row['status_label'] = isset($statusLabels[$row['status_key']]) ? $statusLabels[$row['status_key']] : $row['status_key'];
        $row['outcome_label'] = isset($outcomeLabels[$row['outcome_key']]) ? $outcomeLabels[$row['outcome_key']] : $row['outcome_key'];
        $row['zoom_join_url_masked'] = self::maskZoomURLForAudit(isset($row['manual_zoom_join_url']) ? $row['manual_zoom_join_url'] : '');
        $openCount = isset($row['participant_link_open_count']) ? (int) $row['participant_link_open_count'] : 0;
        if ($openCount <= 0)
        {
            $row['participant_link_tracking_label'] = 'Not opened yet';
        }
        elseif ($openCount === 1)
        {
            $row['participant_link_tracking_label'] = 'Opened once' . (empty($row['participant_link_last_opened_at']) ? '' : ' at ' . date('M j, Y g:i A', strtotime($row['participant_link_last_opened_at'])));
        }
        else
        {
            $row['participant_link_tracking_label'] = 'Opened ' . $openCount . ' times' . (empty($row['participant_link_last_opened_at']) ? '' : '; last at ' . date('M j, Y g:i A', strtotime($row['participant_link_last_opened_at'])));
        }
        return $row;
    }

    /**
     * Ensure a candidate/job-order pair has one safe initial workflow row.
     * Existing rows are deliberately left unchanged so this is safe to call
     * from both public applications and approved board imports.
     */
    public function ensureCandidateWorkflowRow($candidateID, $jobOrderID, $actorUserID = null, $sourceLabel = '', $summary = null, $nextActionLabel = null)
    {
        $candidateID = (int) $candidateID;
        $jobOrderID = (int) $jobOrderID;
        if ($candidateID <= 0 || $jobOrderID <= 0)
        {
            return false;
        }

        $existing = $this->_db->getAssoc(
            sprintf(
                'SELECT candidate_workflow_id
                 FROM nesp_candidate_workflow
                 WHERE candidate_id = %s
                   AND joborder_id = %s
                 LIMIT 1',
                $this->_db->makeQueryInteger($candidateID),
                $this->_db->makeQueryInteger($jobOrderID)
            )
        );
        if (!empty($existing))
        {
            return (int) $existing['candidate_workflow_id'];
        }

        $stage = $this->_db->getAssoc(
            sprintf(
                'SELECT workflow_stage_id
                 FROM nesp_workflow_stage
                 WHERE stage_key = %s
                 LIMIT 1',
                $this->_db->makeQueryString('new')
            )
        );
        if (empty($stage))
        {
            return false;
        }

        $waitingOn = 'Craig';
        $summary = ($summary === null || trim((string) $summary) === '')
            ? 'New application awaiting human review.'
            : trim((string) $summary);
        $nextActionLabel = ($nextActionLabel === null || trim((string) $nextActionLabel) === '')
            ? 'Review application'
            : trim((string) $nextActionLabel);
        $inserted = false;
        if ($this->isColumnInstalled('nesp_candidate_workflow', 'priority'))
        {
            $inserted = $this->_db->query(
                sprintf(
                    'INSERT INTO nesp_candidate_workflow
                        (candidate_id, joborder_id, workflow_stage_id, priority, waiting_on_key, summary, next_action_label, date_created, date_modified)
                     VALUES
                        (%s, %s, %s, 1, %s, %s, %s, NOW(), NOW())',
                    $this->_db->makeQueryInteger($candidateID),
                    $this->_db->makeQueryInteger($jobOrderID),
                    $this->_db->makeQueryInteger($stage['workflow_stage_id']),
                    $this->_db->makeQueryString($waitingOn),
                    $this->_db->makeQueryString($summary),
                    $this->_db->makeQueryString($nextActionLabel)
                )
            );
        }
        else
        {
            $inserted = $this->_db->query(
                sprintf(
                    'INSERT INTO nesp_candidate_workflow
                        (candidate_id, joborder_id, workflow_stage_id, waiting_on_key, summary, next_action_label, date_created, date_modified)
                     VALUES
                        (%s, %s, %s, %s, %s, %s, NOW(), NOW())',
                    $this->_db->makeQueryInteger($candidateID),
                    $this->_db->makeQueryInteger($jobOrderID),
                    $this->_db->makeQueryInteger($stage['workflow_stage_id']),
                    $this->_db->makeQueryString($waitingOn),
                    $this->_db->makeQueryString($summary),
                    $this->_db->makeQueryString($nextActionLabel)
                )
            );
        }

        if (!$inserted)
        {
            // The unique candidate/job-order key makes a concurrent ensure safe.
            $existing = $this->_db->getAssoc(
                sprintf(
                    'SELECT candidate_workflow_id
                     FROM nesp_candidate_workflow
                     WHERE candidate_id = %s
                       AND joborder_id = %s
                     LIMIT 1',
                    $this->_db->makeQueryInteger($candidateID),
                    $this->_db->makeQueryInteger($jobOrderID)
                )
            );
            return !empty($existing) ? (int) $existing['candidate_workflow_id'] : false;
        }

        $workflowID = (int) $this->_db->getLastInsertID();
        if ($workflowID <= 0)
        {
            $existing = $this->_db->getAssoc(
                sprintf(
                    'SELECT candidate_workflow_id
                     FROM nesp_candidate_workflow
                     WHERE candidate_id = %s
                       AND joborder_id = %s
                     LIMIT 1',
                    $this->_db->makeQueryInteger($candidateID),
                    $this->_db->makeQueryInteger($jobOrderID)
                )
            );
            return !empty($existing) ? (int) $existing['candidate_workflow_id'] : false;
        }

        $sourceLabel = trim((string) $sourceLabel);
        $this->logAuditEvent($actorUserID, 'candidate_workflow_created', 'candidate_workflow', $workflowID, array(
            'candidate_id' => $candidateID,
            'joborder_id' => $jobOrderID,
            'stage_key' => 'new',
            'source_label' => $sourceLabel
        ));

        return $workflowID;
    }

    private function setCandidateWorkflowStage($candidateID, $jobOrderID, $stageKey, $waitingOn, $summary, $nextActionLabel, $actorUserID)
    {
        $stage = $this->_db->getAssoc(
            sprintf(
                'SELECT workflow_stage_id
                 FROM nesp_workflow_stage
                 WHERE stage_key = %s
                 LIMIT 1',
                $this->_db->makeQueryString($stageKey)
            )
        );
        if (empty($stage))
        {
            return false;
        }

        $existing = $this->_db->getAssoc(
            sprintf(
                'SELECT candidate_workflow_id
                 FROM nesp_candidate_workflow
                 WHERE candidate_id = %s
                   AND joborder_id = %s
                 LIMIT 1',
                $this->_db->makeQueryInteger($candidateID),
                $this->_db->makeQueryInteger($jobOrderID)
            )
        );

        if (empty($existing))
        {
            if ($this->isColumnInstalled('nesp_candidate_workflow', 'priority'))
            {
                $this->_db->query(
                    sprintf(
                        'INSERT INTO nesp_candidate_workflow
                            (candidate_id, joborder_id, workflow_stage_id, priority, waiting_on_key, summary, next_action_label, date_created, date_modified)
                         VALUES
                            (%s, %s, %s, 1, %s, %s, %s, NOW(), NOW())',
                        $this->_db->makeQueryInteger($candidateID),
                        $this->_db->makeQueryInteger($jobOrderID),
                        $this->_db->makeQueryInteger($stage['workflow_stage_id']),
                        $this->_db->makeQueryString($waitingOn),
                        $this->_db->makeQueryString($summary),
                        $this->_db->makeQueryString($nextActionLabel)
                    )
                );
            }
            else
            {
                $this->_db->query(
                    sprintf(
                        'INSERT INTO nesp_candidate_workflow
                            (candidate_id, joborder_id, workflow_stage_id, waiting_on_key, summary, next_action_label, date_created, date_modified)
                         VALUES
                            (%s, %s, %s, %s, %s, %s, NOW(), NOW())',
                        $this->_db->makeQueryInteger($candidateID),
                        $this->_db->makeQueryInteger($jobOrderID),
                        $this->_db->makeQueryInteger($stage['workflow_stage_id']),
                        $this->_db->makeQueryString($waitingOn),
                        $this->_db->makeQueryString($summary),
                        $this->_db->makeQueryString($nextActionLabel)
                    )
                );
            }
            $workflowID = (int) $this->_db->getLastInsertID();
        }
        else
        {
            $workflowID = (int) $existing['candidate_workflow_id'];
            $this->_db->query(
                sprintf(
                    'UPDATE nesp_candidate_workflow
                     SET workflow_stage_id = %s,
                         waiting_on_key = %s,
                         summary = %s,
                         next_action_label = %s,
                         date_modified = NOW()
                     WHERE candidate_workflow_id = %s',
                    $this->_db->makeQueryInteger($stage['workflow_stage_id']),
                    $this->_db->makeQueryString($waitingOn),
                    $this->_db->makeQueryString($summary),
                    $this->_db->makeQueryString($nextActionLabel),
                    $this->_db->makeQueryInteger($workflowID)
                )
            );
        }

        $this->logAuditEvent($actorUserID, 'candidate_workflow_stage_changed', 'candidate_workflow', $workflowID, array(
            'candidate_id' => (int) $candidateID,
            'joborder_id' => (int) $jobOrderID,
            'stage_key' => $stageKey
        ));

        return true;
    }

    private function decorateQuestionnaireRows($rows)
    {
        $decorated = array();
        foreach ($rows as $row)
        {
            $decorated[] = $this->decorateQuestionnaireRow($row);
        }
        return $decorated;
    }

    private function decorateQuestionnaireRow($row)
    {
        if (empty($row['submitted_at']) && empty($row['token_revoked_at']) && !empty($row['token_expires_at'])
            && strtotime($row['token_expires_at']) < time()
            && in_array($row['status_key'], array('link_ready', 'waiting', 'in_progress')))
        {
            $row['status_key'] = 'expired';
        }
        $labels = self::getQuestionnaireStatusLabels();
        $row['status_label'] = isset($labels[$row['status_key']]) ? $labels[$row['status_key']] : $row['status_key'];
        $set = self::getQuestionnaireSetForRole(isset($row['role_title']) ? $row['role_title'] : '');
        if (empty($row['question_set_key']))
        {
            $row['question_set_key'] = $set['key'];
        }
        $sets = self::getQuestionnaireQuestionSets();
        $row['question_set_label'] = isset($sets[$row['question_set_key']]) ? $sets[$row['question_set_key']]['label'] : $set['label'];
        if (!empty($row['question_set_version_id']) && $this->isTableInstalled('nesp_question_set_version'))
        {
            $versionLabel = $this->_db->getAssoc(sprintf(
                'SELECT COALESCE(NULLIF(qsv.display_name, ""), qs.display_name) AS display_name,
                        COALESCE(qsv.description, qs.description) AS description,
                        qsv.version_number
                 FROM nesp_question_set_version qsv
                 INNER JOIN nesp_question_set qs
                    ON qs.question_set_id = qsv.question_set_id
                 WHERE qsv.question_set_version_id = %s
                 LIMIT 1',
                $this->_db->makeQueryInteger((int) $row['question_set_version_id'])
            ));
            if (!empty($versionLabel))
            {
                $row['question_set_label'] = $versionLabel['display_name'];
                $row['question_set_version'] = (int) $versionLabel['version_number'];
                $row['question_set_intro'] = isset($versionLabel['description']) ? (string) $versionLabel['description'] : '';
            }
        }
        if (empty($row['question_set_intro']))
        {
            $row['question_set_intro'] = self::getQuestionnaireIntroForSet($row['question_set_key']);
        }
        $row['candidate_name'] = isset($row['candidate_name']) ? trim($row['candidate_name']) : '';
        $row['reviewer_name'] = empty($row['reviewer_name']) ? 'Unassigned' : $row['reviewer_name'];
        $row['has_active_link'] = !empty($row['token_hash'])
            && empty($row['token_revoked_at'])
            && empty($row['submitted_at'])
            && (empty($row['token_expires_at']) || strtotime($row['token_expires_at']) >= time());
        return $row;
    }

    private function userCanReviewQuestionnaire($userID, $detail)
    {
        if ($this->getUserIsAdmin($userID))
        {
            return true;
        }
        $profile = $this->getInterviewerProfileForUser($userID);
        if (empty($profile))
        {
            return false;
        }
        if (!empty($detail['reviewer_profile_id']) && (int) $detail['reviewer_profile_id'] === (int) $profile['interviewer_profile_id'])
        {
            return $this->userCanAccessCandidate($userID, $detail['candidate_id'], $detail['joborder_id']);
        }
        return false;
    }

    private function getUserIsAdmin($userID)
    {
        $row = $this->_db->getAssoc(
            sprintf(
                'SELECT access_level
                 FROM `user`
                 WHERE user_id = %s
                 LIMIT 1',
                $this->_db->makeQueryInteger($userID)
            )
        );
        return !empty($row) && (int) $row['access_level'] >= ACCESS_LEVEL_SA;
    }

    private function isQuestionnaireRateLimited($questionnaireID, $tokenHash)
    {
        $ipHash = $this->publicRequestIPHash();
        $row = $this->_db->getAssoc(
            sprintf(
                'SELECT COUNT(*) AS total
                 FROM nesp_screening_questionnaire_activity
                 WHERE token_hash = %s
                   AND ip_hash = %s
                   AND date_created >= DATE_SUB(NOW(), INTERVAL 1 HOUR)',
                $this->_db->makeQueryString($tokenHash),
                $this->_db->makeQueryString($ipHash)
            )
        );
        return !empty($row) && (int) $row['total'] > 80;
    }

    private function logQuestionnaireActivity($questionnaireID, $tokenHash, $activityKey, $metadata)
    {
        $metadataJSON = json_encode($metadata);
        if ($metadataJSON === false)
        {
            $metadataJSON = '{}';
        }
        $this->_db->query(
            sprintf(
                'INSERT INTO nesp_screening_questionnaire_activity
                    (screening_questionnaire_id, token_hash, activity_key, ip_hash, user_agent_hash, metadata_json, date_created)
                 VALUES
                    (%s, %s, %s, %s, %s, %s, NOW())',
                $questionnaireID === null ? 'NULL' : $this->_db->makeQueryInteger($questionnaireID),
                $this->_db->makeQueryString($tokenHash),
                $this->_db->makeQueryString($activityKey),
                $this->_db->makeQueryString($this->publicRequestIPHash()),
                $this->_db->makeQueryString(hash('sha256', isset($_SERVER['HTTP_USER_AGENT']) ? substr($_SERVER['HTTP_USER_AGENT'], 0, 255) : '')),
                $this->_db->makeQueryString($metadataJSON)
            )
        );
    }

    private function publicRequestIPHash()
    {
        return hash('sha256', isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : '');
    }

    private function decoratePhoneScreenRows($rows)
    {
        $decorated = array();
        foreach ($rows as $row)
        {
            $decorated[] = $this->decoratePhoneScreenRow($row);
        }
        return $decorated;
    }

    private function decoratePhoneScreenRow($row)
    {
        $labels = NESPVapiIntegration::getPhoneScreenStatusLabels();
        $row['status_label'] = isset($labels[$row['status_key']]) ? $labels[$row['status_key']] : $row['status_key'];
        $row['has_active_scheduling_link'] = !empty($row['scheduling_token_hash'])
            && empty($row['scheduling_token_revoked_at'])
            && (empty($row['scheduling_token_expires_at']) || strtotime($row['scheduling_token_expires_at']) >= time());
        $row['scheduled_display'] = '';
        if (!empty($row['scheduled_start_et']))
        {
            $row['scheduled_display'] = date('M j, Y g:i A', strtotime($row['scheduled_start_et'])) . ' ET';
        }
        return $row;
    }

    private function getAvailablePhoneScreenSlots($excludePhoneScreenID = 0)
    {
        $settings = $this->getPhoneScreenAvailabilitySettings();
        $blocks = $this->getPhoneScreenAvailabilityBlocks();
        $blackouts = $this->getPhoneScreenBlackoutDates();
        $blackoutMap = array();
        foreach ($blackouts as $blackout)
        {
            $blackoutMap[$blackout['blackout_date']] = true;
        }

        $tz = new DateTimeZone($settings['timezone']);
        $now = new DateTime('now', $tz);
        $earliest = clone $now;
        $earliest->modify('+' . (int) $settings['min_booking_notice_minutes'] . ' minutes');
        $horizon = clone $now;
        $horizon->modify('+' . (int) $settings['booking_horizon_days'] . ' days');
        $appointments = $this->getBookedPhoneScreenAppointments($excludePhoneScreenID);
        $slots = array();

        $day = new DateTime($now->format('Y-m-d 00:00:00'), $tz);
        while ($day <= $horizon && count($slots) < 80)
        {
            $dateKey = $day->format('Y-m-d');
            if (!isset($blackoutMap[$dateKey]))
            {
                $weekday = (int) $day->format('w');
                foreach ($blocks as $block)
                {
                    if ((int) $block['weekday'] !== $weekday || (int) $block['is_available'] !== 1)
                    {
                        continue;
                    }
                    $cursor = new DateTime($dateKey . ' ' . $block['start_time'], $tz);
                    $end = new DateTime($dateKey . ' ' . $block['end_time'], $tz);
                    while ($cursor < $end)
                    {
                        if ($cursor >= $earliest
                            && $cursor->format('H:i') >= $settings['earliest_call_time']
                            && $cursor->format('H:i') <= $settings['latest_call_time'])
                        {
                            $utc = clone $cursor;
                            $utc->setTimezone(new DateTimeZone('UTC'));
                            $utcValue = $utc->format('Y-m-d H:i:s');
                            if ($this->isSlotWithinCapacity($utcValue, $appointments, $settings))
                            {
                                $slots[] = array(
                                    'value' => $utcValue,
                                    'label' => $cursor->format('D, M j, Y g:i A') . ' ET'
                                );
                            }
                        }
                        $cursor->modify('+' . (int) $settings['slot_minutes'] . ' minutes');
                    }
                }
            }
            $day->modify('+1 day');
        }

        return $slots;
    }

    private function getBookedPhoneScreenAppointments($excludePhoneScreenID = 0)
    {
        return $this->_db->getAllAssoc(
            sprintf(
                'SELECT vapi_phone_screen_id, scheduled_start_at_utc
                 FROM nesp_vapi_phone_screen
                 WHERE scheduled_start_at_utc IS NOT NULL
                   AND status_key IN ("phone_screen_scheduled", "call_due", "call_started", "ringing", "in_progress")
                   AND vapi_phone_screen_id <> %s',
                $this->_db->makeQueryInteger($excludePhoneScreenID)
            )
        );
    }

    private function isSlotAvailable($slotStartUTC, $excludePhoneScreenID = 0)
    {
        $settings = $this->getPhoneScreenAvailabilitySettings();
        $appointments = $this->getBookedPhoneScreenAppointments($excludePhoneScreenID);
        return $this->isSlotWithinCapacity($slotStartUTC, $appointments, $settings);
    }

    private function isSlotWithinCapacity($slotStartUTC, $appointments, $settings)
    {
        if (NESPVapiIntegration::slotConflictsWithAppointments($slotStartUTC, $appointments, $settings))
        {
            return false;
        }

        $slot = strtotime($slotStartUTC);
        $hourStart = strtotime(date('Y-m-d H:00:00', $slot));
        $hourEnd = $hourStart + 3600;
        $dayStart = strtotime(date('Y-m-d 00:00:00', $slot));
        $dayEnd = $dayStart + 86400;
        $hourCount = 0;
        $dayCount = 0;
        foreach ($appointments as $appointment)
        {
            $existing = strtotime($appointment['scheduled_start_at_utc']);
            if ($existing >= $hourStart && $existing < $hourEnd)
            {
                $hourCount++;
            }
            if ($existing >= $dayStart && $existing < $dayEnd)
            {
                $dayCount++;
            }
        }

        return $hourCount < (int) $settings['max_screens_per_hour']
            && $dayCount < (int) $settings['max_screens_per_day'];
    }

    private function candidateSchedulingStatusChange($token, $statusKey, $activityKey)
    {
        $page = $this->getSchedulingPageByToken($token);
        if (empty($page['ok']))
        {
            return $page;
        }

        $screen = $page['screen'];
        if (!in_array($screen['status_key'], array('scheduling_link_ready', 'waiting_for_candidate_to_schedule', 'phone_screen_scheduled', 'reschedule_requested')))
        {
            return array('ok' => false, 'state' => 'not_schedulable');
        }

        $tokenHash = NESPVapiIntegration::schedulingTokenHash($token);
        $cancelledAtSQL = $statusKey === 'cancelled' ? 'NOW()' : 'cancelled_at';
        $revokedAtSQL = in_array($statusKey, array('cancelled', 'human_follow_up_requested')) ? 'UTC_TIMESTAMP()' : 'scheduling_token_revoked_at';
        $this->_db->query(
            sprintf(
                'UPDATE nesp_vapi_phone_screen
                 SET status_key = %s,
                     scheduled_start_at_utc = NULL,
                     scheduled_end_at_utc = NULL,
                     scheduled_start_et = NULL,
                     scheduling_token_revoked_at = %s,
                     cancelled_at = %s,
                     date_modified = NOW()
                 WHERE vapi_phone_screen_id = %s
                   AND scheduling_token_hash = %s',
                $this->_db->makeQueryString($statusKey),
                $revokedAtSQL,
                $cancelledAtSQL,
                $this->_db->makeQueryInteger($screen['vapi_phone_screen_id']),
                $this->_db->makeQueryString($tokenHash)
            )
        );
        $this->logSchedulingActivity($screen['vapi_phone_screen_id'], $tokenHash, $activityKey, array());
        $this->logAuditEvent(null, 'vapi_phone_screen_' . $activityKey, 'vapi_phone_screen', $screen['vapi_phone_screen_id'], array());
        return array('ok' => true, 'state' => $statusKey);
    }

    private function isSchedulingRateLimited($phoneScreenID, $tokenHash)
    {
        $ipHash = $this->schedulingIPHash();
        $row = $this->_db->getAssoc(
            sprintf(
                'SELECT COUNT(*) AS total
                 FROM nesp_vapi_scheduling_activity
                 WHERE scheduling_token_hash = %s
                   AND ip_hash = %s
                   AND date_created >= DATE_SUB(NOW(), INTERVAL 1 HOUR)',
                $this->_db->makeQueryString($tokenHash),
                $this->_db->makeQueryString($ipHash)
            )
        );
        return !empty($row) && (int) $row['total'] > 60;
    }

    private function logSchedulingActivity($phoneScreenID, $tokenHash, $activityKey, $metadata)
    {
        $metadataJSON = json_encode($metadata);
        if ($metadataJSON === false)
        {
            $metadataJSON = '{}';
        }
        $this->_db->query(
            sprintf(
                'INSERT INTO nesp_vapi_scheduling_activity
                    (vapi_phone_screen_id, scheduling_token_hash, activity_key, ip_hash, user_agent_hash, metadata_json, date_created)
                 VALUES
                    (%s, %s, %s, %s, %s, %s, NOW())',
                $phoneScreenID === null ? 'NULL' : $this->_db->makeQueryInteger($phoneScreenID),
                $this->_db->makeQueryString($tokenHash),
                $this->_db->makeQueryString($activityKey),
                $this->_db->makeQueryString($this->schedulingIPHash()),
                $this->_db->makeQueryString(hash('sha256', isset($_SERVER['HTTP_USER_AGENT']) ? substr($_SERVER['HTTP_USER_AGENT'], 0, 255) : '')),
                $this->_db->makeQueryString($metadataJSON)
            )
        );
    }

    private function schedulingIPHash()
    {
        return hash('sha256', isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : '');
    }

    private function getDestinationPhoneForScreen($candidateID)
    {
        $row = $this->_db->getAssoc(
            sprintf(
                'SELECT phone_cell, phone_home, phone_work
                 FROM candidate
                 WHERE candidate_id = %s
                 LIMIT 1',
                $this->_db->makeQueryInteger($candidateID)
            )
        );
        if (empty($row))
        {
            return '';
        }
        if (trim($row['phone_cell']) !== '')
        {
            return $row['phone_cell'];
        }
        if (trim($row['phone_home']) !== '')
        {
            return $row['phone_home'];
        }
        return $row['phone_work'];
    }

    private function updatePhoneScreenStatus($phoneScreenID, $statusKey, $providerCallID, $providerEndReason)
    {
        $sql = sprintf(
            'UPDATE nesp_vapi_phone_screen
             SET status_key = %s,
                 provider_call_id = CASE WHEN %s = "" THEN provider_call_id ELSE %s END,
                 provider_end_reason = %s,
                 date_modified = NOW()
             WHERE vapi_phone_screen_id = %s',
            $this->_db->makeQueryString($statusKey),
            $this->_db->makeQueryString($providerCallID),
            $this->_db->makeQueryString($providerCallID),
            $this->_db->makeQueryString($providerEndReason),
            $this->_db->makeQueryInteger($phoneScreenID)
        );
        $this->_db->query($sql);
    }

    private function claimPhoneScreenForStart($phoneScreenID)
    {
        $claimKey = hash('sha256', gethostname() . '|' . microtime(true) . '|' . mt_rand());
        $sql = sprintf(
            'UPDATE nesp_vapi_phone_screen
             SET status_key = "call_started",
                 call_claimed_at = UTC_TIMESTAMP(),
                 call_attempted_at = UTC_TIMESTAMP(),
                 call_attempt_count = call_attempt_count + 1,
                 scheduler_claim_key = %s,
                 date_modified = NOW()
             WHERE vapi_phone_screen_id = %s
               AND status_key IN ("call_due", "phone_screen_scheduled")
               AND scheduled_start_at_utc <= UTC_TIMESTAMP()
               AND scheduling_token_revoked_at IS NULL
               AND call_attempt_count = 0
               AND provider_call_id IS NULL',
            $this->_db->makeQueryString($claimKey),
            $this->_db->makeQueryInteger($phoneScreenID)
        );
        $this->_db->query($sql);
        return $this->_db->getAffectedRows() === 1;
    }

    private function applyPhoneScreenWebhookUpdate($phoneScreenID, $validation, $update)
    {
        $completedAt = in_array($update['status_key'], array('completed', 'no_answer', 'provider_error', 'cancelled', 'consent_refused')) ? 'NOW()' : 'completed_at';
        $startedAt = $update['status_key'] === 'in_progress' ? 'NOW()' : 'started_at';
        $consentAcceptedAt = $update['consent_accepted'] ? 'NOW()' : 'consent_accepted_at';
        $statusKey = $update['consent_status'] === 'refused' ? 'consent_refused' : $update['status_key'];

        $sql = sprintf(
            'UPDATE nesp_vapi_phone_screen
             SET status_key = %s,
                 consent_status = %s,
                 consent_response_raw = %s,
                 consent_accepted_at = %s,
                 transcript_text = CASE WHEN %s = "" THEN transcript_text ELSE %s END,
                 structured_result_json = CASE WHEN %s = "{}" THEN structured_result_json ELSE %s END,
                 provider_end_reason = %s,
                 last_webhook_event_id = %s,
                 last_webhook_at = %s,
                 started_at = %s,
                 completed_at = %s,
                 date_modified = NOW()
             WHERE vapi_phone_screen_id = %s',
            $this->_db->makeQueryString($statusKey),
            $this->_db->makeQueryString($update['consent_status']),
            $this->_db->makeQueryString(substr($update['consent_response_raw'], 0, 255)),
            $consentAcceptedAt,
            $this->_db->makeQueryString($update['transcript_text']),
            $this->_db->makeQueryString($update['transcript_text']),
            $this->_db->makeQueryString($update['structured_result_json']),
            $this->_db->makeQueryString($update['structured_result_json']),
            $this->_db->makeQueryString($update['provider_end_reason']),
            $this->_db->makeQueryString($validation['event_id']),
            $this->_db->makeQueryString($validation['event_timestamp']),
            $startedAt,
            $completedAt,
            $this->_db->makeQueryInteger($phoneScreenID)
        );
        $this->_db->query($sql);
    }

    private function postVapiCall($payload)
    {
        if (!function_exists('curl_init'))
        {
            return array('ok' => false, 'error' => 'curl_unavailable');
        }

        $json = json_encode($payload);
        if ($json === false)
        {
            return array('ok' => false, 'error' => 'payload_encode_failed');
        }

        $ch = curl_init('https://api.vapi.ai/call');
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $json);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 15);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array(
            'Authorization: Bearer ' . getenv('VAPI_API_KEY'),
            'Content-Type: application/json'
        ));
        $body = curl_exec($ch);
        $error = curl_error($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($body === false || $status < 200 || $status >= 300)
        {
            return array('ok' => false, 'error' => $error !== '' ? $error : 'provider_http_' . $status);
        }

        $decoded = json_decode($body, true);
        return array('ok' => true, 'body' => is_array($decoded) ? $decoded : array());
    }

    private function normalizeDashboardCard($row)
    {
        $candidateName = trim($row['first_name'] . ' ' . $row['last_name']);
        $summary = trim($row['summary']);
        if ($summary === '')
        {
            $summary = 'Workflow item is waiting in the ' . $row['stage_name'] . ' stage.';
        }

        $waitingOn = trim($row['waiting_on_key']);
        if ($waitingOn === '')
        {
            $waitingOn = $this->inferWaitingOn($row['stage_key']);
        }

        $nextAction = trim($row['next_action_label']);
        if ($nextAction === '')
        {
            $nextAction = $this->inferNextAction($row['stage_key']);
        }

        $candidateEmail = isset($row['candidate_email']) ? (string) $row['candidate_email'] : '';
        $nextAction = self::resolveContactNextAction($nextAction, $candidateEmail, $row['stage_key']);

        $candidateURL = CATSUtility::getIndexName() . '?m=candidates&amp;a=show&amp;candidateID=' . (int) $row['candidate_id'];
        $primaryActionURL = $candidateURL;
        if (strcasecmp($nextAction, 'Collect contact details') === 0)
        {
            $primaryActionURL = CATSUtility::getIndexName() . '?m=nesp&amp;a=collectContactDetails&amp;workflowID=' . (int) $row['candidate_workflow_id'] . '&amp;candidateID=' . (int) $row['candidate_id'] . '&amp;jobOrderID=' . (int) $row['joborder_id'];
        }
        else if (strcasecmp($nextAction, 'Send questionnaire') === 0)
        {
            $primaryActionURL = CATSUtility::getIndexName() . '?m=nesp&amp;a=confirmQuestionnaire&amp;candidateID=' . (int) $row['candidate_id'] . '&amp;jobOrderID=' . (int) $row['joborder_id'];
        }
        else if ($row['stage_key'] === 'interview_requested')
        {
            $primaryActionURL = CATSUtility::getIndexName() . '?m=nesp&amp;a=scheduleInterview&amp;candidateID=' . (int) $row['candidate_id'] . '&amp;jobOrderID=' . (int) $row['joborder_id'];
        }
        else if (!empty($row['interview_id']) && in_array($row['interview_status_key'], array('scheduled', 'invitation_pending', 'invitation_sent', 'confirmed', 'reschedule_needed')))
        {
            $primaryActionURL = CATSUtility::getIndexName() . '?m=nesp&amp;a=recordInterviewOutcome&amp;interviewID=' . (int) $row['interview_id'];
        }

        $statusLabels = self::getManualInterviewStatusLabels();

        return array(
            'candidate_workflow_id' => (int) $row['candidate_workflow_id'],
            'candidate_id' => (int) $row['candidate_id'],
            'joborder_id' => (int) $row['joborder_id'],
            'interview_id' => isset($row['interview_id']) ? (int) $row['interview_id'] : 0,
            'candidate_name' => $candidateName,
            'can_prepare_questionnaire' => $row['stage_key'] === 'new'
                && self::validateApplicantContactEmail($candidateEmail)['ok'],
            'role_title' => $row['role_title'],
            'stage_name' => $row['stage_name'],
            'stage_key' => $row['stage_key'],
            'waiting_on' => $waitingOn,
            'summary' => $summary,
            'last_activity' => $row['date_modified'],
            'next_action_label' => $nextAction,
            'primary_action_url' => $primaryActionURL,
            'candidate_url' => $candidateURL,
            'job_url' => CATSUtility::getIndexName() . '?m=joborders&amp;a=show&amp;jobOrderID=' . (int) $row['joborder_id'],
            'scheduled_start' => $row['scheduled_start'],
            'scheduled_end' => $row['scheduled_end'],
            'interviewer_name' => $row['interviewer_name'],
            'assigned_interviewer_names' => isset($row['assigned_interviewer_names']) ? trim($row['assigned_interviewer_names']) : '',
            'interview_status_key' => $row['interview_status_key'],
            'interview_status_label' => isset($statusLabels[$row['interview_status_key']]) ? $statusLabels[$row['interview_status_key']] : $row['interview_status_key'],
            'invitation_status_key' => isset($row['invitation_status_key']) ? $row['invitation_status_key'] : '',
            'outcome_key' => isset($row['outcome_key']) ? $row['outcome_key'] : '',
            'scorecard_status_key' => $row['scorecard_status_key'],
            'overall_recommendation' => $row['overall_recommendation']
        );
    }

    private function inferWaitingOn($stageKey)
    {
        if (in_array($stageKey, array('follow_up_needed', 'applicant_clarification_requested', 'phone_screen_pending', 'interview_confirmation_pending')))
        {
            return 'Applicant';
        }
        if (in_array($stageKey, array('interview_scheduled', 'scorecard_pending')))
        {
            return 'Interviewer';
        }
        return 'Craig';
    }

    private function inferNextAction($stageKey)
    {
        $actions = array(
            'new' => 'Review application',
            'needs_review' => 'Make review decision',
            'follow_up_needed' => 'Check follow-up',
            'applicant_clarification_requested' => 'Review applicant reply',
            'phone_screen_pending' => 'Review phone screen status',
            'phone_screen_complete' => 'Review phone screen',
            'interview_requested' => 'Schedule interview',
            'interview_confirmation_pending' => 'Check confirmation',
            'interview_scheduled' => 'Open interview',
            'scorecard_pending' => 'Check scorecard',
            'scorecard_complete' => 'Make decision',
            'offer_review' => 'Review offer',
            'hired' => 'Open record',
            'hold' => 'Open record',
            'not_selected' => 'Open record',
            'withdrawn' => 'Open record',
            'declined' => 'Open record'
        );

        return isset($actions[$stageKey]) ? $actions[$stageKey] : 'Open candidate';
    }

    public function logAuditEvent($actorUserID, $eventType, $entityType, $entityID, $metadata)
    {
        $metadataJSON = json_encode($metadata);
        if ($metadataJSON === false)
        {
            $metadataJSON = '{}';
        }

        $sql = sprintf(
            'INSERT INTO nesp_audit_event
                (actor_user_id, event_type, entity_type, entity_id, ip_address, user_agent, metadata_json, date_created)
             VALUES
                (%s, %s, %s, %s, %s, %s, %s, NOW())',
            $actorUserID === null ? 'NULL' : $this->_db->makeQueryInteger($actorUserID),
            $this->_db->makeQueryString($eventType),
            $this->_db->makeQueryString($entityType),
            $entityID === null ? 'NULL' : $this->_db->makeQueryInteger($entityID),
            $this->_db->makeQueryString(isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : ''),
            $this->_db->makeQueryString(isset($_SERVER['HTTP_USER_AGENT']) ? substr($_SERVER['HTTP_USER_AGENT'], 0, 255) : ''),
            $this->_db->makeQueryString($metadataJSON)
        );

        return $this->_db->query($sql);
    }

    public function interviewerCanReceiveAssignment($interviewerProfileID, $jobOrderID)
    {
        $interviewerProfileID = (int) $interviewerProfileID;
        $jobOrderID = (int) $jobOrderID;
        if ($interviewerProfileID <= 0 || $jobOrderID <= 0)
        {
            return false;
        }
        if ($jobOrderID === 41001)
        {
            return false;
        }

        $availabilityColumn = $this->isColumnInstalled('nesp_interviewer_profile', 'availability_status_key')
            ? "AND ip.availability_status_key = 'open'"
            : '';
        $accountStateColumn = $this->isColumnInstalled('nesp_interviewer_profile', 'account_state_key')
            ? "AND ip.account_state_key = 'active'"
            : '';

        if (!$this->isTableInstalled('nesp_interviewer_job_role'))
        {
            return false;
        }

        $row = $this->_db->getAssoc(
            sprintf(
                'SELECT ip.interviewer_profile_id
                 FROM nesp_interviewer_profile ip
                 INNER JOIN nesp_interviewer_job_role ijr
                    ON ijr.interviewer_profile_id = ip.interviewer_profile_id
                    AND ijr.is_active = 1
                    AND ijr.joborder_id = %s
                 WHERE ip.interviewer_profile_id = %s
                   AND ip.is_active = 1
                   ' . $availabilityColumn . '
                   ' . $accountStateColumn . '
                 LIMIT 1',
                $this->_db->makeQueryInteger($jobOrderID),
                $this->_db->makeQueryInteger($interviewerProfileID)
            )
        );

        return !empty($row);
    }

    private function replaceInterviewerJobRoles($interviewerProfileID, $jobOrderIDs, $actorUserID)
    {
        if (!$this->isTableInstalled('nesp_interviewer_job_role'))
        {
            return false;
        }

        $interviewerProfileID = (int) $interviewerProfileID;
        $allowed = array();
        foreach (self::getInterviewerJobRoleOptions() as $option)
        {
            $allowed[(int) $option['joborder_id']] = $option;
        }

        $this->_db->query(
            sprintf(
                'UPDATE nesp_interviewer_job_role
                 SET is_active = 0,
                     date_modified = NOW()
                 WHERE interviewer_profile_id = %s',
                $this->_db->makeQueryInteger($interviewerProfileID)
            )
        );

        $saved = array();
        foreach ($jobOrderIDs as $jobOrderID)
        {
            $jobOrderID = (int) $jobOrderID;
            if (!isset($allowed[$jobOrderID]))
            {
                continue;
            }
            $saved[] = $jobOrderID;
            $sql = sprintf(
                'INSERT INTO nesp_interviewer_job_role
                    (interviewer_profile_id, joborder_id, role_key, is_active, created_by_user_id, date_created, date_modified)
                 VALUES
                    (%s, %s, %s, 1, %s, NOW(), NOW())
                 ON DUPLICATE KEY UPDATE
                    role_key = VALUES(role_key),
                    is_active = 1,
                    date_modified = NOW()',
                $this->_db->makeQueryInteger($interviewerProfileID),
                $this->_db->makeQueryInteger($jobOrderID),
                $this->_db->makeQueryString($allowed[$jobOrderID]['role_key']),
                $actorUserID === null ? 'NULL' : $this->_db->makeQueryInteger($actorUserID)
            );
            $this->_db->query($sql);
        }

        $this->logAuditEvent(
            $actorUserID,
            'interviewer_job_roles_updated',
            'interviewer_profile',
            $interviewerProfileID,
            array('approved_joborder_ids' => $saved)
        );

        return true;
    }

    private function createOrResetInterviewerUser($interviewerProfileID, $displayName, $email, $temporaryPassword, $active, $actorUserID)
    {
        $interviewerProfileID = (int) $interviewerProfileID;
        $email = trim((string) $email);
        $temporaryPassword = (string) $temporaryPassword;
        if ($interviewerProfileID <= 0 || $email === '' || strlen($temporaryPassword) < 8)
        {
            return false;
        }

        $profile = $this->_db->getAssoc(
            sprintf(
                'SELECT user_id, display_name, email
                 FROM nesp_interviewer_profile
                 WHERE interviewer_profile_id = %s
                 LIMIT 1',
                $this->_db->makeQueryInteger($interviewerProfileID)
            )
        );
        if (empty($profile))
        {
            return false;
        }

        $username = $this->interviewerUsernameFromEmail($email);
        $names = $this->splitDisplayName($displayName === '' ? $profile['display_name'] : $displayName);
        $accessLevel = $active ? ACCESS_LEVEL_READ : ACCESS_LEVEL_DISABLED;
        $interviewerCategory = 'nesp_interviewer';
        $userID = (int) $profile['user_id'];
        if ($userID <= 0)
        {
            $existing = $this->_db->getAssoc(
                sprintf(
                    'SELECT user_id
                     FROM user
                     WHERE user_name = %s OR email = %s
                     ORDER BY user_id ASC
                     LIMIT 1',
                    $this->_db->makeQueryString($username),
                    $this->_db->makeQueryString($email)
                )
            );
            if (!empty($existing))
            {
                return false;
            }
            else
            {
                $this->_db->query(
                    sprintf(
                        'INSERT INTO user
                            (user_name, password, access_level, can_change_password, is_test_user, email, first_name, last_name, categories, can_see_eeo_info)
                         VALUES
                            (%s, %s, %s, 1, 0, %s, %s, %s, %s, 0)',
                        $this->_db->makeQueryString($username),
                        $this->_db->makeQueryString($this->hashTemporaryInterviewerPassword($temporaryPassword)),
                        $this->_db->makeQueryInteger($accessLevel),
                        $this->_db->makeQueryString($email),
                        $this->_db->makeQueryString($names['first_name']),
                        $this->_db->makeQueryString($names['last_name']),
                        $this->_db->makeQueryString($interviewerCategory)
                    )
                );
                $userID = (int) $this->_db->getLastInsertID();
                if ($userID <= 0)
                {
                    return false;
                }
            }
        }
        else
        {
            $linkedUser = $this->_db->getAssoc(
                sprintf(
                    'SELECT access_level
                     FROM user
                     WHERE user_id = %s
                     LIMIT 1',
                    $this->_db->makeQueryInteger($userID)
                )
            );
            if (empty($linkedUser) || (int) $linkedUser['access_level'] >= ACCESS_LEVEL_SA)
            {
                return false;
            }
        }

        $this->_db->query(
            sprintf(
                'UPDATE user
                 SET user_name = %s,
                     email = %s,
                     first_name = %s,
                     last_name = %s,
                     password = %s,
                     access_level = %s,
                     categories = %s,
                     can_change_password = 1
                 WHERE user_id = %s',
                $this->_db->makeQueryString($username),
                $this->_db->makeQueryString($email),
                $this->_db->makeQueryString($names['first_name']),
                $this->_db->makeQueryString($names['last_name']),
                $this->_db->makeQueryString($this->hashTemporaryInterviewerPassword($temporaryPassword)),
                $this->_db->makeQueryInteger($accessLevel),
                $this->_db->makeQueryString($interviewerCategory),
                $this->_db->makeQueryInteger($userID)
            )
        );
        $this->_db->query(
            sprintf(
                'UPDATE nesp_interviewer_profile
                 SET user_id = %s,
                     account_state_key = %s,
                     date_modified = NOW()
                 WHERE interviewer_profile_id = %s',
                $this->_db->makeQueryInteger($userID),
                $this->_db->makeQueryString($active ? 'temporary_password_set' : 'account_prepared'),
                $this->_db->makeQueryInteger($interviewerProfileID)
            )
        );

        $this->logAuditEvent(
            $actorUserID,
            'interviewer_login_prepared',
            'interviewer_profile',
            $interviewerProfileID,
            array('user_id' => $userID, 'access_enabled' => $active ? 1 : 0, 'temporary_password_length' => strlen($temporaryPassword))
        );

        return array(
            'user_id' => $userID,
            'username' => $username,
            'temporary_login_message' => $this->buildTemporaryLoginMessage($displayName, $username, $active),
            'one_time_login_details' => $this->buildOneTimeLoginDetails($displayName, $username, $temporaryPassword, $active)
        );
    }

    private function getInterviewerLoginProfile($interviewerProfileID)
    {
        return $this->_db->getAssoc(sprintf(
            'SELECT
                ip.*,
                u.user_name,
                u.access_level AS linked_access_level,
                u.categories AS linked_categories
             FROM nesp_interviewer_profile ip
             LEFT JOIN user u
                ON u.user_id = ip.user_id
             WHERE ip.interviewer_profile_id = %s
             LIMIT 1',
            $this->_db->makeQueryInteger((int) $interviewerProfileID)
        ));
    }

    private function prepareInterviewerLoginWithPassword($profile, $temporaryPassword, $active, $actorUserID, $auditEvent)
    {
        $temporaryPassword = trim((string) $temporaryPassword);
        if ($temporaryPassword === '')
        {
            $temporaryPassword = $this->generateTemporaryInterviewerPassword();
        }
        $result = $this->createOrResetInterviewerUser(
            (int) $profile['interviewer_profile_id'],
            $profile['display_name'],
            $profile['email'],
            $temporaryPassword,
            false,
            $actorUserID
        );
        if ($result === false)
        {
            return array('ok' => false, 'error' => 'Unable to prepare a safe interviewer login. Check email uniqueness and password length.');
        }
        $this->_db->query(sprintf(
            'UPDATE nesp_interviewer_profile
             SET is_active = 0,
                 account_state_key = "account_prepared",
                 date_modified = NOW()
             WHERE interviewer_profile_id = %s',
            $this->_db->makeQueryInteger((int) $profile['interviewer_profile_id'])
        ));
        $this->logAuditEvent($actorUserID, $auditEvent, 'interviewer_profile', (int) $profile['interviewer_profile_id'], array(
            'user_id' => (int) $result['user_id'],
            'temporary_password_length' => strlen($temporaryPassword)
        ));
        return array(
            'ok' => true,
            'one_time_login_details' => $result['one_time_login_details']
        );
    }

    private function setInterviewerLoginActiveState($profile, $active, $stateKey, $actorUserID, $auditEvent)
    {
        $validation = $this->validateInterviewerLinkedUser($profile, $active);
        if (empty($validation['ok']))
        {
            return $validation;
        }
        if ($active && count($this->getApprovedJobOrderIDsForInterviewer((int) $profile['interviewer_profile_id'])) === 0)
        {
            return array('ok' => false, 'error' => 'Approve at least one job role before activating this interviewer.');
        }

        $accessLevel = $active ? ACCESS_LEVEL_READ : ACCESS_LEVEL_DISABLED;
        $this->_db->query(sprintf(
            'UPDATE user
             SET access_level = %s,
                 categories = "nesp_interviewer"
             WHERE user_id = %s
               AND access_level < %s',
            $this->_db->makeQueryInteger($accessLevel),
            $this->_db->makeQueryInteger((int) $profile['user_id']),
            $this->_db->makeQueryInteger(ACCESS_LEVEL_SA)
        ));
        if ($this->_db->getAffectedRows() !== 1)
        {
            return array('ok' => false, 'error' => 'Unable to update a safe non-admin interviewer account.');
        }
        $this->_db->query(sprintf(
            'UPDATE nesp_interviewer_profile
             SET is_active = %s,
                 account_state_key = %s,
                 date_modified = NOW()
             WHERE interviewer_profile_id = %s',
            $active ? '1' : '0',
            $this->_db->makeQueryString($stateKey),
            $this->_db->makeQueryInteger((int) $profile['interviewer_profile_id'])
        ));
        $this->logAuditEvent($actorUserID, $auditEvent, 'interviewer_profile', (int) $profile['interviewer_profile_id'], array(
            'user_id' => (int) $profile['user_id'],
            'access_level' => $accessLevel,
            'account_state_key' => $stateKey
        ));
        return array('ok' => true);
    }

    private function validateInterviewerLinkedUser($profile, $activating)
    {
        if (empty($profile) || (int) $profile['user_id'] <= 0)
        {
            return array('ok' => false, 'error' => 'Prepare a login before changing interviewer access.');
        }
        $user = $this->_db->getAssoc(sprintf(
            'SELECT user_id, access_level, categories
             FROM user
             WHERE user_id = %s
             LIMIT 1',
            $this->_db->makeQueryInteger((int) $profile['user_id'])
        ));
        if (empty($user))
        {
            return array('ok' => false, 'error' => 'Linked OpenCATS user was not found.');
        }
        if ((int) $user['access_level'] >= ACCESS_LEVEL_SA)
        {
            return array('ok' => false, 'error' => 'Admin, site-admin, and root accounts cannot be used as interviewers.');
        }
        if (trim((string) $user['categories']) !== 'nesp_interviewer')
        {
            return array('ok' => false, 'error' => 'Interviewer accounts must have exactly the nesp_interviewer category.');
        }
        if ($activating && (int) $user['access_level'] > ACCESS_LEVEL_READ)
        {
            return array('ok' => false, 'error' => 'Interviewer access cannot be higher than read-only.');
        }
        return array('ok' => true);
    }

    private function generateTemporaryInterviewerPassword()
    {
        if (function_exists('random_bytes'))
        {
            return substr(strtr(base64_encode(random_bytes(18)), '+/', 'Aa'), 0, 18);
        }
        return substr(hash('sha256', uniqid('', true) . mt_rand()), 0, 18);
    }

    private function syncInterviewerUserAccess($interviewerProfileID, $active, $actorUserID)
    {
        $profile = $this->_db->getAssoc(
            sprintf(
                'SELECT user_id
                 FROM nesp_interviewer_profile
                 WHERE interviewer_profile_id = %s
                 LIMIT 1',
                $this->_db->makeQueryInteger((int) $interviewerProfileID)
            )
        );
        if (empty($profile) || (int) $profile['user_id'] <= 0)
        {
            return false;
        }

        $this->_db->query(
            sprintf(
                'UPDATE user
                 SET access_level = %s,
                     categories = %s
                 WHERE user_id = %s
                   AND access_level < %s',
                $this->_db->makeQueryInteger($active ? ACCESS_LEVEL_READ : ACCESS_LEVEL_DISABLED),
                $this->_db->makeQueryString('nesp_interviewer'),
                $this->_db->makeQueryInteger((int) $profile['user_id']),
                $this->_db->makeQueryInteger(ACCESS_LEVEL_SA)
            )
        );
        $this->logAuditEvent(
            $actorUserID,
            $active ? 'interviewer_login_enabled' : 'interviewer_login_disabled',
            'interviewer_profile',
            (int) $interviewerProfileID,
            array('user_id' => (int) $profile['user_id'])
        );
        return true;
    }

    private function interviewerUsernameFromEmail($email)
    {
        $email = strtolower(trim($email));
        $local = preg_replace('/[^a-z0-9._-]+/', '.', preg_replace('/@.*/', '', $email));
        $local = trim($local, '.-_');
        if ($local === '')
        {
            $local = 'interviewer';
        }
        return substr('nesp.' . $local, 0, 64);
    }

    private function hashTemporaryInterviewerPassword($temporaryPassword)
    {
        return password_hash((string) $temporaryPassword, PASSWORD_DEFAULT);
    }

    private function splitDisplayName($displayName)
    {
        $parts = preg_split('/\s+/', trim((string) $displayName));
        if (!$parts || !count($parts))
        {
            return array('first_name' => 'NESP', 'last_name' => 'Interviewer');
        }
        $first = array_shift($parts);
        $last = count($parts) ? implode(' ', $parts) : 'Interviewer';
        return array(
            'first_name' => substr($first, 0, 40),
            'last_name' => substr($last, 0, 40)
        );
    }

    private function buildTemporaryLoginMessage($displayName, $username, $active)
    {
        return 'Copy-only login details prepared for ' . trim((string) $displayName) . ': username '
            . $username
            . '. Share the temporary password manually and ask the interviewer to change it after first login. Account access is '
            . ($active ? 'enabled.' : 'disabled until Craig activates it.');
    }

    private function buildOneTimeLoginDetails($displayName, $username, $temporaryPassword, $active)
    {
        return array(
            'display_name' => trim((string) $displayName),
            'login_url' => $this->getOpenCATSLoginURL(),
            'username' => $username,
            'temporary_password' => (string) $temporaryPassword,
            'is_active' => $active ? 1 : 0
        );
    }

    private function getOpenCATSLoginURL()
    {
        $baseURL = getenv('NESP_OPENCATS_BASE_URL');
        $baseURL = $baseURL === false ? '' : trim((string) $baseURL);
        if ($baseURL === '')
        {
            $publicBase = getenv('NESP_PUBLIC_BASE_URL');
            $baseURL = $publicBase === false ? '' : trim((string) $publicBase);
        }
        if ($baseURL === '')
        {
            return CATSUtility::getIndexName();
        }
        return rtrim($baseURL, '/') . '/' . CATSUtility::getIndexName();
    }

    private function normalizeInterviewerSettingsOptions($options)
    {
        $states = self::getInterviewerAccountStates();
        $accountState = isset($options['account_state_key']) && isset($states[$options['account_state_key']])
            ? $options['account_state_key']
            : 'profile_created';
        $availabilityStatus = isset($options['availability_status_key']) && $options['availability_status_key'] === 'closed'
            ? 'closed'
            : 'open';

        return array(
            'account_state_key' => $accountState,
            'timezone' => isset($options['timezone']) && trim($options['timezone']) !== '' ? trim($options['timezone']) : 'America/New_York',
            'availability_status_key' => $availabilityStatus,
            'availability_closed_until' => isset($options['availability_closed_until']) ? trim($options['availability_closed_until']) : '',
            'availability_close_reason' => isset($options['availability_close_reason']) ? trim($options['availability_close_reason']) : '',
            'max_interviews_per_day' => isset($options['max_interviews_per_day']) ? max(0, min(20, (int) $options['max_interviews_per_day'])) : 3,
            'max_interviews_per_week' => isset($options['max_interviews_per_week']) ? max(0, min(80, (int) $options['max_interviews_per_week'])) : 12,
            'min_notice_minutes' => isset($options['min_notice_minutes']) ? max(0, min(10080, (int) $options['min_notice_minutes'])) : 1440,
            'default_interview_minutes' => isset($options['default_interview_minutes']) ? max(10, min(180, (int) $options['default_interview_minutes'])) : 30,
            'buffer_minutes' => isset($options['buffer_minutes']) ? max(0, min(120, (int) $options['buffer_minutes'])) : 15,
            'earliest_time' => isset($options['earliest_time']) && self::isValidAvailabilityTime($options['earliest_time']) ? $options['earliest_time'] : '09:00',
            'latest_time' => isset($options['latest_time']) && self::isValidAvailabilityTime($options['latest_time']) ? $options['latest_time'] : '17:00',
            'craig_must_attend' => isset($options['craig_must_attend']) && (int) $options['craig_must_attend'] === 1 ? 1 : 0,
            'may_recommend' => isset($options['may_recommend']) && (int) $options['may_recommend'] === 0 ? 0 : 1,
            'private_admin_notes' => isset($options['private_admin_notes']) ? trim($options['private_admin_notes']) : '',
            'email_warning' => isset($options['email_warning']) ? trim($options['email_warning']) : '',
            'default_zoom_join_url' => isset($options['default_zoom_join_url']) ? trim($options['default_zoom_join_url']) : ''
        );
    }

    private function sqlValueForInterviewerSetting($column, $value)
    {
        if (in_array($column, array('max_interviews_per_day', 'max_interviews_per_week', 'min_notice_minutes', 'default_interview_minutes', 'buffer_minutes', 'craig_must_attend', 'may_recommend')))
        {
            return $this->_db->makeQueryInteger($value);
        }
        if ($column === 'availability_closed_until' && trim($value) === '')
        {
            return 'NULL';
        }

        return $this->_db->makeQueryString($value);
    }

    private function auditSafeInterviewerSettings($row)
    {
        if (empty($row))
        {
            return array();
        }

        $safe = array();
        foreach (array('display_name', 'email', 'role_key', 'is_active', 'account_state_key', 'timezone', 'availability_status_key', 'max_interviews_per_day', 'max_interviews_per_week', 'min_notice_minutes', 'default_interview_minutes', 'buffer_minutes', 'earliest_time', 'latest_time', 'craig_must_attend', 'may_recommend', 'user_id') as $key)
        {
            if (isset($row[$key]))
            {
                $safe[$key] = $row[$key];
            }
        }
        if (isset($row['default_zoom_join_url']))
        {
            $safe['default_zoom_join_url_masked'] = self::maskZoomURLForAudit($row['default_zoom_join_url']);
        }

        return $safe;
    }

    private function maskedInterviewerZoomLink($row)
    {
        if (empty($row) || !isset($row['default_zoom_join_url']))
        {
            return '';
        }

        return self::maskZoomURLForAudit($row['default_zoom_join_url']);
    }

    private function selectOptionalColumn($table, $alias, $column, $fallbackSQL)
    {
        return $this->isColumnInstalled($table, $column)
            ? $alias . '.' . $column
            : $fallbackSQL;
    }

    private function isTableInstalled($table)
    {
        $tableExists = $this->_db->getAssoc(
            sprintf("SHOW TABLES LIKE %s", $this->_db->makeQueryString($table))
        );

        return !empty($tableExists);
    }

    private function isColumnInstalled($table, $column)
    {
        $columnExists = $this->_db->getAssoc(
            sprintf(
                "SHOW COLUMNS FROM %s LIKE %s",
                $table,
                $this->_db->makeQueryString($column)
            )
        );

        return !empty($columnExists);
    }

    private function countRows($sql)
    {
        $rs = $this->_db->getAssoc($sql);
        if (empty($rs) || !isset($rs['total']))
        {
            return 0;
        }

        return (int) $rs['total'];
    }

    private static function emptyFallStaffingDryRun()
    {
        return array(
            'source_label' => '',
            'source_summary' => array(
                'total_tabs' => 0,
                'tabs' => array(),
                'tabs_with_jobs' => array(),
                'tabs_with_assignments' => array(),
                'years_found' => array(),
                'prior_fall_years_present' => false,
                'requires_additional_historical_workbooks' => true
            ),
            'quality' => array(
                'total_source_rows' => 0,
                'recognized_job_rows' => 0,
                'normalized_role_rows' => 0,
                'skipped_blank_rows' => 0,
                'skipped_header_rows' => 0,
                'skipped_date_rows' => 0,
                'skipped_separator_rows' => 0,
                'skipped_non_schedule_tabs' => 0,
                'rows_missing_dates' => 0,
                'rows_missing_location' => 0,
                'rows_missing_start_or_end' => 0,
                'invalid_staffing_rows' => 0,
                'conflicting_assigned_vs_required_rows' => 0,
                'duplicate_rows' => 0,
                'ambiguous_rows' => 0,
                'rows_with_assignments' => 0,
                'records_by_year' => array(),
                'issue_count' => 0
            ),
            'tab_summaries' => array()
        );
    }

    private static function buildNormalizedStaffingDryRun($rows, $issues, $sourceLabel, $sourceType)
    {
        $dryRun = self::emptyFallStaffingDryRun();
        $sourceName = $sourceType === '' ? 'CSV' : $sourceType;
        $dryRun['source_label'] = $sourceLabel;
        $dryRun['source_summary']['total_tabs'] = 1;
        $dryRun['source_summary']['tabs'] = array($sourceName);
        $dryRun['source_summary']['tabs_with_jobs'] = empty($rows) ? array() : array($sourceName);

        $groups = array();
        foreach ($rows as $row)
        {
            $groupKey = self::staffingReviewRowKey($row);
            if (!isset($groups[$groupKey]))
            {
                $groups[$groupKey] = array(
                    'has_issue' => false,
                    'year' => '',
                    'missing_date' => false,
                    'missing_location' => false,
                    'missing_start_or_end' => false
                );
            }

            if (!empty($row['event_date']))
            {
                $year = substr($row['event_date'], 0, 4);
                $groups[$groupKey]['year'] = $year;
                $dryRun['source_summary']['years_found'][$year] = true;
                if (!isset($dryRun['quality']['records_by_year'][$year]))
                {
                    $dryRun['quality']['records_by_year'][$year] = 0;
                }
            }
            else
            {
                $groups[$groupKey]['missing_date'] = true;
            }

            $unresolved = array();
            if (isset($row['unresolved_json']) && $row['unresolved_json'] !== '')
            {
                $decoded = json_decode($row['unresolved_json'], true);
                if (is_array($decoded))
                {
                    $unresolved = $decoded;
                }
            }
            $location = isset($unresolved['location']) ? trim((string) $unresolved['location']) : '';
            if ($location === '')
            {
                $groups[$groupKey]['missing_location'] = true;
            }
            if (empty($row['event_start_time']) || empty($row['event_end_time']))
            {
                $groups[$groupKey]['missing_start_or_end'] = true;
            }
            if ((int) $row['issue_count'] > 0)
            {
                $groups[$groupKey]['has_issue'] = true;
            }
        }

        foreach ($groups as $group)
        {
            $dryRun['quality']['recognized_job_rows']++;
            $dryRun['quality']['total_source_rows']++;
            if ($group['year'] !== '')
            {
                $dryRun['quality']['records_by_year'][$group['year']]++;
            }
            if ($group['has_issue'])
            {
                $dryRun['quality']['ambiguous_rows']++;
            }
            if ($group['missing_date'])
            {
                $dryRun['quality']['rows_missing_dates']++;
            }
            if ($group['missing_location'])
            {
                $dryRun['quality']['rows_missing_location']++;
            }
            if ($group['missing_start_or_end'])
            {
                $dryRun['quality']['rows_missing_start_or_end']++;
            }
        }

        foreach ((array) $issues as $issue)
        {
            if (isset($issue['issue_key']) && $issue['issue_key'] === 'duplicate_source_row')
            {
                $dryRun['quality']['duplicate_rows']++;
            }
            if (isset($issue['issue_key']) && $issue['issue_key'] === 'missing_staff')
            {
                $dryRun['quality']['invalid_staffing_rows']++;
            }
        }

        $dryRun['quality']['normalized_role_rows'] = count($rows);
        $dryRun['quality']['issue_count'] = count($issues);
        $dryRun['source_summary']['years_found'] = array_map('strval', array_keys($dryRun['source_summary']['years_found']));
        sort($dryRun['source_summary']['years_found']);
        $dryRun['source_summary']['prior_fall_years_present'] = count(array_filter(
            $dryRun['source_summary']['years_found'],
            function ($year) {
                return (int) $year < 2026;
            }
        )) > 0;
        $dryRun['source_summary']['requires_additional_historical_workbooks'] = !$dryRun['source_summary']['prior_fall_years_present'];
        $dryRun['tab_summaries'][] = array(
            'tab_name' => $sourceName,
            'source_rows' => count($rows),
            'recognized_job_rows' => count($groups),
            'staffing_rows' => count($rows),
            'assignment_rows' => 0,
            'ambiguous_rows' => $dryRun['quality']['ambiguous_rows'],
            'years_found' => $dryRun['source_summary']['years_found']
        );

        return $dryRun;
    }

    private static function readXLSXWorkbookSheets($zip)
    {
        $sharedStrings = self::readXLSXSharedStrings($zip);
        $sheetTargets = self::readXLSXSheetTargets($zip);
        if (empty($sheetTargets))
        {
            $sheetTargets = array('Sheet1' => 'xl/worksheets/sheet1.xml');
        }

        $sheets = array();
        foreach ($sheetTargets as $sheetName => $target)
        {
            $target = ltrim($target, '/');
            if (strpos($target, 'xl/') !== 0)
            {
                $target = 'xl/' . $target;
            }

            $sheetXML = $zip->getFromName($target);
            if ($sheetXML === false)
            {
                continue;
            }

            $xml = @simplexml_load_string($sheetXML);
            if ($xml === false)
            {
                continue;
            }

            $rows = array();
            foreach ($xml->sheetData->row as $row)
            {
                $values = array();
                foreach ($row->c as $cell)
                {
                    $reference = (string) $cell['r'];
                    $columnIndex = self::xlsxColumnIndexFromReference($reference);
                    if ($columnIndex < 1)
                    {
                        $columnIndex = count($values) + 1;
                    }
                    $values[$columnIndex - 1] = self::normalizeFallXLSXCellValue(
                        self::xlsxCellValue($cell, $sharedStrings),
                        $columnIndex
                    );
                }

                if (!empty($values))
                {
                    ksort($values);
                    $max = max(array_keys($values));
                    for ($i = 0; $i <= $max; $i++)
                    {
                        if (!isset($values[$i]))
                        {
                            $values[$i] = '';
                        }
                    }
                    ksort($values);
                    $rows[] = array_values($values);
                }
            }

            $sheets[$sheetName] = $rows;
        }

        return $sheets;
    }

    private static function readXLSXSharedStrings($zip)
    {
        $strings = array();
        $sharedXML = $zip->getFromName('xl/sharedStrings.xml');
        if ($sharedXML === false)
        {
            return $strings;
        }

        $xml = @simplexml_load_string($sharedXML);
        if ($xml === false)
        {
            return $strings;
        }

        foreach ($xml->si as $stringItem)
        {
            $parts = array();
            if (isset($stringItem->t))
            {
                $parts[] = (string) $stringItem->t;
            }
            foreach ($stringItem->r as $run)
            {
                if (isset($run->t))
                {
                    $parts[] = (string) $run->t;
                }
            }
            $strings[] = implode('', $parts);
        }

        return $strings;
    }

    private static function readXLSXSheetTargets($zip)
    {
        $workbookXML = $zip->getFromName('xl/workbook.xml');
        $relsXML = $zip->getFromName('xl/_rels/workbook.xml.rels');
        if ($workbookXML === false || $relsXML === false)
        {
            return array();
        }

        $workbook = @simplexml_load_string($workbookXML);
        $rels = @simplexml_load_string($relsXML);
        if ($workbook === false || $rels === false)
        {
            return array();
        }

        $targetsByID = array();
        foreach ($rels->Relationship as $relationship)
        {
            $targetsByID[(string) $relationship['Id']] = (string) $relationship['Target'];
        }

        $sheets = array();
        foreach ($workbook->sheets->sheet as $sheet)
        {
            $attributes = $sheet->attributes('http://schemas.openxmlformats.org/officeDocument/2006/relationships');
            $relationshipID = isset($attributes['id']) ? (string) $attributes['id'] : '';
            if ($relationshipID !== '' && isset($targetsByID[$relationshipID]))
            {
                $sheets[(string) $sheet['name']] = $targetsByID[$relationshipID];
            }
        }

        return $sheets;
    }

    private static function xlsxCellValue($cell, $sharedStrings)
    {
        $type = (string) $cell['t'];
        if ($type === 'inlineStr')
        {
            return isset($cell->is->t) ? (string) $cell->is->t : '';
        }

        $value = isset($cell->v) ? (string) $cell->v : '';
        if ($type === 's')
        {
            $index = (int) $value;
            return isset($sharedStrings[$index]) ? $sharedStrings[$index] : '';
        }
        if ($type === 'b')
        {
            return $value === '1' ? 'TRUE' : 'FALSE';
        }

        return $value;
    }

    private static function xlsxColumnIndexFromReference($reference)
    {
        if (!preg_match('/^([A-Z]+)/i', $reference, $matches))
        {
            return 0;
        }

        $letters = strtoupper($matches[1]);
        $index = 0;
        for ($i = 0; $i < strlen($letters); $i++)
        {
            $index = ($index * 26) + (ord($letters[$i]) - 64);
        }

        return $index;
    }

    private static function normalizeFallXLSXCellValue($value, $columnIndex)
    {
        $value = trim((string) $value);
        if ($value === '')
        {
            return '';
        }

        if (is_numeric($value))
        {
            $numeric = (float) $value;
            if ($columnIndex === 1 && $numeric > 20000 && $numeric < 60000)
            {
                return gmdate('Y-m-d', (int) round(($numeric - 25569) * 86400));
            }
            if (in_array($columnIndex, array(28, 29), true) && $numeric >= 0 && $numeric < 1)
            {
                $seconds = (int) round($numeric * 86400);
                return sprintf('%02d:%02d:%02d', floor($seconds / 3600), floor(($seconds % 3600) / 60), $seconds % 60);
            }
        }

        return $value;
    }

    private static function fallScheduleHeaderLooksUsable($headers)
    {
        return in_array('staffing', $headers, true)
            && in_array('in_out', $headers, true)
            && in_array('location', $headers, true)
            && in_array('start', $headers, true)
            && in_array('end', $headers, true);
    }

    private static function fallScheduleRowIsHeader($row)
    {
        $headers = self::normalizeHeader($row);
        return in_array('staffing', $headers, true) && in_array('location', $headers, true);
    }

    private static function fallScheduleRowLooksLikeJob($row)
    {
        $eventName = self::fallScheduleCell($row, 0);
        if ($eventName === '' || self::parseStaffingDate($eventName) !== '')
        {
            return false;
        }

        $markers = array(
            self::fallScheduleCell($row, 3),
            self::fallScheduleCell($row, 4),
            self::fallScheduleCell($row, 5),
            self::fallScheduleCell($row, 26),
            self::fallScheduleCell($row, 27),
            self::fallScheduleCell($row, 28)
        );
        foreach ($markers as $marker)
        {
            if ($marker !== '' && $marker !== '.')
            {
                return true;
            }
        }

        return false;
    }

    private static function fallScheduleCell($row, $index)
    {
        return isset($row[$index]) ? trim((string) $row[$index]) : '';
    }

    private static function fallScheduleRowHasMeaningfulValue($row)
    {
        foreach ($row as $cell)
        {
            $value = trim((string) $cell);
            if ($value !== '' && $value !== '.')
            {
                return true;
            }
        }

        return false;
    }

    private static function parseFallStaffingRequirementText($staffingText)
    {
        $staffingText = strtoupper(trim($staffingText));
        if ($staffingText === '')
        {
            return array();
        }

        $roles = array();
        $parts = preg_split('/\s*\/\s*/', $staffingText);
        foreach ($parts as $part)
        {
            if (!preg_match('/^(\d+)\s*([A-Z]+)/', trim($part), $matches))
            {
                return array();
            }

            $count = (int) $matches[1];
            $roleCode = $matches[2][0];
            switch ($roleCode)
            {
                case 'P':
                    $roleKey = 'photographer';
                    break;
                case 'T':
                    $roleKey = 'table_staff';
                    break;
                case 'A':
                    $roleKey = 'assistant';
                    break;
                case 'L':
                    $roleKey = 'lead';
                    break;
                default:
                    return array();
            }

            if (!isset($roles[$roleKey]))
            {
                $roles[$roleKey] = 0;
            }
            $roles[$roleKey] += $count;
        }

        return $roles;
    }

    private static function countFallScheduleAssignments($row)
    {
        $columnsByRole = array(
            'lead' => array(9),
            'photographer' => array(11, 13, 15, 17, 19),
            'table_staff' => array(21, 23),
            'trainer' => array(25)
        );
        $counts = array('lead' => 0, 'photographer' => 0, 'table_staff' => 0, 'trainer' => 0);
        foreach ($columnsByRole as $role => $columns)
        {
            foreach ($columns as $columnIndex)
            {
                $value = self::fallScheduleCell($row, $columnIndex);
                if ($value !== '' && $value !== '.' && stripos($value, 'no lead') === false)
                {
                    $counts[$role]++;
                }
            }
        }

        return $counts;
    }

    private static function fallScheduleIssueMessage($issueKey, $sheetName, $rowNumber)
    {
        $messages = array(
            'missing_or_malformed_date' => 'No usable date row was found before this job row.',
            'missing_event_name' => 'The job or league name is missing or appears to be a template placeholder.',
            'missing_location' => 'The location cell is empty.',
            'missing_start_or_end_time' => 'Start or end time is missing.',
            'missing_or_invalid_staffing' => 'The STAFFING cell is empty or not in a recognized pattern such as 1P/1T/1A.',
            'assigned_required_conflict' => 'Assigned role columns do not match the required staffing count.',
            'duplicate_source_row' => 'This job row appears to duplicate an earlier source row.'
        );
        $message = isset($messages[$issueKey]) ? $messages[$issueKey] : 'This row requires review.';

        return $sheetName . ' row ' . $rowNumber . ': ' . $message;
    }

    private static function sanitizeStaffingNote($note)
    {
        $note = trim(preg_replace('/\s+/', ' ', $note));
        return substr($note, 0, 500);
    }

    private static function inferStateFromLocation($location)
    {
        if (preg_match('/\b(MA|RI|CT|NH|ME|VT)\b/i', $location, $matches))
        {
            return strtoupper($matches[1]);
        }

        return '';
    }

    private static function inferSportFromEventName($eventName)
    {
        $eventName = strtolower($eventName);
        if (strpos($eventName, 'soccer') !== false || strpos($eventName, 'ysl') !== false)
        {
            return 'Soccer';
        }
        if (strpos($eventName, 'hockey') !== false || strpos($eventName, 'yhl') !== false)
        {
            return 'Hockey';
        }
        if (strpos($eventName, 'football') !== false || strpos($eventName, 'cheer') !== false)
        {
            return 'Football/Cheer';
        }

        return '';
    }

    private static function normalizeHeader($headerRow)
    {
        $headers = array();
        foreach ($headerRow as $header)
        {
            $header = strtolower(trim($header));
            $header = preg_replace('/[^a-z0-9]+/', '_', $header);
            $headers[] = trim($header, '_');
        }

        return $headers;
    }

    private static function findStaffingHeaderRow($rows)
    {
        $limit = min(20, count($rows));
        for ($i = 0; $i < $limit; $i++)
        {
            $header = self::normalizeHeader($rows[$i]);
            $hasDate = in_array('date', $header) || in_array('event_date', $header) || in_array('picture_day', $header);
            $hasStaff = in_array('staff', $header) || in_array('photographers', $header) || in_array('assigned_staff', $header);
            $hasEvent = in_array('event', $header) || in_array('event_name', $header) || in_array('league', $header) || in_array('school', $header);
            $hasDateColumn = false;
            foreach ($rows[$i] as $cell)
            {
                if (self::parseStaffingDate($cell) !== '')
                {
                    $hasDateColumn = true;
                    break;
                }
            }

            if (($hasDate && $hasStaff) || ($hasEvent && $hasDateColumn))
            {
                return $i;
            }
        }

        return 0;
    }

    private static function rowValueMap($headers, $row)
    {
        $mapped = array();
        foreach ($headers as $index => $header)
        {
            if ($header === '')
            {
                continue;
            }
            $mapped[$header] = isset($row[$index]) ? trim($row[$index]) : '';
        }

        return $mapped;
    }

    private static function normalizeStaffingRow($mapped, $rowNumber, $rawText, $sourceLabel)
    {
        $issues = array();
        $date = self::firstMappedValue($mapped, array('date', 'event_date', 'picture_day', 'week'));
        $normalizedDate = self::parseStaffingDate($date);
        if ($normalizedDate === '')
        {
            $issues[] = array(
                'row_number' => $rowNumber,
                'issue_key' => 'missing_or_malformed_date',
                'message' => 'Date could not be normalized without manual review.'
            );
        }

        $staff = self::firstMappedValue($mapped, array('staff', 'photographers', 'photographer', 'assigned_staff', 'names', 'name'));
        $role = self::normalizeStaffingRole(self::firstMappedValue($mapped, array('role', 'staff_role', 'position')));
        $staffNames = self::splitStaffNames($staff);
        if (empty($staffNames))
        {
            $staffNames = array('');
            $issues[] = array(
                'row_number' => $rowNumber,
                'issue_key' => 'missing_staff',
                'message' => 'No staff name or count was found.'
            );
        }

        $startTime = self::parseStaffingTime(self::firstMappedValue($mapped, array('start', 'start_time', 'time')));
        $endTime = self::parseStaffingTime(self::firstMappedValue($mapped, array('end', 'end_time')));
        $staffHours = self::calculateStaffHours($startTime, $endTime, count($staffNames));
        $sourceSheetName = self::firstMappedValue($mapped, array('sheet', 'tab'));
        if ($sourceSheetName === '')
        {
            $sourceSheetName = 'CSV';
        }
        $eventName = self::firstMappedValue($mapped, array('event', 'event_name', 'league', 'school', 'organization'));
        $state = strtoupper(self::firstMappedValue($mapped, array('state', 'st')));
        $location = self::firstMappedValue($mapped, array('location', 'venue', 'field', 'site'));
        if ($location === '')
        {
            $location = $state;
        }
        $unresolved = array(
            'source_label' => $sourceLabel,
            'location' => $location,
            'staffing_text_original' => $staff,
            'source_format' => 'normalized_csv'
        );

        $row = array(
            'source_sheet_name' => $sourceSheetName,
            'source_row_number' => $rowNumber,
            'event_date' => $normalizedDate,
            'event_start_time' => $startTime,
            'event_end_time' => $endTime,
            'state' => $state,
            'sport' => self::firstMappedValue($mapped, array('sport', 'league_sport')),
            'event_name' => $eventName,
            'role_key' => $role,
            'staff_name' => implode('; ', $staffNames),
            'staff_count' => count($staffNames),
            'staff_hours' => $staffHours,
            'raw_source_text' => $rawText,
            'unresolved_json' => json_encode($unresolved),
            'issue_count' => count($issues),
            'status_key' => count($issues) > 0 ? 'needs_review' : 'normalized'
        );
        $row['review_group_key'] = hash('sha256', implode('|', array(
            $sourceLabel,
            $normalizedDate,
            $eventName,
            $state,
            $location,
            $startTime,
            $endTime
        )));
        $row['source_row_hash'] = hash('sha256', $rawText . '|' . $row['event_date'] . '|' . $row['event_name'] . '|' . $row['staff_name']);

        return array('row' => $row, 'issues' => $issues);
    }

    private static function firstMappedValue($mapped, $keys)
    {
        foreach ($keys as $key)
        {
            if (isset($mapped[$key]) && trim($mapped[$key]) !== '')
            {
                return trim($mapped[$key]);
            }
        }

        return '';
    }

    private static function parseStaffingDate($value)
    {
        $value = trim($value);
        if ($value === '')
        {
            return '';
        }

        $timestamp = strtotime($value);
        if ($timestamp === false)
        {
            return '';
        }

        return date('Y-m-d', $timestamp);
    }

    private static function parseStaffingTime($value)
    {
        $value = trim($value);
        if ($value === '')
        {
            return null;
        }

        $timestamp = strtotime($value);
        if ($timestamp === false)
        {
            return null;
        }

        return date('H:i:s', $timestamp);
    }

    private static function normalizeStaffingRole($role)
    {
        $role = strtolower(trim($role));
        if ($role === '')
        {
            return 'photographer';
        }
        if (strpos($role, 'assist') !== false)
        {
            return 'assistant';
        }
        if (strpos($role, 'table') !== false || strpos($role, 'greeter') !== false)
        {
            return 'table_staff';
        }

        return 'photographer';
    }

    private static function splitStaffNames($staff)
    {
        $staff = trim($staff);
        if ($staff === '')
        {
            return array();
        }

        $parts = preg_split('/[;,]+|\s+\+\s+|\s+\/\s+/', $staff);
        $names = array();
        foreach ($parts as $part)
        {
            $part = trim($part);
            if ($part !== '')
            {
                $names[] = $part;
            }
        }

        return $names;
    }

    private static function calculateStaffHours($startTime, $endTime, $staffCount)
    {
        if ((int) $staffCount <= 0)
        {
            return 0.0;
        }

        if ($startTime === null || $endTime === null)
        {
            return 0.0;
        }

        $start = strtotime('2000-01-01 ' . $startTime);
        $end = strtotime('2000-01-01 ' . $endTime);
        if ($start === false || $end === false || $end <= $start)
        {
            return 0.0;
        }

        return round((($end - $start) / 3600) * max(1, (int) $staffCount), 2);
    }

    private static function incrementMetric(&$bucket, $key, $amount)
    {
        if (!isset($bucket[$key]))
        {
            $bucket[$key] = 0;
        }
        $bucket[$key] += $amount;
    }
}

?>
