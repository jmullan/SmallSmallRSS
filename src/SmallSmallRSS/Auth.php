<?php
namespace SmallSmallRSS;

class Auth
{
    public static function isSingleUserMode()
    {
        return (bool) \SmallSmallRSS\Config::get('SINGLE_USER_MODE');
    }
    public static function getSalt()
    {
        return substr(bin2hex(self::getRandomBytes(125)), 0, 250);
    }
    public static function getRandomBytes($length)
    {
        if (function_exists('openssl_random_pseudo_bytes')) {
            return openssl_random_pseudo_bytes($length);
        } else {
            $output = '';
            for ($i = 0; $i < $length; $i++) {
                $output .= chr(mt_rand(0, 255));
            }
            return $output;
        }
    }
    public static function encryptPassword($pass, $salt = '', $mode2 = false)
    {
        if ($salt && $mode2) {
            return 'MODE2:' . hash('sha256', $salt . $pass);
        } elseif ($salt) {
            return 'SHA1X:' . sha1("$salt:$pass");
        } else {
            return 'SHA1:' . sha1($pass);
        }
    }

    public static function authenticateSingleUser()
    {
        $_SESSION['uid'] = 1;
        $_SESSION['name'] = 'admin';
        $_SESSION['access_level'] = 10;
        $_SESSION['hide_hello'] = true;
        $_SESSION['hide_logout'] = true;
        $_SESSION['auth_module'] = false;
        if (!$_SESSION['csrf_token']) {
            $_SESSION['csrf_token'] = sha1(uniqid(rand(), true));
        }
        $_SESSION['ip_address'] = $_SERVER['REMOTE_ADDR'];
        \SmallSmallRSS\UserPrefs::initialize($_SESSION['uid'], false);
    }

    public static function authenticateViaPlugins($login, $password, $check_only)
    {
        $user_id = false;
        $plugins = \SmallSmallRSS\PluginHost::getInstance()->getHooks(\SmallSmallRSS\Hooks::AUTH_USER);
        if (!$plugins) {
            \SmallSmallRSS\Logger::log('No authentication plugins!');
        }
        foreach ($plugins as $plugin) {
            $user_id = (int) $plugin->authenticate($login, $password);
            if ($user_id) {
                $auth_module = get_class($plugin);
                break;
            }
        }
        if ($user_id && !$check_only) {
            $_SESSION['auth_module'] = $auth_module;
            $_SESSION['uid'] = $user_id;
            $_SESSION['version'] = \SmallSmallRSS\Constants::VERSION;
            $user_record = \SmallSmallRSS\Users::getByUid($user_id);
            if (!$user_record) {
                \SmallSmallRSS\Logger::log("Did not find user $user_id");
                return false;
            }
            $_SESSION['name'] = $user_record['login'];
            $_SESSION['access_level'] = $user_record['access_level'];
            $_SESSION['csrf_token'] = sha1(uniqid(rand(), true));
            \SmallSmallRSS\Users::markLogin($_SESSION['uid']);
            $_SESSION['ip_address'] = $_SERVER['REMOTE_ADDR'];
            $_SESSION['user_agent'] = sha1($_SERVER['HTTP_USER_AGENT']);
            $_SESSION['pwd_hash'] = $user_record['pwd_hash'];
            $_SESSION['last_version_check'] = time();
            \SmallSmallRSS\UserPrefs::initialize($_SESSION['uid'], false);
            return true;
        }
        return false;
    }

    public static function authenticate($login, $password, $check_only = false)
    {
        if (self::isSingleUserMode()) {
            return self::authenticateSingleUser();
        } else {
            return self::authenticateViaPlugins($login, $password, $check_only);
        }
    }
}
