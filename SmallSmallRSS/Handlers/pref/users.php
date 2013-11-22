<?php
namespace SmallSmallRSS\Handlers;
class Pref_Users extends ProtectedHandler
{
    function before($method)
    {
        if (parent::before($method)) {
            if ($_SESSION["access_level"] < 10) {
                print __("Your access level is insufficient to open this tab.");
                return false;
            }
            return true;
        }
        return false;
    }

    function CRSFIgnore($method)
    {
        $csrf_ignored = array("index", "edit", "userdetails");

        return array_search($method, $csrf_ignored) !== false;
    }

    function userdetails()
    {

        $uid = sprintf("%d", $_REQUEST["id"]);
        $substring_for_date = \SmallSmallRSS\Config::get('SUBSTRING_FOR_DATE');
        $result = \SmallSmallRSS\Database::query(
            "SELECT login,
                ".$substring_for_date."(last_login,1,16) AS last_login,
                access_level,
                (SELECT COUNT(int_id) FROM ttrss_user_entries
                    WHERE owner_uid = id) AS stored_articles,
                ".$substring_for_date."(created,1,16) AS created
                FROM ttrss_users
                WHERE id = '$uid'"
        );

        if (\SmallSmallRSS\Database::num_rows($result) == 0) {
            print "<h1>".__('User not found')."</h1>";
            return;
        }

        $login = \SmallSmallRSS\Database::fetch_result($result, 0, "login");
        print "<table width='100%'>";
        $last_login = make_local_datetime(
            \SmallSmallRSS\Database::fetch_result($result, 0, "last_login"), true
        );
        $created = make_local_datetime(
            \SmallSmallRSS\Database::fetch_result($result, 0, "created"), true
        );
        $access_level = \SmallSmallRSS\Database::fetch_result($result, 0, "access_level");
        $stored_articles = \SmallSmallRSS\Database::fetch_result($result, 0, "stored_articles");
        print "<tr><td>".__('Registered')."</td><td>$created</td></tr>";
        print "<tr><td>".__('Last logged in')."</td><td>$last_login</td></tr>";
        $result = \SmallSmallRSS\Database::query(
            "SELECT COUNT(id) as num_feeds FROM ttrss_feeds
                WHERE owner_uid = '$uid'"
        );
        $num_feeds = \SmallSmallRSS\Database::fetch_result($result, 0, "num_feeds");
        print "<tr><td>".__('Subscribed feeds count')."</td><td>$num_feeds</td></tr>";
        print "</table>";
        print "<h1>".__('Subscribed feeds')."</h1>";
        $result = \SmallSmallRSS\Database::query(
            "SELECT id,title,site_url FROM ttrss_feeds
                WHERE owner_uid = '$uid' ORDER BY title"
        );
        print "<ul class=\"userFeedList\">";
        while ($line = \SmallSmallRSS\Database::fetch_assoc($result)) {
            $icon_file = \SmallSmallRSS\Config::get('ICONS_URL') . "/" . $line["id"] . ".ico";
            if (file_exists($icon_file) && filesize($icon_file) > 0) {
                $feed_icon = "<img class=\"tinyFeedIcon\" src=\"$icon_file\">";
            } else {
                $feed_icon = "<img class=\"tinyFeedIcon\" src=\"images/blank_icon.gif\">";
            }
            print "<li>$feed_icon&nbsp;<a href=\"".$line["site_url"]."\">".$line["title"]."</a></li>";
        }

        if (\SmallSmallRSS\Database::num_rows($result) < $num_feeds) {
            // FIXME - add link to show ALL subscribed feeds here somewhere
            print "<li><img
                    class=\"tinyFeedIcon\" src=\"images/blank_icon.gif\">&nbsp;...</li>";
        }

        print "</ul>";

        print "<div align='center'>
                <button dojoType=\"dijit.form.Button\" type=\"submit\">".__("Close this window").
            "</button></div>";

        return;
    }

