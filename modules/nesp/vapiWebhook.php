<?php
/*
 * Sessionless Vapi webhook endpoint for NESP hiring phone screens.
 */

$rootDirectory = dirname(dirname(__DIR__));
chdir($rootDirectory);

include_once($rootDirectory . '/config.php');
include_once(LEGACY_ROOT . '/constants.php');
include_once(LEGACY_ROOT . '/lib/DatabaseConnection.php');
include_once(LEGACY_ROOT . '/lib/NESPWorkflow.php');
include_once(LEGACY_ROOT . '/lib/NESPVapiIntegration.php');

function nesp_vapi_headers()
{
    if (function_exists('getallheaders'))
    {
        $headers = getallheaders();
        if (is_array($headers))
        {
            return $headers;
        }
    }

    $headers = array();
    foreach ($_SERVER as $key => $value)
    {
        if (strpos($key, 'HTTP_') === 0)
        {
            $name = str_replace(' ', '-', ucwords(strtolower(str_replace('_', ' ', substr($key, 5)))));
            $headers[$name] = $value;
        }
    }
    if (isset($_SERVER['CONTENT_TYPE']))
    {
        $headers['Content-Type'] = $_SERVER['CONTENT_TYPE'];
    }
    return $headers;
}

function nesp_vapi_json_response($status, $payload)
{
    http_response_code($status);
    header('Content-Type: application/json');
    echo json_encode($payload);
    exit;
}

if (!isset($_SERVER['HTTPS']) || $_SERVER['HTTPS'] !== 'on')
{
    if (!isset($_SERVER['HTTP_X_FORWARDED_PROTO']) || $_SERVER['HTTP_X_FORWARDED_PROTO'] !== 'https')
    {
        nesp_vapi_json_response(400, array('ok' => false, 'error' => 'https_required'));
    }
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST')
{
    nesp_vapi_json_response(405, array('ok' => false, 'error' => 'method_not_allowed'));
}

$rawBody = file_get_contents('php://input');
$headers = nesp_vapi_headers();
$contentType = isset($_SERVER['CONTENT_TYPE']) ? $_SERVER['CONTENT_TYPE'] : '';
$validation = NESPVapiIntegration::validateWebhookRequest(
    $headers,
    $contentType,
    $rawBody,
    time(),
    getenv('VAPI_WEBHOOK_SECRET') === false ? '' : getenv('VAPI_WEBHOOK_SECRET')
);

if (empty($validation['ok']))
{
    nesp_vapi_json_response($validation['status'], array('ok' => false, 'error' => $validation['error']));
}

$workflow = new NESPWorkflow();
$result = $workflow->processVapiWebhook($validation);
if (empty($result['ok']))
{
    nesp_vapi_json_response(isset($result['status']) ? $result['status'] : 500, array('ok' => false, 'error' => 'webhook_processing_failed'));
}

nesp_vapi_json_response(200, array('ok' => true, 'duplicate' => !empty($result['duplicate'])));

?>
