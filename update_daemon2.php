#!/usr/bin/env php
<?php
require_once __DIR__ . '/SmallSmallRSS/bootstrap.php';

chdir(__DIR__);

\SmallSmallRSS\Sanity::initialCheck();

if (!function_exists('pcntl_fork')) {
    die("error: This script requires PHP compiled with PCNTL module.\n");
}

$master_handlers_installed = false;
$last_checkpoint = -1;

function sigchld_handler($signal)
{
    $running_jobs = \SmallSmallRSS\Daemon::reap_children();
    _debug("[SIGCHLD] jobs left: $running_jobs");
    $status = null;
    pcntl_waitpid(-1, $status, WNOHANG);
}

function shutdown($caller_pid)
{
    if ($caller_pid == posix_getpid()) {
        _debug("removing lockfile (master)...");
        \SmallSmallRSS\Lockfiles::unlock("update_daemon.lock");
    }
}

function task_shutdown()
{
    $pid = posix_getpid();
    _debug("removing lockfile ($pid)...");
    \SmallSmallRSS\Lockfiles::unlock("update_daemon-$pid.lock");
}

function sigint_handler()
{
    _debug("[MASTER] SIG_INT received.\n");
    shutdown(posix_getpid());
    die;
}

function task_sigint_handler()
{
    _debug("[TASK] SIG_INT received.\n");
    task_shutdown();
    die;
}

pcntl_signal(SIGCHLD, 'sigchld_handler');

$longopts = array("log:", "tasks:", "interval:", "quiet", "help");

$options = getopt("", $longopts);

if (isset($options["help"])) {
    print "Tiny Tiny RSS update daemon.\n\n";
    print "Options:\n";
    print "  --log FILE           - log messages to FILE\n";
    print "  --tasks N            - amount of update tasks to spawn\n";
    print "                         default: " . \SmallSmallRSS\Config::get('MAX_JOBS') . "\n";
    print "  --interval N         - task spawn interval\n";
    print "                         default: " . \SmallSmallRSS\Config::get('SPAWN_INTERVAL') . " seconds.\n";
    print "  --quiet              - don't output messages to stdout\n";
    return;
}

\SmallSmallRSS\Config::set('VERBOSITY', isset($options['quiet']) ? 0 : 1);

if (isset($options["tasks"])) {
    _debug("Set to spawn " . $options["tasks"] . " children.");
    \SmallSmallRSS\Config::set('MAX_JOBS', (int) $options["tasks"]);
}
$max_jobs = \SmallSmallRSS\Config::get('MAX_JOBS');

if (isset($options["interval"])) {
    _debug("Spawn interval: " . $options["interval"] . " seconds.");
    \SmallSmallRSS\Config::set('SPAWN_INTERVAL', (int) $options["interval"]);
}
$spawn_interval = \SmallSmallRSS\Config::get('SPAWN_INTERVAL');
if (isset($options["log"])) {
    _debug("Logging to " . $options["log"]);
    define('LOGFILE', $options["log"]);
}

if (file_is_locked("update_daemon.lock")) {
    die("error: Can't create lockfile. ".
        "Maybe another daemon is already running.\n");
}

// Try to lock a file in order to avoid concurrent update.
$lock_handle = \SmallSmallRSS\Lockfiles::make("update_daemon.lock");
if (!$lock_handle) {
    die("error: Can't create lockfile. Maybe another daemon is already running.\n");
}
\SmallSmallRSS\Sanity::schemaOrDie();

// Protip: children close shared database handle when terminating, it's a bad idea to
// do database stuff on main process from now on.

while (true) {

    // Since sleep is interupted by SIGCHLD, we need another way to
    // respect the spawn interval
    $next_spawn = $last_checkpoint + $spawn_interval - time();

    if ($next_spawn % 60 == 0) {
        $running_jobs = \SmallSmallRSS\Daemon::running_jobs();
        _debug("[MASTER] active jobs: $running_jobs, next spawn at $next_spawn sec.");
    }

    if ($last_checkpoint + $spawn_interval < time()) {
        \SmallSmallRSS\Daemon::kill_stuck_children();
        \SmallSmallRSS\Daemon::reap_children();

        for ($j = \SmallSmallRSS\Daemon::running_jobs(); $j < $max_jobs; $j++) {
            $pid = pcntl_fork();
            if ($pid == -1) {
                die("fork failed!\n");
            } elseif ($pid) {
                if (!$master_handlers_installed) {
                    _debug("[MASTER] installing shutdown handlers");
                    pcntl_signal(SIGINT, 'sigint_handler');
                    pcntl_signal(SIGTERM, 'sigint_handler');
                    register_shutdown_function('shutdown', posix_getpid());
                    $master_handlers_installed = true;
                }

                _debug("[MASTER] spawned client $j [PID:$pid]...");
                \SmallSmallRSS\Daemon::track_pid($pid);
            } else {
                pcntl_signal(SIGCHLD, SIG_IGN);
                pcntl_signal(SIGINT, 'task_sigint_handler');

                register_shutdown_function('task_shutdown');

                $quiet = (isset($options["quiet"])) ? "--quiet" : "";

                $my_pid = posix_getpid();

                passthru(
                    \SmallSmallRSS\Config::get('PHP_EXECUTABLE')
                    . " update.php --daemon-loop $quiet --task $j --pidlock $my_pid"
                );
                sleep(1);

                // We exit in order to avoid fork bombing.
                exit(0);
            }
        }
        $last_checkpoint = time();
    }
    sleep(1);
}
