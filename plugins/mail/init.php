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
        \SmallSmallRSS\Hooks::RENDER_ARTICLE_BUTTON
    );

    public function getJavascript()
    {
        return file_get_contents(dirname(__FILE__) . "/mail.js");
    }

    public function hookRenderArticleButton($line)
    {
        echo "<img src=\"plugins/mail/mail.png\" class=\"tagsPic\" style=\"cursor: pointer\"";
        echo " onclick=\"emailArticle(";
        echo $line["id"];
        echo ")\"";
        echo " alt=\"Zoom\" title='";
        echo __('Forward by email');
        echo "'>";
    }

    public function emailArticle()
    {
        $param = \SmallSmallRSS\Database::escape_string($_REQUEST['param']);
        echo "<input data-dojo-type=\"dijit.form.TextBox\" style=\"display: none\"";
        echo " name=\"op\" value=\"pluginhandler\">";
        echo "<input data-dojo-type=\"dijit.form.TextBox\" style=\"display: none\"";
        echo " name=\"plugin\" value=\"mail\">";
        echo "<input data-dojo-type=\"dijit.form.TextBox\" style=\"display: none\"";
        echo " name=\"method\" value=\"sendEmail\">";
        $result = \SmallSmallRSS\Database::query(
            "SELECT email, full_name FROM ttrss_users WHERE id = " . $_SESSION["uid"]
        );
        $user_email = htmlspecialchars(\SmallSmallRSS\Database::fetch_result($result, 0, "email"));
        $user_name = htmlspecialchars(\SmallSmallRSS\Database::fetch_result($result, 0, "full_name"));
        if (!$user_name) {
            $user_name = $_SESSION['name'];
        }
        echo "<input data-dojo-type=\"dijit.form.TextBox\" style=\"display: none\"";
        echo " name=\"from_email\" value=\"$user_email\">";
        echo "<input data-dojo-type=\"dijit.form.TextBox\" style=\"display: none\"";
        echo " name=\"from_name\" value=\"$user_name\">";
        $tpl = new \MiniTemplator\Engine();
        $tpl_t = new \MiniTemplator\Engine();
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
        echo "<table width=\"100%\">";
        echo "<tr>";
        echo "<td>";
        echo __('From:');
        echo "</td><td>";
        echo "<input data-dojo-type=\"dijit.form.TextBox\" disabled=\"1\" style=\"width: 30em;\"";
        echo " value=\"$user_name &lt;$user_email&gt;\">";
        echo "</td></tr><tr><td>";
        echo __('To:');
        echo "</td><td>";
        echo "<input data-dojo-type=\"dijit.form.ValidationTextBox\" required=\"true\"";
        echo " style=\"width: 30em;\" name=\"destination\" id=\"emailArticleDlg_destination\">";
        echo "<div class=\"autocomplete\" id=\"emailArticleDlg_dst_choices\" style=\"z-index: 30; display: none\">";
        echo "</div>";
        echo "</td></tr><tr><td>";
        echo __('Subject:');
        echo "</td><td>";
        echo "<input data-dojo-type=\"dijit.form.ValidationTextBox\" required=\"true\"
                style=\"width: 30em;\"
                name=\"subject\" value=\"$subject\" id=\"subject\">";
        echo "</td></tr>";
        echo "<tr>";
        echo "<td colspan='2'>";
        echo "<textarea data-dojo-type=\"dijit.form.SimpleTextarea\"";
        echo " style=\"font-size: 12px; width: 100%\" rows=\"20\" name='content'>";
        echo $content;
        echo "</textarea>";
        echo "</td>";
        echo "</tr>";
        echo "</table>";
        echo "<div class='dlgButtons'>";
        echo "<button data-dojo-type=\"dijit.form.Button\" onclick=\"dijit.byId('emailArticleDlg').execute()\">";
        echo __('Send e-mail');
        echo "</button> ";
        echo "<button data-dojo-type=\"dijit.form.Button\" onclick=\"dijit.byId('emailArticleDlg').hide()\">";
        echo __('Cancel');
        echo "</button>";
        echo "</div>";
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
            foreach ($addresses as $address) {
                save_email_address(\SmallSmallRSS\Database::escape_string($address));
            }
            $reply['message'] = "UPDATE_COUNTERS";
        }
        echo json_encode($reply);
    }

    public function completeEmails()
    {
        $search = \SmallSmallRSS\Database::escape_string($_REQUEST["search"]);
        echo "<ul>";
        foreach ($_SESSION['stored_emails'] as $email) {
            if (strpos($email, $search) !== false) {
                echo "<li>$email</li>";
            }
        }
        echo "</ul>";
    }
}
