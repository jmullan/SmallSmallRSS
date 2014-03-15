<?php

class Mark_Button extends \SmallSmallRSS\Plugin
{
    const API_VERSION = 2;
    const VERSION = 1.0;
    const NAME = 'Star Articles';
    const DESCRIPTION = 'Bottom un/star button for the combined mode';
    const AUTHOR = 'fox';
    const IS_SYSTEM = false;

    public static $provides = array(
        \SmallSmallRSS\Hooks::RENDER_ARTICLE_BUTTON
    );

    public function hookRenderArticleButton($line)
    {
        $id = $line["id"];
        if (\SmallSmallRSS\DBPrefs::read("COMBINED_DISPLAY_MODE")) {
            if (\SmallSmallRSS\Database::fromSQLBool($line["marked"])) {
                $image = 'images/mark_set.svg';
                $action = __('Star article');
            } else {
                $image = 'images/mark_unset.svg';
                $action = __('Unstar article');
            }
            echo "<img src=\"$image\" class=\"markedPic\" alt=\"$action\" onclick=\"toggleMark($id)\">";
        }
    }
}
