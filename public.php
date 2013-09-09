<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/SmallSmallRSS/bootstrap.php';
\SmallSmallRSS\Sanity::initial_check();
require_once "db.php";
require_once "db-prefs.php";

\SmallSmallRSS\Session::init();

startup_gettext();

$script_started = microtime(true);

if (!init_plugins()) return;

if (ENABLE_GZIP_OUTPUT && function_exists("ob_gzhandler")) {
    ob_start("ob_gzhandler");
}

$method = $_REQUEST["op"];

$override = PluginHost::getInstance()->lookup_handler("public", $method);

if ($override) {
    $handler = $override;
} else {
    $handler = new Handler_Public($_REQUEST);
}

if (implements_interface($handler, "IHandler") && $handler->before($method)) {
    if ($method && method_exists($handler, $method)) {
        $handler->$method();
    } elseif (method_exists($handler, 'index')) {
        $handler->index();
    }
    $handler->after();
    return;
}

header("Content-Type: text/plain");
print json_encode(array("error" => array("code" => 7)));
