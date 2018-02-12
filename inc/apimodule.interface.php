<?php
/**
 * @copyright 2012 - 2017 Zurvan Labs 
 * @author snixtho <snixtho@gmail.com>
 */
declare(strict_types=1);
namespace Z7API\Core;

/**
 * An interface that all module types must implement.
 */
interface IAPIModule {
	/**
	 * Generator that yields names of required modules.
	 */
	public function required();

	/**
	 * Generator which yields event handlers to be registered.
	 */
	public function eventHandlers();

	/**
	 * Initialization function.
	 */
	public function init(APIMessage $msg) : APIMessage;
};

/**
 * This module type is for handling requests.
 */
abstract class APIRequestModule implements IAPIModule {
	/**
	 * Default implementations.
	 */
	public function init(APIMessage $msg) : APIMessage {
		return new APIMessage();
	}

	/**
	 * Default implementation of GET event.
	 * @param  APIMessage $msg In-comming message.
	 * @return APIMessage      Out-going message.
	 */
	function onGET(APIMessage $msg) : APIMessage {
		throw new ModuleRequestShutdownException(APIErrors::EventGETNotImplemented);
	}

	/**
	 * Default implementation of POST event.
	 * @param  APIMessage $msg In-comming message.
	 * @return APIMessage      Out-going message.
	 */
	function onPOST(APIMessage $msg) : APIMessage {
		throw new ModuleRequestShutdownException(APIErrors::EventPOSTNotImplemented);
	}

	/**
	 * Default implementation of PUT event.
	 * @param  APIMessage $msg In-comming message.
	 * @return APIMessage      Out-going message.
	 */
	function onPUT(APIMessage $msg) : APIMessage {
		throw new ModuleRequestShutdownException(APIErrors::EventPUTNotImplemented);
	}

	/**
	 * Default implementation of DELETe event.
	 * @param  APIMessage $msg In-comming message.
	 * @return APIMessage      Out-going message.
	 */
	function onDELETE(APIMessage $msg) : APIMessage {
		throw new ModuleRequestShutdownException(APIErrors::EventDELETENotImplemented);
	}

	/**
	 * Default implementation of PATCH event.
	 * @param  APIMessage $msg In-comming message.
	 * @return APIMessage      Out-going message.
	 */
	function onPATCH(APIMessage $msg) : APIMessage {
		throw new ModuleRequestShutdownException(APIErrors::EventPATCHNotImplemented);
	}

	/**
	 * Default implementation of OPTIONS event.
	 * @param  APIMessage $msg In-comming message.
	 * @return APIMessage      Out-going message.
	 */
	function onOPTIONS(APIMessage $msg) : APIMessage {
		throw new ModuleRequestShutdownException(APIErrors::EventOPTIONSNotImplemented);
	}
};
