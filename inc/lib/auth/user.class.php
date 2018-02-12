<?php
/**
 * @copyright 2012 - 2017 Zurvan Labs
 * @author snixtho <snixtho@gmail.com>
 */
declare(strict_types=1);

namespace Z7API\Lib\Auth;

use Z7API\Core\{
	Database,
	Config,
	IO,
	APIMessage,
	MySQLException,
	PublicPropertiesConvertible,
	F
};

class User implements PublicPropertiesConvertible {
	/**
	 * User's user id.
	 *
	 * @var int
	 */
	private $_uid;
	/**
	 * User's email address.
	 *
	 * @var string
	 */
	private $_email;
	/**
	 * User's password.
	 *
	 * @var Password
	 */
	private $_password;
	/**
	 * When the user was created.
	 *
	 * @var int
	 */
	private $_created;
	/**
	 * Groups the user is a part of.
	 *
	 * @var array
	 */
	private $_groups;
	/**
	 * User's permissions.
	 *
	 * @var PermissionSet
	 */
	private $_permissions;
	/**
	 * The user's session.
	 *
	 * @var Session
	 */
	private $_session;
	/**
	 * Whether the user is valid or not (exists or not).
	 * @var boolean
	 */
	private $_isValidUser = false;

	/**
	 * Increases the login attempts by 1.
	 *
	 * @return boolean True on success, false if not.
	 */
	private function increaseLoginAttempts() {
		Database::instance()->selectdb('auth');
		$prep = Database::instance()->prepare('UPDATE `'.Config::get('db.table_prefix.auth').'antihijack` SET `loginattempts`=loginattempts+1 WHERE `uid`=?');
		if (!$prep)
		{
			return false;
		}

		$prep->bind_param('i', $this->_uid);
		$prep->execute();
		$prep->close();

		return true;
	}

	/**
	 * Resets login attempts in the database.
	 */
	private function resetLoginAttempts() {
		Database::instance()->simpleUpdate(Config::get('db.table_prefix.auth').'antihijack', array('loginattempts' => 0), 'uid=?', array($this->_uid));
	}

	/**
	 * Check whether login attempts has been exceeded for the user. NOTE: returns true if no antihijack found.
	 * @return boolean True if exceeded, false if not.
	 */
	private function loginAttemptsExceeded($autoReset=true) {
		Database::instance()->selectdb('auth');
		$tprefix = Config::get('db.table_prefix.auth');
		$result = Database::instance()->simpleSelect($tprefix.'antihijack', 'loginattempts,attempttime', 'uid=?', array($this->_uid));

		if (!$result || $result->num_rows <= 0)
		{
			return true;
		}

		$attemptData = $result->fetch_assoc();

		$attemptTime = strtotime($attemptData['attempttime']);
		$numAttempts = $attemptData['loginattempts'];

		if (time() > Config::get('auth.maxFreezeTime') + $attemptTime && $autoReset)
		{
			// reset attempts
			$numAttempts = 0;
			$this->resetLoginAttempts();
		}

		return $numAttempts >= Config::get('auth.maxLoginFailure');
	}

	/**
	 * Load user information from their email.
	 *
	 * @param  string $email The email assoicated with the user.
	 */
	public function loadFromEmail(string $email) {
		Database::instance()->selectdb('auth');
		$tprefix = Config::get('db.table_prefix.auth');
		$result = Database::instance()->simpleSelect($tprefix.'users', 'uid,email,password,created,groups', 'email=?', array($email));

		if ($result && $result->num_rows > 0)
		{
			$userData = $result->fetch_assoc();

			$this->_email = $userData['email'];
			$this->_password = new Password($userData['password'], null, true);
			$this->_uid = $userData['uid'];
			$this->_created = $userData['created'];

			$groups = explode(',', $userData['groups']);

			$this->_groups = array();
			if (count($groups) > 0) $this->_groups = $groups;
		}
		else
		{
			return;
		}

		$this->_permissions = Permissions::loadPermissions($groups);
		$this->_session = SessionFactory::GetUserSession($this->_uid);
		$this->_isValidUser = true;
	}

