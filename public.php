<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/SmallSmallRSS/bootstrap.php';
\SmallSmallRSS\Sanity::initialCheck();
\SmallSmallRSS\Session::init();

startup_gettext();

$script_started = microtime(true);

if (!init_plugins()) return;

if (ENABLE_GZIP_OUTPUT && function_exists("ob_gzhandler")) {
    ob_start("ob_gzhandler");
}

$method = $_REQUEST["op"];

$override = \SmallSmallRSS\PluginHost::getInstance()->lookup_handler("public", $method);

if ($override) {
    $handler = $override;
} else {
    $handler = new \SmallSmallRSS\Handlers\PublicHandler($_REQUEST);
}

if ($handler instanceof \SmallSmallRSS\Handlers\IHandler && $handler->before($method)) {
    if ($method && method_exists($handler, $method)) {
        $handler->$method();
    } elseif (method_exists($handler, 'index')) {
        $handler->index();
    }
    $handler->after();
    return;
}
$renderer = new \SmallSmallRSS\Renderers\JSONError(7);
$renderer->render();
