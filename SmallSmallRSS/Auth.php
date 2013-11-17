<?php
namespace SmallSmallRSS;

class Auth {
    public static function is_single_user_mode() {
        return (bool) \SmallSmallRSS\Config::get('SINGLE_USER_MODE');
    }
}