<?php
require_once __DIR__ . '/../src/SmallSmallRSS/bootstrap.php';
\SmallSmallRSS\Sessions::init();

\SmallSmallRSS\Locale::startupGettext();

$action = $_REQUEST['action'];

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
    $reg_suffix = 'enabled';
} else {
    $avail_users = 0;
    $reg_suffix = 'disabled';
}

$software_name = \SmallSmallRSS\Config::get('SOFTWARE_NAME');

if ($_REQUEST['format'] == 'feed') {
    header('Content-Type: text/xml');
    echo '<?xml version="1.0" encoding="utf-8"?>';
    echo '<feed xmlns="http://www.w3.org/2005/Atom">';
    echo '<id>'.htmlspecialchars(\SmallSmallRSS\Config::get('SELF_URL_PATH') . '/register.php').'</id>';
    echo "<title>$software_name: Registration Slots</title>";
    echo '<link rel="self" href="';
    echo htmlspecialchars(\SmallSmallRSS\Config::get('SELF_URL_PATH') . '/register.php?format=feed');
    echo '"/>';
    echo '<link rel="alternate" href="'.htmlspecialchars(\SmallSmallRSS\Config::get('SELF_URL_PATH')).'"/>';

    echo '<entry>';
    echo '<id>';
    echo htmlspecialchars(\SmallSmallRSS\Config::get('SELF_URL_PATH'));
    echo "/register.php?$num_users";
    echo '</id>';
    echo '<link rel="alternate" href="';
    echo htmlspecialchars(\SmallSmallRSS\Config::get('SELF_URL_PATH') . '/register.php');
    echo '"/>';
    echo "<title>$num_users slots are currently available, registration $reg_suffix</title>";
    echo "<summary>$num_users slots are currently available, registration $reg_suffix</summary>";
    echo '</entry>';
    echo '</feed>';
    return;
}

/* Remove users which didn't login after receiving their registration information */
\SmallSmallRSS\Users::clearExpired();
if ($action == 'check') {
    $is_registered = \SmallSmallRSS\Users::isRegistered($_REQUEST['login']);
    header('Content-Type: application/xml');
    echo '<result>';
    echof('%d', $is_registered);
    echo '</result>';
    exit;
}
?>
<!DOCTYPE html>
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
  <h1><?php echo __('Create new account') ?></h1>
  <div class="content">
<?php
    if (!$registration_enabled) {
        \SmallSmallRSS\Renderers\Messages::renderError(
            __('New user registrations are administratively disabled.')
        );
        echo '<form method="GET" action="backend.php">';
        echo '<input type="hidden" name="op" value="logout" />';
        echo '<p><input type="submit" value="<?php echo __("Return to Tiny Tiny RSS"); ?>" /></p>';
        echo '</form>';
        return;
    }

if (!$max_users || $avail_users) {
    if (!$action) {
        echo '<p>';
        echo __('Your temporary password will be sent to the specified email. Accounts, which were not logged in once, are erased automatically 24 hours after temporary password is sent.');
        echo '</p>';
        echo '<form action="register.php" method="POST" name="register_form">';
        echo '<input type="hidden" name="action" value="do_register">';
        echo '<table>';
        echo '<tr>';
        echo '<td>';
        echo __('Desired login:');
        echo '</td><td>';
        echo '<input name="login" required>';
        echo '</td>';
        echo '<td>';
        echo '<input type="submit" value="';
        echo __('Check availability');
        echo '" onclick="return checkUsername()">';
        echo '</td>';
        echo '</tr>';
        echo '<tr><td>';
        echo __('Email:');
        echo '</td><td>';
        echo '<input name="email" type="email" required>';
        echo '</td></tr>';
        echo '<tr><td>';
        echo __('How much is two plus two:');
        echo '</td><td>';
        echo '<input name="turing_test" required></td></tr>';
        echo '<tr><td colspan="2" align="right">';
        echo '<input type="submit" name="sub_btn" value="';
        echo __('Submit registration');
        echo '"';
        echo 'disabled="disabled" onclick="return validateRegForm()" />';
        echo '</td></tr>';
        echo '</table>';
        echo '</form>';
        echo '<p><form method="GET" action="index.php">';
        echo '<input type="submit" value="'.__('Return to Tiny Tiny RSS').'">';
        echo '</form>';
    } elseif ($action == 'do_register') {
        $test = trim($_REQUEST['turing_test']);
        if (empty($_REQUEST['login']) || empty($_REQUEST['email']) || !$test) {
            \SmallSmallRSS\Renderers\Messages::renderError(
                __('Your registration information is incomplete.')
            );
            echo '<p><form method="GET" action="index.php">
                <input type="submit" value="'.__('Return to Tiny Tiny RSS').'">
                </form>';
            return;
        }

        if ($test == 'four' || $test == '4') {
            $is_registered = \SmallSmallRSS\Users::isRegistered($_REQUEST['login']);
            if ($is_registered) {
                \SmallSmallRSS\Renderers\Messages::renderError(
                    __('Sorry, this username is already taken.')
                );
                echo '<p><form method="GET" action="index.php">';
                echo '<input type="submit" value="'.__('Return to Tiny Tiny RSS').'">';
                echo '</form>';
            } else {
                $new_uid = \SmallSmallRSS\Users::create($_REQUEST['login'], $_REQUEST['email']);
                if (!$new_uid) {
                    \SmallSmallRSS\Renderers\Messages::renderError(__('Registration failed.'));
                    echo '<p><form method="GET" action="index.php">';
                    echo '<input type="submit" value="'.__('Return to Tiny Tiny RSS').'">';
                    echo '</form>';
                } else {
                    \SmallSmallRSS\Feeds\newUser($new_uid);
                    $reg_text = "Hi!\n".
                        "\n".
                        "You are receiving this message, because you (or somebody else) have opened\n".
                        "an account at $software_name.\n".
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
                    $rc = $mail->quickMail(
                        $email,
                        '',
                        "Registration information for $software_name",
                        $reg_text,
                        false
                    );

                    if (!$rc) {
                        \SmallSmallRSS\Renderers\Messages::renderError($mail->ErrorInfo);
                    }
                    unset($reg_text);
                    unset($mail);
                    unset($rc);
                    $reg_text = "Hi!\n"
                        . "\n"
                        . "A new user has registered at your $software_name installation.\n"
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
                            "Registration notice for $software_name",
                            $reg_text,
                            false
                        );
                        if (!$rc) {
                            \SmallSmallRSS\Renderers\Messages::renderError($mail->ErrorInfo);
                        }
                    }
                    \SmallSmallRSS\Renderers\Messages::renderNotice(
                        __('Account created successfully.')
                    );
                    echo '<p><form method="GET" action="index.php">';
                    echo '<input type="submit" value="'.__('Return to Tiny Tiny RSS').'">';
                    echo '</form>';

                }

            }
        } else {
            \SmallSmallRSS\Renderers\Messages::renderError(
                'Plese check the form again, you have failed the robot test.'
            );
            echo '<p><form method="GET" action="index.php">';
            echo '<input type="submit" value="'.__('Return to Tiny Tiny RSS').'" />';
            echo '</form>';
        }
    }
} else {
    \SmallSmallRSS\Renderers\Messages::renderNotice(__('New user registrations are currently closed.'));
    echo '<p><form method="GET" action="index.php">';
    echo '<input type="submit" value="'.__('Return to Tiny Tiny RSS').'" />';
    echo '</form>';
}
?>
  </div>
</body>
</html>
