<?php
namespace SmallSmallRSS;

class Database
{
    private static $adapter = null;
    private static $link = null;

    private static function adapter()
    {
        if (!self::$adapter) {
            $er = error_reporting(E_ALL);
            if (\SmallSmallRSS\Config::get('ENABLE_PDO') && class_exists("\PDO")) {
                self::$adapter = new \SmallSmallRSS\Database\PDOWrapper();
            } else {
                switch (\SmallSmallRSS\Config::get('DB_TYPE')) {
                    case 'mysql':
                        if (function_exists('mysqli_connect')) {
                            self::$adapter = new \SmallSmallRSS\Database\MySQLi();
                        } else {
                            self::$adapter = new \SmallSmallRSS\Database\MySQL();
                        }
                        break;
                    case 'pgsql':
                        self::$adapter = new \SmallSmallRSS\Database\PostgreSQL();
                        break;
                    default:
                        die('Unknown DB_TYPE: ' . \SmallSmallRSS\Config::get('DB_TYPE'));
                }
            }
            if (!self::adapter()) {
                die('Error initializing database adapter for ' . \SmallSmallRSS\Config::get('DB_TYPE'));
            }
            self::$link = self::adapter()->connect(
                \SmallSmallRSS\Config::get('DB_HOST'),
                \SmallSmallRSS\Config::get('DB_USER'),
                \SmallSmallRSS\Config::get('DB_PASS'),
                \SmallSmallRSS\Config::get('DB_NAME'),
                \SmallSmallRSS\Config::get('DB_PORT')
            );
            if (!self::$link) {
                die('Error connecting through adapter: ' . self::adapter()->lastError());
            }
            error_reporting($er);
        }
        return self::$adapter;
    }

    public static function getLink()
    {
        self::adapter();
        return self::$link;
    }

    public static function escapeString($s, $strip_tags = true)
    {
        return self::adapter()->escapeString($s, $strip_tags);
    }

    public static function quoteString($s, $strip_tags = true)
    {
        return "'" . self::adapter()->escapeString($s, $strip_tags) . "'";
    }

    public static function query($query, $die_on_error = true)
    {
        $result = self::adapter()->query($query, $die_on_error);
        $error = self::lastError();
        if ($error) {
            \SmallSmallRSS\Logger::log('SQL error');
            \SmallSmallRSS\Logger::log($error);
            \SmallSmallRSS\Logger::log($query);
        }
        return $result;
    }

    public static function fetchAssoc($result)
    {
        return self::adapter()->fetchAssoc($result);
    }

    public static function numRows($result)
    {
        return self::adapter()->numRows($result);
    }

    public static function fetchResult($result, $row, $param)
    {
        return self::adapter()->fetchResult($result, $row, $param);
    }

    public static function affectedRows($result)
    {
        return self::adapter()->affectedRows($result);
    }

    public static function lastError()
    {
        return self::adapter()->lastError();
    }

    public static function quote($str)
    {
        return "'$str'";
    }

    public static function close()
    {
        return self::adapter()->close();
    }

    public static function getRandomFunction()
    {
        if (\SmallSmallRSS\Config::get('DB_TYPE') == 'mysql') {
            return 'RAND()';
        } else {
            return 'RANDOM()';
        }
    }

    public static function getSubstringForDateFunction()
    {
        if (\SmallSmallRSS\Config::get('DB_TYPE') == 'pgsql') {
            return 'SUBSTRING_FOR_DATE';
        } else {
            return 'SUBSTRING';
        }

    }

    public static function toSQLBool($boolean)
    {
        return ((bool) $boolean ? 'true' : 'false');
    }
    public static function fromSQLBool($boolean)
    {
        if (is_string($boolean)) {
            $boolean = strtolower($boolean);
        }
        if ($boolean == 't'
            || $boolean == 'true'
            || $boolean == 'y'
            || $boolean == 'yes'
            || $boolean == '1'
            || $boolean == 1
            || true === $boolean) {
            return true;
        } else {
            return false;
        }
    }
    public static function getPostgreSQLVersion()
    {
        $result = self::query('SELECT version() AS version');
        $version = explode(' ', self::fetchResult($result, 0, 'version'));
        return $version[1];
    }
}
