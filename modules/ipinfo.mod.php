<?php
/**
 * @copyright 2012 - 2017 Zurvan Labs 
 * @author snixtho <snixtho@gmail.com>
 */
/**
 * This module is used for retrieving IP information
 * about the connecting client.
 */
declare(strict_types=1);

use Z7API\Core\{
	APIRequestModule,
	APIMessage,
	IOValidationException,
	IO,
	F
};

use Z7API\Lib\Auth\{
	AuthErrors,
	AuthSystem
};

class Module_IPInfo extends APIRequestModule {
	public function required() {
		yield 'auth';
	}

	public function eventHandlers() {}

	public function init(APIMessage $msg) : APIMessage {
		return parent::init($msg);
	}

	public function onPOST(APIMessage $msg) : APIMessage {
		$output = new APIMessage();

		if (!AuthSystem::CheckSession($msg))
		{ // session invalid
			$output->addError(AuthErrors::PermissionDenied);
			return $output;
		}

		// permission granted, show ip
		$clientIp = $_SERVER['HTTP_X_FORWARDED_FOR'] ??
					$_SERVER['HTTP_X_FORWARDED_FOR'] ??
					$_SERVER['HTTP_FORWARDED'] ??
					$_SERVER['HTTP_CF_CONNECTING_IP'] ??
					$_SERVER['REMOTE_ADDR'] ??
					$_SERVER['HTTP_CLIENT_IP'];
		$domain = gethostbyaddr($clientIp);
		$device = F::getDeviceInfoId();

		$output->set('clientip', $clientIp);
		$output->set('domain', $domain);
		$output->set('device', $device);
		
		return $output;
	}
};
