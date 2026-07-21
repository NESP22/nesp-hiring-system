<?php
/*
 * Sessionless applicant-facing interview-link redirect.
 * The opaque token is stored hashed and never included in audit metadata.
 */

$appRoot = realpath(dirname(__FILE__) . '/../..');
if (!defined('LEGACY_ROOT'))
{
    define('LEGACY_ROOT', $appRoot);
}
include_once($appRoot . '/config.php');
include_once($appRoot . '/lib/DatabaseConnection.php');
include_once($appRoot . '/lib/NESPWorkflow.php');

function nesp_interview_link_unavailable()
{
    header('Content-Type: text/html; charset=UTF-8');
    header('Cache-Control: no-store, private');
    header('Referrer-Policy: no-referrer');
    http_response_code(404);
    echo '<!doctype html><html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Interview Link Unavailable</title>';
    echo '<style>body{margin:0;background:#f5f7fb;color:#172535;font:16px Arial,sans-serif;line-height:1.5}.wrap{max-width:720px;margin:64px auto;padding:0 18px}.panel{background:#fff;border:1px solid #d8e0ea;border-top:4px solid #cf001c;padding:24px}h1{margin:0 0 10px;color:#061f46;font-size:28px}</style></head><body><main class="wrap"><section class="panel"><h1>Interview link unavailable</h1><p>This secure link is invalid, has been replaced, or is no longer available. Please contact the NESP hiring team for an updated interview link.</p></section></main></body></html>';
    exit;
}

header('Cache-Control: no-store, private');
header('Referrer-Policy: no-referrer');
$token = isset($_GET['t']) ? $_GET['t'] : '';
$workflow = new NESPWorkflow();
$result = $workflow->openInterviewParticipantLink($token);
if (empty($result['ok']) || empty($result['destination_url']))
{
    nesp_interview_link_unavailable();
}

header('Location: ' . $result['destination_url'], true, 302);
exit;
?>
