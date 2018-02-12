<?php
/**
 * This module provides authentication utilities through the API as well as a request API
 * for user authentication.
 *
 * @copyright 2012 - 2017 Zurvan Labs
 * @author snixtho <snixtho@gmail.com>
 */

declare(strict_types=1);

use Z7API\Core\{
	APIRequestModule,
	APIMessage,
	APIErrors,
	Event,
	IO,
	IOValidationException,
	F,
	Database,
	Config,
	MySQLException,
	MySQLErrors,
	ModuleOverrideOutputException
};
use Z7API\Lib\Auth\{
	AuthSystem,
	AuthErrors,
	User,
	UserFactory,
	Password,
	Permissions,
	PermissionDuplicateException,
	InvalidPermissionDisplayException,
	InvalidPermissionNameException,
	PermissionDoesNotExistException,
	GroupDuplicateException,
	InvalidGroupDisplayException,
	InvalidGroupNameException,
	InvalidPermissionNamesException,
	GroupDoesNotExistException
};

class Module_Auth extends APIRequestModule {
	public function required() {}

	public function eventHandlers() {
		yield new Event('action.login', 'auth.action.login', 'onActionLogin', $this);
		yield new Event('action.logout', 'auth.action.logout', 'onActionLogout', $this);
		yield new Event('action.changepassword', 'auth.action.changepassword', 'onActionChangePassword', $this);
		yield new Event('action.changeemail', 'auth.action.changeemail', 'onActionChangeEmail', $this);
		yield new Event('action.getpermissions', 'auth.action.getpermissions', 'onActionGetPermissions', $this);
		yield new Event('action.refresh', 'auth.action.refresh', 'onActionRefresh', $this);

		yield new Event('action.admin', 'auth.action.admin', 'onActionAdmin', $this);
		yield new Event('auth.admin.createuser', 'auth.admin.createuser', 'onAdminCreateUser', $this);
		yield new Event('auth.admin.deleteuser', 'auth.admin.deleteuser', 'onAdminDeleteUser', $this);
		yield new Event('auth.admin.getuser', 'auth.admin.getuser', 'onAdminGetUser', $this);

		yield new Event('auth.admin.addpermission', 'auth.admin.addpermission', 'onAdminAddPermission', $this);
		yield new Event('auth.admin.deletepermission', 'auth.admin.deletepermission', 'onAdminDeletePermission', $this);
		yield new Event('auth.admin.getpermission', 'auth.admin.getpermission', 'onAdminGetPermission', $this);

		yield new Event('auth.admin.addgroup', 'auth.admin.addgroup', 'onAdminAddGroup', $this);
		yield new Event('auth.admin.deletegroup', 'auth.admin.deletegroup', 'onAdminDeleteGroup', $this);
		yield new Event('auth.admin.getgroup', 'auth.admin.getgroup', 'onAdminGetGroup', $this);
	}

	public function init(APIMessage $msg) : APIMessage {
		F::loadLibrary('authutils');
		$this->tryInstall();

		return new APIMessage();
	}

	private function tryInstall() {
		// initialize the auth system.
		AuthSystem::instance();
	}

	public function onPOST(APIMessage $msg) : APIMessage { return new APIMessage(); }

	/**
	 * Handles login actions.
	 */
	public function onActionLogin(APIMessage $msg, string $arg1, string $arg2) : APIMessage {
		$retMsg = new APIMessage();

		User::DoLogin($msg, $retMsg);

		return $retMsg;
	}

	/**
	 * Handles logging out requests.
	 */
	public function onActionLogout(APIMessage $msg, string $arg1, string $arg2) : APIMessage {
		$retMsg = new APIMessage();

		User::DoLogout($msg, $retMsg);

		return $retMsg;
	}

