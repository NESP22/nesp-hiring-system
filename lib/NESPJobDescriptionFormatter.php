<?php

/**
 * Shared formatter for the NESP public job page and external job feeds.
 */
class NESPJobDescriptionFormatter
{
    public static function formatHTML($description, $jobOrderID)
    {
        if (!self::isNESPJobOrderID($jobOrderID))
        {
            return nl2br(self::escape($description));
        }

        $tokens = self::polishTokens(
            $jobOrderID,
            self::polishOpening($jobOrderID, self::tokenize($description))
        );
        if (empty($tokens))
        {
            return '';
        }

        $html = '<div class="nesp-description-content">';
        $sectionOpen = false;
        $listOpen = false;

        foreach ($tokens as $token)
        {
            if ($token['type'] === 'heading')
            {
                if ($listOpen)
                {
                    $html .= '</ul>';
                    $listOpen = false;
                }
                if ($sectionOpen)
                {
                    $html .= '</section>';
                }

                $sectionClass = strcasecmp($token['text'], 'Apply Now') === 0
                    ? ' nesp-description-apply'
                    : '';
                $html .= '<section class="nesp-description-section' . $sectionClass . '">';
                $html .= '<h3>' . self::escape($token['text']) . '</h3>';
                $sectionOpen = true;
                continue;
            }

            if (!$sectionOpen)
            {
                $html .= '<p class="nesp-description-intro">' . self::escape($token['text']) . '</p>';
                continue;
            }

            if ($token['type'] === 'bullet')
            {
                if (!$listOpen)
                {
                    $html .= '<ul>';
                    $listOpen = true;
                }
                $html .= '<li>' . self::escape($token['text']) . '</li>';
                continue;
            }

            if ($listOpen)
            {
                $html .= '</ul>';
                $listOpen = false;
            }
            $html .= '<p>' . self::escape($token['text']) . '</p>';
        }

        if ($listOpen)
        {
            $html .= '</ul>';
        }
        if ($sectionOpen)
        {
            $html .= '</section>';
        }

        return $html . '</div>';
    }

    public static function formatIndeed($description, $jobOrderID)
    {
        $tokens = self::polishTokens(
            $jobOrderID,
            self::polishOpening($jobOrderID, self::tokenize($description))
        );
        $html = '';
        if (self::isNESPJobOrderID($jobOrderID))
        {
            $html = '<p><strong>Availability:</strong> ' . self::escape(self::getAvailabilityLine($jobOrderID)) . '</p>';
        }
        $listOpen = false;

        foreach ($tokens as $token)
        {
            if ($token['type'] === 'heading')
            {
                if ($listOpen)
                {
                    $html .= '</ul>';
                    $listOpen = false;
                }
                $html .= '<h3>' . self::escape($token['text']) . '</h3>';
                continue;
            }

            if ($token['type'] === 'bullet')
            {
                if (!$listOpen)
                {
                    $html .= '<ul>';
                    $listOpen = true;
                }
                $html .= '<li>' . self::escape($token['text']) . '</li>';
                continue;
            }

            if ($listOpen)
            {
                $html .= '</ul>';
                $listOpen = false;
            }
            $html .= '<p>' . self::escape($token['text']) . '</p>';
        }

        if ($listOpen)
        {
            $html .= '</ul>';
        }

        return $html;
    }

    private static function getAvailabilityLine($jobOrderID)
    {
        switch ((int) $jobOrderID)
        {
            case 41001:
                return 'Year-round, approximately 20-30 hours per week.';

            case 41002:
            case 41005:
                return 'Part-time seasonal work is generally available September-November and April-June.';

            case 41003:
                return 'Part-time seasonal contract assignments are generally available September-November and April-June.';

            default:
                return 'See the schedule and season details below.';
        }
    }

    private static function tokenize($description)
    {
        $description = trim(str_replace(array("\r\n", "\r"), "\n", (string) $description));
        $lines = preg_split('/\n/', $description);
        $tokens = array();
        $skippingFacts = false;

        foreach ($lines as $line)
        {
            $line = trim($line);
            if ($line === '')
            {
                continue;
            }

            if (strcasecmp($line, 'Quick Facts') === 0)
            {
                $skippingFacts = true;
                continue;
            }

            if ($skippingFacts && preg_match('/^(Pay|Location|Territory|Work location|Employment type|Typical schedule|Season|Availability):/i', $line))
            {
                continue;
            }

            $skippingFacts = false;
            if (self::isHeading($line))
            {
                $tokens[] = array('type' => 'heading', 'text' => $line);
                continue;
            }

            if (strpos($line, '- ') === 0)
            {
                $tokens[] = array('type' => 'bullet', 'text' => substr($line, 2));
                continue;
            }

            $tokens[] = array('type' => 'paragraph', 'text' => $line);
        }

        return $tokens;
    }

