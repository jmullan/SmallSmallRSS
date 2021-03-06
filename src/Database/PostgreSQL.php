<?php
namespace SmallSmallRSS\Database;

class PostgreSQL implements DatabaseInterface
{
    private $link;

    public function connect($host, $user, $pass, $db, $port)
    {
        $string = "dbname=$db user=$user";
        if ($pass) {
            $string .= " password=$pass";
        }
        if ($host) {
            $string .= " host=$host";
        }
        if (is_numeric($port) && $port > 0) {
            $string = "$string port=" . $port;
        }
        $this->link = \pg_connect($string);
        if (!$this->link) {
            die("Unable to connect to database (as $user to $host, database $db):" . \pg_last_error());
        }
        $this->init();
        return $this->link;
    }

    public function escapeString($s, $strip_tags = true)
    {
        if ($strip_tags) {
            $s = strip_tags($s);
        }
        return \pg_escape_string($s);
    }

    public function query($query, $die_on_error = true)
    {
        $result = \pg_query($query);
        if (!$result) {
            $query = htmlspecialchars($query); // just in case
            user_error(
                "Query $query failed: " . ($this->link ? \pg_last_error($this->link) : 'No connection'),
                $die_on_error ? E_USER_ERROR : E_USER_WARNING
            );
        }
        return $result;
    }

    public function fetchAssoc($result)
    {
        return \pg_fetch_assoc($result);
    }


    public function numRows($result)
    {
        return \pg_num_rows($result);
    }

    public function fetchResult($result, $row, $param)
    {
        return \pg_fetch_result($result, $row, $param);
    }

    public function close()
    {
        return \pg_close($this->link);
    }

    public function affectedRows($result)
    {
        return \pg_affected_rows($result);
    }

    public function lastError()
    {
        return \pg_last_error($this->link);
    }

    public function init()
    {
        $this->query("set client_encoding = 'UTF-8'");
        \pg_set_client_encoding('UNICODE');
        $this->query("set datestyle = 'ISO, european'");
        $this->query('set TIME ZONE 0');
        return true;
    }
}
