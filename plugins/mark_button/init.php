<?php

class Mark_Button extends \SmallSmallRSS\Plugin {
    private $host;
    const API_VERSION = 2;
    const VERSION = 1.0;
    const NAME = 'Star Articles';
    const DESCRIPTION = 'Bottom un/star button for the combined mode';
    const AUTHOR = 'fox';
    const IS_SYSTEM = false;

    public static $provides = array(
        \SmallSmallRSS\PluginHost::HOOK_ARTICLE_BUTTON
    );

    public function hookArticleButton($line)
    {
        $marked_pic = "";
        $id = $line["id"];

        if (\SmallSmallRSS\DBPrefs::read("COMBINED_DISPLAY_MODE")) {
            if (sql_bool_to_bool($line["marked"])) {
                $image = 'images/mark_set.svg';
                $action = __('Star article');
            } else {
                $image = 'images/mark_unset.svg';
                $action = __('Unstar article');
            }
            $marked_pic = "<img src=\"$image\" class=\"markedPic\" alt=\"$action\" onclick='toggleMark($id)'>";
        }
        return $marked_pic;
    }
}
