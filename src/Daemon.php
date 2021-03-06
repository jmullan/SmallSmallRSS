<?php
namespace SmallSmallRSS;

class Daemon
{
    public static $children = array();
    public static $ctimes = array();

    public static function reapChildren()
    {
        $tmp = array();
        foreach (self::$children as $pid) {
            if (pcntl_waitpid($pid, $status, WNOHANG) != $pid) {
                if (\SmallSmallRSS\Lockfiles::isLocked("update_daemon-$pid.lock")) {
                    array_push($tmp, $pid);
                } else {
                    \SmallSmallRSS\Logger::debug("[reapChildren] child $pid seems active but lockfile is unlocked.");
                    unset(self::$ctimes[$pid]);
                }
            } else {
                unset(self::$ctimes[$pid]);
            }
        }
        self::$children = $tmp;
        return count($tmp);
    }

    public static function killStuckChildren()
    {
        foreach (array_keys(self::$ctimes) as $pid) {
            $started = self::$ctimes[$pid];
            if (time() - $started > \SmallSmallRSS\Config::get('MAX_CHILD_RUNTIME')) {
                \SmallSmallRSS\Logger::debug("[MASTER] child process $pid seems to be stuck, aborting...");
                posix_kill($pid, SIGKILL);
            }
        }
    }

    public static function runningJobs()
    {
        return count(self::$children);
    }

    public static function trackPid()
    {
        array_push(self::$children, $pid);
        self::$ctimes[$pid] = time();
    }
}
