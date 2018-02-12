<?php
/**
 * @copyright 2012 - 2017 Zurvan Labs 
 * @author snixtho <snixtho@gmail.com>
 */
/**
 * This module provides API-key functionality. If this module is required,
 * an API-key must be provided with the in-comming message. This module will
 * also track the usage of this api-key and will disallow further execution
 * if a set number of usages exceedes the threshold limit set for each user.
 */

declare(strict_types=1);

use Z7API\Core\{
	APIRequestModule,
	APIMessage
};

class Module_Example extends APIRequestModule {
	public function required() {}
	public function eventHandlers() {}

	public function init(APIMessage $msg) : APIMessage { return $msg; }

	public function onGET(APIMessage $msg) : APIMessage {
		$returnMessage = new APIMessage();

		$returnMessage->set('Hello', 'World!');

		return $returnMessage;
	}
};