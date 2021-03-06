#!/usr/bin/env php
<?php
require_once __DIR__ . '/../src/bootstrap.php';
chdir(__DIR__);
\SmallSmallRSS\PluginHost::initAll();

$longopts = array(
    "feeds",
    "feedbrowser",
    "daemon",
    "daemon-loop",
    "task:",
    "cleanup-tags",
    "quiet",
    "log:",
    "indexes",
    "pidlock:",
    "num-updates:",
    "update-schema",
    "convert-filters",
    "force-update",
    "list-plugins",
    "help"
);

foreach (\SmallSmallRSS\PluginHost::getInstance()->getCommands() as $command => $data) {
    array_push($longopts, $command . $data["suffix"]);
}

$options = getopt("", $longopts);

if (count($options) == 0 && !defined('STDIN')) {
    $updater = new \SmallSmallRSS\Renderers\Updater();
    $updater->render();
    exit;
}

if (count($options) == 0 || isset($options["help"])) {
    print "Tiny Tiny RSS data update script.\n\n";
    print "Options:\n";
    print "  --feeds              - update feeds\n";
    print "  --feedbrowser        - update feedbrowser\n";
    print "  --daemon             - start single-process update daemon\n";
    print "  --task N             - create lockfile using this task id\n";
    print "  --cleanup-tags       - perform tags table maintenance\n";
    print "  --quiet              - don't output messages to stdout\n";
    print "  --log FILE           - log messages to FILE\n";
    print "  --indexes            - recreate missing schema indexes\n";
    print "  --update-schema      - update database schema\n";
    print "  --convert-filters    - convert type1 filters to type2\n";
    print "  --force-update       - force update of all feeds\n";
    print "  --list-plugins       - list all available plugins\n";
    print "  --help               - show this help\n";
    print "  --num-updates N      - update this many feeds\n";
    print "Plugin options:\n";

    foreach (\SmallSmallRSS\PluginHost::getInstance()->getCommands() as $command => $data) {
        $args = $data['arghelp'];
        printf(" --%-19s - %s\n", "$command $args", $data["description"]);
    }

    return;
}

if (!isset($options['update-schema'])) {
    \SmallSmallRSS\Sanity::schemaOrDie();
}

\SmallSmallRSS\Config::set('VERBOSITY', isset($options['quiet']) ? 0 : 1);

if (isset($options["log"])) {
    define('LOGFILE', $options["log"]);
}

if (!isset($options["daemon"])) {
    $lock_filename = "update.lock";
} else {
    $lock_filename = "update_daemon.lock";
}

if (isset($options["task"])) {
    $lock_filename = $lock_filename . "-task_" . $options["task"];
}

if (isset($options["pidlock"])) {
    $my_pid = $options["pidlock"];
    $lock_filename = "update_daemon-$my_pid.lock";
}

$num_updates = null;
if (isset($options["num-updates"])) {
    $num_updates = intval($options["num-updates"]);
    if (!$num_updates) {
        die('Error: invalid number of updates');
    }
}

$lock_handle = \SmallSmallRSS\Lockfiles::make($lock_filename);
// Try to lock a file in order to avoid concurrent update.
if (!$lock_handle) {
    die(
        "error: Can't create lockfile ($lock_filename)."
        . "Maybe another update process is already running.\n"
    );
}
$must_exit = false;

if (isset($options["task"]) && isset($options["pidlock"])) {
    $waits = $options["task"] * 5;
    sleep($waits);
}


if (isset($options["force-update"])) {
    \SmallSmallRSS\Feeds::resetAll();
}

if (isset($options["feeds"])) {
    \SmallSmallRSS\Lockfiles::stamp('update_feeds');
    \SmallSmallRSS\RSSUpdater::updateBatch($num_updates);
    \SmallSmallRSS\RSSUpdater::housekeeping();
    \SmallSmallRSS\PluginHost::getInstance()->runHooks(\SmallSmallRSS\Hooks::UPDATE_TASK);
}

if (isset($options["feedbrowser"])) {
    $lines = \SmallSmallRSS\Feeds::countFeedSubscribers();
    $count = \SmallSmallRSS\FeedbrowserCache::update($lines);
    print "Finished, $count feeds processed.\n";
}

if (isset($options["daemon"])) {
    while (true) {
        $quiet = (isset($options["quiet"])) ? "--quiet" : "";
        passthru(\SmallSmallRSS\Config::get('PHP_EXECUTABLE') . " " . $argv[0] ." --daemon-loop $quiet");
        $sleep = \SmallSmallRSS\Config::get('DAEMON_SLEEP_INTERVAL');
        sleep($sleep);
    }
}

