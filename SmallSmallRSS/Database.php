<?php
namespace SmallSmallRSS;
class Database
{
    public static function escape_string($s, $strip_tags = true)
    {
        return \Db::get()->escape_string($s, $strip_tags);
    }

    public static function query($query, $die_on_error = true)
    {
        $result = \Db::get()->query($query, $die_on_error);
        $error = \Db::get()->last_error();
        if ($error) {
            \SmallSmallRSS\Logger::log('SQL error');
        }
        return $result;
    }

    public static function fetch_assoc($result)
    {
        return \Db::get()->fetch_assoc($result);
    }


    public static function num_rows($result)
    {
        return \Db::get()->num_rows($result);
    }

    public static function fetch_result($result, $row, $param)
    {
        return \Db::get()->fetch_result($result, $row, $param);
    }

    public static function affected_rows($result)
    {
        return \Db::get()->affected_rows($result);
    }

    public static function last_error()
    {
        return \Db::get()->last_error();
    }

    public static function quote($str)
    {
        return \Db::get()->quote($str);
    }
}
