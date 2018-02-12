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
	APIErrors,
	APIMessage,
	IO,
	F,
	APICustomException
};

require_once(ABSPATH.'inc/lib/auth/password.class.php');
require_once(ABSPATH.'inc/lib/auth/user.class.php');
require_once(ABSPATH.'inc/lib/auth/permissionset.class.php');
require_once(ABSPATH.'inc/lib/auth/permissions.class.php');
require_once(ABSPATH.'inc/lib/auth/session.class.php');

/*******************************
 * Exceptions for use within the auth system.
 *****/
/**
 * Thrown througout the permission system.
 */
class PermissionException extends APICustomException {}
/**
 * Thrown when a permission doesnt exist.
 */
class PermissionDoesNotExistException extends APICustomException {}
/**
 * Thrown when a permission name is invalid.
 */
class InvalidPermissionNameException extends PermissionException {}
/**
 * Thrown when one or more permission names are invalid.
 */
class InvalidPermissionNamesException extends PermissionException {}
/**
 * Thrown when the display name of a permission is invalid.
 */
class InvalidPermissionDisplayException extends PermissionException {}
/**
 * Thrown when a permission name already exists.
 */
class PermissionDuplicateException extends PermissionException {}
/**
 * Thrown when a group name is invalid.
 */
class InvalidGroupNameException extends PermissionException {}
/**
 * Thrown when the display name of a group is invalid.
 */
class InvalidGroupDisplayException extends PermissionException {}
/**
 * Thrown when a group name already exists.
 */
class GroupDuplicateException extends PermissionException {}
/**
 * Thrown when a group doesnt exist.
 */
class GroupDoesNotExistException extends APICustomException {}



/**
 * Authentication error constants.
 */
class AuthErrors {
	const UnknownError = 1000;
	const UserDoesntExist = 1001;
	const InvalidLogin = 1002;
	const NewPasswordNotSpecified = 1003;
	const UserAlreadyExists = 1004;
	const PermissionDenied = 1005;
	const CannotLogin = 1006;
	const SetEmailFailed = 1007;
	const InvalidSession = 1008;
	const InvalidEmail = 1009;
	const CantDeleteAdminSelf = 1010;
	const UserDeletionFailed = 1011;

	const PermissionDuplicate = 1012;
	const PermissionInvalidName = 1013;
	const PermissionInvalidDisplayName = 1014;
	const PermissionDoesNotExist = 1015;
	const GroupInvalidName = 1016;
	const GroupInvalidDisplayName = 1017;
	const GroupDoesNotExist = 1018;
	const PermissionInvalidNames = 1018;
	const GroupDuplicate = 1019;
};

trait AuthSystemEasyAPI {
	/**
	 * Check if a session id and key combination is correct and active.
	 * @param APIMessage $msg The message containing the sessionid and sessionkey fields.
	 */
	public static function CheckSession(APIMessage $msg) {
		try
		{
			IO::Validate($msg, array(
				'sessionkey' => array('required' => true, 'type' => 'string'),
				'sessionuid' => array('required' => true, 'type' => 'integer')
			));
		}
		catch (IOValidationException $e)
		{
			return false;
		}

		return static::instance()->matchSession($msg->get('sessionuid'), $msg->get('sessionkey'));
	}
};

/**
 * Class which handles authentication for the Auth module.
 */
class AuthSystem {
	use AuthSystemEasyAPI;

	private static $auth_instance = NULL;