if (isset($options["daemon-loop"])) {
    if (!\SmallSmallRSS\Lockfiles::stamp('update_daemon')) {
        \SmallSmallRSS\Logger::debug("warning: unable to create stampfile\n");
    }
    \SmallSmallRSS\RSSUpdater::updateBatch($num_updates);
    if (!isset($options["pidlock"]) || $options["task"] == 0) {
        \SmallSmallRSS\RSSUpdater::housekeeping();
    }
    \SmallSmallRSS\PluginHost::getInstance()->runHooks(\SmallSmallRSS\Hooks::UPDATE_TASK);
}

if (isset($options["cleanup-tags"])) {
    $rc = cleanup_tags(14, 50000);
}

if (isset($options["indexes"])) {
    print "PLEASE BACKUP YOUR DATABASE BEFORE PROCEEDING!";
    print "Type 'yes' to continue.";
    if (\SmallSmallRSS\Utils::readStdin() != 'yes') {
        exit;
    }
    \SmallSmallRSS\Database\Updater::dropIndexes();
    \SmallSmallRSS\Database\Updater::createIndexes();
}

if (isset($options["convert-filters"])) {
    print "WARNING: this will remove all existing type2 filters.";
    print "Type 'yes' to continue.";
    if (\SmallSmallRSS\Utils::readStdin() != 'yes') {
        exit;
    }
    \SmallSmallRSS\Database::query("DELETE FROM ttrss_filters2");

    $result = \SmallSmallRSS\Database::query("SELECT * FROM ttrss_filters ORDER BY id");

    while (($line = \SmallSmallRSS\Database::fetchAssoc($result))) {
        $owner_uid = $line["owner_uid"];

        // date filters are removed
        if ($line["filter_type"] != 5) {
            $filter = array();

            if (\SmallSmallRSS\Database::fromSQLBool($line["cat_filter"])) {
                $feed_id = "CAT:" . (int) $line["cat_id"];
            } else {
                $feed_id = (int) $line["feed_id"];
            }

            $filter["enabled"] = $line["enabled"] ? "on" : "off";
            $filter["rule"] = array(
                json_encode(array(
                                "reg_exp" => $line["reg_exp"],
                                "feed_id" => $feed_id,
                                "filter_type" => $line["filter_type"])));

            $filter["action"] = array(
                json_encode(
                    array(
                        "action_id" => $line["action_id"],
                        "action_param_label" => $line["action_param"],
                        "action_param" => $line["action_param"]
                    )
                )
            );

            // Oh god it's full of hacks

            $_REQUEST = $filter;
            $_SESSION["uid"] = $owner_uid;

            $filters = new PrefFilters($_REQUEST);
            $filters->add();
        }
    }

}

if (isset($options["update-schema"])) {
    $updater = new \SmallSmallRSS\Database\Updater();
    if ($updater->isUpdateRequired()) {
        print "WARNING: please backup your database before continuing.";
        print "Type 'yes' to continue.";

        if (\SmallSmallRSS\Utils::readStdin() != 'yes') {
            exit;
        }
        for ($i = $updater->getSchemaVersion() + 1; $i <= \SmallSmallRSS\Constants::SCHEMA_VERSION; $i++) {
            $result = $updater->performUpdateTo($i);
            if (!$result) {
                print 'DATABASE UPDATE FAILED';
                exit(1);
            }
        }
    }
}

if (isset($options["list-plugins"])) {
    $tmppluginhost = new \SmallSmallRSS\PluginHost();
    $tmppluginhost->loadAll($tmppluginhost::KIND_ALL);
    $enabled = array_map('trim', explode(',', \SmallSmallRSS\Config::get('PLUGINS')));
    echo "List of all available plugins:\n";
    foreach ($tmppluginhost->getPlugins() as $name => $plugin) {
        $about = $plugin->about();
        $status = $about[3] ? "system" : "user";
        if (in_array($name, $enabled)) {
            $name .= "*";
        }
        printf(
            "%-50s %-10s v%.2f (by %s)\n%s\n\n",
            $name,
            $status,
            $about[0],
            $about[2],
            $about[1]
        );
    }
    echo "Plugins marked by * are currently enabled for all users.\n";
}

\SmallSmallRSS\PluginHost::getInstance()->runCommands($options);
\SmallSmallRSS\Lockfiles::unlock($lock_filename);
