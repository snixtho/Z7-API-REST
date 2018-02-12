<?php
/**
 * @copyright 2012 - 2017 Zurvan Labs 
 * @author snixtho <snixtho@gmail.com>
 */
declare(strict_types=1);

namespace Z7API\Core;

/**
 * Logging system.
 * 
 * To create a proper database table for a logging system, it must include at least
 * the fields defined in the systemlogs table. Other than that, you can define
 * any extra fields if neccessary. Also note that the table name must be named the
 * exact same was as the name of the logging system you will later add with the
 * Logs::addLoggingSystem() function and ending with 'logs'. For example, if your
 * logging system is called 'user', then the proper database table name would be 'userlogs'.
 */
class Logs {
	/**
	 * Set up the logging system.
	 */
	public static function setup() {
		Database::instance()->selectdb('api');
		$table_prefix = Config::get('db.table_prefix.api');

		// Create database tables if they don't already exist.
		if (!Database::instance()->tableExists($table_prefix . 'systemlogs'))
		{
			$query = 'CREATE TABLE ' . $table_prefix . 'systemlogs(
				`id` INT NOT NULL AUTO_INCREMENT,
				`level` ENUM(\'debug\', \'info\', \'warning\', \'error\', \'critical\') NOT NULL,
				`message` TEXT NOT NULL,
				`created` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
				PRIMARY KEY(`id`)
			) ENGINE = InnoDB CHARACTER SET=utf8 COLLATE=utf8_bin';

			Database::instance()->query($query);
		}
	}

	/**
	 * The singleton instance holder.
	 * 
	 * @var Logs
	 */
	private static $logInstance = null;

	/**
	 * Holds a list of possible log systems.
	 * @var array
	 */
	private $logSystems = null;

	/**
	 * Get the instance of the logging system.
	 * 
	 * @return Logs The instance of the logging system.
	 */
	public static function instance() {
		if (static::$logInstance == NULL)
			static::$logInstance = new Logs();
		return static::$logInstance;
	}

	/**
	 * Variable for enabling/disabling logging of debug messages.
	 * 
	 * @var boolean
	 */
	private $debugLogging = false;

	function __construct() {
		$this->logSystems = array();

		$this->addLoggingSystem('system');
	}

	
	/**
	 * Enable or disable logging of debug messages.
	 * 
	 * @param bool $enable Set to true to enabled debug messages, false to disable.
	 */
	public function setDebugLogging(bool $enable) {
		$this->debugLogging = $enable;
	}

	/**
	 * Add a new logging system.
	 * 
	 * @param stirng $name The logging system's name.
	 * @param array  $args Potential extra information to be passed for each log.
	 */
	public function addLoggingSystem(string $name, array $args=array()) {
		if (!array_key_exists($name, $this->logSystems))
		{
			$this->logSystems[$name] = $args;
		}
	}

	/**
	 * Create a new log in the logging system's database table.
	 * 
	 * @param string $level     The logging level: debug, info, warning, error, critical
	 * @param string $logSystem The logging system name.
	 * @param string $message   The message to be sent.
	 * @param array $extra      Potentially extra information for the log. It is specific per logging system.
	 */
	public function addLog(string $level, string $logSystem, string $message, array $extra) {
		Database::instance()->selectdb('api');
		$table_prefix = Config::get('db.table_prefix.api');

		$options = array(
			'level' => $level,
			'message' => $message
		);

		if (!array_key_exists($logSystem, $this->logSystems) || count($this->logSystems[$logSystem]) != count($extra))
		{
			throw new BadFunctionCallException("'$funcName()' is undefined.");
		}

		foreach ($this->logSystems[$logSystem] as $index => $option)
		{
			$options[$option] = $extra[$index];
		}

		return Database::instance()->simpleInsert($table_prefix.$logSystem.'logs', $options);
	}

	public static function __callStatic(string $funcName, array $extra) {
		if (count($extra) == 0)
		{
			throw new \BadFunctionCallException("'$funcName()' must have at least 1 argument.");
		}

		$result = preg_match('/^([a-zA-Z_]+)(Debug|Info|Warning|Error|Critical)$/', $funcName, $matches);

		if (!$result)
		{
			throw new \BadFunctionCallException("'$funcName()' is undefined.");
		}

		$logSystem = $matches[1];
		$level = strtolower($matches[2]);
		$message = $extra[0];

		unset($extra[0]);

		return static::instance()->addLog($level, $logSystem, $message, $extra);
	}
};