	function __construct() {
		APIErrors::instance()->addError(AuthErrors::UnknownError, 'An unknown error occurred, please contact the Zurvan-Labs developers.');
		APIErrors::instance()->addError(AuthErrors::UserDoesntExist, 'The provided user does not exist.');
		APIErrors::instance()->addError(AuthErrors::InvalidLogin, 'The provided login credentials are invalid.');
		APIErrors::instance()->addError(AuthErrors::NewPasswordNotSpecified, 'The new password was not specified.');
		APIErrors::instance()->addError(AuthErrors::UserAlreadyExists, 'A user with the provided email address already exists.');
		APIErrors::instance()->addError(AuthErrors::PermissionDenied, 'You do not have permission to access this resource.');
		APIErrors::instance()->addError(AuthErrors::CannotLogin, 'You may not login at this time (Can be too many failed logins, multiple logins is not enabled for this user, or you are banned from logging in).');
		APIErrors::instance()->addError(AuthErrors::SetEmailFailed, 'Failed to change email. This error might occur if the provided email already exists in the database.');
		APIErrors::instance()->addError(AuthErrors::InvalidSession, 'Session is invalid, no permission to further execute.');
		APIErrors::instance()->addError(AuthErrors::InvalidEmail, 'The provided email address is invalid.');
		APIErrors::instance()->addError(AuthErrors::CantDeleteAdminSelf, 'You cannot delete yourself as an admin.');
		APIErrors::instance()->addError(AuthErrors::UserDeletionFailed, 'Could not delete user. Possible error is that they dont exist.');
		APIErrors::instance()->addError(AuthErrors::PermissionDuplicate, 'The provided permission name already exists in the database.');
		APIErrors::instance()->addError(AuthErrors::PermissionInvalidName, 'The provided name for the permission does not have the correct format.');
		APIErrors::instance()->addError(AuthErrors::PermissionInvalidDisplayName, 'The provided display name for the permission is not in the correct format. Possibly too long.');
		APIErrors::instance()->addError(AuthErrors::PermissionDoesNotExist, 'The provided permission name does not exist.');
		APIErrors::instance()->addError(AuthErrors::GroupInvalidName, 'The provided name for the group does not have the correct format.');
		APIErrors::instance()->addError(AuthErrors::GroupInvalidDisplayName, 'The provided display name for the group is not in the correct format. Possibly too long.');
		APIErrors::instance()->addError(AuthErrors::GroupDoesNotExist, 'The provided group name does not exist.');
		APIErrors::instance()->addError(AuthErrors::PermissionInvalidNames, 'One or more of the permission names provided are invalid or does not exist.');
		APIErrors::instance()->addError(AuthErrors::GroupDuplicate, 'The provided group name already exists in the database.');

		global $config;

		if (!file_exists(ABSPATH . 'auth_settings.php')) {
			$settings_config = <<<'EOD'
<?php
// The database username
$config['db']['user']['auth'] = 'root';

// The database password
$config['db']['pass']['auth'] = 'sniper';

// The database name
$config['db']['name']['auth'] = 'auth';

// The table prefix
$config['db']['table_prefix']['auth'] = 'z7_';

// Whether to freeze a user's login capability after a certain amount of tries.
$config['auth']['enableLoginFailureBlock'] = true;

// Maximum email & password login failures before the user is freezed. Default is 3.
$config['auth']['maxLoginFailure'] = 3;

// The time, in seconds that specifies for how long a user is freezed. Default is 900 = 15 min.
$config['auth']['maxFreezeTime'] = 900;

// Maximum time in seconds a session can be active after a user is inactive. Default: 600 = 10 min
$config['auth']['sessions']['maxtime'] = 600;
EOD;
			$f = fopen(ABSPATH . 'auth_settings.php', 'w');
			fwrite($f, $settings_config, strlen($settings_config));
			fclose($f);
		}

		require_once ABSPATH . "auth_settings.php";

		Database::instance()->connectNew('auth', Config::get('db.name.auth'),
											Config::get('db.user.auth'),
											Config::get('db.pass.auth'),
											Config::get('db.host'),
											Config::get('db.port'));

		Database::instance()->selectdb('auth');

		$tprefix = Config::get('db.table_prefix.auth');
		$installSteps = 0;

		$tables = array(
			'users' => 'CREATE TABLE ' . $tprefix . 'users(
				`uid` INT NOT NULL AUTO_INCREMENT,
				`email` VARCHAR(254) NOT NULL,
				`password` VARCHAR(256) NOT NULL,
				`created` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
				`groups` TEXT NOT NULL,
				PRIMARY KEY(`uid`),
				UNIQUE(`email`)
			) CHARACTER SET=utf8 COLLATE=utf8_bin',

			'sessions' => 'CREATE TABLE ' . $tprefix . 'sessions(
				`uid` INT NOT NULL,
				`sessionkey` VARCHAR(64) NOT NULL,
				`created` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
				`maxtime` INT(12) NOT NULL,
				PRIMARY KEY(`sessionkey`)
			) CHARACTER SET=utf8 COLLATE=utf8_bin',