	/**
	 * Handles change password actions.
	 */
	public function onActionChangePassword(APIMessage $msg, string $arg1, string $arg2) : APIMessage {
		$retMsg = new APIMessage();

		try
		{
			IO::Validate($msg, array(
				'email' => array('required' => true, 'type' => 'string'),
				'password' => array('required' => true, 'type' => 'string'),
				'newpassword' => array('required' => true, 'type' => 'string')
			));
		}
		catch (IOValidationException $e)
		{
			$retMsg->addErrorFormat($e->getCode(), $e->getValueName());
			return $retMsg;
		}

		$user = UserFactory::GetFromEmail($msg->get('email'));
		if (!$user->isValid())
		{
			$retMsg->addError(AuthErrors::UserDoesntExist);
			return $retMsg;
		}

		// check if the user has permission to change their password.
		if ($user->hasPermission('!auth.accountmanaging.basic'))
		{
			$retMsg->addError(AuthErrors::PermissionDenied);
			return $retMsg;
		}

		$result = $user->checkLogin(new Password($msg->get('password'), $user->getPasswordSalt()));

		if ($result === 0)
		{
			$user->setPassword(new Password($msg->get('newpassword')));
			$retMsg->set('success', true);

			return $retMsg;
		}
		else if ($result === 1)
		{
			$retMsg->addError(AuthErrors::InvalidLogin);
		}
		else
		{
			$retMsg->addError(AuthErrors::CannotLogin);
		}

		return $retMsg;
	}

	/**
	 * Handles change email actions.
	 */
	public function onActionChangeEmail(APIMessage $msg, string $arg1, string $arg2) : APIMessage {
		$retMsg = new APIMessage();

		try
		{
			IO::Validate($msg, array(
				'email' => array('required' => true, 'type' => 'string'),
				'password' => array('required' => true, 'type' => 'string'),
				'newemail' => array('required' => true, 'type' => 'string')
			));
		}
		catch (IOValidationException $e)
		{
			$retMsg->addErrorFormat($e->getCode(), $e->getValueName());
			return $retMsg;
		}

		$user = UserFactory::GetFromEmail($msg->get('email'));
		if (!$user->isValid())
		{
			$retMsg->addError(AuthErrors::UserDoesntExist);
			return $retMsg;
		}

		// check if the user has permission to change their email
		if ($user->hasPermission('!auth.accountmanaging.basic'))
		{
			$retMsg->addError(AuthErrors::PermissionDenied);
			return $retMsg;
		}

		$result = $user->checkLogin(new Password($msg->get('password'), $user->getPasswordSalt()));

		if ($result === 0)
		{
			if (!$user->setEmail($msg->get('newemail')))
			{ // failed setting email, usually its because it already exists.
				$retMsg->addError(AuthErrors::SetEmailFailed);
			}
			else
			{
				$retMsg->set('success', true);
			}

			return $retMsg;
		}
		else if ($result === 1)
		{
			$retMsg->addError(AuthErrors::InvalidLogin);
		}
		else
		{
			$retMsg->addError(AuthErrors::CannotLogin);
		}

		return $retMsg;
	}

	public function onActionRefresh(APIMessage $msg, string $arg1, string $arg2) : APIMessage {
		$retMsg = new APIMessage();

		try
		{
			IO::Validate($msg, array(
				'sessionuid' => array('required' => true, 'type' => 'integer'),
				'sessionkey' => array('required' => true, 'type' => 'string')
			));
		}
		catch (IOValidationException $e)
		{
			$retMsg->addErrorFormat($e->getCode(), $e->getValueName());
			return $retMsg;
		}

		$user = UserFactory::GetFromUid($msg->get('sessionuid'));
		if (!$user->isValid())
		{ // user invalid
			$retMsg->addError(AuthErrors::UserDoesntExist);
			return $retMsg;
		}

		if (!$user->matchesSession($msg->get('sessionuid'), $msg->get('sessionkey')))
		{// session invalid
			$retMsg->addError(AuthErrors::InvalidSession);
			return $retMsg;
		}

		$user->updateSessionActivity();

		$retMsg->set("success", true);

		return $retMsg;
	}

