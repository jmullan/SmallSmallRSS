<?php
namespace SmallSmallRSS\Handlers;

class Pref_Users extends ProtectedHandler
{
    public function before($method)
    {
        if (parent::before($method)) {
            if ($_SESSION['access_level'] < 10) {
                echo __('Your access level is insufficient to open this tab.');
                return false;
            }
            return true;
        }
        return false;
    }

    public function ignoreCSRF($method)
    {
        $csrf_ignored = array('index', 'edit', 'userdetails');

        return array_search($method, $csrf_ignored) !== false;
    }

    public function userdetails()
    {

        $uid = sprintf('%d', $_REQUEST['id']);
        $substring_for_date = \SmallSmallRSS\Config::get('SUBSTRING_FOR_DATE');
        $result = \SmallSmallRSS\Database::query(
            'SELECT login,
                '.$substring_for_date.'(last_login,1,16) AS last_login,
                access_level,
                (SELECT COUNT(int_id) FROM ttrss_user_entries
                    WHERE owner_uid = id) AS stored_articles,
                '.$substring_for_date."(created,1,16) AS created
                FROM ttrss_users
                WHERE id = '$uid'"
        );

        if (\SmallSmallRSS\Database::num_rows($result) == 0) {
            echo '<h1>'.__('User not found').'</h1>';
            return;
        }

        $login = \SmallSmallRSS\Database::fetch_result($result, 0, 'login');
        echo "<table width='100%'>";
        $last_login = make_local_datetime(
            \SmallSmallRSS\Database::fetch_result($result, 0, 'last_login'),
            true
        );
        $created = make_local_datetime(
            \SmallSmallRSS\Database::fetch_result($result, 0, 'created'),
            true
        );
        $access_level = \SmallSmallRSS\Database::fetch_result($result, 0, 'access_level');
        $stored_articles = \SmallSmallRSS\Database::fetch_result($result, 0, 'stored_articles');
        echo '<tr><td>'.__('Registered')."</td><td>$created</td></tr>";
        echo '<tr><td>'.__('Last logged in')."</td><td>$last_login</td></tr>";
        $result = \SmallSmallRSS\Database::query(
            "SELECT COUNT(id) as num_feeds
             FROM ttrss_feeds
             WHERE owner_uid = '$uid'"
        );
        $num_feeds = \SmallSmallRSS\Database::fetch_result($result, 0, 'num_feeds');
        echo '<tr><td>'.__('Subscribed feeds count')."</td><td>$num_feeds</td></tr>";
        echo '</table>';
        echo '<h1>'.__('Subscribed feeds').'</h1>';
        $result = \SmallSmallRSS\Database::query(
            "SELECT id, title, site_url
             FROM ttrss_feeds
             WHERE owner_uid = '$uid'
             ORDER BY title"
        );
        echo '<ul class="userFeedList">';
        while ($line = \SmallSmallRSS\Database::fetch_assoc($result)) {
            $icon_file = \SmallSmallRSS\Config::get('ICONS_URL') . '/' . $line['id'] . '.ico';
            if (file_exists($icon_file) && filesize($icon_file) > 0) {
                $feed_icon = "<img class=\"tinyFeedIcon\" src=\"$icon_file\" />";
            } else {
                $feed_icon = '<img class="tinyFeedIcon" src="images/blank_icon.gif" />';
            }
            echo "<li>$feed_icon&nbsp;<a href=\"".$line['site_url'].'">'.$line['title'].'</a></li>';
        }
        if (\SmallSmallRSS\Database::num_rows($result) < $num_feeds) {
            // FIXME - add link to show ALL subscribed feeds here somewhere
            echo '<li><img
                    class="tinyFeedIcon" src="images/blank_icon.gif">&nbsp;...</li>';
        }
        echo '</ul>';
        echo "<div align=\"center\">";
        echo "<button data-dojo-type=\"dijit.form.Button\" type=\"submit\">";
        echo __('Close this window');
        echo '</button></div>';
    }

