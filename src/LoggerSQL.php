<?php
namespace SmallSmallRSS;

class LoggerSQL extends LoggerAbstract implements LoggerInterface
{
    public static function logError($errno, $errstr, $file, $line, $context)
    {
        if (\SmallSmallRSS\Sanity::getSchemaVersion() > 117) {
            $errno = \SmallSmallRSS\Database::escapeString($errno);
            $errstr = \SmallSmallRSS\Database::escapeString($errstr);
            $file = \SmallSmallRSS\Database::escapeString($file);
            $line = \SmallSmallRSS\Database::escapeString($line);
            $context = '';
            $owner_uid = 'NULL';
            if (!isset($_SESSION)) {
                trigger_error('Tried to access $_SESSION before it was created');
            }
            if (!empty($_SESSION['uid'])) {
                $owner_uid = $_SESSION['uid'];
            }
            $result = \SmallSmallRSS\Database::query(
                "INSERT INTO ttrss_error_log
                 (errno, errstr, filename, lineno, context, owner_uid, created_at)
                 VALUES
                 ($errno, '$errstr', '$file', '$line', '$context', $owner_uid, NOW())"
            );
            return \SmallSmallRSS\Database::affectedRows($result) != 0;
        }
        return false;
    }


    public static function clearExpired()
    {
        if (\SmallSmallRSS\Config::get('DB_TYPE') == 'pgsql') {
            \SmallSmallRSS\Database::query(
                "DELETE FROM ttrss_error_log
                 WHERE created_at < NOW() - INTERVAL '7 days'"
            );
        } else {
            \SmallSmallRSS\Database::query(
                'DELETE FROM ttrss_error_log
                 WHERE created_at < DATE_SUB(NOW(), INTERVAL 7 DAY)'
            );
        }
        return true;
    }
}
