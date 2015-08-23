<?php

class Share extends \SmallSmallRSS\Plugin
{
    const API_VERSION = 2;
    const VERSION = 1.0;
    const NAME = 'Share article by unique URL';
    const DESCRIPTION = '';
    const AUTHOR = 'fox';
    const IS_SYSTEM = false;

    public static $provides = array(
        \SmallSmallRSS\Hooks::RENDER_ARTICLE_BUTTON
    );

    public function getJavascript()
    {
        return file_get_contents(dirname(__FILE__) . "/share.js");
    }

    public function hookRenderArticleButton($line)
    {
        echo "<img src=\"plugins/share/share.png\" class=\"tagsPic\" style=\"cursor: pointer\"";
        echo " onclick=\"shareArticle(";
        echo $line['int_id'];
        echo ")\"";
        echo " title=\"";
        echo __('Share by URL');
        echo "\">";
    }

    public function shareArticle()
    {
        $param = \SmallSmallRSS\Database::escapeString($_REQUEST['param']);
        $result = \SmallSmallRSS\Database::query(
            "SELECT uuid, ref_id
             FROM ttrss_user_entries
             WHERE int_id = '$param' AND owner_uid = " . $_SESSION['uid']
        );
        if (\SmallSmallRSS\Database::numRows($result) == 0) {
            echo "Article not found.";
        } else {
            $uuid = \SmallSmallRSS\Database::fetchResult($result, 0, "uuid");
            $ref_id = \SmallSmallRSS\Database::fetchResult($result, 0, "ref_id");

            if (!$uuid) {
                $uuid = \SmallSmallRSS\Database::escapeString(sha1(uniqid(rand(), true)));
                \SmallSmallRSS\Database::query(
                    "UPDATE ttrss_user_entries
                     SET uuid = '$uuid'
                     WHERE
                         int_id = '$param'
                         AND owner_uid = " . $_SESSION['uid']
                );
            }
            echo "<h2>";
            echo __("You can share this article by the following unique URL:");
            echo "</h2>";
            $url_path = get_self_url_prefix();
            $url_path .= "/public.php?op=share&key=$uuid";
            echo "<div class=\"tagCloudContainer\">";
            echo "<a id=\"pub_opml_url\" href=\"$url_path\" target=\"_blank\">";
            echo $url_path;
            echo "</a>";
            echo "</div>";
        }
        echo "<div align='center'>";
        echo "<button data-dojo-type=\"dijit.form.Button\" onclick=\"return dijit.byId('shareArticleDlg').hide()\">";
        echo __('Close this window');
        echo "</button>";
        echo "</div>";
    }
}
