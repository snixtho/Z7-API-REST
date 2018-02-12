<?php
/**
 * @copyright 2012 - 2017 Zurvan Labs 
 * @author snixtho <snixtho@gmail.com>
 */

declare(strict_types=1);

include "tests/tests.php";
include "inc/io.class.php";

use \Z7API\Core\IO;

z7Test(IO::ValueCanBe('integer', 1));
z7Test(IO::ValueCanBe('integer', 1.0));
z7Test(IO::ValueCanBe('integer', 0));
z7Test(IO::ValueCanBe('integer', 0.0));
z7Test(!IO::ValueCanBe('integer', false));
z7Test(IO::ValueCanBe('integer', true));
z7Test(!IO::ValueCanBe('integer', 43.32));
z7Test(IO::ValueCanBe('integer', -0));
z7Test(IO::ValueCanBe('integer', 34534));
z7Test(IO::ValueCanBe('integer', -345));
z7Test(IO::ValueCanBe('integer', -1));
z7Test(IO::ValueCanBe('integer', "345"));
z7Test(IO::ValueCanBe('integer', "-32"));
z7Test(IO::ValueCanBe('integer', "1.0"));
z7Test(IO::ValueCanBe('integer', "0.0"));
z7Test(!IO::ValueCanBe('integer', "sdf"));
z7Test(IO::ValueCanBe('integer', "1"));
z7Test(!IO::ValueCanBe('integer', "\0"));
z7Test(IO::ValueCanBe('integer', "0"));

z7Test(IO::ValueCanBe('string', "dfsgdfg"));
z7Test(IO::ValueCanBe('string', 0));
z7Test(IO::ValueCanBe('string', 1));
z7Test(IO::ValueCanBe('string', true));
z7Test(IO::ValueCanBe('string', false));
z7Test(IO::ValueCanBe('string', 3245.345));
z7Test(IO::ValueCanBe('string', -32));

z7Test(IO::ValueCanBe('boolean', "true"));
z7Test(IO::ValueCanBe('boolean', "false"));
z7Test(IO::ValueCanBe('boolean', true));
z7Test(IO::ValueCanBe('boolean', false));
z7Test(IO::ValueCanBe('boolean', "1"));
z7Test(IO::ValueCanBe('boolean', "0"));
z7Test(IO::ValueCanBe('boolean', 1));
z7Test(IO::ValueCanBe('boolean', 0));
z7Test(IO::ValueCanBe('boolean', 1.0));
z7Test(IO::ValueCanBe('boolean', 0.0));
z7Test(!IO::ValueCanBe('boolean', 34));
z7Test(!IO::ValueCanBe('boolean', -1));
z7Test(!IO::ValueCanBe('boolean', "-1"));
z7Test(IO::ValueCanBe('boolean', "1.0"));
z7Test(IO::ValueCanBe('boolean', "0.0"));

z7Test(IO::ValueCanBe('double', 1.0));
z7Test(IO::ValueCanBe('double', 0.0));
z7Test(IO::ValueCanBe('double', 1));
z7Test(IO::ValueCanBe('double', 0));
z7Test(IO::ValueCanBe('double', 34));
z7Test(IO::ValueCanBe('double', -32));
z7Test(IO::ValueCanBe('double', -1.0));
z7Test(IO::ValueCanBe('double', "1.0"));
z7Test(IO::ValueCanBe('double', "0.0"));
z7Test(IO::ValueCanBe('double', "1"));
z7Test(IO::ValueCanBe('double', "0"));
z7Test(IO::ValueCanBe('double', "34"));
z7Test(IO::ValueCanBe('double', "-32"));
z7Test(IO::ValueCanBe('double', "-1.0"));
z7Test(!IO::ValueCanBe('double', "sdfg"));
z7Test(!IO::ValueCanBe('double', false));
z7Test(IO::ValueCanBe('double', true));

z7Test(IO::ValueCanBe('float', 1.0));
z7Test(IO::ValueCanBe('float', 0.0));
z7Test(IO::ValueCanBe('float', 1));
z7Test(IO::ValueCanBe('float', 0));
z7Test(IO::ValueCanBe('float', 34));
z7Test(IO::ValueCanBe('float', -32));
z7Test(IO::ValueCanBe('float', -1.0));
z7Test(IO::ValueCanBe('float', "1.0"));
z7Test(IO::ValueCanBe('float', "0.0"));
z7Test(IO::ValueCanBe('float', "1"));
z7Test(IO::ValueCanBe('float', "0"));
z7Test(IO::ValueCanBe('float', "34"));
z7Test(IO::ValueCanBe('float', "-32"));
z7Test(IO::ValueCanBe('float', "-1.0"));
z7Test(!IO::ValueCanBe('float', "sdfg"));
z7Test(!IO::ValueCanBe('float', false));
z7Test(IO::ValueCanBe('float', true));
