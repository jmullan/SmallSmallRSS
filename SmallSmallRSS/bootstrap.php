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
require 'functions.php';