			'antihijack' =>'CREATE TABLE ' . $tprefix . 'antihijack(
				`uid` INT NOT NULL AUTO_INCREMENT,
				`loginattempts` INT NOT NULL,
				`attempttime` TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
				`devicelist` TEXT NOT NULL,
				`allowmulti` ENUM(\'0\', \'1\') NOT NULL,
				PRIMARY KEY(`uid`)
			) CHARACTER SET=utf8 COLLATE=utf8_bin',

			'permissions' => 'CREATE TABLE ' . $tprefix . 'permissions(
				`pid` INT NOT NULL AUTO_INCREMENT,
				`permissionname` VARCHAR(128) NOT NULL,
				PRIMARY KEY(`pid`),
				UNIQUE(`permissionname`)
			) CHARACTER SET=utf8 COLLATE=utf8_bin',

			'permissionsdata' => 'CREATE TABLE ' . $tprefix . 'permissionsdata(
				`pid` INT NOT NULL,
				`displayname` VARCHAR(512) NOT NULL,
				`description` TEXT NOT NULL,
				PRIMARY KEY(`pid`)
			) CHARACTER SET=utf8 COLLATE=utf8_bin',

			'groups' => 'CREATE TABLE ' . $tprefix . 'groups(
				`gid` INT NOT NULL AUTO_INCREMENT,
				`groupname` VARCHAR(128) NOT NULL,
				`permissions` TEXT NOT NULL,
				`priority` INT NOT NULL,
				PRIMARY KEY(`gid`),
				UNIQUE(`groupname`)
			) CHARACTER SET=utf8 COLLATE=utf8_bin',

			'groupsdata' => 'CREATE TABLE ' . $tprefix . 'groupsdata(
				`gid` INT NOT NULL,
				`displayname` VARCHAR(512) NOT NULL,
				`description` TEXT NOT NULL,
				PRIMARY KEY(`gid`)
			) CHARACTER SET=utf8 COLLATE=utf8_bin'
		);

		$newTables = array();

		foreach ($tables as $tableName => $query)
		{
			if (!Database::instance()->tableExists($tprefix.$tableName))
			{
				if (!Database::instance()->query($query))
				{
					$conn = Database::instance()->getCurrentResource();
					echo $conn->errno . " -> " . $conn->error;
					throw new MySQLQueryException($conn->errno, $conn->error);
				}

				$newTables[$tableName] = null;
			}
		}

		// add default data for permissions
		if (array_key_exists('permissions', $newTables) && array_key_exists('permissionsdata', $newTables))
		{
			$defaultPermissions = array(
				'auth.canlogin' => array('displayname' => 'Can Login', 'description' => 'Whether a user can login or not.'),
				'auth.admin.createuser' => array('displayname' => 'Admin: Create user', 'description' => 'Whether an admin can create an user.'),
				'auth.admin.deleteuser' => array('displayname' => 'Admin: Delete User', 'description' => 'Whether an admin can delete an user.'),
				'auth.admin.action' => array('displayname' => 'Admin Actions Access', 'description' => 'Whether a user have access to running any admin action.'),
				'auth.admin.getuser' => array('displayname' => 'Admin: Get User', 'description' => 'Whether an admin is allowed to get information about users.'),
				'auth.accountmanaging.basic' => array('displayname' => 'Basic Account Management', 'description' => 'Basic account management like changing password and email.'),
				'auth.admin.getgroup' => array('displayname' => 'Get Group', 'description' => 'Permission to get group information.'),
				'auth.admin.deletegroup' => array('displayname' => 'Delete Group', 'description' => 'Permission to delete a group.'),
				'auth.admin.creategroup' => array('displayname' => 'Create Group', 'description' => 'Permission to create a new group.'),
				'auth.admin.getpermission' => array('displayname' => 'Get Permission', 'description' => 'Permission to get information about a permission.'),
				'auth.admin.deletepermission' => array('displayname' => 'Delete Permission', 'description' => 'Whether an admin can delete a permission.'),
				'auth.admin.createpermission' => array('displayname' => 'Create Permission', 'description' => 'Permission to create a new permission.')
			);

			$i = 1;
			foreach ($defaultPermissions as $name => $data)
			{
				Database::instance()->simpleInsert($tprefix.'permissions', array('permissionname' => $name));
				Database::instance()->simpleInsert($tprefix.'permissionsdata', array(
					'pid' => $i,
					'displayname' => $data['displayname'],
					'description' => $data['description']
				));
				$i++;
			}
		}

		// add default data for groups
		if (array_key_exists('groups', $newTables) && array_key_exists('groupsdata', $newTables))
		{
			$defualtGroups = array(
				'auth.normal' => array('permissions' => 'auth.canlogin,auth.accountmanaging.basic', 'priority' => 0, 'data' => array('displayname' => 'Normal Users', 'description' => 'Normal users who can login and do basic account changes.')),
				'auth.admin' => array('permissions' => 'auth.*', 'priority' => 0, 'data' => array('displayname' => 'Admin Users', 'description' => 'Users who have access to admin functionality.')),
				'auth.banned' => array('permissions' => '!*', 'priority' => 10000, 'data' => array('displayname' => 'Banned Users', 'description' => 'Users who have no permissions at all.'))
			);

			$i = 1;
			foreach ($defualtGroups as $name => $other)
			{
				Database::instance()->simpleInsert($tprefix.'groups', array(
					'groupname' => $name,
					'permissions' => $other['permissions'],
					'priority' => $other['priority']
				));
				Database::instance()->simpleInsert($tprefix.'groupsdata', array(
					'gid' => $i,
					'displayname' => $other['data']['displayname'],
					'description' => $other['data']['description']
				));
				$i++;
			}
		}

		// add default admin user.
		if (array_key_exists('users', $newTables))
		{ // todo: add admin permissions
			$uid = $this->createUser('admin@api.net', 'Z7APIAdmin678', array('auth.normal', 'auth.admin'));
		}
	}

	/**
	 * Get the instance of the authentication system.
	 */
	public static function instance() {
		if (static::$auth_instance == NULL)
			static::$auth_instance = new AuthSystem();
		return static::$auth_instance;
	}

	/**
	 * Create a new user. Will throw an MySQLException on failure.
	 * 
	 * @param  string       $email    The email of the user.
	 * @param  string       $password The password hash for the user's password.
	 * @param  bool|boolean $canlogin Whether the user is able to login.
	 * @return int 					  The user's id.
	 */
	public function createUser(string $email, string $password, array $groups=array('auth.normal')) {
		Database::instance()->selectdb('auth');

		$tprefix = Config::get('db.table_prefix.auth');
		$passObj = new Password($password);

		Database::instance()->simpleInsert($tprefix.'users', array(
			'email' => $email,
			'password' => $passObj->getHash(),
			'groups' => implode(',', $groups)
		));

		$uidres = Database::instance()->simpleSelect($tprefix.'users', 'uid', 'email=?', array($email));
		$uid = $uidres->fetch_row()[0];

		$existsRes = Database::instance()->simpleSelect($tprefix.'antihijack', 'uid', 'uid=?', array($uid));
		if (!$existsRes || $existsRes->fetch_row() === NULL)
		{
			Database::instance()->simpleInsert($tprefix.'antihijack', array(
				'uid' => $uid,
				'loginattempts' => 0,
				'devicelist' => serialize(array(F::getDeviceInfoId())),
				'allowmulti' => '1'
			));
		}
		else
		{
			Database::instance()->simpleUpdate($tprefix.'antihijack', array(
				'loginattempts' => 0,
				'devicelist' => serialize(array(F::getDeviceInfoId())),
				'allowmulti' => '1'
			), 'uid=?', array($uid));
		}

		return $uid;
	}

	/**
	 * Deletes a user from the database. Throws an MySQLException on failure.
	 * This function assumes the user exists for efficiency reasons.
	 * 
	 * @param  string $email The user's email address.
	 */
	public function deleteUser(string $email) : bool {
		Database::instance()->selectdb('auth');
		$tprefix = Config::get('db.table_prefix.auth');

		$uidRes = Database::instance()->simpleSelect($tprefix.'users', 'uid', 'email=?', array($email));
		if ($uidRes)
		{
			$row = $uidRes->fetch_row();
			if ($row != NULL)
			{
				$uid = $row[0];

				// delete db records
				Database::instance()->simpleDelete($tprefix.'users', 'uid=?', array($uid));
				Database::instance()->simpleDelete($tprefix.'sessions', 'uid=?', array($uid));

				return true;
			}
		}

		return false;
	}

	/**
	 * Checks whether the specified password matches the user's password.
	 *
	 * @deprecated PermBuild Deprecated because of User class.
	 * 
	 * @param  string   $email    Email of the user.
	 * @param  Password $password Password object to use.
	 * @return bool            	  True if passwords matches, false if not.
	 */
	public function matchPassword(string $email, Password $password) : bool {
		Database::instance()->selectdb('auth');
		$tprefix = Config::get('db.table_prefix.auth');

		$result = Database::instance()->simpleSelect($tprefix.'users', 'password', 'email=?', array($email));
		if ($result)
		{
			$row = $result->fetch_row();
			if (count($row) > 0)
			{
				$dbHashedPass = new Password($row[0], '', true);

				return $dbHashedPass->verify($password);
			}
		}

		return false;
	}

 	/**
	 * Matches a session key against the key associated with the provided
	 * uid in the database.
	 *
	 * @deprecated PermBuild Deprecated because of User class.
	 * 
	 * @param  int    $uid        Id of the user to check.
	 * @param  string $sessionkey The session key.
	 * @return bool               True if the session keys matches, false if not.
	 */
	public function matchSession(int $uid, string $sessionkey) : bool {
		Database::instance()->selectdb('auth');
		$tprefix = Config::get('db.table_prefix.auth');

		$devRes = Database::instance()->simpleSelect($tprefix.'antihijack', 'devicelist', 'uid=?', array($uid));
		if ($devRes)
		{
			$row = $devRes->fetch_row();
			if ($row && count($row) > 0)
			{ // check allowed device
				$allowedDevice = false;
				$currDevice = F::getDeviceInfoId();

				$devicelist = unserialize($row[0]);

				if ($devicelist)
				{
					foreach ($devicelist as $device)
					{
						if ($device === $currDevice)
						{
							$allowedDevice = true;
							break;
						}
					}
				}

				if ($allowedDevice)
				{
					// check session key and session expiration
					$result = Database::instance()->simpleSelect($tprefix.'sessions', 'sessionkey,created,maxtime', 'uid=?', array($uid));

					if ($result)
					{
						$row = $result->fetch_assoc();

						if ($row && $row['sessionkey'] === $sessionkey)
						{
							$created = strtotime($row['created']);

							if ($row['maxtime'] === 0)
							{ // ignore expiration if no expiration time
								return true;
							}
							else
							{
								return time() <= $created + $row['maxtime'];
							}
						}
					}
				}
			}
		}

		return false;
	}

	/**
	 * Get the password salt of a user.
	 *
	 * @deprecated PermBuild Deprecated because of User class.
	 * 
	 * @param  string $email The email associated with the user.
	 * @return string        The salt, null on error.
	 */
	public function getPasswordSalt(string $email) : string {
		Database::instance()->selectdb('auth');
		$tprefix = Config::get('db.table_prefix.auth');

		$result = Database::instance()->simpleSelect($tprefix.'users', 'password', 'email=?', array($email));

		if ($result)
		{
			$row = $result->fetch_row();
			if (count($row) > 0)
			{
				$pass = new Password($row[0], '', true);
				return $pass->getSalt();
			}
		}

		return '';
	}

	/**
	 * Checks whether a user is allowed to login.
	 *
	 * @deprecated PermBuild Deprecated because of User class.
	 * 
	 * @param  string $email The email associated with the user.
	 * @return bool          True if the user can login, false if not.
	 */
	public function canLogin(string $email) : bool {
		Database::instance()->selectdb('auth');
		$tprefix = Config::get('db.table_prefix.auth');

		$dbres = Database::instance()->simpleSelect($tprefix.'users', 'uid,canlogin', 'email=?', array($email));
		if ($dbres)
		{
			$user = $dbres->fetch_assoc();
			if ($user)
			{
				if ($user['canlogin'] === '1')
				{
					$dbRes = Database::instance()->simpleSelect($tprefix.'antihijack', 'loginattempts,attempttime,allowmulti', 'uid=?', array($user['uid']));
					if ($dbRes)
					{
						$ah = $dbRes->fetch_assoc();
						if ($ah)
						{
							$hasActiveSession = true;
							$sessRes = Database::instance()->simpleSelect($tprefix.'sessions', 'created,maxtime', 'uid=?', array($user['uid']));

							if ($sessRes)
							{
								$row = $sessRes->fetch_assoc();
								if ($row)
								{
									$sessCreated = strtotime($row['created']);
									if ($row['maxtime'] === 0 || time() > $sessCreated + $row['maxtime'])
									{
										$hasActiveSession = false;
									}
								}
							}

							if (!$ah['allowmulti'] && $hasActiveSession)
							{ // disallow login from multiple places
								return false;
							}

							// reset blocked login if freezetime has been exceeded
							if (strtotime($ah['attempttime']) + Config::get('auth.maxFreezeTime') < time())
							{
								Database::instance()->selectdb('auth');
								Database::instance()->simpleUpdate($tprefix.'antihijack', array('loginattempts' => 0), 'uid=?', array($user['uid']));
								$ah['loginattempts'] = 0;
							}

							if (Config::get('auth.enableLoginFailureBlock') === true && $ah['loginattempts'] >= Config::get('auth.maxLoginFailure'))
							{ // disallow login if too many failed attempts was made
								return false;
							}

							return true;
						}
					}
					else
					{ // antihijack functions are ignored if no record is found
						return true;
					}
				}
			}
		}

		return false;
	}

	/**
	 * Checks whether a user exists in the database.
	 *
	 * @deprecated PermBuild Deprecated because of User class.
	 * 
	 * @param  string $email The email associated with the user.
	 * @return bool          True if the user exists, false if not.
	 */
	public function userExists(string $email) : bool {
		Database::instance()->selectdb('auth');
		$tprefix = Config::get('db.table_prefix.auth');

		$res = Database::instance()->simpleSelect($tprefix.'users', 'uid', 'email=?', array($email));
		if ($res)
		{
			return $res->fetch_row() !== NULL;
		}

		return false;
	}

	/**
	 * Tries to create a login session for the provided user and password. If a session already exists,
	 * replace the session. It will return the new session key.
	 *
	 * @deprecated PermBuild Deprecated because of User class.
	 * 
	 * @param  string       $email        The email of the user.
	 * @param  Password     $password     Password object associated with the user's password.
	 * @param  APIMessage   &$msgOut      Return message for possible errors.
	 * @param  bool|boolean $noexpiration If true, the session never expires, if false, it will have a time which is set in the config.
	 * @return string                     The session key.
	 */
	public function passwordLogin(string $email, Password $password, APIMessage &$msgOut, bool $noexpiration=false) : string {
		if ($this->userExists($email))
		{
			if ($this->canLogin($email))
			{
				$uid = $this->getUidFromEmail($email);

				if ($uid !== -1)
				{
					if ($this->matchPassword($email, $password))
					{
						Database::instance()->selectdb('auth');
						$tprefix = Config::get('db.table_prefix.auth');
						
						$sessionkey = F::randomString(64, F::RANDOM_CHARSET_SECRET);

						// reset failed logins
						Database::instance()->simpleUpdate($tprefix.'antihijack', array(
							'loginattempts' => 0)
						, 'uid=?', array($uid));

						// check if session exists, if it is, update it
						$eres = Database::instance()->simpleSelect($tprefix.'sessions', 'uid', 'uid=?', array($uid));
						if ($eres)
						{
							$erow = $eres->fetch_row();
							if ($erow)
							{
								Database::instance()->simpleUpdate($tprefix.'sessions', array(
									'sessionkey' => $sessionkey,
									'maxtime' => ($noexpiration ? 0 : 1000)
								), 'uid=?', array($uid));
								return $sessionkey;
							}
						}

						Database::instance()->simpleInsert($tprefix.'sessions', array(
							'uid' => $uid,
							'sessionkey' => $sessionkey,
							'maxtime' => ($noexpiration ? 0 : 1000)
						));
						return $sessionkey;
					}
					else
					{
						$msgOut->addError(AuthErrors::InvalidLogin);

						// increment failed logins counter
						Database::instance()->selectdb('auth');
						$tprefix = Config::get('db.table_prefix.auth');

						$prep = Database::instance()->prepare('UPDATE `'.$tprefix.'antihijack` SET `loginattempts`=loginattempts+1 WHERE `uid`=?');
						if ($prep)
						{
							$prep->bind_param('i', $uid);
							$prep->execute();
							$prep->close();
						}
						else
						{
							$msgOut->addError(AuthErrors::UnknownError);
						}
					}
				}
				else
				{
					$msgOut->addError(AuthErrors::UnknownError);
				}
			}
			else
			{
				$msgOut->addError(AuthErrors::CannotLogin);
			}
		}
		else
		{
			$msgOut->addError(AuthErrors::UserDoesntExist);
		}

		return "";
	}

	/**
	 * Change the password of a user. This action is required the email and password for security reasons.
	 * Session keys have a higher chance of getting stolen than the user's password, and therefore, using
	 * a session key to identify a user is not as secure as using the email and password.
	 *
	 * @deprecated PermBuild Deprecated because of User class.
	 * 
	 * @param  string     $email       The user's email address.
	 * @param  Password   $oldPassword The user's current password.
	 * @param  Password   $newPassword The new password for the user.
	 * @param  APIMessage &$msgOut     Return message for possible errors.
	 * @return bool                    True if password was changed, false if not.
	 */
	public function changePassword(string $email, Password $oldPassword, Password $newPassword, APIMessage &$msgOut) : bool {
		if ($this->passwordLogin($email, $oldPassword, $msgOut) !== "")
		{
			Database::instance()->selectdb('auth');
			$tprefix = Config::get('db.table_prefix.auth');

			Database::instance()->simpleUpdate($tprefix.'users', array(
				'password' => $newPassword->getHash()
			), 'email=?', array($email));

			return true;
		}

		return false;
	}

	/**
	 * Get the user's id from their email.
	 *
	 * @deprecated PermBuild Deprecated because of User class.
	 * 
	 * @param  string $email The email associated with the user.
	 * @return int           The user's id. Returns -1 if the user was not found.
	 */
	public function getUidFromEmail(string $email) : int {
		if (AuthSystem::instance()->userExists($email))
		{
			Database::instance()->selectdb('auth');
			$tprefix = Config::get('db.table_prefix.auth');

			$selRes = Database::instance()->simpleSelect($tprefix.'users', 'uid', 'email=?', array($email));
			if ($selRes && ($row = $selRes->fetch_row()))
			{
				return $row[0];
			}
		}

		return -1;
	}

	/**
	 * Check if a user is an admin. DEPRECATED.
	 *
	 * @deprecated PermBuild Deprecated because of User class.
	 * 
	 * @param  int     $uid The user id associated with the user.
	 * @return boolean      True if the user is an admin, false if not.
	 */
	public function isAdmin(int $uid) : bool {
		Database::instance()->selectdb('auth');
		$tprefix = Config::get('db.table_prefix.auth');

		$permRes = Database::instance()->simpleSelect($tprefix.'permissions', 'isadmin', 'uid=?', array($uid));
		if ($permRes)
		{
			$row = $permRes->fetch_row();
			if ($row)
			{
				return $row[0] === '1';
			}
		}

		return false;
	}
};
