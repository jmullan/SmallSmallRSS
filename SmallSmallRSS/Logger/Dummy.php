<?php
namespace \SmallSmallRSS;

class Logger_Dummy extends Logger_Abstract implements Logger_Interface{
    function log_error($errno, $errstr, $file, $line, $context) {
        return False;
    }

    function log($string, $priority=LOG_ERR) {
        return False;
    }
}
