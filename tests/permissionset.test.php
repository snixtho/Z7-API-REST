<?php
/**
 * @copyright 2012 - 2017 Zurvan Labs 
 * @author snixtho <snixtho@gmail.com>
 */
declare(strict_types=1);

include "tests/tests.php";
include "inc/functions.php";
include "inc/lib/auth/permissionset.class.php";

use Z7API\Lib\Auth\PermissionSet;

$perms = new PermissionSet();

$perms->set('auth.login');
$perms->set('auth.admin.help');
$perms->set('auth.normal.help');
$perms->set('auth.');
$perms->set('auth.admin');
$perms->set('lol.test');

z7Test($perms->has('auth.login'));
z7Test($perms->has('auth.admin.help'));
z7Test($perms->has('auth.normal.help'));
z7Test($perms->has('auth.'));
z7Test($perms->has('auth.admin'));
z7Test(!$perms->has('sdgds'));
z7Test($perms->has('auth.*'));
z7Test(!$perms->has('!auth.*'));
z7Test($perms->has('auth*admin*help'));
z7Test($perms->has('auth.*.*he*'));
z7Test(!$perms->has('!auth.*.*he*'));
z7Test($perms->has('!auth.banned.help'));
z7Test(!$perms->hasAll(array('!auth.*.*he*')));
z7Test($perms->hasAll(array('auth.login', 'auth.admin.help', 'auth.normal.help', 'auth.', 'auth.admin', 'lol.test')));
z7Test(!$perms->hasAll(array('auth.login', 'auth.admin.help', 'auth.normal.help', 'auth.', 'auth.admin', '!lol.test')));
z7Test($perms->hasAll(array('auth.login', 'auth.admin', '!auth.banned.help')));
z7Test(!$perms->hasAll(array('a.list', 'of.permissions.that', 'doesnt.exist')));
z7Test($perms->hasAll(array('!a.list', '!of.permissions.that', '!doesnt.exist')));
