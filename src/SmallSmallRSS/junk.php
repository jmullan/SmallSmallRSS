<?php
function _debug($msg, $show = true, $is_debug = true)
{
    \SmallSmallRSS\Logger::debug($msg, $show, $is_debug);
}

function initialize_user_prefs($uid, $profile = false)
{
    \SmallSmallRSS\Database::query("BEGIN");
    $active_prefs = \SmallSmallRSS\UserPrefs::getActive($uid, $profile);
    $default_prefs = \SmallSmallRSS\Prefs::getAll();
    foreach ($default_prefs as $pref_name => $value) {
        if (!isset($active_prefs[$pref_name])) {
            \SmallSmallRSS\UserPrefs::insert($pref_name, $value, $uid, $profile);
        }
    }
    \SmallSmallRSS\Database::query("COMMIT");
}

function get_ssl_certificate_id()
{
    if (!empty($_SERVER["REDIRECT_SSL_CLIENT_M_SERIAL"])) {
        return sha1(
            $_SERVER["REDIRECT_SSL_CLIENT_M_SERIAL"]
            . $_SERVER["REDIRECT_SSL_CLIENT_V_START"]
            . $_SERVER["REDIRECT_SSL_CLIENT_V_END"]
            . $_SERVER["REDIRECT_SSL_CLIENT_S_DN"]
        );
    }
    return "";
}

function authenticate_user($login, $password, $check_only = false)
{
    if (!\SmallSmallRSS\Auth::is_single_user_mode()) {
        $user_id = false;
        $plugins = \SmallSmallRSS\PluginHost::getInstance()->get_hooks(\SmallSmallRSS\Hooks::AUTH_USER);
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
            $_SESSION["auth_module"] = $auth_module;
            $_SESSION["uid"] = $user_id;
            $_SESSION["version"] = \SmallSmallRSS\Constants::VERSION;
            $result = \SmallSmallRSS\Database::query(
                "SELECT
                     login,
                     access_level,
                     pwd_hash
                 FROM ttrss_users
                 WHERE id = '$user_id'"
            );
            $_SESSION["name"] = \SmallSmallRSS\Database::fetch_result($result, 0, "login");
            $_SESSION["access_level"] = \SmallSmallRSS\Database::fetch_result($result, 0, "access_level");
            $_SESSION["csrf_token"] = sha1(uniqid(rand(), true));
            \SmallSmallRSS\Database::query("UPDATE ttrss_users SET last_login = NOW() WHERE id = " . $_SESSION["uid"]);
            $_SESSION["ip_address"] = $_SERVER["REMOTE_ADDR"];
            $_SESSION["user_agent"] = sha1($_SERVER['HTTP_USER_AGENT']);
            $_SESSION["pwd_hash"] = \SmallSmallRSS\Database::fetch_result($result, 0, "pwd_hash");
            $_SESSION["last_version_check"] = time();
            initialize_user_prefs($_SESSION["uid"]);
            return true;
        }
        return false;
    } else {
        $_SESSION["uid"] = 1;
        $_SESSION["name"] = "admin";
        $_SESSION["access_level"] = 10;
        $_SESSION["hide_hello"] = true;
        $_SESSION["hide_logout"] = true;
        $_SESSION["auth_module"] = false;
        if (!$_SESSION["csrf_token"]) {
            $_SESSION["csrf_token"] = sha1(uniqid(rand(), true));
        }
        $_SESSION["ip_address"] = $_SERVER["REMOTE_ADDR"];
        initialize_user_prefs($_SESSION["uid"]);
        return true;
    }
}

function logout_user()
{
    session_destroy();
    if (isset($_COOKIE[session_name()])) {
        setcookie(session_name(), '', time()-42000, '/');
    }
}

function validate_csrf($csrf_token)
{
    return $csrf_token == $_SESSION['csrf_token'];
}

function load_user_plugins($owner_uid)
{
    if ($owner_uid) {
        $plugins = \SmallSmallRSS\DBPrefs::read("_ENABLED_PLUGINS", $owner_uid);
        \SmallSmallRSS\PluginHost::getInstance()->load(
            $plugins,
            \SmallSmallRSS\PluginHost::KIND_USER,
            $owner_uid
        );
        if (\SmallSmallRSS\Sanity::getSchemaVersion() > 100) {
            \SmallSmallRSS\PluginHost::getInstance()->load_data();
        }
    }
}

function login_sequence()
{
    if (\SmallSmallRSS\Auth::is_single_user_mode()) {
        authenticate_user("admin", null);
        load_user_plugins($_SESSION["uid"]);
    } else {
        if (!\SmallSmallRSS\Session::validate()) {
            $_SESSION["uid"] = false;
        }
        if (!$_SESSION["uid"]) {
            if (\SmallSmallRSS\Config::get('AUTH_AUTO_LOGIN') && authenticate_user(null, null)) {
                $_SESSION["ref_schema_version"] = \SmallSmallRSS\Sanity::getSchemaVersion(true);
            } else {
                authenticate_user(null, null, true);
            }
            if (!$_SESSION["uid"]) {
                @session_destroy();
                setcookie(session_name(), '', time()-42000, '/');
                $renderer = new \SmallSmallRSS\Renderers\LoginPage();
                $renderer->render();
                exit;
            }
        } else {
            /* bump login timestamp */
            \SmallSmallRSS\Database::query(
                "UPDATE ttrss_users
                 SET last_login = NOW()
                 WHERE id = " . $_SESSION["uid"]
            );
            $_SESSION["last_login_update"] = time();
        }

        if ($_SESSION["uid"]) {
            \SmallSmallRSS\Locale::startupGettext();
            load_user_plugins($_SESSION["uid"]);
            /* cleanup ccache */
            \SmallSmallRSS\CountersCache::cleanup($_SESSION["uid"]);
            \SmallSmallRSS\CatCountersCache::cleanup($_SESSION['uid']);
        }
    }
}

function truncate_string($str, $max_len, $suffix = '&hellip;')
{
    if (mb_strlen($str, "utf-8") > $max_len - 3) {
        return mb_substr($str, 0, $max_len, "utf-8") . $suffix;
    } else {
        return $str;
    }
}

function convert_timestamp($timestamp, $source_tz, $dest_tz)
{
    try {
        $source_tz = new DateTimeZone($source_tz);
    } catch (Exception $e) {
        $source_tz = new DateTimeZone('UTC');
    }
    try {
        $dest_tz = new DateTimeZone($dest_tz);
    } catch (Exception $e) {
        $dest_tz = new DateTimeZone('UTC');
    }
    $dt = new DateTime(date('Y-m-d H:i:s', $timestamp), $source_tz);
    return $dt->format('U') + $dest_tz->getOffset($dt);
}

function make_local_datetime($timestamp, $long, $owner_uid = false, $no_smart_dt = false)
{
    static $utc_tz = null;
    if (is_null($utc_tz)) {
        $utc_tz = new DateTimeZone('UTC');
    }
    if (!$owner_uid) {
        $owner_uid = $_SESSION['uid'];
    }
    if (!$timestamp) {
        $timestamp = '1970-01-01 0:00';
    }
    $timestamp = substr($timestamp, 0, 19);
    // We store date in UTC internally
    $dt = new DateTime($timestamp, $utc_tz);
    $user_tz_string = \SmallSmallRSS\DBPrefs::read('USER_TIMEZONE', $owner_uid);
    if ($user_tz_string != 'Automatic') {
        try {
            $user_tz = new DateTimeZone($user_tz_string);
        } catch (Exception $e) {
            $user_tz = $utc_tz;
        }
        $tz_offset = $user_tz->getOffset($dt);
    } else {
        $tz_offset = (int) -$_SESSION["clientTzOffset"];
    }

    $user_timestamp = $dt->format('U') + $tz_offset;

    if (!$no_smart_dt) {
        return smart_date_time($user_timestamp, $tz_offset, $owner_uid);
    } else {
        if ($long) {
            $format = \SmallSmallRSS\DBPrefs::read('LONG_DATE_FORMAT', $owner_uid);
        } else {
            $format = \SmallSmallRSS\DBPrefs::read('SHORT_DATE_FORMAT', $owner_uid);
        }
        return date($format, $user_timestamp);
    }
}

function smart_date_time($timestamp, $tz_offset = 0, $owner_uid = false)
{
    if (!$owner_uid) {
        $owner_uid = $_SESSION['uid'];
    }
    if (date("Y.m.d", $timestamp) == date("Y.m.d", time() + $tz_offset)) {
        return date("G:i", $timestamp);
    } elseif (date("Y", $timestamp) == date("Y", time() + $tz_offset)) {
        $format = \SmallSmallRSS\DBPrefs::read('SHORT_DATE_FORMAT', $owner_uid);
        return date($format, $timestamp);
    } else {
        $format = \SmallSmallRSS\DBPrefs::read('LONG_DATE_FORMAT', $owner_uid);
        return date($format, $timestamp);
    }
}

function sql_bool_to_bool($s)
{
    if ($s == "t" || $s == "1" || strtolower($s) == "true") {
        return true;
    } else {
        return false;
    }
}

function bool_to_sql_bool($s)
{
    if ($s) {
        return "true";
    } else {
        return "false";
    }
}

function sql_random_function()
{
    if (\SmallSmallRSS\Config::get('DB_TYPE') == "mysql") {
        return "RAND()";
    } else {
        return "RANDOM()";
    }
}

function getAllCounters()
{
    $data = getGlobalCounters();
    $data = array_merge($data, getVirtCounters());
    $data = array_merge($data, getLabelCounters());
    $data = array_merge($data, getFeedCounters());
    $data = array_merge($data, getCategoryCounters());
    return $data;
}

function getCategoryTitle($cat_id)
{
    if ($cat_id == -1) {
        return __("Special");
    } elseif ($cat_id == -2) {
        return __("Labels");
    } else {
        $result = \SmallSmallRSS\Database::query("SELECT title FROM ttrss_feed_categories WHERE id = '$cat_id'");
        if (\SmallSmallRSS\Database::num_rows($result) == 1) {
            return \SmallSmallRSS\Database::fetch_result($result, 0, "title");
        } else {
            return __("Uncategorized");
        }
    }
}

function getCategoryCounters()
{
    $ret_arr = array();
    /* Labels category */
    $cv = array("id" => -2, "kind" => "cat", "counter" => getCategoryUnread(-2));
    array_push($ret_arr, $cv);
    $result = \SmallSmallRSS\Database::query(
        "SELECT
            id AS cat_id,
            value AS unread,
            (
                 SELECT COUNT(id)
                 FROM ttrss_feed_categories AS c2
                 WHERE c2.parent_cat = ttrss_feed_categories.id
            ) AS num_children
            FROM
                ttrss_feed_categories,
                ttrss_cat_counters_cache
            WHERE
                ttrss_cat_counters_cache.feed_id = id
                AND ttrss_cat_counters_cache.owner_uid = ttrss_feed_categories.owner_uid
                AND ttrss_feed_categories.owner_uid = " . $_SESSION["uid"]
    );
    while (($line = \SmallSmallRSS\Database::fetch_assoc($result))) {
        $line["cat_id"] = (int) $line["cat_id"];
        if ($line["num_children"] > 0) {
            $child_counter = getCategoryChildrenUnread($line["cat_id"], $_SESSION["uid"]);
        } else {
            $child_counter = 0;
        }
        $cv = array("id" => $line["cat_id"], "kind" => "cat", "counter" => $line["unread"] + $child_counter);
        array_push($ret_arr, $cv);
    }

    /* Special case: NULL category doesn't actually exist in the DB */

    $cv = array(
        "id" => 0,
        "kind" => "cat",
        "counter" => (int) \SmallSmallRSS\CountersCache::find(0, $_SESSION["uid"], true)
    );
    array_push($ret_arr, $cv);
    return $ret_arr;
}

// only accepts real cats (>= 0)
function getCategoryChildrenUnread($cat, $owner_uid = false)
{
    if (!$owner_uid) {
        $owner_uid = $_SESSION["uid"];
    }
    $result = \SmallSmallRSS\Database::query(
        "SELECT id
         FROM ttrss_feed_categories
         WHERE
             parent_cat = '$cat'
             AND owner_uid = $owner_uid"
    );
    $unread = 0;
    while (($line = \SmallSmallRSS\Database::fetch_assoc($result))) {
        $unread += getCategoryUnread($line["id"], $owner_uid);
        $unread += getCategoryChildrenUnread($line["id"], $owner_uid);
    }
    return $unread;
}

