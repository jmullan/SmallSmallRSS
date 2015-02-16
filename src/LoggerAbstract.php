<?php
namespace SmallSmallRSS;

abstract class LoggerAbstract implements LoggerInterface
{
    public static function logError($errno, $errstr, $file, $line, $context)
    {
        return false;
    }
    public static function log($string, $priority = LOG_ERR)
    {
        return false;
    }
    public static function trace($string, $priority = LOG_ERR)
    {
        return false;
    }
    public static function clearExpired()
    {
        return false;
    }
}
