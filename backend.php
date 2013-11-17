<?php
require_once __DIR__ . '/SmallSmallRSS/bootstrap.php';

$op = (isset($_REQUEST['op']) ? $_REQUEST["op"] : '');
$method = (!empty($_REQUEST['subop']) ? $_REQUEST['subop'] : $_REQUEST["method"]);
if (!$method) {
    $method = 'index';
} else {
    $method = strtolower($method);
}

/* Public calls compatibility shim */

$public_calls = array(
    "globalUpdateFeeds",
    "rss",
    "getUnread",
    "getProfiles",
    "share",
    "fbexport",
    "logout",
    "pubsub"
);

if (array_search($op, $public_calls) !== false) {
    header("Location: public.php?" . $_SERVER['QUERY_STRING']);
    return;
}

$csrf_token = (isset($_REQUEST['csrf_token']) ? $_REQUEST['csrf_token'] : '');

\SmallSmallRSS\Session::init();

startup_gettext();

$script_started = microtime(true);

if (!\SmallSmallRSS\PluginHost::init_all()) {
    return;
}
header("Content-Type: application/json; charset=utf-8");

if (\SmallSmallRSS\Auth::is_single_user_mode()) {
    authenticate_user("admin", null);
}

if ($_SESSION["uid"]) {
    if (!\SmallSmallRSS\Session::validate()) {
        $renderer = new \SmallSmallRSS\Renderers\JSONError(6);
        $renderer->render();
        return;
    }
    load_user_plugins($_SESSION["uid"]);
}
$op = str_replace("-", "_", $op);
$legacy_ops = array(
    'api' => 'API',
    'rpc' => 'RPC',
    'dlg' => 'Dlg',
    'feeds' => 'Feeds',

);
if (isset($legacy_ops[$op])) {
    $op = $legacy_ops[$op];
}
$handler = '\\SmallSmallRSS\\Handlers\\' . $op;
$override = \SmallSmallRSS\PluginHost::getInstance()->lookup_handler($op, $method);
$error_code = 7;
if (class_exists($handler) || $override) {
    if ($override) {
        $handler = $override;
    } else {
        $handler = new $handler($_REQUEST);
    }
    if ($handler) {
        if (!$handler instanceof \SmallSmallRSS\Handlers\IHandler) {
            $error_code = 6;
        } elseif (validate_csrf($csrf_token) || $handler->csrf_ignore($method)) {
            if ($handler->before($method)) {
                if ($method && method_exists($handler, $method)) {
                    $handler->$method();
                } else {
                    if (method_exists($handler, "catchall")) {
                        $handler->catchall($method);
                    }
                }
                $handler->after();
                return;
            } else {
                $error_code = 6;
            }
        } else {
            $error_code = 6;
        }
    }
}
$renderer = new \SmallSmallRSS\Renderers\JSONError($error_code);
$renderer->render();
