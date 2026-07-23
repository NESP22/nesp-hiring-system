<?php

$appRoot = realpath(dirname(__FILE__) . '/../../..');
if (!defined('LEGACY_ROOT'))
{
    define('LEGACY_ROOT', $appRoot);
}

include_once($appRoot . '/config.php');
include_once($appRoot . '/lib/DatabaseConnection.php');
include_once($appRoot . '/lib/FileUtility.php');
include_once($appRoot . '/lib/Attachments.php');
include_once($appRoot . '/lib/Candidates.php');
include_once($appRoot . '/lib/Pipelines.php');
include_once($appRoot . '/lib/Users.php');
include_once($appRoot . '/lib/JobOrders.php');
include_once($appRoot . '/lib/BoardApplicantIntake.php');
include_once($appRoot . '/lib/NESPCareerApplicationSupport.php');
include_once($appRoot . '/lib/NESPWorkflow.php');
include_once(dirname(__FILE__) . '/NESPSimplePublicApplication.php');

function nesp_simple_escape($value)
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function nesp_simple_render_page($title, $body)
{
    header('Content-Type: text/html; charset=UTF-8');
    header('Cache-Control: no-store, private');
    header('Pragma: no-cache');
    echo '<!doctype html><html lang="en"><head><meta charset="utf-8" />';
    echo '<meta name="viewport" content="width=device-width, initial-scale=1" />';
    echo '<title>' . nesp_simple_escape($title) . '</title>';
    echo '<link rel="stylesheet" href="styles.css" /></head><body>';
    echo '<main class="page"><header class="brand"><img src="../../../images/nesp-logo.png" alt="New England Sports Photo" />';
    echo '<div><p class="eyebrow">NESP Hiring</p><h1>Apply to New England Sports Photo</h1>';
    echo '<p>One application, one role-specific questionnaire, and a human review.</p></div></header>';
    echo $body . '</main></body></html>';
    exit;
}

function nesp_simple_render_role_selector($jobs)
{
    $options = '<option value="">Choose a position</option>';
    foreach ($jobs as $jobOrderID => $title)
    {
        $options .= '<option value="' . (int) $jobOrderID . '">' . nesp_simple_escape($title) . '</option>';
    }

    return '<section class="band"><h2>Choose the position</h2>'
        . '<p>Select one role to open the correct application and pre-interview questions.</p>'
        . '<form method="get" class="role-form"><label for="job">Position</label>'
        . '<select id="job" name="job" required>' . $options . '</select>'
        . '<button type="submit">Continue</button></form></section>';
}

$jobs = NESPSimplePublicApplication::allowedJobs();
$signingKey = NESPSimplePublicApplication::signingKeyFromEnvironment();
if ($signingKey === '')
{
    nesp_simple_render_page(
        'Applications Temporarily Unavailable',
        '<section class="band"><h2>Applications are temporarily unavailable</h2>'
        . '<p>The hiring team is completing a secure configuration step. Please try again later.</p></section>'
    );
}

$jobOrderID = isset($_POST['jobOrderID'])
    ? (int) $_POST['jobOrderID']
    : (isset($_GET['job']) ? (int) $_GET['job'] : 0);
if ($jobOrderID === 0)
{
    nesp_simple_render_page('NESP Job Application', nesp_simple_render_role_selector($jobs));
}

$workflow = new NESPWorkflow();
$definition = NESPSimplePublicApplication::questionnaireDefinitionForJob($jobOrderID, $workflow);
if (empty($definition))
{
    nesp_simple_render_page(
        'Position Not Available',
        '<section class="band"><h2>Choose an available position</h2>'
        . '<p>That position is not available from this application page.</p>'
        . '<p><a class="button-link" href="index.php">Choose another position</a></p></section>'
    );
}

$result = array(
    'ok' => false,
    'error' => '',
    'contactErrors' => array(),
    'resumeError' => ''
);
if ($_SERVER['REQUEST_METHOD'] === 'POST')
{
    $protection = NESPSimplePublicApplication::validatePublicPost(
        $_POST,
        $signingKey,
        null,
        $definition['fingerprint']
    );
    if (empty($protection['ok']))
    {
        nesp_simple_render_page(
            'Application Needs a Fresh Start',
            '<section class="band"><h2>Please reopen the application</h2>'
            . '<p>The secure form expired or could not be verified. Your information was not saved.</p>'
            . '<p><a class="button-link" href="index.php?job=' . (int) $jobOrderID . '">Open a fresh application</a></p></section>'
        );
    }

    $result = NESPSimplePublicApplication::persist($_POST, $_FILES);
    if (!empty($result['ok']))
    {
        if (empty($result['duplicate']) && !empty($result['questionnairePath']))
        {
            header('Location: ' . $result['questionnairePath'], true, 303);
            exit;
        }

        $duplicateMessage = '<p>We already have an application for this email address and position, so no duplicate was created.</p>';
        $resumeWarning = !empty($result['resumeWarning'])
            ? '<div class="notice warning">' . nesp_simple_escape($result['resumeWarning']) . '</div>'
            : '';
        nesp_simple_render_page(
            'Application Received',
            '<section class="band success"><p class="eyebrow">Application received</p>'
            . '<h2>Thank you for applying</h2>' . $duplicateMessage
            . '<p>A person on the NESP hiring team will review your information and contact you about next steps.</p>'
            . '<p>No automated hiring decision is made from this application.</p>'
            . $resumeWarning
            . '<p><a class="button-link" href="index.php">View other positions</a></p></section>'
        );
    }
}

