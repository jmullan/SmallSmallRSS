<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/SmallSmallRSS/bootstrap.php';
require_once __DIR__ . '/include/errordefs.php';
if (isset($_REQUEST['mode']) && $_REQUEST['mode'] == 'js') {
    header("Content-Type: text/javascript; charset=UTF-8");
    print "var ERRORS = " . json_encode($ERRORS) . ";\n";
}
