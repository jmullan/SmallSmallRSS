<?php
/**
 * Set up an application to use SmallSmallRSS
 */
require_once __DIR__ . '/../../vendor/autoload.php';
error_reporting(-1);
\SmallSmallRSS\Sanity::initialCheck();
\SmallSmallRSS\Utils::cleanUpStripslashes();

mb_internal_encoding('UTF-8');
date_default_timezone_set('UTC');
if (defined('E_DEPRECATED')) {
    error_reporting(-1);
}

\SmallSmallRSS\Sessions::init();


\SmallSmallRSS\Config::set(
    'SELF_USER_AGENT',
    'Tiny Tiny RSS/' . \SmallSmallRSS\Constants::VERSION . ' (http://tt-rss.org/)'
);
ini_set('user_agent', \SmallSmallRSS\Config::get('SELF_USER_AGENT'));


require __DIR__ . '/junk.php';
