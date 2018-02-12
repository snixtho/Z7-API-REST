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
	ModuleOverrideOutputException,
	IO
};

use Z7API\Lib\Auth\{
	AuthErrors,
	AuthSystem,
	User,
	UserFactory,
	PermissionSet,
	Permissions
};

class Module_PermTest extends APIRequestModule {
	public function required() {
		yield 'auth';
	}

	public function eventHandlers() {}

	public function init(APIMessage $msg) : APIMessage {
		return parent::init($msg);
	}

	public function onPOST(APIMessage $msg) : APIMessage {
		$permset = new PermissionSet();

		
		$repository = $msg->get('repository');
		$repoName = $repository->name;
		$pullrequest = $msg->get('pullrequest');
		$prAuthor = $pullrequest->author->display_name;
		$prDiffLink = 'https://bitbucket.org/' . str_replace('\\', '', $repository->full_name) . '/pull-requests/' . (string)$pullrequest->id . '/master/diff';

		$result = $prAuthor . ' opened a pull request on ' . $repoName . ': ' . $prDiffLink;

		$f = fopen('test.txt', 'a');

		fwrite($f, $result);
		fclose($f);

		throw new ModuleOverrideOutputException("");
	}
};
