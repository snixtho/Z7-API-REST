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
	F,
	PublicPropertiesConvertible
};

class Session implements PublicPropertiesConvertible {
	const DisallowMultiSession = '0';
	const AllowMultiSession = '1';


	/**
	 * The session key.
	 *
	 * @var string
	 */
	private $_sessionkey;
	/**
	 * Session user id.
	 *
	 * @var int
	 */
	private $_sessionuid;
	/**
	 * The time when the session was created. (unixtimestamp)
	 *
	 * @var int
	 */
	private $_created;
	/**
	 * The maximum time the session is valid for.
	 *
	 * @var int
	 */
	private $_maxtime;

	/**
	 * Load session information from a user.
	 * @param  int    $uid The user's session
	 */
	public function loadSessionInfo(int $uid) {
		Database::instance()->selectdb('auth');
		$tprefix = Config::get('db.table_prefix.auth');
		$result = Database::instance()->simpleSelect($tprefix.'sessions', 'sessionkey,created,maxtime', 'uid=?', array($uid));

		if (!$result || $result->num_rows <= 0)
		{
			$this->_sessionkey = '';
			$this->_sessionuid = 0;
			$this->_created = 0;
			$this->_maxtime = 0;

			return;
		}

		$sessionInfo = $result->fetch_assoc();

		$this->_sessionuid = $uid;
		$this->_sessionkey = $sessionInfo['sessionkey'];
		$this->_created = strtotime($sessionInfo['created']);
		$this->_maxtime = $sessionInfo['maxtime'];
	}

	/**
	 * Create a new session for the user.
	 *
	 * @param  int    $uid The user's id.
	 */
	public function newSession(int $uid, bool $expire=true) {
		Database::instance()->selectdb('auth');
		$tprefix = Config::get('db.table_prefix.auth');

		$sessionKey = F::randomString(64, F::RANDOM_CHARSET_SECRET);
		$maxSessionTime = $expire ? Config::get('auth.sessions.maxtime') : 0;

		$hasSessionRes = Database::instance()->simpleSelect($tprefix.'sessions', 'uid', 'uid=?', array($uid));

		if (!$hasSessionRes || $hasSessionRes->num_rows == 0)
		{ // create new session field
			Database::instance()->simpleInsert($tprefix.'sessions', array(
				'uid' => $uid,
				'sessionkey' => $sessionKey,
				'maxtime' => $maxSessionTime
			));
		}
		else
		{ // update current session field
			Database::instance()->simpleUpdate($tprefix.'sessions', array(
				'sessionkey' => $sessionKey,
				'maxtime' => $maxSessionTime
			), 'uid=?', array($uid));
		}

		$this->_sessionkey = $sessionKey;
		$this->_sessionuid = $uid;
		$this->_created = time();
		$this->_maxtime = $maxSessionTime;
	}

	/**
	 * Check whether the session is valid and not expired.
	 *
	 * @return boolean True if valid, false if not.
	 */
	public function isValid() {
		return time() <= $this->_maxtime + $this->_created;
	}

	/**
	 * Check whether the user's session matches the session values from an input message.
	 * Also takes anti-hijack and validity into consideration.
	 *
	 * @param  APIMessage $msg The message that contains the session uid and session key.
	 * @return boolean         True if matches, false if not.
	 */
	public function matches(int $sessionuid, string $sessionkey) {
		if (!$this->isValid()) return false;

		$isMatch = $this->_sessionuid === $sessionuid || $this->_sessionkey === $sessionkey;
		$currentDevice = F::getDeviceInfoId();

		// check if current device is allowed.
		Database::instance()->selectdb('auth');
		$tprefix = Config::get('db.table_prefix.auth');
		$result = Database::instance()->simpleSelect($tprefix.'antihijack', 'devicelist', 'uid=?', array($this->_sessionuid));

		if (!$result || $result->num_rows <= 0)
		{
			return false;
		}

		$deviceList = unserialize($result->fetch_row()[0]);

		$allowedDevice = false;
		foreach ($deviceList as $savedDevice)
		{
			if ($savedDevice == $currentDevice)
			{
				$allowedDevice = true;
				break;
			}
		}

		return $allowedDevice && $isMatch;
	}

	/**
	 * Get the session key.
	 * @return string The user's session key.
	 */
	public function getSessionKey() : string {
		return $this->_sessionkey;
	}

	/**
	 * Get the session user id.
	 * @return int The user's id associated with this session.
	 */
	public function getSessionUid() : int {
		return $this->_sessionuid;
	}

	/**
	 * Resets creation time of the session making it valid for a new full period.
	 */
	public function updateActivity() {
		Database::instance()->selectdb('auth');
		$tprefix = Config::get('db.table_prefix.auth');
		$prep = Database::instance()->prepare('UPDATE '.$tprefix.'sessions SET created=NOW() WHERE uid=?');
		if ($prep)
		{
			$prep->bind_param('i', $this->_sessionuid);
			$prep->execute();
			$prep->close();
		}
	}

	/**
	 * Invalidates a session, aka deleting it's record from the database.
	 */
	public function invalidate() {
		Database::instance()->selectdb('auth');
		$tprefix = Config::get('db.table_prefix.auth');
		Database::instance()->simpleDelete($tprefix.'sessions', 'uid=?', array($this->_sessionuid));
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

/**
 * Easy creation of sessions.
 */
class SessionFactory {
	/**
	 * Get the current session from a user.
	 *
	 * @param int $uid The user's id.
	 * @return Session A new session object.
	 */
	public static function GetUserSession(int $uid) : Session {
		$session = new Session();
		$session->loadSessionInfo($uid);
		return $session;
	}
};
