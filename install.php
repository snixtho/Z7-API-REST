<?php
/**
 * @copyright 2012 - 2017 Zurvan Labs 
 * @author snixtho <snixtho@gmail.com>
 */
declare(strict_types=1);

namespace Z7API\Install;

use Z7API\Core\{
	Config,
	MySQLException,
	Logs
};

class Install {
	/**
	 * Set default configurations for the script.
	 */
	private static function set_default_options()
	{
		\Z7API\Core\Config::set('api.maintainance', true);
	}

	/**
	 * Tries to setup neccesary things for the API to run.
	 */
	public static function tryInstall()
	{
		try
		{
			Config::setup();
			Logs::setup();
		}
		catch (MySQLException $e)
		{
			die("[Complete Fatal Error] Could not run further because: " . $e->getMessage());
		}

		include(ABSPATH.'config_cache.php');

		// add cache options to global scope $config variable.
		foreach ($config as $key => $value)
		{
			$GLOBALS['config'][$key] = $value;
		}

		// Synchronize cached config with the database config.
		Config::updateCache();

		if (!file_exists(ABSPATH.'install_lock'))
		{
			self::set_default_options();
			touch('install_lock');
		}
	}
};
