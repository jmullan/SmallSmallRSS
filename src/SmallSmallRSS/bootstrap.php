<?php
/**
 * Set up an application to use SmallSmallRSS
 */
require_once __DIR__ . '/../../vendor/autoload.php';
error_reporting(-1);
\SmallSmallRSS\Sanity::initialCheck();
\SmallSmallRSS\ErrorHandler::register();
\SmallSmallRSS\Utils::cleanUpStripslashes();

mb_internal_encoding('UTF-8');
date_default_timezone_set('UTC');

\SmallSmallRSS\Sessions::init();
ini_set('user_agent', \SmallSmallRSS\Fetcher::getUserAgent());
require __DIR__ . '/junk.php';