function getCategoryUnread($cat, $owner_uid = false)
{
    if (!$owner_uid) {
        $owner_uid = $_SESSION["uid"];
    }
    if ($cat >= 0) {
        if ($cat != 0) {
            $cat_query = "cat_id = '$cat'";
        } else {
            $cat_query = "cat_id IS NULL";
        }
        $result = \SmallSmallRSS\Database::query(
            "SELECT id
             FROM ttrss_feeds
             WHERE
                 $cat_query
                 AND owner_uid = " . $owner_uid
        );
        $cat_feeds = array();
        while (($line = \SmallSmallRSS\Database::fetch_assoc($result))) {
            array_push($cat_feeds, "feed_id = " . $line["id"]);
        }

        if (count($cat_feeds) == 0) {
            return 0;
        }

        $match_part = implode(" OR ", $cat_feeds);
        $result = \SmallSmallRSS\Database::query(
            "SELECT COUNT(int_id) AS unread
             FROM ttrss_user_entries
             WHERE
                 unread = true
                 AND ($match_part)
                 AND owner_uid = " . $owner_uid
        );
        $unread = 0;
        // this needs to be rewritten
        while (($line = \SmallSmallRSS\Database::fetch_assoc($result))) {
            $unread += $line["unread"];
        }

        return $unread;
    } elseif ($cat == -1) {
        return getFeedUnread(-1) + getFeedUnread(-2) + getFeedUnread(-3) + getFeedUnread(0);
    } elseif ($cat == -2) {
        $result = \SmallSmallRSS\Database::query(
            "SELECT COUNT(unread) AS unread
             FROM ttrss_user_entries, ttrss_user_labels2
             WHERE
                 article_id = ref_id
                 AND unread = true
                 AND ttrss_user_entries.owner_uid = '$owner_uid'"
        );
        $unread = \SmallSmallRSS\Database::fetch_result($result, 0, "unread");
        return $unread;
    }
}

function getFeedUnread($feed, $is_cat = false)
{
    return getFeedArticles($feed, $is_cat, true, $_SESSION["uid"]);
}

function getLabelUnread($label_id, $owner_uid = false)
{
    if (!$owner_uid) {
        $owner_uid = $_SESSION["uid"];
    }

    $result = \SmallSmallRSS\Database::query(
        "SELECT COUNT(ref_id) AS unread
         FROM ttrss_user_entries, ttrss_user_labels2
         WHERE
             owner_uid = '$owner_uid'
             AND unread = true
             AND label_id = '$label_id'
             AND article_id = ref_id"
    );
    if (\SmallSmallRSS\Database::num_rows($result) != 0) {
        return \SmallSmallRSS\Database::fetch_result($result, 0, "unread");
    } else {
        return 0;
    }
}

function getFeedArticles($feed, $is_cat = false, $unread_only = false, $owner_uid = false)
{
    $n_feed = (int) $feed;
    $need_entries = false;
    if (!$owner_uid) {
        $owner_uid = $_SESSION["uid"];
    }
    if ($unread_only) {
        $unread_qpart = "unread = true";
    } else {
        $unread_qpart = "true";
    }
    if ($is_cat) {
        return getCategoryUnread($n_feed, $owner_uid);
    } elseif ($n_feed == -6) {
        return 0;
    } elseif ($feed != "0" && $n_feed == 0) {
        $feed = \SmallSmallRSS\Database::escape_string($feed);
        $result = \SmallSmallRSS\Database::query(
            "SELECT SUM(
                 (
                      SELECT COUNT(int_id)
                      FROM ttrss_user_entries, ttrss_entries
                      WHERE
                          int_id = post_int_id
                          AND ref_id = id
                          AND $unread_qpart
                 )
             ) AS count
             FROM ttrss_tags
             WHERE
                 owner_uid = $owner_uid
                 AND tag_name = '$feed'"
        );
        return \SmallSmallRSS\Database::fetch_result($result, 0, "count");
    } elseif ($n_feed == -1) {
        $match_part = "marked = true";
    } elseif ($n_feed == -2) {
        $match_part = "published = true";
    } elseif ($n_feed == -3) {
        $match_part = "unread = true AND score >= 0";
        $intl = \SmallSmallRSS\DBPrefs::read("FRESH_ARTICLE_MAX_AGE", $owner_uid);
        if (\SmallSmallRSS\Config::get('DB_TYPE') == "pgsql") {
            $match_part .= " AND updated > NOW() - INTERVAL '$intl hour' ";
        } else {
            $match_part .= " AND updated > DATE_SUB(NOW(), INTERVAL $intl HOUR) ";
        }
        $need_entries = true;
    } elseif ($n_feed == -4) {
        $match_part = "true";
    } elseif ($n_feed >= 0) {
        if ($n_feed != 0) {
            $match_part = "feed_id = '$n_feed'";
        } else {
            $match_part = "feed_id IS NULL";
        }
    } elseif ($feed < \SmallSmallRSS\Constants::LABEL_BASE_INDEX) {
        $label_id = feed_to_label_id($feed);
        return getLabelUnread($label_id, $owner_uid);
    }
    if ($match_part) {
        if ($need_entries) {
            $from_qpart = "ttrss_user_entries, ttrss_entries";
            $from_where = "ttrss_entries.id = ttrss_user_entries.ref_id AND ";
        } else {
            $from_qpart = "ttrss_user_entries";
            $from_where = '';
        }

        $query = "SELECT count(int_id) AS unread
                  FROM $from_qpart
                  WHERE
                      $unread_qpart
                      AND $from_where ($match_part)
                      AND ttrss_user_entries.owner_uid = $owner_uid";
        $result = \SmallSmallRSS\Database::query($query);
    } else {
        $result = \SmallSmallRSS\Database::query(
            "SELECT COUNT(post_int_id) AS unread
             FROM ttrss_tags, ttrss_user_entries, ttrss_entries
             WHERE
                 tag_name = '$feed'
                 AND post_int_id = int_id
                 AND ref_id = ttrss_entries.id
                 AND $unread_qpart
                 AND ttrss_tags.owner_uid = " . $owner_uid
        );
    }
    $unread = \SmallSmallRSS\Database::fetch_result($result, 0, "unread");
    return $unread;
}

function getGlobalUnread($user_id = false)
{
    if (!$user_id) {
        $user_id = $_SESSION["uid"];
    }
    return \SmallSmallRSS\CountersCache::getGlobalUnread($user_id);
}

function getGlobalCounters($global_unread = -1)
{
    $ret_arr = array();
    if ($global_unread == -1) {
        $global_unread = getGlobalUnread();
    }
    $cv = array("id" => "global-unread", "counter" => (int) $global_unread);
    array_push($ret_arr, $cv);
    $result = \SmallSmallRSS\Database::query(
        "SELECT COUNT(id) AS fn
         FROM ttrss_feeds
         WHERE owner_uid = " . $_SESSION["uid"]
    );
    $subscribed_feeds = \SmallSmallRSS\Database::fetch_result($result, 0, "fn");
    $cv = array("id" => "subscribed-feeds", "counter" => (int) $subscribed_feeds);
    array_push($ret_arr, $cv);
    return $ret_arr;
}

function getVirtCounters()
{
    $ret_arr = array();
    for ($i = 0; $i >= -4; $i--) {
        $count = getFeedUnread($i);
        if ($i == 0 || $i == -1 || $i == -2) {
            $auxctr = getFeedArticles($i, false);
        } else {
            $auxctr = 0;
        }
        $cv = array(
            "id" => $i,
            "counter" => (int) $count,
            "auxcounter" => $auxctr
        );
        array_push($ret_arr, $cv);
    }
    $feeds = \SmallSmallRSS\PluginHost::getInstance()->getFeeds(-1);
    if (is_array($feeds)) {
        foreach ($feeds as $feed) {
            $cv = array(
                "id" => \SmallSmallRSS\PluginHost::pfeed_to_feed_id($feed['id']),
                "counter" => $feed['sender']->get_unread($feed['id'])
            );
            array_push($ret_arr, $cv);
        }
    }
    return $ret_arr;
}

function getLabelCounters($descriptions = false)
{
    $ret_arr = array();
    $owner_uid = $_SESSION["uid"];
    $result = \SmallSmallRSS\Database::query(
        "SELECT
             id,
             caption,
             COUNT(u1.unread) AS unread,
             COUNT(u2.unread) AS total
         FROM
             ttrss_labels2
             LEFT JOIN ttrss_user_labels2 ON
                (ttrss_labels2.id = label_id)
             LEFT JOIN ttrss_user_entries AS u1
             ON (u1.ref_id = article_id AND u1.unread = true AND u1.owner_uid = $owner_uid)
             LEFT JOIN ttrss_user_entries AS u2
             ON (u2.ref_id = article_id AND u2.unread = false AND u2.owner_uid = $owner_uid)
         WHERE ttrss_labels2.owner_uid = $owner_uid
         GROUP BY
             ttrss_labels2.id, ttrss_labels2.caption"
    );
    while (($line = \SmallSmallRSS\Database::fetch_assoc($result))) {
        $id = label_to_feed_id($line["id"]);
        $cv = array(
            "id" => $id,
            "counter" => (int) $line["unread"],
            "auxcounter" => (int) $line["total"]
        );
        if ($descriptions) {
            $cv["description"] = $line["caption"];
        }
        array_push($ret_arr, $cv);
    }
    return $ret_arr;
}

function getFeedCounters($active_feed = false)
{
    $ret_arr = array();
    $substring_for_date = \SmallSmallRSS\Config::get('SUBSTRING_FOR_DATE');
    $query = "
        SELECT
            ttrss_feeds.id,
            ttrss_feeds.title,
            ".$substring_for_date."(ttrss_feeds.last_updated,1,19) AS last_updated,
            last_error,
            value AS count
        FROM ttrss_feeds, ttrss_counters_cache
        WHERE
            ttrss_feeds.owner_uid = ".$_SESSION["uid"]."
            AND ttrss_counters_cache.owner_uid = ttrss_feeds.owner_uid
            AND ttrss_counters_cache.feed_id = id";

    $result = \SmallSmallRSS\Database::query($query);
    $fctrs_modified = false;
    while (($line = \SmallSmallRSS\Database::fetch_assoc($result))) {
        $id = $line["id"];
        $count = $line["count"];
        $last_error = htmlspecialchars($line["last_error"]);
        $last_updated = make_local_datetime($line['last_updated'], false);
        $has_img = \SmallSmallRSS\Feeds::hasIcon($id);
        if (date('Y') - date('Y', strtotime($line['last_updated'])) > 2) {
            $last_updated = '';
        }
        $cv = array(
            "id" => $id,
            "updated" => $last_updated,
            "counter" => (int) $count,
            "has_img" => (int) $has_img
        );
        if ($last_error) {
            $cv["error"] = $last_error;
        }
        if ($active_feed && $id == $active_feed) {
            $cv["title"] = truncate_string($line["title"], 30);
        }
        array_push($ret_arr, $cv);
    }
    return $ret_arr;
}

function get_pgsql_version()
{
    $result = \SmallSmallRSS\Database::query("SELECT version() AS version");
    $version = explode(" ", \SmallSmallRSS\Database::fetch_result($result, 0, "version"));
    return $version[1];
}

/**
 * @return array (code => Status code, message => error message if available)
 *
 *                 0 - OK, Feed already exists
 *                 1 - OK, Feed added
 *                 2 - Invalid URL
 *                 3 - URL content is HTML, no feeds available
 *                 4 - URL content is HTML which contains multiple feeds.
 *                     Here you should call extractfeedurls in rpc-backend
 *                     to get all possible feeds.
 *                 5 - Couldn't download the URL content.
 *                 6 - Content is an invalid XML.
 */
