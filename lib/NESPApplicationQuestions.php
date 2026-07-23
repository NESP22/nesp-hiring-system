<?php

class NESPApplicationQuestions
{
    const FIELD_NAME = 'nesp_prescreen';
    const STATEMENT = 'These questions help the NESP hiring team understand your availability and job-related qualifications. Every application is reviewed by a person.';
    const QUESTIONNAIRE_DESCRIPTION = 'NESP job-related prescreening answers. Human review required; answers do not automatically reject, rank, hire, assign, email, or change applicant status.';

    public static function hasQuestionsForJob($jobOrderID)
    {
        $config = self::getConfig();
        return isset($config[(int) $jobOrderID]);
    }

    public static function removeLegacyCaptchaForJob($content, $jobOrderID)
    {
        if (!self::hasQuestionsForJob($jobOrderID))
        {
            return (string) $content;
        }

        $content = preg_replace_callback(
            '/<tr\b[^>]*>.*?<\/tr>/is',
            function ($matches)
            {
                return strpos($matches[0], '<input-captcha') !== false ? '' : $matches[0];
            },
            (string) $content
        );

        $content = preg_replace(
            '/\s*<label\b[^>]*id=(["\'])captchaLabel\1[^>]*>.*?<\/label>\s*/is',
            '',
            $content
        );

        return str_replace(array('<input-captcha>', '<input-captcha req>'), '', $content);
    }

    public static function requiresLegacyCaptcha($content, $jobOrderID)
    {
        return !self::hasQuestionsForJob($jobOrderID)
            && strpos((string) $content, '<input-captcha req>') !== false;
    }

    public static function getRoleTitle($jobOrderID)
    {
        $config = self::getConfig();
        $jobOrderID = (int) $jobOrderID;

        return isset($config[$jobOrderID]) ? $config[$jobOrderID]['title'] : '';
    }

    public static function getQuestionsForJob($jobOrderID)
    {
        $config = self::getConfig();
        $jobOrderID = (int) $jobOrderID;

        return isset($config[$jobOrderID]) ? $config[$jobOrderID]['questions'] : array();
    }

    public static function renderForJob($jobOrderID, $postData = array())
    {
        $questions = self::getQuestionsForJob($jobOrderID);
        if (empty($questions))
        {
            return '';
        }

        $html = '<section class="nesp-form-panel nesp-prescreen-panel">' . "\n";
        $html .= '<h3>Job-Related Questions</h3>' . "\n";
        $html .= '<p class="nesp-prescreen-note">' . self::escape(self::STATEMENT) . '</p>' . "\n";

        foreach ($questions as $question)
        {
            $fieldName = self::FIELD_NAME . '[' . $question['key'] . ']';
            $fieldID = 'nesp_prescreen_' . $question['key'];
            $value = self::getSubmittedValue($question['key'], $postData);
            $required = !empty($question['required']);
            $requiredAttr = $required ? ' required' : '';
            $requiredText = $required ? ' *' : '';

            $html .= '<div class="nesp-prescreen-field">' . "\n";
            $html .= '<label for="' . self::escape($fieldID) . '">' . self::escape($question['label'] . $requiredText) . '</label>' . "\n";

            if ($question['type'] === 'text')
            {
                $html .= '<textarea name="' . self::escape($fieldName) . '" id="' . self::escape($fieldID)
                    . '" class="inputBoxArea nesp-prescreen-text" maxlength="1000"' . $requiredAttr . '>'
                    . self::escape($value) . '</textarea>' . "\n";
            }
            else
            {
                $options = $question['type'] === 'yesno' ? array('Yes', 'No') : $question['options'];
                $html .= '<select name="' . self::escape($fieldName) . '" id="' . self::escape($fieldID)
                    . '" class="inputBoxNormal nesp-prescreen-select"' . $requiredAttr . '>' . "\n";
                $html .= '<option value="">Select one</option>' . "\n";
                foreach ($options as $option)
                {
                    $selected = ($value === $option) ? ' selected' : '';
                    $html .= '<option value="' . self::escape($option) . '"' . $selected . '>'
                        . self::escape($option) . '</option>' . "\n";
                }
                $html .= '</select>' . "\n";
            }

            $html .= '</div>' . "\n";
        }

        $html .= '</section>' . "\n";

        return $html;
    }

    public static function validatePost($jobOrderID, $postData)
    {
        $errors = array();
        $answers = isset($postData[self::FIELD_NAME]) && is_array($postData[self::FIELD_NAME])
            ? $postData[self::FIELD_NAME]
            : array();

        foreach (self::getQuestionsForJob($jobOrderID) as $question)
        {
            $value = isset($answers[$question['key']]) ? trim((string) $answers[$question['key']]) : '';
            if (!empty($question['required']) && $value === '')
            {
                $errors[] = $question['label'];
                continue;
            }

            if ($value === '')
            {
                continue;
            }

            if ($question['type'] === 'yesno' && !in_array($value, array('Yes', 'No'), true))
            {
                $errors[] = $question['label'];
                continue;
            }

            if ($question['type'] === 'select' && !in_array($value, $question['options'], true))
            {
                $errors[] = $question['label'];
            }
        }

        return $errors;
    }

    public static function extractAnswers($jobOrderID, $postData)
    {
        $answers = array();
        $postedAnswers = isset($postData[self::FIELD_NAME]) && is_array($postData[self::FIELD_NAME])
            ? $postData[self::FIELD_NAME]
            : array();

        foreach (self::getQuestionsForJob($jobOrderID) as $question)
        {
            $value = isset($postedAnswers[$question['key']])
                ? trim(preg_replace('/\s+/', ' ', (string) $postedAnswers[$question['key']]))
                : '';
            if ($value === '' && empty($question['required']))
            {
                continue;
            }

            $answers[] = array(
                'question' => $question['label'],
                'answer' => substr($value, 0, 255)
            );
        }

        return $answers;
    }

