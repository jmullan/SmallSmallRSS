<?php
namespace SmallSmallRSS;

class LoggerSyslog implements \SmallSmallRSS\LoggerInterface
{
    public static function logError($errno, $errstr, $file, $line, $context)
    {
        switch ($errno) {
            case E_ERROR:
            case E_PARSE:
            case E_CORE_ERROR:
            case E_COMPILE_ERROR:
            case E_USER_ERROR:
                $priority = LOG_ERR;
                break;
            case E_WARNING:
            case E_CORE_WARNING:
            case E_COMPILE_WARNING:
            case E_USER_WARNING:
                $priority = LOG_WARNING;
                break;
            default:
                $priority = LOG_INFO;
                break;
        }
        $errname = \SmallSmallRSS\Logger::$errornames[$errno] . " ($errno)";
        syslog($priority, "[tt-rss] $errname ($file:$line) $errstr");

    }

    public static function log($string, $priority = LOG_ERR)
    {
        if (!is_string($string)) {
            $string = var_export($string, true);
        }
        $backtrace = debug_backtrace();
        foreach (array_slice($backtrace, 1, 1) as $step) {
            $file = str_replace(dirname(dirname(dirname(__DIR__))) . '/', '', $step['file']);
            $string = $file . ':' . $step['line'] . ' ' . $string;
        }
        error_log($string);
    }

    public static function trace($string, $priority = LOG_ERR)
    {
        if (!is_string($string)) {
            $string = var_export($string, true);
        }
        $backtrace = debug_backtrace();
        foreach ($backtrace as $step) {
            $file = str_replace(dirname(dirname(dirname(__DIR__))) . '/', '', $step['file']);
            $string = $file . ':' . $step['line'] . ' ' . $string;
        }
        error_log($string);
    }

    public static function clearExpired()
    {
        return false;
    }
}
