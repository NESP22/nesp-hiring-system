<?php

/*
 * Twice-daily board inbox scheduler entry point.
 *
 * Render invokes four UTC candidate hours so both New York UTC offsets are
 * covered. Only the corresponding 08:00 or 18:00 local run reaches the
 * scheduler class.
 */

if (PHP_SAPI !== 'cli')
{
    http_response_code(404);
    exit(1);
}

$boardIntakeSchedulerExited = false;
ini_set('display_errors', '0');
ini_set('log_errors', '0');
mysqli_report(MYSQLI_REPORT_OFF);
ob_start(static function ($buffer) {
    return '';
});

register_shutdown_function(static function () use (&$boardIntakeSchedulerExited) {
    if ($boardIntakeSchedulerExited)
    {
        return;
    }
    while (ob_get_level() > 0)
    {
        ob_end_clean();
    }
    fwrite(STDERR, "Board inbox scheduler terminated safely.\n");
    exit(1);
});

function boardIntakeSchedulerExit($message, $exitCode)
{
    global $boardIntakeSchedulerExited;
    $boardIntakeSchedulerExited = true;
    while (ob_get_level() > 0)
    {
        ob_end_clean();
    }
    $stream = $exitCode === 0 ? STDOUT : STDERR;
    fwrite($stream, $message . "\n");
    exit($exitCode);
}

set_exception_handler(static function (Throwable $exception) {
    boardIntakeSchedulerExit('Board inbox scheduler terminated safely.', 1);
});

$CATSHome = realpath(dirname(__FILE__) . '/..');
if ($CATSHome === false || !chdir($CATSHome))
{
    boardIntakeSchedulerExit('Board inbox scheduler bootstrap failed.', 1);
}

if (!is_readable('./config.php'))
{
    boardIntakeSchedulerExit('Board inbox scheduler bootstrap failed.', 1);
}

include_once('./config.php');
if (!defined('LEGACY_ROOT'))
{
    boardIntakeSchedulerExit('Board inbox scheduler bootstrap failed.', 1);
}
include_once(LEGACY_ROOT . '/constants.php');
include_once(LEGACY_ROOT . '/lib/DatabaseConnection.php');

$database = DatabaseConnection::getInstance();
$connection = $database->getConnection();
$flagResult = @mysqli_query(
    $connection,
    "SELECT `is_enabled` FROM `nesp_feature_flag` " .
    "WHERE `flag_key` = 'NESP_BOARD_INTAKE_SCHEDULER_ENABLED' LIMIT 1"
);

if ($flagResult === false)
{
    boardIntakeSchedulerExit('Board inbox scheduler configuration is unavailable.', 1);
}

$flag = mysqli_fetch_assoc($flagResult);
mysqli_free_result($flagResult);
if (!is_array($flag) || (int) $flag['is_enabled'] !== 1)
{
    boardIntakeSchedulerExit('Board inbox scheduler is disabled.', 0);
}

$timezoneName = trim((string) getenv('APP_TIMEZONE'));
$apiToken = trim((string) getenv('NESP_BOARD_INTAKE_MISSIVE_API_TOKEN'));
$webhookSecret = trim((string) getenv('NESP_BOARD_INTAKE_MISSIVE_WEBHOOK_SECRET'));
$ruleID = trim((string) getenv('NESP_BOARD_INTAKE_MISSIVE_RULE_ID'));
$sharedLabelID = trim((string) getenv('NESP_BOARD_INTAKE_MISSIVE_SHARED_LABEL_ID'));
$systemUserID = trim((string) getenv('NESP_BOARD_INTAKE_SYSTEM_USER_ID'));

if ($timezoneName !== 'America/New_York' || $apiToken === '' || $webhookSecret === '' || $ruleID === '' ||
    $sharedLabelID === '' ||
    !ctype_digit($systemUserID) || (int) $systemUserID < 1)
{
    boardIntakeSchedulerExit('Board inbox scheduler configuration is incomplete or invalid.', 1);
}

$userStatement = @mysqli_prepare(
    $connection,
    'SELECT `user_id` FROM `user` WHERE `user_id` = ? AND `access_level` >= 400 LIMIT 1'
);
$systemUserID = (int) $systemUserID;
if ($userStatement === false || !mysqli_stmt_bind_param($userStatement, 'i', $systemUserID) ||
    !mysqli_stmt_execute($userStatement))
{
    if ($userStatement !== false)
    {
        mysqli_stmt_close($userStatement);
    }
    boardIntakeSchedulerExit('Board inbox scheduler system user is unavailable.', 1);
}

mysqli_stmt_store_result($userStatement);
$systemUserExists = mysqli_stmt_num_rows($userStatement) === 1;
mysqli_stmt_close($userStatement);
if (!$systemUserExists)
{
    boardIntakeSchedulerExit('Board inbox scheduler system user is unavailable.', 1);
}

$timezone = new DateTimeZone('America/New_York');
$localHour = (int) (new DateTimeImmutable('now', $timezone))->format('G');
if (!in_array($localHour, array(8, 18), true))
{
    boardIntakeSchedulerExit('Board inbox scheduler skipped a non-local candidate hour.', 0);
}

$schedulerPath = LEGACY_ROOT . '/lib/NESPBoardIntakeScheduler.php';
if (!is_readable($schedulerPath))
{
    boardIntakeSchedulerExit('Board inbox scheduler runtime is unavailable.', 1);
}

include_once($schedulerPath);
if (!class_exists('NESPBoardIntakeScheduler') ||
    !method_exists('NESPBoardIntakeScheduler', 'runScheduledSlot'))
{
    boardIntakeSchedulerExit('Board inbox scheduler runtime is unavailable.', 1);
}

try
{
    $result = (new NESPBoardIntakeScheduler($database))->runScheduledSlot($systemUserID);
    $status = is_array($result) && isset($result['status']) ? $result['status'] : 'failed';
    if (in_array($status, array('failed', 'degraded', 'stopped'), true))
    {
        boardIntakeSchedulerExit('Board inbox scheduler run failed.', 1);
    }
}
catch (Throwable $exception)
{
    boardIntakeSchedulerExit('Board inbox scheduler run failed.', 1);
}

boardIntakeSchedulerExit('Board inbox scheduler candidate completed.', 0);
