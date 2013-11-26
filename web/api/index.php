<?php
chdir(__DIR__ . "/..");

$input = file_get_contents("php://input");
if ((defined('_API_DEBUG_HTTP_ENABLED') && _API_DEBUG_HTTP_ENABLED)) {
    // Override $_REQUEST with JSON-encoded data if available
    // fallback on HTTP parameters
    if ($input) {
        $input = json_decode($input, true);
        if ($input) {
            $_REQUEST = $input;
        }
    }
} else {
    // Accept JSON only
    $input = json_decode($input, true);
    $_REQUEST = $input;
}
if (!empty($_REQUEST["sid"])) {
    session_id($_REQUEST["sid"]);
}

require_once __DIR__ . '/../../src/SmallSmallRSS/bootstrap.php';
\SmallSmallRSS\Config::set('AUTH_DISABLE_OTP', true);
\SmallSmallRSS\Session::init('ttrss_api_sid');

if (!\SmallSmallRSS\PluginHost::init_all()) {
    Logger::log('Error initializing plugins');
    return;
}

$method = strtolower(isset($_REQUEST['op']) ? $_REQUEST["op"] : '');
$handler = new \SmallSmallRSS\Handlers\API($_REQUEST);
if ($handler->before($method)) {
    if ($method && method_exists($handler, $method)) {
        $handler->$method();
    } elseif (method_exists($handler, 'index')) {
        $handler->index($method);
    }
    $handler->after();
}
header("Api-Content-Length: " . ob_get_length());
ob_end_flush();