	/**
	 * Handles getting of user's permissions.
	 */
	public function onActionGetPermissions(APIMessage $msg, string $arg1, string $arg2) : APIMessage {
		$retMsg = new APIMessage();

		try
		{
			IO::Validate($msg, array(
				'sessionuid' => array('required' => true, 'type' => 'integer'),
				'sessionkey' => array('required' => true, 'type' => 'string')
			));
		}
		catch (IOValidationException $e)
		{
			$retMsg->addErrorFormat($e->getCode(), $e->getValueName());
			return $retMsg;
		}

		$user = UserFactory::GetFromUid($msg->get('sessionuid'));
		if (!$user->isValid())
		{ // user invalid
			$retMsg->addError(AuthErrors::UserDoesntExist);
			return $retMsg;
		}

		if (!$user->matchesSession($msg->get('sessionuid'), $msg->get('sessionkey')))
		{// session invalid
			$retMsg->addError(AuthErrors::InvalidSession);
			return $retMsg;
		}

		try
		{
			IO::Validate($msg, array(
				'details' => array('required' => false, 'type' => 'boolean', 'default' => false)
			));
		}
		catch (IOValidationException $e)
		{
			$retMsg->addErrorFormat($e->getCode(), $e->getValueName());
			return $retMsg;
		}

		$outArr = array();
		$arr = array();

		$user->updateSessionActivity();

		foreach ($user->getPermissions()->names() as $perm)
		{
			$arr[] = $perm;
		}

		if ($msg->get('details')) {
			$qcond = "";
			foreach ($user->getPermissions()->names() as $perm)
				$qcond .= "permissionname=? OR ";
			$qcond = substr($qcond, 0, strlen($qcond) - 4);

			$tprefix = Config::get('db.table_prefix.auth');
			$pidRes = Database::instance()->simpleSelect($tprefix."permissions", "pid,permissionname", $qcond, $arr);
			if (!$pidRes) {
				$retMsg->addError(APIErrors::Unknown);
				return $retMsg;
			}

			while (($row = $pidRes->fetch_assoc()) != NULL) {
				$pid = $row['pid'];
				$name = $row['permissionname'];
				$displayname = "";
				$description = "";

				$permRes = Database::instance()->simpleSelect($tprefix."permissionsdata", "*", "pid=?", array($pid));
				if ($pidRes && $pidRes->num_rows > 0) {
					$dataRow = $permRes->fetch_assoc();
					$displayname = $dataRow['displayname'];
					$description = $dataRow['description'];
				}

				$outArr[] = array(
					'name' => $name,
					'displayname' => $displayname,
					'description' => $description
				);
			}
		}
		else
		{
			$outArr = $arr;
		}

		$retMsg->set("permissions", $outArr);
		$retMsg->set('success', true);

		return $retMsg;
	}

	/**
	 * This function tries to map in-comming admin actions.
	 */
	public function onActionAdmin(APIMessage $msg, string $adminAction, string $subaction2) : APIMessage {
		$retMsg = new APIMessage();
		$adminActionAllowed = false;

		try
		{
			IO::Validate($msg, array(
				'sessionuid' => array('required' => true, 'type' => 'integer'),
				'sessionkey' => array('required' => true, 'type' => 'string')
			), false);
		}
		catch (IOValidationException $e)
		{
			$retMsg->addErrorFormat($e->getCode(), $e->getValueName());
			return $retMsg;
		}

		$user = UserFactory::GetFromUid($msg->get('sessionuid'));
		if (!$user->isValid())
		{ // user invalid
			$retMsg->addError(AuthErrors::UserDoesntExist);
			return $retMsg;
		}

		if (!$user->matchesSession($msg->get('sessionuid'), $msg->get('sessionkey')))
		{// session invalid
			$retMsg->addError(AuthErrors::InvalidSession);
			return $retMsg;
		}

		if ($user->hasPermission('auth.admin.action'))
		{ // has permission to further proceed.
			$adminActionAllowed = true;
			$user->updateSessionActivity();
		}

		if ($adminActionAllowed)
		{
			global $API;

			$eventName = 'auth.admin.'.$adminAction;

			if ($adminAction != "" && $API->mm->eventExists($eventName))
			{
				$result = $API->mm->dispatchEvent($eventName, $msg, $user);
				$retMsg->addFromMessage($result[$eventName]);
			}
			else
			{
				$retMsg->addError(APIErrors::InvalidAction);
			}
		}
		else
		{
			$retMsg->addError(AuthErrors::PermissionDenied);
		}

		return $retMsg;
	}