    public function edit()
    {
        $access_level_names = \SmallSmallRSS\Constants::access_level_names();

        $id = \SmallSmallRSS\Database::escape_string($_REQUEST['id']);
        echo "<form id=\"user_edit_form\" onsubmit='return false' data-dojo-type=\"dijit.form.Form\">";

        echo "<input data-dojo-type=\"dijit.form.TextBox\" style=\"display: none\" name=\"id\" value=\"$id\">";
        echo '<input data-dojo-type="dijit.form.TextBox" style="display: none" name="op" value="pref-users">';
        echo '<input data-dojo-type="dijit.form.TextBox" style="display: none" name="method" value="editSave">';

        $result = \SmallSmallRSS\Database::query("SELECT * FROM ttrss_users WHERE id = '$id'");

        $login = \SmallSmallRSS\Database::fetch_result($result, 0, 'login');
        $access_level = \SmallSmallRSS\Database::fetch_result($result, 0, 'access_level');
        $email = \SmallSmallRSS\Database::fetch_result($result, 0, 'email');

        $sel_disabled = ($id == $_SESSION['uid']) ? 'disabled' : '';

        echo '<div class="dlgSec">'.__('User').'</div>';
        echo '<div class="dlgSecCont">';

        if ($sel_disabled) {
            echo "<input data-dojo-type=\"dijit.form.TextBox\" style=\"display: none\" name=\"login\" value=\"$login\">";
        }

        echo "<input size=\"30\" style=\"font-size: 16px\"
                data-dojo-type=\"dijit.form.ValidationTextBox\" required=\"1\"
                onkeypress=\"return filterCR(event, userEditSave)\" $sel_disabled
                name=\"login\" value=\"$login\">";

        echo '</div>';

        echo '<div class="dlgSec">'.__('Authentication').'</div>';
        echo '<div class="dlgSecCont">';
        echo __('Access level: ') . ' ';
        $form_elements_renderer = new \SmallSmallRSS\Renderers\FormElements();
        if (!$sel_disabled) {
            $form_elements_renderer->renderSelect(
                'access_level',
                $access_level,
                $access_level_names,
                "data-dojo-type=\"dijit.form.Select\" $sel_disabled"
            );
        } else {
            $form_elements_renderer->renderSelect(
                '',
                $access_level,
                $access_level_names,
                "data-dojo-type=\"dijit.form.Select\" $sel_disabled"
            );
            echo '<input data-dojo-type="dijit.form.TextBox" style="display: none"';
            echo " name=\"access_level\" value=\"$access_level\">";
        }
        echo '<hr />';
        echo '<input data-dojo-type="dijit.form.TextBox" type="password" size="20"';
        echo ' onkeypress="return filterCR(event, userEditSave)"';
        echo ' placeholder="Change password" name="password">';
        echo '</div>';
        echo '<div class="dlgSec">'.__('Options').'</div>';
        echo '<div class="dlgSecCont">';
        echo '<input data-dojo-type="dijit.form.TextBox" size="30" name="email"';
        echo ' onkeypress="return filterCR(event, userEditSave)" placeholder="E-mail"';
        echo " value=\"$email\">";
        echo '</div>';
        echo '</table>';
        echo '</form>';
        echo '<div class="dlgButtons">';
        echo '<button data-dojo-type="dijit.form.Button" type="submit">';
        echo __('Save');
        echo '</button>';
        echo "<button data-dojo-type=\"dijit.form.Button\" onclick=\"dijit.byId('userEditDlg').hide()\">";
        echo __('Cancel');
        echo '</button></div>';
    }

    public function editSave()
    {
        $login = \SmallSmallRSS\Database::escape_string(trim($_REQUEST['login']));
        $uid = \SmallSmallRSS\Database::escape_string($_REQUEST['id']);
        $access_level = (int) $_REQUEST['access_level'];
        $email = \SmallSmallRSS\Database::escape_string(trim($_REQUEST['email']));
        $password = $_REQUEST['password'];
        if ($password) {
            $salt = substr(bin2hex(get_random_bytes(125)), 0, 250);
            $pwd_hash = encrypt_password($password, $salt, true);
            $pass_query_part = "pwd_hash = '$pwd_hash', salt = '$salt',";
        } else {
            $pass_query_part = '';
        }
        \SmallSmallRSS\Database::query(
            "UPDATE ttrss_users
             SET
                $pass_query_part
                login = '$login',
                access_level = '$access_level',
                email = '$email',
                otp_enabled = false
             WHERE id = '$uid'"
        );
    }