    function edit()
    {
        $access_level_names = \SmallSmallRSS\Constants::access_level_names();

        $id = \SmallSmallRSS\Database::escape_string($_REQUEST["id"]);
        print "<form id=\"user_edit_form\" onsubmit='return false' dojoType=\"dijit.form.Form\">";

        print "<input dojoType=\"dijit.form.TextBox\" style=\"display : none\" name=\"id\" value=\"$id\">";
        print "<input dojoType=\"dijit.form.TextBox\" style=\"display : none\" name=\"op\" value=\"pref-users\">";
        print "<input dojoType=\"dijit.form.TextBox\" style=\"display : none\" name=\"method\" value=\"editSave\">";

        $result = \SmallSmallRSS\Database::query("SELECT * FROM ttrss_users WHERE id = '$id'");

        $login = \SmallSmallRSS\Database::fetch_result($result, 0, "login");
        $access_level = \SmallSmallRSS\Database::fetch_result($result, 0, "access_level");
        $email = \SmallSmallRSS\Database::fetch_result($result, 0, "email");

        $sel_disabled = ($id == $_SESSION["uid"]) ? "disabled" : "";

        print "<div class=\"dlgSec\">".__("User")."</div>";
        print "<div class=\"dlgSecCont\">";

        if ($sel_disabled) {
            print "<input dojoType=\"dijit.form.TextBox\" style=\"display : none\" name=\"login\" value=\"$login\">";
        }

        print "<input size=\"30\" style=\"font-size : 16px\"
                dojoType=\"dijit.form.ValidationTextBox\" required=\"1\"
                onkeypress=\"return filterCR(event, userEditSave)\" $sel_disabled
                name=\"login\" value=\"$login\">";

        print "</div>";

        print "<div class=\"dlgSec\">".__("Authentication")."</div>";
        print "<div class=\"dlgSecCont\">";

        print __('Access level: ') . " ";

        $form_elements_renderer = new \SmallSmallRSS\Renderers\FormElements();
        if (!$sel_disabled) {
            $form_elements_renderer->renderSelect(
                "access_level", $access_level, $access_level_names,
                "dojoType=\"dijit.form.Select\" $sel_disabled"
            );
        } else {
            $form_elements_renderer->renderSelect(
                "", $access_level, $access_level_names,
                "dojoType=\"dijit.form.Select\" $sel_disabled"
            );
            print "<input dojoType=\"dijit.form.TextBox\" style=\"display : none\" name=\"access_level\" value=\"$access_level\">";
        }

        print "<hr/>";

        print "<input dojoType=\"dijit.form.TextBox\" type=\"password\" size=\"20\" onkeypress=\"return filterCR(event, userEditSave)\" placeholder=\"Change password\"
                name=\"password\">";

        print "</div>";

        print "<div class=\"dlgSec\">".__("Options")."</div>";
        print "<div class=\"dlgSecCont\">";

        print "<input dojoType=\"dijit.form.TextBox\" size=\"30\" name=\"email\" onkeypress=\"return filterCR(event, userEditSave)\" placeholder=\"E-mail\"
                value=\"$email\">";

        print "</div>";

        print "</table>";

        print "</form>";

        print "<div class=\"dlgButtons\">
                <button dojoType=\"dijit.form.Button\" type=\"submit\">".
            __('Save')."</button>
                <button dojoType=\"dijit.form.Button\" onclick=\"dijit.byId('userEditDlg').hide()\">".
            __('Cancel')."</button></div>";

        return;
    }

    function editSave()
    {
        $login = \SmallSmallRSS\Database::escape_string(trim($_REQUEST["login"]));
        $uid = \SmallSmallRSS\Database::escape_string($_REQUEST["id"]);
        $access_level = (int) $_REQUEST["access_level"];
        $email = \SmallSmallRSS\Database::escape_string(trim($_REQUEST["email"]));
        $password = $_REQUEST["password"];

        if ($password) {
            $salt = substr(bin2hex(get_random_bytes(125)), 0, 250);
            $pwd_hash = encrypt_password($password, $salt, true);
            $pass_query_part = "pwd_hash = '$pwd_hash', salt = '$salt',";
        } else {
            $pass_query_part = "";
        }

        \SmallSmallRSS\Database::query(
            "UPDATE ttrss_users SET $pass_query_part login = '$login',
                access_level = '$access_level', email = '$email', otp_enabled = false
                WHERE id = '$uid'"
        );

    }

    function remove()
    {
        $ids = explode(",", \SmallSmallRSS\Database::escape_string($_REQUEST["ids"]));

        foreach ($ids as $id) {
            if ($id != $_SESSION["uid"] && $id != 1) {
                \SmallSmallRSS\Database::query("DELETE FROM ttrss_tags WHERE owner_uid = '$id'");
                \SmallSmallRSS\Database::query("DELETE FROM ttrss_feeds WHERE owner_uid = '$id'");
                \SmallSmallRSS\Database::query("DELETE FROM ttrss_users WHERE id = '$id'");
            }
        }
    }