	/**
	 * Load user information from their user id.
	 *
	 * @param  int    $uid The user's id.
	 */
	public function loadFromUid(int $uid) {
		Database::instance()->selectdb('auth');
		$tprefix = Config::get('db.table_prefix.auth');
		$result = Database::instance()->simpleSelect($tprefix.'users', 'uid,email,password,created,groups', 'uid=?', array($uid));
		if ($result && $result->num_rows > 0)
		{
			$userData = $result->fetch_assoc();

			$this->_email = $userData['email'];
			$this->_password = new Password($userData['password'], null, true);
			$this->_uid = $userData['uid'];
			$this->_created = $userData['created'];

			$groups = explode(',', $userData['groups']);

			$this->_groups = array();
			if (count($groups) > 0) $this->_groups = $groups;
		}
		else
		{
			// todo: load failed error handling
			echo "user loading failed.";
			return;
		}

		// todo: load failed error handling

		$this->_permissions = Permissions::loadPermissions($groups);
		$this->_session = SessionFactory::GetUserSession($this->_uid);
		$this->_isValidUser = true;
	}

	/**
	 * Get the user's id.
	 *
	 * @return int The id of the user.
	 */
	public function getUserId() : int {
		return $this->_uid;
	}

	/**
	 * Get the user's email address.
	 *
	 * @return string The email of the user.
	 */
	public function getEmail() : string {
		return $this->_email;
	}

	/**
	 * Get the user's password salt.
	 *
	 * @see Password::getSalt() For how it works.
	 * @return string The salt used for hashing of the user's password.
	 */
	public function getPasswordSalt() : string {
		return $this->_password->getSalt();
	}

	/**
	 * Get the user session's key.
	 *
	 * @see Session::getSessionKey() for how it works.
	 * @return string The user's session key.
	 */
	public function getSessionKey() : string {
		return $this->_session->getSessionKey();
	}

	/**
	 * Check whether the user is valid (exists or not).
	 *
	 * @return boolean True if valid, false if not.
	 */
	public function isValid() {
		return $this->_isValidUser;
	}

	/**
	 * Checks whether a user can login or not.
	 *
	 * @param  boolean $onlyCheck If true, no modification in the database is made.
	 * @return boolean            True if the user can login, false if not.
	 */
	public function canLogin($onlyCheck=false) {
		Database::instance()->selectdb('auth');
		$loginAttmpsNPerm = $this->_permissions->has('auth.canlogin') && !$this->loginAttemptsExceeded(!$onlyCheck);

		if (!$loginAttmpsNPerm)
		{
			return false;
		}

		// check if user is allowed to be renew session without logging out first.
		$tprefix = Config::get('db.table_prefix.auth');
		$hasSession = Database::instance()->simpleSelect($tprefix.'sessions', 'uid', 'uid=?', array($this->_uid));
		if ($hasSession && $hasSession->num_rows > 0)
		{
			$allowMultiRes = Database::instance()->simpleSelect($tprefix.'antihijack', 'allowmulti', 'uid=?', array($this->_uid));
			if (!$allowMultiRes || $allowMultiRes->num_rows <= 0)
			{ // dont allow access on no antihijack row because thats an error and everyone should have one.
				return false;
			}

			if ($allowMultiRes->fetch_row()[0] == Session::DisallowMultiSession)
			{
				return false;
			}
		}

		// all good, user is allowed to login
		return true;
	}

	/**
	 * Checks the login without modfying user data.
	 *
	 * @param  Password $otherPassword The input password to check against current password.
	 * @return int                 	   0 - Success, -1 - No permission to login, 1 - Password mismatch.
	 */
	public function checkLogin(Password $otherPassword) {
		if (!$this->_isValidUser || !$this->canLogin(true)) return -1;

		if (!$otherPassword->verify($this->_password))
		{ // password mismatch
			return 1;
		}

		return 0;
	}

	/**
	 * Sets a new password for the user.
	 *
	 * @param Password $newPassword The new password to set.
	 */
	public function setPassword(Password $newPassword) {
		$tprefix = Config::get('db.table_prefix.auth');
		Database::instance()->selectdb('auth');
		Database::instance()->simpleUpdate($tprefix.'users',
			array('password' => $newPassword->getHash()),
			'uid=?',
			array($this->_uid));
	}

	/**
	 * Set a new email for the user. Note that duplicate emails is not allowed in the database.
	 *
	 * @param string $email The new email for the user.
	 * @return boolean		True if changed, false on error (probably duplicate).
	 */
	public function setEmail(string $email) {
		$tprefix = Config::get('db.table_prefix.auth');
		Database::instance()->selectdb('auth');

		try
		{
			Database::instance()->simpleUpdate($tprefix.'users', array('email' => $email), 'uid=?', array($this->_uid));
		}
		catch (MySQLException $e)
		{
			return false;
		}

		return true;
	}

