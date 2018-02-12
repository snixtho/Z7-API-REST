<?php
// The database username
$config['db']['user']['auth'] = 'root';

// The database password
$config['db']['pass']['auth'] = 'sniper';

// The database name
$config['db']['name']['auth'] = 'api-auth';

// The table prefix
$config['db']['table_prefix']['auth'] = 'z7_';

// Whether to freeze a user's login capability after a certain amount of tries.
$config['auth']['enableLoginFailureBlock'] = true;

// Maximum email & password login failures before the user is freezed. Default is 3.
$config['auth']['maxLoginFailure'] = 3;

// The time, in seconds that specifies for how long a user is freezed. Default is 900 = 15 min.
$config['auth']['maxFreezeTime'] = 900;

// Maximum time in seconds a session can be active after a user is inactive. Default: 600 = 10 min
$config['auth']['sessions']['maxtime'] = 180;
