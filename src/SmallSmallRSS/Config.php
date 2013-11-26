<?php
namespace SmallSmallRSS;

class Config {
    private static $initialized = false;
    private static $values = array();

    private static function initialize() {
        if (self::$initialized) {
            return;
        }
        self::$initialized = true;
        self::read_from_ini();
    }

    private static function read_from_ini() {
        $ini_file = __DIR__ . '/../../config.ini';
        if (!file_exists($ini_file)) {
            return;
        }
        if (!is_readable($ini_file)) {
            return;
        }
        $ini_array = parse_ini_file($ini_file);
        foreach ($ini_array as $key => $value) {
            $type = \SmallSmallRSS\DefaultConfigs::type($key);
            settype($value, $type);
            self::$values[$key] = $value;
        }
    }

    public static function get($key) {
        self::initialize();
        if (!isset(self::$values[$key])) {
            if (\SmallSmallRSS\DefaultConfigs::has($key)) {
                self::$values[$key] = \SmallSmallRSS\DefaultConfigs::get($key);
            }
            if (defined($key)) {
                self::$values[$key] = constant($key);
            }
        }
        return self::$values[$key];
    }

    public static function set($key, $value) {
        self::initialize();
        self::$values[$key] = $value;
    }
}