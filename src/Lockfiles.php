<?php
namespace SmallSmallRSS;

class Lockfiles
{
    public static function unlinkExpired()
    {
        $num_deleted = 0;
        if (!is_writable(\SmallSmallRSS\Config::get('LOCK_DIRECTORY'))) {
            \SmallSmallRSS\Logger::debug(
                \SmallSmallRSS\Config::get('LOCK_DIRECTORY') . ' is not writable'
            );
            return;
        }
        $files = glob(\SmallSmallRSS\Config::get('LOCK_DIRECTORY') . '/*.lock');
        if ($files) {
            foreach ($files as $file) {
                if (!self::isLocked(basename($file)) && time() - filemtime($file) > 86400 * 2) {
                    unlink($file);
                    ++$num_deleted;
                }
            }
        }
    }

    public static function getContents($lockfile)
    {
        $full_path = \SmallSmallRSS\Config::get('LOCK_DIRECTORY') . "/$lockfile";
        if (is_file($full_path) && is_readable($full_path)) {
            return file_get_contents($full_path);
        } else {
            return null;
        }
    }

    public static function unlock($lockfile)
    {
        $full_path = \SmallSmallRSS\Config::get('LOCK_DIRECTORY') . "/$lockfile";
        if (file_exists($full_path)) {
            unlink($full_path);
        }
    }

    public static function make($lockfile)
    {
        $full_path = \SmallSmallRSS\Config::get('LOCK_DIRECTORY') . "/$lockfile";
        if (!touch($full_path)) {
            \SmallSmallRSS\Logger::Log("Could not touch $full_path");
            return false;
        }
        $fp = fopen($full_path, 'w');
        if (!$fp) {
            \SmallSmallRSS\Logger::Log("Could not get lockfile pointer to $full_path");
            return false;
        }
        if (!flock($fp, LOCK_EX | LOCK_NB)) {
            \SmallSmallRSS\Logger::Log("Could not get lock on $full_path");
            return false;
        }
        $stat_h = fstat($fp);
        $stat_f = stat($full_path);
        if (strtoupper(substr(PHP_OS, 0, 3)) !== 'WIN') {
            if ($stat_h['ino'] != $stat_f['ino'] ||
                $stat_h['dev'] != $stat_f['dev']) {
                \SmallSmallRSS\Logger::log('lockfile stats do not match.');
                return false;
            }
        }
        if (function_exists('posix_getpid')) {
            fwrite($fp, posix_getpid() . "\n");
        }
        return $fp;
    }

    public static function isLocked($lockfile)
    {
        $full_path = \SmallSmallRSS\Config::get('LOCK_DIRECTORY') . "/$lockfile";
        if (!file_exists($full_path)) {
            return false;
        }
        if (!function_exists('flock')) {
            return true; // consider the file always locked and skip the test
        }
        $fp = @fopen($full_path, 'r');
        if (!$fp) {
            return false;
        }
        if (flock($fp, LOCK_EX | LOCK_NB)) {
            flock($fp, LOCK_UN);
            fclose($fp);
            return false;
        }
        fclose($fp);
        return true;
    }

    public static function lastModified($lockfile)
    {
        $full_path = \SmallSmallRSS\Config::get('LOCK_DIRECTORY') . "/$lockfile";
        if (!file_exists($full_path)) {
            return 0;
        }
        $stat_f = stat($full_path);
        return $stat_f['mtime'];
    }

    public static function stamp($stamp)
    {
        $lockfile = $stamp . '.stamp';
        $full_path = \SmallSmallRSS\Config::get('LOCK_DIRECTORY') . "/$lockfile";
        $fp = fopen($full_path, 'w');
        if (!$fp) {
            \SmallSmallRSS\Logger::Log("Could not get write handle on $full_path");
            return false;
        }
        if (!flock($fp, LOCK_EX | LOCK_NB)) {
            \SmallSmallRSS\Logger::Log("Could not get lock on $full_path");
            fclose($fp);
            return false;
        }
        fwrite($fp, time() . "\n");
        flock($fp, LOCK_UN);
        fclose($fp);
        return true;
    }

    public static function whenStamped($stamp)
    {
        $lockfile = $stamp . '.stamp';
        return self::getContents($lockfile);

    }
}
