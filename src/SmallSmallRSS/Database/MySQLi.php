<?php
namespace SmallSmallRSS\Database;

class MySQLi implements DatabaseInterface
{
    private $link;

    public function connect($host, $user, $pass, $db, $port)
    {
        if ($port) {
            $this->link = mysqli_connect($host, $user, $pass, $db, $port);
        } else {
            $this->link = mysqli_connect($host, $user, $pass, $db);
        }
        if ($this->link) {
            $this->init();
            return $this->link;
        } else {
            die("Unable to connect to database (as $user to $host, database $db): " . mysqli_connect_error());
        }
    }

    public function escape_string($s, $strip_tags = true)
    {
        if ($strip_tags) {
            $s = strip_tags($s);
        }
        return mysqli_real_escape_string($this->link, $s);
    }

    public function query($query, $die_on_error = true)
    {
        $result = mysqli_query($this->link, $query);
        if (!$result) {
            user_error(
                "Query $query failed: " . ($this->link ? mysqli_error($this->link) : 'No connection'),
                $die_on_error ? E_USER_ERROR : E_USER_WARNING
            );
        }
        return $result;
    }

    public function fetch_assoc($result)
    {
        if (!$result) {
            trigger_error("Cannot return associative array from result", E_USER_NOTICE);
            return array();
        }
        return mysqli_fetch_assoc($result);
    }


    public function num_rows($result)
    {
        if ($result) {
            return mysqli_num_rows($result);
        } else {
            trigger_error("Cannot count rows of a non-mysql result", E_USER_NOTICE);
            return 0;
        }
    }

    public function fetch_result($result, $row, $param)
    {
        if ($result && mysqli_data_seek($result, $row)) {
            $line = self::fetch_assoc($result);
            if (isset($line[$param])) {
                return $line[$param];
            }
        }
        trigger_error("Cannot get param from result", E_USER_NOTICE);
        return false;
    }

    public function close()
    {
        return mysqli_close($this->link);
    }

    public function affected_rows($result)
    {
        return mysqli_affected_rows($this->link);
    }

    public function last_error()
    {
        return mysqli_error($this->link);
    }

    public function init()
    {
        $this->query("SET time_zone = '+0:0'");
        $this->query('SET NAMES ' . \SmallSmallRSS\Config::get('MYSQL_CHARSET'));
        return true;
    }
}
