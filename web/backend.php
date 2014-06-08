<?php
require_once __DIR__ . '/../src/SmallSmallRSS/bootstrap.php';

$op = (isset($_REQUEST['op']) ? $_REQUEST['op'] : '');
$method = (!empty($_REQUEST['subop']) ? $_REQUEST['subop'] : $_REQUEST['method']);
if (!$method) {
    $method = 'index';
} else {
    $method = strtolower($method);
}

/* Public calls compatibility shim */

$public_calls = array(
    'globalUpdateFeeds',
    'rss',
    'getUnread',
    'getProfiles',
    'share',
    'fbexport',
    'logout',
    'pubsub'
);

if (array_search($op, $public_calls) !== false) {
    header('Location: public.php?' . $_SERVER['QUERY_STRING']);
    return;
}

$csrf_token = (isset($_REQUEST['csrf_token']) ? $_REQUEST['csrf_token'] : '');
\SmallSmallRSS\Sessions::init();
\SmallSmallRSS\Locale::startupGettext($_SESSION['uid']);
$script_started = microtime(true);
if (!\SmallSmallRSS\PluginHost::initAll()) {
    return;
}
header('Content-Type: application/json; charset=utf-8');

if (\SmallSmallRSS\Auth::isSingleUserMode()) {
    \SmallSmallRSS\Auth::authenticate('admin', null);
}

if ($_SESSION['uid']) {
    if (!\SmallSmallRSS\Sessions::validate()) {
        $renderer = new \SmallSmallRSS\Renderers\JSONError(\SmallSmallRSS\Errors::REQUEST_UNAUTHORIZED());
        $renderer->render();
        return;
    }
    \SmallSmallRSS\PluginHost::loadUserPlugins($_SESSION['uid']);
}
$op = str_replace('-', '_', $op);
$legacy_ops = array(
    'api' => 'API',
    'rpc' => 'RPC',
    'dlg' => 'Dlg',
    'feeds' => 'Feeds',
    'pref_feeds' => 'PrefFeeds',

);
if (isset($legacy_ops[$op])) {
    $op = $legacy_ops[$op];
}
$handler = '\\SmallSmallRSS\\Handlers\\' . $op;
$override = \SmallSmallRSS\PluginHost::getInstance()->lookupHandler($op, $method);
$error_code = \SmallSmallRSS\Errors::NOTHING_TO_DO();
if (class_exists($handler) || $override) {
    if ($override) {
        $handler = $override;
    } else {
        $handler = new $handler($_REQUEST);
    }
    if ($handler) {
        if (!$handler instanceof \SmallSmallRSS\Handlers\IHandler) {
            $error_code = \SmallSmallRSS\Errors::REQUEST_UNAUTHORIZED();
        } elseif ($handler->ignoreCSRF($method) || \SmallSmallRSS\Sessions::validateCSRF($csrf_token)) {
            if ($handler->before($method)) {
                if ($method && method_exists($handler, $method)) {
                    $handler->$method();
                } else {
                    if (method_exists($handler, 'catchall')) {
                        $handler->catchall($method);
                    }
                }
                $handler->after();
                return;
            } else {
                $error_code = \SmallSmallRSS\Errors::REQUEST_UNAUTHORIZED();
            }
        } else {
            $error_code = \SmallSmallRSS\Errors::REQUEST_UNAUTHORIZED();
        }
    }
}
$renderer = new \SmallSmallRSS\Renderers\JSONError($error_code);
$renderer->render();