	/**
	 * Tries to create a new session for the user. If success, session is updated automatically.
	 *
	 * @param  Password $otherPassword The input password to check against current password.
	 * @return int                 	 0 - Success, -1 - No permission to login, 1 - Password mismatch.
	 */
	public function tryLogin(Password $otherPassword, bool $expire=true) {
		if (!$this->_isValidUser || !$this->canLogin()) return -1;

		if (!$otherPassword->verify($this->_password))
		{ // password mismatch
			$this->increaseLoginAttempts();
			return 1;
		}

		$this->_session->newSession($this->_uid, $expire);
		$this->resetLoginAttempts();

		// update device
		$tprefix = Config::get('db.table_prefix.auth');
		Database::instance()->selectdb('auth');

		try
		{
			$userDevices = Database::instance()->simpleSelect($tprefix.'antihijack', 'devicelist', 'uid=?', array($this->_uid));
			if (!$userDevices || $userDevices->num_rows <= 0)
			{ // dont allow login on system failure
				return 1;
			}

			$device = F::getDeviceInfoId();
			$deviceList = unserialize($userDevices->fetch_row()[0]);
			array_push($deviceList, $device);

			$deviceListStr = serialize($deviceList);
			Database::instance()->simpleUpdate($tprefix.'antihijack', array('devicelist' => $deviceListStr), 'uid=?', array($this->_uid));
		}
		catch (MySQLException $e)
		{
			return false;
		}

		return 0;
	}

	/**
	 * Check if the following permission expression matches a part of the user's permission set.
	 *
	 * @see PermissionSet::has() For a better description.
	 * @param  string  $permissionExpr The permission expression. wildcard, negation, and, or are supported.
	 * @return boolean                 True if match was a success, false if not.
	 */
	public function hasPermission(string $permissionExpr) {
		return $this->_permissions->has($permissionExpr);
	}

	/**
	 * Get the permission set of the user.
	 *
	 * @return PermissionSet The permission set of the user.
	 */
	public function getPermissions() : PermissionSet {
		return $this->_permissions;
	}

	/**
	 * Check if the session is valid or expried.
	 *
	 * @see Session::isValid For explanation.
	 * @return boolean True if valid, false if not.
	 */
	public function hasValidSession() {
		return $this->_session->isValid();
	}

	/**
	 * Refreshes validity of the user's session.
	 *
	 * @see Session::updateActivity() To see how it works.
	 */
	public function updateSessionActivity() {
		$this->_session->updateActivity();
	}

	/**
	 * Check if an outside session matches the user's current session. If the user's session is invalid, it will always return false.
	 *
	 * @param  int    $sessionuid The session user id to check.
	 * @param  string $sessionkey The session key to check.
	 * @return boolean            True if the sessions matches, false if not.
	 */
	public function matchesSession(int $sessionuid, string $sessionkey) {
		return $this->_session->matches($sessionuid, $sessionkey);
	}

	/**
	 * Logs a user out and invalidates their session.
	 */
	public function logout() {
		$this->_session->invalidate();
	}

	/**
	 * OVERRIDE
	 * @see functions.php:F::PublicPropertiesConvertible To see how it works.
	 */
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

	/**
	 * Internal function for checking whether a user matches the specified permission expression or not from a message.
	 *
	 * @param  APIMessage $inptMsg     The message containing either login info or session info.
	 * @param  string     $permExpr    The permission expression.
	 * @param  bool       $fromSession If true, the function checks against session info, if false, it checks against login info.
	 * @return boolean                 True if permission expression matches, false if not.
	 */
	private static function hasPermissionMsg(APIMessage $inptMsg, string $permExpr, bool $fromSession) {
		try {
			if ($fromSession)
				IO::Validate($inptMsg, array(
					'sessionuid' => array('required' => true, 'type' => 'integer'),
					'sessionkey' => array('required' => true, 'type' => 'string')
				));
			else
				IO::Validate($inptMsg, array(
					'email' => array('required' => true, 'type' => 'integer'),
					'password' => array('required' => true, 'type' => 'string')
				));
		}
		catch (IOValidationException $e)
		{
			return false;
		}

		$user = null;

		if ($fromSession)
		{
			$user = UserFactory::GetFromUid($inptMsg->get('sessionuid'));
		}
		else
		{
			$user = UserFactory::GetFromEmail($inptMsg->get('email'));

			if (!$user->checkLogin(new Password($inptMsg->get('password'), $user->getSalt())))
			{
				return false;
			}
		}

		if (!$user->isValid())
		{
			return false;
		}

		// check if the user has permission to change their password.
		if ($user->hasPermission($permExpr))
		{
			return true;
		}

		return false;
	}

