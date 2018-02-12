<?php
/**
 * @copyright 2012 - 2017 Zurvan Labs 
 * @author snixtho <snixtho@gmail.com>
 */
declare(strict_types=1);

function z7Test($result) {
	if (!$result)
	{
		debug_print_backtrace();
		trigger_error("Test failed", E_USER_ERROR);
	}
}

function z7TestCheckArray($arr, $match) {
	if (count($arr) != count($match))
	{
		z7Test(false);
	}

	for ($i = 0; $i < count($arr); $i++)
	{
		if ($arr[$i] != $match[$i])
		{
			z7Test(false);
		}
	}
}