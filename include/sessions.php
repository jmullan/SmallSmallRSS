<?php
// Original from http://www.daniweb.com/code/snippet43.html
require_once __DIR__ . "/../config.php";
require_once __DIR__ . '/../SmallSmallRSS/bootstrap.php';

require_once __DIR__ . "/../classes/db.php";
require_once __DIR__ . "/errorhandler.php";
require_once __DIR__ . "/../lib/accept-to-gettext.php";
require_once __DIR__ . "/../lib/gettext/gettext.inc";
require_once __DIR__ . "/version.php";



function validate_session() {
    if (SINGLE_USER_MODE) {
        return true;
    }

    if (!isset($_COOKIE[session_name()]) || !isset($_SESSION) || !isset($_SESSION["version"])) {
        return false;
    }

    if (VERSION_STATIC != $_SESSION["version"]) {
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

    if ($_SESSION["ref_schema_version"] != SmallSmallRSS\Sanity::get_schema_version(true))
        return false;

    if (sha1($_SERVER['HTTP_USER_AGENT']) != $_SESSION["user_agent"])
        return false;

    if ($_SESSION["uid"]) {
        $result = Db::get()->query(
            "SELECT pwd_hash FROM ttrss_users WHERE id = '".$_SESSION["uid"]."'");

        // user not found
        if (Db::get()->num_rows($result) == 0) {
            return false;
        } else {
            $pwd_hash = Db::get()->fetch_result($result, 0, "pwd_hash");

            if ($pwd_hash != $_SESSION["pwd_hash"]) {
                return false;
            }
        }
    }

    return true;
}


function ttrss_open($s, $n) {
    return true;
}

function ttrss_read($id) {
    $res = Db::get()->query("SELECT data FROM ttrss_sessions WHERE id='$id'");

    if (Db::get()->num_rows($res) != 1) {

        $expire = time() + \SmallSmallRSS\Session::$session_expire;

        Db::get()->query("INSERT INTO ttrss_sessions (id, data, expire)
					VALUES ('$id', '', '$expire')");

        return "";
    } else {
        return base64_decode(Db::get()->fetch_result($res, 0, "data"));
    }

}

function ttrss_write($id, $data) {
    $data = base64_encode($data);
    $expire = time() + \SmallSmallRSS\Session::$session_expire;
    Db::get()->query("UPDATE ttrss_sessions SET data='$data', expire='$expire' WHERE id='$id'");
    return true;
}

function ttrss_close() {
    return true;
}

function ttrss_destroy($id) {
    Db::get()->query("DELETE FROM ttrss_sessions WHERE id = '$id'");

    return true;
}

function ttrss_gc($expire) {
    Db::get()->query("DELETE FROM ttrss_sessions WHERE expire < " . time());
}

\SmallSmallRSS\Session::init();
