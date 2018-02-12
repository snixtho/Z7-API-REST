<?php
/**
 * @copyright 2012 - 2017 Zurvan Labs 
 * @author snixtho <snixtho@gmail.com>
 */
declare(strict_types=1);

namespace Z7API\Core;

interface PublicPropertiesConvertible {
	/**
	 * Converts possible private properties to public, and might proccess some of them at the same for output.
	 * @return mixed An array/object containing all public properties wanted.
	 */
	public function jsonGetPrivateProperties();
};

/**
 * Holds global functions for the entire script.
 */
class F {
	/**
	 * Convert all properties to public properties. To convert private properties, the object must implement PublicPropertiesConvertible.
	 * 
	 * @param  mixed $obj  The object to convert.
	 * @return mixed       The new object with all public properties.
	 */
	public static function getAllProperties($obj) {
		$allProps = new \stdClass();
		if ($obj instanceof PublicPropertiesConvertible)
		{
			$properties = $obj->jsonGetPrivateProperties();
			foreach ($properties as $name => $value)
			{
				if (is_object($value))
				{
					$allProps->{$name} = static::getAllProperties($value);
				}
				else if (is_array($value))
				{
					$allProps->{$name} = static::getAllPropertiesArray($value);
				}
				else
				{
					$allProps->{$name} = $value;
				}
			}
		}

		return $allProps;
	}

	/**
	 * Get all properties from each object in an array. Calls getAllProperties on each element if object.
	 * 
	 * @param  array  $theArray The array to with the objects to convert.
	 * @return array            The converted array.
	 */
	public static function getAllPropertiesArray(array $theArray)
	{
		$outArray = array();
		foreach ($theArray as $key => $value)
		{
			if (is_object($value))
			{
				$outArray[$key] = static::getAllProperties($value);
			}
			else if (is_array($value))
			{
				$outArray[$key] = static::getAllPropertiesArray($value);
			}
			else
			{
				$outArray[$key] = $value;
			}
		}

		return $outArray;
	}

	/**
	 * Automatically convert all private objects to public and encode to json.
	 * 
	 * @param  mixed $obj  The object to encode.
	 * @return string      Encoded object in json format.
	 */
	public static function jsonEncodeWithPrivate($obj) {
		return json_encode(static::getAllProperties($obj));
	}

	/**
	 * Convert all properties of each object in the array to public and encode it to json.
	 * 
	 * @param  array  $arr The array to encode.
	 * @return string      Encoded json.
	 */
	public static function jsonEncodeWithPrivateArray(array $arr) {
		return json_encode(static::getAllPropertiesArray($arr));
	}

	/**
	 * Load classes from /inc directory.
	 * 
	 * @param  string $class_name The class to load
	 */
	public static function loadClass(string $class_name) {
		require_once ABSPATH . INCPATH . strtolower($class_name) . '.class.php';
	}

	/**
	 * Load interfaces from /inc directory.
	 * 
	 * @param  string $interface_name The interface to load
	 */
	public static function loadInterface(string $interface_name) {
		require_once ABSPATH . INCPATH . strtolower($interface_name) . '.interface.php';
	}

	/**
	 * Loads libraries from inc/lib.
	 * @param  string $library_name The name of the library.
	 */
	public static function loadLibrary(string $library_name) {
		require_once ABSPATH . LIBPATH . strtolower($library_name) . '.lib.php';
	}

	/**
	 * Checks for the correct system versions and setup. 
	 * If any is incorrect, the whole script exits.
	 */
	public static function check_system_setup() {
		global $config;

		$version_min_php = '7.0.0';

		// check php version
		if (version_compare(PHP_VERSION, $version_min_php, '<'))
		{
			die('Script terminated due to bad PHP version. (' . PHP_VERSION . ' < ' . $version_min_php . ')');
		}

		// check mysql installation
		if (!extension_loaded('mysqli'))
		{
			die('This script uses the mysqli extension. It was not found, please install this.');
		}

		// check config setup
		if (!file_exists(ABSPATH . 'settings.php'))
		{
			touch(ABSPATH . 'settings.php');
			die('Settings file not found, please create settings.php and set up the correct configurations.');
		}
		else
		{
			if (!isset($config['db']['host']) &&

				/* !isset($config['db']['user']['mybb']) &&
				!isset($config['db']['pass']['mybb']) &&
				!isset($config['db']['name']['mybb']) &&
				!isset($config['db']['table_prefix']['mybb']) &&		 */		

				!isset($config['db']['user']['api']) &&
				!isset($config['db']['pass']['api']) &&
				!isset($config['db']['name']['api']) &&
				!isset($config['db']['table_prefix']['api']))
			{
				die('One or more configurations for the database in settings.php has not been set.');
			}
		}

		/* if (!isset($config['misc']['mybb_path']))
		{
			die('The MyBB root path has not been set in settings.php.');
		}
		else if (!file_exists($config['misc']['mybb_path']))
		{
			die('The path to MyBB root is not valid.');
		} */
	}

	/**
	 * Generate an array filled with the specified token.
	 * 
	 * @param  string $token       String to fill each element of the array with.
	 * @param  int    $repeatCount Number of elements of the array.
	 * @return array               The generated array.
	 */
	public static function genArray(string $token, int $repeatCount) {
		$arr = array();

		for ($i = 0; $i < $repeatCount; $i++)
			array_push($arr, $token);

		return $arr;
	}

