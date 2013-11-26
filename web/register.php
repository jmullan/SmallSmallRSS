<?php
require_once __DIR__ . '/../src/SmallSmallRSS/bootstrap.php';
\SmallSmallRSS\Session::init();

\SmallSmallRSS\Locale::startupGettext();

$action = $_REQUEST["action"];

if (!\SmallSmallRSS\PluginHost::init_all()) {
    return;
}

$registration_enabled = \SmallSmallRSS\Config::get('ENABLE_REGISTRATION');
$num_users = \SmallSmallRSS\Users::count();
$max_users = \SmallSmallRSS\Config::get('REG_MAX_USERS');
if (!$max_users) {
    $max_users = $num_users + 1;
}
$avail_users = max($max_users - $num_users, 0);
if ($registration_enabled) {
    $reg_suffix = "enabled";
} else {
    $avail_users = 0;
    $reg_suffix = "disabled";
}

if ($_REQUEST["format"] == "feed") {
    header("Content-Type: text/xml");
    print '<?xml version="1.0" encoding="utf-8"?>';
    print "<feed xmlns=\"http://www.w3.org/2005/Atom\">";
    print "<id>".htmlspecialchars(\SmallSmallRSS\Config::get('SELF_URL_PATH') . "/register.php")."</id>";
    print "<title>Tiny Tiny RSS registration slots</title>";
    print "<link rel=\"self\" href=\"";
    print htmlspecialchars(\SmallSmallRSS\Config::get('SELF_URL_PATH') . "/register.php?format=feed");
    print "\"/>";
    print "<link rel=\"alternate\" href=\"".htmlspecialchars(\SmallSmallRSS\Config::get('SELF_URL_PATH'))."\"/>";

    print "<entry>";
    print "<id>";
    print htmlspecialchars(\SmallSmallRSS\Config::get('SELF_URL_PATH'));
    print "/register.php?$num_users";
    print "</id>";
    print "<link rel=\"alternate\" href=\"";
    print htmlspecialchars(\SmallSmallRSS\Config::get('SELF_URL_PATH') . "/register.php");
    print "\"/>";
    print "<title>$num_users slots are currently available, registration $reg_suffix</title>";
    print "<summary>$num_users slots are currently available, registration $reg_suffix</summary>";
    print "</entry>";
    print "</feed>";
    return;
}

/* Remove users which didn't login after receiving their registration information */
\SmallSmallRSS\Users::clearExpired();
if ($action == "check") {
    $is_registered = \SmallSmallRSS\Users::isRegistered($_REQUEST['login']);
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
  <script type="text/javascript" src="js/register.js"></script>
</head>
<body>
  <div class="floatingLogo"><img src="images/logo_small.png" /></div>
  <h1><?php echo __("Create new account") ?></h1>
  <div class="content">
<?php
    if (!$registration_enabled) {
        \SmallSmallRSS\Renderers\Messages::renderError(
            __("New user registrations are administratively disabled.")
        );
        print '<form method="GET" action="backend.php">';
        print '<input type="hidden" name="op" value="logout" />';
        print '<p><input type="submit" value="<?php echo __("Return to Tiny Tiny RSS"); ?>" /></p>';
        print '</form>';
        return;
    }

if (!$max_users || $avail_users) {
    if (!$action) {
        print '<p>';
        echo __('Your temporary password will be sent to the specified email. Accounts, which were not logged in once, are erased automatically 24 hours after temporary password is sent.');
        print '</p>';
        print '<form action="register.php" method="POST" name="register_form">';
        print '<input type="hidden" name="action" value="do_register">';
        print '<table>';
        print '<tr>';
        print '<td>';
        echo __('Desired login:');
        print '</td><td>';
        print '<input name="login" required>';
        print '</td>';
        print '<td>';
        print '<input type="submit" value="';
        echo __('Check availability');
        print '" onclick="return checkUsername()">';
        print '</td>';
        print '</tr>';
        print '<tr><td>';
        echo __('Email:');
        print '</td><td>';
        print '<input name="email" type="email" required>';
        print '</td></tr>';
        print '<tr><td>';
        echo __('How much is two plus two:');
        print '</td><td>';
        print '<input name="turing_test" required></td></tr>';
        print '<tr><td colspan="2" align="right">';
        print '<input type="submit" name="sub_btn" value="';
        echo __('Submit registration');
        print '"';
        print 'disabled="disabled" onclick="return validateRegForm()" />';
        print '</td></tr>';
        print '</table>';
        print '</form>';
        print "<p><form method=\"GET\" action=\"index.php\">";
        print "<input type=\"submit\" value=\"".__("Return to Tiny Tiny RSS")."\">";
        print "</form>";
    } elseif ($action == "do_register") {
        $test = trim($_REQUEST["turing_test"]);
        if (empty($_REQUEST['login']) || empty($_REQUEST['email']) || !$test) {
            \SmallSmallRSS\Renderers\Messages::renderError(
                __("Your registration information is incomplete.")
            );
            print "<p><form method=\"GET\" action=\"index.php\">
                <input type=\"submit\" value=\"".__("Return to Tiny Tiny RSS")."\">
                </form>";
            return;
        }

        if ($test == "four" || $test == "4") {
            $is_registered = \SmallSmallRSS\Users::isRegistered($_REQUEST['login']);
            if ($is_registered) {
                \SmallSmallRSS\Renderers\Messages::renderError(
                    __('Sorry, this username is already taken.')
                );
                print "<p><form method=\"GET\" action=\"index.php\">";
                print "<input type=\"submit\" value=\"".__("Return to Tiny Tiny RSS")."\">";
                print "</form>";
            } else {
                $new_uid = \SmallSmallRSS\Users::create($_REQUEST['login'], $_REQUEST['email']);
                if (!$new_uid) {
                    \SmallSmallRSS\Renderers\Messages::renderError(__('Registration failed.'));
                    print "<p><form method=\"GET\" action=\"index.php\">";
                    print "<input type=\"submit\" value=\"".__("Return to Tiny Tiny RSS")."\">";
                    print "</form>";
                } else {
                    \SmallSmallRSS\Feeds\newUser($new_uid);
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
                    print "<p><form method=\"GET\" action=\"index.php\">";
                    print "<input type=\"submit\" value=\"".__("Return to Tiny Tiny RSS")."\">";
                    print "</form>";

                }

            }
        } else {
            \SmallSmallRSS\Renderers\Messages::renderError(
                'Plese check the form again, you have failed the robot test.'
            );
            print "<p><form method=\"GET\" action=\"index.php\">";
            print "<input type=\"submit\" value=\"".__("Return to Tiny Tiny RSS")."\" />";
            print "</form>";
        }
    }
} else {
    \SmallSmallRSS\Renderers\Messages::renderNotice(__('New user registrations are currently closed.'));
    print "<p><form method=\"GET\" action=\"index.php\">";
    print "<input type=\"submit\" value=\"".__("Return to Tiny Tiny RSS")."\" />";
    print "</form>";
}
?>
  </div>
</body>
</html>
