<?php
namespace SmallSmallRSS;
class Database
{
    private static $adapter = null;
    private static $link = null;

    private static function adapter() {
        if (!self::$adapter) {
            $er = error_reporting(E_ALL);
            if (defined('_ENABLE_PDO') && _ENABLE_PDO && class_exists("PDO")) {
                self::$adapter = new \SmallSmallRSS\Database\PDOWrapper();
            } else {
                switch (DB_TYPE) {
                    case "mysql":
                        if (function_exists("mysqli_connect")) {
                            self::$adapter = new \SmallSmallRSS\Database\MySQLi();
                        } else {
                            self::$adapter = new \SmallSmallRSS\Database\MySQL();
                        }
                        break;
                    case "pgsql":
                        self::$adapter = new \SmallSmallRSS\Database\PostgreSQL();
                        break;
                    default:
                        die("Unknown DB_TYPE: " . DB_TYPE);
                }
            }
            if (!self::adapter()) {
                die("Error initializing database adapter for " . DB_TYPE);
            }
            self::$link = self::adapter()->connect(
                DB_HOST,
                DB_USER,
                DB_PASS,
                DB_NAME,
                defined('DB_PORT') ? DB_PORT : ""
            );
            if (!self::$link) {
                die("Error connecting through adapter: " . self::adapter()->last_error());
            }
            error_reporting($er);
        }
        return self::$adapter;
    }

    public static function escape_string($s, $strip_tags = true)
    {
        return self::adapter()->escape_string($s, $strip_tags);
    }

    public static function query($query, $die_on_error = true)
    {
        $result = self::adapter()->query($query, $die_on_error);
        $error = self::last_error();
        if ($error) {
            \SmallSmallRSS\Logger::log('SQL error');
        }
        return $result;
    }

    public static function fetch_assoc($result)
    {
        return self::adapter()->fetch_assoc($result);
    }

    public static function num_rows($result)
    {
        return self::adapter()->num_rows($result);
    }

    public static function fetch_result($result, $row, $param)
    {
        return self::adapter()->fetch_result($result, $row, $param);
    }

    public static function affected_rows($result)
    {
        return self::adapter()->affected_rows($result);
    }

    public static function last_error()
    {
        return self::adapter()->last_error();
    }

    public static function quote($str)
    {
        return "'$str'";
    }

    public static function close()
    {
        return self::adapter()->close();
    }

}
