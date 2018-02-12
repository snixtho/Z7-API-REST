<?php
/**
 * @copyright 2012 - 2017 Zurvan Labs 
 * @author snixtho <snixtho@gmail.com>
 */
declare(strict_types=1);

namespace Z7API\Core;

/**
 * Manages errors for messages.
 */
class APIErrors {
	const Unknown = 500;					// Generic purpose, Unknown error
	const InvalidJson = 503;				// Failed to parse an encoded-json string.
	const MaintainceMode = 504;				// API is under maintainance.
	const IOArgumentRequired = 601;			// Argument is required.
	const IOInvalidArgumentType = 602;		// An argument has an invalid type.
	const IOArgumentMismatch = 603;			// An argument does not match the specified values.
	const InvalidModule = 701;				// Invalid API module requested
	const InvalidAction = 702;				// Invalid action requested
	const InvalidRequestMethod = 703;		// Unknown request method used 
	const MissingArguments = 801;			// The request is missing arguments
	const EventGETNotImplemented = 810;  	// onGET not implemented.
	const EventPOSTNotImplemented = 811;  	// onPOST not implemented.
	const EventPUTNotImplemented = 812;  	// onPUT not implemented.
	const EventDELETENotImplemented = 813; 	// onDELETE not implemented.
	const EventPATCHNotImplemented = 814;  	// onPATCH not implemented.
	const EventOPTIONSNotImplemented = 815; // onOPTIONS not implemented.

	/**
	 * Instance of the singleton.
	 * 
	 * @var APIErrors
	 */
	private static $apierrors_ins = NULL;

	/**
	 * Holds an array of error strings at the position that represents their error code.
	 * 
	 * @var array
	 */
	private $error_db = array();

	/**
	 * Generator that yields all built-in error codes.
	 */
	public static function builtinErrors() {
		yield self::Unknown => 						'Unknown Internal Error.';
		yield self::InvalidJson => 					'Parsing JSON failed.';
		yield self::MaintainceMode => 				'The API is under maintainance.';
		yield self::IOArgumentRequired =>			'%s: Argument is required and missing.';
		yield self::IOInvalidArgumentType =>		'%s: Argument type is invalid.';
		yield self::IOArgumentMismatch =>			'%s: Argument does not match any of the allowed values.';
		yield self::InvalidModule => 				'The requested module is invalid.';
		yield self::InvalidAction => 				'The requested action is invalid.';
		yield self::InvalidRequestMethod => 		'Invalid request for this action.';
		yield self::MissingArguments => 			'The request is missing one or more arguments.';
		yield self::EventGETNotImplemented => 		'GET is not a supported method for this resource.';
		yield self::EventPOSTNotImplemented => 		'POST is not a supported method for this resource.';
		yield self::EventPUTNotImplemented => 		'PUT is not a supported method for this resource.';
		yield self::EventDELETENotImplemented =>	'DELETE is not a supported method for this resource.';
		yield self::EventPATCHNotImplemented => 	'PATCH is not a supported method for this resource.';
		yield self::EventOPTIONSNotImplemented =>	'OPTIONS is not a supported method for this resource.';
	}

	/**
	 * @return APIErrors
	 */
	public static function instance() : APIErrors {
		if (static::$apierrors_ins == NULL)
			static::$apierrors_ins = new APIErrors();

		return static::$apierrors_ins;
	}

	function __construct() {
		// reserve built-in errors first
		foreach (self::builtinErrors() as $errorCode => $errorStr)
		{
			$this->addError($errorCode, $errorStr);
		}
	}

	/**
	 * Add a new error to the error database.
	 * 
	 * @param int The unique error code.
	 * @param string The error string.
	 */
	public function addError(int $errorCode, string $errorStr) {
		if ($errorCode >= 0 && !isset($this->error_db[$errorCode]))
		{
			$this->error_db[$errorCode] = $errorStr;
		}
	}

