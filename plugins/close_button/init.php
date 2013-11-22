<?php
class Close_Button extends \SmallSmallRSS\Plugin {
    private $host;

    const API_VERSION = 2;
    const VERSION = 1.0;
    const NAME = 'Article Panel Close Button';
    const DESCRIPTION = 'Adds a button to close article panel';
    const AUTHOR = 'fox';
    const IS_SYSTEM = false;

    public static $provides = array(
        \SmallSmallRSS\PluginHost::HOOK_ARTICLE_BUTTON
    );

    function hookArticleButton($line)
    {
        if (!\SmallSmallRSS\DBPrefs::read("COMBINED_DISPLAY_MODE")) {
            $rv = "<img src=\"plugins/close_button/button.png\"
                    class='tagsPic' style=\"cursor: pointer\"
                    onclick=\"closeArticlePanel()\"
                    title='".__('Close article')."'>";
        }
        return $rv;
    }
}