function subscribe_to_feed($url, $cat_id = 0, $auth_login = '', $auth_pass = '')
{
    $url = fix_url($url);
    if (!$url || !validate_feed_url($url)) {
        return array("code" => 2);
    }
    $fetcher = new \SmallSmallRSS\Fetcher();
    $contents = $fetcher->getFileContents($url, false, $auth_login, $auth_pass);
    $fetch_last_error = $fetcher->last_error;
    if (!$contents) {
        return array("code" => 5, "message" => $fetch_last_error);
    }
    if (\SmallSmallRSS\Utils::isHtml($contents)) {
        $feedUrls = get_feeds_from_html($url, $contents);
        if (count($feedUrls) == 0) {
            return array("code" => 3);
        } elseif (count($feedUrls) > 1) {
            return array("code" => 4, "feeds" => $feedUrls);
        }
        //use feed url as new URL
        $url = key($feedUrls);
    }
    if ($cat_id == "0" || !$cat_id) {
        $cat_qpart = "NULL";
    } else {
        $cat_qpart = "'$cat_id'";
    }
    $result = \SmallSmallRSS\Database::query(
        "SELECT id
         FROM ttrss_feeds
         WHERE
             feed_url = '$url'
             AND owner_uid = " . $_SESSION["uid"]
    );
    if (\SmallSmallRSS\Crypt::is_enabled()) {
        $auth_pass = substr(\SmallSmallRSS\Crypt::en($auth_pass), 0, 250);
        $auth_pass_encrypted = 'true';
    } else {
        $auth_pass_encrypted = 'false';
    }
    $auth_pass = \SmallSmallRSS\Database::escape_string($auth_pass);
    if (\SmallSmallRSS\Database::num_rows($result) == 0) {
        $result = \SmallSmallRSS\Database::query(
            "INSERT INTO ttrss_feeds
             (owner_uid,feed_url,title,cat_id, auth_login,auth_pass,update_method,auth_pass_encrypted)
             VALUES (
                 '".$_SESSION["uid"]."', '$url',
                 '[Unknown]', $cat_qpart, '$auth_login', '$auth_pass', 0, $auth_pass_encrypted)"
        );
        $result = \SmallSmallRSS\Database::query(
            "SELECT id FROM ttrss_feeds
             WHERE
                 feed_url = '$url'
                 AND owner_uid = " . $_SESSION["uid"]
        );
        $feed_id = \SmallSmallRSS\Database::fetch_result($result, 0, "id");
        if ($feed_id) {
            \SmallSmallRSS\RSSUpdater::updateFeed($feed_id);
        }
        return array("code" => 1);
    } else {
        return array("code" => 0);
    }
}

function print_feed_select(
    $id,
    $default_id = "",
    $attributes = "",
    $include_all_feeds = true,
    $root_id = false,
    $nest_level = 0
) {

    if (!$root_id) {
        print "<select id=\"$id\" name=\"$id\" $attributes>";
        if ($include_all_feeds) {
            $is_selected = ("0" == $default_id) ? "selected=\"1\"" : "";
            print "<option $is_selected value=\"0\">".__('All feeds')."</option>";
        }
    }
    if (\SmallSmallRSS\DBPrefs::read('ENABLE_FEED_CATS')) {
        if ($root_id) {
            $parent_qpart = "parent_cat = '$root_id'";
        } else {
            $parent_qpart = "parent_cat IS NULL";
        }
        $result = \SmallSmallRSS\Database::query(
            "SELECT
                 id,
                 title,
                 (
                      SELECT COUNT(id)
                      FROM ttrss_feed_categories AS c2
                      WHERE c2.parent_cat = ttrss_feed_categories.id
                 ) AS num_children
                 FROM ttrss_feed_categories
                 WHERE
                     owner_uid = " . $_SESSION["uid"] . "
                     AND $parent_qpart ORDER BY title"
        );
        while (($line = \SmallSmallRSS\Database::fetch_assoc($result))) {
            for ($i = 0; $i < $nest_level; $i++) {
                $line["title"] = " - " . $line["title"];
            }
            $is_selected = ("CAT:".$line["id"] == $default_id) ? "selected=\"1\"" : "";
            printf(
                "<option $is_selected value='CAT:%d'>%s</option>",
                $line["id"],
                htmlspecialchars($line["title"])
            );
            if ($line["num_children"] > 0) {
                print_feed_select(
                    $id,
                    $default_id,
                    $attributes,
                    $include_all_feeds,
                    $line["id"],
                    $nest_level + 1
                );
            }
            $feed_result = \SmallSmallRSS\Database::query(
                "SELECT id, title
                 FROM ttrss_feeds
                 WHERE
                     cat_id = '" . $line["id"] . "'
                     AND owner_uid = ".$_SESSION["uid"] . "
                 ORDER BY title"
            );
            while ($fline = \SmallSmallRSS\Database::fetch_assoc($feed_result)) {
                $is_selected = ($fline["id"] == $default_id) ? "selected=\"1\"" : "";
                $fline["title"] = " + " . $fline["title"];
                for ($i = 0; $i < $nest_level; $i++) {
                    $fline["title"] = " - " . $fline["title"];
                }
                printf(
                    "<option $is_selected value='%d'>%s</option>",
                    $fline["id"],
                    htmlspecialchars($fline["title"])
                );
            }
        }
        if (!$root_id) {
            $default_is_cat = ($default_id == "CAT:0");
            $is_selected = $default_is_cat ? "selected=\"1\"" : "";
            printf("<option $is_selected value='CAT:0'>%s</option>", __("Uncategorized"));
            $feed_result = \SmallSmallRSS\Database::query(
                "SELECT id, title
                 FROM ttrss_feeds
                 WHERE
                     cat_id IS NULL
                     AND owner_uid = ".$_SESSION["uid"] . "
                 ORDER BY title"
            );
            while ($fline = \SmallSmallRSS\Database::fetch_assoc($feed_result)) {
                $is_selected = ($fline["id"] == $default_id && !$default_is_cat) ? "selected=\"1\"" : "";
                $fline["title"] = " + " . $fline["title"];
                for ($i = 0; $i < $nest_level; $i++) {
                    $fline["title"] = " - " . $fline["title"];
                }
                printf(
                    "<option $is_selected value='%d'>%s</option>",
                    $fline["id"],
                    htmlspecialchars($fline["title"])
                );
            }
        }
    } else {
        $result = \SmallSmallRSS\Database::query(
            "SELECT id, title
             FROM ttrss_feeds
             WHERE owner_uid = " . $_SESSION["uid"] . "
             ORDER BY title"
        );
        while (($line = \SmallSmallRSS\Database::fetch_assoc($result))) {
            $is_selected = ($line["id"] == $default_id) ? "selected=\"1\"" : "";
            printf(
                "<option $is_selected value='%d'>%s</option>",
                $line["id"],
                htmlspecialchars($line["title"])
            );
        }
    }
    if (!$root_id) {
        print "</select>";
    }
}

function print_feed_cat_select(
    $id,
    $default_id,
    $attributes,
    $include_all_cats = true,
    $root_id = false,
    $nest_level = 0
) {
    if (!$root_id) {
        print "<select id=\"$id\" name=\"$id\" default=\"$default_id\"";
        print " onchange=\"catSelectOnChange(this)\" $attributes>";
    }
    if ($root_id) {
        $parent_qpart = "parent_cat = '$root_id'";
    } else {
        $parent_qpart = "parent_cat IS NULL";
    }
    $result = \SmallSmallRSS\Database::query(
        "SELECT
             id,
             title,
             (
                  SELECT COUNT(id)
                  FROM ttrss_feed_categories AS c2
                  WHERE
                      c2.parent_cat = ttrss_feed_categories.id
             ) AS num_children
             FROM ttrss_feed_categories
             WHERE
                 owner_uid = " . $_SESSION["uid"] . "
                 AND $parent_qpart ORDER BY title"
    );

    while (($line = \SmallSmallRSS\Database::fetch_assoc($result))) {
        if ($line["id"] == $default_id) {
            $is_selected = "selected=\"1\"";
        } else {
            $is_selected = "";
        }
        for ($i = 0; $i < $nest_level; $i++) {
            $line["title"] = " - " . $line["title"];
        }
        if ($line["title"]) {
            printf(
                "<option $is_selected value='%d'>%s</option>",
                $line["id"],
                htmlspecialchars($line["title"])
            );
        }
        if ($line["num_children"] > 0) {
            print_feed_cat_select($id, $default_id, $attributes,
                                  $include_all_cats, $line["id"], $nest_level+1);
        }
    }

    if (!$root_id) {
        if ($include_all_cats) {
            if (\SmallSmallRSS\Database::num_rows($result) > 0) {
                print "<option disabled=\"1\">--------</option>";
            }
            if ($default_id == 0) {
                $is_selected = "selected=\"1\"";
            } else {
                $is_selected = "";
            }
            print "<option $is_selected value=\"0\">".__('Uncategorized')."</option>";
        }
        print "</select>";
    }
}

function checkbox_to_sql_bool($val)
{
    return ($val == "on") ? "true" : "false";
}

function getFeedCatTitle($id)
{
    if ($id == -1) {
        return __("Special");
    } elseif ($id < \SmallSmallRSS\Constants::LABEL_BASE_INDEX) {
        return __("Labels");
    } elseif ($id > 0) {
        $result = \SmallSmallRSS\Database::query(
            "SELECT ttrss_feed_categories.title
             FROM
                 ttrss_feeds,
                 ttrss_feed_categories
             WHERE
                 ttrss_feeds.id = '$id'
                 AND cat_id = ttrss_feed_categories.id"
        );
        if (\SmallSmallRSS\Database::num_rows($result) == 1) {
            return \SmallSmallRSS\Database::fetch_result($result, 0, "title");
        } else {
            return __("Uncategorized");
        }
    } else {
        return "getFeedCatTitle($id) failed";
    }

}

function getFeedIcon($id)
{
    switch ($id) {
        case 0:
            return "images/archive.png";
            break;
        case -1:
            return "images/mark_set.svg";
            break;
        case -2:
            return "images/pub_set.svg";
            break;
        case -3:
            return "images/fresh.png";
            break;
        case -4:
            return "images/tag.png";
            break;
        case -6:
            return "images/recently_read.png";
            break;
        default:
            if ($id < \SmallSmallRSS\Constants::LABEL_BASE_INDEX) {
                return "images/label.png";
            } else {
                if (file_exists(\SmallSmallRSS\Config::get('ICONS_DIR') . "/$id.ico")) {
                    return \SmallSmallRSS\Config::get('ICONS_URL') . "/$id.ico";
                }
            }
            break;
    }
    return false;
}

function get_hotkeys_info()
{
    $hotkeys = array(
        __("Navigation") => array(
            "next_feed" => __("Open next feed"),
            "prev_feed" => __("Open previous feed"),
            "next_article" => __("Open next article"),
            "prev_article" => __("Open previous article"),
            "next_article_noscroll" => __("Open next article (don't scroll long articles)"),
            "prev_article_noscroll" => __("Open previous article (don't scroll long articles)"),
            "next_article_noexpand" => __("Move to next article (don't expand or mark read)"),
            "prev_article_noexpand" => __("Move to previous article (don't expand or mark read)"),
            "search_dialog" => __("Show search dialog")),
        __("Article") => array(
            "toggle_mark" => __("Toggle starred"),
            "toggle_publ" => __("Toggle published"),
            "toggle_unread" => __("Toggle unread"),
            "edit_tags" => __("Edit tags"),
            "dismiss_selected" => __("Dismiss selected"),
            "dismiss_read" => __("Dismiss read"),
            "open_in_new_window" => __("Open in new window"),
            "catchup_below" => __("Mark below as read"),
            "catchup_above" => __("Mark above as read"),
            "article_scroll_down" => __("Scroll down"),
            "article_scroll_up" => __("Scroll up"),
            "select_article_cursor" => __("Select article under cursor"),
            "email_article" => __("Email article"),
            "close_article" => __("Close/collapse article"),
            "toggle_expand" => __("Toggle article expansion (combined mode)"),
            "toggle_widescreen" => __("Toggle widescreen mode"),
            "toggle_embed_original" => __("Toggle embed original")),
        __("Article selection") => array(
            "select_all" => __("Select all articles"),
            "select_unread" => __("Select unread"),
            "select_marked" => __("Select starred"),
            "select_published" => __("Select published"),
            "select_invert" => __("Invert selection"),
            "select_none" => __("Deselect everything")),
        __("Feed") => array(
            "feed_refresh" => __("Refresh current feed"),
            "feed_unhide_read" => __("Un/hide read feeds"),
            "feed_subscribe" => __("Subscribe to feed"),
            "feed_edit" => __("Edit feed"),
            "feed_catchup" => __("Mark as read"),
            "feed_reverse" => __("Reverse headlines"),
            "feed_debug_update" => __("Debug feed update"),
            "catchup_all" => __("Mark all feeds as read"),
            "cat_toggle_collapse" => __("Un/collapse current category"),
            "toggle_combined_mode" => __("Toggle combined mode"),
            "toggle_cdm_expanded" => __("Toggle auto expand in combined mode")),
        __("Go to") => array(
            "goto_all" => __("All articles"),
            "goto_fresh" => __("Fresh"),
            "goto_marked" => __("Starred"),
            "goto_published" => __("Published"),
            "goto_tagcloud" => __("Tag cloud"),
            "goto_prefs" => __("Preferences")),
        __("Other") => array(
            "create_label" => __("Create label"),
            "create_filter" => __("Create filter"),
            "collapse_sidebar" => __("Un/collapse sidebar"),
            "help_dialog" => __("Show help dialog"))
    );
    $hotkey_hooks = \SmallSmallRSS\PluginHost::getInstance()->get_hooks(
        \SmallSmallRSS\Hooks::HOTKEY_INFO
    );
    foreach ($hotkey_hooks as $plugin) {
        $hotkeys = $plugin->hook_hotkey_info($hotkeys);
    }
    return $hotkeys;
}


