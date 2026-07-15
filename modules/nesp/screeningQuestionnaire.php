<?php
/*
 * Public candidate screening questionnaire for NESP hiring.
 *
 * This endpoint is intentionally sessionless from an account perspective. It
 * uses opaque questionnaire tokens and never exposes candidate IDs, job-order
 * IDs, interviewer notes, admin notes, or integration secrets.
 */

$appRoot = realpath(dirname(__FILE__) . '/../..');
if (!defined('LEGACY_ROOT'))
{
    define('LEGACY_ROOT', $appRoot);
}
include_once($appRoot . '/config.php');
include_once($appRoot . '/lib/DatabaseConnection.php');
include_once($appRoot . '/lib/NESPWorkflow.php');
include_once($appRoot . '/lib/NESPVapiIntegration.php');

if (session_status() !== PHP_SESSION_ACTIVE)
{
    session_start();
}
if (empty($_SESSION['nesp_questionnaire_csrf']))
{
    $_SESSION['nesp_questionnaire_csrf'] = NESPWorkflow::generateQuestionnaireToken();
}

function nesp_questionnaire_escape($value)
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function nesp_questionnaire_render($title, $body)
{
    header('Content-Type: text/html; charset=UTF-8');
    echo '<!doctype html><html><head><meta charset="utf-8" />';
    echo '<meta name="viewport" content="width=device-width, initial-scale=1" />';
    echo '<title>' . nesp_questionnaire_escape($title) . '</title>';
    echo '<style>
        body{margin:0;background:#f5f7fb;color:#172535;font-family:Arial,Helvetica,sans-serif;line-height:1.45}
        .wrap{max-width:820px;margin:0 auto;padding:24px 16px 42px}
        .brand{border-bottom:1px solid #cfd9e6;padding:0 0 14px;margin:0 0 18px}
        .brand h1{margin:0;color:#071e41;font-size:26px}
        .panel{background:#fff;border:1px solid #d8e0ea;padding:18px;margin:0 0 14px}
        .notice{border:1px solid #d0a846;background:#fff8e6;color:#3d310f;padding:12px;margin:0 0 14px;font-weight:bold}
        label{display:block;margin:14px 0 6px;font-weight:bold}
        input[type=text],textarea{width:100%;box-sizing:border-box;padding:10px;border:1px solid #b8c5d5;font-size:16px}
        textarea{min-height:88px}
        button{border:1px solid #315f93;background:#315f93;color:#fff;padding:10px 14px;font-weight:bold;cursor:pointer;margin:8px 8px 0 0}
        .secondary{background:#4f6578;border-color:#4f6578}
        .muted{color:#536579;font-size:14px}
        .error{border:1px solid #bd5b5b;background:#fff1f1;color:#742121;padding:12px;margin:0 0 14px}
        dl{display:grid;grid-template-columns:190px minmax(0,1fr);gap:8px 12px}
        dt{font-weight:bold;color:#536579}dd{margin:0}
        @media (max-width:640px){dl{display:block}dt{margin-top:8px}.wrap{padding:18px 12px 34px}}
    </style></head><body><div class="wrap"><div class="brand"><h1>New England Sports Photo</h1></div>' . $body . '</div></body></html>';
    exit;
}

$token = isset($_GET['t']) ? trim($_GET['t']) : (isset($_POST['token']) ? trim($_POST['token']) : '');
$workflow = new NESPWorkflow();
$errors = array();

if ($_SERVER['REQUEST_METHOD'] === 'POST')
{
    $csrf = isset($_POST['csrfToken']) ? $_POST['csrfToken'] : '';
    if (!hash_equals($_SESSION['nesp_questionnaire_csrf'], $csrf))
    {
        nesp_questionnaire_render('Questionnaire Unavailable', '<div class="panel"><h2>Questionnaire unavailable</h2><p>The request token was invalid. Please reopen your secure questionnaire link and try again.</p></div>');
    }

    $action = isset($_POST['candidateAction']) ? $_POST['candidateAction'] : '';
    if ($action === 'submit')
    {
        $answers = isset($_POST['answers']) && is_array($_POST['answers']) ? $_POST['answers'] : array();
        $result = $workflow->submitQuestionnaireFromToken($token, $answers);
        if (!empty($result['ok']))
        {
            nesp_questionnaire_render('Questionnaire Submitted', '<div class="panel"><h2>Thank you</h2><p>Your screening questionnaire has been submitted. A person on the NESP hiring team will review your answers.</p><p class="muted">No automated hiring decision is made from this questionnaire.</p></div>');
        }
        if (isset($result['state']) && $result['state'] === 'validation_failed')
        {
            $errors = isset($result['missing']) ? $result['missing'] : array();
        }
        else
        {
            nesp_questionnaire_render('Questionnaire Unavailable', '<div class="panel"><h2>Questionnaire unavailable</h2><p>This questionnaire is invalid, expired, revoked, already submitted, or temporarily rate-limited.</p></div>');
        }
    }
    else if ($action === 'humanFollowUp')
    {
        $workflow->requestQuestionnaireHumanFollowUpFromToken($token);
        nesp_questionnaire_render('Human Follow-Up Requested', '<div class="panel"><h2>Human follow-up requested</h2><p>The NESP hiring team will review your request for a person to follow up.</p></div>');
    }
}

$page = $workflow->getQuestionnairePageByToken($token);
if (empty($page['ok']))
{
    nesp_questionnaire_render('Questionnaire Link Unavailable', '<div class="panel"><h2>Questionnaire link unavailable</h2><p>This secure link is invalid, expired, revoked, already submitted, or temporarily rate-limited.</p></div>');
}

$questionnaire = $page['questionnaire'];
$errorHTML = '';
if (count($errors))
{
    $errorHTML = '<div class="error">Please answer all required questions before submitting.</div>';
}

$fields = '';
foreach ($questionnaire['questions'] as $question)
{
    $key = $question['key'];
    $value = isset($_POST['answers'][$key]) ? $_POST['answers'][$key] : '';
    $requiredMark = !empty($question['required']) ? ' *' : '';
    $fields .= '<label for="answer_' . nesp_questionnaire_escape($key) . '">' . nesp_questionnaire_escape($question['label'] . $requiredMark) . '</label>';
    if ($question['type'] === 'text')
    {
        $fields .= '<input type="text" id="answer_' . nesp_questionnaire_escape($key) . '" name="answers[' . nesp_questionnaire_escape($key) . ']" value="' . nesp_questionnaire_escape($value) . '" />';
    }
    else
    {
        $fields .= '<textarea id="answer_' . nesp_questionnaire_escape($key) . '" name="answers[' . nesp_questionnaire_escape($key) . ']">' . nesp_questionnaire_escape($value) . '</textarea>';
    }
}

$body = '<div class="notice">Your answers will be reviewed by a person. No automated hiring decision will be made.</div>'
    . '<div class="panel"><h2>Screening Questionnaire</h2>'
    . '<dl>'
    . '<dt>Position</dt><dd>' . nesp_questionnaire_escape($questionnaire['role_title']) . '</dd>'
    . '<dt>Estimated time</dt><dd>Approximately 5-10 minutes</dd>'
    . '<dt>Account required</dt><dd>No account is required</dd>'
    . '</dl>'
    . '<p class="muted">Please answer only job-related questions. Do not include protected personal information such as age, race, religion, medical history, disability, marital or family status.</p></div>'
    . $errorHTML
    . '<div class="panel"><form method="post">'
    . '<input type="hidden" name="token" value="' . nesp_questionnaire_escape($token) . '" />'
    . '<input type="hidden" name="csrfToken" value="' . nesp_questionnaire_escape($_SESSION['nesp_questionnaire_csrf']) . '" />'
    . $fields
    . '<p><button type="submit" name="candidateAction" value="submit">Submit Questionnaire</button>'
    . '<button class="secondary" type="submit" name="candidateAction" value="humanFollowUp">Request Human Follow-Up</button></p>'
    . '</form></div>';

nesp_questionnaire_render('NESP Screening Questionnaire', $body);
?>
