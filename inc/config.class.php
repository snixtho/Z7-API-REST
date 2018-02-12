<?php
/**
 * @copyright 2012 - 2017 Zurvan Labs 
 * @author snixtho <snixtho@gmail.com>
 */
declare(strict_types=1);

namespace Z7API\Core;

/**
 * This singleton class manages global configurations for the api system.
 */
class Config {
	/**
	 * Checks if the database config table is correctly defined.
	 */
	private static function check_config_table() {
		Database::instance()->selectdb('api');
		$table_prefix = Config::get('db.table_prefix.api');

		// Create config table if it doesn't already exist.
		if (!Database::instance()->tableExists($table_prefix . 'config'))
		{
			$query = 'CREATE TABLE ' . $table_prefix . 'config(
				`id` INT NOT NULL AUTO_INCREMENT,
				`key` varchar(512) NOT NULL,
				`value` TEXT NOT NULL,
				`type` VARCHAR(64) NOT NULL,
				`created` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
				`lastupdate` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
				PRIMARY KEY(`id`)
			) ENGINE = InnoDB CHARACTER SET=utf8 COLLATE=utf8_bin';

			Database::instance()->query($query);
		}
	}

	/**
	 * Converts the given string into the specified type.
	 * @param  string $type  Type to convert into.
	 * @param  string $value String to convert.
	 * @return mixed         The converted value.
	 */
	private static function get_correct_type(string $type, string $value) {
		switch ($type)
		{
			case 'string':
				$value = (string)$value;
				break;
			case 'integer':
				$value = intval($value);
				break;
			case 'boolean':
				$value = (boolean)$value;
				break;
			case 'double':
				$value = doubleval($value);
				break;
			case 'array':
				$value = unserialize($value);
				break;
			case 'object':
				$value = unserialize($value);
				break;
			case 'resource':
				$value = $value; // this should be impossible to add to the db in the first place
				break;
			case 'NULL':
				$value = NULL;
				break;
			case 'unknown type':
				$value = $value; // should never happen?
				break;
			default:
				$value = (string)$value;
		}

		return $value;
	}

	/**
	 * Synchronizes the local cache with the online database.
	 */
	public static function updateCache() {
		global $config;

		Database::instance()->selectdb('api');
		$result = Database::instance()->simpleSelect(Config::get('db.table_prefix.api').'config', 'key,value,type', '', NULL, array('limit' => 10));
		$pos = 10;

		while ($result && $result->num_rows > 0)
		{
			while ($row = $result->fetch_assoc())
			{
				$value = self::get_correct_type($row['type'], $row['value']);
				$cachedValue = $config;
				$path = explode('.', $row['key']);

				foreach ($path as $subPath)
				{
					$cachedValue = @$cachedValue[$subPath]; 
				}

				if ($cachedValue == NULL || $cachedValue != $value)
				{
					Config::set($row['key'], $value);
				}
			}

			$result = Database::instance()->simpleSelect(Config::get('db.table_prefix.api').'config', 'key,value,type', '', NULL, array('limit' => 10, 'limitstart' => $pos));
			$pos += 10;
		}
	}

	/**
	 * Check if a config key name is valid for use.
	 * @param  string $key Key string to check
	 * @return bool        True if it is valid, false if not.
	 */
	private static function key_name_is_valid(string $key) {
		return preg_match('/[\W]/', str_replace('.', '', $key)) != 1;
	}

	/**
	 * Add new config to cache file.
	 * 
	 * @param  string $key   Key of the option.
	 * @param  mixed $value  Value of the option.
	 */
	private static function save_new_cache_option(string $key, $value) {
		if (self::key_name_is_valid($key))
		{
			echo "saving new: " + $option;
			
			global $config;

			$cfgVar = $config;
			$path = explode('.', $key);
			$save_str = '$config';

			foreach ($path as $option)
			{
				$cfgVar = &$cfgVar[$option];
				$save_str .= '[\'' . $option . '\']';
			}

			$save_str .= ' = ' . var_export($value, true) . ";\n";
			$cfgVar = $value;

			$f = fopen(ABSPATH.'config_cache.php', 'a');
			fwrite($f, $save_str);
			fclose($f);
		}
	}

	/**
	 * Make the config handler ready for use.
	 */
	public static function setup() {
		// Checks whether the database table for config is fine.
		self::check_config_table();

		// reload config if requested.
		if (file_exists(ABSPATH.'reload_config'))
		{
			unlink(ABSPATH.'config_cache.php');
			unlink(ABSPATH.'reload_config');
		}

		// Create cache file if it doesnt exist.
		if (!file_exists(ABSPATH.'config_cache.php'))
		{
			$configFile = fopen(ABSPATH.'config_cache.php', 'w');
			fwrite($configFile, "<?php\n
/************************************************************
**** AUTO-GENERATED CACHE FILE. DO _NOT_ EDIT THIS FILE. ****
************************************************************/\n\n");
			fclose($configFile);
		}
	}

	/**
	 * Assign a config key to a value.
	 * 
	 * @param string $option Key to use.
	 * @param mixed  $value  Value to assign.
	 */
	public static function set(string $option, $value) {
		if (!self::key_name_is_valid($option))
		{
			// EXCEPTION: InvalidConfigKeyException, The key name is an invalid config key.
			echo 'InvalidConfigKeyException, The key name is an invalid config key.', PHP_EOL;
			return;
		}

		global $config;
		$tableName = Config::get('db.table_prefix.api').'config';

		Database::instance()->selectdb('api');
		$result = Database::instance()->simpleSelect($tableName, 'id', '`key`=?', array($option));

		// serialize if array
		if (is_array($value) || is_object($value))
			$dbValue = serialize($value);
		else if ($value === NULL)
			return; // ignore null values.
		else
			$dbValue = (string)$value;

		if ($result && $result->num_rows == 1)
		{ // option already exists, just update it
			Database::instance()->simpleUpdate($tableName, array('value' => $dbValue, 'type' => gettype($value)), '`key`=?', array($option));
		}
		else
		{
			Database::instance()->simpleInsert($tableName, array('key' => $option, 'value' => $dbValue, 'type' => gettype($value)));
		}

		// Set global $config and update config_cache.php with the new data.
		$tree = explode('.', $option);
		$cfgOption = &$config;
		$cfgOptionStr = '$config';
		foreach ($tree as $subOption)
		{
			$cfgOption = &$cfgOption[$subOption];
			$cfgOptionStr .= '[\'' . $subOption . '\']';
		}

		$cfgOptionStr .= ' = ';

		$settingsFile = fopen(ABSPATH.'config_cache.php', 'r');
		$contents = fread($settingsFile, filesize(ABSPATH.'settings.php'));
		fclose($settingsFile);
		$settingsFile = fopen(ABSPATH.'config_cache.php', 'w');

		if (!isset($cfgOption))
		{
			$contents .= $cfgOptionStr . var_export($value, true) . ";\n";
		}
		else
		{
			$contents = str_replace($cfgOptionStr.var_export($cfgOption, true).';', $cfgOptionStr.var_export($value, true).';', $contents);
		}

		fwrite($settingsFile, $contents);
		fclose($settingsFile);

		$cfgOption = $value;
	}

	/**
	 * Get a config option value.
	 * The keys are passed in a string like 'option.suboption.subsuboption'.
	 * A config option can contain an infinite amount of suboptions.
	 * 
	 * @return  mixed Value of the config option. Returns NULL if $option does not exist.
	 */
	public static function get(string $option) {
		global $config;

		$confVar = $config;
		$path = explode('.', $option);

		foreach ($path as $subOption)
		{
			$confVar = @$confVar[$subOption];
		}

		if ($confVar === NULL)
		{
			Database::instance()->selectdb('api');
			$tprefix = Config::get('db.table_prefix.api');
			$result = Database::instance()->simpleSelect($tprefix.'config', 'value,type', '`key`=?', array($option));

			if ($result && $result->num_rows == 1)
			{
				$data = $result->fetch_assoc();
				$confVar = self::get_correct_type($data['type'], $data['value']);

				self::save_new_cache_option($option, $confVar);
			}
			else
			{
				return NULL;
			}
		}

		return $confVar;
	}

	/**
	 * Delete a config key/value pair.
	 * 
	 * @param  string $key Key to delete.
	 */
	public static function unset(string $key) {
		global $config;

		Database::instance()->selectdb('api');
		Database::instance()->simpleDelete(Config::get('db.table_prefix.api').'config', '`key`=?', array($key));

		$value = self::get($key);
		if ($value != NULL)
		{
			$cfgFile = fopen(ABSPATH.'config_cache.php', 'r');
			$contents = fread($cfgFile, filesize(ABSPATH.'config_cache.php'));
			fclose($cfgFile);
		
			$match_str = '$config';
			$path = explode('.', $key);
			$cfgOption = $config;
			foreach ($path as $subpath) {
				$match_str .= '[\'' . $subpath . '\']';
				$cfgOption = &$cfgOption[$subpath];
			}

			$match_str .= ' = ' . var_export($cfgOption, true) . ";\n";

			unset($cfgOption);
			$cfgFile = fopen(ABSPATH.'config_cache.php', 'w');
			$contents = str_replace($match_str, '', $contents);
			fwrite($cfgFile, $contents);
			fclose($cfgFile);
		}
	}
}