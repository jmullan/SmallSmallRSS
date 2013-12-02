<?php
namespace SmallSmallRSS;

// Not sure what the following line refers to:
// Original from http://www.daniweb.com/code/snippet43.html

\SmallSmallRSS\ErrorHandler::register();

class Session
{
    public static $session_expire = 86400;
    public static $session_name = 'ttrss_sid';

    public static function init($session_name = null)
    {
        self::$session_expire = max(\SmallSmallRSS\Config::get('SESSION_COOKIE_LIFETIME'), 86400);
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
        ini_set(
            'session.cookie_lifetime',
            min(0, \SmallSmallRSS\Config::get('SESSION_COOKIE_LIFETIME'))
        );
        if (!\SmallSmallRSS\Auth::is_single_user_mode()) {
            session_set_save_handler(
                '\SmallSmallRSS\Sessions::open',
                '\SmallSmallRSS\Sessions::close',
                '\SmallSmallRSS\Sessions::read',
                '\SmallSmallRSS\Sessions::write',
                '\SmallSmallRSS\Sessions::destroy',
                '\SmallSmallRSS\Sessions::gc'
            );
            register_shutdown_function('session_write_close');
        }
        session_start();
    }

    public static function validate()
    {
        if (\SmallSmallRSS\Auth::is_single_user_mode()) {
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
                $check_ip = substr($check_ip, 0, strrpos($check_ip, '.')+1);
                break;
            case 2:
                $check_ip = substr($check_ip, 0, strrpos($check_ip, '.'));
                $check_ip = substr($check_ip, 0, strrpos($check_ip, '.')+1);
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

    public static function open($s, $n)
    {
        return true;
    }

    public static function read($id)
    {
        $res = \SmallSmallRSS\Database::query(
            "SELECT data
             FROM ttrss_sessions
             WHERE id='$id'"
        );
        if (\SmallSmallRSS\Database::num_rows($res) != 1) {
            $expire = time() + \SmallSmallRSS\Sessions::$session_expire;
            \SmallSmallRSS\Database::query(
                "INSERT INTO ttrss_sessions
                 (id, data, expire)
                 VALUES ('$id', '', $expire)"
            );
            return '';
        } else {
            return base64_decode(\SmallSmallRSS\Database::fetch_result($res, 0, 'data'));
        }

    }

    public static function write($id, $data)
    {
        $data = base64_encode($data);
        $expire = time() + \SmallSmallRSS\Sessions::$session_expire;
        \SmallSmallRSS\Database::query(
            "UPDATE ttrss_sessions
             SET
                data='$data',
                expire=$expire
             WHERE id='$id'"
        );
        return true;
    }

    public static function close()
    {
        return true;
    }

    public static function destroy($id)
    {
        \SmallSmallRSS\Database::query(
            "DELETE FROM ttrss_sessions
             WHERE id = '$id'"
        );

        return true;
    }

    public static function gc($expire)
    {
        $expire = time();
        \SmallSmallRSS\Database::query(
            'DELETE FROM ttrss_sessions
             WHERE expire < $expire'
        );
    }
}
