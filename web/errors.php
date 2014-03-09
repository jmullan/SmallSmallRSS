<?php
require_once __DIR__ . '/../src/SmallSmallRSS/bootstrap.php';
if (isset($_REQUEST['mode']) && $_REQUEST['mode'] == 'js') {
    header('Content-Type: text/javascript; charset=UTF-8');
    print 'var ERRORS = ' . json_encode(\SmallSmallRSS\Errors::dump(), JSON_FORCE_OBJECT) . ";\n";
}
