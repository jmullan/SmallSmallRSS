<?php
namespace SmallSmallRSS;

class Logger_SQL extends Logger_Abstract implements Logger_Interface
{
    public static function logError($errno, $errstr, $file, $line, $context)
    {
        if (\Db::get() && \SmallSmallRSS\Sanity::getSchemaVersion() > 117) {

            $errno = \SmallSmallRSS\Database::escape_string($errno);
            $errstr = \SmallSmallRSS\Database::escape_string($errstr);
            $file = \SmallSmallRSS\Database::escape_string($file);
            $line = \SmallSmallRSS\Database::escape_string($line);
            $context = '';
            $owner_uid = 'NULL';
            if (!isset($_SESSION)) {
                trigger_error('Tried to access $_SESSION before it was created');
            }
            if (!empty($_SESSION['uid'])) {
                $owner_uid = $_SESSION["uid"];
            }

            $result = \SmallSmallRSS\Database::query(
                "INSERT INTO ttrss_error_log
				(errno, errstr, filename, lineno, context, owner_uid, created_at) VALUES
				($errno, '$errstr', '$file', '$line', '$context', $owner_uid, NOW())"
            );

            return \SmallSmallRSS\Database::affected_rows($result) != 0;
        }
        return false;
    }
}
