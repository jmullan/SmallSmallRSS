<?php
class Mail extends \SmallSmallRSS\Plugin
{
    private $host;
    const API_VERSION = 2;
    const VERSION = 1.0;
    const NAME = 'Mail Article';
    const DESCRIPTION = 'Share article via email';
    const AUTHOR = 'fox';
    const IS_SYSTEM = false;

    public static $provides = array(
        \SmallSmallRSS\PluginHost::HOOK_ARTICLE_BUTTON
    );

    public function getJavascript()
    {
        return file_get_contents(dirname(__FILE__) . "/mail.js");
    }

    public function hookArticleButton($line)
    {
        return "<img src=\"plugins/mail/mail.png\"
                    class='tagsPic' style=\"cursor : pointer\"
                    onclick=\"emailArticle(".$line["id"].")\"
                    alt='Zoom' title='".__('Forward by email')."'>";
    }

    public function emailArticle()
    {
        $param = \SmallSmallRSS\Database::escape_string($_REQUEST['param']);
        print "<input dojoType=\"dijit.form.TextBox\" style=\"display: none\" name=\"op\" value=\"pluginhandler\">";
        print "<input dojoType=\"dijit.form.TextBox\" style=\"display: none\" name=\"plugin\" value=\"mail\">";
        print "<input dojoType=\"dijit.form.TextBox\" style=\"display: none\" name=\"method\" value=\"sendEmail\">";
        $result = \SmallSmallRSS\Database::query(
            "SELECT email, full_name FROM ttrss_users WHERE id = " . $_SESSION["uid"]
        );
        $user_email = htmlspecialchars(\SmallSmallRSS\Database::fetch_result($result, 0, "email"));
        $user_name = htmlspecialchars(\SmallSmallRSS\Database::fetch_result($result, 0, "full_name"));
        if (!$user_name) {
            $user_name = $_SESSION['name'];
        }
        print "<input dojoType=\"dijit.form.TextBox\" style=\"display: none\" name=\"from_email\" value=\"$user_email\">";
        print "<input dojoType=\"dijit.form.TextBox\" style=\"display: none\" name=\"from_name\" value=\"$user_name\">";
        require_once "lib/MiniTemplator.class.php";
        $tpl = new MiniTemplator;
        $tpl_t = new MiniTemplator;
        $tpl->readTemplateFromFile("templates/email_article_template.txt");
        $tpl->setVariable('USER_NAME', $_SESSION["name"], true);
        $tpl->setVariable('USER_EMAIL', $user_email, true);
        $tpl->setVariable('TTRSS_HOST', $_SERVER["HTTP_HOST"], true);
        $result = \SmallSmallRSS\Database::query(
            "SELECT link, content, title, note
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
            $tnote = strip_tags($line["note"]);
            if ($tnote != '') {
                $tpl->setVariable('ARTICLE_NOTE', $tnote, true);
                $tpl->addBlock('note');
            }
            $tpl->setVariable('ARTICLE_URL', strip_tags($line["link"]));
            $tpl->addBlock('article');
        }
        $tpl->addBlock('email');
        $content = "";
        $tpl->generateOutputToString($content);
        print "<table width='100%'><tr><td>";
        print __('From:');
        print "</td><td>";
        print "<input dojoType=\"dijit.form.TextBox\" disabled=\"1\" style=\"width: 30em;\"
                value=\"$user_name <$user_email>\">";
        print "</td></tr><tr><td>";
        print __('To:');
        print "</td><td>";
        print "<input dojoType=\"dijit.form.ValidationTextBox\" required=\"true\"
                style=\"width: 30em;\"
                name=\"destination\" id=\"emailArticleDlg_destination\">";
        print "<div class=\"autocomplete\" id=\"emailArticleDlg_dst_choices\"
                style=\"z-index: 30; display: none\"></div>";
        print "</td></tr><tr><td>";
        print __('Subject:');
        print "</td><td>";
        print "<input dojoType=\"dijit.form.ValidationTextBox\" required=\"true\"
                style=\"width: 30em;\"
                name=\"subject\" value=\"$subject\" id=\"subject\">";
        print "</td></tr>";
        print "<tr><td colspan='2'><textarea dojoType=\"dijit.form.SimpleTextarea\" style='font-size: 12px; width: 100%' rows=\"20\"
            name='content'>$content</textarea>";
        print "</td></tr></table>";
        print "<div class='dlgButtons'>";
        print "<button dojoType=\"dijit.form.Button\" onclick=\"dijit.byId('emailArticleDlg').execute()\">".__('Send e-mail')."</button> ";
        print "<button dojoType=\"dijit.form.Button\" onclick=\"dijit.byId('emailArticleDlg').hide()\">".__('Cancel')."</button>";
        print "</div>";
    }

    public function sendEmail()
    {
        $reply = array();

        $mail = new \SmallSmallRSS\Mailer();

        $mail->From = strip_tags($_REQUEST['from_email']);
        $mail->FromName = strip_tags($_REQUEST['from_name']);
        //$mail->AddAddress($_REQUEST['destination']);
        $addresses = explode(';', $_REQUEST['destination']);
        foreach ($addresses as $nextaddr) {
            $mail->AddAddress($nextaddr);
        }
        $mail->IsHTML(false);
        $mail->Subject = $_REQUEST['subject'];
        $mail->Body = $_REQUEST['content'];

        $rc = $mail->Send();

        if (!$rc) {
            $reply['error'] = $mail->ErrorInfo;
        } else {
            save_email_address(\SmallSmallRSS\Database::escape_string($destination));
            $reply['message'] = "UPDATE_COUNTERS";
        }

        print json_encode($reply);
    }

    public function completeEmails()
    {
        $search = \SmallSmallRSS\Database::escape_string($_REQUEST["search"]);
        print "<ul>";
        foreach ($_SESSION['stored_emails'] as $email) {
            if (strpos($email, $search) !== false) {
                print "<li>$email</li>";
            }
        }
        print "</ul>";
    }
}
