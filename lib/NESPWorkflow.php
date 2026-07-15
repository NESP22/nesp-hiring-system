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

class NESPWorkflow
{
    private $_db;

    public function __construct($db = null)
    {
        $this->_db = ($db === null) ? DatabaseConnection::getInstance() : $db;
    }

    public static function getDefaultFeatureFlags()
    {
        return array(
            array('NESP_WORKFLOW_ENABLED', 'NESP Workflow', 'Craig-reviewed hiring workflow dashboard and task queues.', 0),
            array('NESP_INTERVIEWER_POOL_ENABLED', 'Interviewer Pool', 'Scoped interviewer access to assigned candidates and interviews.', 0),
            array('NESP_PRESCREEN_ENABLED', 'Prescreen Workflow', 'Craig-approved phone-screen workflow status and results.', 0),
            array('NESP_VAPI_ENABLED', 'Vapi Phone Screens', 'Disabled integration flag. No calls are placed by this module.', 0),
            array('NESP_ZOOM_ENABLED', 'Zoom Scheduling', 'Disabled integration flag. No meetings are created by this module.', 0),
            array('NESP_AI_REVIEW_ENABLED', 'AI Candidate Review', 'Disabled integration flag. No model calls are made by this module.', 0),
            array('NESP_STAFFING_FORECAST_ENABLED', 'Staffing Forecast', 'Seasonal staffing forecast screen and internal draft recommendations.', 0),
            array('NESP_STAFFING_DRIVE_IMPORT_ENABLED', 'Staffing Drive Import', 'Google Drive staffing schedule discovery and import controls.', 0)
        );
    }

    public static function getRequiredFeatureFlagKeys()
    {
        return array(
            'NESP_WORKFLOW_ENABLED',
            'NESP_INTERVIEWER_POOL_ENABLED',
            'NESP_PRESCREEN_ENABLED',
            'NESP_VAPI_ENABLED',
            'NESP_ZOOM_ENABLED',
            'NESP_AI_REVIEW_ENABLED',
            'NESP_STAFFING_FORECAST_ENABLED',
            'NESP_STAFFING_DRIVE_IMPORT_ENABLED'
        );
    }

