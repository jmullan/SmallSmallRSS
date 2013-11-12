<?php
namespace SmallSmallRSS;

abstract class Logger_Abstract implements Logger_Interface {
    function log_error($errno, $errstr, $file, $line, $context) {
        return false;
    }
    function log($string, $priority=LOG_ERR) {
        return false;
    }
}