<?php
namespace SmallSmallRSS;
interface Logger_Interface {
    function log_error($errno, $errstr, $file, $line, $context);
    function log($string, $priority=LOG_ERR);
}