	/**
	 * Tries to log a user in from a input message.
	 *
	 * @param APIMessage $inptMsg   The input message to use that should contain login info.
	 * @param APIMessage &$OutptMsg The output message which will be used for adding errors and success information if so.
	 * @return  bool 				True if login was a success, false if not.
	 */
	public static function DoLogin(APIMessage $inptMsg, APIMessage &$OutptMsg) {
		try
		{
			IO::Validate($inptMsg, array(
				'email' => array('required' => true, 'type' => 'string'),
				'password' => array('required' => true, 'type' => 'string'),
				'expires' => array('required' => false, 'type' => 'boolean', 'default' => true)
			));
		}
		catch (IOValidationException $e)
		{
			$OutptMsg->addErrorFormat($e->getCode(), $e->getValueName());
			return false;
		}

		$user = UserFactory::GetFromEmail($inptMsg->get('email'));
		if (!$user->isValid())
		{
			$OutptMsg->addError(AuthErrors::UserDoesntExist);
			return false;
		}

		$expires = $inptMsg->get('expires') == null ? true : $inptMsg->get('expires');

		$result = $user->tryLogin(new Password($inptMsg->get('password'), $user->getPasswordSalt()), $expires);

		if ($result === 0)
		{
			$OutptMsg->set('sessionkey', $user->getSessionKey());
			$OutptMsg->set('sessionuid', $user->getUserId());
			$OutptMsg->set('success', true);
			$OutptMsg->set('refresh_interval', Config::get("auth.sessions.maxtime"));

			return true;
		}
		else if ($result === 1)
		{
			$OutptMsg->addError(AuthErrors::InvalidLogin);
		}
		else
		{
			$OutptMsg->addError(AuthErrors::CannotLogin);
		}

		return false;
	}

	/**
	 * Tries to log a user out from a input message.
	 * @param APIMessage $inptMsg   The input message that should contain session info.
	 * @param APIMessage &$OutptMsg The output message that will contain success or errors if occured.
	 * @return bool 				True if logout was a success, false if not.
	 */
	public static function DoLogout(APIMessage $inptMsg, APIMessage &$OutptMsg) {
		try
		{
			IO::Validate($inptMsg, array(
				'sessionkey' => array('required' => true, 'type' => 'string'),
				'sessionuid' => array('required' => true, 'type' => 'integer')
			));
		}
		catch (IOValidationException $e)
		{
			$OutptMsg->addErrorFormat($e->getCode(), $e->getValueName());
			return false;
		}

		$user = UserFactory::GetFromUid($inptMsg->get('sessionuid'));
		if (!$user->isValid() || !$user->hasValidSession())
		{
			$OutptMsg->addError(AuthErrors::InvalidSession);
			return false;
		}

		$user->logout();
		$OutptMsg->set('success', true);
		return true;
	}

	/**
	 * Check if a user has a permission set matching the input expression from a session.
	 *
	 * @param APIMessage $inptMsg  	A message containing session info.
	 * @param string     $permExpr 	The permission expression to match against.
	 * @return bool 				True if the user has permission matching the expression, false if not.
	 */
	public static function HasPermissionSession(APIMessage $inptMsg, string $permExpr) {
		return self::hasPermissionMsg($inptMsg, $permExpr, true);
	}

	/**
	 * Check if a user has a permission set matching the input expression from login data.
	 *
	 * @param APIMessage $inptMsg  	A message containing login info.
	 * @param string     $permExpr 	The permission expression to match against.
	 * @return bool 				True if the user has permission matching the expression, false if not.
	 */
	public static function HasPermissionLogin(APIMessage $inptMsg, string $permExpr) {
		return self::hasPermissionMsg($inptMsg, $permExpr, false);
	}
};

/**
 * Easy getting of users.
 */
class UserFactory {
	/**
	 * Get a user form their email address.
	 *
	 * @param string $email The email address of the user.
	 * @return User A user object.
	 */
	public static function GetFromEmail(string $email) : User {
		$user = new User();
		$user->loadFromEmail($email);
		return $user;
	}

	/**
	 * Get a user from a session.
	 *
	 * @param int    $sessionId  The session user id.
	 * @param string $sessionKey The session key.
	 * @return User A user object.
	 */
	public static function GetFromUid(int $uid) : User {
		$user = new User();
		$user->loadFromUid($uid);
		return $user;
	}

	/**
	 * Get a user from an input message.
	 *
	 * @param APIMessage $msg The message that should contain the 'sessionuid' field.
	 * @return User A user object.
	 */
	public static function FromMessage(APIMessage $msg) : User {
		try
		{
			IO::Validate($msg, array(
				'sessionuid' => array('required' => true, 'type' => 'integer')
			));
		}
		catch (IOValidationException $e)
		{
			return new User();
		}

		$user = new User();
		$user->loadFromUid($msg->get('sessionuid'));
		return $user;
	}
};
