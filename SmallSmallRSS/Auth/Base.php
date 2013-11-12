<?php
namespace SmallSmallRSS;

class Auth_Base implements Auth_Interface
{
    function check_password($owner_uid, $password)
    {
        return false;
    }

    function authenticate($login, $password)
    {
        return false;
    }

    // Auto-creates specified user if allowed by system configuration
    // Can be used instead of find_user_by_login() by external auth modules
    function auto_create_user($login, $password = false)
    {
        if ($login && defined('AUTH_AUTO_CREATE') && AUTH_AUTO_CREATE) {
            $user_id = $this->find_user_by_login($login);

            if (!$password) {  $password = make_password();
            }

            if (!$user_id) {
                $login = \SmallSmallRSS\Database::escape_string($login);
                $salt = substr(bin2hex(get_random_bytes(125)), 0, 250);
                $pwd_hash = encrypt_password($password, $salt, true);

                $query = "INSERT INTO ttrss_users
			  (login,access_level,last_login,created,pwd_hash,salt)
			  VALUES ('$login', 0, null, NOW(), '$pwd_hash','$salt')";

                \SmallSmallRSS\Database::query($query);

                return $this->find_user_by_login($login);

            } else {
                return $user_id;
            }
        }

        return $this->find_user_by_login($login);
    }

    function find_user_by_login($login)
    {
        $login = \SmallSmallRSS\Database::escape_string($login);

        $result = \SmallSmallRSS\Database::query(
            "SELECT id FROM ttrss_users WHERE
			login = '$login'"
        );

        if (\SmallSmallRSS\Database::num_rows($result) > 0) {
            return \SmallSmallRSS\Database::fetch_result($result, 0, "id");
        } else {
            return false;
        }

    }
}
