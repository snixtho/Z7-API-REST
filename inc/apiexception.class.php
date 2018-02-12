<?php
/**
 * @copyright 2012 - 2017 Zurvan Labs 
 * @author snixtho & TNTworks <snixtho@gmail.com, tiger545454@gmail.com>
 */
declare(strict_types=1);

namespace Z7API\Core;

/**
 * Import Exception to use as base class
 */
use Exception;

/**
 * Class which holds helping functions
 */
class APIExceptionUtilities {
	/**
	 * Returns detailed trace in string format. Supports chained exceptions
	 *
	 * Source: http://php.net/manual/en/exception.gettraceasstring.php#114980
	 *
	 * @param Exception      $e    Exception to trace
	 * @param array|null     $seen Argument used internally by this function using recursion. Do not use it.
	 * @return array|string        Returns human-readable Java like exception trace string
	 */
	public static function DetailedTrace($e, array $seen = null) : string {
		$starter = $seen ? 'Caused by: ' : '';
		$result = array();
		if (!$seen) {
			$seen = array();
		}
		$trace = $e->getTrace();
		$prev = $e->getPrevious();
		$result[] = sprintf('%s%s: Message:(%s) Code:(%d)', $starter, get_class($e), $e->getMessage(), $e->getCode());
		$file = $e->getFile();
		$line = $e->getLine();
		while (true) {
			$current = "$file:$line";
			// If the call chain is same from this point, e.g. was already printed by previous exception, just print the number and continue to next exception
			if (is_array($seen) && in_array($current, $seen)) {
				$result[] = sprintf(' ... %d more', count($trace) + 1);
				break;
			}
			
			$result[] = sprintf(
				' at %s%s%s(%s%s%s)',
				// Format namespace and class if there is any, replace \ with .
				count($trace) && array_key_exists('class', $trace[0]) ? str_replace('\\', '.', $trace[0]['class']) : '',

				// Add dot after the namespace.class if there is function in the path
				count($trace) && array_key_exists('class', $trace[0]) && array_key_exists('function', $trace[0]) ? '.' : '',

				// Check if the Exception was throw in main or function
				count($trace) && array_key_exists('function', $trace[0]) ? str_replace('\\', '.', $trace[0]['function']) : '(main)',

				// Formats to filename:line
				$line === null ? $file : basename($file),
				$line === null ? '' : ':',
				$line === null ? '' : $line
			);

			// Adds seen functions so they are not printed twice
			if (is_array($seen)) {
				$seen[] = "$file:$line";
			}
			if (!count($trace)) {
				break;
			}

			// Set file and line of parent function
			$file = array_key_exists('file', $trace[0]) ? $trace[0]['file'] : 'Unknown Source';
			$line = array_key_exists('file', $trace[0]) && array_key_exists('line', $trace[0]) && $trace[0]['line'] ? $trace[0]['line'] : null;
			array_shift($trace);
		}
		$result = join("\n", $result);

		// Recursion for chained exceptions
		if ($prev) {
			$result .= "\n" . APIExceptionUtilities::DetailedTrace($prev, $seen);
		}

		// Return formated string
		return $result;
	}
}

/**
 * Base class for custom exceptions
 */
class APICustomException extends Exception {
	/**
	 * Constructor of custom exceptions
	 *
	 * @param int|integer    $code    Code which the thrown exception holds
	 * @param string|null    $message Message which the thrown exception holds
	 * @param Exception|null $e       Previous exception in the exception chain
	 */
	public function __construct(int $code=0, string $message=null, Exception $e=null) {
		if ($message === null) {
			$message = "*Unspecified Message*";
		}
		parent::__construct($message, $code, $e);
	}

	/**
	 * Returns the exception in human-readable format
	 *
	 * ClassName: Message:(Exception Message) Code:(Exception Code)
	 * in FileName(Line)
	 * TraceString
	 * @return string The formated string of the exception
	 */
	public function __toString() {
		return sprintf('%s: Message:(%s) Code:(%d)%sin %s(%s)%s%s', get_class($this), $this->message, $this->code, "\n", $this->file, $this->line, "\n", $this->getTraceAsString());
	}
}

/**
 * User created custom exceptions
 *
 * Example: class APIexample_exception extends APICustomException {}
 */

/**
 * Main exception for modules
 */
class ModuleException extends APICustomException {}
/**
 * Exception for module classes not implementing IAPIModule.
 */
class ModuleInvalidInterfaceException extends ModuleException {}
/**
 * Exception used for shutting down the script on the spot.
 */
class ModuleRequestShutdownException extends ModuleException {}
/**
 * Exception used for overriding the output.
 */
class ModuleOverrideOutputException extends ModuleException {
	/**
	 * Holds custom data to be outputted.
	 * @var null
	 */
	private $_data = NULL;

	function __construct($data=NULL) {
		$this->_data = $data;
		parent::__construct();
	}

	/**
	 * Returns the data passed into the constructor.
	 * 
	 * @return mixed Value of $_data.
	 */
	public function getData() {
		return $this->_data;
	}
}
/**
 * Module does not exist.
 */
class ModuleDoesNotExistException extends ModuleException {}


/**
 * Main exception for mysql
 */
class MySQLException extends APICustomException {}
/**
 * Exception for param binding fails.
 */
class MySQLParamBindingException extends MySQLException {}
/**
 * Exception for param binding fails.
 */
class MySQLQueryException extends MySQLException {}
/**
 * Exception for param binding fails.
 */
class MySQLPreparedStatementException extends MySQLException {}

/**
 * Main exception for the database class.
 */
class DatabaseException extends APICustomException {}
/**
 * Exception for when a database already exists.
 */
class DatabaseAlreadyExistsException extends DatabaseException {}


/**
 * Main exception for passwords.
 */
class PasswordException extends APICustomException {}
/**
 * Exception for wrong password hash format.
 */
class PasswordWrongFormatException extends PasswordException {}


/**
 * Main exception for events.
 */
class EventException extends APICustomException {}
/**
 * Exception for when a callback function is not callable (not a function).
 */
class EventNotCallableException extends EventException {}

/**
 * Main exception for input/output.
 */
class IOException extends APICustomException {}
/**
 * Exception for when parsing of json failed.
 */
class IOInvalidJSONException extends IOException {}
/**
 * Exception for when IO::validate has detected an invalid value.
 */
class IOValidationException extends IOException {
	/**
	 * The name of the value that is invalid.
	 * @var String
	 */
	private $valName;

	function __construct(string $valueName, int $code, string $message="") {
		parent::__construct($code, $message);

		$this->valName = $valueName;
	}

	/**
	 * Get the name of the invalid value.
	 * 
	 * @return String The name of the invalid value.
	 */
	public function getValueName() {
		return $this->valName;
	}
}