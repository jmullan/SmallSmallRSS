<?php
namespace SmallSmallRSS;
// Original from http://www.daniweb.com/code/snippet43.html
require_once __DIR__ . "/../config.php";
require_once __DIR__ . "/../classes/db.php";
require_once __DIR__ . "/../lib/accept-to-gettext.php";
require_once __DIR__ . "/../lib/gettext/gettext.inc";

\SmallSmallRSS\ErrorHandler::register();

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
                '\SmallSmallRSS\Session::open',
                '\SmallSmallRSS\Session::close',
                '\SmallSmallRSS\Session::read',
                '\SmallSmallRSS\Session::write',
                '\SmallSmallRSS\Session::destroy',
                '\SmallSmallRSS\Session::gc'
            );
            register_shutdown_function('session_write_close');
        }
        session_start();
    }

    function validate() {
        if (SINGLE_USER_MODE) {
            return true;
        }

        if (!isset($_COOKIE[session_name()]) || !isset($_SESSION) || !isset($_SESSION["version"])) {
            return false;
        }

        if (\SmallSmallRSS\Constants::VERSION != $_SESSION["version"]) {
            return false;
        }

        $check_ip = $_SESSION['ip_address'];

        switch (SESSION_CHECK_ADDRESS) {
            case 0:
                $check_ip = '';
                break;
            case 1:
                $check_ip = substr($check_ip, 0, strrpos($check_ip, '.')+1);
                break;
            case 2:
                $check_ip = substr($check_ip, 0, strrpos($check_ip, '.'));
                $check_ip = substr($check_ip, 0, strrpos($check_ip, '.')+1);
                break;
        };

        if ($check_ip && strpos($_SERVER['REMOTE_ADDR'], $check_ip) !== 0) {
            $_SESSION["login_error_msg"] =
                __("Session failed to validate (incorrect IP)");
            return false;
        }

        if ($_SESSION["ref_schema_version"] != \SmallSmallRSS\Sanity::get_schema_version(true)) {
            return false;
        }

        if (sha1($_SERVER['HTTP_USER_AGENT']) != $_SESSION["user_agent"]) {
            return false;
        }
        if ($_SESSION["uid"]) {
            $result = \SmallSmallRSS\Database::query(
                "SELECT pwd_hash FROM ttrss_users WHERE id = '".$_SESSION["uid"]."'");

            // user not found
            if (\SmallSmallRSS\Database::num_rows($result) == 0) {
                return false;
            } else {
                $pwd_hash = \SmallSmallRSS\Database::fetch_result($result, 0, "pwd_hash");

                if ($pwd_hash != $_SESSION["pwd_hash"]) {
                    return false;
                }
            }
        }

        return true;
    }


    function open($s, $n) {
        return true;
    }

    function read($id) {
        $res = \SmallSmallRSS\Database::query("SELECT data FROM ttrss_sessions WHERE id='$id'");

        if (\SmallSmallRSS\Database::num_rows($res) != 1) {

            $expire = time() + \SmallSmallRSS\Session::$session_expire;

            \SmallSmallRSS\Database::query("INSERT INTO ttrss_sessions (id, data, expire)
					VALUES ('$id', '', '$expire')");

            return "";
        } else {
            return base64_decode(\SmallSmallRSS\Database::fetch_result($res, 0, "data"));
        }

    }

    function write($id, $data) {
        $data = base64_encode($data);
        $expire = time() + \SmallSmallRSS\Session::$session_expire;
        \SmallSmallRSS\Database::query("UPDATE ttrss_sessions SET data='$data', expire='$expire' WHERE id='$id'");
        return true;
    }

    function close() {
        return true;
    }

    function destroy($id) {
        \SmallSmallRSS\Database::query("DELETE FROM ttrss_sessions WHERE id = '$id'");

        return true;
    }

    function gc($expire) {
        \SmallSmallRSS\Database::query("DELETE FROM ttrss_sessions WHERE expire < " . time());
    }

}
