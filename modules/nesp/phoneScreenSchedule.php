<?php
/*
 * Public candidate scheduling page for NESP Vapi phone screens.
 *
 * This endpoint is intentionally sessionless. It uses opaque scheduling tokens
 * and never exposes candidate IDs, job-order IDs, admin notes, or secrets.
 */

include_once(dirname(__FILE__) . '/../../config.php');
include_once(LEGACY_ROOT . '/lib/DatabaseConnection.php');
include_once(LEGACY_ROOT . '/lib/NESPWorkflow.php');
include_once(LEGACY_ROOT . '/lib/NESPVapiIntegration.php');

function nesp_schedule_escape($value)
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function nesp_schedule_render($title, $body)
{
    header('Content-Type: text/html; charset=UTF-8');
    echo '<!doctype html><html><head><meta charset="utf-8" />';
    echo '<meta name="viewport" content="width=device-width, initial-scale=1" />';
    echo '<title>' . nesp_schedule_escape($title) . '</title>';
    echo '<style>
        body{margin:0;background:#f4f7fb;color:#1b2733;font-family:Arial,Helvetica,sans-serif}
        .wrap{max-width:760px;margin:0 auto;padding:28px 18px 42px}
        .brand{border-bottom:1px solid #ccd8e6;padding:0 0 14px;margin:0 0 18px}
        .brand h1{margin:0;color:#071e41;font-size:26px}
        .panel{background:#fff;border:1px solid #d7dee8;padding:18px;margin:0 0 14px}
        .notice{border:1px solid #d6b256;background:#fff7df;color:#4c3b0f;padding:12px;margin:0 0 14px;font-weight:bold}
        label{display:block;margin:12px 0 6px;font-weight:bold}
        select,textarea{width:100%;box-sizing:border-box;padding:9px;border:1px solid #b9c5d3;font-size:15px}
        button{border:1px solid #315f93;background:#315f93;color:#fff;padding:10px 13px;font-weight:bold;cursor:pointer}
        .secondary{background:#4f6578;border-color:#4f6578}
        .muted{color:#536579;font-size:13px;line-height:1.45}
        dl{display:grid;grid-template-columns:180px minmax(0,1fr);gap:8px 12px}
        dt{font-weight:bold;color:#536579}dd{margin:0}
    </style></head><body><div class="wrap"><div class="brand"><h1>New England Sports Photo</h1></div>' . $body . '</div></body></html>';
    exit;
}

$token = isset($_GET['t']) ? trim($_GET['t']) : (isset($_POST['token']) ? trim($_POST['token']) : '');
$workflow = new NESPWorkflow();

if ($_SERVER['REQUEST_METHOD'] === 'POST')
{
    $action = isset($_POST['candidateAction']) ? $_POST['candidateAction'] : '';
    if ($action === 'schedule' || $action === 'reschedule')
    {
        $slot = isset($_POST['slot']) ? $_POST['slot'] : '';
        $result = $workflow->schedulePhoneScreenFromToken($token, $slot);
        if (!empty($result['ok']))
        {
            nesp_schedule_render('Phone Screen Scheduled', '<div class="panel"><h2>Phone screen scheduled</h2><p>Your NESP Hiring phone screen has been scheduled. Times are stored and shown in Eastern Time.</p><p class="muted">The call will come from the NESP Hiring number. Audio will not be recorded; the conversation will be transcribed only after you consent.</p></div>');
        }
        nesp_schedule_render('Scheduling Unavailable', '<div class="panel"><h2>That time is no longer available</h2><p>Please go back and choose another available time.</p></div>');
    }
    if ($action === 'cancel')
    {
        $workflow->cancelPhoneScreenFromToken($token);
        nesp_schedule_render('Phone Screen Cancelled', '<div class="panel"><h2>Phone screen cancelled</h2><p>Your automated phone screen appointment has been cancelled.</p></div>');
    }
    if ($action === 'humanFollowUp')
    {
        $workflow->requestHumanFollowUpFromToken($token);
        nesp_schedule_render('Human Follow-Up Requested', '<div class="panel"><h2>Human follow-up requested</h2><p>The NESP hiring team will review your request for a person to follow up.</p></div>');
    }
}

$page = $workflow->getSchedulingPageByToken($token);
if (empty($page['ok']))
{
    nesp_schedule_render('Scheduling Link Unavailable', '<div class="panel"><h2>Scheduling link unavailable</h2><p>This scheduling link is invalid, expired, revoked, or temporarily rate-limited.</p></div>');
}

$screen = $page['screen'];
$slotOptions = '';
foreach ($screen['available_slots'] as $slot)
{
    $slotOptions .= '<option value="' . nesp_schedule_escape($slot['value']) . '">' . nesp_schedule_escape($slot['label']) . '</option>';
}
if ($slotOptions === '')
{
    $slotOptions = '<option value="">No appointment windows are currently available</option>';
}

$currentAppointment = $screen['scheduled_display'] === '' ? 'Not scheduled' : $screen['scheduled_display'];
$body = '<div class="notice">This is an automated NESP Hiring phone screen. Audio is not recorded, and the call is transcribed only after you consent.</div>'
    . '<div class="panel"><h2>Schedule your phone screen</h2>'
    . '<dl>'
    . '<dt>Position</dt><dd>' . nesp_schedule_escape($screen['role_title']) . '</dd>'
    . '<dt>Expected call length</dt><dd>Approximately 7-10 minutes</dd>'
    . '<dt>Caller ID label</dt><dd>NESP Hiring</dd>'
    . '<dt>Time zone</dt><dd>All times are shown in Eastern Time</dd>'
    . '<dt>Current appointment</dt><dd>' . nesp_schedule_escape($currentAppointment) . '</dd>'
    . '</dl></div>'
    . '<div class="panel"><form method="post">'
    . '<input type="hidden" name="token" value="' . nesp_schedule_escape($token) . '" />'
    . '<label for="slot">Available appointment windows</label>'
    . '<select id="slot" name="slot">' . $slotOptions . '</select>'
    . '<p><button type="submit" name="candidateAction" value="' . ($screen['scheduled_display'] === '' ? 'schedule' : 'reschedule') . '">Confirm Appointment</button></p>'
    . '<p><button class="secondary" type="submit" name="candidateAction" value="cancel">Cancel Appointment</button> '
    . '<button class="secondary" type="submit" name="candidateAction" value="humanFollowUp">Request Human Follow-Up</button></p>'
    . '</form></div>';

nesp_schedule_render('Schedule NESP Phone Screen', $body);
?>
