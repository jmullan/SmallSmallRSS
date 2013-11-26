<?php
namespace SmallSmallRSS;

class Users
{
    public static function count()
    {
        $result = \SmallSmallRSS\Database::query("SELECT COUNT(*) AS cu FROM ttrss_users");
        return \SmallSmallRSS\Database::fetch_result($result, 0, "cu");
    }
    public static function clearExpired()
    {
        if (\SmallSmallRSS\Config::get('DB_TYPE') == "pgsql") {
            \SmallSmallRSS\Database::query(
                "DELETE FROM ttrss_users
                 WHERE
                     last_login IS NULL
                     AND created < NOW() - INTERVAL '1 day'
                     AND access_level = 0"
            );
        } else {
            \SmallSmallRSS\Database::query(
                "DELETE FROM ttrss_users
                 WHERE
                     last_login IS NULL
                     AND created < DATE_SUB(NOW(), INTERVAL 1 DAY)
                     AND access_level = 0"
            );
        }
    }
    public static function isRegistered($login)
    {
        $login = \SmallSmallRSS\Database::escape_string(mb_strtolower(trim($login)));
        $result = \SmallSmallRSS\Database::query(
            "SELECT id
             FROM ttrss_users
             WHERE LOWER(login) = LOWER('$login')"
        );
        return \SmallSmallRSS\Database::num_rows($result) > 0;
    }

    public static function create($login, $email)
    {
        $login = \SmallSmallRSS\Database::escape_string(mb_strtolower(trim($login)));
        $email = \SmallSmallRSS\Database::escape_string(trim($_REQUEST["email"]));
        $password = make_password();
        $salt = substr(bin2hex(get_random_bytes(125)), 0, 250);
        $pwd_hash = encrypt_password($password, $salt, true);
        \SmallSmallRSS\Database::query(
            "INSERT INTO ttrss_users
             (login,pwd_hash,access_level,last_login, email, created, salt)
             VALUES
             ('$login', '$pwd_hash', 0, null, '$email', NOW(), '$salt')"
        );
        $result = \SmallSmallRSS\Database::query(
            "SELECT id FROM ttrss_users
             WHERE
                 login = '$login'
                 AND pwd_hash = '$pwd_hash'"
        );
        if (\SmallSmallRSS\Database::num_rows($result) != 1) {
            return false;
        }
        return \SmallSmallRSS\Database::fetch_result($result, 0, "id");
    }
}
