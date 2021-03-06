<?php
class Auth_Internal extends \SmallSmallRSS\Plugin implements \SmallSmallRSS\AuthInterface
{
    const API_VERSION = 2;
    const VERSION = 1.0;
    const NAME = 'Internal Authentication';
    const DESCRIPTION = 'Authenticates against internal tt-rss database';
    const AUTHOR = 'fox';
    const IS_SYSTEM = false;

    public static $provides = array(
        \SmallSmallRSS\Hooks::AUTH_USER
    );

    private function renderOTP($return, $login, $password)
    {
        $renderer = new \SmallSmallRSS\Renderers\CSS();
        ?>
         <!DOCTYPE html>
         <html>
          <head>
            <title><?php echo \SmallSmallRSS\Config::get('SOFTWARE_NAME'); ?></title>
            <?php $renderer->renderStylesheetTag("css/utility.css"); ?>
          </head>
          <body class="otp">
            <div class="content">
              <form action="public.php?return=<?php echo $return ?>" method="POST" class="otpform">
                <input type="hidden" name="op" value="login" />
                <input type="hidden" name="login" value="<?php echo htmlspecialchars($login) ?>" />
                <input type="hidden" name="password" value="<?php echo htmlspecialchars($password) ?>" />
                <label for="otp"><?php echo __("Please enter your one time password:") ?></label>
                <input autocomplete="off" size="6" name="otp" value="" />
                <input type="submit" value="Continue" />
              </form>
            </div>
            <script type="text/javascript">
              document.forms[0].otp.focus();
            </script>
        <?php
    }


    public function authenticate($login, $password)
    {
        $pwd_hash1 = \SmallSmallRSS\Auth::encryptPassword($password);
        $pwd_hash2 = \SmallSmallRSS\Auth::encryptPassword($password, $login);
        $login = \SmallSmallRSS\Database::escapeString($login);
        if (!empty($_REQUEST["otp"]) && \SmallSmallRSS\Sanity::getSchemaVersion() > 96) {
            $otp = \SmallSmallRSS\Database::escapeString($_REQUEST["otp"]);
            if (!\SmallSmallRSS\Config::get('AUTH_DISABLE_OTP')) {
                $result = \SmallSmallRSS\Database::query(
                    "SELECT otp_enabled, salt
                     FROM ttrss_users
                     WHERE login = '$login'"
                );
                if (\SmallSmallRSS\Database::numRows($result) > 0) {
                    $base32 = new \OTPHP\Base32();
                    $otp_enabled = \SmallSmallRSS\Database::fromSQLBool(\SmallSmallRSS\Database::fetchResult($result, 0, "otp_enabled"));
                    $secret = $base32->encode(sha1(\SmallSmallRSS\Database::fetchResult($result, 0, "salt")));
                    $topt = new \OTPHP\TOTP($secret);
                    $otp_check = $topt->now();
                    if ($otp_enabled) {
                        if ($otp) {
                            if ($otp != $otp_check) {
                                return false;
                            }
                        } else {
                            $return = urlencode($_REQUEST["return"]);
                            $this->renderOTP($return, $login, $password);
                            exit;
                        }
                    }
                }
            }
        }

        if (\SmallSmallRSS\Sanity::getSchemaVersion() > 87) {

            $result = \SmallSmallRSS\Database::query(
                "SELECT salt FROM ttrss_users WHERE
                 login = '$login'"
            );
            if (\SmallSmallRSS\Database::numRows($result) != 1) {
                return false;
            }
            $salt = \SmallSmallRSS\Database::fetchResult($result, 0, "salt");
            if ($salt == "") {
                $query = "SELECT id
                      FROM ttrss_users
                          WHERE
                              login = '$login'
                              AND (pwd_hash = '$pwd_hash1'
                                   OR pwd_hash = '$pwd_hash2')";
                // verify and upgrade password to new salt base
                $result = \SmallSmallRSS\Database::query($query);
                if (\SmallSmallRSS\Database::numRows($result) == 1) {
                    // upgrade password to MODE2
                    $salt = \SmallSmallRSS\Auth::getSalt();
                    $pwd_hash = \SmallSmallRSS\Auth::encryptPassword($password, $salt, true);
                    \SmallSmallRSS\Database::query(
                        "UPDATE ttrss_users
                         SET pwd_hash = '$pwd_hash', salt = '$salt'
                         WHERE login = '$login'"
                    );
                    $query = "SELECT id
                    FROM ttrss_users WHERE
                        login = '$login' AND pwd_hash = '$pwd_hash'";
                } else {
                    return false;
                }

            } else {
                $pwd_hash = \SmallSmallRSS\Auth::encryptPassword($password, $salt, true);
                $query = "SELECT id
                          FROM ttrss_users
                          WHERE login = '$login' AND pwd_hash = '$pwd_hash'";
            }

        } else {
            $query = "SELECT id
                      FROM ttrss_users
                      WHERE
                          login = '$login'
                          AND (
                              pwd_hash = '$pwd_hash1' OR
                              pwd_hash = '$pwd_hash2'
                          )";
        }
        $result = \SmallSmallRSS\Database::query($query);
        if (\SmallSmallRSS\Database::numRows($result) == 1) {
            return \SmallSmallRSS\Database::fetchResult($result, 0, "id");
        }
        return false;
    }

    public function checkPassword($owner_uid, $password)
    {
        $owner_uid = \SmallSmallRSS\Database::escapeString($owner_uid);
        $result = \SmallSmallRSS\Database::query(
            "SELECT salt, login
             FROM ttrss_users
             WHERE id = '$owner_uid'"
        );

        $salt = \SmallSmallRSS\Database::fetchResult($result, 0, "salt");
        $login = \SmallSmallRSS\Database::fetchResult($result, 0, "login");

        if (!$salt) {
            $password_hash1 = \SmallSmallRSS\Auth::encryptPassword($password);
            $password_hash2 = \SmallSmallRSS\Auth::encryptPassword($password, $login);

            $query = "SELECT id FROM ttrss_users WHERE
                id = '$owner_uid' AND (pwd_hash = '$password_hash1' OR
                pwd_hash = '$password_hash2')";

        } else {
            $password_hash = \SmallSmallRSS\Auth::encryptPassword($password, $salt, true);

            $query = "SELECT id FROM ttrss_users WHERE
                id = '$owner_uid' AND pwd_hash = '$password_hash'";
        }

        $result = \SmallSmallRSS\Database::query($query);

        return \SmallSmallRSS\Database::numRows($result) != 0;
    }

    public function change_password($owner_uid, $old_password, $new_password)
    {
        $owner_uid = \SmallSmallRSS\Database::escapeString($owner_uid);

        if ($this->checkPassword($owner_uid, $old_password)) {

            $new_salt = \SmallSmallRSS\Auth::getSalt();
            $new_password_hash = \SmallSmallRSS\Auth::encryptPassword($new_password, $new_salt, true);

            \SmallSmallRSS\Database::query(
                "UPDATE ttrss_users
                 SET
                    pwd_hash = '$new_password_hash',
                    salt = '$new_salt',
                    otp_enabled = false
                 WHERE id = '$owner_uid'"
            );

            $_SESSION["pwd_hash"] = $new_password_hash;

            return __("Password has been changed.");
        } else {
            return "ERROR: ".__('Old password is incorrect.');
        }
    }
}
