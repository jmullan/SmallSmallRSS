<?php
namespace SmallSmallRSS\Handlers;

class pluginhandler extends ProtectedHandler
{
    public function ignoreCSRF($method)
    {
        return true;
    }

    public function catchall($method)
    {
        $plugin = \SmallSmallRSS\PluginHost::getInstance()->getPlugin($_REQUEST['plugin']);

        if ($plugin) {
            if (method_exists($plugin, $method)) {
                $plugin->$method();
            } else {
                print json_encode(array('error' => 'METHOD_NOT_FOUND'));
            }
        } else {
            print json_encode(array('error' => 'PLUGIN_NOT_FOUND'));
        }
    }
}
