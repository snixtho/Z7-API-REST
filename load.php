<?php
/**
 * @copyright 2012 - 2017 Zurvan Labs 
 * @author snixtho <snixtho@gmail.com>
 */
declare(strict_types=1);
error_reporting(E_ALL);

/*
 * Absolute path to main directory.
 * */
if (!defined('ABSPATH'))
	define('ABSPATH', dirname(__FILE__) . '/');

/*
 * Path to includes directory.
 * */
if (!defined('INCPATH'))
	define('INCPATH', 'inc/');

/*
 * Path to libraries directory.
 * */
if (!defined('LIBPATH'))
	define('LIBPATH', 'inc/lib/');

/*
 * Requested module name.
 * */
if (!defined('MODULE'))
	define('MODULE', isset($_GET['mod']) ? $_GET['mod'] : '' );

/*
 * This contains the type of request made.
 * */
if (!defined('HTTP_METHOD'))
{
	if (!isset($_SERVER['REQUEST_METHOD']))
		define('HTTP_METHOD', 'UNKNOWN');
	else
		define('HTTP_METHOD', strtoupper($_SERVER['REQUEST_METHOD']));
}

require_once ABSPATH . "settings.php";
require_once ABSPATH . INCPATH . "functions.php";

use Z7API\Core\F;

/**
 * F is a class that contains static functions for global use. Found in inc/functions.php.
 */
F::check_system_setup();

/*
 * Load neccessary classes
 * */
F::loadClass('APIException');
F::loadClass('Database');
F::loadClass('Config');

F::loadClass('APIMessage');
F::loadInterface('APIModule');
F::loadClass('IO');
F::loadClass('ModulesManager');
F::loadClass('Z7API');
F::loadClass('Event');
F::loadClass('Logs');

use Z7API\Core\{
	Config,
	APIMessage,
	APIErrors,
	Z7API,
	APIExceptionUtilities,
	IOException,
	Logs
};

try
{
	// This will install the api if its not already installed.
	require_once ABSPATH . 'install.php';
	\Z7API\Install\Install::tryInstall();

	// Check for maintaince mode before starting main program
	if (Config::get('api.maintainance') == 1)
	{
		header('Content-type: application/json');
		$msg = new APIMessage();
		$msg->set('maintainance', true);
		$msg->addError(APIErrors::MaintainceMode);
		die($msg->json());
	}
	else
	{
		$API = new Z7API();
		$API->run();
	}
}
catch (IOException $e)
{
	try
	{
		$m = new APIMessage();
		$m->addError($e->getCode());

		header('Content-type: application/json');
		echo $m->json();
	}
	catch (Error $e)
	{
		echo APIExceptionUtilities::DetailedTrace($e);
	}
}
catch (Exception $e)
{
	echo APIExceptionUtilities::DetailedTrace($e);
}
catch (Error $e)
{
	$stacktrace = APIExceptionUtilities::DetailedTrace($e);
	Logs::systemError("An error occured:\n\n" . $stacktrace);
	echo $stacktrace;
}