function get_hotkeys_map()
{
    $hotkeys = array(
        //            "navigation" => array(
        "k" => "next_feed",
        "j" => "prev_feed",
        "n" => "next_article",
        "p" => "prev_article",
        "(38)|up" => "prev_article",
        "(40)|down" => "next_article",
        //                "^(38)|Ctrl-up" => "prev_article_noscroll",
        //                "^(40)|Ctrl-down" => "next_article_noscroll",
        "(191)|/" => "search_dialog",
        //            "article" => array(
        "s" => "toggle_mark",
        "*s" => "toggle_publ",
        "u" => "toggle_unread",
        "*t" => "edit_tags",
        "*d" => "dismiss_selected",
        "*x" => "dismiss_read",
        "o" => "open_in_new_window",
        "c p" => "catchup_below",
        "c n" => "catchup_above",
        "*n" => "article_scroll_down",
        "*p" => "article_scroll_up",
        "*(38)|Shift+up" => "article_scroll_up",
        "*(40)|Shift+down" => "article_scroll_down",
        "a *w" => "toggle_widescreen",
        "a e" => "toggle_embed_original",
        "e" => "email_article",
        "a q" => "close_article",
        //            "article_selection" => array(
        "a a" => "select_all",
        "a u" => "select_unread",
        "a *u" => "select_marked",
        "a p" => "select_published",
        "a i" => "select_invert",
        "a n" => "select_none",
        //            "feed" => array(
        "f r" => "feed_refresh",
        "f a" => "feed_unhide_read",
        "f s" => "feed_subscribe",
        "f e" => "feed_edit",
        "f q" => "feed_catchup",
        "f x" => "feed_reverse",
        "f *d" => "feed_debug_update",
        "f *c" => "toggle_combined_mode",
        "f c" => "toggle_cdm_expanded",
        "*q" => "catchup_all",
        "x" => "cat_toggle_collapse",
        //            "goto" => array(
        "g a" => "goto_all",
        "g f" => "goto_fresh",
        "g s" => "goto_marked",
        "g p" => "goto_published",
        "g t" => "goto_tagcloud",
        "g *p" => "goto_prefs",
        //            "other" => array(
        "(9)|Tab" => "select_article_cursor", // tab
        "c l" => "create_label",
        "c f" => "create_filter",
        "c s" => "collapse_sidebar",
        "^(191)|Ctrl+/" => "help_dialog",
    );

    if (\SmallSmallRSS\DBPrefs::read('COMBINED_DISPLAY_MODE')) {
        $hotkeys["^(38)|Ctrl-up"] = "prev_article_noscroll";
        $hotkeys["^(40)|Ctrl-down"] = "next_article_noscroll";
    }

    $hotkey_hooks = \SmallSmallRSS\PluginHost::getInstance()->get_hooks(
        \SmallSmallRSS\Hooks::HOTKEY_MAP
    );
    foreach ($hotkey_hooks as $plugin) {
        $hotkeys = $plugin->hookHotkeyMap($hotkeys);
    }

    $prefixes = array();

    foreach (array_keys($hotkeys) as $hotkey) {
        $pair = explode(" ", $hotkey, 2);
        if (count($pair) > 1 && !in_array($pair[0], $prefixes)) {
            array_push($prefixes, $pair[0]);
        }
    }
    return array($prefixes, $hotkeys);
}

function make_runtime_info()
{
    $data = array();
    $result = \SmallSmallRSS\Database::query(
        "SELECT
             MAX(id) AS mid,
             COUNT(*) AS nf
         FROM ttrss_feeds
         WHERE owner_uid = " . $_SESSION["uid"]);

    $max_feed_id = \SmallSmallRSS\Database::fetch_result($result, 0, "mid");
    $num_feeds = \SmallSmallRSS\Database::fetch_result($result, 0, "nf");
    $data["max_feed_id"] = (int) $max_feed_id;
    $data["num_feeds"] = (int) $num_feeds;
    $data['last_article_id'] = getLastArticleId();
    $data['cdm_expanded'] = \SmallSmallRSS\DBPrefs::read('CDM_EXPANDED');
    $data['dependency_timestamp'] = calculate_dep_timestamp();
    $data['reload_on_ts_change'] = \SmallSmallRSS\Config::get('RELOAD_ON_TS_CHANGE');
    $feeds_stamp = \SmallSmallRSS\Lockfiles::whenStamped("update_feeds");
    $daemon_stamp = \SmallSmallRSS\Lockfiles::whenStamped("update_daemon");
    $data['daemon_is_running'] = (
        \SmallSmallRSS\Lockfiles::is_locked("update_daemon.lock")
        || (bool) $feeds_stamp
        || (bool) $daemon_stamp
    );

    $last_run = max((int) $daemon_stamp, (int) $feeds_stamp);
    $data['feeds_stamp'] = $feeds_stamp;
    $data['daemon_stamp'] = $daemon_stamp;
    $data['last_run'] = $last_run;
    if ($data['daemon_is_running']) {
        if (time() - $_SESSION["daemon_stamp_check"] > 30) {
            $stamp_delta = time() - $last_run;
            if ($stamp_delta > 1800) {
                $stamp_check = false;
            } else {
                $stamp_check = true;
                $_SESSION["daemon_stamp_check"] = time();
            }
            $data['daemon_stamp_ok'] = $stamp_check;
            $stamp_fmt = date("Y.m.d, G:i", $last_run);
            $data['daemon_stamp'] = $stamp_fmt;
        }
    }
    return $data;
}

function search_to_sql($search)
{
    $search_query_part = "";
    $keywords = explode(" ", $search);
    $query_keywords = array();
    foreach ($keywords as $k) {
        if (strpos($k, "-") === 0) {
            $k = substr($k, 1);
            $not = "NOT";
        } else {
            $not = "";
        }
        $commandpair = explode(":", mb_strtolower($k), 2);
        switch ($commandpair[0]) {
            case "title":
                if ($commandpair[1]) {
                    array_push(
                        $query_keywords,
                        "($not (LOWER(ttrss_entries.title) LIKE '%".
                        \SmallSmallRSS\Database::escape_string(mb_strtolower($commandpair[1]))."%'))"
                    );
                } else {
                    array_push(
                        $query_keywords,
                        "(UPPER(ttrss_entries.title) $not LIKE UPPER('%$k%')
                            OR UPPER(ttrss_entries.content) $not LIKE UPPER('%$k%'))"
                    );
                }
                break;
            case "author":
                if ($commandpair[1]) {
                    array_push(
                        $query_keywords,
                        "($not (LOWER(author) LIKE '%"
                        . \SmallSmallRSS\Database::escape_string(mb_strtolower($commandpair[1]))."%'))"
                    );
                } else {
                    array_push(
                        $query_keywords,
                        "(UPPER(ttrss_entries.title) $not LIKE UPPER('%$k%')
                            OR UPPER(ttrss_entries.content) $not LIKE UPPER('%$k%'))"
                    );
                }
                break;
            case "note":
                if ($commandpair[1]) {
                    if ($commandpair[1] == "true") {
                        array_push($query_keywords, "($not (note IS NOT NULL AND note != ''))");
                    } elseif ($commandpair[1] == "false") {
                        array_push($query_keywords, "($not (note IS NULL OR note = ''))");
                    } else {
                        array_push(
                            $query_keywords,
                            "($not (LOWER(note) LIKE '%"
                            . \SmallSmallRSS\Database::escape_string(mb_strtolower($commandpair[1]))."%'))"
                        );
                    }
                } else {
                    array_push(
                        $query_keywords,
                        "(UPPER(ttrss_entries.title) $not LIKE UPPER('%$k%')
                            OR UPPER(ttrss_entries.content) $not LIKE UPPER('%$k%'))"
                    );
                }
                break;
            case "star":

                if ($commandpair[1]) {
                    if ($commandpair[1] == "true") {
                        array_push($query_keywords, "($not (marked = true))");
                    } else {
                        array_push($query_keywords, "($not (marked = false))");
                    }
                } else {
                    array_push(
                        $query_keywords,
                        "(UPPER(ttrss_entries.title) $not LIKE UPPER('%$k%')
                            OR UPPER(ttrss_entries.content) $not LIKE UPPER('%$k%'))"
                    );
                }
                break;
            case "pub":
                if ($commandpair[1]) {
                    if ($commandpair[1] == "true") {
                        array_push($query_keywords, "($not (published = true))");
                    } else {
                        array_push($query_keywords, "($not (published = false))");
                    }
                } else {
                    array_push($query_keywords, "(UPPER(ttrss_entries.title) $not LIKE UPPER('%$k%')
                            OR UPPER(ttrss_entries.content) $not LIKE UPPER('%$k%'))");
                }
                break;
            default:
                if (strpos($k, "@") === 0) {

                    $user_tz_string = \SmallSmallRSS\DBPrefs::read('USER_TIMEZONE', $_SESSION['uid']);
                    $orig_ts = strtotime(substr($k, 1));
                    $k = date("Y-m-d", convert_timestamp($orig_ts, $user_tz_string, 'UTC'));

                    //$k = date("Y-m-d", strtotime(substr($k, 1)));
                    $substring_for_date = \SmallSmallRSS\Config::get('SUBSTRING_FOR_DATE');
                    array_push($query_keywords, "(".$substring_for_date."(updated,1,LENGTH('$k')) $not = '$k')");
                } else {
                    array_push(
                        $query_keywords,
                        "(UPPER(ttrss_entries.title) $not LIKE UPPER('%$k%')
                         OR UPPER(ttrss_entries.content) $not LIKE UPPER('%$k%'))"
                    );
                }
        }
    }
    $search_query_part = implode("AND", $query_keywords);
    return $search_query_part;
}

function getParentCategories($cat, $owner_uid)
{
    $rv = array();
    $result = \SmallSmallRSS\Database::query(
        "SELECT parent_cat
         FROM ttrss_feed_categories
         WHERE
              id = '$cat'
              AND parent_cat IS NOT NULL
              AND owner_uid = $owner_uid"
    );
    while (($line = \SmallSmallRSS\Database::fetch_assoc($result))) {
        array_push($rv, $line["parent_cat"]);
        $rv = array_merge($rv, getParentCategories($line["parent_cat"], $owner_uid));
    }
    return $rv;
}

function getChildCategories($cat, $owner_uid)
{
    $rv = array();
    $result = \SmallSmallRSS\Database::query(
        "SELECT id
         FROM ttrss_feed_categories
         WHERE
             parent_cat = '$cat'
             AND owner_uid = $owner_uid"
    );
    while (($line = \SmallSmallRSS\Database::fetch_assoc($result))) {
        array_push($rv, $line["id"]);
        $rv = array_merge($rv, getChildCategories($line["id"], $owner_uid));
    }
    return $rv;
}

