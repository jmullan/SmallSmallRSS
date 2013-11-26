<?php

class Auth_Remote extends \SmallSmallRSS\Plugin implements \SmallSmallRSS\Auth_Interface
{
    private $host;
    private $base;

    const API_VERSION = 2;
    const VERSION = 1.0;
    const NAME = 'Remote Authentication';
    const DESCRIPTION = 'Authenticates against remote password (e.g. supplied by Apache)';
    const AUTHOR = 'fox';
    const IS_SYSTEM = false;

    public static $provides = array(
        \SmallSmallRSS\Hooks::AUTH_USER
    );

    public function __construct($pluginhost)
    {
        $this->host = $pluginhost;
        $this->base = new \SmallSmallRSS\Auth_Base();
    }

    public function getLoginBySSLCertificate()
    {
        $cert_serial = \SmallSmallRSS\Database::escape_string(get_ssl_certificate_id());
        if ($cert_serial) {
            $result = \SmallSmallRSS\Database::query(
                "SELECT login
                 FROM ttrss_user_prefs, ttrss_users
                 WHERE
                     pref_name = 'SSL_CERT_SERIAL'
                     AND value = '$cert_serial'
                     AND owner_uid = ttrss_users.id"
            );
            if (\SmallSmallRSS\Database::num_rows($result) != 0) {
                return \SmallSmallRSS\Database::escape_string(
                    \SmallSmallRSS\Database::fetch_result($result, 0, "login")
                );
            }
        }
        return false;
    }

    public function authenticate($login, $password)
    {
        $try_login = \SmallSmallRSS\Database::escape_string($_SERVER["REMOTE_USER"]);
        // php-cgi
        if (!$try_login) {
            $try_login = \SmallSmallRSS\Database::escape_string($_SERVER["REDIRECT_REMOTE_USER"]);
        }
        if (!$try_login) {
            $try_login = $this->getLoginBySSLCertificate();
        }
        if ($try_login) {
            $user_id = $this->base->autoCreateUser($try_login, $password);
            if ($user_id) {
                $_SESSION["fake_login"] = $try_login;
                $_SESSION["fake_password"] = "******";
                $_SESSION["hide_hello"] = true;
                $_SESSION["hide_logout"] = true;
                // LemonLDAP can send user informations via HTTP HEADER
                if (\SmallSmallRSS\Config::get('AUTH_AUTO_CREATE')) {
                    // update user name
                    $fullname = (
                        $_SERVER['HTTP_USER_NAME']
                        ? $_SERVER['HTTP_USER_NAME']
                        : $_SERVER['AUTHENTICATE_CN']
                    );
                    if ($fullname) {
                        $fullname = \SmallSmallRSS\Database::escape_string($fullname);
                        \SmallSmallRSS\Database::query(
                            "UPDATE ttrss_users
                             SET full_name = '$fullname'
                             WHERE id = " . $user_id
                        );
                    }
                    // update user mail
                    $email = $_SERVER['HTTP_USER_MAIL'] ? $_SERVER['HTTP_USER_MAIL'] : $_SERVER['AUTHENTICATE_MAIL'];
                    if ($email) {
                        $email = \SmallSmallRSS\Database::escape_string($email);
                        \SmallSmallRSS\Database::query(
                            "UPDATE ttrss_users SET email = '$email' WHERE id = " . $user_id
                        );
                    }
                }
                return $user_id;
            }
        }
        return false;
    }
}
