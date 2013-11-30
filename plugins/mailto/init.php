<?php

class MailTo extends \SmallSmallRSS\Plugin
{
    private $host;

    const API_VERSION = 2;
    const VERSION = 1.0;
    const NAME = 'Mail Articles';
    const DESCRIPTION = 'Share article via email (using mailto: links, invoking your mail client)';
    const AUTHOR = 'fox';
    const IS_SYSTEM = false;

    public static $provides = array(
        \SmallSmallRSS\Hooks::ARTICLE_BUTTON
    );

    public function getJavascript()
    {
        return file_get_contents(dirname(__FILE__) . "/init.js");
    }

    public function hookArticleButton($line)
    {
        return "<img src=\"plugins/mailto/mail.png\"
                    class='tagsPic' style=\"cursor : pointer\"
                    onclick=\"mailtoArticle(".$line["id"].")\"
                    alt='Zoom' title='".__('Forward by email')."'>";
    }

    public function emailArticle()
    {
        $param = \SmallSmallRSS\Database::escape_string($_REQUEST['param']);
        $tpl = new \MiniTemplator\Engine();
        $tpl_t = new \MiniTemplator\Engine();
        $tpl->readTemplateFromFile("templates/email_article_template.txt");
        $tpl->setVariable('USER_NAME', $_SESSION["name"], true);
        $tpl->setVariable('USER_EMAIL', $user_email, true);
        $tpl->setVariable('TTRSS_HOST', $_SERVER["HTTP_HOST"], true);
        $result = \SmallSmallRSS\Database::query(
            "SELECT link, content, title
             FROM ttrss_user_entries, ttrss_entries
             WHERE
                 id = ref_id
                 AND id IN ($param)
                 AND owner_uid = " . $_SESSION["uid"]
        );
        if (\SmallSmallRSS\Database::num_rows($result) > 1) {
            $subject = __("[Forwarded]") . " " . __("Multiple articles");
        }
        while ($line = \SmallSmallRSS\Database::fetch_assoc($result)) {
            if (!$subject) {
                $subject = __("[Forwarded]") . " " . htmlspecialchars($line["title"]);
            }
            $tpl->setVariable('ARTICLE_TITLE', strip_tags($line["title"]));
            $tpl->setVariable('ARTICLE_URL', strip_tags($line["link"]));
            $tpl->addBlock('article');
        }
        $tpl->addBlock('email');
        $content = "";
        $tpl->generateOutputToString($content);
        $mailto_link = htmlspecialchars(
            "mailto:?subject=" . rawurlencode($subject)
            . "&body=" . rawurlencode($content)
        );
        print __("Clicking the following link to invoke your mail client:");
        print "<div class=\"tagCloudContainer\">";
        print "<a target=\"_blank\" href=\"$mailto_link\">";
        print __("Forward selected article(s) by email.");
        print "</a>";
        print "</div>";
        print __("You should be able to edit the message before sending in your mail client.");
        print "<p>";
        print "<div style='text-align : center'>";
        print "<button dojoType=\"dijit.form.Button\" onclick=\"dijit.byId('emailArticleDlg').hide()\">";
        print __('Close this dialog');
        print "</button>";
        print "</div>";
    }
}
