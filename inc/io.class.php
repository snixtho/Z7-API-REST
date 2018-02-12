<?php
/**
 * @copyright 2012 - 2017 Zurvan Labs 
 * @author snixtho <snixtho@gmail.com>
 */
declare(strict_types=1);

namespace Z7API\Core;

/**
 * Input/Output Handler.
 */
class IO {
	/**
	 * A generator that yields all supported HTTP methods of the API.
	 * */
	public static function HTTPMethods() {
		 yield 'GET';		// Used for retrieving data
		 yield 'POST';		// Used for sending data
		 yield 'PUT';		// Used for creating/inserting data
		 yield 'DELETE';	// Used for removing data
		 yield 'PATCH';		// Used for updating data
		 yield 'OPTIONS';	// Used for retrieving possible API methods/API doc.
	}
	
	/**
	 * Contains the raw input data from the body.
	 * 
	 * @type string
	 * */
	private $raw_data;

	/**
	 * Input parsed and added to an APIMessage object.
	 * 
	 * @var APIMessage
	 */
	private $parsed_input_msg;
	
	/**
	 * Is true if the input is empty.
	 * 
	 * @type bool
	 * */
	private $input_empty;

	function __construct() {
		$this->createBuffer();
		
		// check for correct http method
		$valid = false;
		foreach (self::HTTPMethods() as $method)
		{
			if ($method == HTTP_METHOD)
			{
				$valid = true;
				break;
			}
		}
		
		if (!$valid)
		{
			$msg = new APIMessage();
			$msg->addError(APIErrors::InvalidRequestMethod);
			echo $msg->json();
			$this->flush();
			exit(-1);
		}
		
		// read input stream to retrieve the raw body data
		$this->raw_data = file_get_contents("php://input");
		$this->input_empty = empty($this->raw_data);
		
		// parse the raw data into an APIMessage
		$this->parsed_input_msg = new APIMessage();
		if ($this->raw_data !== "")
		{
			$this->parsed_input_msg->addFromJson($this->raw_data);
		}
	}
	
	/**
	 * Returns a reference to the raw body data.
	 * It is a good idea to accept this reference
	 * because the raw data might be big. Hence
	 * could cause memory problems.
	 * 
	 * @return string
	 */
	public function &getRawData() : string {
		return $this->raw_data;
	}
	
	/**
	 * Returns true if the body data is empty. False if it has contents.
	 * 
	 * @return bool
	 * */
	public function inputIsEmpty() : bool {
		return $this->input_empty;
	}
	
	/**
	 * Returns the input message in the form of an APIMessage object.
	 * 
	 * @return APIMessage
	 * */
	public function getInputMessage() : APIMessage {
		return $this->parsed_input_msg;
	}

	/**
	 * Create and initialize an output buffer.
	 */
	public function createBuffer() {
		ob_start();
	}

	/**
	 * Add string data to the output buffer.
	 * 
	 * @param mixed $str String to add.
	 */
	public function write($str) {
		echo $str;
	}

	/**
	 * Flushes and writes all data to output stream.
	 */
	public function flush() {
		ob_end_flush();
	}

	/**
	 * Cleans the output buffer and dont write data.
	 */
	public function eraseOutput() {
		ob_end_clean();
	}

	/**
	 * Sets the output content type.
	 * 
	 * @param string $contentType Type of the output content.
	 */
	public function setContentType(string $contentType) {
		header('Content-type: ' . $contentType);
	}

	/**
	 * Returns whether a value can be converted to a specific type without losing information.
	 * 
	 * @param  string $type  The type the value will be checked for conversion. Types are integer, string, boolean, double
	 * @param  mixed  $value The value to check.
	 * @return boolean       True if it can be converted without losing information, false if not.
	 */
	public static function ValueCanBe(string $type, $value) {
		switch ($type)
		{
			case 'integer':
				if (is_int($value)) return true;
				return preg_match('/^((\-)?[0-9]+(\.0)?)$/', (string)$value);
			case 'string':
				return true;
			case 'boolean':
				if (is_bool($value)) return true;
				return preg_match('/^(true|false|0|1|0\.0|1\.0)$/i', (string)$value);
			case 'double':
				if (is_double($value) || is_float($value)) return true;
				return preg_match('/^((\-)?[0-9]+(\.[0-9]+)?)$/', (string)$value);
			case 'float':
				if (is_double($value) || is_float($value)) return true;
				return preg_match('/^((\-)?[0-9]+(\.[0-9]+)?)$/', (string)$value);
			case 'array':
				return is_array($value);
		}

		return false;
	}

	/**
	 * Convert a value to a specific type.
	 * 
	 * @param string $type  The type represented by a string. Can be: integer, string, boolean, double, float
	 * @param mixed  $value The value to be converted.
	 * @return mixed The converted value.
	 */
	public static function ConvertTo(string $type, $value) {
		switch ($type)
		{
			case 'integer':
				return intval($value);
			case 'string':
				return (string)$value;
			case 'boolean':
				if (is_string($value) && (strtolower($value) == "false" || strtolower($value) == "0.0")) return false;
				return boolval($value);
			case 'double':
				return doubleval($value);
			case 'float':
				return floatval($value);
		}

		return $value;
	}

	/**
	 * Validates the input for empty and invalid types,
	 * and converts to proper types of possible. It will replace
	 * the specified input message only with the defined values
	 * which are defined in the options.
	 * 
	 * @param APIMessage &$inpMsg The message input to validate.
	 * @param array      $options Options for each input value.
	 */
	public static function Validate(APIMessage &$inpMsg, array $options, bool $removeUnspecified=true) {
		// remove unwanted input values
		foreach ($inpMsg as $name => $v)
		{
			if (!array_key_exists($name, $options) && $removeUnspecified)
			{
				$inpMsg->unset($name);
			}
		}

		// validate current input
		foreach ($options as $name => $option)
		{
			$inptValue = $inpMsg->get($name);

			// check if input is required
			if ($inptValue === NULL)
			{
				if (isset($option['required']) && $option['required'] == true)
				{
					throw new IOValidationException($name, APIErrors::IOArgumentRequired, 'Argument is required.');
				}

				if (isset($option['default']))
				{
					$inpMsg->set($name, $option['default']);
				}

				continue;
			}

			// check if the input has the correct type.
			if (isset($option['type']))
			{
				$type = $option['type'];
				
				if (($type == 'integer' 
					|| $type == 'string' 
					|| $type == 'boolean' 
					|| $type == 'double'
					|| $type == 'float'
					|| $type == 'array') 
					&& !static::ValueCanBe($type, $inptValue))
				{
					throw new IOValidationException($name, APIErrors::IOInvalidArgumentType, 'Argument type is invalid, must be of type \'' . $type . '\'.');
				}

				$inptValue = static::ConvertTo($type, $inptValue);
			}

			// check if the input matches one of the defined matches
			if (isset($option['matches']))
			{
				$hasMatch = false;

				foreach ($option['matches'] as $match)
				{
					if ($match === $inptValue)
					{
						$hasMatch = true;
						break;
					}
				}

				if (!$hasMatch)
				{
					throw new IOValidationException($name, APIErrors::IOArgumentMismatch, 'Argument must match one of the following: ' . implode(',', $option['matches']) . '.');
				}
			}

			$inpMsg->set($name, $inptValue);
		}
	}
};
