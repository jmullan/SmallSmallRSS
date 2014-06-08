<?php
namespace SmallSmallRSS\Database;

class MySQL implements DatabaseInterface
{
    private $link;

    public function connect($host, $user, $pass, $db, $port)
    {
        $this->link = mysql_connect($host, $user, $pass);
        if ($this->link) {
            $result = mysql_select_db($db, $this->link);
            if (!$result) {
                die("Can't select DB: " . mysql_error($this->link));
            }
            $this->init();
            return $this->link;
        } else {
            die("Unable to connect to database (as $user to $host, database $db): " . mysql_error());
        }
    }

    public function escape_string($s, $strip_tags = true)
    {
        if ($strip_tags) {
            $s = strip_tags($s);
        }
        return mysql_real_escape_string($s, $this->link);
    }

    public function query($query, $die_on_error = true)
    {
        $result = mysql_query($query, $this->link);
        if (!$result) {
            user_error(
                "Query $query failed: " . ($this->link ? mysql_error($this->link) : 'No connection'),
                $die_on_error ? E_USER_ERROR : E_USER_WARNING
            );
        }
        return $result;
    }

    public function fetch_assoc($result)
    {
        return mysql_fetch_assoc($result);
    }


    public function num_rows($result)
    {
        return mysql_num_rows($result);
    }

    public function fetch_result($result, $row, $param)
    {
        return mysql_result($result, $row, $param);
    }

    public function close()
    {
        return mysql_close($this->link);
    }

    public function affected_rows($result)
    {
        return mysql_affected_rows($this->link);
    }

    public function last_error()
    {
        return mysql_error();
    }

    public function init()
    {
        $this->query("SET time_zone = '+0:0'");
        $this->query('SET NAMES ' . \SmallSmallRSS\Config::get('MYSQL_CHARSET'));
        return true;
    }
}