	/**
	 * Remove an error from the error database.
	 * 
	 * @param  int Error code to remove
	 */
	public function deleteError(int $errorCode) {
		if ($errorCode >= 0 && !isset($this->error_db[$errorCode]))
		{
			unset($this->error_db[$errorCode]);
		}
	}

	/**
	 * Get the error string representing the provided error code.
	 * 
	 * @param  int The error code to retrieve the string from.
	 * @return string The string representing the error code. NULL is returned if $errorCode is not found.
	 */
	public function getErrorString(int $errorCode) : string {
		if (isset($this->error_db[$errorCode]))
		{
			return $this->error_db[$errorCode];
		}
		else
		{
			return NULL;
		}
	}

	/**
	 * A generator that yields a list of error code/string pairs.
	 * If $start is bigger than -1, the generator yields errors bigger or equal to $start.
	 * If both $start and $end is bigger than -1, the generator
	 * yields errors between and including $start and $end.
	 * 
	 * @param  Starting error code.
	 * @param  Ending error code.
	 * @return void Returns nothing. Is a generator.
	 */
	public function getErrors($start=-1, $end=-1) {
		if ($start > -1)
		{
			foreach ($this->error_db as $code => $str)
			{
				if ($end < 0)
				{
					if ($code >= $start)
					{
						yield $code => $str;
					}
				}
				else
				{
					if ($code >= $start && $code <= $end)
					{
						yield $code => $str;
					}
				}
			}
		}
		else
		{
			foreach ($this->error_db as $code => $str)
			{
				yield $code => $str;
			}
		}
	}
};

/**
 * Holds error codes and strings.
 */
class APIMessage_Error {
	/**
	 * The error code representing the error.
	 * 
	 * @var int
	 */
	public $errorCode;

	/**
	 * The error string, telling you what the error is.
	 * 
	 * @var string
	 */
	public $errorStr;

	function __construct(int $key, string $str) {
		$this->errorCode = $key;
		$this->errorStr = $str;
	}
};

/**
 * API message manager.
 */
class APIMessage {
	/**
	 * Contains a list of APIMessage_Error objects to represent possible errors occured.
	 * 
	 * @var array
	 */
	public $errors = array();

	/**
	 * Generator which yields the reserved keys for the class.
	 */
	private static function reservedKeys() {
		yield 'errors';		// Holds error messages
		yield 'apikey';		// To prevent modules from sending the api key through the standard way
		yield 'secret';		// To prevent modules from sending the secret through the standard way
	}
	
	/**
	 * Returns true if the provided key is NOT a reserved key. Returns false if it is.
	 * 
	 * @param  string The key to check.
	 * @return boolean True of key isnt reserved. False if it is.
	 */
	private static function isNotReserved(string $checkKey) {
		$reserved = true;
		
		foreach (self::reservedKeys() as $key) {
			if ($key == $checkKey)
			{
				$reserved = false;
				break;
			}
		}
		
		return $reserved;
	}
	
	/**
	 * Assigns all variables from an object to the message.
	 * 
	 * @param  mixed The object to merge with.
	 */
	private function objectAdd($obj) {
		if (!is_array($obj))
		{
			foreach ($obj as $key => $value) {
				$this->set((string)$key, $value);
			}
		}
	}

	/**
	 * Check whether a specific key has been set in the message.
	 * @param  string  $key The key to check.
	 * @return boolean      True if the key has been set, false if not.
	 */
	public function has(string $key) {
		return isset($this->{$key});
	}

	/**
	 * Sets a message value.
	 * 
	 * @param string The key of the new value
	 * @param mixed The value of the key.
	 *
	 */
	public function set(string $key, $value) {
		if (!is_callable($value) && self::isNotReserved($key))
		{
			$this->{$key} = $value;
		}
	}

	/**
	 * Gets a value from the specified key.
	 * 
	 * @param  string The key to use.
	 * @return mixed The value associated with the provided key.
	 */
	public function get(string $key) {
		if (isset($this->{$key}))
			return $this->{$key};
		else
			return NULL;
	}

