<?php
function stripslashes_deep($value) {
    $value = (
        is_array($value)
        ? array_map('stripslashes_deep', $value)
        : stripslashes($value));
    return $value;
}

function clean_up_stripslashes() {
    static $finished = false;
    if (!$finished) {
        if (get_magic_quotes_gpc()) {
            $_POST = array_map('stripslashes_deep', $_POST);
            $_GET = array_map('stripslashes_deep', $_GET);
            $_COOKIE = array_map('stripslashes_deep', $_COOKIE);
            $_REQUEST = array_map('stripslashes_deep', $_REQUEST);
        }
        $finished = true;
    }
}

function tiny_tiny_autoload($class) {
    $class_file = str_replace("_", "/", strtolower(basename($class)));
    $file = __DIR__ . "/../classes/$class_file.php";
    if (file_exists($file)) {
        require $file;
    }
}
error_reporting(-1);

clean_up_stripslashes();

spl_autoload_register('tiny_tiny_autoload');

require __DIR__ . '/../jwage/SplClassLoader.php';
$myLibLoader = new SplClassLoader('SmallSmallRSS', dirname(__DIR__));
$myLibLoader->register();

set_include_path(dirname(__DIR__) . '/include/' . PATH_SEPARATOR . get_include_path());
require 'functions.php';

if (false && defined('ENABLE_GZIP_OUTPUT') && ENABLE_GZIP_OUTPUT && function_exists("ob_gzhandler")) {

    ob_start("ob_gzhandler");
} else {
    ob_start();
}
