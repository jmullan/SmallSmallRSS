<?php
require __DIR__ . '/../include/functions.php';

function tiny_tiny_autoload($class) {
    $class_file = str_replace("_", "/", strtolower(basename($class)));
    $file = __DIR__ . "/../classes/$class_file.php";
    if (file_exists($file)) {
        require $file;
    }
}

spl_autoload_register('tiny_tiny_autoload');

require __DIR__ . '/../jwage/SplClassLoader.php';
$myLibLoader = new SplClassLoader('SmallSmallRSS', dirname(__DIR__));
$myLibLoader->register();