	/**
	 * Deletes a key from the message and returns its value.
	 * 
	 * @param  string The key to remove.
	 * @return mixed The value of the key.
	 */
	public function unset(string $key) {
		$value = NULL;
		
		if (isset($this->{$key}) && self::isNotReserved($key))
		{
			$value = $this->{$key};
			unset($this->{$key});
		}
		
		return $value;
	}
	
	/**
	 * This function sets the api key value of the message.
	 *  
	 * @param string The API key, must be exactly 32 characters in length.
	 */
	public function setAPIKey(string $key) {
		// make sure it is 64 characters long before setting it.
		$keylen = Config::get('misc.apikeylength');
		if (isset($key[$keylen - 1]) && !isset($key[$keylen]))
		{
			$this->{'apikey'} = $key;
		}
	}

	/**
	 * This function sets the secret key value of the message.
	 * @param string $secret The secret value.
	 */
	public function setSecretKey(string $secret) {
		$keylen = Config::get('misc.secretlength');
		if (isset($secret[$keylen - 1]) && !isset($secret[$keylen]))
		{
			$this->{'secret'} = $secret;
		}
	}

	/**
	 * Adds an error to the message. If an Unknown error is passed, no error is added.
	 * 
	 * @param int The error code representing the error you want to add.
	 */
	public function addError(int $errorCode) {
		// ignore if already added
		foreach ($this->errors as $error)
		{
			if ($error->errorCode === $errorCode)
			{
				return;
			}
		}

		// add the new error
		array_push( $this->errors,
					new APIMessage_Error(
						$errorCode, 
						APIErrors::instance()->getErrorString($errorCode)
					));
	}

	/**
	 * Add an error that contains a more customized formatted message.
	 * 
	 * @param int    $errorCode The error code.
	 * @param mixed $args      Arguments containing values for the formatted message.
	 */
	public function addErrorFormat(int $errorCode, ...$args) {
		// ignore if already added
		foreach ($this->errors as $error)
		{
			if ($error->errorCode === $errorCode)
			{
				return;
			}
		}

		// add the new error
		array_push( $this->errors,
					new APIMessage_Error(
						$errorCode, 
						sprintf(APIErrors::instance()->getErrorString($errorCode), ...$args)
					));
	}
	
	/**
	 * Returns a json encoded string of the message.
	 *
	 * @param pretty Whether to return the encoded-json in a nice format.
	 * @return string An string of encoded json made from the object.
	 */
	public function json(bool $pretty = false) : string {
		return json_encode($this, $pretty ? JSON_PRETTY_PRINT : 0);
	}

	/**
	 * Adds values from a raw json string.
	 * 
	 * @param string An string containing only encoded json.
	 */
	public function addFromJson(string $jsonRaw) {
		$obj = json_decode($jsonRaw);
		
		if ($obj != NULL)
		{
			$this->objectAdd($obj);

			// set specials
			if (isset($obj->apikey))
			{
				$this->{'apikey'} = $obj->apikey;
			}

			if (isset($obj->secret))
			{
				$this->{'secret'} = $obj->secret;
			}
		}
		else
		{
			throw new IOInvalidJsonException(APIErrors::InvalidJson, APIErrors::instance()->getErrorString(APIErrors::InvalidJson));
		}
	}

	/**
	 * Adds values from another APIMessage message.
	 * 
	 * @param APIMessage The other message to merge with.
	 */
	public function addFromMessage(APIMessage $msg) {
		if ($msg != NULL)
		{
			$this->objectAdd($msg);

			// add errors
			foreach ($msg->errors as $error)
			{
				$this->errors[] = $error;
			}
			
			// set specials
			if (isset($msg->apikey))
			{
				$this->{'apikey'} = $msg->apikey;
			}

			if (isset($msg->secret))
			{
				$this->{'secret'} = $msg->secret;
			}
		}
	}
};