function queryFeedHeadlines(
    $feed,
    $limit,
    $view_mode,
    $cat_view,
    $search,
    $search_mode,
    $override_order = false,
    $offset = 0,
    $owner_uid = 0,
    $filter = false,
    $since_id = 0,
    $include_children = false,
    $ignore_vfeed_group = false
) {
    $last_updated = 0;
    $last_error = 0;
    $feed_site_url = '';
    $query_strategy_part = '';
    if (!$owner_uid) {
        $owner_uid = $_SESSION["uid"];
    }
    $ext_tables_part = "";
    if ($search) {
        if (\SmallSmallRSS\Config::get('SPHINX_ENABLED')) {
            $ids = join(",", @sphinx_search($search, 0, 500));
            if ($ids) {
                $search_query_part = "ref_id IN ($ids) AND ";
            } else {
                $search_query_part = "ref_id = -1 AND ";
            }
        } else {
            $search_query_part = search_to_sql($search);
            $search_query_part .= " AND ";
        }
    } else {
        $search_query_part = "";
    }
    if ($filter) {
        if (\SmallSmallRSS\Config::get('DB_TYPE') == "pgsql") {
            $query_strategy_part .= " AND updated > NOW() - INTERVAL '14 days' ";
        } else {
            $query_strategy_part .= " AND updated > DATE_SUB(NOW(), INTERVAL 14 DAY) ";
        }
        $override_order = "updated DESC";
        $filter_query_part = filter_to_sql($filter, $owner_uid);

        // Try to check if SQL regexp implementation chokes on a valid regexp

        $result = \SmallSmallRSS\Database::query(
            "SELECT true AS true_val
             FROM ttrss_entries, ttrss_user_entries, ttrss_feeds
             WHERE $filter_query_part
             LIMIT 1",
            false
        );
        if ($result) {
            $test = \SmallSmallRSS\Database::fetch_result($result, 0, "true_val");
            if (!$test) {
                $filter_query_part = "false AND";
            } else {
                $filter_query_part .= " AND";
            }
        } else {
            $filter_query_part = "false AND";
        }
    } else {
        $filter_query_part = "";
    }
    if ($since_id) {
        $since_id_part = "ttrss_entries.id > $since_id AND ";
    } else {
        $since_id_part = "";
    }
    $view_query_part = "";
    if ($view_mode == "adaptive") {
        if ($search) {
            $view_query_part = " ";
        } elseif ($feed != -1) {
            $unread = getFeedUnread($feed, $cat_view);
            if ($cat_view && $feed > 0 && $include_children) {
                $unread += getCategoryChildrenUnread($feed);
            }
            if ($unread > 0) {
                $view_query_part = " unread = true AND ";
            }
        }
    }
    if ($view_mode == "marked") {
        $view_query_part = " marked = true AND ";
    }
    if ($view_mode == "has_note") {
        $view_query_part = " (note IS NOT NULL AND note != '') AND ";
    }
    if ($view_mode == "published") {
        $view_query_part = " published = true AND ";
    }
    if ($view_mode == "unread" && $feed != -6) {
        $view_query_part = " unread = true AND ";
    }
    if ($limit > 0) {
        $limit_query_part = "LIMIT " . $limit;
    }
    $allow_archived = false;
    $vfeed_query_part = "";
    // override query strategy and enable feed display when searching globally
    if ($search && $search_mode == "all_feeds") {
        $query_strategy_part = "true";
        $vfeed_query_part = "ttrss_feeds.title AS feed_title,";
        /* tags */
    } elseif (!is_numeric($feed)) {
        $query_strategy_part = "true";
        $vfeed_query_part = "(SELECT title FROM ttrss_feeds WHERE
                    id = feed_id) as feed_title,";
    } elseif ($search && $search_mode == "this_cat") {
        $vfeed_query_part = "ttrss_feeds.title AS feed_title,";
        if ($feed > 0) {
            if ($include_children) {
                $subcats = getChildCategories($feed, $owner_uid);
                array_push($subcats, $feed);
                $cats_qpart = join(",", $subcats);
            } else {
                $cats_qpart = $feed;
            }
            $query_strategy_part = "ttrss_feeds.cat_id IN ($cats_qpart)";
        } else {
            $query_strategy_part = "ttrss_feeds.cat_id IS NULL";
        }
    } elseif ($feed > 0) {
        if ($cat_view) {
            if ($feed > 0) {
                if ($include_children) {
                    // sub-cats
                    $subcats = getChildCategories($feed, $owner_uid);
                    array_push($subcats, $feed);
                    $query_strategy_part = "cat_id IN (" . implode(",", $subcats) . ")";
                } else {
                    $query_strategy_part = "cat_id = '$feed'";
                }
            } else {
                $query_strategy_part = "cat_id IS NULL";
            }
            $vfeed_query_part = "ttrss_feeds.title AS feed_title,";
        } else {
            $query_strategy_part = "feed_id = '$feed'";
        }
    } elseif ($feed == 0 && !$cat_view) { // archive virtual feed
        $query_strategy_part = "feed_id IS NULL";
        $allow_archived = true;
    } elseif ($feed == 0 && $cat_view) { // uncategorized
        $query_strategy_part = "cat_id IS NULL AND feed_id IS NOT NULL";
        $vfeed_query_part = "ttrss_feeds.title AS feed_title,";
    } elseif ($feed == -1) { // starred virtual feed
        $query_strategy_part = "marked = true";
        $vfeed_query_part = "ttrss_feeds.title AS feed_title,";
        $allow_archived = true;
        if (!$override_order) {
            $override_order = "last_marked DESC, date_entered DESC, updated DESC";
        }
    } elseif ($feed == -2) { // published virtual feed OR labels category
        if (!$cat_view) {
            $query_strategy_part = "published = true";
            $vfeed_query_part = "ttrss_feeds.title AS feed_title,";
            $allow_archived = true;
            if (!$override_order) {
                $override_order = "last_published DESC, date_entered DESC, updated DESC";
            }
        } else {
            $vfeed_query_part = "ttrss_feeds.title AS feed_title,";
            $ext_tables_part = ",ttrss_labels2,ttrss_user_labels2";
            $query_strategy_part = "ttrss_labels2.id = ttrss_user_labels2.label_id
                                    AND ttrss_user_labels2.article_id = ref_id";
        }
    } elseif ($feed == -6) { // recently read
        $query_strategy_part = "unread = false AND last_read IS NOT NULL";
        $vfeed_query_part = "ttrss_feeds.title AS feed_title,";
        $allow_archived = true;
        if (!$override_order) {
            $override_order = "last_read DESC";
        }
    } elseif ($feed == -3) { // fresh virtual feed
        $query_strategy_part = "unread = true AND score >= 0";
        $intl = \SmallSmallRSS\DBPrefs::read("FRESH_ARTICLE_MAX_AGE", $owner_uid);
        if (\SmallSmallRSS\Config::get('DB_TYPE') == "pgsql") {
            $query_strategy_part .= " AND date_entered > NOW() - INTERVAL '$intl hour' ";
        } else {
            $query_strategy_part .= " AND date_entered > DATE_SUB(NOW(), INTERVAL $intl HOUR) ";
        }
        $vfeed_query_part = "ttrss_feeds.title AS feed_title,";
    } elseif ($feed == -4) { // all articles virtual feed
        $allow_archived = true;
        $query_strategy_part = "true";
        $vfeed_query_part = "ttrss_feeds.title AS feed_title,";
    } elseif ($feed <= \SmallSmallRSS\Constants::LABEL_BASE_INDEX) { // labels
        $label_id = feed_to_label_id($feed);
        $query_strategy_part = "label_id = '$label_id'
                    AND ttrss_labels2.id = ttrss_user_labels2.label_id
                    AND ttrss_user_labels2.article_id = ref_id";

        $vfeed_query_part = "ttrss_feeds.title AS feed_title,";
        $ext_tables_part = ",ttrss_labels2,ttrss_user_labels2";
        $allow_archived = true;

    } else {
        $query_strategy_part = "true";
    }

    $order_by = "score DESC, date_entered DESC, updated DESC";

    if ($view_mode == "unread_first") {
        $order_by = "unread DESC, $order_by";
    }

    if ($override_order) {
        $order_by = $override_order;
    }

    $feed_title = "";

    if ($search) {
        $feed_title = T_sprintf("Search results: %s", $search);
    } else {
        if ($cat_view) {
            $feed_title = getCategoryTitle($feed);
        } else {
            if (is_numeric($feed) && $feed > 0) {
                $result = \SmallSmallRSS\Database::query(
                    "SELECT title,site_url,last_error,last_updated
                     FROM ttrss_feeds
                     WHERE id = '$feed' AND owner_uid = $owner_uid"
                );
                $feed_title = \SmallSmallRSS\Database::fetch_result($result, 0, "title");
                $feed_site_url = \SmallSmallRSS\Database::fetch_result($result, 0, "site_url");
                $last_error = \SmallSmallRSS\Database::fetch_result($result, 0, "last_error");
                $last_updated = \SmallSmallRSS\Database::fetch_result($result, 0, "last_updated");
            } else {
                $feed_title = \SmallSmallRSS\Feeds::getTitle($feed);
            }
        }
    }
    $content_query_part = "content as content_preview, cached_content, ";
    if (is_numeric($feed)) {
        if ($feed >= 0) {
            $feed_kind = "Feeds";
        } else {
            $feed_kind = "Labels";
        }
        if ($limit_query_part) {
            $offset_query_part = "OFFSET $offset";
        }
        // proper override_order applied above
        if ($vfeed_query_part && !$ignore_vfeed_group && \SmallSmallRSS\DBPrefs::read('VFEED_GROUP_BY_FEED', $owner_uid)) {
            if (!$override_order) {
                $order_by = "ttrss_feeds.title, $order_by";
            } else {
                $order_by = "ttrss_feeds.title, $override_order";
            }
        }

        if (!$allow_archived) {
            $from_qpart = "ttrss_entries,ttrss_user_entries,ttrss_feeds$ext_tables_part";
            $feed_check_qpart = "ttrss_user_entries.feed_id = ttrss_feeds.id AND";

        } else {
            $from_qpart = "ttrss_entries$ext_tables_part,ttrss_user_entries
                        LEFT JOIN ttrss_feeds ON (feed_id = ttrss_feeds.id)";
            $feed_check_qpart = '';
        }

        if ($vfeed_query_part) {
            $vfeed_query_part .= "favicon_avg_color,";
        }

        $query = "SELECT DISTINCT
                        date_entered,
                        guid,
                        ttrss_entries.id,ttrss_entries.title,
                        updated,
                        label_cache,
                        tag_cache,
                        always_display_enclosures,
                        site_url,
                        note,
                        num_comments,
                        comments,
                        int_id,
                        hide_images,
                        unread,feed_id,marked,published,link,last_read,orig_feed_id,
                        last_marked, last_published,
                        $vfeed_query_part
                        $content_query_part
                        author,score
                    FROM
                        $from_qpart
                    WHERE
                    $feed_check_qpart
                    ttrss_user_entries.ref_id = ttrss_entries.id AND
                    ttrss_user_entries.owner_uid = '$owner_uid' AND
                    $search_query_part
                    $filter_query_part
                    $view_query_part
                    $since_id_part
                    $query_strategy_part ORDER BY $order_by
                    $limit_query_part $offset_query_part";

        if (!empty($_REQUEST["debug"])) {
            print $query;
        }

        $result = \SmallSmallRSS\Database::query($query);

    } else {
        // browsing by tag

        $select_qpart = "SELECT DISTINCT " .
            "date_entered," .
            "guid," .
            "note," .
            "ttrss_entries.id as id," .
            "title," .
            "updated," .
            "unread," .
            "feed_id," .
            "orig_feed_id," .
            "marked," .
            "num_comments, " .
            "comments, " .
            "tag_cache," .
            "label_cache," .
            "link," .
            "last_read," .
            "(SELECT hide_images FROM ttrss_feeds WHERE id = feed_id) AS hide_images," .
            "last_marked, last_published, " .
            $since_id_part .
            $vfeed_query_part .
            $content_query_part .
            "score ";

        $feed_kind = "Tags";
        $all_tags = explode(",", $feed);
        if ($search_mode == 'any') {
            $tag_sql = "tag_name in (" . implode(", ", array_map("\SmallSmallRSS\Database::quote", $all_tags)) . ")";
            $from_qpart = " FROM ttrss_entries,ttrss_user_entries,ttrss_tags ";
            $where_qpart = " WHERE " .
                "ref_id = ttrss_entries.id AND " .
                "ttrss_user_entries.owner_uid = $owner_uid AND " .
                "post_int_id = int_id AND $tag_sql AND " .
                $view_query_part .
                $search_query_part .
                $query_strategy_part . " ORDER BY $order_by " .
                $limit_query_part;

        } else {
            $i = 1;
            $sub_selects = array();
            $sub_ands = array();
            foreach ($all_tags as $term) {
                array_push(
                    $sub_selects,
                    "(SELECT post_int_id from ttrss_tags WHERE tag_name = "
                    . \SmallSmallRSS\Database::quote($term)
                    . " AND owner_uid = $owner_uid) as A$i"
                );
                $i++;
            }
            if ($i > 2) {
                $x = 1;
                $y = 2;
                do {
                    array_push($sub_ands, "A$x.post_int_id = A$y.post_int_id");
                    $x++;
                    $y++;
                } while ($y < $i);
            }
            array_push($sub_ands, "A1.post_int_id = ttrss_user_entries.int_id and ttrss_user_entries.owner_uid = $owner_uid");
            array_push($sub_ands, "ttrss_user_entries.ref_id = ttrss_entries.id");
            $from_qpart = " FROM " . implode(", ", $sub_selects) . ", ttrss_user_entries, ttrss_entries";
            $where_qpart = " WHERE " . implode(" AND ", $sub_ands);
        }
        $result = \SmallSmallRSS\Database::query($select_qpart . $from_qpart . $where_qpart);
    }
    return array($result, $feed_title, $feed_site_url, $last_error, $last_updated);
}

