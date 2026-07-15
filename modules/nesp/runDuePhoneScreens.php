<?php
/*
 * Render-compatible due-call runner for candidate-scheduled NESP Vapi screens.
 *
 * Intended command:
 *   php modules/nesp/runDuePhoneScreens.php
 *
 * This script does not rely on Craig's Mac, a browser session, or any local
 * process. It must run only inside the hosted application environment.
 */

if (php_sapi_name() !== 'cli')
{
    http_response_code(404);
    exit;
}

include_once(dirname(__FILE__) . '/../../config.php');
include_once(LEGACY_ROOT . '/lib/DatabaseConnection.php');
include_once(LEGACY_ROOT . '/lib/NESPWorkflow.php');
include_once(LEGACY_ROOT . '/lib/NESPVapiIntegration.php');

$limit = isset($argv[1]) ? (int) $argv[1] : 10;
$workflow = new NESPWorkflow();
$results = $workflow->runDueScheduledPhoneScreens($limit);

$summary = array(
    'ok' => true,
    'attempted' => count($results),
    'started' => 0,
    'blocked' => 0
);

foreach ($results as $result)
{
    if (!empty($result['ok']))
    {
        $summary['started']++;
    }
    else
    {
        $summary['blocked']++;
    }
}

echo json_encode($summary) . "\n";
?>