	/**
	 * Handles deletion of users in the database.
	 */
	public function onAdminGetUser(APIMessage $msg, User $adminUser) : APIMessage {
		$retMsg = new APIMessage();

		if ($adminUser->hasPermission('!auth.admin.getuser'))
		{
			$retMsg->addError(AuthErrors::PermissionDenied);
			return $retMsg;
		}

		try
		{
			IO::Validate($msg, array(
				'email' => array('required' => false, 'type' => 'string'),
				'uid' => array('required' => false, 'type' => 'integer'),
				'emailmatch' => array('required' => false, 'type' => 'string')
			));
		}
		catch (IOValidationException $e)
		{
			$retMsg->addErrorFormat($e->getCode(), $e->getValueName());
			return $retMsg;
		}

		$users = array();

		if ($msg->has('email'))
		{
			$user = UserFactory::GetFromEmail($msg->get('email'));
			if ($user->isValid())
			{
				array_push($users, $user);
			}
		}

		if ($msg->has('uid'))
		{
			$user = UserFactory::GetFromUid($msg->get('uid'));
			if ($user->isValid())
			{
				array_push($users, $user);
			}
		}

		if ($msg->has('emailmatch'))
		{
			$matchExpr = str_replace('*', '%', $msg->get('emailmatch'));
			Database::instance()->selectdb('auth');
			$tprefix = Config::get('db.table_prefix.auth');
			$result = Database::instance()->simpleSelect($tprefix.'users', 'uid', 'email LIKE ?', array($matchExpr));

			if (!$result || $result->num_rows <= 0)
			{
				return $retMsg;
			}

			while ($uidRow = $result->fetch_row())
			{
				$user = UserFactory::GetFromUid($uidRow[0]);
				if ($user->isValid())
				{
					array_push($users, $user);
				}
			}
		}


		$retMsg->set('users', F::getAllPropertiesArray($users));
		$retMsg->set('success', true);

		return $retMsg;
	}

	/**
	 * Handles creation of users in the database.
	 */
	public function onAdminCreateUser(APIMessage $msg, User $adminUser) : APIMessage {
		$retMsg = new APIMessage();

		if ($adminUser->hasPermission('!auth.admin.createuser'))
		{
			$retMsg->addError(AuthErrors::PermissionDenied);
			return $retMsg;
		}

		try
		{
			IO::Validate($msg, array(
				'email' => array('required' => true, 'type' => 'string'),
				'password' => array('required' => true, 'type' => 'string'),
				'groups' => array('required' => true, 'type' => 'array')
			));
		}
		catch (IOValidationException $e)
		{
			$retMsg->addErrorFormat($e->getCode(), $e->getValueName());
			return $retMsg;
		}

		try
		{
			// check email validity first
			if (!filter_var($msg->get('email'), FILTER_VALIDATE_EMAIL))
			{
				$retMsg->addError(AuthErrors::InvalidEmail);
				return $retMsg;
			}

			$uid = AuthSystem::instance()->createUser($msg->get('email'), $msg->get('password'),  $msg->get('groups'));
			$msg->set('uid', $uid);
		}
		catch (MySQLException $e)
		{
			if ($e->getCode() == MySQLErrors::DUPLICATE_KEY)
				$retMsg->addError(AuthErrors::UserAlreadyExists);
			else
				$retMsg->addError(AuthErrors::Unknown);

			return $retMsg;
		}

		$retMsg->set('success', true);

		return $retMsg;
	}

