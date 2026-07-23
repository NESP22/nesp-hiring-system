<?php

class NESPCareerApplicationSupport
{
    public static function inspectResumeUpload($files, $field = 'file')
    {
        if (!isset($files[$field]) || !is_array($files[$field]))
        {
            return array('hasUpload' => false, 'warning' => '');
        }

        $upload = $files[$field];
        $name = isset($upload['name']) ? trim((string) $upload['name']) : '';
        $error = isset($upload['error']) ? (int) $upload['error'] : UPLOAD_ERR_NO_FILE;

        if ($name === '' || $error === UPLOAD_ERR_NO_FILE)
        {
            return array('hasUpload' => false, 'warning' => '');
        }

        if ($error !== UPLOAD_ERR_OK)
        {
            return array(
                'hasUpload' => false,
                'warning' => FileUtility::getErrorMessage($error)
            );
        }

        return array('hasUpload' => true, 'warning' => '');
    }

    public static function resumeInputHTML($id = 'resume', $name = 'file', $size = null)
    {
        $sizeAttribute = $size === null ? '' : ' size="' . (int) $size . '"';

        return '<input type="file" id="' . htmlspecialchars($id, ENT_QUOTES | ENT_SUBSTITUTE, HTML_ENCODING)
            . '" name="' . htmlspecialchars($name, ENT_QUOTES | ENT_SUBSTITUTE, HTML_ENCODING)
            . '" class="inputBoxFile" accept=".pdf,.doc,.docx,.rtf,.odt,.txt,.pages"'
            . $sizeAttribute . ' />'
            . '<p class="nesp-field-help">Optional. Accepted resume formats: PDF, DOC, DOCX, RTF, ODT, TXT, or Pages.</p>';
    }

    public static function renderSuccessPage($jobTitle, $resumeWarning = '')
    {
        $jobTitleEscaped = htmlspecialchars(
            (string) $jobTitle,
            ENT_QUOTES | ENT_SUBSTITUTE,
            HTML_ENCODING
        );
        $warningHTML = '';

        if ($resumeWarning !== '')
        {
            $warningHTML = '<div class="nesp-notice nesp-notice-warning">'
                . '<strong>Your application was received.</strong> '
                . 'The resume could not be attached. Our hiring team can request it during review.'
                . '</div>';
        }

        return '<main id="careerContent" class="nesp-main nesp-application-confirmation">'
            . '<section class="nesp-section-heading">'
            . '<p class="nesp-kicker">Application received</p>'
            . '<h2>Thank you for applying</h2>'
            . '<p>Your application for <strong>' . $jobTitleEscaped . '</strong> was received.</p>'
            . '<p>A member of the New England Sports Photo hiring team will review it and contact you about next steps.</p>'
            . $warningHTML
            . '<a class="nesp-button" href="index.php?m=careers&amp;p=showAll">Back to Current Opportunities</a>'
            . '</section>'
            . '</main>';
    }
}
