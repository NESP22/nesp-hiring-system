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

        $tokens = self::tokenize($description);
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
        $tokens = self::tokenize($description);
        $html = '';
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

            if ($skippingFacts && preg_match('/^(Pay|Location|Territory|Work location|Employment type|Typical schedule|Season):/i', $line))
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