    private static function polishOpening($jobOrderID, $tokens)
    {
        $opening = self::getOpeningParagraphs($jobOrderID);
        if (empty($opening))
        {
            return $tokens;
        }

        $leadingParagraphCount = 0;
        foreach ($tokens as $token)
        {
            if ($token['type'] !== 'paragraph')
            {
                break;
            }
            $leadingParagraphCount++;
        }

        if ($leadingParagraphCount === 0)
        {
            return $tokens;
        }

        $polished = array();
        foreach ($opening as $paragraph)
        {
            $polished[] = array('type' => 'paragraph', 'text' => $paragraph);
        }

        return array_merge($polished, array_slice($tokens, $leadingParagraphCount));
    }

    private static function polishTokens($jobOrderID, $tokens)
    {
        if ((int) $jobOrderID !== 41001)
        {
            return $tokens;
        }

        foreach ($tokens as &$token)
        {
            if ($token['type'] === 'bullet' && stripos($token['text'], 'spring and fall seasons') !== false)
            {
                $token['text'] = 'Year-round weekday schedule with approximately 20-30 hours per week';
            }

            if ($token['type'] === 'paragraph' && stripos($token['text'], 'daytime weekday work during peak seasons') !== false)
            {
                $token['text'] = str_ireplace(
                    'daytime weekday work during peak seasons',
                    'daytime weekday work throughout the year',
                    $token['text']
                );
            }
        }
        unset($token);

        return $tokens;
    }

    private static function getOpeningParagraphs($jobOrderID)
    {
        switch ((int) $jobOrderID)
        {
            case 41001:
                return array(
                    'New England Sports Photo is hiring a part-time Customer Service Representative for our Methuen office. You will become a trusted point of contact for parents, families, league coordinators, and school partners, turning order, delivery, account, reprint, and support questions into clear next steps.',
                    'This is a steady, year-round opportunity for someone who enjoys practical problem-solving, clear communication, and helping people feel taken care of.'
                );

            case 41002:
                return array(
                    'We are hiring dependable weekend photographers to create polished individual portraits and team photographs at youth-sports Picture Day events. NESP provides the equipment, training, and workflow, so photography experience is helpful but not required.',
                    'If you enjoy active community events and want seasonal work with a repeatable process and opportunities to return, this role is designed to help you succeed.'
                );

            case 41003:
                return array(
                    'New England Sports Photo is seeking experienced freelance photographers for recurring youth-sports Picture Day assignments. This role is a strong fit for photographers who own approved professional equipment, understand camera and flash settings, and want well-organized seasonal work with clear expectations.',
                    'You will bring your craft and equipment; NESP provides the assignment details, event process, and support needed to work efficiently and professionally with families and leagues.'
                );

            case 41005:
                return array(
                    'We are hiring friendly, organized field assistants to help youth-sports Picture Day events run smoothly. This active weekend role is a great fit for someone who enjoys welcoming families, keeping details straight, and supporting a photographer and event team in the field.',
                    'You will often be one of the first NESP team members families meet. Your calm, welcoming presence and attention to names, teams, forms, and player numbers will help the entire event stay organized and on schedule.'
                );

            default:
                return array();
        }
    }

    private static function isNESPJobOrderID($jobOrderID)
    {
        return in_array((int) $jobOrderID, array(41001, 41002, 41003, 41005), true);
    }

    private static function isHeading($line)
    {
        return in_array(
            $line,
            array(
                'Why This Role May Be a Good Fit',
                'What You\'ll Do',
                'What We\'re Looking For',
                'Equipment You Provide',
                'What NESP Provides',
                'Schedule and Travel Expectations',
                'Schedule and Work Expectations',
                'Apply Now'
            ),
            true
        );
    }

    private static function escape($value)
    {
        return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, defined('HTML_ENCODING') ? HTML_ENCODING : 'UTF-8');
    }
}

?>
