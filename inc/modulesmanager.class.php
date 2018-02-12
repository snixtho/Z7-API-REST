<?php
/**
 * @copyright 2012 - 2017 Zurvan Labs 
 * @author snixtho <snixtho@gmail.com>
 */
declare(strict_types=1);

namespace Z7API\Core;

/**
 * Class that handles API modules and events.
 */
class ModulesManager {
	/**
	 * Holds loaded module objects.
	 * @var object
	 */
	private $loadedModules = NULL;

	/**
	 * Holds the main loaded module.
	 * @var string
	 */
	private $mainModule = '';

	/**
	 * Holds the io manager.
	 * @var IO
	 */
	private $io = NULL;

	/**
	 * Holds the combined initialization messages.
	 * @var APIMessage
	 */
	private $initMsg = NULL;

	/**
	 * Contains a datastructure which holds a list of registered events.
	 * 
	 * @var array
	 */
	private $eventslist = array();

	function __construct(IO &$io) {
		$this->loadedModules = new \stdClass();
		$this->io = $io;
		$this->initMsg = new APIMessage();
	}

	/**
	 * Load a module by it's name.
	 * @param  string $name Name of the module.
	 * @return bool         Returns true if module was successfully loaded, false if it doesn't exist.
	 */
	public function loadModule(string $name) : bool {
		$name = preg_replace('/[^A-Za-z0-9-]/', '', basename($name));
		$validPath = realpath(ABSPATH . 'modules/');
		$inPath = realpath($validPath . '/' . strtolower($name) . '.mod.php');

		if ($inPath !== false && $validPath === dirname($inPath) && file_exists($inPath))
		{
			require_once $inPath;

			// sanitize $name for eval.
			if ($name[strlen($name) - 1] == '-')
				$name = substr($name, 0, strlen($name) - 2);

			$name = F::advanced_replace('/-[a-zA-Z0-9]/', $name, function($match) {
				return strtoupper($match[1]);
			});

			$name = str_replace('-', '', $name);

			try
			{
				eval('$modObject = new Module_'.$name.'();');
			}
			catch (\Error $ex)
			{
				throw $ex;
			}

			if ($modObject instanceof IAPIModule)
			{
				// register possible events
				$eventHandlers = $modObject->eventHandlers();
				if ($eventHandlers)
				{
					foreach ($eventHandlers as $event)
					{
						$this->registerEvent($event);
					}
				}

				// load the module's required modules
				$required = $modObject->required();
				if ($required)
				{
					foreach ($required as $req)
					{
						if (strtolower($req) != strtolower($name) && !isset($this->loadedModules->{$req}))
						{
							$this->loadModule($req);
						}
					}
				}

				$saveName = strtolower($name);

				$this->loadedModules->{$saveName} = $modObject;
				$msg = $this->loadedModules->{$saveName}->init($this->io->getInputMessage());
				$this->initMsg->addFromMessage($msg);
				$this->setMainModule($saveName);

				return true;
			}
			else
			{
				throw new ModuleInvalidInterfaceException();
			}
		}
		else
		{
			return false;
		}
	}

	/**
	 * Sets the specified module as the main module.
	 * 
	 * @param string $name Name of the module to set as main.
	 */
	public function setMainModule(string $name) {
		if (isset($this->loadedModules->{$name}))
		{
			$this->mainModule = $name;
		}
	}

	/**
	 * Gets the main module.
	 *
	 * @return A reference to the main module
	 */
	public function &getMainModule() {
		return $this->loadedModules->{$this->mainModule};
	}

	/**
	 * Returns the message formed from module initialization.
	 * @return APIMessage Initialization message.
	 */
	public function getInitMessage() : APIMessage {
		return $this->initMsg;
	}

	/**
	 * Retrieve the instance of a module.
	 * @param  string $name The name of the module, case sensitive.
	 * @return IAPIModule       The instance of the module associated with the provided name. Returns NULL if module does not exist.
	 */
	public function getModule(string $name) : IAPIModule {
		if (isset($this->loadedModules->{$name}))
		{
			return $this->loadedModules->{$name};
		}
		else
		{
			throw new ModuleDoesNotExistException(0, "The module '$name' does not exist.");
		}
	}

	/**
	 * Registers a event handler for a event.
	 * @param  Event  $event The event object that contains information about the handler.
	 */
	public function registerEvent(Event $event) {
		$ename = $event->getEventName();

		if (!array_key_exists($ename, $this->eventslist))
		{
			$this->eventslist[$ename] = array();
		}

		array_push($this->eventslist[$ename], $event);
	}

	/**
	 * Dispatch an event. The function will return an array of results from all the callbacks.
	 * @param  string $eventName The name of the event to dispatch.
	 * @param  string $args Arguments passed to the event callback.
	 * @return array             Results.
	 */
	public function dispatchEvent(string $eventName, ...$args) : array {
		$results = array();

		if (array_key_exists($eventName, $this->eventslist))
		{
			foreach ($this->eventslist[$eventName] as $event)
			{
				$results[$event->getId()] = $event->dispatch(...$args);
			}
		}

		return $results;
	}

	/**
	 * Check if an event exists.
	 * @param  string $eventName The event's name.
	 * @return bool              True if the event exists, false if not.
	 */
	public function eventExists(string $eventName) : bool {
		return array_key_exists($eventName, $this->eventslist);
	}
};
