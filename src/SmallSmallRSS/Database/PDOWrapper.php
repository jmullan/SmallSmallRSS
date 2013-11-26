<?php
namespace SmallSmallRSS\Database;

class PDOWrapper implements DatabaseInterface
{
    private $pdo;

    public function connect($host, $user, $pass, $db, $port)
    {
        $connstr = \SmallSmallRSS\Config::get('DB_TYPE') . ":host=$host;dbname=$db";
        if (\SmallSmallRSS\Config::get('DB_TYPE') == "mysql") {
            $connstr .= ";charset=utf8";
        }
        try {
            $this->pdo = new PDO($connstr, $user, $pass);
            $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $this->init();
        } catch (PDOException $e) {
            die($e->getMessage());
        }
        return $this->pdo;
    }

    public function escape_string($s, $strip_tags = true)
    {
        if ($strip_tags) {  $s = strip_tags($s);
        }
        $qs = $this->pdo->quote($s);
        return mb_substr($qs, 1, mb_strlen($qs)-2);
    }

    public function query($query, $die_on_error = true)
    {
        try {
            return new \SmallSmallRSS\Database\PDOStatement($this->pdo->query($query));
        } catch (PDOException $e) {
            user_error($e->getMessage(), $die_on_error ? E_USER_ERROR : E_USER_WARNING);
        }
    }

    public function fetch_assoc($result)
    {
        try {
            if ($result) {
                return $result->fetch();
            } else {
                return null;
            }
        } catch (PDOException $e) {
            user_error($e->getMessage(), E_USER_WARNING);
        }
    }

    public function num_rows($result)
    {
        try {
            if ($result) {
                return $result->rowCount();
            } else {
                return false;
            }
        } catch (PDOException $e) {
            user_error($e->getMessage(), E_USER_WARNING);
        }
    }

    public function fetch_result($result, $row, $param)
    {
        return $result->fetch_result($row, $param);
    }

    public function close()
    {
        $this->pdo = null;
    }

    public function affected_rows($result)
    {
        try {
            if ($result) {
                return $result->rowCount();
            } else {
                return null;
            }
        } catch (PDOException $e) {
            user_error($e->getMessage(), E_USER_WARNING);
        }
    }

    public function last_error()
    {
        return join(" ", $this->pdo->errorInfo());
    }

    public function init()
    {
        switch (\SmallSmallRSS\Config::get('DB_TYPE')) {
            case "pgsql":
                $this->query("set client_encoding = 'UTF-8'");
                $this->query("set datestyle = 'ISO, european'");
                $this->query("set TIME ZONE 0");
            case "mysql":
                $this->query("SET time_zone = '+0:0'");
                return;
        }
        return true;
    }
}
