<?php
namespace SmallSmallRSS\Handlers;
class PluginHandler extends ProtectedHandler {
    function csrf_ignore($method) {
        return true;
    }

    function catchall($method) {
        $plugin = PluginHost::getInstance()->get_plugin($_REQUEST["plugin"]);

        if ($plugin) {
            if (method_exists($plugin, $method)) {
                $plugin->$method();
            } else {
                print json_encode(array("error" => "METHOD_NOT_FOUND"));
            }
        } else {
            print json_encode(array("error" => "PLUGIN_NOT_FOUND"));
        }
    }
}
