<?php
namespace SmallSmallRSS;

interface LoggerInterface
{
    public static function logError($errno, $errstr, $file, $line, $context);
    public static function log($string, $priority = LOG_ERR);
    public static function clearExpired();
}
