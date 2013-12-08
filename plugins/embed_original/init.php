<?php
class Embed_Original extends \SmallSmallRSS\Plugin
{
    private $host;

    const API_VERSION = 2;
    const VERSION = 1.0;
    const NAME = 'Original Article Scraper';
    const DESCRIPTION = 'Try to display original article content inside tt-rss';
    const AUTHOR = 'fox';
    const IS_SYSTEM = false;

    public static $provides = array(
        \SmallSmallRSS\Hooks::RENDER_ARTICLE_BUTTON
    );

    public function getJavascript()
    {
        return file_get_contents(__DIR__ . "/init.js");
    }

    public function getCSS()
    {
        return file_get_contents(__DIR__ . "/init.css");
    }

    public function hookRenderArticleButton($line)
    {
        $id = $line["id"];
        echo "<img src=\"plugins/embed_original/button.png\"";
        echo " class=\"tagsPic\" style=\"cursor : pointer\"";
        echo " onclick=\"embedOriginalArticle($id)\"";
        echo "title='";
        echo __('Toggle embed original');
        echo "'>";
    }

    public function getUrl()
    {
        $id = \SmallSmallRSS\Database::escape_string($_REQUEST['id']);
        $result = \SmallSmallRSS\Database::query(
            "SELECT link
             FROM ttrss_entries, ttrss_user_entries
             WHERE
                 id = '$id'
                 AND ref_id = id
                 AND owner_uid = " .$_SESSION['uid']
        );
        $url = "";
        if (\SmallSmallRSS\Database::num_rows($result) != 0) {
            $url = \SmallSmallRSS\Database::fetch_result($result, 0, "link");

        }
        print json_encode(array("url" => $url, "id" => $id));
    }
}
