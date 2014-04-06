<?php
namespace SmallSmallRSS;

class PluginHost
{
    private $dbh;
    private $hooks = array();
    private $plugins = array();
    private $handlers = array();
    private $commands = array();
    private $storage = array();
    private $feeds = array();
    private $api_methods = array();
    private $owner_uid;
    private $last_registered;
    private static $instance;

    const API_VERSION = 2;
    const KIND_ALL = 1;
    const KIND_SYSTEM = 2;
    const KIND_USER = 3;

    public function __construct()
    {
        $this->storage = array();
    }

    private function __clone()
    {
    }

    public static function getInstance()
    {
        if (self::$instance == null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function getPlugins()
    {
        return $this->plugins;
    }

    public function getPlugin($name)
    {
        if (isset($this->plugins[$name])) {
            return $this->plugins[$name];
        } else {
            return null;
        }
    }

    public function runHooks($type, $args = null)
    {
        $method = \SmallSmallRSS\Hooks::getHookMethod($type);
        if ($method !== false) {
            foreach ($this->getHooks($type) as $hook) {
                $hook->$method($args);
            }
        }
    }

    public function addHook($type, $sender)
    {
        if (!isset($this->hooks[$type]) || !is_array($this->hooks[$type])) {
            $this->hooks[$type] = array();
        }

        array_push($this->hooks[$type], $sender);
    }

    public function delete_hook($type, $sender)
    {
        if (is_array($this->hooks[$type])) {
            $key = array_Search($this->hooks[$type], $sender);
            if ($key !== false) {
                unset($this->hooks[$type][$key]);
            }
        }
    }

    public function getHooks($type)
    {
        if (isset($this->hooks[$type])) {
            return $this->hooks[$type];
        } else {
            return array();
        }
    }

    public static function classRoot()
    {
        $class_dir = __DIR__ . "/../../plugins/";
        if (!is_dir($class_dir)) {
            Logger::log("Missing class dir $class_dir");
            return false;
        }
        return $class_dir;
    }

    public function loadAll($kind, $owner_uid = false)
    {
        $class_root = self::classRoot();
        if ($class_root === false) {
            return;
        }
        $plugins = array_map('basename', glob($class_root . '*'));
        $this->load(join(',', $plugins), $kind, $owner_uid);
    }

    public function load($classlist, $kind, $owner_uid = false)
    {
        $class_root = self::classRoot();
        $plugins = explode(',', $classlist);
        $this->owner_uid = (int) $owner_uid;
        foreach ($plugins as $class) {
            $class = trim($class);
            if (!$class) {
                continue;
            }
            $class_file = strtolower(basename($class));
            $class_dir = $class_root . $class_file;
            if (!is_dir($class_dir)) {
                Logger::log("Missing class dir $class_dir");
                continue;
            }

            $file = "$class_dir/init.php";
            if (!isset($this->plugins[$class])) {
                if (file_exists($file)) {
                    require_once $file;
                }
                if (!class_exists($class)) {
                    Logger::log("Missing class $class from $file");
                    continue;
                }
                if (!is_subclass_of($class, '\SmallSmallRSS\Plugin')) {
                    $subclassing = var_export(class_parents($class), true);
                    Logger::log("Wrong class type $class: $subclassing");
                    continue;
                }
                $plugin = new $class($this);
                if ($plugin::API_VERSION < \SmallSmallRSS\PluginHost::API_VERSION) {
                    user_error(
                        "Plugin $class is not compatible with current API version (need: "
                        . \SmallSmallRSS\PluginHost::API_VERSION
                        . ", got: $plugin_api)",
                        E_USER_WARNING
                    );
                    continue;
                }
                $this->last_registered = $class;
                switch ($kind) {
                    case self::KIND_SYSTEM:
                        if ($plugin::IS_SYSTEM) {
                            $plugin->register();
                            $this->plugins[$class] = $plugin;
                        }
                        break;
                    case self::KIND_USER:
                        if (!$plugin::IS_SYSTEM) {
                            $plugin->register();
                            $this->plugins[$class] = $plugin;
                        }
                        break;
                    case self::KIND_ALL:
                        $plugin->register();
                        $this->plugins[$class] = $plugin;
                        break;
                }
            }
        }
    }

    // only system plugins are allowed to modify routing
    public function addHandler($handler, $method, $sender)
    {
        $handler = str_replace('-', '_', strtolower($handler));
        $method = strtolower($method);
        if ($sender::IS_SYSTEM) {
            if (!isset($this->handlers[$handler])
                || !is_array($this->handlers[$handler])) {
                $this->handlers[$handler] = array();
            }
            $this->handlers[$handler][$method] = $sender;
        }
    }

    public function deleteHandler($handler, $method, $sender)
    {
        $handler = str_replace('-', '_', strtolower($handler));
        $method = strtolower($method);
        if ($sender::IS_SYSTEM) {
            unset($this->handlers[$handler][$method]);
        }
    }

    public function lookupHandler($handler, $method)
    {
        $handler = str_replace('-', '_', strtolower($handler));
        $method = strtolower($method);

        if (!empty($this->handlers[$handler]) && is_array($this->handlers[$handler])) {
            if (isset($this->handlers[$handler]['*'])) {
                return $this->handlers[$handler]['*'];
            } else {
                return $this->handlers[$handler][$method];
            }
        }

        return false;
    }

    public function addCommand($command, $description, $sender, $suffix = '', $arghelp = '')
    {
        // turn the command from foo-bar to fooBar
        $parts = explode('-', $command);
        $command = array_shift($parts);
        foreach ($parts as $part) {
            $command .= ucfirst($part);
        }
        $this->commands[$command] = array(
            'description' => $description,
            'suffix' => $suffix,
            'arghelp' => $arghelp,
            'class' => $sender
        );
    }

    public function delCommand($command)
    {
        $command = '-' . strtolower($command);

        unset($this->commands[$command]);
    }

    public function lookupCommand($command)
    {
        $command = '-' . strtolower($command);

        if (is_array($this->commands[$command])) {
            return $this->commands[$command]['class'];
        } else {
            return false;
        }

        return false;
    }

    public function getCommands()
    {
        return $this->commands;
    }

    public function runCommands($args)
    {
        foreach ($this->getCommands() as $command => $data) {
            if (isset($args[$command])) {
                $command = str_replace('-', '', $command);
                $data['class']->$command($args);
            }
        }
    }

    public function loadData($force = false)
    {
        if ($this->owner_uid) {
            $result = \SmallSmallRSS\Database::query(
                "SELECT name, content FROM ttrss_plugin_storage
                 WHERE owner_uid = '".$this->owner_uid."'"
            );

            while ($line = \SmallSmallRSS\Database::fetch_assoc($result)) {
                $this->storage[$line['name']] = unserialize($line['content']);
            }
        }
    }

    private function saveData($plugin)
    {
        if ($this->owner_uid) {
            $plugin = \SmallSmallRSS\Database::escape_string($plugin);

            \SmallSmallRSS\Database::query('BEGIN');

            $result = \SmallSmallRSS\Database::query(
                "SELECT id FROM ttrss_plugin_storage WHERE
                owner_uid= '".$this->owner_uid."' AND name = '$plugin'"
            );

            if (!isset($this->storage[$plugin])) {
                $this->storage[$plugin] = array();
            }

            $content = \SmallSmallRSS\Database::escape_string(
                serialize($this->storage[$plugin]),
                false
            );

            if (\SmallSmallRSS\Database::num_rows($result) != 0) {
                \SmallSmallRSS\Database::query(
                    "UPDATE ttrss_plugin_storage SET content = '$content'
                    WHERE owner_uid= '".$this->owner_uid."' AND name = '$plugin'"
                );

            } else {
                \SmallSmallRSS\Database::query(
                    "INSERT INTO ttrss_plugin_storage
                    (name,owner_uid,content) VALUES
                    ('$plugin','".$this->owner_uid."','$content')"
                );
            }

            \SmallSmallRSS\Database::query('COMMIT');
        }
    }

    public function set($sender, $name, $value, $sync = true)
    {
        $idx = get_class($sender);

        if (!isset($this->storage[$idx])) {
            $this->storage[$idx] = array();
        }

        $this->storage[$idx][$name] = $value;

        if ($sync) {
            $this->saveData(get_class($sender));
        }
    }

    public function get($sender, $name, $default_value = false)
    {
        $idx = get_class($sender);

        if (isset($this->storage[$idx][$name])) {
            return $this->storage[$idx][$name];
        } else {
            return $default_value;
        }
    }

    public function getAll($sender)
    {
        $idx = get_class($sender);
        if (isset($this->storage[$idx])) {
            return $this->storage[$idx];
        }
        return null;
    }

    public function clearData($sender)
    {
        if ($this->owner_uid) {
            $idx = get_class($sender);

            unset($this->storage[$idx]);

            \SmallSmallRSS\Database::query(
                "DELETE FROM ttrss_plugin_storage WHERE name = '$idx'
                AND owner_uid = " . $this->owner_uid
            );
        }
    }

    // Plugin feed functions are *EXPERIMENTAL*!
    // cat_id: only -1 is supported (Special)
    public function addFeed($cat_id, $title, $icon, $sender)
    {
        if (!isset($this->feeds[$cat_id])) {
            $this->feeds[$cat_id] = array();
        }

        $id = count($this->feeds[$cat_id]);

        array_push(
            $this->feeds[$cat_id],
            array('id' => $id, 'title' => $title, 'sender' => $sender, 'icon' => $icon)
        );

        return $id;
    }

    public function getFeeds($cat_id)
    {
        if (!isset($this->feeds[$cat_id])) {
            $this->feeds[$cat_id] = array();
        }
        return $this->feeds[$cat_id];
    }

    // convert feed_id (e.g. -129) to pfeed_id first
    public function getFeedHandler($pfeed_id)
    {
        foreach ($this->feeds as $cat) {
            foreach ($cat as $feed) {
                if ($feed['id'] == $pfeed_id) {
                    return $feed['sender'];
                }
            }
        }
    }

    public static function pfeedToFeedId($label)
    {
        return \SmallSmallRSS\Constants::PLUGIN_FEED_BASE_INDEX - 1 - abs($label);
    }

    public static function feedToPfeedId($feed)
    {
        return \SmallSmallRSS\Constants::PLUGIN_FEED_BASE_INDEX - 1 + abs($feed);
    }

    public function addApiMethod($name, $sender)
    {
        if ($sender::IS_SYSTEM) {
            $this->api_methods[strtolower($name)] = $sender;
        }
    }

    public function getApiMethod($name)
    {
        $name = strtolower($name);
        if (isset($this->api_methods[$name])) {
            return $this->api_methods[$name];
        }
        return null;
    }

    public static function initAll()
    {
        self::getInstance()->load(\SmallSmallRSS\Config::get('PLUGINS'), self::KIND_ALL);
        return true;
    }
    public function initUser($owner_uid)
    {
        $user_plugins = \SmallSmallRSS\DBPrefs::read('_ENABLED_PLUGINS', $owner_uid);
        $this->load($user_plugins, self::KIND_USER, $owner_uid);
        return true;
    }
    public static function loadUserPlugins($owner_uid)
    {
        if (!$owner_uid) {
            return;
        }
        $plugins = \SmallSmallRSS\DBPrefs::read('_ENABLED_PLUGINS', $owner_uid);
        \SmallSmallRSS\PluginHost::getInstance()->load(
            $plugins,
            \SmallSmallRSS\PluginHost::KIND_USER,
            $owner_uid
        );
        if (\SmallSmallRSS\Sanity::getSchemaVersion() > 100) {
            \SmallSmallRSS\PluginHost::getInstance()->loadData();
        }
    }
}
