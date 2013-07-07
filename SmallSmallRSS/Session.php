<?php
namespace SmallSmallRSS;
class Session {
    static $session_expire = 86400;
    static $session_name = 'ttrss_sid';

    static public function init() {
        self::$session_expire = max(SESSION_COOKIE_LIFETIME, 86400);
        if (defined('TTRSS_SESSION_NAME')) {
            self::$session_name = TTRSS_SESSION_NAME;
        }

        if (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] == "on") {
            self::$session_name .= "_ssl";
            ini_set("session.cookie_secure", true);
        }

        ini_set("session.auto_start", false);
        ini_set("session.gc_probability", 75);
        ini_set("session.name", self::$session_name);
        ini_set("session.use_only_cookies", true);
        ini_set("session.gc_maxlifetime", self::$session_expire);
        ini_set("session.cookie_lifetime", min(0, SESSION_COOKIE_LIFETIME));
        if (!SINGLE_USER_MODE) {
            session_set_save_handler(
                "ttrss_open",
                "ttrss_close", "ttrss_read", "ttrss_write",
                "ttrss_destroy", "ttrss_gc");
            register_shutdown_function('session_write_close');
        }
        session_start();
    }
}
