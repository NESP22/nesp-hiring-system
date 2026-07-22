<?php

/* Daily, fail-closed questionnaire reminder entry point. */

if (PHP_SAPI !== 'cli')
{
    http_response_code(404);
    exit(1);
}

$reminderExited = false;
ini_set('display_errors', '0');
ini_set('log_errors', '0');
mysqli_report(MYSQLI_REPORT_OFF);
ob_start(static function ($buffer) {
    return '';
});

register_shutdown_function(static function () use (&$reminderExited) {
    if ($reminderExited)
    {
        return;
    }
    while (ob_get_level() > 0)
    {
        ob_end_clean();
    }
    fwrite(STDERR, "Questionnaire reminder scheduler terminated safely.\n");
    exit(1);
});

function questionnaireReminderExit($message, $exitCode)
{
    global $reminderExited;
    $reminderExited = true;
    while (ob_get_level() > 0)
    {
        ob_end_clean();
    }
    fwrite($exitCode === 0 ? STDOUT : STDERR, $message . "\n");
    exit($exitCode);
}

set_exception_handler(static function (Throwable $exception) {
    questionnaireReminderExit('Questionnaire reminder scheduler terminated safely.', 1);
});

$CATSHome = realpath(dirname(__FILE__) . '/..');
if ($CATSHome === false || !chdir($CATSHome) || !is_readable('./config.php'))
{
    questionnaireReminderExit('Questionnaire reminder scheduler bootstrap failed.', 1);
}

include_once('./config.php');
if (!defined('LEGACY_ROOT'))
{
    questionnaireReminderExit('Questionnaire reminder scheduler bootstrap failed.', 1);
}
include_once(LEGACY_ROOT . '/constants.php');
include_once(LEGACY_ROOT . '/lib/DatabaseConnection.php');
include_once(LEGACY_ROOT . '/lib/NESPWorkflow.php');

if (!NESPWorkflow::isQuestionnaireReminderSchedulerEnabled())
{
    questionnaireReminderExit('Questionnaire reminder scheduler is disabled.', 0);
}

$timezoneName = trim((string) getenv('APP_TIMEZONE'));
$systemUserID = trim((string) getenv('NESP_QUESTIONNAIRE_REMINDER_SYSTEM_USER_ID'));
if ($timezoneName !== 'America/New_York' || !ctype_digit($systemUserID) || (int) $systemUserID < 1)
{
    questionnaireReminderExit('Questionnaire reminder scheduler configuration is incomplete or invalid.', 1);
}

$localHour = (int) (new DateTimeImmutable('now', new DateTimeZone('America/New_York')))->format('G');
if ($localHour !== 9)
{
    questionnaireReminderExit('Questionnaire reminder scheduler skipped a non-local candidate hour.', 0);
}

$database = DatabaseConnection::getInstance();
$admin = $database->getAssoc(sprintf(
    'SELECT user_id FROM `user` WHERE user_id = %s AND access_level >= 400 LIMIT 1',
    $database->makeQueryInteger((int) $systemUserID)
));
if (empty($admin))
{
    questionnaireReminderExit('Questionnaire reminder scheduler system user is unavailable.', 1);
}

$workflow = new NESPWorkflow($database);
$result = $workflow->sendDueQuestionnaireReminders((int) $systemUserID, 200);
if (!empty($result['error']) || (int) $result['failed'] > 0)
{
    questionnaireReminderExit('Questionnaire reminder scheduler finished with a protected failure.', 1);
}

questionnaireReminderExit(sprintf(
    'Questionnaire reminder scheduler completed: %d sent, %d skipped.',
    (int) $result['sent'],
    (int) $result['skipped']
), 0);
