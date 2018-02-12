<?php
/**
 * @copyright 2012 - 2017 Zurvan Labs
 * @author snixtho <snixtho@gmail.com>
 */
declare(strict_types=1);

namespace Z7API\Lib\Auth;

use Z7API\Core\PublicPropertiesConvertible;

/**
 * Holds a set of permissions.
 */
class PermissionSet implements PublicPropertiesConvertible {
	private $_permset;

	function __construct() {
		$this->_permset = array();
	}

	/**
	 * Sets a permission.
	 *
	 * @param string $permissionname The name of the permission.
	 */
	public function set(string $permissionname) {
		$this->_permset[$permissionname] = null;
	}

	/**
	 * Removes a permission. Wildcards supported.
	 *
	 * @param  string $permissionname The name of the permission.
	 */
	public function del(string $permissionname) {
		$parts = explode('*', $permissionname);
		$partsLen = count($parts);

		if ($partsLen == 1)
		{ // dont bother wildcard checking
			unset($this->_permset[$permissionname]);
		}

		for ($i = 0; $i < $partsLen; $i++) $parts[$i] = str_replace('.', "\\.", $parts[$i]);
		$pattern = '/^('. implode('(.*)', $parts) . ')$/';

		foreach ($this->_permset as $name => $v)
			if (preg_match($pattern, $name))
				unset($this->_permset[$name]);
	}

	/**
	 * Checks if a permission is present. Negation and wildcard supported. If wildcard is present, the function is O(n) otherwise O(1).
	 *
	 * @param  string  $permissionname Permission name.
	 * @return boolean                 True if it exists, false if not.
	 */
	public function has(string $permissionname) {
		$permissionname = trim($permissionname);
		if ($permissionname == '') return false;
		$invert = $permissionname[0] == '!';
		if ($invert) $permissionname = substr($permissionname, 1);
		$parts = explode('*', $permissionname);
		$partsLen = count($parts);

		if ($partsLen == 1)
		{ // dont bother regex
			return boolval(array_key_exists($parts[0], $this->_permset) ^ $invert);
		}

		for ($i = 0; $i < $partsLen; $i++) $parts[$i] = str_replace('.', "\\.", $parts[$i]);
		$pattern = '/^('. implode('(.*)', $parts) . ')$/';

		foreach ($this->_permset as $name => $v)
			if (preg_match($pattern, $name))
				return !$invert;

		return $invert;
	}

	/**
	 * Check if the permission set includes all the following permissions. Negation and wildcard is supported.
	 *
	 * @param  array   $permissionnames The list with permissions.
	 * @return boolean                  True if they all exists, false if not.
	 */
	public function hasAll(array $permissionnames) {
		foreach ($permissionnames as $name)
			if (!$this->has($name))
				return false;

		return true;
	}

	/**
	 * Get all the permission names. NOTE: this is a generator, use in a loop.
	 * @return Generator Yielded permission names.
	 */
	public function names() {
		foreach ($this->_permset as $perm => $v) {
			yield $perm;
		}
	}

	/* Override */
	public function jsonGetPrivateProperties() {
  	$properties = get_object_vars($this);

  	foreach ($properties as $name => $value)
  	{
  		$oldName = $name;
  		if ($name[0] == '_')
  			$name = substr($name, 1);

  		$properties[$name] = $value;
  		unset($properties[$oldName]);
  	}

  	return $properties;
  }
};
