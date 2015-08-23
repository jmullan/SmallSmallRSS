<?php
namespace SmallSmallRSS\Database;

interface DatabaseInterface
{
    public function connect($host, $user, $pass, $db, $port);
    public function escapeString($s, $strip_tags = true);
    public function query($query, $die_on_error = true);
    public function fetchAssoc($result);
    public function numRows($result);
    public function fetchResult($result, $row, $param);
    public function close();
    public function affectedRows($result);
    public function lastError();
}
