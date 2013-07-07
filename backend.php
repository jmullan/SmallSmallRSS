<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/SmallSmallRSS/bootstrap.php';

$op = (isset($_REQUEST['op']) ? $_REQUEST["op"] : '');
@$method = (!empty($_REQUEST['subop']) ? $_REQUEST['subop'] : $_REQUEST["method"]);

if (!$method) {
    $method = 'index';
} else {
    $method = strtolower($method);
}

/* Public calls compatibility shim */

$public_calls = array(
    "globalUpdateFeeds", "rss", "getUnread", "getProfiles", "share",
    "fbexport", "logout", "pubsub"
);

if (array_search($op, $public_calls) !== false) {
    header("Location: public.php?" . $_SERVER['QUERY_STRING']);
    return;
}

$csrf_token = (isset($_REQUEST['csrf_token']) ? $_REQUEST['csrf_token'] : '');

require_once "sessions.php";
require_once "db.php";
require_once "db-prefs.php";

startup_gettext();

$script_started = microtime(true);

if (!init_plugins()) {
    return;
}
header("Content-Type: text/json; charset=utf-8");

if (ENABLE_GZIP_OUTPUT && function_exists("ob_gzhandler")) {
    ob_start("ob_gzhandler");
}

if (SINGLE_USER_MODE) {
    authenticate_user("admin", null);
}

if ($_SESSION["uid"]) {
    if (!validate_session()) {
        header("Content-Type: text/json");
        print json_encode(array("error" => array("code" => 6)));
        return;
    }
    load_user_plugins($_SESSION["uid"]);
}

#$error = sanity_check();

#if ($error['code'] != 0 && $op != "logout") {
#	print json_encode(array("error" => $error));
#	return;
#}

$op = str_replace("-", "_", $op);

$override = PluginHost::getInstance()->lookup_handler($op, $method);

if (class_exists($op) || $override) {
    if ($override) {
        $handler = $override;
    } else {
        $handler = new $op($_REQUEST);
    }

    if ($handler && implements_interface($handler, 'IHandler')) {
        if (validate_csrf($csrf_token) || $handler->csrf_ignore($method)) {
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
                header("Content-Type: text/json");
                print json_encode(array("error" => array("code" => 6)));
                return;
            }
        } else {
            header("Content-Type: text/json");
            print json_encode(array("error" => array("code" => 6)));
            return;
        }
    }
}

header("Content-Type: text/json");
print json_encode(array("error" => array("code" => 7)));