	/**
	 * Replaces each occurances of the matched string with the output of $replacement callback.
	 * 
	 * @param  string $match         Regular expression to match against.
	 * @param  string $haystack      The string to search in.
	 * @param  function $replacement Call back function with the prototype callback($match), which returns the string to replace.
	 * @return string                New string.
	 */
	public static function advanced_replace(string $regex, string $haystack, $replacement) : string {
		if (is_callable($replacement))
		{
			$pos = 0;
			while (preg_match($regex, $haystack, $match, PREG_OFFSET_CAPTURE, $pos))
			{
				$repl = $replacement($match[0][0]);
				$pos = $match[0][1];

				$newStr = substr($haystack, 0, $pos);
				$newStr .= $repl . substr($haystack, $pos + strlen($match[0][0]), strlen($haystack) - strlen($newStr) - strlen($repl) + 1);
				$pos += strlen($repl);

				$haystack = $newStr;
			}

			return $haystack;
		}

		return '';
	}

	/**
	 * Produces a crypto-secure psudo-random 4-byte integer.
	 * 
	 * @return int The random integer.
	 */
	public static function randomUInt() : int {
		$i1 = hexdec(bin2hex(random_bytes(1)));
		$i2 = hexdec(bin2hex(random_bytes(1)));
		$i3 = hexdec(bin2hex(random_bytes(1)));
		$i4 = hexdec(bin2hex(random_bytes(1)));

		return abs($i1 | ($i2 << 8) | ($i3 << 16) | ($i3 << 24));
	}

	/**
	 * Pre-defined charsets to be used with randomString().
	 */
	const RANDOM_CHARSET_ALL = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ1234567890!"#$%&\'()*+,-./:;<=>?@\\[]^_`{|}~';
	const RANDOM_CHARSET_LOWER = 'abcdefghijklmnopqrstuvwxyz';
	const RANDOM_CHARSET_UPPER = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ';
	const RANDOM_CHARSET_NUMBER = '1234567890';
	const RANDOM_CHARSET_SPECIAL = '!"#$%&\'()*+,-./:;<=>?@\\[]^_`{|}~';
	const RANDOM_CHARSET_SPECIAL_LITE = '!#$%&()*+,-./:;<=>?@[]_{|}';
	const RANDOM_CHARSET_HEX_LOWER = '0123456789abcdef';
	const RANDOM_CHARSET_HEX_UPPER = '0123456789ABCDEF';
	const RANDOM_CHARSET_APIKEY = F::RANDOM_CHARSET_LOWER.F::RANDOM_CHARSET_UPPER.F::RANDOM_CHARSET_NUMBER;
	const RANDOM_CHARSET_SECRET = F::RANDOM_CHARSET_LOWER.F::RANDOM_CHARSET_UPPER.F::RANDOM_CHARSET_NUMBER.F::RANDOM_CHARSET_SPECIAL_LITE;
	const RANDOM_CHARSET_SALT = F::RANDOM_CHARSET_LOWER.F::RANDOM_CHARSET_UPPER.F::RANDOM_CHARSET_NUMBER;

	/**
	 * Produce a random string with a specified length.
	 * 
	 * @param  integer $length  Length of the random string. Default is 32.
	 * @param  string  $charset The character set to use.
	 * @return string           Random string produced from the charset.
	 */
	public static function randomString(int $length=32, string $charset=F::RANDOM_CHARSET_ALL) : string {
		if (strlen($charset) > 0)
		{
			$randStr = '';
			$maxIndex = strlen($charset);
			for ($i = 0; $i < $length; ++$i)
				$randStr .= $charset[F::randomUInt() % $maxIndex];

			return $randStr;
		}
		else
		{
			return '';
		}
	}

	/**
	 * Converts a password & salt combination into a hash for MyBB.
	 * 
	 * @param  string $pass The password.
	 * @param  string $salt The salt.
	 * @return string       Hashed password.
	 */
	public static function mybbHashPassword(string $pass, string $salt) {
		return md5(md5($salt).md5($pass));
	}

	/**
	 * Get the user's client information in a id-based format.
	 * 
	 * @return string The id-based device info.
	 */
	public static function getDeviceInfoId() : string {
		$clientIp = $_SERVER['HTTP_X_FORWARDED_FOR'] ??
					$_SERVER['HTTP_X_FORWARDED_FOR'] ??
					$_SERVER['HTTP_FORWARDED'] ??
					$_SERVER['HTTP_CF_CONNECTING_IP'] ??
					$_SERVER['REMOTE_ADDR'] ??
					$_SERVER['HTTP_CLIENT_IP'];

		$domain = gethostbyaddr($clientIp);

		$userAgent = "";
		if (isset($_SERVER['HTTP_USER_AGENT']))
		{
			$ua = $_SERVER['HTTP_USER_AGENT'];
			if (strpos($ua, 'Firefox/') && strpos($ua, 'Seamonkey/') === false) $userAgent = 'firefox';
			else if (strpos($ua, 'Seamonkey/')) $userAgent = 'Seamonkey';
			else if (strpos($ua, 'Chrome/') && strpos($ua, 'Chromium/') === false) $userAgent = 'Google Chrome';
			else if (strpos($ua, 'Chromium/')) $userAgent = 'Chromium';
			else if (strpos($ua, 'Safari/') && strpos($ua, 'Chrome/') === false && strpos($ua, 'Chromium/') === false) $userAgent = 'Safari';
			else if (strpos($ua, 'OPR/') && strpos($ua, 'Opera/')) $userAgent = 'Opera';
			else if (strpos($ua, '; MSIE ')) $userAgent = 'Internet Explorer';
			else $userAgent = "Unknown";
		}

		return '' . $userAgent . '@'.$clientIp.':'.$domain;
	}

};
