<?php
namespace SmallSmallRSS;

class Crypt
{
    public static function isEnabled()
    {
        return strlen(\SmallSmallRSS\Config::get('FEED_CRYPT_KEY')) > 0;
    }
    public static function de($str)
    {
        $key = hash('SHA256', \SmallSmallRSS\Config::get('FEED_CRYPT_KEY'), true);
        $pair = explode(':', $str);
        if (count($pair) == 2) {
            @$iv = base64_decode($pair[0]);
            @$encstr = base64_decode($pair[1]);
            if ($iv && $encstr) {
                $str = mcrypt_decrypt(MCRYPT_RIJNDAEL_128, $key, $encstr, MCRYPT_MODE_CBC, $iv);
                if ($str) {
                    return rtrim($str);
                }
            }
        }
        return false;
    }
    public static function en($str)
    {
        $key = hash('SHA256', \SmallSmallRSS\Config::get('FEED_CRYPT_KEY'), true);
        $iv = mcrypt_create_iv(mcrypt_get_iv_size(MCRYPT_RIJNDAEL_128, MCRYPT_MODE_CBC), MCRYPT_RAND);
        $encstr = mcrypt_encrypt(MCRYPT_RIJNDAEL_128, $key, $str, MCRYPT_MODE_CBC, $iv);
        $iv_base64 = base64_encode($iv);
        $encstr_base64 = base64_encode($encstr);
        return "$iv_base64:$encstr_base64";
    }
}
