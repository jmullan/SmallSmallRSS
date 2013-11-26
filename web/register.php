<?php
require_once __DIR__ . '/src/SmallSmallRSS/bootstrap.php';
\SmallSmallRSS\Session::init();

startup_gettext();

$action = $_REQUEST["action"];

if (!\SmallSmallRSS\PluginHost::init_all()) {
    return;
}

if ($_REQUEST["format"] == "feed") {
    header("Content-Type: text/xml");
    print '<?xml version="1.0" encoding="utf-8"?>';
    print "<feed xmlns=\"http://www.w3.org/2005/Atom\">
            <id>".htmlspecialchars(\SmallSmallRSS\Config::get('SELF_URL_PATH') . "/register.php")."</id>
            <title>Tiny Tiny RSS registration slots</title>
            <link rel=\"self\" href=\"".htmlspecialchars(\SmallSmallRSS\Config::get('SELF_URL_PATH') . "/register.php?format=feed")."\"/>
            <link rel=\"alternate\" href=\"".htmlspecialchars(\SmallSmallRSS\Config::get('SELF_URL_PATH'))."\"/>";

    if (\SmallSmallRSS\Config::get('ENABLE_REGISTRATION')) {
        $result = \SmallSmallRSS\Database::query("SELECT COUNT(*) AS cu FROM ttrss_users");
        $num_users = \SmallSmallRSS\Database::fetch_result($result, 0, "cu");

        $num_users = \SmallSmallRSS\Config::get('REG_MAX_USERS') - $num_users;
        if ($num_users < 0) $num_users = 0;
        $reg_suffix = "enabled";
    } else {
        $num_users = 0;
        $reg_suffix = "disabled";
    }

    print "<entry>
            <id>".htmlspecialchars(\SmallSmallRSS\Config::get('SELF_URL_PATH'))."/register.php?$num_users"."</id>
            <link rel=\"alternate\" href=\"".htmlspecialchars(\SmallSmallRSS\Config::get('SELF_URL_PATH') . "/register.php")."\"/>";

    print "<title>$num_users slots are currently available, registration $reg_suffix</title>";
    print "<summary>$num_users slots are currently available, registration $reg_suffix</summary>";

    print "</entry>";

    print "</feed>";

    return;
}

/* Remove users which didn't login after receiving their registration information */

if (\SmallSmallRSS\Config::get('DB_TYPE') == "pgsql") {
    \SmallSmallRSS\Database::query(
        "DELETE FROM ttrss_users WHERE last_login IS NULL
         AND created < NOW() - INTERVAL '1 day' AND access_level = 0"
    );
} else {
    \SmallSmallRSS\Database::query(
        "DELETE FROM ttrss_users WHERE last_login IS NULL
         AND created < DATE_SUB(NOW(), INTERVAL 1 DAY) AND access_level = 0"
    );
}

