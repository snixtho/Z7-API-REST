<?php
/**
 * @copyright 2012 - 2017 Zurvan Labs
 * @author snixtho <snixtho@gmail.com>
 */
declare(strict_types=1);

namespace Z7API\Lib\Auth;

use Z7API\Core\{
	F
};

/**
 * Class for securely storing/handling of passwords.
 */
class Password {
	/**
	 * Contains the password plaintext.
	 *
	 * @var string
	 */
	private $m_hashed;

	/**
	 * The password's salt.
	 *
	 * @var string
	 */
	private $m_salt;

	/**
	 * @param string       $password      The password.
	 * @param string       $salt          The salt to use, if not specified, it is auto generated.
	 * @param bool|boolean $alreadyHashed Whether the $password contains an already-hashed string. Salt parameter is then ignored if this is true.
	 */
	function __construct(string $password, string $salt=NULL, bool $alreadyHashed=false) {
		if (!$alreadyHashed)
		{
			if ($salt == NULL)
			{
				$this->m_salt = $this->m_salt = '$6$' . F::randomString(16, F::RANDOM_CHARSET_SALT) . '$';
			}
			else
			{
				$this->m_salt = $salt;
			}

			$this->m_hashed = $this->CreateHash($password, $this->m_salt);
		}
		else
		{
			// parse the hash
			if (preg_match('/\\$6\\$([a-zA-Z0-9]+)\\$(.+)/', $password, $matches) === 1)
			{
				$this->m_hashed = $matches[0];
				$this->m_salt = '$6$' . $matches[1] . '$';
			}
			else
			{
				throw new PasswordWrongFormatException(0, 'The specified password hash is in the wrong format.');
			}
		}
	}

	/**
	 * Generates a hash from a password and salt.
	 *
	 * @param string $password The password.
	 * @param string $salt     The salt.
	 *
	 * @return string The hashed password.
	 */
	public static function CreateHash(string $password, string $salt) : string {
		return crypt($password, $salt);
	}

	/**
	 * Return the hashed string of the password.
	 *
	 * @return string String in a hashed version.
	 */
	function getHash() : string {
		return $this->m_hashed;
	}

	/**
	 * Return the salt used for the hashing.
	 *
	 * @return string The salt.
	 */
	function getSalt() : string {
		return $this->m_salt;
	}

	/**
	 * Checks if two passwords equals.
	 *
	 * @param  Password $otherPassword The other password.
	 * @return bool                  True if equal, false if not.
	 */
	function verify(Password $otherPassword) : bool {
		return hash_equals($this->m_hashed, $otherPassword->getHash());
	}
};
