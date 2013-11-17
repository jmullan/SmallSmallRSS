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
        $ini_file = __DIR__ . '/../config.ini';
        if (!file_exists($ini_file)) {
            return;
        }
        if (!is_readable($ini_file)) {
            return;
        }
        $ini_array = parse_ini_file($ini_file);
        foreach ($ini_array as $key => $value) {
            self::$values[$key] = $value;
        }
    }

    public static function get($key) {
        self::initialize();
        if (!isset(self::$values[$key])) {
            $default_key = '\SmallSmallRSS\DefaultConfigs::' . $key;
            if (defined($default_key)) {
                self::$values[$key] = constant($default_key);
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