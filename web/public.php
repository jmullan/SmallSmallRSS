<?php
require_once __DIR__ . '/../src/SmallSmallRSS/bootstrap.php';
\SmallSmallRSS\Sessions::init();
$user_id = null;
if (isset($_SESSION['uid'])) {
    $user_id = $_SESSION['uid'];
}
\SmallSmallRSS\Locale::startupGettext($user_id);

$script_started = microtime(true);

if (!\SmallSmallRSS\PluginHost::init_all()) {
    return;
}
$method = $_REQUEST['op'];
$override = \SmallSmallRSS\PluginHost::getInstance()->lookup_handler('public', $method);
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
$renderer = new \SmallSmallRSS\Renderers\JSONError(\SmallSmallRSS\Errors::NOTHING_TO_DO());
$renderer->render();
