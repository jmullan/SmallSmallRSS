<?php

class GoogleReaderKeys extends \SmallSmallRSS\Plugin
{
    private $host;

    const API_VERSION = 2;
    const VERSION = 1.0;
    const NAME = 'Google Reader Hotkeys';
    const DESCRIPTION = 'Keyboard hotkeys to emulate Google Reader';
    const AUTHOR = 'markwaters';
    const IS_SYSTEM = false;

    public static $provides = array(
        \SmallSmallRSS\PluginHost::HOOK_HOTKEY_MAP
    );

    public function hookHotkeyMap($hotkeys)
    {
        $hotkeys["j"] = "next_article_noscroll";
        $hotkeys["k"] = "prev_article_noscroll";
        $hotkeys["*n"] = "next_feed";
        $hotkeys["*p"] = "prev_feed";
        $hotkeys["v"] = "open_in_new_window";
        $hotkeys["r"] = "feed_refresh";
        $hotkeys["m"] = "toggle_unread";
        $hotkeys["o"] = "toggle_expand";
        $hotkeys["(13)|enter"] = "toggle_expand";
        $hotkeys["*(191)|?"] = "help_dialog";
        $hotkeys["(32)|space"] = "next_article";
        $hotkeys["(38)|up"] = "article_scroll_up";
        $hotkeys["(40)|down"] = "article_scroll_down";
        return $hotkeys;
    }
}
