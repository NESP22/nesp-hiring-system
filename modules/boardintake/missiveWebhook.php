<?php

$rootDirectory = dirname(dirname(__DIR__));
chdir($rootDirectory);

include_once($rootDirectory . '/config.php');
include_once(LEGACY_ROOT . '/constants.php');
include_once(LEGACY_ROOT . '/lib/DatabaseConnection.php');
include_once(LEGACY_ROOT . '/lib/NESPBoardInboxIntegration.php');
include_once(LEGACY_ROOT . '/lib/NESPBoardIntakeScheduler.php');

function nesp_board_inbox_headers()
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
    return $headers;
}

function nesp_board_inbox_response($status, $payload)
{
    http_response_code($status);
    header('Content-Type: application/json');
    header('Cache-Control: no-store');
    echo json_encode($payload);
    exit;
}

$isHTTPS = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on';
if (!$isHTTPS && isset($_SERVER['HTTP_X_FORWARDED_PROTO']))
{
    $isHTTPS = strtolower((string) $_SERVER['HTTP_X_FORWARDED_PROTO']) === 'https';
}

$rawBody = file_get_contents('php://input');
$validation = NESPBoardInboxIntegration::validateWebhookRequest(
    isset($_SERVER['REQUEST_METHOD']) ? $_SERVER['REQUEST_METHOD'] : '',
    $isHTTPS,
    isset($_SERVER['CONTENT_TYPE']) ? $_SERVER['CONTENT_TYPE'] : '',
    nesp_board_inbox_headers(),
    $rawBody
);
if (empty($validation['ok']))
{
    nesp_board_inbox_response($validation['status'], array('ok' => false, 'error' => $validation['error']));
}

$scheduler = new NESPBoardIntakeScheduler();
if (!$scheduler->isEnabled())
{
    nesp_board_inbox_response(503, array('ok' => false, 'error' => 'feature_disabled'));
}
$queued = $scheduler->queueWebhookEvent($validation['event']);
if (empty($queued['ok']))
{
    nesp_board_inbox_response(503, array('ok' => false, 'error' => 'queue_unavailable'));
}

nesp_board_inbox_response(202, array('ok' => true, 'duplicate' => !empty($queued['duplicate'])));
