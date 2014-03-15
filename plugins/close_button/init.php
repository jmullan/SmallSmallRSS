<?php
class Close_Button extends \SmallSmallRSS\Plugin
{
    const API_VERSION = 2;
    const VERSION = 1.0;
    const NAME = 'Article Panel Close Button';
    const DESCRIPTION = 'Adds a button to close article panel';
    const AUTHOR = 'fox';
    const IS_SYSTEM = false;

    public static $provides = array(
        \SmallSmallRSS\Hooks::RENDER_ARTICLE_BUTTON
    );

    public function hookRenderArticleButton($line)
    {
        if (!\SmallSmallRSS\DBPrefs::read("COMBINED_DISPLAY_MODE")) {
            echo "<img src=\"plugins/close_button/button.png\"";
            echo " class=\"tagsPic\" style=\"cursor: pointer\"";
            echo " onclick=\"closeArticlePanel()\"";
            echo " title='";
            echo __('Close article');
            echo "' />";
        }
    }
}