    function add()
    {

        $login = \SmallSmallRSS\Database::escape_string(trim($_REQUEST["login"]));
        $tmp_user_pwd = make_password(8);
        $salt = substr(bin2hex(get_random_bytes(125)), 0, 250);
        $pwd_hash = encrypt_password($tmp_user_pwd, $salt, true);

        $result = \SmallSmallRSS\Database::query(
            "SELECT id FROM ttrss_users WHERE
                login = '$login'"
        );

        if (\SmallSmallRSS\Database::num_rows($result) == 0) {

            \SmallSmallRSS\Database::query(
                "INSERT INTO ttrss_users
                    (login,pwd_hash,access_level,last_login,created, salt)
                    VALUES ('$login', '$pwd_hash', 0, null, NOW(), '$salt')"
            );


            $result = \SmallSmallRSS\Database::query(
                "SELECT id FROM ttrss_users WHERE
                    login = '$login' AND pwd_hash = '$pwd_hash'"
            );

            if (\SmallSmallRSS\Database::num_rows($result) == 1) {

                $new_uid = \SmallSmallRSS\Database::fetch_result($result, 0, "id");

                \SmallSmallRSS\Renderers\Messages::renderNotice(
                    T_sprintf("Added user <b>%s</b> with password <b>%s</b>", $login, $tmp_user_pwd)
                );

                initialize_user($new_uid);

            } else {

                \SmallSmallRSS\Renderers\Messages::renderWarning(
                    T_sprintf("Could not create user <b>%s</b>", $login)
                );

            }
        } else {
            \SmallSmallRSS\Renderers\Messages::renderWarning(
                T_sprintf("User <b>%s</b> already exists.", $login)
            );
        }
    }

    static function resetUserPassword($uid, $show_password)
    {

        $result = \SmallSmallRSS\Database::query(
            "SELECT login,email
                FROM ttrss_users WHERE id = '$uid'"
        );

        $login = \SmallSmallRSS\Database::fetch_result($result, 0, "login");
        $email = \SmallSmallRSS\Database::fetch_result($result, 0, "email");
        $salt = \SmallSmallRSS\Database::fetch_result($result, 0, "salt");

        $new_salt = substr(bin2hex(get_random_bytes(125)), 0, 250);
        $tmp_user_pwd = make_password(8);

        $pwd_hash = encrypt_password($tmp_user_pwd, $new_salt, true);

        \SmallSmallRSS\Database::query(
            "UPDATE ttrss_users SET pwd_hash = '$pwd_hash', salt = '$new_salt'
                WHERE id = '$uid'"
        );

        if ($show_password) {
            print T_sprintf("Changed password of user <b>%s</b> to <b>%s</b>", $login, $tmp_user_pwd);
        } else {
            \SmallSmallRSS\Renderers\Messages::renderNotice(
                T_sprintf("Sending new password of user <b>%s</b> to <b>%s</b>", $login, $email)
            );
        }

        if ($email) {
            require_once "lib/MiniTemplator.class.php";

            $tpl = new MiniTemplator;

            $tpl->readTemplateFromFile("templates/resetpass_template.txt");

            $tpl->setVariable('LOGIN', $login);
            $tpl->setVariable('NEWPASS', $tmp_user_pwd);

            $tpl->addBlock('message');

            $message = "";

            $tpl->generateOutputToString($message);

            $mail = new \SmallSmallRSS\Mailer();

            $rc = $mail->quickMail(
                $email, $login,
                __("[tt-rss] Password change notification"),
                $message, false
            );

            if (!$rc) {
                \SmallSmallRSS\Renderers\Messages::renderError($mail->ErrorInfo);
            }
        }
    }

    function resetPass()
    {
        $uid = \SmallSmallRSS\Database::escape_string($_REQUEST["id"]);
        Pref_Users::resetUserPassword($uid, true);
    }