$post = $_SERVER['REQUEST_METHOD'] === 'POST' ? $_POST : array();
$contactErrors = isset($result['contactErrors']) ? $result['contactErrors'] : array();
$errorHTML = '';
if (!empty($result['error']))
{
    $errorHTML = '<div class="notice error"><strong>Please review the form.</strong> '
        . nesp_simple_escape($result['error']) . '</div>';
}

$formToken = NESPSimplePublicApplication::createFormToken(
    $jobOrderID,
    $signingKey,
    null,
    null,
    $definition['fingerprint']
);
$body = '<section class="role-summary"><p class="eyebrow">Position</p><h2>'
    . nesp_simple_escape($definition['roleTitle']) . '</h2>'
    . '<p><a href="index.php">Choose a different position</a></p></section>'
    . $errorHTML
    . '<form method="post" enctype="multipart/form-data" class="application-form">'
    . '<input type="hidden" name="jobOrderID" value="' . (int) $jobOrderID . '" />'
    . '<input type="hidden" name="formToken" value="' . nesp_simple_escape($formToken) . '" />'
    . '<div class="honeypot" aria-hidden="true"><label>Company website<input type="text" name="companyWebsite" value="" tabindex="-1" autocomplete="off" /></label></div>'
    . '<section class="band"><h2>1. Your contact details</h2><p class="help">Fields marked with * are required.</p>'
    . '<div class="contact-grid">'
    . '<label>First name *<input type="text" name="firstName" autocomplete="given-name" required value="' . nesp_simple_escape(isset($post['firstName']) ? $post['firstName'] : '') . '" />'
    . (isset($contactErrors['firstName']) ? '<span class="field-message">' . nesp_simple_escape($contactErrors['firstName']) . '</span>' : '') . '</label>'
    . '<label>Last name *<input type="text" name="lastName" autocomplete="family-name" required value="' . nesp_simple_escape(isset($post['lastName']) ? $post['lastName'] : '') . '" />'
    . (isset($contactErrors['lastName']) ? '<span class="field-message">' . nesp_simple_escape($contactErrors['lastName']) . '</span>' : '') . '</label>'
    . '<label>Email address *<input type="email" name="email" autocomplete="email" required value="' . nesp_simple_escape(isset($post['email']) ? $post['email'] : '') . '" />'
    . (isset($contactErrors['email']) ? '<span class="field-message">' . nesp_simple_escape($contactErrors['email']) . '</span>' : '') . '</label>'
    . '<label>Phone number<input type="tel" name="phone" autocomplete="tel" value="' . nesp_simple_escape(isset($post['phone']) ? $post['phone'] : '') . '" /></label>'
    . '<label>City<input type="text" name="city" autocomplete="address-level2" value="' . nesp_simple_escape(isset($post['city']) ? $post['city'] : '') . '" /></label>'
    . '<label>State<input type="text" name="state" autocomplete="address-level1" value="' . nesp_simple_escape(isset($post['state']) ? $post['state'] : '') . '" /></label>'
    . '<label>ZIP code<input type="text" name="zip" autocomplete="postal-code" value="' . nesp_simple_escape(isset($post['zip']) ? $post['zip'] : '') . '" /></label>'
    . '</div></section>'
    . '<section class="band"><h2>2. Optional resume</h2>'
    . '<p class="help">Accepted files: PDF, DOC, DOCX, RTF, or ODT. Maximum 10 MB.</p>'
    . (!empty($result['resumeError']) ? '<p class="field-message">' . nesp_simple_escape($result['resumeError']) . '</p>' : '')
    . '<input type="file" name="resume" accept=".pdf,.doc,.docx,.rtf,.odt" /></section>'
    . '<section class="band"><h2>3. Continue to the role questionnaire</h2>'
    . '<p>After saving this application, you will continue to the existing NESP '
    . nesp_simple_escape($definition['questionSetLabel']) . ' questionnaire.</p>'
    . '<p class="privacy">The questions come from the currently published NESP question set. '
    . 'No questionnaire wording is duplicated on this application page.</p></section>'
    . '<section class="submit-band"><button type="submit">Save and Continue to Questionnaire</button>'
    . '<p>Submitting does not create an automated hiring decision or send a message.</p></section>'
    . '</form>';

nesp_simple_render_page('Apply to NESP', $body);
