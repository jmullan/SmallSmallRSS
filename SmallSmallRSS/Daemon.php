namespace SmallSmallRSS;
class Daemon {
    static public $children = array();
    static public $ctimes = array();

    static public function reap_children() {
        $tmp = array();
        foreach (self::$children as $pid) {
            if (pcntl_waitpid($pid, $status, WNOHANG) != $pid) {
                if (file_is_locked("update_daemon-$pid.lock")) {
                    array_push($tmp, $pid);
                } else {
                    _debug("[reap_children] child $pid seems active but lockfile is unlocked.");
                    unset(self::$ctimes[$pid]);
                }
            } else {
                _debug("[reap_children] child $pid reaped.");
                unset(self::$ctimes[$pid]);
            }
        }
        self::$children = $tmp;
        return count($tmp);
    }

    static public function kill_stuck_children() {
        foreach (array_keys(self::$ctimes) as $pid) {
            $started = self::$ctimes[$pid];
            if (time() - $started > MAX_CHILD_RUNTIME) {
                _debug("[MASTER] child process $pid seems to be stuck, aborting...");
                posix_kill($pid, SIGKILL);
            }
        }
    }

    static public function running_jobs() {
        return count(self::$children);
    }

    static public function track_pid() {
        array_push(self::$children, $pid);
        self::$ctimes[$pid] = time();
    }
}