	/**
	 * Handles deletion of users in the database.
	 */
	public function onAdminDeleteUser(APIMessage $msg, User $adminUser) : APIMessage {
		$retMsg = new APIMessage();

		if ($adminUser->hasPermission('!auth.admin.deleteuser'))
		{
			$retMsg->addError(AuthErrors::PermissionDenied);
			return $retMsg;
		}

		try
		{
			IO::Validate($msg, array(
				'email' => array('required' => true, 'type' => 'string')
			));
		}
		catch (IOValidationException $e)
		{
			$retMsg->addErrorFormat($e->getCode(), $e->getValueName());
			return $retMsg;
		}

		if ($admnUser->getEmail() == $msg->get('email'))
		{
			$retMsg->addError(AuthErrors::CantDeleteAdminSelf);
			return $retMsg;
		}

		if (!AuthSystem::instance()->deleteUser($msg->get('email')))
		{
			$retMsg->addError(AuthErrors::UserDeletionFailed);
		}
		else
		{
			$retMsg->set('success', true);
		}

		return $retMsg;
	}

	/**
	 * Handles creation of permissions.
	 */
	public function onAdminAddPermission(APIMessage $msg, User $adminUser) : APIMessage {
		$retMsg = new APIMessage();
		if ($adminUser->hasPermission('!auth.admin.createpermission'))
		{
			$retMsg->addError(AuthErrors::PermissionDenied);
			return $retMsg;
		}

		try {
			IO::Validate($msg, array(
				'name' => array('required' => true, 'type' => 'string'),
				'displayname' => array('required' => true, 'type' => 'string'),
				'description' => array('required' => true, 'type' => 'string')
			));
		} catch (IOValidationException $e) {
			$retMsg->addErrorFormat($e->getCode(), $e->getValueName());
			return $retMsg;
		}

		try {
			Permissions::AddPermission($msg->get('name'), $msg->get('displayname'), $msg->get('description'));
			$retMsg->set("success", true);
		} catch (PermissionDuplicateException $e) {
			$retMsg->addError(AuthErrors::PermissionDuplicate);
		} catch (InvalidPermissionDisplayException $e) {
			$retMsg->addError(AuthErrors::PermissionInvalidDisplayName);
		} catch (InvalidPermissionNameException $e) {
			$retMsg->addError(AuthErrors::PermissionInvalidName);
		} catch (Exception $e) {
			$retMsg->addError(APIErrors::Unknown);
		}

		return $retMsg;
	}

	/**
	 * Handles deletion of permissions.
	 */
	public function onAdminDeletePermission(APIMessage $msg, User $adminUser) : APIMessage {
		$retMsg = new APIMessage();

		if ($adminUser->hasPermission('!auth.admin.deletepermission'))
		{
			$retMsg->addError(AuthErrors::PermissionDenied);
			return $retMsg;
		}

		try {
			IO::Validate($msg, array(
				'name' => array('required' => true, 'type' => 'string')
			));
		} catch (IOValidationException $e) {
			$retMsg->addErrorFormat($e->getCode(), $e->getValueName());
			return $retMsg;
		}

		try {
			Permissions::DeletePermission($msg->get('name'));
			$retMsg->set("success", true);
		} catch (InvalidPermissionNameException $e) {
			$retMsg->addError(AuthErrors::PermissionInvalidName);
		} catch (PermissionDoesNotExistException $e) {
			$retMsg->addError(AuthErrors::PermissionDoesNotExist);
		} catch (Exception $e) {
			$retMsg->addError(APIErrors::Unknown);
		}

		return $retMsg;
	}

	/**
	 * Handles getting of permission information.
	 */
	public function onAdminGetPermission(APIMessage $msg, User $adminUser) : APIMessage {
		$retMsg = new APIMessage();

		if ($adminUser->hasPermission('!auth.admin.getpermission'))
		{
			$retMsg->addError(AuthErrors::PermissionDenied);
			return $retMsg;
		}

		try {
			IO::Validate($msg, array(
				'name' => array('required' => true, 'type' => 'string')
			));
		} catch (IOValidationException $e) {
			$retMsg->addErrorFormat($e->getCode(), $e->getValueName());
			return $retMsg;
		}

		try {
			$permInfo = Permissions::GetPermission($msg->get('name'));

			foreach ($permInfo as $name => $value)
				$retMsg->set($name, $value);

			$retMsg->set("success", true);
		} catch (InvalidPermissionNameException $e) {
			$retMsg->addError(AuthErrors::PermissionInvalidName);
		} catch (PermissionDoesNotExistException $e) {
			$retMsg->addError(AuthErrors::PermissionDoesNotExist);
		} catch (Exception $e) {
			$retMsg->addError(APIErrors::Unknown);
		}

		return $retMsg;
	}

