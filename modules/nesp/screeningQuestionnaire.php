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
        :root{--nesp-navy:#061f46;--nesp-blue:#315f93;--nesp-red:#cf001c;--nesp-ink:#172535;--nesp-muted:#536579;--nesp-line:#d8e0ea;--nesp-soft:#f5f7fb;--nesp-warm:#fff8e6}
        body{margin:0;background:var(--nesp-soft);color:var(--nesp-ink);font-family:Arial,Helvetica,sans-serif;line-height:1.5}
        .wrap{max-width:880px;margin:0 auto;padding:24px 16px 44px}
        .brand{display:flex;align-items:center;gap:18px;border-bottom:4px solid var(--nesp-red);background:#fff;padding:18px;margin:0 0 18px}
        .brand img{width:156px;max-width:42vw;height:auto}
        .brand span{display:block;color:var(--nesp-red);font-size:12px;font-weight:bold;text-transform:uppercase}
        .brand h1{margin:2px 0 4px;color:var(--nesp-navy);font-size:28px;line-height:1.15}
        .brand p{margin:0;color:var(--nesp-muted);font-size:14px}
        .panel{background:#fff;border:1px solid var(--nesp-line);padding:18px;margin:0 0 14px}
        .panel h2{margin:0 0 10px;color:var(--nesp-navy);font-size:22px;line-height:1.25}
        .notice{border:1px solid #d0a846;background:var(--nesp-warm);color:#3d310f;padding:12px;margin:0 0 14px;font-weight:bold}
        .steps{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:8px;margin:0 0 14px}
        .step{background:#fff;border:1px solid var(--nesp-line);padding:10px;color:var(--nesp-muted);font-weight:bold}
        .step span{display:inline-flex;align-items:center;justify-content:center;width:22px;height:22px;margin-right:6px;background:var(--nesp-navy);color:#fff;font-size:12px}
        label{display:block;margin:16px 0 6px;color:var(--nesp-navy);font-weight:bold}
        .required-note{color:var(--nesp-muted);font-size:13px;margin:0 0 10px}
        input[type=text],textarea{width:100%;box-sizing:border-box;padding:11px 12px;border:1px solid #b8c5d5;background:#fff;font-size:16px;color:var(--nesp-ink)}
        input[type=text]:focus,textarea:focus{outline:3px solid rgba(49,95,147,.22);border-color:var(--nesp-blue)}
        textarea{min-height:96px;resize:vertical}
        .actions{display:flex;flex-wrap:wrap;gap:10px;margin-top:16px}
        button{border:1px solid var(--nesp-blue);background:var(--nesp-blue);color:#fff;padding:11px 15px;font-weight:bold;cursor:pointer;font-size:14px}
        .secondary{background:#fff;border-color:var(--nesp-red);color:var(--nesp-red)}
        .muted{color:var(--nesp-muted);font-size:14px}
        .error{border:1px solid #bd5b5b;background:#fff1f1;color:#742121;padding:12px;margin:0 0 14px;font-weight:bold}
        dl{display:grid;grid-template-columns:190px minmax(0,1fr);gap:8px 12px}
        dt{font-weight:bold;color:var(--nesp-muted)}dd{margin:0}
        @media (max-width:640px){.wrap{padding:14px 10px 32px}.brand{display:block;padding:14px}.brand img{width:132px;margin:0 0 10px}.brand h1{font-size:23px}.steps{grid-template-columns:1fr}dl{display:block}dt{margin-top:8px}.actions button{width:100%;margin:0}}
    </style></head><body><div class="wrap"><div class="brand"><img src="../../images/nesp-logo.png" alt="New England Sports Photo" /><div><span>Applicant Screening</span><h1>New England Sports Photo</h1><p>Sports photography roles reviewed by the NESP hiring team.</p></div></div>' . $body . '</div></body></html>';
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
            nesp_questionnaire_render('Questionnaire Submitted', '<div class="panel"><h2>Thank you</h2><p>Your screening questionnaire has been submitted to New England Sports Photo. A person on the NESP hiring team will review your answers.</p><p class="muted">No automated hiring decision is made from this questionnaire.</p></div>');
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
        $result = $workflow->requestQuestionnaireHumanFollowUpFromToken($token);
        if (!empty($result['ok']))
        {
            nesp_questionnaire_render('Human Follow-Up Requested', '<div class="panel"><h2>Human follow-up requested</h2><p>The NESP hiring team will review your request for a person to follow up.</p></div>');
        }
        nesp_questionnaire_render('Questionnaire Unavailable', '<div class="panel"><h2>Questionnaire unavailable</h2><p>This questionnaire is invalid, expired, revoked, already submitted, or temporarily rate-limited.</p></div>');
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
    $requiredAttribute = !empty($question['required']) ? ' aria-required="true"' : '';
    $fields .= '<label for="answer_' . nesp_questionnaire_escape($key) . '">' . nesp_questionnaire_escape($question['label'] . $requiredMark) . '</label>';
    if ($question['type'] === 'text')
    {
        $fields .= '<input type="text" id="answer_' . nesp_questionnaire_escape($key) . '" name="answers[' . nesp_questionnaire_escape($key) . ']" value="' . nesp_questionnaire_escape($value) . '"' . $requiredAttribute . ' />';
    }
    else
    {
        $fields .= '<textarea id="answer_' . nesp_questionnaire_escape($key) . '" name="answers[' . nesp_questionnaire_escape($key) . ']"' . $requiredAttribute . '>' . nesp_questionnaire_escape($value) . '</textarea>';
    }
}

$body = '<div class="notice">Your answers will be reviewed by a person. No automated hiring decision will be made.</div>'
    . '<div class="steps" aria-label="Questionnaire progress"><div class="step"><span>1</span>Review the role</div><div class="step"><span>2</span>Answer questions</div><div class="step"><span>3</span>NESP follows up</div></div>'
    . '<div class="panel"><h2>Screening Questionnaire</h2>'
    . '<dl>'
    . '<dt>Position</dt><dd>' . nesp_questionnaire_escape($questionnaire['role_title']) . '</dd>'
    . '<dt>Estimated time</dt><dd>Approximately 5-10 minutes</dd>'
    . '<dt>Account required</dt><dd>No account is required</dd>'
    . '</dl>'
    . '<p class="muted">Please answer only job-related questions about your availability, experience, and fit for the role. Do not include protected personal information such as age, race, religion, medical history, disability, marital or family status.</p></div>'
    . $errorHTML
    . '<div class="panel"><form method="post">'
    . '<input type="hidden" name="token" value="' . nesp_questionnaire_escape($token) . '" />'
    . '<input type="hidden" name="csrfToken" value="' . nesp_questionnaire_escape($_SESSION['nesp_questionnaire_csrf']) . '" />'
    . '<p class="required-note">Fields marked with * are required.</p>'
    . $fields
    . '<p class="actions"><button type="submit" name="candidateAction" value="submit">Submit Questionnaire</button>'
    . '<button class="secondary" type="submit" name="candidateAction" value="humanFollowUp">Request Human Follow-Up</button></p>'
    . '</form></div>';

nesp_questionnaire_render('NESP Screening Questionnaire', $body);
?>