    public static function logCandidateAnswers($jobOrderID, $candidateID, $postData)
    {
        $answers = self::extractAnswers($jobOrderID, $postData);
        if (empty($answers))
        {
            return;
        }

        $questionnaire = new Questionnaire();
        $title = 'NESP Prescreen - ' . self::getRoleTitle($jobOrderID);

        foreach ($answers as $answer)
        {
            $questionnaire->log(
                $candidateID,
                $title,
                self::QUESTIONNAIRE_DESCRIPTION,
                $answer['question'],
                $answer['answer']
            );
        }
    }

    private static function getSubmittedValue($key, $postData)
    {
        if (!isset($postData[self::FIELD_NAME]) || !is_array($postData[self::FIELD_NAME]))
        {
            return '';
        }

        return isset($postData[self::FIELD_NAME][$key])
            ? trim((string) $postData[self::FIELD_NAME][$key])
            : '';
    }

    private static function escape($value)
    {
        return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, HTML_ENCODING);
    }

    private static function yesNo($key, $label, $required = true)
    {
        return array(
            'key' => $key,
            'label' => $label,
            'type' => 'yesno',
            'required' => $required
        );
    }

    private static function text($key, $label, $required = false)
    {
        return array(
            'key' => $key,
            'label' => $label,
            'type' => 'text',
            'required' => $required
        );
    }

    private static function select($key, $label, $options, $required = true)
    {
        return array(
            'key' => $key,
            'label' => $label,
            'type' => 'select',
            'required' => $required,
            'options' => $options
        );
    }

    private static function getConfig()
    {
        return array(
            41001 => array(
                'title' => 'Part-Time Customer Service Representative',
                'questions' => array(
                    self::yesNo('methuen_office', 'Are you able to work in person at our Methuen, Massachusetts office?'),
                    self::yesNo('weekday_schedule', 'Are you generally available Monday through Friday for a set daytime schedule, typically 9:00 a.m.-3:00 p.m. or 10:00 a.m.-4:00 p.m.?'),
                    self::select('experience_years', 'How many years of customer-service or administrative experience do you have?', array(
                        'Less than 1 year',
                        '1-2 years',
                        '3-5 years',
                        'More than 5 years'
                    )),
                    self::yesNo('calls_and_email', 'Are you comfortable answering customer phone calls and responding to customer emails?'),
                    self::yesNo('computer_systems', 'Are you comfortable using email, spreadsheets, support systems, and web applications?'),
                    self::text('resolved_issue', 'Briefly describe a customer issue you helped resolve.')
                )
            ),
            41002 => array(
                'title' => 'Weekend Staff Portrait & Team Photographer - Youth Sports',
                'questions' => array(
                    self::yesNo('drivers_license', 'Do you have a valid driver\'s license?'),
                    self::yesNo('transportation', 'Do you have reliable personal transportation?'),
                    self::yesNo('early_weekends', 'Are you available for early-morning weekend assignments during the spring season?'),
                    self::yesNo('travel', 'Are you comfortable traveling to assigned youth-sports locations, commonly about one hour from home and sometimes farther?'),
                    self::yesNo('children_families', 'Are you comfortable working with children and families?'),
                    self::yesNo('background_checks', 'Are you willing and able to pass required background checks and youth-sports screenings?'),
                    self::yesNo('nesp_equipment', 'Are you comfortable learning camera, lighting, and Picture Day procedures using NESP-provided equipment?'),
                    self::text('related_experience', 'Briefly describe any photography, sports, youth-program, event, or customer-service experience.')
                )
            ),
            41003 => array(
                'title' => 'Freelance/Contract Youth Sports Photographer',
                'questions' => array(
                    self::yesNo('camera_body', 'Do you own a professional or advanced camera body suitable for portrait work?'),
                    self::yesNo('external_flash', 'Does your equipment include an external speedlight capable of manual and TTL operation?'),
                    self::yesNo('portrait_lens', 'Do you own a suitable portrait zoom lens covering approximately 24-70mm through 24-120mm full-frame equivalent?'),
                    self::yesNo('manual_settings', 'Are you comfortable adjusting shutter speed, aperture, ISO, and flash settings manually?'),
                    self::yesNo('early_weekends', 'Are you available for early-morning weekend assignments?'),
                    self::yesNo('travel', 'Are you comfortable traveling approximately 60-90 minutes to assignments?'),
                    self::yesNo('transportation', 'Do you have reliable transportation for yourself and your equipment?'),
                    self::text('equipment_list', 'List the camera body, flash, and primary portrait lens you would use.', true),
                    self::text('photo_experience', 'Briefly describe your portrait, event, school, sports, or volume-photography experience.', true)
                )
            ),
            41005 => array(
                'title' => 'Weekend Table Greeter / Field Assistant',
                'questions' => array(
                    self::yesNo('drivers_license', 'Do you have a valid driver\'s license?'),
                    self::yesNo('transportation', 'Do you have reliable personal transportation?'),
                    self::yesNo('early_weekends', 'Are you available for early-morning weekend assignments?'),
                    self::yesNo('outdoors', 'Are you comfortable working outdoors at youth-sports fields and facilities?'),
                    self::yesNo('welcome_families', 'Are you comfortable welcoming families and directing groups of athletes?'),
                    self::yesNo('stand_extended', 'Can you stand for extended periods?'),
                    self::yesNo('lift_25', 'Can you lift up to 25 pounds?'),
                    self::yesNo('background_checks', 'Are you willing and able to pass required background checks?'),
                    self::text('related_experience', 'Briefly describe any customer-service, event, coaching, school, youth-program, or team experience.')
                )
            )
        );
    }
}