function sanitize($str, $force_remove_images = false, $owner = false, $site_url = false)
{
    if (!$owner) {
        $owner = $_SESSION["uid"];
    }
    $res = trim($str);
    if (!$res) {
        return '';
    }
    if (strpos($res, "href=") === false) {
        $res = rewrite_urls($res);
    }
    $charset_hack = '<head>
            <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
        </head>';

    $res = trim($res);
    if (!$res) {
        return '';
    }
    libxml_use_internal_errors(true);

    $doc = new DOMDocument();
    $doc->loadHTML($charset_hack . $res);
    $xpath = new DOMXPath($doc);

    $entries = $xpath->query('(//a[@href]|//img[@src])');
    foreach ($entries as $entry) {
        if ($site_url) {
            if ($entry->hasAttribute('href')) {
                $entry->setAttribute('href', \SmallSmallRSS\Utils::rewriteRelativeUrl($site_url, $entry->getAttribute('href')));
                $entry->setAttribute('rel', 'noreferrer');
            }
            if ($entry->nodeName == 'img') {
                if ($entry->hasAttribute('src')) {
                    \SmallSmallRSS\ImageCache::processEntry($entry, false);
                }
                if (($owner && \SmallSmallRSS\DBPrefs::read("STRIP_IMAGES", $owner)) ||
                    $force_remove_images || !empty($_SESSION["bw_limit"])) {
                    $p = $doc->createElement('p');
                    $a = $doc->createElement('a');
                    $a->setAttribute('href', $entry->getAttribute('src'));
                    $a->appendChild(new DOMText($entry->getAttribute('src')));
                    $a->setAttribute('target', '_blank');
                    $p->appendChild($a);
                    $entry->parentNode->replaceChild($p, $entry);
                }
            }
        }
        if (strtolower($entry->nodeName) == "a") {
            $entry->setAttribute("target", "_blank");
        }
    }

    $entries = $xpath->query('//iframe');
    foreach ($entries as $entry) {
        $entry->setAttribute('sandbox', 'allow-scripts');

    }

    $allowed_elements = array('a', 'address', 'audio', 'article', 'aside',
                              'b', 'bdi', 'bdo', 'big', 'blockquote', 'body', 'br',
                              'caption', 'cite', 'center', 'code', 'col', 'colgroup',
                              'data', 'dd', 'del', 'details', 'div', 'dl', 'font',
                              'dt', 'em', 'footer', 'figure', 'figcaption',
                              'h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'header', 'html', 'i',
                              'img', 'ins', 'kbd', 'li', 'main', 'mark', 'nav', 'noscript',
                              'ol', 'p', 'pre', 'q', 'ruby', 'rp', 'rt', 's', 'samp', 'section',
                              'small', 'source', 'span', 'strike', 'strong', 'sub', 'summary',
                              'sup', 'table', 'tbody', 'td', 'tfoot', 'th', 'thead', 'time',
                              'tr', 'track', 'tt', 'u', 'ul', 'var', 'wbr', 'video');

    if (!empty($_SESSION['hasSandbox'])) {
        $allowed_elements[] = 'iframe';
    }

    $disallowed_attributes = array('id', 'style', 'class');

    foreach (\SmallSmallRSS\PluginHost::getInstance()->get_hooks(\SmallSmallRSS\Hooks::SANITIZE) as $plugin) {
        $retval = $plugin->hook_sanitize($doc, $site_url, $allowed_elements, $disallowed_attributes);
        if (is_array($retval)) {
            $doc = $retval[0];
            $allowed_elements = $retval[1];
            $disallowed_attributes = $retval[2];
        } else {
            $doc = $retval;
        }
    }
    $doc->removeChild($doc->firstChild); //remove doctype
    $doc = strip_harmful_tags($doc, $allowed_elements, $disallowed_attributes);
    $res = $doc->saveHTML();
    return $res;
}

function strip_harmful_tags($doc, $allowed_elements, $disallowed_attributes)
{
    $xpath = new DOMXPath($doc);
    $entries = $xpath->query('//*');
    foreach ($entries as $entry) {
        if (!in_array($entry->nodeName, $allowed_elements)) {
            $entry->parentNode->removeChild($entry);
        }
        if ($entry->hasAttributes()) {
            $attrs_to_remove = array();
            foreach ($entry->attributes as $attr) {
                if (strpos($attr->nodeName, 'on') === 0) {
                    array_push($attrs_to_remove, $attr);
                }
                if (in_array($attr->nodeName, $disallowed_attributes)) {
                    array_push($attrs_to_remove, $attr);
                }
            }
            foreach ($attrs_to_remove as $attr) {
                $entry->removeAttributeNode($attr);
            }
        }
    }
    return $doc;
}

function catchupArticlesById($ids, $cmode, $owner_uid = false)
{
    if (!$owner_uid) {
        $owner_uid = $_SESSION["uid"];
    }
    if (count($ids) == 0) {
        return;
    }
    $tmp_ids = array();
    foreach ($ids as $id) {
        array_push($tmp_ids, "ref_id = '$id'");
    }
    $ids_qpart = join(" OR ", $tmp_ids);
    if ($cmode == 0) {
        \SmallSmallRSS\Database::query(
            "UPDATE ttrss_user_entries
             SET unread = false, last_read = NOW()
             WHERE ($ids_qpart) AND owner_uid = $owner_uid"
        );
    } elseif ($cmode == 1) {
        \SmallSmallRSS\Database::query(
            "UPDATE ttrss_user_entries SET
            unread = true
            WHERE ($ids_qpart) AND owner_uid = $owner_uid"
        );
    } else {
        \SmallSmallRSS\Database::query(
            "UPDATE ttrss_user_entries SET
            unread = NOT unread,last_read = NOW()
            WHERE ($ids_qpart) AND owner_uid = $owner_uid"
        );
    }

    /* update ccache */
    $result = \SmallSmallRSS\Database::query(
        "SELECT DISTINCT feed_id
         FROM ttrss_user_entries
         WHERE ($ids_qpart) AND owner_uid = $owner_uid"
    );
    while (($line = \SmallSmallRSS\Database::fetch_assoc($result))) {
        \SmallSmallRSS\CountersCache::update($line["feed_id"], $owner_uid);
    }
}

function get_article_tags($id, $owner_uid = 0, $tag_cache = false)
{

    $a_id = \SmallSmallRSS\Database::escape_string($id);

    if (!$owner_uid) {
        $owner_uid = $_SESSION["uid"];
    }



    $tags = array();

    /* check cache first */

    if ($tag_cache === false) {
        $result = \SmallSmallRSS\Database::query(
            "SELECT tag_cache FROM ttrss_user_entries
             WHERE ref_id = '$id' AND owner_uid = $owner_uid"
        );
        $tag_cache = \SmallSmallRSS\Database::fetch_result($result, 0, "tag_cache");
    }
    if ($tag_cache) {
        $tags = explode(",", $tag_cache);
    } else {

        /* do it the hard way */
        $query = "SELECT DISTINCT tag_name,
                  owner_uid as owner
              FROM
                  ttrss_tags
              WHERE post_int_id = (
                  SELECT int_id
                  FROM ttrss_user_entries
                  WHERE
                      ref_id = '$a_id'
                      AND owner_uid = '$owner_uid'
                  LIMIT 1
              ) ORDER BY tag_name";
        $tmp_result = \SmallSmallRSS\Database::query($query);

        while ($tmp_line = \SmallSmallRSS\Database::fetch_assoc($tmp_result)) {
            array_push($tags, $tmp_line["tag_name"]);
        }

        /* update the cache */

        $tags_str = \SmallSmallRSS\Database::escape_string(join(",", $tags));

        \SmallSmallRSS\Database::query(
            "UPDATE ttrss_user_entries
             SET tag_cache = '$tags_str'
             WHERE ref_id = '$id'
             AND owner_uid = $owner_uid"
        );
    }

    return $tags;
}

function trim_array($array)
{
    $tmp = $array;
    array_walk($tmp, 'trim');
    return $tmp;
}

function tag_is_valid($tag)
{
    if ($tag == '') {
        return false;
    }
    if (preg_match("/^[0-9]*$/", $tag)) {
        return false;
    }
    if (mb_strlen($tag) > 250) {
        return false;
    }

    if (function_exists('iconv')) {
        $tag = iconv("utf-8", "utf-8", $tag);
    }

    if (!$tag) {
        return false;
    }
    return true;
}

function T_sprintf()
{
    $args = func_get_args();
    return vsprintf(__(array_shift($args)), $args);
}

function format_inline_player($url, $ctype)
{
    $entry = "";
    $url = htmlspecialchars($url);
    if (strpos($ctype, "audio/") === 0) {
        if ($_SESSION["hasAudio"]
            && (strpos($ctype, "ogg") !== false || $_SESSION["hasMp3"])
        ) {
            $entry .= "<audio preload=\"none\" controls>
                         <source type=\"$ctype\" src=\"$url\"></source>
                       </audio>";
        } else {
            $entry .= "<object type=\"application/x-shockwave-flash\"
                        data=\"lib/button/musicplayer.swf?song_url=$url\"
                        width=\"17\" height=\"17\" style='float : left; margin-right : 5px;'>
                         <param name=\"movie\" value=\"lib/button/musicplayer.swf?song_url=$url\" />
                       </object>";
        }
        if ($entry) {
            $entry .= "&nbsp; <a target=\"_blank\" href=\"$url\">" . basename($url) . "</a>";
        }
    }
    return $entry;
}

