<?php
namespace SmallSmallRSS;

class Sessions
{
    public static $session_expire = null;
    public static $session_name = 'ttrss_sid';
    private static $initialized = false;

    public static function init($session_name = null)
    {
        if (self::$initialized) {
            return;
        }
        self::$initialized = true;
        self::$session_expire = \SmallSmallRSS\Config::get('SESSION_COOKIE_LIFETIME');
        if (!is_null($session_name)) {
            self::$session_name = $session_name;
        }

        if (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] == 'on') {
            self::$session_name .= '_ssl';
            ini_set('session.cookie_secure', true);
        }

        ini_set('session.auto_start', false);
        ini_set('session.gc_probability', 75);
        ini_set('session.name', self::$session_name);
        ini_set('session.use_only_cookies', true);
        ini_set('session.gc_maxlifetime', self::$session_expire);
        ini_set('session.cookie_lifetime', self::$session_expire);
        $session_dsn = 'mysql:dbname=jmullan_sessions;host=127.0.0.1';
        $handler = new \Jmullan\Sessions\Handler();
        $link = \SmallSmallRSS\Database::getLink();
        $handler->setDbConnection($link);
        $handler->setDBTable('sessions');
        session_set_save_handler($handler, true);
        session_start();
    }

    public static function validate()
    {
        if (\SmallSmallRSS\Auth::isSingleUserMode()) {
            return true;
        }

        if (!isset($_COOKIE[session_name()]) || !isset($_SESSION) || !isset($_SESSION['version'])) {
            return false;
        }

        if (\SmallSmallRSS\Constants::VERSION != $_SESSION['version']) {
            return false;
        }

        $check_ip = $_SESSION['ip_address'];

        switch (\SmallSmallRSS\Config::get('SESSION_CHECK_ADDRESS')) {
            case 0:
                $check_ip = '';
                break;
            case 1:
                $check_ip = substr($check_ip, 0, strrpos($check_ip, '.') + 1);
                break;
            case 2:
                $check_ip = substr($check_ip, 0, strrpos($check_ip, '.'));
                $check_ip = substr($check_ip, 0, strrpos($check_ip, '.') + 1);
                break;
        };

        if ($check_ip && strpos($_SERVER['REMOTE_ADDR'], $check_ip) !== 0) {
            $_SESSION['login_error_msg'] = __('Session failed to validate (incorrect IP)');
            return false;
        }

        if ($_SESSION['ref_schema_version'] != \SmallSmallRSS\Sanity::getSchemaVersion(true)) {
            return false;
        }

        if (sha1($_SERVER['HTTP_USER_AGENT']) != $_SESSION['user_agent']) {
            return false;
        }
        if ($_SESSION['uid']) {
            $user_record = \SmallSmallRSS\Users::getByUid($_SESSION['uid']);
            if (!$user_record) {
                // user not found
                return false;
            }
            if ($user_record['pwd_hash'] != $_SESSION['pwd_hash']) {
                return false;
            }
        }
        return true;
    }

    public static function validateCSRF($csrf_token)
    {
        return $csrf_token == $_SESSION['csrf_token'];
    }

    public static function logout()
    {
        session_destroy();
        if (isset($_COOKIE[session_name()])) {
            setcookie(session_name(), '', time() - 42000, '/');
        }
    }
}