if ($action == "check") {
    $login = trim(\SmallSmallRSS\Database::escape_string($_REQUEST['login']));
    $result = \SmallSmallRSS\Database::query("SELECT id FROM ttrss_users WHERE
            LOWER(login) = LOWER('$login')");
    $is_registered = \SmallSmallRSS\Database::num_rows($result) > 0;

    header("Content-Type: application/xml");
    print "<result>";
    printf("%d", $is_registered);
    print "</result>";
    exit;
}
?>

<html>
<head>
<title>Create new account</title>
<meta http-equiv="Content-Type" content="text/html; charset=utf-8">
    <link rel="stylesheet" type="text/css" href="css/utility.css">
    <script type="text/javascript" src="js/functions.js"></script>
    <script type="text/javascript" src="lib/prototype.js"></script>
    <script type="text/javascript" src="lib/scriptaculous/scriptaculous.js?load=effects,dragdrop,controls"></script>
    </head>

    <script type="text/javascript">

    function checkUsername() {

    try {
        var f = document.forms['register_form'];
        var login = f.login.value;

        if (login == "") {
            new Effect.Highlight(f.login);
            f.sub_btn.disabled = true;
            return false;
        }

        var query = "register.php?action=check&login=" +
        param_escape(login);

        new Ajax.Request(query, {
            onComplete: function(transport) {

                    try {

                        var reply = transport.responseXML;

                        var result = reply.getElementsByTagName('result')[0];
                        var result_code = result.firstChild.nodeValue;

                        if (result_code == 0) {
                            new Effect.Highlight(f.login, {startcolor : '#00ff00'});
                            f.sub_btn.disabled = false;
                        } else {
                            new Effect.Highlight(f.login, {startcolor : '#ff0000'});
                            f.sub_btn.disabled = true;
                        }
                    } catch (e) {
                        exception_error("checkUsername_callback", e);
                    }

                } });

    } catch (e) {
        exception_error("checkUsername", e);
    }

    return false;

}

function validateRegForm() {
    try {

        var f = document.forms['register_form'];

        if (f.login.value.length == 0) {
            new Effect.Highlight(f.login);
            return false;
        }

        if (f.email.value.length == 0) {
            new Effect.Highlight(f.email);
            return false;
        }

        if (f.turing_test.value.length == 0) {
            new Effect.Highlight(f.turing_test);
            return false;
        }

        return true;

    } catch (e) {
        exception_error("validateRegForm", e);
        return false;
    }
}

</script>

<body>

<div class="floatingLogo"><img src="images/logo_small.png"></div>

    <h1><?php echo __("Create new account") ?></h1>

    <div class="content">

<?php
    if (!\SmallSmallRSS\Config::get('ENABLE_REGISTRATION')) {
        \SmallSmallRSS\Renderers\Messages::renderError(
            __("New user registrations are administratively disabled."));

        print "<p><form method=\"GET\" action=\"backend.php\">
                <input type=\"hidden\" name=\"op\" value=\"logout\">
                <input type=\"submit\" value=\"".__("Return to Tiny Tiny RSS")."\">
                </form>";
        return;
    }
?>

<?php if (\SmallSmallRSS\Config::get('REG_MAX_USERS') > 0) {
$result = \SmallSmallRSS\Database::query("SELECT COUNT(*) AS cu FROM ttrss_users");
$num_users = \SmallSmallRSS\Database::fetch_result($result, 0, "cu");
} ?>

<?php
if (!\SmallSmallRSS\Config::get('REG_MAX_USERS')
    || $num_users < \SmallSmallRSS\Config::get('REG_MAX_USERS')) {
?>
    <?php if (!$action) { ?>

    <p><?php echo __('Your temporary password will be sent to the specified email. Accounts, which were not logged in once, are erased automatically 24 hours after temporary password is sent.') ?></p>

    <form action="register.php" method="POST" name="register_form">
    <input type="hidden" name="action" value="do_register">
    <table>
    <tr>
    <td><?php echo __('Desired login:') ?></td><td>
        <input name="login" required>
    </td><td>
        <input type="submit" value="<?php echo __('Check availability') ?>" onclick='return checkUsername()'>
    </td></tr>
    <tr><td><?php echo __('Email:') ?></td><td>
        <input name="email" type="email" required>
    </td></tr>
    <tr><td><?php echo __('How much is two plus two:') ?></td><td>
        <input name="turing_test" required></td></tr>
    <tr><td colspan="2" align="right">
    <input type="submit" name="sub_btn" value="<?php echo __('Submit registration') ?>"
            disabled="disabled" onclick='return validateRegForm()'>
    </td></tr>
    </table>
    </form>

    <?php print "<p><form method=\"GET\" action=\"index.php\">
                <input type=\"submit\" value=\"".__("Return to Tiny Tiny RSS")."\">
                </form>"; ?>

    <?php } elseif ($action == "do_register") { ?>

    <?php
        $login = mb_strtolower(trim(\SmallSmallRSS\Database::escape_string($_REQUEST["login"])));
        $email = trim(\SmallSmallRSS\Database::escape_string($_REQUEST["email"]));
        $test = trim(\SmallSmallRSS\Database::escape_string($_REQUEST["turing_test"]));

        if (!$login || !$email || !$test) {
            \SmallSmallRSS\Renderers\Messages::renderError(
                            __("Your registration information is incomplete."));
            print "<p><form method=\"GET\" action=\"index.php\">
                <input type=\"submit\" value=\"".__("Return to Tiny Tiny RSS")."\">
                </form>";
            return;
        }

        if ($test == "four" || $test == "4") {

            $result = \SmallSmallRSS\Database::query("SELECT id FROM ttrss_users WHERE
                login = '$login'");

            $is_registered = \SmallSmallRSS\Database::num_rows($result) > 0;

            if ($is_registered) {
                \SmallSmallRSS\Renderers\Messages::renderError(
                                    __('Sorry, this username is already taken.'));
                print "<p><form method=\"GET\" action=\"index.php\">
                <input type=\"submit\" value=\"".__("Return to Tiny Tiny RSS")."\">
                </form>";
            } else {

                $password = make_password();

                $salt = substr(bin2hex(get_random_bytes(125)), 0, 250);
                $pwd_hash = encrypt_password($password, $salt, true);

                \SmallSmallRSS\Database::query("INSERT INTO ttrss_users
                    (login,pwd_hash,access_level,last_login, email, created, salt)
                    VALUES ('$login', '$pwd_hash', 0, null, '$email', NOW(), '$salt')");

                $result = \SmallSmallRSS\Database::query("SELECT id FROM ttrss_users WHERE
                    login = '$login' AND pwd_hash = '$pwd_hash'");

                if (\SmallSmallRSS\Database::num_rows($result) != 1) {
                    \SmallSmallRSS\Renderers\Messages::renderError(__('Registration failed.'));
                    print "<p><form method=\"GET\" action=\"index.php\">
                    <input type=\"submit\" value=\"".__("Return to Tiny Tiny RSS")."\">
                    </form>";
                } else {

                    $new_uid = \SmallSmallRSS\Database::fetch_result($result, 0, "id");

                    initialize_user($new_uid);

                    $reg_text = "Hi!\n".
                        "\n".
                        "You are receiving this message, because you (or somebody else) have opened\n".
                        "an account at Tiny Tiny RSS.\n".
                        "\n".
                        "Your login information is as follows:\n".
                        "\n".
                        "Login: $login\n".
                        "Password: $password\n".
                        "\n".
                        "Don't forget to login at least once to your new account, otherwise\n".
                        "it will be deleted in 24 hours.\n".
                        "\n".
                        "If that wasn't you, just ignore this message. Thanks.";

                    $mail = new \SmallSmallRSS\Mailer();
                    $mail->IsHTML(false);
                    $rc = $mail->quickMail($email, "", "Registration information for Tiny Tiny RSS", $reg_text, false);

                    if (!$rc) {
                                            \SmallSmallRSS\Renderers\Messages::renderError($mail->ErrorInfo);
                                        }

                    unset($reg_text);
                    unset($mail);
                    unset($rc);
                    $reg_text = "Hi!\n"
                        . "\n"
                        . "A new user has registered at your Tiny Tiny RSS installation.\n"
                        . "\n"
                        . "Login: $login\n"
                        . "Email: $email\n";


                    $mail = new \SmallSmallRSS\Mailer();
                    $mail->IsHTML(false);
                    $reg_notify_address = \SmallSmallRSS\Config::get('REG_NOTIFY_ADDRESS');
                    if (strlen($reg_notify_address)) {
                        $rc = $mail->quickMail(
                            $reg_notify_address,
                            '',
                            "Registration notice for Tiny Tiny RSS",
                            $reg_text,
                            false
                        );
                        if (!$rc) {
                            \SmallSmallRSS\Renderers\Messages::renderError($mail->ErrorInfo);
                        }
                    }

                    \SmallSmallRSS\Renderers\Messages::renderNotice(
                        __("Account created successfully.")
                    );

                    print "<p><form method=\"GET\" action=\"index.php\">
                    <input type=\"submit\" value=\"".__("Return to Tiny Tiny RSS")."\">
                    </form>";

                }

            }

                } else {
                    \SmallSmallRSS\Renderers\Messages::renderError(
                        'Plese check the form again, you have failed the robot test.'
                    );
                    print "<p><form method=\"GET\" action=\"index.php\">
                           <input type=\"submit\" value=\"".__("Return to Tiny Tiny RSS")."\" />
                           </form>";

         }
    }
} else {
    \SmallSmallRSS\Renderers\Messages::renderNotice(__('New user registrations are currently closed.'));
    print "<p><form method=\"GET\" action=\"index.php\">
           <input type=\"submit\" value=\"".__("Return to Tiny Tiny RSS")."\" />
           </form>";
} ?>

    </div>
</body>
</html>
