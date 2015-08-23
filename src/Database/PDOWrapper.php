<?php
namespace SmallSmallRSS\Database;

class PDOWrapper implements DatabaseInterface
{
    private $pdo;

    public function connect($host, $user, $pass, $db, $port)
    {
        $connstr = \SmallSmallRSS\Config::get('DB_TYPE') . ":host=$host;dbname=$db";
        if (\SmallSmallRSS\Config::get('DB_TYPE') == 'mysql') {
            $connstr .= ';charset=utf8';
        }
        try {
            $this->pdo = new \PDO($connstr, $user, $pass);
            $this->pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
            $this->init();
        } catch (\PDOException $e) {
            die($e->getMessage());
        }
        return $this->pdo;
    }

    public function escapeString($s, $strip_tags = true)
    {
        if ($strip_tags) {
            $s = strip_tags($s);
        }
        $qs = $this->pdo->quote($s);
        return mb_substr($qs, 1, mb_strlen($qs)-2);
    }

    public function query($query, $die_on_error = true)
    {
        try {
            return new \SmallSmallRSS\Database\PDOStatement($this->pdo->query($query));
        } catch (\PDOException $e) {
            user_error($e->getMessage(), $die_on_error ? E_USER_ERROR : E_USER_WARNING);
        }
    }

    public function fetchAssoc($result)
    {
        try {
            if ($result) {
                return $result->fetch(\PDO::FETCH_ASSOC);
            } else {
                return null;
            }
        } catch (\PDOException $e) {
            user_error($e->getMessage(), E_USER_WARNING);
        }
    }

    public function numRows($result)
    {
        try {
            if ($result) {
                return $result->rowCount();
            } else {
                return false;
            }
        } catch (\PDOException $e) {
            user_error($e->getMessage(), E_USER_WARNING);
        }
    }

    public function fetchResult($result, $row, $param)
    {
        if (!$result) {
            \SmallSmallRSS\Logger::trace("Cannot get param from non-result");
            return false;
        }
        if ($row) {
            $line = $result->fetch(\PDO::FETCH_ASSOC, PDO::FETCH_ORI_ABS, $row);
        } else {
            $line = self::fetchAssoc($result);
        }
        if (!$line) {
            \SmallSmallRSS\Logger::trace("Fetching an array returned nothing from result");
            return false;
        }
        if (!array_key_exists($param, $line)) {
            trigger_error("Cannot get row $row param $param from result", E_USER_NOTICE);
            return false;
        }
        return $line[$param];
    }

    public function close()
    {
        $this->pdo = null;
    }

    public function affectedRows($result)
    {
        try {
            if ($result) {
                return $result->rowCount();
            } else {
                return null;
            }
        } catch (\PDOException $e) {
            user_error($e->getMessage(), E_USER_WARNING);
        }
    }

    public function lastError()
    {
        $errorInfo = $this->pdo->errorInfo();
        if ($errorInfo[0] && $errorInfo[0] !== '00000') {
            return join(' ', $errorInfo);
        } else {
            return null;
        }
    }

    public function init()
    {
        switch (\SmallSmallRSS\Config::get('DB_TYPE')) {
            case 'pgsql':
                $this->query("set client_encoding = 'UTF-8'");
                $this->query("set datestyle = 'ISO, european'");
                $this->query('set TIME ZONE 0');
                return;
            case 'mysql':
                $this->query("SET time_zone = '+0:0'");
                return;
        }
        return true;
    }
}
