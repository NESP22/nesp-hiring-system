<?php
/*
 * Shared bootstrap for directly requested AJAX endpoint scripts.
 */

$rootDirectory = dirname(__DIR__);

if (!defined('LEGACY_ROOT'))
{
    define('LEGACY_ROOT', $rootDirectory);
}

include_once($rootDirectory . '/config.php');
include_once(LEGACY_ROOT . '/constants.php');
include_once(LEGACY_ROOT . '/lib/DatabaseConnection.php');
include_once(LEGACY_ROOT . '/lib/Session.php');
include_once(LEGACY_ROOT . '/lib/AJAXInterface.php');

?>
