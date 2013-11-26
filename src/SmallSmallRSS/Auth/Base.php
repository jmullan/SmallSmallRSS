<?php
namespace SmallSmallRSS;

class Auth_Base implements Auth_Interface
{
    public static function checkPassword($owner_uid, $password)
    {
        return false;
    }

    public static function authenticate($login, $password)
    {
        return false;
    }

    // Auto-creates specified user if allowed by system configuration
    // Can be used instead of find_user_by_login() by external auth modules
    public static function autoCreateUser($login, $password = false)
    {
        $user_id = false;
        if ($login && \SmallSmallRSS\Config::get('AUTH_AUTO_CREATE')) {
            $user_id = \SmallSmallRSS\Users::findUserByLogin($login);
            if (!$user_id) {
                $user_id = \SmallSmallRSS\Users::create($login, '', $password);
            }
        }
        return $user_id;
    }
}