    public function remove()
    {
        $ids = explode(',', \SmallSmallRSS\Database::escape_string($_REQUEST['ids']));
        foreach ($ids as $id) {
            if ($id != $_SESSION['uid'] && $id != 1) {
                \SmallSmallRSS\Database::query("DELETE FROM ttrss_tags WHERE owner_uid = '$id'");
                \SmallSmallRSS\Database::query("DELETE FROM ttrss_feeds WHERE owner_uid = '$id'");
                \SmallSmallRSS\Database::query("DELETE FROM ttrss_users WHERE id = '$id'");
            }
        }
    }
    public function add()
    {

        $login = \SmallSmallRSS\Database::escape_string(trim($_REQUEST['login']));
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
                $new_uid = \SmallSmallRSS\Database::fetch_result($result, 0, 'id');
                \SmallSmallRSS\Renderers\Messages::renderNotice(
                    T_sprintf('Added user <b>%s</b> with password <b>%s</b>', $login, $tmp_user_pwd)
                );
                \SmallSmallRSS\Feeds\newUser($new_uid);
            } else {
                \SmallSmallRSS\Renderers\Messages::renderWarning(
                    T_sprintf('Could not create user <b>%s</b>', $login)
                );
            }
        } else {
            \SmallSmallRSS\Renderers\Messages::renderWarning(
                T_sprintf('User <b>%s</b> already exists.', $login)
            );
        }
    }

    public static function resetUserPassword($uid, $show_password)
    {

        $result = \SmallSmallRSS\Database::query(
            "SELECT login, email
             FROM ttrss_users
             WHERE id = '$uid'"
        );
        $login = \SmallSmallRSS\Database::fetch_result($result, 0, 'login');
        $email = \SmallSmallRSS\Database::fetch_result($result, 0, 'email');
        $salt = \SmallSmallRSS\Database::fetch_result($result, 0, 'salt');
        $new_salt = substr(bin2hex(get_random_bytes(125)), 0, 250);
        $tmp_user_pwd = make_password(8);
        $pwd_hash = encrypt_password($tmp_user_pwd, $new_salt, true);
        \SmallSmallRSS\Database::query(
            "UPDATE ttrss_users
             SET
                 pwd_hash = '$pwd_hash',
                 salt = '$new_salt'
             WHERE id = '$uid'"
        );
        if ($show_password) {
            echo T_sprintf('Changed password of user <b>%s</b> to <b>%s</b>', $login, $tmp_user_pwd);
        } else {
            \SmallSmallRSS\Renderers\Messages::renderNotice(
                T_sprintf('Sending new password of user <b>%s</b> to <b>%s</b>', $login, $email)
            );
        }
        if ($email) {
            $tpl = new \MiniTemplator\Engine();
            $tpl->readTemplateFromFile('templates/resetpass_template.txt');
            $tpl->setVariable('LOGIN', $login);
            $tpl->setVariable('NEWPASS', $tmp_user_pwd);
            $tpl->addBlock('message');
            $message = '';
            $tpl->generateOutputToString($message);
            $mail = new \SmallSmallRSS\Mailer();
            $rc = $mail->quickMail(
                $email,
                $login,
                __('[tt-rss] Password change notification'),
                $message,
                false
            );
            if (!$rc) {
                \SmallSmallRSS\Renderers\Messages::renderError($mail->ErrorInfo);
            }
        }
    }

    public function resetPass()
    {
        $uid = \SmallSmallRSS\Database::escape_string($_REQUEST['id']);
        Pref_Users::resetUserPassword($uid, true);
    }
    public function index()
    {
        $access_level_names = \SmallSmallRSS\Constants::access_level_names();
        echo '<div id="pref-user-wrap" data-dojo-type="dijit.layout.BorderContainer" gutters="false">';
        echo '<div id="pref-user-header" data-dojo-type="dijit.layout.ContentPane" region="top">';
        echo '<div id="pref-user-toolbar" data-dojo-type="dijit.Toolbar">';
        $user_search = \SmallSmallRSS\Database::escape_string($_REQUEST['search']);
        if (array_key_exists('search', $_REQUEST)) {
            $_SESSION['prefs_user_search'] = $user_search;
        } else {
            $user_search = $_SESSION['prefs_user_search'];
        }
        echo '<div style="float: right; padding-right : 4px;">';
        echo "<input data-dojo-type=\"dijit.form.TextBox\" id=\"user_search\" size=\"20\" type=\"search\"";
        echo " value=\"$user_search\">";
        echo "<button data-dojo-type=\"dijit.form.Button\" onclick=\"updateUsersList()\">";
        echo __('Search');
        echo '</button>';
        echo '</div>';
        $sort = \SmallSmallRSS\Database::escape_string($_REQUEST['sort']);
        if (!$sort || $sort == 'undefined') {
            $sort = 'login';
        }
        echo '<div data-dojo-type="dijit.form.DropDownButton">';
        echo '<span>' . __('Select').'</span>';
        echo '<div data-dojo-type="dijit.Menu" style="display: none;">';
        echo "<div onclick=\"selectTableRows('prefUserList', 'all')\"";
        echo " data-dojo-type=\"dijit.MenuItem\">".__('All').'</div>';
        echo "<div onclick=\"selectTableRows('prefUserList', 'none')\"";
        echo " data-dojo-type=\"dijit.MenuItem\">".__('None').'</div>';
        echo '</div>';
        echo '</div>';
        echo '<button data-dojo-type="dijit.form.Button" onclick="addUser()">';
        echo __('Create user');
        echo '</button>';
        echo '<button data-dojo-type="dijit.form.Button" onclick="selectedUserDetails()">';
        echo __('Details');
        echo '</button>';
        echo '<button data-dojo-type="dijit.form.Button" onclick="editSelectedUser()">';
        echo __('Edit');
        echo '</button>';
        echo '<button data-dojo-type="dijit.form.Button" onclick="removeSelectedUsers()">';
        echo __('Remove');
        echo '</button>';
        echo '<button data-dojo-type="dijit.form.Button" onclick="resetSelectedUserPass()">';
        echo __('Reset password');
        echo '</button>';
        \SmallSmallRSS\PluginHost::getInstance()->runHooks(
            \SmallSmallRSS\Hooks::RENDER_RENDER_PREFS_TAB_SECTION,
            'prefUsersToolbar'
        );
        echo '</div>';
        echo '</div>';
        echo '<div id="pref-user-content" data-dojo-type="dijit.layout.ContentPane" region="center">';
        echo '<div id="sticky-status-msg"></div>';
        if ($user_search) {
            $user_search = explode(' ', $user_search);
            $tokens = array();
            foreach ($user_search as $token) {
                $token = trim($token);
                array_push($tokens, "(UPPER(login) LIKE UPPER('%$token%'))");
            }
            $user_search_query = '(' . join($tokens, ' AND ') . ') AND ';
        } else {
            $user_search_query = '';
        }
        $substring_for_date = \SmallSmallRSS\Config::get('SUBSTRING_FOR_DATE');
        $result = \SmallSmallRSS\Database::query(
            'SELECT
                 id,login,access_level,email,
                 '.$substring_for_date.'(last_login,1,16) as last_login,
                 '.$substring_for_date."(created,1,16) as created
             FROM
                 ttrss_users
             WHERE
                 $user_search_query
                 id > 0
             ORDER BY $sort"
        );

        if (\SmallSmallRSS\Database::num_rows($result) > 0) {
            echo '<p><table width="100%" cellspacing="0" class="prefUserList" id="prefUserList">';
            echo '<tr class="title">';
            echo "<td align='center' width=\"5%\">&nbsp;</td>";
            echo "<td width='30%'><a href=\"#\" onclick=\"updateUsersList('login')\">";
            echo __('Login');
            echo '</a></td>';
            echo "<td width='30%'><a href=\"#\" onclick=\"updateUsersList('access_level')\">";
            echo __('Access Level');
            echo '</a></td>';
            echo "<td width='20%'><a href=\"#\" onclick=\"updateUsersList('created')\">";
            echo __('Registered');
            echo '</a></td>';
            echo "<td width='20%'><a href=\"#\" onclick=\"updateUsersList('last_login')\">";
            echo __('Last login');
            echo '</a></td></tr>';
            $lnum = 0;
            while ($line = \SmallSmallRSS\Database::fetch_assoc($result)) {
                $uid = $line['id'];
                echo "<tr id=\"UMRR-$uid\">";
                $line['login'] = htmlspecialchars($line['login']);
                $line['created'] = make_local_datetime($line['created'], false);
                $line['last_login'] = make_local_datetime($line['last_login'], false);
                echo "<td align='center'><input onclick='toggleSelectRow2(this);'";
                echo " data-dojo-type=\"dijit.form.CheckBox\" type=\"checkbox\"";
                echo " id=\"UMCHK-$uid\"></td>";
                $onclick = "onclick='editUser($uid, event)' title='".__('Click to edit')."'";
                echo "<td $onclick>" . $line['login'] . '</td>';
                if (!$line['email']) {
                    $line['email'] = '&nbsp;';
                }
                echo "<td $onclick>" .    $access_level_names[$line['access_level']] . '</td>';
                echo "<td $onclick>" . $line['created'] . '</td>';
                echo "<td $onclick>" . $line['last_login'] . '</td>';
                echo '</tr>';
                $lnum += 1;
            }
            echo '</table>';
        } else {
            echo '<p>';
            if (!$user_search) {
                \SmallSmallRSS\Renderers\Messages::renderWarning(__('No users defined.'));
            } else {
                \SmallSmallRSS\Renderers\Messages::renderWarning(__('No matching users found.'));
            }
            echo '</p>';
        }
        echo '</div>';
        \SmallSmallRSS\PluginHost::getInstance()->runHooks(\SmallSmallRSS\Hooks::RENDER_PREFS_TAB, 'prefUsers');
        echo '</div>';
    }
}
