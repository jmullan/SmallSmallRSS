<?php
/**
 * Set up an application to use SmallSmallRSS
 */

require_once __DIR__ . '/Utils.php';
error_reporting(-1);
\SmallSmallRSS\Utils::cleanUpStripslashes();
spl_autoload_register(array('\SmallSmallRSS\Utils', 'smallSmallAutoload'));
require __DIR__ . '/../lib/jwage/SplClassLoader.php';
$myLibLoader = new SplClassLoader('SmallSmallRSS', dirname(__DIR__));
$myLibLoader->register();
$include_paths = array(
    dirname(__DIR__) . '/include/',
    get_include_path(),
);
set_include_path(join(PATH_SEPARATOR, $include_paths));

if (\SmallSmallRSS\Config::get('DB_TYPE') == "pgsql") {
    \SmallSmallRSS\Config::set('SUBSTRING_FOR_DATE', 'SUBSTRING_FOR_DATE');
} else {
    \SmallSmallRSS\Config::set('SUBSTRING_FOR_DATE', 'SUBSTRING');
}

mb_internal_encoding("UTF-8");
date_default_timezone_set('UTC');
if (defined('E_DEPRECATED')) {
    error_reporting(-1);
}

\SmallSmallRSS\Session::init();


\SmallSmallRSS\Config::set(
    'SELF_USER_AGENT',
    'Tiny Tiny RSS/' . \SmallSmallRSS\Constants::VERSION . ' (http://tt-rss.org/)'
);
ini_set('user_agent', SELF_USER_AGENT);


require __DIR__ . '/../include/functions.php';