	/**
	 * Handles adding of groups.
	 */
	public function onAdminAddGroup(APIMessage $msg, User $adminUser) : APIMessage {
		$retMsg = new APIMessage();
		if ($adminUser->hasPermission('!auth.admin.creategroup'))
		{
			$retMsg->addError(AuthErrors::PermissionDenied);
			return $retMsg;
		}

		try {
			IO::Validate($msg, array(
				'name' => array('required' => true, 'type' => 'string'),
				'displayname' => array('required' => true, 'type' => 'string'),
				'description' => array('required' => true, 'type' => 'string'),
				'priority' => array('required' => false, 'type' => 'integer', 'default' => 0),
				'permissions' => array('required' => false, 'type' => 'array', 'default' => array())
			));
		} catch (IOValidationException $e) {
			$retMsg->addErrorFormat($e->getCode(), $e->getValueName());
			return $retMsg;
		}

		try {
			Permissions::AddGroup($msg->get('name'), $msg->get('permissions'), $msg->get('displayname'), $msg->get('description'), $msg->get('priority'));
			$retMsg->set("success", true);
		} catch (GroupDuplicateException $e) {
			$retMsg->addError(AuthErrors::GroupDuplicate);
		} catch (InvalidGroupDisplayException $e) {
			$retMsg->addError(AuthErrors::GroupInvalidDisplayName);
		} catch (InvalidGroupNameException $e) {
			$retMsg->addError(AuthErrors::GroupInvalidName);
		} catch (Exception $e) {
			$retMsg->addError(APIErrors::Unknown);
		}

		return $retMsg;
	}

	/**
	 * Handles deletion of groups.
	 */
	public function onAdminDeleteGroup(APIMessage $msg, User $adminUser) : APIMessage {
		$retMsg = new APIMessage();

		if ($adminUser->hasPermission('!auth.admin.deletegroup'))
		{
			$retMsg->addError(AuthErrors::PermissionDenied);
			return $retMsg;
		}

		try {
			IO::Validate($msg, array(
				'name' => array('required' => true, 'type' => 'string')
			));
		} catch (IOValidationException $e) {
			$retMsg->addErrorFormat($e->getCode(), $e->getValueName());
			return $retMsg;
		}

		try {
			Permissions::DeleteGroup($msg->get('name'));
			$retMsg->set("success", true);
		} catch (InvalidGroupNameException $e) {
			$retMsg->addError(AuthErrors::GroupInvalidName);
		} catch (GroupDoesNotExistException $e) {
			$retMsg->addError(AuthErrors::GroupDoesNotExist);
		} catch (Exception $e) {
			$retMsg->addError(APIErrors::Unknown);
		}

		return $retMsg;
	}

	/**
	 * Handles getting of permission information.
	 */
	public function onAdminGetGroup(APIMessage $msg, User $adminUser) : APIMessage {
		$retMsg = new APIMessage();

		if ($adminUser->hasPermission('!auth.admin.getgroup'))
		{
			$retMsg->addError(AuthErrors::PermissionDenied);
			return $retMsg;
		}

		try {
			IO::Validate($msg, array(
				'name' => array('required' => true, 'type' => 'string')
			));
		} catch (IOValidationException $e) {
			$retMsg->addErrorFormat($e->getCode(), $e->getValueName());
			return $retMsg;
		}

		try {
			$permInfo = Permissions::GetGroup($msg->get('name'));

			foreach ($permInfo as $name => $value)
				$retMsg->set($name, $value);

			$retMsg->set("success", true);
		} catch (InvalidGroupNameException $e) {
			$retMsg->addError(AuthErrors::GroupInvalidName);
		} catch (GroupDoesNotExistException $e) {
			$retMsg->addError(AuthErrors::GroupDoesNotExist);
		} catch (Exception $e) {
			$retMsg->addError(APIErrors::Unknown);
		}

		return $retMsg;
	}
};
