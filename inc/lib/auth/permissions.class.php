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
	MysqlException,
	MySQLErrors
};

/**
 * Handles mainly loading of permissions.
 */
class Permissions {
	const PermissionMatch = '/^([a-zA-Z0-9_]+(\\.?))+$/';
	const GroupMatch = '/^([a-zA-Z0-9_]+(\\.?))+$/';

	/**
	 * Load all permissions associated with the provided groups. Taking permission wildcard and negation into account.
	 * Groups with higher priority will override permissions with groups with a lower priority.
	 *
	 * @param  array  $groupnames An array of groups to get permissions from.
	 * @return PermissionSet      A set containing the permissions found.
	 */
	public static function loadPermissions(array $groupnames) : PermissionSet {
		$tprefix = Config::get('db.table_prefix.auth');
		$groups = array();

		$groupQueryLogic = '';
		$groupnamesLen = count($groupnames);
		for ($i = 0; $i < $groupnamesLen; $i++)
		{
			$groupnames[$i] = trim($groupnames[$i]);

			$groupQueryLogic .= 'groupname=?';

			if ($i+1 < $groupnamesLen)
				$groupQueryLogic .= ' OR ';
		}

		Database::instance()->selectdb('auth');
		$result = Database::instance()->simpleSelect($tprefix.'groups', 'permissions', $groupQueryLogic, $groupnames, array('orderby' => 'priority'));
		if (!$result || $result->num_rows <= 0)
		{
			return new PermissionSet();
		}

		$permset = new PermissionSet();

		while ($row = $result->fetch_row())
		{
			$groupPerms = explode(',', $row[0]);
			foreach ($groupPerms as $perm)
			{
				$perm = trim($perm);

				// dont process empty permissions
				if ($perm == '') continue;

				if ($perm[0] == '!')
				{// remove permission
					$perm = substr($perm, 1);
					$permset->del($perm);
					continue;
				}

				$parts = explode('*', $perm);
				if (count($parts) == 1)
				{ // dont bother wildcard checking
					$permset->set($parts[0]);
					continue;
				}

				// fetch all permissions matching this wild-card enabeld name
				$pattern = implode('%', $parts);
				$permResult = Database::instance()->simpleSelect($tprefix.'permissions', 'permissionname', 'permissionname LIKE ?', array($pattern));

				if (!$permResult || $result->num_rows <= 0)
				{ // no permissions, so just continue to next permission
					continue;
				}

				while ($wildcardPerm = $permResult->fetch_row())
				{
					$permset->set($wildcardPerm[0]);
				}
			}
		}

		return $permset;
	}

	/**
	 * Add a permission to the database.
	 *
	 * @param string $permissionName The name of the permission, should only contain alpha and numbers, _ (underline) and .(punctation).
	 * @param string $displayName    The display name of the permission.
	 * @param string $description    The description of the permission.
	 */
	public static function AddPermission(string $permissionName, string $displayName, string $description) {
		if (strlen($displayName) > 512) throw new InvalidPermissionDisplayException();
		if (!preg_match(Permissions::PermissionMatch, $permissionName)) throw new InvalidPermissionNameException();

		Database::instance()->selectdb('auth');
		$tprefix = Config::get('db.table_prefix.auth');

		try
		{
			Database::instance()->simpleInsert($tprefix.'permissions', array('permissionname' => $permissionName));
			$pidRes = Database::instance()->simpleSelect($tprefix.'permissions', 'pid', 'permissionname=?', array($permissionName));
			if (!$pidRes || $pidRes->num_rows <= 0)
			{
				throw new PermissionException();
			}

			$pid = $pidRes->fetch_row()[0];
			Database::instance()->simpleInsert($tprefix.'permissionsdata', array(
				'pid' => $pid,
				'displayname' => $displayName,
				'description' => $description
			));
		}
		catch (MysqlException $e)
		{
			if ($e->getCode() == MySQLErrors::DUPLICATE_KEY)
				throw new PermissionDuplicateException();

			throw $e;
		}
	}

	/**
	 * Delete a permission from the database, including its data.
	 *
	 * @param string $permissionName The name of the permission to delete.
	 */
	public static function DeletePermission(string $permissionName) {
		if (!preg_match(Permissions::PermissionMatch, $permissionName)) throw new InvalidPermissionNameException();

		Database::instance()->selectdb('auth');
		$tprefix = Config::get('db.table_prefix.auth');

		try
		{
			$res = Database::instance()->simpleSelect($tprefix."permissions", "pid", "permissionname=?", array($permissionName));
			if (!$res || $res->num_rows <= 0)
			{
				throw new PermissionDoesNotExistException();
			}

			$permId = $res->fetch_row()[0];
			Database::instance()->simpleDelete($tprefix."permissions", "pid=?", array($permId));
			Database::instance()->simpleDelete($tprefix."permissionsdata", "pid=?", array($permId));
		}
		catch (MysqlException $e)
		{
			throw $e;
		}
	}

