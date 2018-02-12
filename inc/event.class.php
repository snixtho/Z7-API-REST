<?php
/**
 * @copyright 2012 - 2017 Zurvan Labs 
 * @author snixtho <snixtho@gmail.com>
 */

declare(strict_types=1);

namespace Z7API\Core;

/**
 * Event handler class.
 */
class Event {
	/**
	 * The name of the event that is fired.
	 * 
	 * @var string
	 */
	private $m_name;

	/**
	 * An unique id which identifies the handler.
	 * This should be unique to each handler per event
	 * and will be used as an identifier in the return results.
	 * 
	 * @var string
	 */
	private $m_id;

	/**
	 * The callback function for this event handler.
	 * 
	 * @var string
	 */
	private $m_callback;

	/**
	 * Contains the object which the callback is a member of.
	 * @var mixed
	 */
	private $m_callbackObj = NULL;
	
	/**
	 * Construct a new event handler.
	 * 
	 * @param string $name        Name of the event to handle.
	 * @param string $handlerId   The ID of the handler.
	 * @param function $callback  The callback function which handles event dispatching.
	 * @param mixed $callbackObj  The object which the callback is a member of, if any.
	 */
	public function __construct(string $name, string $handlerId, string $callback, $callbackObj=NULL) {
		$this->m_name = $name;
		$this->m_id = $handlerId;

		$this->m_callback = $callback;
		$this->m_callbackObj = $callbackObj;
	}

	/**
	 * Get the type of event.
	 * 
	 * @return string The event type's name.
	 */
	public function getEventName() : string {
		return $this->m_name;
	}

	/**
	 * Get the ID of the handler.
	 * 
	 * @return string The Id of the handler.
	 */
	public function getId() : string {
		return $this->m_id;
	}

	/**
	 * Dispatch a call to the callback of this event handler.
	 * 
	 * @param  mixed $args  Arguments for the callback.
	 * @return mixed Possible return of the event callback.
	 */
	public function dispatch(...$args) {
		if ($this->m_callbackObj !== NULL)
		{
			// call_user_func_array(array($this->m_callbackObj, $m_callbackObj), $args);
			return $this->m_callbackObj->{$this->m_callback}(...$args);
		}
		else
		{
			return $this->m_callbackObj(...$args);
		}
	}
};
