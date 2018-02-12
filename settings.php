<?php


// The database host
$config['db']['host'] = 'localhost';

// The database port
$config['db']['port'] = 3306;

/**
 * API Database Config
 */

// The database username
$config['db']['user']['api'] = 'root';

// The database password
$config['db']['pass']['api'] = 'sniper';

// The database name
$config['db']['name']['api'] = 'api-dev';

// The table prefix
$config['db']['table_prefix']['api'] = 'z7_';

/**
 * MyBB Root path
 */
// $config['misc']['mybb_path'] = 'D:/xampp/htdocs/mybb';

/**
 * API-Key & Secret length.
 */
// Number of characters in the API-key.
$config['misc']['apikeylength'] = 32;

// Number of characters in the secret.
$config['misc']['secretlength'] = 64;