function format_article($id, $mark_as_read = true, $zoom_mode = false, $owner_uid = false)
{
    if (!$owner_uid) {
        $owner_uid = $_SESSION["uid"];
    }

    $rv = array();

    $rv['id'] = $id;

    /* we can figure out feed_id from article id anyway, why do we
     * pass feed_id here? let's ignore the argument :(*/

    $result = \SmallSmallRSS\Database::query(
        "SELECT feed_id FROM ttrss_user_entries
         WHERE ref_id = '$id'"
    );

    $feed_id = (int) \SmallSmallRSS\Database::fetch_result($result, 0, "feed_id");

    $rv['feed_id'] = $feed_id;

    if ($mark_as_read) {
        $result = \SmallSmallRSS\Database::query(
            "UPDATE ttrss_user_entries
             SET unread = false,last_read = NOW()
             WHERE ref_id = '$id' AND owner_uid = $owner_uid"
        );
        \SmallSmallRSS\CountersCache::update($feed_id, $owner_uid);
    }
    $substring_for_date = \SmallSmallRSS\Config::get('SUBSTRING_FOR_DATE');
    $result = \SmallSmallRSS\Database::query(
        "SELECT
             id,
             title,
             link,
             content,
             feed_id,
             comments,
             int_id,
             ".$substring_for_date."(updated,1,16) as updated,
             (SELECT site_url FROM ttrss_feeds WHERE id = feed_id) as site_url,
             (SELECT hide_images FROM ttrss_feeds WHERE id = feed_id) as hide_images,
             (
                  SELECT always_display_enclosures
                  FROM ttrss_feeds
                  WHERE id = feed_id
             ) as always_display_enclosures,
             num_comments,
             tag_cache,
             author,
             orig_feed_id,
             note,
             cached_content
         FROM ttrss_entries,ttrss_user_entries
         WHERE
             id = '$id'
             AND ref_id = id
             AND owner_uid = $owner_uid"
    );

    if ($result) {
        $line = \SmallSmallRSS\Database::fetch_assoc($result);
        $tag_cache = $line["tag_cache"];
        $line["tags"] = get_article_tags($id, $owner_uid, $line["tag_cache"]);
        unset($line["tag_cache"]);
        $line["content"] = sanitize(
            $line["content"],
            sql_bool_to_bool($line['hide_images']),
            $owner_uid,
            $line["site_url"]
        );
        foreach (\SmallSmallRSS\PluginHost::getInstance()->get_hooks(\SmallSmallRSS\Hooks::FILTER_ARTICLE) as $p) {
            $line = $p->hookFilterArticle($line);
        }
        $num_comments = $line["num_comments"];
        $entry_comments = "";
        if ($num_comments > 0) {
            if ($line["comments"]) {
                $comments_url = htmlspecialchars($line["comments"]);
            } else {
                $comments_url = htmlspecialchars($line["link"]);
            }
            $entry_comments = "<a target='_blank' href=\"$comments_url\">$num_comments comments</a>";
        } else {
            if ($line["comments"] && $line["link"] != $line["comments"]) {
                $entry_comments = "<a target='_blank' href=\"".htmlspecialchars($line["comments"])."\">comments</a>";
            }
        }
        if ($zoom_mode) {
            header("Content-Type: text/html");
            $rv['content'] .= "<html><head>
                        <meta http-equiv=\"Content-Type\" content=\"text/html; charset=utf-8\"/>
                        <title>Tiny Tiny RSS - ".$line["title"]."</title>
                        <link rel=\"stylesheet\" type=\"text/css\" href=\"css/tt-rss.css\">
                    </head><body id=\"ttrssZoom\">";
        }
        $rv['content'] .= "<div class=\"postReply\" id=\"POST-$id\">";
        $rv['content'] .= "<div class=\"postHeader\" id=\"POSTHDR-$id\">";
        $entry_author = $line["author"];
        if ($entry_author) {
            $entry_author = __(" - ") . $entry_author;
        }
        $parsed_updated = make_local_datetime(
            $line["updated"],
            true,
            $owner_uid,
            true
        );
        $rv['content'] .= "<div class=\"postDate\">$parsed_updated</div>";
        if ($line["link"]) {
            $rv['content'] .= "<div class='postTitle'><a target='_blank'
                    title=\"".htmlspecialchars($line['title'])."\"
                    href=\"" .
                htmlspecialchars($line["link"]) . "\">" .
                $line["title"] . "</a>" .
                "<span class='author'>$entry_author</span></div>";
        } else {
            $rv['content'] .= "<div class='postTitle'>" . $line["title"] . "$entry_author</div>";
        }

        $tags_str = format_tags_string($line["tags"], $id);
        $tags_str_full = join(", ", $line["tags"]);
        if (!$tags_str_full) {
            $tags_str_full = __("no tags");
        }
        if (!$entry_comments) {
            $entry_comments = "&nbsp;"; # placeholder
        }

        $rv['content'] .= "<div class='postTags' style='float : right'>
                <img src='images/tag.png'
                class='tagsPic' alt='Tags' title='Tags'>&nbsp;";

        if (!$zoom_mode) {
            $rv['content'] .= "<span id=\"ATSTR-$id\">$tags_str</span>
                    <a title=\"".__('Edit tags for this article')."\"
                    href=\"#\" onclick=\"editArticleTags($id, $feed_id)\">(+)</a>";

            $rv['content'] .= "<div dojoType=\"dijit.Tooltip\"
                    id=\"ATSTRTIP-$id\" connectId=\"ATSTR-$id\"
                    position=\"below\">$tags_str_full</div>";
            foreach (\SmallSmallRSS\PluginHost::getInstance()->get_hooks(\SmallSmallRSS\Hooks::ARTICLE_BUTTON) as $p) {
                $rv['content'] .= $p->hookArticleButton($line);
            }

        } else {
            $tags_str = strip_tags($tags_str);
            $rv['content'] .= "<span id=\"ATSTR-$id\">$tags_str</span>";
        }
        $rv['content'] .= "</div>";
        $rv['content'] .= "<div clear='both'>";

        foreach (\SmallSmallRSS\PluginHost::getInstance()->get_hooks(\SmallSmallRSS\Hooks::ARTICLE_LEFT_BUTTON) as $p) {
            $rv['content'] .= $p->hookArticleLeftButton($line);
        }

        $rv['content'] .= "$entry_comments</div>";

        if ($line["orig_feed_id"]) {

            $tmp_result = \SmallSmallRSS\Database::query(
                "SELECT * FROM ttrss_archived_feeds
                 WHERE id = ".$line["orig_feed_id"]
            );

            if (\SmallSmallRSS\Database::num_rows($tmp_result) != 0) {

                $rv['content'] .= "<div clear='both'>";
                $rv['content'] .= __("Originally from:");

                $rv['content'] .= "&nbsp;";

                $tmp_line = \SmallSmallRSS\Database::fetch_assoc($tmp_result);

                $rv['content'] .= "<a target='_blank'
                        href=' " . htmlspecialchars($tmp_line['site_url']) . "'>" .
                    $tmp_line['title'] . "</a>";

                $rv['content'] .= "&nbsp;";

                $rv['content'] .= "<a target='_blank' href='" . htmlspecialchars($tmp_line['feed_url']) . "'>";
                $rv['content'] .= "<img title='".__('Feed URL')."'class='tinyFeedIcon' src='images/pub_set.svg'></a>";

                $rv['content'] .= "</div>";
            }
        }
        $rv['content'] .= "</div>";
        $rv['content'] .= "<div id=\"POSTNOTE-$id\">";
        if ($line['note']) {
            $rv['content'] .= format_article_note($id, $line['note'], !$zoom_mode);
        }
        $rv['content'] .= "</div>";
        $rv['content'] .= "<div class=\"postContent\">";
        $rv['content'] .= $line["content"];
        $rv['content'] .= format_article_enclosures(
            $id,
            sql_bool_to_bool($line["always_display_enclosures"]),
            $line["content"],
            sql_bool_to_bool($line["hide_images"])
        );
        $rv['content'] .= "</div>";
        $rv['content'] .= "</div>";
    }

    if ($zoom_mode) {
        $rv['content'] .= "
                <div class='footer'>
                <button onclick=\"return window.close()\">".
            __("Close this window")."</button></div>";
        $rv['content'] .= "</body></html>";
    }
    return $rv;
}

function print_checkpoint($n, $s)
{
    $ts = microtime(true);
    echo sprintf("<!-- CP[$n] %.4f seconds -->\n", $ts - $s);
    return $ts;
}

function sanitize_tag($tag)
{
    $tag = trim($tag);
    $tag = mb_strtolower($tag, 'utf-8');
    $tag = preg_replace('/[\'\"\+\>\<]/', "", $tag);
    //        $tag = str_replace('"', "", $tag);
    //        $tag = str_replace("+", " ", $tag);
    $tag = str_replace("technorati tag: ", "", $tag);
    return $tag;
}

function get_self_url_prefix()
{
    $self_url_path = \SmallSmallRSS\Config::get('SELF_URL_PATH');
    if (strrpos($self_url_path, "/") === strlen($self_url_path) - 1) {
        return substr($self_url_path, 0, strlen($self_url_path) - 1);
    } else {
        return $self_url_path;
    }
}

/**
 * Compute the Mozilla Firefox feed adding URL from server HOST and REQUEST_URI.
 *
 * @return string The Mozilla Firefox feed adding URL.
 */
function add_feed_url()
{
    $url_path = get_self_url_prefix() . "/public.php?op=subscribe&feed_url=%s";
    return $url_path;
}

function encrypt_password($pass, $salt = '', $mode2 = false)
{
    if ($salt && $mode2) {
        return "MODE2:" . hash('sha256', $salt . $pass);
    } elseif ($salt) {
        return "SHA1X:" . sha1("$salt:$pass");
    } else {
        return "SHA1:" . sha1($pass);
    }
}

function load_filters($feed_id, $owner_uid, $action_id = false)
{
    $filters = array();
    $cat_id = (int) getFeedCategory($feed_id);
    $result = \SmallSmallRSS\Database::query(
        "SELECT * FROM ttrss_filters2
         WHERE
             owner_uid = $owner_uid
             AND enabled = true
         ORDER BY order_id, title"
    );

    $check_cats = join(
        ',',
        array_merge(getParentCategories($cat_id, $owner_uid), array($cat_id))
    );

    while (($line = \SmallSmallRSS\Database::fetch_assoc($result))) {
        $filter_id = $line["id"];

        $result2 = \SmallSmallRSS\Database::query(
            "SELECT
                 r.reg_exp, r.inverse, r.feed_id, r.cat_id, r.cat_filter, t.name AS type_name
             FROM
                 ttrss_filters2_rules AS r,
                 ttrss_filter_types AS t
             WHERE
                 (cat_id IS NULL OR cat_id IN ($check_cats))
                 AND (feed_id IS NULL OR feed_id = '$feed_id')
                 AND filter_type = t.id
                 AND filter_id = '$filter_id'"
        );

        $rules = array();
        $actions = array();

        while ($rule_line = \SmallSmallRSS\Database::fetch_assoc($result2)) {
            #                print_r($rule_line);

            $rule = array();
            $rule["reg_exp"] = $rule_line["reg_exp"];
            $rule["type"] = $rule_line["type_name"];
            $rule["inverse"] = sql_bool_to_bool($rule_line["inverse"]);

            array_push($rules, $rule);
        }

        $result2 = \SmallSmallRSS\Database::query(
            "SELECT a.action_param,t.name AS type_name
             FROM
                 ttrss_filters2_actions AS a,
                 ttrss_filter_actions AS t
             WHERE
                 action_id = t.id AND filter_id = '$filter_id'"
        );

        while ($action_line = \SmallSmallRSS\Database::fetch_assoc($result2)) {
            #                print_r($action_line);

            $action = array();
            $action["type"] = $action_line["type_name"];
            $action["param"] = $action_line["action_param"];

            array_push($actions, $action);
        }


        $filter = array();
        $filter["match_any_rule"] = sql_bool_to_bool($line["match_any_rule"]);
        $filter["inverse"] = sql_bool_to_bool($line["inverse"]);
        $filter["rules"] = $rules;
        $filter["actions"] = $actions;

        if (count($rules) > 0 && count($actions) > 0) {
            array_push($filters, $filter);
        }
    }

    return $filters;
}

function get_score_pic($score)
{
    if ($score > 100) {
        return "score_high.png";
    } elseif ($score > 0) {
        return "score_half_high.png";
    } elseif ($score < -100) {
        return "score_low.png";
    } elseif ($score < 0) {
        return "score_half_low.png";
    } else {
        return "score_neutral.png";
    }
}

function format_tags_string($tags, $id)
{
    $tags_str = '';
    if (!is_array($tags) || count($tags) == 0) {
        return __("no tags");
    } else {
        $maxtags = min(5, count($tags));

        for ($i = 0; $i < $maxtags; $i++) {
            $tags_str .= "<a class=\"tag\" href=\"#\" onclick=\"viewfeed('".$tags[$i]."')\">" . $tags[$i] . "</a>, ";
        }
        $tags_str = mb_substr($tags_str, 0, mb_strlen($tags_str)-2);
        if (count($tags) > $maxtags) {
            $tags_str .= ", &hellip;";
        }
        return $tags_str;
    }
}

function format_article_labels($labels, $id)
{

    if (!is_array($labels)) {
        return '';
    }
    $labels_str = "";
    foreach ($labels as $l) {
        $labels_str .= sprintf(
            "<span class='hlLabelRef' style='color : %s; background-color : %s'>%s</span>",
            $l[2], $l[3], $l[1]
        );
    }

    return $labels_str;

}

function format_article_note($id, $note, $allow_edit = true)
{
    $str = "<div class='articleNote'    onclick=\"editArticleNote($id)\">
            <div class='noteEdit' onclick=\"editArticleNote($id)\">".
        ($allow_edit ? __('(edit note)') : "")."</div>$note</div>";

    return $str;
}

/**
 * Fixes incomplete URLs by prepending "http://".
 * Also replaces feed:// with http://, and
 * prepends a trailing slash if the url is a domain name only.
 *
 * @param string $url Possibly incomplete URL
 *
 * @return string Fixed URL.
 */
function fix_url($url)
{
    if (strpos($url, '://') === false) {
        $url = 'http://' . $url;
    } elseif (substr($url, 0, 5) == 'feed:') {
        $url = 'http:' . substr($url, 5);
    }

    //prepend slash if the URL has no slash in it
    // "http://www.example" -> "http://www.example/"
    if (strpos($url, '/', strpos($url, ':') + 3) === false) {
        $url .= '/';
    }

    if ($url != "http:///") {
        return $url;
    } else {
        return '';
    }
}

function validate_feed_url($url)
{
    $parts = parse_url($url);
    return ($parts['scheme'] == 'http' || $parts['scheme'] == 'feed' || $parts['scheme'] == 'https');

}

