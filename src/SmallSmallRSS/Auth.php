<?php
namespace SmallSmallRSS;

class Auth
{
    public static function is_single_user_mode()
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
}
