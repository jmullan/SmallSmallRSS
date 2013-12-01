<?php

class Share extends \SmallSmallRSS\Plugin
{
    private $host;

    const API_VERSION = 2;
    const VERSION = 1.0;
    const NAME = 'Share article by unique URL';
    const DESCRIPTION = '';
    const AUTHOR = 'fox';
    const IS_SYSTEM = false;

    public static $provides = array(
        \SmallSmallRSS\Hooks::ARTICLE_BUTTON
    );

    public function getJavascript()
    {
        return file_get_contents(dirname(__FILE__) . "/share.js");
    }

    public function hookArticleButton($line)
    {
        return "<img src=\"plugins/share/share.png\"
            class='tagsPic' style=\"cursor : pointer\"
            onclick=\"shareArticle(".$line['int_id'].")\"
            title='".__('Share by URL')."'>";
    }

    public function shareArticle()
    {
        $param = \SmallSmallRSS\Database::escape_string($_REQUEST['param']);
        $result = \SmallSmallRSS\Database::query(
            "SELECT uuid, ref_id
             FROM ttrss_user_entries
             WHERE int_id = '$param' AND owner_uid = " . $_SESSION['uid']
        );
        if (\SmallSmallRSS\Database::num_rows($result) == 0) {
            print "Article not found.";
        } else {
            $uuid = \SmallSmallRSS\Database::fetch_result($result, 0, "uuid");
            $ref_id = \SmallSmallRSS\Database::fetch_result($result, 0, "ref_id");

            if (!$uuid) {
                $uuid = \SmallSmallRSS\Database::escape_string(sha1(uniqid(rand(), true)));
                \SmallSmallRSS\Database::query(
                    "UPDATE ttrss_user_entries
                     SET uuid = '$uuid'
                     WHERE
                         int_id = '$param'
                         AND owner_uid = " . $_SESSION['uid']
                );
            }
            print "<h2>". __("You can share this article by the following unique URL:") . "</h2>";
            $url_path = get_self_url_prefix();
            $url_path .= "/public.php?op=share&key=$uuid";
            print "<div class=\"tagCloudContainer\">";
            print "<a id='pub_opml_url' href='$url_path' target='_blank'>$url_path</a>";
            print "</div>";
        }
        print "<div align='center'>";
        print "<button data-dojo-type=\"dijit.form.Button\" onclick=\"return dijit.byId('shareArticleDlg').hide()\">";
        print __('Close this window');
        print "</button>";
        print "</div>";
    }
}
