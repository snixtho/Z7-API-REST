<?php
/**
 * @copyright 2012 - 2017 Zurvan Labs 
 * @author snixtho <snixtho@gmail.com>
 */
declare(strict_types=1);

namespace Z7API\Core;

/**
 * The main class which manages the core of the API system.
 * It handles incoming requests and processes them using different parts of the API.
 */
class Z7API {
	/**
	 * Holds the io handler.
	 * @var IO
	 */
	public $io = NULL;

	/**
	 * Holds the modules manager.
	 * @var ModulesManager
	 */
	public $mm = NULL;

	function __construct() {
		$m_glmsg = array();

		$this->io = new IO();
		$this->mm = new ModulesManager($this->io);
	}

	/**
	 * Get the instance of a module.
	 * @param  string $name The name of the module.
	 * @return IAPIModule       Instance of the requested module. NULL when the module doesnt exist.
	 */
	public function getMod(string $name) : IAPIModule {
		return $this->mm->getModule($name);
	}

	/**
	 * Start the main script, modules and events.
	 */
	public function run() {
		$output = new APIMessage();
		$this->io->setContentType('application/json');

		try
		{
			$success = $this->mm->loadModule(MODULE);
			if ($success)
			{
				$output->addFromMessage($this->mm->getInitMessage());
			}
			else
			{
				$msg = new APIMessage();
				$msg->addError(APIErrors::InvalidModule);
				$this->io->write($msg->json());
				return;
			}
		}
		catch (ModuleRequestShutdownException $ex)
		{
			$msg = new APIMessage();
			$msg->addError($ex->getCode());
			$this->io->write($msg->json());
			return;
		}
		catch (ModuleOverrideOutputException $ex)
		{ // print custom output and shut down
			$this->io->write($ex);
			return;
		}
		catch (ModuleInvalidInterfaceException $ex)
		{ // force shutdown signal
			$this->io->write(APIExceptionUtilities::DetailedTrace($ex));
			return;
		}
		catch (ModuleInvalidInterfaceException $ex)
		{
			$this->io->write('The requested module has to be an instance of IAPIModule.');
			return;
		}
		catch (\Error $ex)
		{ // module compilation error
			$this->io->write(APIExceptionUtilities::DetailedTrace($ex));
			return;
		}

		// call HTTP method events if the module is a request module
		if ($this->mm->getMainModule() instanceof APIRequestModule)
		{
			try
			{
				switch (HTTP_METHOD)
				{
					case 'GET': // fire onGET event
						$output->addFromMessage($this->mm->getMainModule()->onGET($this->io->getInputMessage()));
						break;
					case 'POST': // fire onPOST event
						$output->addFromMessage($this->mm->getMainModule()->onPOST($this->io->getInputMessage()));
						break;
					case 'PUT': // fire onPUT event
						$output->addFromMessage($this->mm->getMainModule()->onPUT($this->io->getInputMessage()));
						break;
					case 'DELETE': // fire onDELETE event
						$output->addFromMessage($this->mm->getMainModule()->onDELETE($this->io->getInputMessage()));
						break;
					case 'PATCH': // fire onPATCH event
						$output->addFromMessage($this->mm->getMainModule()->onPATCH($this->io->getInputMessage()));
						break;
					case 'OPTIONS': // fire onOPTIONS event
						$output->addFromMessage($this->mm->getMainModule()->onOPTIONS($this->io->getInputMessage()));
						break;
					default:
						{
							$msg = new APIMessage();
							$msg->addError(APIErrors::InvalidRequestMethod);
							$this->io->write($msg->json());
							return;
						}
				}
			}
			catch (ModuleRequestShutdownException $ex)
			{ // force shutdown
				$msg = new APIMessage();
				$msg->addError($ex->getCode());
				$this->io->write($msg->json());
				return;
			}
			catch (ModuleOverrideOutputException $ex)
			{ // print custom output and shut down
				$this->io->write($ex->getData());
				return;
			}
		}

		// call the action event
		try
		{
			if (isset($_GET['action']))
			{
				$subaction1 = isset($_GET['subaction1']) ? $_GET['subaction1'] : '';
				$subaction2 = isset($_GET['subaction2']) ? $_GET['subaction2'] : '';
				
				$results = $this->mm->dispatchEvent('action.' . $_GET['action'], $this->io->getInputMessage(), $subaction1, $subaction2);

				if (count($results) > 0)
				{
					foreach ($results as $result)
					{
						$output->addFromMessage($result);
					}
				}
				else
				{
					$output->addError(APIErrors::InvalidAction);
				}
			}
			
		}
		catch (ModuleRequestShutdownException $ex)
		{ // force shutdown
			$msg = new APIMessage();
			$msg->addError($ex->getCode());
			$this->io->write($msg->json());
			return;
		}
		catch (ModuleOverrideOutputException $ex)
		{ // print custom output and shut down
			$this->io->write($ex->getData());
			return;
		}

		WRITE_FLUSH:

		$this->io->write($output->json());
		$this->io->flush();
	}
};