	/**
	 * Get information about an permission from the database.
	 * @param string $permissionName The name of the permission to retrieve data from.
	 */
	public static function GetPermission(string $permissionName) {
		if (!preg_match(Permissions::PermissionMatch, $permissionName)) throw new InvalidPermissionNameException();

		Database::instance()->selectdb('auth');
		$tprefix = Config::get('db.table_prefix.auth');

		try
		{
			$res = Database::instance()->simpleSelect($tprefix."permissions", "pid", "permissionname=?", array($permissionName));
			if (!$res || $res->num_rows <= 0) throw new PermissionDoesNotExistException();
			$dataRes = Database::instance()->simpleSelect($tprefix."permissionsdata", "*", "pid=?", array($res->fetch_row()[0]));
			if (!$dataRes || $dataRes->num_rows <= 0) throw new PermissionException();

			$row = $dataRes->fetch_assoc();
			$row['permissionname'] = $permissionName;
			return $row;
		}
		catch (MysqlException $e)
		{
			throw $e;
		}

		throw new PermissionException();
	}

	/**
	 * Add a group to the database.
	 * @param string      $groupName   The name of the group to add.
	 * @param array       $permissions An array with zero or more permission names the group will have.
	 * @param string      $displayName The display name of the group.
	 * @param string      $description The description of the group.
	 * @param int|integer $priority    The priority of the group, groups with higher priority will overwrite their permissions over lower priority group's permissions.
	 */
	public static function AddGroup(string $groupName, array $permissions, string $displayName, string $description, int $priority=0) {
		if (strlen($displayName) > 512) throw new InvalidGroupDisplayException();
		if (!preg_match(Permissions::GroupMatch, $groupName)) throw new InvalidGroupNameException();

		Database::instance()->selectdb('auth');
		$tprefix = Config::get('db.table_prefix.auth');

		// make sure all permissions are in the database
		/* $nPerms = count($permissions);
		$pQueryLogic = "";

		for ($i = 0; $i < $nPerms; $i++)
		{
			$pQueryLogic .= 'permissionname=?';
			if ($i+1 < $nPerms)
			{
				$pQueryLogic .= ' OR ';
			}
		}

		$npermsRes = Database::instance()->simpleSelect($tprefix.'permissions', 'pid', $pQueryLogic, $permissions);
		if (!$npermsRes || $npermsRes->num_rows != $nPerms)
		{
			throw new InvalidPermissionNamesException();
		} */

		foreach ($permissions as $perm)
		{
			if (!preg_match(Permissions::PermissionMatch, $perm))
			{
				throw new InvalidPermissionNamesException();
			}
		}

		// all permissions are valid, continue adding the group
		try
		{
			Database::instance()->simpleInsert($tprefix.'groups', array(
				'groupname' => $groupName,
				'permissions' => implode(',', $permissions),
				'priority' => $priority
				));

			$gidRes = Database::instance()->simpleSelect($tprefix.'groups', 'gid', 'groupname=?', array($groupName));
			if (!$gidRes || $gidRes->num_rows <= 0)
			{
				throw new PermissionException();
			}

			$gid = $gidRes->fetch_row()[0];
			Database::instance()->simpleInsert($tprefix.'groupsdata', array(
				'gid' => $gid,
				'displayname' => $displayName,
				'description' => $description
			));
		}
		catch (MysqlException $e)
		{
			if ($e->getCode() == MySQLErrors::DUPLICATE_KEY)
				throw new GroupDuplicateException();

			throw $e;
		}
	}

	/**
	 * Delete a group from the database.
	 *
	 * @param string $groupName The name of the group to delete.
	 */
	public static function DeleteGroup(string $groupName) {
		if (!preg_match(Permissions::GroupMatch, $groupName)) throw new InvalidGroupNameException();

		Database::instance()->selectdb('auth');
		$tprefix = Config::get('db.table_prefix.auth');

		try
		{
			$res = Database::instance()->simpleSelect($tprefix."groups", "gid", "groupname=?", array($groupName));
			if (!$res || $res->num_rows <= 0)
			{
				throw new GroupDoesNotExistException();
			}

			$groupId = $res->fetch_row()[0];
			Database::instance()->simpleDelete($tprefix."groups", "gid=?", array($groupId));
			Database::instance()->simpleDelete($tprefix."groupsdata", "gid=?", array($groupId));
		}
		catch (MysqlException $e)
		{
			throw $e;
		}
	}

	/**
	 * Get information about a group from the database.
	 *
	 * @param string $groupName The name of the group to retrieve it's information from.
	 */
	public static function GetGroup(string $groupName) {
		if (!preg_match(Permissions::GroupMatch, $groupName)) throw new InvalidGroupNameException();

		Database::instance()->selectdb('auth');
		$tprefix = Config::get('db.table_prefix.auth');

		try
		{
			$res = Database::instance()->simpleSelect($tprefix."groups", "gid", "groupname=?", array($groupName));
			if (!$res || $res->num_rows <= 0) throw new GroupDoesNotExistException();
			$dataRes = Database::instance()->simpleSelect($tprefix."groupsdata", "*", "gid=?", array($res->fetch_row()[0]));
			if (!$dataRes || $dataRes->num_rows <= 0) throw new PermissionException();

			$row = $dataRes->fetch_assoc();
			$row['groupname'] = $groupName;
			return $row;
		}
		catch (MysqlException $e)
		{
			throw $e;
		}

		throw new PermissionException();
	}
};