function get_article_enclosures($post_id)
{
    return \SmallSmallRSS\Enclosures::get($post_id);
}

function save_email_address($email)
{
    // FIXME: implement persistent storage of emails
    if (!$_SESSION['stored_emails']) {
        $_SESSION['stored_emails'] = array();
    }
    if (!in_array($email, $_SESSION['stored_emails'])) {
        array_push($_SESSION['stored_emails'], $email);
    }
}


function get_feed_access_key($feed_id, $is_cat, $owner_uid = false)
{
    if (!$owner_uid) {
        $owner_uid = $_SESSION["uid"];
    }
    return \SmallSmallRSS\AccessKeys::getForFeed($feed_id, $is_cat, $owner_uid);
}

function get_feeds_from_html($url, $content)
{
    $url = fix_url($url);
    $baseUrl = substr($url, 0, strrpos($url, '/') + 1);

    libxml_use_internal_errors(true);

    $doc = new DOMDocument();
    $doc->loadHTML($content);
    $xpath = new DOMXPath($doc);
    $entries = $xpath->query('/html/head/link[@rel="alternate"]');
    $feedUrls = array();
    foreach ($entries as $entry) {
        if ($entry->hasAttribute('href')) {
            $title = $entry->getAttribute('title');
            if ($title == '') {
                $title = $entry->getAttribute('type');
            }
            $feedUrl = \SmallSmallRSS\Utils::rewriteRelativeUrl($baseUrl, $entry->getAttribute('href'));
            $feedUrls[$feedUrl] = $title;
        }
    }
    return $feedUrls;
}


function print_label_select($name, $value, $attributes = "")
{

    $result = \SmallSmallRSS\Database::query(
        "SELECT caption FROM ttrss_labels2
         WHERE owner_uid = '".$_SESSION["uid"]."' ORDER BY caption"
    );

    print "<select default=\"$value\" name=\"" . htmlspecialchars($name) .
        "\" $attributes onchange=\"labelSelectOnChange(this)\" >";

    while (($line = \SmallSmallRSS\Database::fetch_assoc($result))) {

        $issel = ($line["caption"] == $value) ? "selected=\"1\"" : "";

        print "<option value=\"".htmlspecialchars($line["caption"])."\"
                $issel>" . htmlspecialchars($line["caption"]) . "</option>";

    }

    #        print "<option value=\"ADD_LABEL\">" .__("Add label...") . "</option>";

    print "</select>";


}

function format_article_enclosures(
    $id,
    $always_display_enclosures,
    $article_content,
    $hide_images = false
) {

    $result = get_article_enclosures($id);
    $rv = '';

    if (count($result) > 0) {

        $entries_html = array();
        $entries = array();
        $entries_inline = array();

        foreach ($result as $line) {

            $url = $line["content_url"];
            $ctype = $line["content_type"];

            if (!$ctype) {
                $ctype = __("unknown type");
            }

            $filename = substr($url, strrpos($url, "/")+1);

            $player = format_inline_player($url, $ctype);

            if ($player) {
                array_push($entries_inline, $player);
            }

            $entry = "<div onclick=\"window.open('".htmlspecialchars($url)."')\"
                    dojoType=\"dijit.MenuItem\">$filename ($ctype)</div>";

            array_push($entries_html, $entry);

            $entry = array();

            $entry["type"] = $ctype;
            $entry["filename"] = $filename;
            $entry["url"] = $url;

            array_push($entries, $entry);
        }

        if ($_SESSION['uid'] && !\SmallSmallRSS\DBPrefs::read("STRIP_IMAGES") && !$_SESSION["bw_limit"]) {
            if ($always_display_enclosures ||
                !preg_match("/<img/i", $article_content)) {

                foreach ($entries as $entry) {

                    if (preg_match("/image/", $entry["type"]) ||
                        preg_match("/\.(jpg|png|gif|bmp)/i", $entry["filename"])) {

                        if (!$hide_images) {
                            $rv .= "<p><img
                                    alt=\"".htmlspecialchars($entry["filename"])."\"
                                    src=\"" .htmlspecialchars($entry["url"]) . "\"/></p>";
                        } else {
                            $rv .= "<p><a target=\"_blank\"
                                    href=\"".htmlspecialchars($entry["url"])."\"
                                    >" .htmlspecialchars($entry["url"]) . "</a></p>";

                        }
                    }
                }
            }
        }

        if (count($entries_inline) > 0) {
            $rv .= "<hr clear='both'/>";
            foreach ($entries_inline as $entry) {
                $rv .= $entry;
            };
            $rv .= "<hr clear='both'/>";
        }

        $rv .= "<select class=\"attachments\" onchange=\"openSelectedAttachment(this)\">".
            "<option value=''>" . __('Attachments')."</option>";

        foreach ($entries as $entry) {
            $rv .= "<option value=\"".htmlspecialchars($entry["url"])."\">" . htmlspecialchars($entry["filename"]) . "</option>";

        };

        $rv .= "</select>";
    }

    return $rv;
}

function getLastArticleId()
{
    return \SmallSmallRSS\UserEntries::getLastId($_SESSION["uid"]);
}

function sphinx_search($query, $offset = 0, $limit = 30)
{
    require_once __DIR__ . '/../lib/sphinxapi.php';
    $sphinxClient = new SphinxClient();
    $sphinxpair = explode(":", \SmallSmallRSS\Config::get('SPHINX_SERVER'), 2);
    $sphinxClient->SetServer($sphinxpair[0], (int) $sphinxpair[1]);
    $sphinxClient->SetConnectTimeout(1);
    $sphinxClient->SetFieldWeights(
        array(
            'title' => 70,
            'content' => 30,
            'feed_title' => 20
        )
    );
    $sphinxClient->SetMatchMode(SPH_MATCH_EXTENDED2);
    $sphinxClient->SetRankingMode(SPH_RANK_PROXIMITY_BM25);
    $sphinxClient->SetLimits($offset, $limit, 1000);
    $sphinxClient->SetArrayResult(false);
    $sphinxClient->SetFilter('owner_uid', array($_SESSION['uid']));

    $result = $sphinxClient->Query(
        $query,
        \SmallSmallRSS\Config::get('SPHINX_INDEX')
    );

    $ids = array();

    if (is_array($result['matches'])) {
        foreach (array_keys($result['matches']) as $int_id) {
            $ref_id = $result['matches'][$int_id]['attrs']['ref_id'];
            array_push($ids, $ref_id);
        }
    }

    return $ids;
}



function rewrite_urls($html)
{
    libxml_use_internal_errors(true);
    $charset_hack = '<head>
                     <meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
                     </head>';
    $doc = new DOMDocument();
    $doc->loadHTML($charset_hack . $html);
    $xpath = new DOMXPath($doc);
    $entries = $xpath->query('//*/text()');
    foreach ($entries as $entry) {
        if (strstr($entry->wholeText, "://") !== false) {
            $text = preg_replace(
                "/((?<!=.)((http|https|ftp)+):\/\/[^ ,!]+)/i",
                "<a target=\"_blank\" href=\"\\1\">\\1</a>",
                $entry->wholeText
            );
            if ($text != $entry->wholeText) {
                $cdoc = new DOMDocument();
                $cdoc->loadHTML($charset_hack . $text);
                foreach ($cdoc->childNodes as $cnode) {
                    $cnode = $doc->importNode($cnode, true);
                    if ($cnode) {
                        $entry->parentNode->insertBefore($cnode);
                    }
                }
                $entry->parentNode->removeChild($entry);
            }
        }
    }
    $node = $doc->getElementsByTagName('body')->item(0);
    // http://tt-rss.org/forum/viewtopic.php?f=1&t=970
    if ($node) {
        return $doc->saveXML($node);
    } else {
        return $html;
    }
}

function filter_to_sql($filter, $owner_uid)
{
    $query = array();
    if (\SmallSmallRSS\Config::get('DB_TYPE') == "pgsql") {
        $reg_qpart = "~";
    } else {
        $reg_qpart = "REGEXP";
    }
    foreach ($filter["rules"] as $rule) {
        $regexp_valid = preg_match('/' . $rule['reg_exp'] . '/', $rule['reg_exp']) !== false;
        if ($regexp_valid) {
            $rule['reg_exp'] = \SmallSmallRSS\Database::escape_string($rule['reg_exp']);
            switch ($rule["type"]) {
                case "title":
                    $qpart = "LOWER(ttrss_entries.title) $reg_qpart LOWER('".
                        $rule['reg_exp'] . "')";
                    break;
                case "content":
                    $qpart = "LOWER(ttrss_entries.content) $reg_qpart LOWER('".
                        $rule['reg_exp'] . "')";
                    break;
                case "both":
                    $qpart = "LOWER(ttrss_entries.title) $reg_qpart LOWER('".
                        $rule['reg_exp'] . "') OR LOWER(" .
                        "ttrss_entries.content) $reg_qpart LOWER('" . $rule['reg_exp'] . "')";
                    break;
                case "tag":
                    $qpart = "LOWER(ttrss_user_entries.tag_cache) $reg_qpart LOWER('".
                        $rule['reg_exp'] . "')";
                    break;
                case "link":
                    $qpart = "LOWER(ttrss_entries.link) $reg_qpart LOWER('".
                        $rule['reg_exp'] . "')";
                    break;
                case "author":
                    $qpart = "LOWER(ttrss_entries.author) $reg_qpart LOWER('".
                        $rule['reg_exp'] . "')";
                    break;
            }
            if (isset($rule['inverse'])) {
                $qpart = "NOT ($qpart)";
            }
            if (isset($rule["feed_id"]) && $rule["feed_id"] > 0) {
                $qpart .= " AND feed_id = " . \SmallSmallRSS\Database::escape_string($rule["feed_id"]);
            }
            if (isset($rule["cat_id"])) {
                if ($rule["cat_id"] > 0) {
                    $children = getChildCategories($rule["cat_id"], $owner_uid);
                    array_push($children, $rule["cat_id"]);
                    $children = join(",", $children);
                    $cat_qpart = "cat_id IN ($children)";
                } else {
                    $cat_qpart = "cat_id IS NULL";
                }
                $qpart .= " AND $cat_qpart";
            }
            array_push($query, "($qpart)");
        }
    }
    if (count($query) > 0) {
        $fullquery = "(" . join($filter["match_any_rule"] ? "OR" : "AND", $query) . ")";
    } else {
        $fullquery = "(false)";
    }
    if ($filter['inverse']) {
        $fullquery = "(NOT $fullquery)";
    }
    return $fullquery;
}

if (!function_exists('gzdecode')) {
    function gzdecode($string)
    {
        // no support for 2nd argument
        return file_get_contents(
            'compress.zlib://data:who/cares;base64,'.
            base64_encode($string)
        );
    }
}

function get_random_bytes($length)
{
    if (function_exists('openssl_random_pseudo_bytes')) {
        return openssl_random_pseudo_bytes($length);
    } else {
        $output = "";
        for ($i = 0; $i < $length; $i++) {
            $output .= chr(mt_rand(0, 255));
        }
        return $output;
    }
}

function read_stdin()
{
    $fp = fopen("php://stdin", "r");
    if ($fp) {
        $line = trim(fgets($fp));
        fclose($fp);
        return $line;
    }
    return null;
}

function tmpdirname($path, $prefix)
{
    // Use PHP's tmpfile function to create a temporary
    // directory name. Delete the file and keep the name.
    $tempname = tempnam($path, $prefix);
    if (!$tempname) {
        return false;
    }
    if (!unlink($tempname)) {
        return false;
    }
    return $tempname;
}

function getFeedCategory($feed_id)
{
    return \SmallSmallRSS\Feeds::getCategory($feed_id);
}

function calculate_dep_timestamp()
{
    $files = array_merge(glob("js/*.js"), glob("css/*.css"));
    $max_ts = -1;
    foreach ($files as $file) {
        if (filemtime($file) > $max_ts) {
            $max_ts = filemtime($file);
        }
    }
    return $max_ts;
}

function label_to_feed_id($label)
{
    return \SmallSmallRSS\Constants::LABEL_BASE_INDEX - 1 - abs($label);
}

function feed_to_label_id($feed)
{
    return \SmallSmallRSS\Constants::LABEL_BASE_INDEX - 1 + abs($feed);
}

function format_libxml_error($error)
{
    return T_sprintf(
        "LibXML error %s at line %d (column %d): %s",
        $error->code,
        $error->line,
        $error->column,
        $error->message
    );
}