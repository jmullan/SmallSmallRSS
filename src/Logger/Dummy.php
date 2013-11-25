<?php
namespace SmallSmallRSS;

class Logger_Dummy extends Logger_Abstract implements Logger_Interface
{
    public static function logError($errno, $errstr, $file, $line, $context)
    {
        return false;
    }

    public static function log($string, $priority = LOG_ERR)
    {
        return false;
    }
}
