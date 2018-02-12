<?php
/**
 * @copyright 2012 - 2017 Zurvan Labs
 * @author snixtho <snixtho@gmail.com>
 */
declare(strict_types=1);

include "tests/tests.php";
include "inc/functions.php";

use \Z7API\Core\F;

z7TestCheckArray(F::genArray('test', 5), array('test', 'test', 'test', 'test', 'test'));
z7TestCheckArray(F::genArray('', 5), array('', '', '', '', ''));

z7Test(F::mybbHashPassword('apass', 'somesalt') == md5(md5('somesalt').md5('apass')));