    function index()
    {

        $access_level_names = \SmallSmallRSS\Constants::access_level_names();

        print "<div id=\"pref-user-wrap\" dojoType=\"dijit.layout.BorderContainer\" gutters=\"false\">";
        print "<div id=\"pref-user-header\" dojoType=\"dijit.layout.ContentPane\" region=\"top\">";

        print "<div id=\"pref-user-toolbar\" dojoType=\"dijit.Toolbar\">";

        $user_search = \SmallSmallRSS\Database::escape_string($_REQUEST["search"]);

        if (array_key_exists("search", $_REQUEST)) {
            $_SESSION["prefs_user_search"] = $user_search;
        } else {
            $user_search = $_SESSION["prefs_user_search"];
        }

        print "<div style='float : right; padding-right : 4px;'>
                <input dojoType=\"dijit.form.TextBox\" id=\"user_search\" size=\"20\" type=\"search\"
                    value=\"$user_search\">
                <button dojoType=\"dijit.form.Button\" onclick=\"updateUsersList()\">".
            __('Search')."</button>
                </div>";

        $sort = \SmallSmallRSS\Database::escape_string($_REQUEST["sort"]);

        if (!$sort || $sort == "undefined") {
            $sort = "login";
        }

        print "<div dojoType=\"dijit.form.DropDownButton\">".
            "<span>" . __('Select')."</span>";
        print "<div dojoType=\"dijit.Menu\" style=\"display: none;\">";
        print "<div onclick=\"selectTableRows('prefUserList', 'all')\"
                dojoType=\"dijit.MenuItem\">".__('All')."</div>";
        print "<div onclick=\"selectTableRows('prefUserList', 'none')\"
                dojoType=\"dijit.MenuItem\">".__('None')."</div>";
        print "</div></div>";

        print "<button dojoType=\"dijit.form.Button\" onclick=\"addUser()\">".__('Create user')."</button>";

        print "
                <button dojoType=\"dijit.form.Button\" onclick=\"selectedUserDetails()\">".
            __('Details')."</button dojoType=\"dijit.form.Button\">
                <button dojoType=\"dijit.form.Button\" onclick=\"editSelectedUser()\">".
            __('Edit')."</button dojoType=\"dijit.form.Button\">
                <button dojoType=\"dijit.form.Button\" onclick=\"removeSelectedUsers()\">".
            __('Remove')."</button dojoType=\"dijit.form.Button\">
                <button dojoType=\"dijit.form.Button\" onclick=\"resetSelectedUserPass()\">".
            __('Reset password')."</button dojoType=\"dijit.form.Button\">";

        \SmallSmallRSS\PluginHost::getInstance()->run_hooks(
            \SmallSmallRSS\PluginHost::HOOK_PREFS_TAB_SECTION,
            "hookPreferencesTab_section", "prefUsersToolbar"
        );

        print "</div>"; #toolbar
        print "</div>"; #pane
        print "<div id=\"pref-user-content\" dojoType=\"dijit.layout.ContentPane\" region=\"center\">";

        print "<div id=\"sticky-status-msg\"></div>";

        if ($user_search) {

            $user_search = explode(" ", $user_search);
            $tokens = array();

            foreach ($user_search as $token) {
                $token = trim($token);
                array_push($tokens, "(UPPER(login) LIKE UPPER('%$token%'))");
            }

            $user_search_query = "(" . join($tokens, " AND ") . ") AND ";

        } else {
            $user_search_query = "";
        }
        $substring_for_date = \SmallSmallRSS\Config::get('SUBSTRING_FOR_DATE');
        $result = \SmallSmallRSS\Database::query(
            "SELECT
                    id,login,access_level,email,
                    ".$substring_for_date."(last_login,1,16) as last_login,
                    ".$substring_for_date."(created,1,16) as created
                FROM
                    ttrss_users
                WHERE
                    $user_search_query
                    id > 0
                ORDER BY $sort"
        );

        if (\SmallSmallRSS\Database::num_rows($result) > 0) {

            print "<p><table width=\"100%\" cellspacing=\"0\"
                class=\"prefUserList\" id=\"prefUserList\">";

            print "<tr class=\"title\">
                        <td align='center' width=\"5%\">&nbsp;</td>
                        <td width='30%'><a href=\"#\" onclick=\"updateUsersList('login')\">".__('Login')."</a></td>
                        <td width='30%'><a href=\"#\" onclick=\"updateUsersList('access_level')\">".__('Access Level')."</a></td>
                        <td width='20%'><a href=\"#\" onclick=\"updateUsersList('created')\">".__('Registered')."</a></td>
                        <td width='20%'><a href=\"#\" onclick=\"updateUsersList('last_login')\">".__('Last login')."</a></td></tr>";

            $lnum = 0;

            while ($line = \SmallSmallRSS\Database::fetch_assoc($result)) {

                $uid = $line["id"];

                print "<tr id=\"UMRR-$uid\">";

                $line["login"] = htmlspecialchars($line["login"]);

                $line["created"] = make_local_datetime($line["created"], false);
                $line["last_login"] = make_local_datetime($line["last_login"], false);

                print "<td align='center'><input onclick='toggleSelectRow2(this);'
                    dojoType=\"dijit.form.CheckBox\" type=\"checkbox\"
                    id=\"UMCHK-$uid\"></td>";

                $onclick = "onclick='editUser($uid, event)' title='".__('Click to edit')."'";

                print "<td $onclick>" . $line["login"] . "</td>";

                if (!$line["email"]) {  $line["email"] = "&nbsp;";
                }

                print "<td $onclick>" .    $access_level_names[$line["access_level"]] . "</td>";
                print "<td $onclick>" . $line["created"] . "</td>";
                print "<td $onclick>" . $line["last_login"] . "</td>";

                print "</tr>";

                ++$lnum;
            }

            print "</table>";

        } else {
            print "<p>";
            if (!$user_search) {
                \SmallSmallRSS\Renderers\Messages::renderWarning(__('No users defined.'));
            } else {
                \SmallSmallRSS\Renderers\Messages::renderWarning(__('No matching users found.'));
            }
            print "</p>";

        }

        print "</div>"; #pane
        \SmallSmallRSS\PluginHost::getInstance()->run_hooks(
            \SmallSmallRSS\PluginHost::HOOK_PREFS_TAB,
            "hookPreferencesTab", "prefUsers"
        );
        print "</div>"; #container
    }
}
