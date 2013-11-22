<?php

class Swap_JK extends \SmallSmallRSS\Plugin
{
    private $host;

    const API_VERSION = 2;
    const VERSION = 1.0;
    const NAME = 'vi-style navigation';
    const DESCRIPTION = 'Swap j and k hotkeys (for vi brethren)';
    const AUTHOR = 'fox';
    const IS_SYSTEM = false;

    public static $provides = array(
        \SmallSmallRSS\PluginHost::HOOK_HOTKEY_MAP,
    );

    public function hook_hotkey_map($hotkeys)
    {
        $hotkeys["j"] = "next_feed";
        $hotkeys["k"] = "prev_feed";
        return $hotkeys;
    }
}
