<?php
namespace SmallSmallRSS;

abstract class Plugin
{
    private $dbh;
    private $host;

    const API_VERSION_COMPAT = 1;
    const API_VERSION = 2;

    const VERSION = 1.0;
    const NAME = 'plugin';
    const DESCRIPTION = 'No Description';
    const AUTHOR = 'No Author';
    const IS_SYSTEM = false;

    public static $provides = array();

    public function __construct($pluginhost)
    {
        $this->host = $pluginhost;
    }

    public function register()
    {
        foreach (static::$provides as $hook) {
            $this->host->add_hook($hook, $this);
        }
        $this->addCommands();
    }

    public function addCommands()
    {
    }

    public function about()
    {
        return array(
            static::VERSION,
            static::NAME,
            static::AUTHOR,
            static::DESCRIPTION,
            static::IS_SYSTEM
        );
    }

    public function getJavascript()
    {
        return '';
    }

    public function getPreferencesJavascript()
    {
        return '';
    }

    public function getCSS()
    {
        return '';
    }
}
