<?php
namespace SmallSmallRSS;
class ErrorHandler
{
    public static function handle_error($errno, $errstr, $file, $line, $context)
    {
        if (error_reporting() == 0 || !$errno) {
            return false;
        }
        $file = substr(str_replace(dirname(dirname(__FILE__)), "", $file), 1);
        return \SmallSmallRSS\Logger::logError($errno, $errstr, $file, $line, $context);
    }

    public static function shutdown_function()
    {
        $error = error_get_last();
        if ($error !== null) {
            $errno = $error["type"];
            $file = $error["file"];
            $line = $error["line"];
            $errstr = $error["message"];

            if (!$errno) {
                return false;
            }
            $context = debug_backtrace();
            $file = substr(str_replace(dirname(dirname(__FILE__)), "", $file), 1);
            return \SmallSmallRSS\Logger::logError($errno, $errstr, $file, $line, $context);
        }
        return false;
    }

    public static function register()
    {
        static $singleton = false;
        if (!$singleton) {
            $singleton = true;
            register_shutdown_function('\SmallSmallRSS\ErrorHandler::shutdown_function');
            set_error_handler('\SmallSmallRSS\ErrorHandler::handle_error');
        }
    }
}
