<?php
namespace SmallSmallRSS;

class Lockfiles {
    public static function unlink_expired($debug) {
        $num_deleted = 0;
        if (!is_writable(\SmallSmallRSS\Config::get('LOCK_DIRECTORY'))) {
            \SmallSmallRSS\Logger::debug(
                \SmallSmallRSS\Config::get('LOCK_DIRECTORY') . " is not writable",
                $debug,
                $debug
            );
            return;
        }
        $files = glob(\SmallSmallRSS\Config::get('LOCK_DIRECTORY') . "/*.lock");
        if ($files) {
            foreach ($files as $file) {
                if (!file_is_locked(basename($file)) && time() - filemtime($file) > 86400*2) {
                    unlink($file);
                    ++$num_deleted;
                }
            }
        }
        \SmallSmallRSS\Logger::debug("Removed $num_deleted old lock files.", $debug, $debug);
    }

    public static function get_contents($lockfile) {
        $full_path = \SmallSmallRSS\Config::get('LOCK_DIRECTORY') . "/$filename";
        if (is_file($full_path) && is_readable($full_path)) {
            return file_get_contents();
        } else {
            return null;
        }
    }

    public static function unlock($lockfile) {
        $full_path = \SmallSmallRSS\Config::get('LOCK_DIRECTORY') . "/$filename";
        if (file_exists($full_path)) {
            unlink($full_path);
        }
    }

    public static function make($lockfile) {
        $full_path = \SmallSmallRSS\Config::get('LOCK_DIRECTORY') . "/$full_pathname";
        $fp = fopen($full_path, "w");
        if (!$fp) {
            \SmallSmallRSS\Logger::Log("Could not get lockfile pointer to $full_path");
            return false;
        }
        if (!flock($fp, LOCK_EX | LOCK_NB)) {
            \SmallSmallRSS\Logger::Log("Could not get lock on $full_path");
            return false;
        }
        $stat_h = fstat($fp);
        $stat_f = stat(\SmallSmallRSS\Config::get('LOCK_DIRECTORY') . "/$full_pathname");
        if (strtoupper(substr(PHP_OS, 0, 3)) !== 'WIN') {
            if ($stat_h["ino"] != $stat_f["ino"] ||
                $stat_h["dev"] != $stat_f["dev"]) {
                \SmallSmallRSS\Logger::log('lockfile stats do not match.');
                return false;
            }
        }
        if (function_exists('posix_getpid')) {
            fwrite($fp, posix_getpid() . "\n");
        }
        return $fp;
    }
}