<?php
namespace SmallSmallRSS;

class Logger
{
    private static $instance;
    private $adapter;

    public static $errornames = array(
        1 => 'E_ERROR',
        2 => 'E_WARNING',
        4 => 'E_PARSE',
        8 => 'E_NOTICE',
        16 => 'E_CORE_ERROR',
        32 => 'E_CORE_WARNING',
        64 => 'E_COMPILE_ERROR',
        128 => 'E_COMPILE_WARNING',
        256 => 'E_USER_ERROR',
        512 => 'E_USER_WARNING',
        1024 => 'E_USER_NOTICE',
        2048 => 'E_STRICT',
        4096 => 'E_RECOVERABLE_ERROR',
        8192 => 'E_DEPRECATED',
        16384 => 'E_USER_DEPRECATED',
        32767 => 'E_ALL'
    );

    public static $level = -1;

    public static function logError($errno, $errstr, $file, $line, $context)
    {
        if (!$errno & self::$level) {
            return false;
        }
        return self::get()->adapter->logError($errno, $errstr, $file, $line, $context);
    }

    public static function log($string)
    {
        return self::get()->adapter->log($string);
    }

    private function __clone()
    {
    }

    public function __construct()
    {
        switch (\SmallSmallRSS\Config::get('LOG_DESTINATION')) {
            case 'sql':
                $this->adapter = new Logger_SQL();
                break;
            case 'syslog':
                $this->adapter = new Logger_Syslog();
                break;
            default:
                $this->adapter = new Logger_Dummy();
        }
    }

    public static function get()
    {
        if (self::$instance == null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Print a timestamped debug message.
     *
     * @param string $msg The debug message.
     * @return void
     */
    public static function debug($msg, $show = true, $is_debug = true)
    {
        if (!$is_debug) {
            return;
        }

        $ts = strftime('%H:%M:%S', time());
        if (function_exists('posix_getpid')) {
            $ts = "$ts/" . posix_getpid();
        }
        $rawcalls = debug_backtrace();
        $message = '';
        if ('_debug' == $rawcalls[1]['function']) {
            $file = str_replace(dirname(dirname(__DIR__)) . '/', '', $rawcalls[1]['file']);
            $message = '' . $file . ':' . $rawcalls[1]['line'] . ' ';
        }
        $message .= "[$ts] $msg";
        if ($show && \SmallSmallRSS\Config::get('VERBOSITY')) {
            print "$message\n";
        }

        if (defined('LOGFILE')) {
            $fp = fopen(LOGFILE, 'a+');
            if ($fp) {
                fputs($fp, "$message\n");
                fclose($fp);
            }
        }

    }

    public static function clearExpired()
    {
        self::get()->adapter->clearExpired();
    }
}
