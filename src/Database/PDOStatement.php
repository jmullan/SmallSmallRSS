<?php
namespace SmallSmallRSS\Database;

class PDOStatement
{
    private $stmt;
    private $cache;

    public function __construct($stmt)
    {
        $this->stmt = $stmt;
        $this->cache = false;
    }

    public function fetch_result($row, $param)
    {
        if (!$this->cache) {
            $this->cache = $this->stmt->fetchAll();
        }
        if (isset($this->cache[$row])) {
            return $this->cache[$row][$param];
        } else {
            user_error("Unable to jump to row $row", E_USER_WARNING);
            return false;
        }
    }

    public function rowCount()
    {
        return $this->stmt->rowCount();
    }

    public function fetch()
    {
        return $this->stmt->fetch();
    }
}