    public static function getDashboardNavigation()
    {
        return array(
            array('key' => 'needsCraig', 'label' => 'Needs Craig', 'action' => 'dashboard'),
            array('key' => 'waiting', 'label' => 'Waiting', 'action' => 'waiting'),
            array('key' => 'interviews', 'label' => 'Interviews', 'action' => 'interviews'),
            array('key' => 'questionnaires', 'label' => 'Questionnaires', 'action' => 'questionnaires'),
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
            array('zoom', 'Zoom Scheduling', 'disabled', 'Disabled in Phase 2. No meetings can be created.'),
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

        if (in_array($action, array('waiting', 'interviews', 'completed', 'auditLog', 'jobAds')))
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
            'createInterviewerRoleRule',
            'createCandidateGrant',
            'createInterviewerAvailability',
            'myAvailability',
            'setInterviewerAvailabilityStatus',
            'createInterviewerAvailabilityOverride',
            'createInterviewerBlackout'
        )))
        {
            return 'NESP_INTERVIEWER_POOL_ENABLED';
        }

        if (in_array($action, array('staffingForecast', 'createStaffingRecommendation')))
        {
            return 'NESP_STAFFING_FORECAST_ENABLED';
        }

        if (in_array($action, array(
            'questionnaires',
            'confirmQuestionnaire',
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

        if (in_array($action, array('settings', 'featureFlags', 'saveFeatureFlags')))
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
        return hash('sha256', (string) $token);
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
            'weekend_sports_photographer' => array(
                'label' => 'Weekend Sports Photographer',
                'match' => array('weekend sports photographer', 'staff photographer', 'freelance photographer', 'sports photographer', 'photographer'),
                'questions' => array(
                    array('key' => 'weekend_availability', 'label' => 'Are you available on Saturdays and Sundays?', 'type' => 'textarea', 'required' => true),
                    array('key' => 'travel_areas', 'label' => 'What towns or areas can you reliably travel to?', 'type' => 'textarea', 'required' => true),
                    array('key' => 'transportation', 'label' => 'Do you have reliable transportation?', 'type' => 'textarea', 'required' => true),
                    array('key' => 'camera_ownership', 'label' => 'Do you own a DSLR or mirrorless camera?', 'type' => 'textarea', 'required' => true),
                    array('key' => 'photography_experience', 'label' => 'Describe your photography experience.', 'type' => 'textarea', 'required' => true),
                    array('key' => 'youth_sports_comfort', 'label' => 'Are you comfortable photographing children and youth sports?', 'type' => 'textarea', 'required' => true),
                    array('key' => 'lifting_ability', 'label' => 'Can you lift and carry approximately 25-40 pounds of equipment?', 'type' => 'textarea', 'required' => true),
                    array('key' => 'start_date', 'label' => 'What is your earliest available start date?', 'type' => 'text', 'required' => true),
                    array('key' => 'pay_expectations', 'label' => 'What hourly pay range are you seeking?', 'type' => 'text', 'required' => true),
                    array('key' => 'interest', 'label' => 'Why are you interested in working with NESP?', 'type' => 'textarea', 'required' => true)
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
            'photography_assistant_poser' => array(
                'label' => 'Photography Assistant / Poser',
                'match' => array('assistant', 'poser', 'field assistant'),
                'questions' => array(
                    array('key' => 'availability', 'label' => 'What weekend and weekday availability do you have?', 'type' => 'textarea', 'required' => true),
                    array('key' => 'children_comfort', 'label' => 'Are you comfortable working with children?', 'type' => 'textarea', 'required' => true),
                    array('key' => 'standing', 'label' => 'Are you able to stand for long periods?', 'type' => 'textarea', 'required' => true),
                    array('key' => 'carry_equipment', 'label' => 'Are you able to carry equipment as needed?', 'type' => 'textarea', 'required' => true),
                    array('key' => 'transportation', 'label' => 'Do you have reliable transportation?', 'type' => 'textarea', 'required' => true),
                    array('key' => 'customer_service', 'label' => 'Describe any customer-service experience.', 'type' => 'textarea', 'required' => true),
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

    public static function validateQuestionnaireAnswers($questions, $answers)
    {
        $clean = array();
        $errors = array();
        foreach ($questions as $question)
        {
            $key = $question['key'];
            $value = isset($answers[$key]) ? trim((string) $answers[$key]) : '';
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
            'deactivated' => 'Deactivated'
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
                'email' => 'brandon@sportsphoto.com',
                'role_group' => 'Table, greeter, and field-support interviews',
                'account_state_key' => 'email_needs_confirmation',
                'is_active' => 0,
                'approved_joborder_ids' => array(41005),
                'email_warning' => 'Please confirm that brandon@sportsphoto.com is the correct email address.'
            ),
            array(
                'display_name' => 'Nate',
                'email' => 'nate@nesportsphoto.com',
                'role_group' => 'All field roles except Customer Service',
                'account_state_key' => 'ready_for_account_creation',
                'is_active' => 0,
                'approved_joborder_ids' => array(41002, 41003, 41005),
                'email_warning' => ''
            )
        );
    }

    public static function findSchedulingConflicts($interviewer, $approvedJobIDs, $availabilityBlocks, $blackouts, $existingInterviews, $jobOrderID, $startTime, $endTime)
    {
        $conflicts = array();
        $jobOrderID = (int) $jobOrderID;
        $start = strtotime($startTime);
        $end = strtotime($endTime);
        if ($start === false || $end === false || $start >= $end)
        {
            return array('Invalid interview time.');
        }

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

        $insideBlock = false;
        $weekday = date('l', $start);
        $candidateStart = date('H:i:s', $start);
        $candidateEnd = date('H:i:s', $end);
        foreach ($availabilityBlocks as $block)
        {
            if ((int) $block['is_active'] !== 1 || $block['weekday_key'] !== $weekday)
            {
                continue;
            }
            if ($candidateStart >= $block['start_time'] && $candidateEnd <= $block['end_time'])
            {
                $insideBlock = true;
                break;
            }
        }
        if (!$insideBlock)
        {
            $conflicts[] = 'Requested time is outside available blocks.';
        }

        foreach ($blackouts as $blackout)
        {
            $blackoutStart = strtotime($blackout['starts_at']);
            $blackoutEnd = strtotime($blackout['ends_at']);
            if ($blackoutStart !== false && $blackoutEnd !== false && $start < $blackoutEnd && $end > $blackoutStart)
            {
                $conflicts[] = 'Requested time overlaps a blackout date.';
                break;
            }
        }

        $bufferMinutes = isset($interviewer['buffer_minutes']) ? (int) $interviewer['buffer_minutes'] : 15;
        foreach ($existingInterviews as $interview)
        {
            $existingStart = strtotime($interview['scheduled_start']) - ($bufferMinutes * 60);
            $existingEnd = strtotime($interview['scheduled_end']) + ($bufferMinutes * 60);
            if ($existingStart !== false && $existingEnd !== false && $start < $existingEnd && $end > $existingStart)
            {
                $conflicts[] = 'Requested time overlaps an existing interview or buffer.';
                break;
            }
        }

        $dailyLimit = isset($interviewer['max_interviews_per_day']) ? (int) $interviewer['max_interviews_per_day'] : 3;
        $weeklyLimit = isset($interviewer['max_interviews_per_week']) ? (int) $interviewer['max_interviews_per_week'] : 12;
        $dayCount = 0;
        $weekCount = 0;
        $targetDay = date('Y-m-d', $start);
        $targetWeek = date('o-W', $start);
        foreach ($existingInterviews as $interview)
        {
            $existingStart = strtotime($interview['scheduled_start']);
            if ($existingStart === false)
            {
                continue;
            }
            if (date('Y-m-d', $existingStart) === $targetDay)
            {
                $dayCount++;
            }
            if (date('o-W', $existingStart) === $targetWeek)
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

    public static function normalizeStaffingRows($rows, $sourceLabel, $sourceType = 'CSV')
    {
        $normalized = array();
        $issues = array();
        if (empty($rows))
        {
            return array(
                'rows' => $normalized,
                'issues' => array(array('row_number' => 0, 'issue_key' => 'empty_source', 'message' => 'No rows were found.')),
                'checksum' => hash('sha256', ''),
                'source_label' => $sourceLabel,
                'source_type' => $sourceType
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
            'source_type' => $sourceType
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
            'average_staff_per_event' => 0,
            'recommended_pool' => 0,
            'recommended_backup' => 0,
            'hiring_gap' => 0,
            'confidence' => 'Low',
            'formulas' => array(
                'recommended_pool' => 'ceil(peak_day_staffing * (1 + buffer_percent / 100))',
                'recommended_backup' => 'ceil(recommended_pool * buffer_percent / 100)',
                'hiring_gap' => 'max(0, recommended_pool + recommended_backup - active_staff - expected_returning_staff - confirmed_available_staff)',
                'confidence' => 'High requires at least 3 usable seasons and no open import issues; Medium requires at least 2 usable seasons.'
            )
        );

        $events = array();
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
            $metrics['staff_hours'] += (float) $row['staff_hours'];
            $metrics['total_staff_assignments'] += max(1, (int) $row['staff_count']);
        }

        foreach ($staffBySeason as $season => $staff)
        {
            $metrics['unique_staff_by_season'][$season] = count($staff);
        }

        $metrics['total_events'] = count($events);
        $metrics['peak_day_staffing'] = empty($dayStaff) ? 0 : max($dayStaff);
        $metrics['peak_concurrent_staff'] = $metrics['peak_day_staffing'];
        $metrics['average_staff_per_event'] = $metrics['total_events'] > 0
            ? round($metrics['total_staff_assignments'] / $metrics['total_events'], 2)
            : 0;
        $metrics['staff_hours'] = round($metrics['staff_hours'], 2);
        $metrics['recommended_pool'] = (int) ceil($metrics['peak_day_staffing'] * (1 + ((float) $config['buffer_percent'] / 100)));
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
        return $this->_db->getAllAssoc(
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
                flag_key IN ("NESP_WORKFLOW_ENABLED", "NESP_INTERVIEWER_POOL_ENABLED", "NESP_PRESCREEN_ENABLED", "NESP_VAPI_ENABLED", "NESP_ZOOM_ENABLED", "NESP_AI_REVIEW_ENABLED", "NESP_STAFFING_FORECAST_ENABLED", "NESP_STAFFING_DRIVE_IMPORT_ENABLED")
            ORDER BY
                display_name'
        );
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
        return $this->_db->getAllAssoc(
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
                "SELECT COUNT(*) AS total FROM nesp_interview WHERE status_key = 'scheduled'"
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
                   AND status_key IN ("scheduled", "confirmed", "needs_notes")'
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
                && in_array($row['interview_status_key'], array('scheduled', 'confirmed', 'needs_notes'))
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
                    jo.title AS role_title,
                    ws.stage_key,
                    ws.display_name AS stage_name,
                    i.interview_id,
                    i.scheduled_start,
                    i.scheduled_end,
                    i.status_key AS interview_status_key,
                    ip.display_name AS interviewer_name,
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

        return $this->_db->getAllAssoc(
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
                    i.status_key
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
                    AND i.status_key IN ("scheduled", "confirmed", "needs_notes")
                ORDER BY
                    i.scheduled_start ASC
                LIMIT %s',
                $this->_db->makeQueryInteger($limit)
            )
        );
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
            'default_interview_minutes' => '30',
            'buffer_minutes' => '15',
            'earliest_time' => '"09:00:00"',
            'latest_time' => '"17:00:00"',
            'craig_must_attend' => '0',
            'may_recommend' => '1',
            'private_admin_notes' => '""',
            'last_login_at' => 'NULL',
            'email_warning' => '""'
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

        return $this->_db->getAllAssoc(
            'SELECT
                ' . implode(",\n                ", $profileSelect) . ',
                COUNT(DISTINCT cg.grant_id) AS active_grants
                , ' . $jobRoleSelect . '
             FROM
                nesp_interviewer_profile ip
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

    public function createCandidateGrant($interviewerProfileID, $candidateID, $jobOrderID, $actorUserID)
    {
        $interviewerProfileID = (int) $interviewerProfileID;
        $candidateID = (int) $candidateID;
        $jobOrderID = (int) $jobOrderID;

        if ($interviewerProfileID <= 0 || $candidateID <= 0 || $jobOrderID <= 0)
        {
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
                COUNT(DISTINCT CASE WHEN i.status_key IN ("scheduled", "confirmed", "needs_notes") THEN i.interview_id END) AS open_interviews,
                COUNT(DISTINCT CASE WHEN DATE(i.scheduled_start) = CURDATE() AND i.status_key IN ("scheduled", "confirmed", "needs_notes") THEN i.interview_id END) AS interviews_today,
                COUNT(DISTINCT CASE WHEN i.scheduled_start >= CURDATE() AND i.scheduled_start < DATE_ADD(CURDATE(), INTERVAL 7 DAY) AND i.status_key IN ("scheduled", "confirmed", "needs_notes") THEN i.interview_id END) AS interviews_this_week,
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
            $sets[] = 'email = ' . $this->_db->makeQueryString(trim($settings['email']));
        }
        if (isset($settings['role_key']))
        {
            $sets[] = 'role_key = ' . $this->_db->makeQueryString(trim($settings['role_key']));
        }
        if (isset($settings['is_active']))
        {
            $sets[] = 'is_active = ' . (((int) $settings['is_active']) === 1 ? '1' : '0');
        }
        if (isset($settings['user_id']))
        {
            $userID = (int) $settings['user_id'];
            $sets[] = 'user_id = ' . ($userID > 0 ? $this->_db->makeQueryInteger($userID) : 'NULL');
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

        $accountResult = array();
        if (!empty($settings['temporary_password']))
        {
            $accountResult = $this->createOrResetInterviewerUser(
                $interviewerProfileID,
                isset($settings['display_name']) ? $settings['display_name'] : $before['display_name'],
                isset($settings['email']) ? $settings['email'] : $before['email'],
                $settings['temporary_password'],
                !empty($settings['is_active']),
                $actorUserID
            );
            if ($accountResult === false)
            {
                return false;
            }
        }
        elseif (isset($settings['is_active']))
        {
            $this->syncInterviewerUserAccess($interviewerProfileID, !empty($settings['is_active']), $actorUserID);
        }

        $this->logAuditEvent(
            $actorUserID,
            'interviewer_settings_updated',
            'interviewer_profile',
            $interviewerProfileID,
            array(
                'old' => $this->auditSafeInterviewerSettings($before),
                'new' => $this->auditSafeInterviewerSettings($this->_db->getAssoc(sprintf(
                    'SELECT * FROM nesp_interviewer_profile WHERE interviewer_profile_id = %s LIMIT 1',
                    $this->_db->makeQueryInteger($interviewerProfileID)
                )))
            )
        );

        return array(
            'ok' => true,
            'temporary_login_message' => isset($accountResult['temporary_login_message']) ? $accountResult['temporary_login_message'] : ''
        );
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

    public function createInterviewerAvailabilityOverride($interviewerProfileID, $overrideDate, $overrideTypeKey, $startTime, $endTime, $timezone, $privateReason, $actorUserID)
    {
        if (!$this->isTableInstalled('nesp_interviewer_availability_override'))
        {
            return false;
        }
        $interviewerProfileID = (int) $interviewerProfileID;
        $overrideDate = trim($overrideDate);
        $overrideTypeKey = in_array($overrideTypeKey, array('available', 'available_all_day', 'unavailable')) ? $overrideTypeKey : 'available';
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

        $row['candidate_name'] = trim($row['first_name'] . ' ' . $row['last_name']);
        $set = self::getQuestionnaireSetForRole($row['title']);
        $row['question_set_key'] = $set['key'];
        $row['question_set_label'] = $set['label'];
        $row['questions'] = self::getQuestionnaireQuestionsForSet($set['key']);
        $row['estimated_minutes'] = '5-10 minutes';
        return $row;
    }

    public function requestQuestionnaire($candidateID, $jobOrderID, $actorUserID)
    {
        $preview = $this->getCandidateQuestionnairePreview($candidateID, $jobOrderID);
        if (empty($preview))
        {
            return false;
        }

        $existing = $this->_db->getAssoc(
            sprintf(
                'SELECT screening_questionnaire_id
                 FROM nesp_screening_questionnaire
                 WHERE candidate_id = %s
                   AND joborder_id = %s
                   AND status_key IN ("link_ready", "waiting", "in_progress", "completed", "human_follow_up_requested")
                 ORDER BY screening_questionnaire_id DESC
                 LIMIT 1',
                $this->_db->makeQueryInteger($candidateID),
                $this->_db->makeQueryInteger($jobOrderID)
            )
        );
        if (!empty($existing))
        {
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

        $this->_db->query(
            sprintf(
                'INSERT INTO nesp_screening_questionnaire
                    (candidate_id, joborder_id, status_key, question_set_key, question_set_version, token_hash, token_expires_at, link_created_at, requested_by_user_id, review_status_key, date_created, date_modified)
                 VALUES
                    (%s, %s, "link_ready", %s, 1, %s, DATE_ADD(UTC_TIMESTAMP(), INTERVAL %s HOUR), UTC_TIMESTAMP(), %s, "not_started", NOW(), NOW())',
                $this->_db->makeQueryInteger($candidateID),
                $this->_db->makeQueryInteger($jobOrderID),
                $this->_db->makeQueryString($preview['question_set_key']),
                $this->_db->makeQueryString($tokenHash),
                $this->_db->makeQueryInteger(self::getQuestionnaireDefaultExpirationHours()),
                $actorUserID === null ? 'NULL' : $this->_db->makeQueryInteger($actorUserID)
            )
        );

        $questionnaireID = (int) $this->_db->getLastInsertID();
        $this->logQuestionnaireActivity($questionnaireID, $tokenHash, 'link_created', array('expires_at_hours' => self::getQuestionnaireDefaultExpirationHours()));
        $this->logAuditEvent($actorUserID, 'screening_questionnaire_link_created', 'screening_questionnaire', $questionnaireID, array('candidate_id' => (int) $candidateID, 'joborder_id' => (int) $jobOrderID, 'question_set_key' => $preview['question_set_key']));

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
            'human_follow_up' => array()
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
        $detail['questions'] = self::getQuestionnaireQuestionsForSet($detail['question_set_key']);
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
        $row['questions'] = self::getQuestionnaireQuestionsForSet($row['question_set_key']);
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
        $questions = self::getQuestionnaireQuestionsForSet($questionnaire['question_set_key']);
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

        return array(
            'candidate_id' => (int) $row['candidate_id'],
            'joborder_id' => (int) $row['joborder_id'],
            'candidate_name' => $candidateName,
            'role_title' => $row['role_title'],
            'stage_name' => $row['stage_name'],
            'stage_key' => $row['stage_key'],
            'waiting_on' => $waitingOn,
            'summary' => $summary,
            'last_activity' => $row['date_modified'],
            'next_action_label' => $nextAction,
            'candidate_url' => CATSUtility::getIndexName() . '?m=candidates&amp;a=show&amp;candidateID=' . (int) $row['candidate_id'],
            'job_url' => CATSUtility::getIndexName() . '?m=joborders&amp;a=show&amp;jobOrderID=' . (int) $row['joborder_id'],
            'scheduled_start' => $row['scheduled_start'],
            'scheduled_end' => $row['scheduled_end'],
            'interviewer_name' => $row['interviewer_name'],
            'interview_status_key' => $row['interview_status_key'],
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
            'interview_requested' => 'Assign interviewer',
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

        $this->_db->query($sql);
    }

    public function interviewerCanReceiveAssignment($interviewerProfileID, $jobOrderID)
    {
        $interviewerProfileID = (int) $interviewerProfileID;
        $jobOrderID = (int) $jobOrderID;
        if ($interviewerProfileID <= 0 || $jobOrderID <= 0)
        {
            return false;
        }

        $availabilityColumn = $this->isColumnInstalled('nesp_interviewer_profile', 'availability_status_key')
            ? "AND ip.availability_status_key = 'open'"
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
            'temporary_login_message' => $this->buildTemporaryLoginMessage($displayName, $username, $active)
        );
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
            'default_interview_minutes' => isset($options['default_interview_minutes']) ? max(10, min(180, (int) $options['default_interview_minutes'])) : 30,
            'buffer_minutes' => isset($options['buffer_minutes']) ? max(0, min(120, (int) $options['buffer_minutes'])) : 15,
            'earliest_time' => isset($options['earliest_time']) && self::isValidAvailabilityTime($options['earliest_time']) ? $options['earliest_time'] : '09:00',
            'latest_time' => isset($options['latest_time']) && self::isValidAvailabilityTime($options['latest_time']) ? $options['latest_time'] : '17:00',
            'craig_must_attend' => isset($options['craig_must_attend']) && (int) $options['craig_must_attend'] === 1 ? 1 : 0,
            'may_recommend' => isset($options['may_recommend']) && (int) $options['may_recommend'] === 0 ? 0 : 1,
            'private_admin_notes' => isset($options['private_admin_notes']) ? trim($options['private_admin_notes']) : '',
            'email_warning' => isset($options['email_warning']) ? trim($options['email_warning']) : ''
        );
    }

    private function sqlValueForInterviewerSetting($column, $value)
    {
        if (in_array($column, array('max_interviews_per_day', 'max_interviews_per_week', 'default_interview_minutes', 'buffer_minutes', 'craig_must_attend', 'may_recommend')))
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
        foreach (array('display_name', 'email', 'role_key', 'is_active', 'account_state_key', 'timezone', 'availability_status_key', 'max_interviews_per_day', 'max_interviews_per_week', 'default_interview_minutes', 'buffer_minutes', 'earliest_time', 'latest_time', 'craig_must_attend', 'may_recommend', 'user_id') as $key)
        {
            if (isset($row[$key]))
            {
                $safe[$key] = $row[$key];
            }
        }

        return $safe;
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
        $row = array(
            'source_sheet_name' => self::firstMappedValue($mapped, array('sheet', 'tab')),
            'source_row_number' => $rowNumber,
            'event_date' => $normalizedDate,
            'event_start_time' => $startTime,
            'event_end_time' => $endTime,
            'state' => strtoupper(self::firstMappedValue($mapped, array('state', 'st'))),
            'sport' => self::firstMappedValue($mapped, array('sport', 'league_sport')),
            'event_name' => self::firstMappedValue($mapped, array('event', 'event_name', 'league', 'school', 'organization')),
            'role_key' => $role,
            'staff_name' => implode('; ', $staffNames),
            'staff_count' => count($staffNames),
            'staff_hours' => $staffHours,
            'raw_source_text' => $rawText,
            'unresolved_json' => json_encode(array('source_label' => $sourceLabel)),
            'issue_count' => count($issues),
            'status_key' => count($issues) > 0 ? 'needs_review' : 'normalized'
        );
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
