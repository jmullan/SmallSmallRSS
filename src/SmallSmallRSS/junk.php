<?php
function authenticate_user($login, $password, $check_only = false)
{
    if (\SmallSmallRSS\Auth::is_single_user_mode()) {
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
        return true;
    } else {
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
}

function login_sequence()
{
    if (\SmallSmallRSS\Auth::is_single_user_mode()) {
        authenticate_user('admin', null);
        \SmallSmallRSS\PluginHost::loadUserPlugins($_SESSION['uid']);
    } else {
        if (!\SmallSmallRSS\Sessions::validate()) {
            $_SESSION['uid'] = false;
        }
        if (!$_SESSION['uid']) {
            if (\SmallSmallRSS\Config::get('AUTH_AUTO_LOGIN') && authenticate_user(null, null)) {
                $_SESSION['ref_schema_version'] = \SmallSmallRSS\Sanity::getSchemaVersion(true);
            } else {
                authenticate_user(null, null, true);
            }
            if (!$_SESSION['uid']) {
                @session_destroy();
                setcookie(session_name(), '', time()-42000, '/');
                $renderer = new \SmallSmallRSS\Renderers\LoginPage();
                $renderer->render();
                exit;
            }
        } else {
            \SmallSmallRSS\Users::markLogin($_SESSION['uid']);
            $_SESSION['last_login_update'] = time();
        }

        if ($_SESSION['uid']) {
            \SmallSmallRSS\Locale::startupGettext($_SESSION['uid']);
            \SmallSmallRSS\PluginHost::loadUserPlugins($_SESSION['uid']);
            /* cleanup ccache */
            \SmallSmallRSS\CountersCache::cleanup($_SESSION['uid']);
            \SmallSmallRSS\CatCountersCache::cleanup($_SESSION['uid']);
        }
    }
}

function make_local_datetime($timestamp, $long, $owner_uid, $no_smart_dt = false)
{
    static $utc_tz = null;
    if (is_null($utc_tz)) {
        $utc_tz = new DateTimeZone('UTC');
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
        $tz_offset = (int) -$_SESSION['clientTzOffset'];
    }

    $user_timestamp = $dt->format('U') + $tz_offset;

    if (!$no_smart_dt) {
        return \SmallSmallRSS\Utils::smartDateTime($user_timestamp, $owner_uid, $tz_offset);
    } else {
        if ($long) {
            $format = \SmallSmallRSS\DBPrefs::read('LONG_DATE_FORMAT', $owner_uid);
        } else {
            $format = \SmallSmallRSS\DBPrefs::read('SHORT_DATE_FORMAT', $owner_uid);
        }
        return date($format, $user_timestamp);
    }
}

function getAllCounters($owner_uid)
{
    $data = getGlobalCounters($owner_uid);
    $data = array_merge($data, getFeedCounters($owner_uid));
    $data = array_merge($data, getVirtCounters($owner_uid));
    $data = array_merge($data, getLabelCounters($owner_uid));
    $data = array_merge($data, getCategoryCounters($owner_uid));
    return $data;
}

function getGlobalCounters($owner_uid)
{
    $start = microtime(true);
    $global_unread = \SmallSmallRSS\CountersCache::getGlobalUnread($owner_uid);
    $global_unread_time = (microtime(true) - $start) * 1000;
    $start = microtime(true);
    $subscribed_feeds = \SmallSmallRSS\Feeds::count($owner_uid);
    $subscribed_feeds_time = (microtime(true) - $start) * 1000;
    return array(
        array(
            'id' => 'global-unread',
            'counter' => $global_unread,
            'time' => $global_unread_time
        ),
        array(
            'id' => 'subscribed-feeds',
            'counter' => $subscribed_feeds,
            'time' => $subscribed_feeds_time
        )
    );
}

function getVirtCounters($owner_uid)
{
    $ret_arr = array();
    for ($i = 0; $i >= -4; $i--) {
        $start = microtime(true);
        $count = countUnreadFeedArticles($i, false, true, $owner_uid);
        if ($i == 0 || $i == -1 || $i == -2) {
            $auxctr = countUnreadFeedArticles($i, false, false, $owner_uid);
        } else {
            $auxctr = 0;
        }
        $time = (microtime(true) - $start) * 1000;
        $cv = array(
            'id' => $i,
            'counter' => (int) $count,
            'auxcounter' => $auxctr,
            'time' => $time
        );
        $ret_arr[] = $cv;
    }
    $feeds = \SmallSmallRSS\PluginHost::getInstance()->getFeeds(-1);
    if (is_array($feeds)) {
        foreach ($feeds as $feed) {
            $start = microtime(true);
            $count = $feed['sender']->get_unread($feed['id']);
            $time = (microtime(true) - $start) * 1000;
            $cv = array(
                'id' => \SmallSmallRSS\PluginHost::pfeed_to_feed_id($feed['id']),
                'counter' => (int) $count,
                'time' => $time
            );
            $ret_arr[] = $cv;
        }
    }
    return $ret_arr;
}

function getLabelCounters($owner_uid, $descriptions = false)
{
    $start = microtime(true);
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
    $ret_arr = array();
    while (($line = \SmallSmallRSS\Database::fetch_assoc($result))) {
        $id = \SmallSmallRSS\Labels::toFeedId($line['id']);
        $cv = array(
            'id' => $id,
            'counter' => (int) $line['unread'],
            'auxcounter' => (int) $line['total'],
            'time' => 0
        );
        if ($descriptions) {
            $cv['description'] = $line['caption'];
        }
        $ret_arr[] = $cv;
    }
    $time = (microtime(true) - $start) * 1000;
    if ($ret_arr) {
        $time /= count($ret_arr);
    }
    foreach ($ret_arr as $k => $v) {
        $ret_arr[$k]['time'] = $time;
    }
    return $ret_arr;
}


function getCategoryCounters($owner_uid)
{
    $ret_arr = array();
    /* Special case: NULL category doesn't actually exist in the DB */
    $start = microtime(true);
    $ret_arr[] = array(
        'id' => \SmallSmallRSS\FeedCategories::NONE,
        'kind' => 'cat',
        'counter' => (int) \SmallSmallRSS\CountersCache::find(
            \SmallSmallRSS\FeedCategories::NONE,
            $owner_uid,
            true
        ),
        'time' => (microtime(true) - $start) * 1000
    );
    $start = microtime(true);
    $ret_arr[] = array(
        'id' => \SmallSmallRSS\FeedCategories::LABELS,
        'kind' => 'cat',
        'counter' => getCategoryUnread(\SmallSmallRSS\FeedCategories::LABELS, $owner_uid),
        'time' => (microtime(true) - $start) * 1000
    );
    $start = microtime(true);
    $result = \SmallSmallRSS\Database::query(
        'SELECT
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
                AND ttrss_feed_categories.owner_uid = ' . $owner_uid
    );
    $query_time = (microtime(true) - $start) * 1000;
    while (($line = \SmallSmallRSS\Database::fetch_assoc($result))) {
        $start = microtime(true);
        $line['cat_id'] = (int) $line['cat_id'];
        if ($line['num_children'] > 0) {
            $child_counter = getCategoryChildrenUnread($line['cat_id'], $owner_uid);
        } else {
            $child_counter = 0;
        }
        $cv = array(
            'id' => $line['cat_id'],
            'kind' => 'cat',
            'counter' => $line['unread'] + $child_counter,
            'time' => (microtime(true) - $start) * 1000
        );
        $ret_arr[] = $cv;
    }
    return $ret_arr;
}

// only accepts real cats (>= 0)
function getCategoryChildrenUnread($cat, $owner_uid)
{
    $unread = getCategoryUnread($cat, $owner_uid);
    if ($cat == \SmallSmallRSS\FeedCategories::SPECIAL
        || $cat == \SmallSmallRSS\FeedCategories::LABELS
        || $cat == \SmallSmallRSS\FeedCategories::NONE
    ) {
        return $unread;
    }
    $cat_ids = \SmallSmallRSS\FeedCategories::getDescendents($cat, $owner_uid);
    $unreads = getCategoriesUnread($cat_ids, $owner_uid);
    $unread += sum($unreads);
    return $unread + $children_unread;
}

function getCategoriesUnread($cat_ids, $owner_uid)
{
    $unreads = array();
    $regular_cat_ids = array();
    foreach ($cat_ids as $cat_id) {
        if ($cat_id >= 0) {
            $regular_cat_ids[] = $cat_id;
        } else {
            $unreads[$cat_id] = getCategoryUnread($cat_id, $owner_uid);
        }
    }
    if ($regular_cat_ids) {
        $escaped_regular_cat_ids = join(',', $regular_cat_ids);
        $result = \SmallSmallRSS\Database::query(
            "SELECT
                 ttrss_feeds.cat_id,
                 COUNT(ttrss_user_entries.int_id) AS unread
             FROM
                 ttrss_feeds
             INNER JOIN
                 ttrss_user_entries
             WHERE
                 ttrss_feeds.owner_uid = $owner_uid
                 AND ttrss_feeds.cat_id IN ($escaped_regular_cat_ids)

                 AND ttrss_user_entries.feed_id = ttrss_feeds.id

                 AND ttrss_user_entries.unread = true
                 AND ttrss_user_entries.owner_uid = $owner_uid"
        );
        while (($line = \SmallSmallRSS\Database::fetch_assoc($result))) {
            $unreads[$line['cat_id']] == $line['unread'];
        }
    }
    return $unreads;
}

function getCategoryUnread($cat, $owner_uid)
{
    if ($cat >= 0) {
        $feed_ids = \SmallSmallRSS\Feeds::getForCategory($cat, $owner_uid);
        $unread = \SmallSmallRSS\UserEntries::countUnread($feed_ids, $owner_uid);
        return $unread;
    } elseif ($cat == \SmallSmallRSS\FeedCategories::SPECIAL) {
        return (
            countUnreadFeedArticles(-1, false, true, $owner_uid)
            + countUnreadFeedArticles(-2, false, true, $owner_uid)
            + countUnreadFeedArticles(-3, false, true, $owner_uid)
            + countUnreadFeedArticles(0, false, true, $owner_uid)
        );
    } elseif ($cat == \SmallSmallRSS\FeedCategories::LABELS) {
        $result = \SmallSmallRSS\Database::query(
            "SELECT COUNT(unread) AS unread
             FROM
                 ttrss_user_entries,
                 ttrss_user_labels2
             WHERE
                 article_id = ref_id
                 AND unread = true
                 AND ttrss_user_entries.owner_uid = '$owner_uid'"
        );
        $unread = \SmallSmallRSS\Database::fetch_result($result, 0, 'unread');
        return $unread;
    }
}

function getLabelUnread($label_id, $owner_uid)
{
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
        return \SmallSmallRSS\Database::fetch_result($result, 0, 'unread');
    } else {
        return 0;
    }
}

function countUnreadFeedArticles($feed, $is_cat, $unread_only, $owner_uid)
{
    $n_feed = (int) $feed;
    $need_entries = false;
    if ($unread_only) {
        $unread_qpart = 'unread = true';
    } else {
        $unread_qpart = 'true';
    }
    if ($is_cat) {
        return getCategoryUnread($n_feed, $owner_uid);
    } elseif ($n_feed == -6) {
        return 0;
    } elseif ($feed != '0' && $n_feed == 0) {
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
        return \SmallSmallRSS\Database::fetch_result($result, 0, 'count');
    } elseif ($n_feed == -1) {
        $match_part = 'marked = true';
    } elseif ($n_feed == -2) {
        $match_part = 'published = true';
    } elseif ($n_feed == -3) {
        $match_part = 'unread = true AND score >= 0';
        $intl = \SmallSmallRSS\DBPrefs::read('FRESH_ARTICLE_MAX_AGE', $owner_uid);
        if (\SmallSmallRSS\Config::get('DB_TYPE') == 'pgsql') {
            $match_part .= " AND updated > NOW() - INTERVAL '$intl hour' ";
        } else {
            $match_part .= " AND updated > DATE_SUB(NOW(), INTERVAL $intl HOUR) ";
        }
        $need_entries = true;
    } elseif ($n_feed == -4) {
        $match_part = 'true';
    } elseif ($n_feed >= 0) {
        if ($n_feed != 0) {
            $match_part = "feed_id = '$n_feed'";
        } else {
            $match_part = 'feed_id IS NULL';
        }
    } elseif ($feed < \SmallSmallRSS\Constants::LABEL_BASE_INDEX) {
        $label_id = \SmallSmallRSS\Labels::fromFeedId($feed);
        return getLabelUnread($label_id, $owner_uid);
    }
    if ($match_part) {
        if ($need_entries) {
            $from_qpart = 'ttrss_user_entries, ttrss_entries';
            $from_where = 'ttrss_entries.id = ttrss_user_entries.ref_id AND ';
        } else {
            $from_qpart = 'ttrss_user_entries';
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
    $unread = \SmallSmallRSS\Database::fetch_result($result, 0, 'unread');
    return $unread;
}

function getFeedCounters($owner_uid)
{
    $active_feed = false;
    $start = microtime(true);
    $ret_arr = array();
    $substring_for_date = \SmallSmallRSS\Database::getSubstringForDateFunction();
    $query = '
        SELECT
            ttrss_feeds.id,
            ttrss_feeds.title,
            '.$substring_for_date.'(ttrss_feeds.last_updated,1,19) AS last_updated,
            last_error,
            value AS count
        FROM ttrss_feeds, ttrss_counters_cache
        WHERE
            ttrss_feeds.owner_uid = '.$owner_uid.'
            AND ttrss_counters_cache.owner_uid = ttrss_feeds.owner_uid
            AND ttrss_counters_cache.feed_id = id';

    $result = \SmallSmallRSS\Database::query($query);
    $fctrs_modified = false;
    while (($line = \SmallSmallRSS\Database::fetch_assoc($result))) {
        $id = $line['id'];
        $count = $line['count'];
        $last_error = htmlspecialchars($line['last_error']);
        $last_updated = make_local_datetime($line['last_updated'], false, $owner_uid);
        $has_img = \SmallSmallRSS\Feeds::hasIcon($id);
        if (date('Y') - date('Y', strtotime($line['last_updated'])) > 2) {
            $last_updated = '';
        }
        $cv = array(
            'id' => (int) $id,
            'kind' => 'feed',
            'updated' => $last_updated,
            'counter' => (int) $count,
            'has_img' => (int) $has_img,
            'time' => 0
        );
        if ($last_error) {
            $cv['error'] = $last_error;
        }
        $ret_arr[] = $cv;
    }
    $time = (microtime(true) - $start) * 1000 / count($ret_arr);
    foreach ($ret_arr as $k => $v) {
        $ret_arr[$k]['time'] = $time;
    }
    return $ret_arr;
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
        return array('code' => 2);
    }
    $fetcher = new \SmallSmallRSS\Fetcher();
    $contents = $fetcher->getFileContents($url, false, $auth_login, $auth_pass);
    $fetch_last_error = $fetcher->last_error;
    if (!$contents) {
        return array('code' => 5, 'message' => $fetch_last_error);
    }
    if (\SmallSmallRSS\Utils::isHtml($contents)) {
        $feedUrls = get_feeds_from_html($url, $contents);
        if (count($feedUrls) == 0) {
            return array('code' => 3);
        } elseif (count($feedUrls) > 1) {
            return array('code' => 4, 'feeds' => $feedUrls);
        }
        //use feed url as new URL
        $url = key($feedUrls);
    }
    $feed_id = \SmallSmallRSS\Feeds::getByUrl($url, $_SESSION['uid']);
    if (!$feed_id) {
        \SmallSmallRSS\Feeds::add($url, $cat_id, $_SESSION['uid'], $auth_login, $auth_pass);
        $feed_id = \SmallSmallRSS\Feeds::getByUrl($url, $_SESSION['uid']);
        if ($feed_id) {
            \SmallSmallRSS\RSSUpdater::updateFeed($feed_id);
        }
        return array('code' => 1);
    } else {
        return array('code' => 0);
    }
}

function print_feed_select($id, $default_id = '', $attributes = '', $include_all = true, $root_id = false, $depth = 0)
{
    if (!$root_id) {
        print "<select id=\"$id\" name=\"$id\" $attributes>";
        if ($include_all) {
            $is_selected = ('0' == $default_id) ? ' selected="1"' : '';
            print "<option $is_selected value=\"0\">";
            print __('All feeds');
            print '</option>';
        }
    }
    if (\SmallSmallRSS\DBPrefs::read('ENABLE_FEED_CATS')) {
        $children = \SmallSmallRSS\FeedCategories::getChildrenForSelect($root_id, $_SESSION['uid']);
        foreach ($children as $line) {
            $cat_id = intval($line['id']);
            $title = str_repeat(' - ', $depth) . $line['title'];
            $value = 'CAT:' . $cat_id;
            $is_selected = ($value == $default_id) ? ' selected="1"' : '';
            print "<option $is_selected value=\"$value\">";
            print htmlspecialchars($title);
            print '</option>';
            if ($line['num_children'] > 0) {
                print_feed_select($id, $default_id, $attributes, $include_all, $line['id'], $depth + 1);
            }
            $feeds = \SmallSmallRSS\Feeds::getForSelectByCategory($line['id'], $_SESSION['uid']);
            foreach ($feeds as $fline) {
                $is_selected = ($fline['id'] == $default_id) ? ' selected="1"' : '';
                $fline['title'] = ' + ' . $fline['title'];
                for ($i = 0; $i < $depth; $i++) {
                    $fline['title'] = ' - ' . $fline['title'];
                }
                printf(
                    "<option $is_selected value='%d'>%s</option>",
                    $fline['id'],
                    htmlspecialchars($fline['title'])
                );
            }
        }
        if (!$root_id) {
            $default_is_cat = ($default_id == 'CAT:0');
            $is_selected = $default_is_cat ? 'selected="1"' : '';
            printf("<option $is_selected value='CAT:0'>%s</option>", __('Uncategorized'));

            $feeds = \SmallSmallRSS\Feeds::getForSelectByCategory($root_id, $_SESSION['uid']);
            foreach ($feeds as $fline) {
                $is_selected = ($fline['id'] == $default_id && !$default_is_cat) ? ' selected="1"' : '';
                $fline['title'] = ' + ' . $fline['title'];
                for ($i = 0; $i < $depth; $i++) {
                    $fline['title'] = ' - ' . $fline['title'];
                }
                printf(
                    "<option $is_selected value='%d'>%s</option>",
                    $fline['id'],
                    htmlspecialchars($fline['title'])
                );
            }
        }
    } else {
        $feeds = \SmallSmallRSS\Feeds::getAllForSelectByCategory($_SESSION['uid']);
        foreach ($feeds as $line) {
            $is_selected = ($line['id'] == $default_id) ? ' selected="1"' : '';
            printf(
                "<option $is_selected value='%d'>%s</option>",
                $line['id'],
                htmlspecialchars($line['title'])
            );
        }
    }
    if (!$root_id) {
        print '</select>';
    }
}

function print_feed_cat_select($id, $default_id, $attributes, $include_all = true, $root_id = false, $depth = 0)
{
    if (!$root_id) {
        print "<select id=\"$id\" name=\"$id\" default=\"$default_id\" $attributes";
        print ' onchange="catSelectOnChange(this)">';
    }
    $children = \SmallSmallRSS\FeedCategories::getChildrenForSelect($root_id, $_SESSION['uid']);
    foreach ($children as $line) {
        if ($line['id'] == $default_id) {
            $is_selected = ' selected="1"';
        } else {
            $is_selected = '';
        }
        for ($i = 0; $i < $depth; $i++) {
            $line['title'] = ' - ' . $line['title'];
        }
        if ($line['title']) {
            printf(
                "<option $is_selected value='%d'>%s</option>",
                $line['id'],
                htmlspecialchars($line['title'])
            );
        }
        if ($line['num_children'] > 0) {
            print_feed_cat_select($id, $default_id, $attributes, $include_all, $line['id'], $depth + 1);
        }
    }

    if (!$root_id) {
        if ($include_all) {
            if ($children) {
                print '<option disabled="1">--------</option>';
            }
            if ($default_id == 0) {
                $is_selected = ' selected="selected"';
            } else {
                $is_selected = '';
            }
            print "<option $is_selected value=\"0\">";
            echo __('Uncategorized');
            echo '</option>';
        }
        print '</select>';
    }
}

function getFeedIcon($id)
{
    switch ($id) {
        case 0:
            return 'images/archive.png';
            break;
        case -1:
            return 'images/mark_set.svg';
            break;
        case -2:
            return 'images/pub_set.svg';
            break;
        case -3:
            return 'images/fresh.png';
            break;
        case -4:
            return 'images/tag.png';
            break;
        case -6:
            return 'images/recently_read.png';
            break;
        default:
            if ($id < \SmallSmallRSS\Constants::LABEL_BASE_INDEX) {
                return 'images/label.png';
            } else {
                if (file_exists(\SmallSmallRSS\Config::get('ICONS_DIR') . "/$id.ico")) {
                    return \SmallSmallRSS\Config::get('ICONS_URL') . "/$id.ico";
                }
            }
            break;
    }
}

function make_runtime_info()
{
    $data = array();
    $counts = \SmallSmallRSS\Feeds::getCounts($_SESSION['uid']);
    $data['max_feed_id'] = (int) $counts['max_feed_id'];
    $data['num_feeds'] = (int) $counts['num_feeds'];
    $data['last_article_id'] = \SmallSmallRSS\UserEntries::getLastId($_SESSION['uid']);
    $data['cdm_expanded'] = \SmallSmallRSS\DBPrefs::read('CDM_EXPANDED');
    $data['dependency_timestamp'] = calculate_dep_timestamp();
    $data['reload_on_ts_change'] = \SmallSmallRSS\Config::get('RELOAD_ON_TS_CHANGE');
    $feeds_stamp = \SmallSmallRSS\Lockfiles::whenStamped('update_feeds');
    $daemon_stamp = \SmallSmallRSS\Lockfiles::whenStamped('update_daemon');
    $data['daemon_is_running'] = (
        \SmallSmallRSS\Lockfiles::is_locked('update_daemon.lock')
        || (bool) $feeds_stamp
        || (bool) $daemon_stamp
    );
    $last_run = max((int) $daemon_stamp, (int) $feeds_stamp);
    $data['feeds_stamp'] = $feeds_stamp;
    $data['daemon_stamp'] = $daemon_stamp;
    $data['last_run'] = $last_run;
    if ($data['daemon_is_running']) {
        if (!isset($_SESSION['daemon_stamp_check'])
            || time() - $_SESSION['daemon_stamp_check'] > 30) {
            $stamp_delta = time() - $last_run;
            if ($stamp_delta > 1800) {
                $stamp_check = false;
            } else {
                $stamp_check = true;
                $_SESSION['daemon_stamp_check'] = time();
            }
            $data['daemon_stamp_ok'] = $stamp_check;
            $stamp_fmt = date('Y.m.d, G:i', $last_run);
            $data['daemon_stamp'] = $stamp_fmt;
        }
    }
    return $data;
}

function search_to_sql($search)
{
    $search_query_part = '';
    $keywords = explode(' ', $search);
    $query_keywords = array();
    foreach ($keywords as $k) {
        if (strpos($k, '-') === 0) {
            $k = substr($k, 1);
            $not = 'NOT';
        } else {
            $not = '';
        }
        $commandpair = explode(':', mb_strtolower($k), 2);
        switch ($commandpair[0]) {
            case 'title':
                if ($commandpair[1]) {
                    $query_keywords[] = "($not (LOWER(ttrss_entries.title) LIKE '%"
                        . \SmallSmallRSS\Database::escape_string(mb_strtolower($commandpair[1]))."%'))";
                } else {
                    $query_keywords[] = "(UPPER(ttrss_entries.title) $not LIKE UPPER('%$k%')"
                        . " OR UPPER(ttrss_entries.content) $not LIKE UPPER('%$k%'))";
                }
                break;
            case 'author':
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
            case 'note':
                if ($commandpair[1]) {
                    if ($commandpair[1] == 'true') {
                        array_push($query_keywords, "($not (note IS NOT NULL AND note != ''))");
                    } elseif ($commandpair[1] == 'false') {
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
            case 'star':
                if ($commandpair[1]) {
                    if ($commandpair[1] == 'true') {
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
            case 'pub':
                if ($commandpair[1]) {
                    if ($commandpair[1] == 'true') {
                        array_push($query_keywords, "($not (published = true))");
                    } else {
                        array_push($query_keywords, "($not (published = false))");
                    }
                } else {
                    $query_keywords[] = (
                        "(UPPER(ttrss_entries.title) $not LIKE UPPER('%$k%')
                          OR UPPER(ttrss_entries.content) $not LIKE UPPER('%$k%'))"
                    );
                }
                break;
            default:
                if (strpos($k, '@') === 0) {
                    $user_tz_string = \SmallSmallRSS\DBPrefs::read('USER_TIMEZONE', $_SESSION['uid']);
                    $orig_ts = strtotime(substr($k, 1));
                    $k = date('Y-m-d', \SmallSmallRSS\Utils::convertTimestamp($orig_ts, $user_tz_string, 'UTC'));
                    $substring_for_date = \SmallSmallRSS\Database::getSubstringForDateFunction();
                    array_push($query_keywords, '('.$substring_for_date."(updated,1,LENGTH('$k')) $not = '$k')");
                } else {
                    array_push(
                        $query_keywords,
                        "(UPPER(ttrss_entries.title) $not LIKE UPPER('%$k%')
                         OR UPPER(ttrss_entries.content) $not LIKE UPPER('%$k%'))"
                    );
                }
        }
    }
    $search_query_part = implode('AND', $query_keywords);
    return $search_query_part;
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
        $owner_uid = $_SESSION['uid'];
    }
    $ext_tables_part = '';
    if ($search) {
        if (\SmallSmallRSS\Config::get('SPHINX_ENABLED')) {
            $ids = join(',', sphinx_search($search, 0, 500));
            if ($ids) {
                $search_query_part = "ref_id IN ($ids) AND ";
            } else {
                $search_query_part = 'ref_id = -1 AND ';
            }
        } else {
            $search_query_part = search_to_sql($search);
            $search_query_part .= ' AND ';
        }
    } else {
        $search_query_part = '';
    }
    if ($filter) {
        if (\SmallSmallRSS\Config::get('DB_TYPE') == 'pgsql') {
            $query_strategy_part .= " AND updated > NOW() - INTERVAL '14 days' ";
        } else {
            $query_strategy_part .= ' AND updated > DATE_SUB(NOW(), INTERVAL 14 DAY) ';
        }
        $override_order = 'updated DESC';
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
            $test = \SmallSmallRSS\Database::fetch_result($result, 0, 'true_val');
            if (!$test) {
                $filter_query_part = 'false AND';
            } else {
                $filter_query_part .= ' AND';
            }
        } else {
            $filter_query_part = 'false AND';
        }
    } else {
        $filter_query_part = '';
    }
    if ($since_id) {
        $since_id_part = "ttrss_entries.id > $since_id AND ";
    } else {
        $since_id_part = '';
    }
    $view_query_part = '';
    if ($view_mode == 'adaptive') {
        if ($search) {
            $view_query_part = ' ';
        } elseif ($feed != -1) {
            $unread = countUnreadFeedArticles($feed, $cat_view, true, $_SESSION['uid']);
            if ($cat_view && $feed > 0 && $include_children) {
                $unread += getCategoryChildrenUnread($feed, $_SESSION['uid']);
            }
            if ($unread > 0) {
                $view_query_part = ' unread = true AND ';
            }
        }
    }
    if ($view_mode == 'marked') {
        $view_query_part = ' marked = true AND ';
    }
    if ($view_mode == 'has_note') {
        $view_query_part = " (note IS NOT NULL AND note != '') AND ";
    }
    if ($view_mode == 'published') {
        $view_query_part = ' published = true AND ';
    }
    if ($view_mode == 'unread' && $feed != -6) {
        $view_query_part = ' unread = true AND ';
    }
    if ($limit > 0) {
        $limit_query_part = 'LIMIT ' . $limit;
    }
    $allow_archived = false;
    $vfeed_query_part = '';
    // override query strategy and enable feed display when searching globally
    if ($search && $search_mode == 'all_feeds') {
        $query_strategy_part = 'true';
        $vfeed_query_part = 'ttrss_feeds.title AS feed_title,';
        /* tags */
    } elseif (!is_numeric($feed)) {
        $query_strategy_part = 'true';
        $vfeed_query_part = '(SELECT title FROM ttrss_feeds WHERE
                    id = feed_id) as feed_title,';
    } elseif ($search && $search_mode == 'this_cat') {
        $vfeed_query_part = 'ttrss_feeds.title AS feed_title,';
        if ($feed > 0) {
            if ($include_children) {
                $subcats = \SmallSmallRSS\FeedCategories::getDescendents($feed, $owner_uid);
                $subcats[] = $feed;
                $cats_qpart = join(',', $subcats);
            } else {
                $cats_qpart = $feed;
            }
            $query_strategy_part = "ttrss_feeds.cat_id IN ($cats_qpart)";
        } else {
            $query_strategy_part = 'ttrss_feeds.cat_id IS NULL';
        }
    } elseif ($feed > 0) {
        if ($cat_view) {
            if ($feed > 0) {
                if ($include_children) {
                    $subcats = \SmallSmallRSS\FeedCategories::getDescendents($feed, $owner_uid);
                    $subcats[] = $feed;
                    $query_strategy_part = 'cat_id IN (' . implode(',', $subcats) . ')';
                } else {
                    $query_strategy_part = "cat_id = '$feed'";
                }
            } else {
                $query_strategy_part = 'cat_id IS NULL';
            }
            $vfeed_query_part = 'ttrss_feeds.title AS feed_title,';
        } else {
            $query_strategy_part = "feed_id = '$feed'";
        }
    } elseif ($feed == 0 && !$cat_view) { // archive virtual feed
        $query_strategy_part = 'feed_id IS NULL';
        $allow_archived = true;
    } elseif ($feed == 0 && $cat_view) { // uncategorized
        $query_strategy_part = 'cat_id IS NULL AND feed_id IS NOT NULL';
        $vfeed_query_part = 'ttrss_feeds.title AS feed_title,';
    } elseif ($feed == -1) { // starred virtual feed
        $query_strategy_part = 'marked = true';
        $vfeed_query_part = 'ttrss_feeds.title AS feed_title,';
        $allow_archived = true;
        if (!$override_order) {
            $override_order = 'last_marked DESC, date_entered DESC, updated DESC';
        }
    } elseif ($feed == -2) { // published virtual feed OR labels category
        if (!$cat_view) {
            $query_strategy_part = 'published = true';
            $vfeed_query_part = 'ttrss_feeds.title AS feed_title,';
            $allow_archived = true;
            if (!$override_order) {
                $override_order = 'last_published DESC, date_entered DESC, updated DESC';
            }
        } else {
            $vfeed_query_part = 'ttrss_feeds.title AS feed_title,';
            $ext_tables_part = ',ttrss_labels2,ttrss_user_labels2';
            $query_strategy_part = 'ttrss_labels2.id = ttrss_user_labels2.label_id
                                    AND ttrss_user_labels2.article_id = ref_id';
        }
    } elseif ($feed == -6) { // recently read
        $query_strategy_part = 'unread = false AND last_read IS NOT NULL';
        $vfeed_query_part = 'ttrss_feeds.title AS feed_title,';
        $allow_archived = true;
        if (!$override_order) {
            $override_order = 'last_read DESC';
        }
    } elseif ($feed == -3) { // fresh virtual feed
        $query_strategy_part = 'unread = true AND score >= 0';
        $intl = \SmallSmallRSS\DBPrefs::read('FRESH_ARTICLE_MAX_AGE', $owner_uid);
        if (\SmallSmallRSS\Config::get('DB_TYPE') == 'pgsql') {
            $query_strategy_part .= " AND date_entered > NOW() - INTERVAL '$intl hour' ";
        } else {
            $query_strategy_part .= " AND date_entered > DATE_SUB(NOW(), INTERVAL $intl HOUR) ";
        }
        $vfeed_query_part = 'ttrss_feeds.title AS feed_title,';
    } elseif ($feed == -4) { // all articles virtual feed
        $allow_archived = true;
        $query_strategy_part = 'true';
        $vfeed_query_part = 'ttrss_feeds.title AS feed_title,';
    } elseif ($feed <= \SmallSmallRSS\Constants::LABEL_BASE_INDEX) { // labels
        $label_id = \SmallSmallRSS\Labels::fromFeedId($feed);
        $query_strategy_part = "label_id = '$label_id'
                    AND ttrss_labels2.id = ttrss_user_labels2.label_id
                    AND ttrss_user_labels2.article_id = ref_id";

        $vfeed_query_part = 'ttrss_feeds.title AS feed_title,';
        $ext_tables_part = ',ttrss_labels2,ttrss_user_labels2';
        $allow_archived = true;

    } else {
        $query_strategy_part = 'true';
    }

    $order_by = 'score DESC, date_entered DESC, updated DESC';

    if ($view_mode == 'unread_first') {
        $order_by = "unread DESC, $order_by";
    }

    if ($override_order) {
        $order_by = $override_order;
    }

    $feed_title = '';

    if ($search) {
        $feed_title = T_sprintf('Search results: %s', $search);
    } else {
        if ($cat_view) {
            $feed_title = \SmallSmallRSS\FeedCategories::getTitle($feed);
        } else {
            if (is_numeric($feed) && $feed > 0) {
                $result = \SmallSmallRSS\Database::query(
                    "SELECT title,site_url,last_error,last_updated
                     FROM ttrss_feeds
                     WHERE
                         id = '$feed'
                         AND owner_uid = $owner_uid"
                );
                $feed_title = \SmallSmallRSS\Database::fetch_result($result, 0, 'title');
                $feed_site_url = \SmallSmallRSS\Database::fetch_result($result, 0, 'site_url');
                $last_error = \SmallSmallRSS\Database::fetch_result($result, 0, 'last_error');
                $last_updated = \SmallSmallRSS\Database::fetch_result($result, 0, 'last_updated');
            } else {
                $feed_title = \SmallSmallRSS\Feeds::getTitle($feed);
            }
        }
    }
    $content_query_part = 'content as content_preview, cached_content, ';
    if (is_numeric($feed)) {
        if ($feed >= 0) {
            $feed_kind = 'Feeds';
        } else {
            $feed_kind = 'Labels';
        }
        if ($limit_query_part) {
            $offset_query_part = "OFFSET $offset";
        }
        // proper override_order applied above
        if ($vfeed_query_part
            && !$ignore_vfeed_group
            && \SmallSmallRSS\DBPrefs::read('VFEED_GROUP_BY_FEED', $owner_uid)
        ) {
            if (!$override_order) {
                $order_by = "ttrss_feeds.title, $order_by";
            } else {
                $order_by = "ttrss_feeds.title, $override_order";
            }
        }

        if (!$allow_archived) {
            $from_qpart = "ttrss_entries,ttrss_user_entries,ttrss_feeds$ext_tables_part";
            $feed_check_qpart = 'ttrss_user_entries.feed_id = ttrss_feeds.id AND';

        } else {
            $from_qpart = "ttrss_entries$ext_tables_part,ttrss_user_entries
                        LEFT JOIN ttrss_feeds ON (feed_id = ttrss_feeds.id)";
            $feed_check_qpart = '';
        }

        if ($vfeed_query_part) {
            $vfeed_query_part .= 'favicon_avg_color,';
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
        $result = \SmallSmallRSS\Database::query($query);
    } else {
        // browsing by tag

        $select_qpart = 'SELECT DISTINCT ' .
            'date_entered,' .
            'guid,' .
            'note,' .
            'ttrss_entries.id as id,' .
            'title,' .
            'updated,' .
            'unread,' .
            'feed_id,' .
            'orig_feed_id,' .
            'marked,' .
            'num_comments, ' .
            'comments, ' .
            'tag_cache,' .
            'label_cache,' .
            'link,' .
            'last_read,' .
            '(SELECT hide_images FROM ttrss_feeds WHERE id = feed_id) AS hide_images,' .
            'last_marked, last_published, ' .
            $since_id_part .
            $vfeed_query_part .
            $content_query_part .
            'score ';

        $feed_kind = 'Tags';
        $all_tags = explode(',', $feed);
        if ($search_mode == 'any') {
            $tag_sql = 'tag_name in (' . implode(', ', array_map("\SmallSmallRSS\Database::quote", $all_tags)) . ')';
            $from_qpart = ' FROM ttrss_entries,ttrss_user_entries,ttrss_tags ';
            $where_qpart = ' WHERE ' .
                'ref_id = ttrss_entries.id AND ' .
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
                $sub_selects [] =  '(SELECT post_int_id from ttrss_tags WHERE tag_name = '
                    . \SmallSmallRSS\Database::quote($term)
                    . " AND owner_uid = $owner_uid) as A$i";
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
            $sub_ands[] = "A1.post_int_id = ttrss_user_entries.int_id and ttrss_user_entries.owner_uid = $owner_uid";
            $sub_ands[] = 'ttrss_user_entries.ref_id = ttrss_entries.id';
            $from_qpart = ' FROM ' . implode(', ', $sub_selects) . ', ttrss_user_entries, ttrss_entries';
            $where_qpart = ' WHERE ' . implode(' AND ', $sub_ands);
        }
        $result = \SmallSmallRSS\Database::query($select_qpart . $from_qpart . $where_qpart);
    }
    return array($result, $feed_title, $feed_site_url, $last_error, $last_updated);
}

function sanitize($str, $owner, $force_remove_images = false, $site_url = false)
{
    $res = trim($str);
    if (!$res) {
        return '';
    }
    if (strpos($res, 'href=') === false) {
        $res = rewrite_urls($res);
    }
    $charset_hack = (
        '<head>'
        . '<meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>'
        . '</head>'
    );

    $res = trim($res);
    if (!$res) {
        return '';
    }
    libxml_use_internal_errors(true);

    $doc = new \DOMDocument();
    $doc->loadHTML($charset_hack . $res);
    $xpath = new \DOMXPath($doc);

    $entries = $xpath->query('(//a[@href]|//img[@src])');
    $strip_images = (
        ($owner && \SmallSmallRSS\DBPrefs::read('STRIP_IMAGES', $owner))
        || $force_remove_images || !empty($_SESSION['bw_limit'])
    );

    foreach ($entries as $entry) {
        if ($site_url) {
            if ($entry->hasAttribute('href')) {
                $entry->setAttribute(
                    'href',
                    \SmallSmallRSS\Utils::rewriteRelativeUrl($site_url, $entry->getAttribute('href'))
                );
                $entry->setAttribute('rel', 'noreferrer');
            }
            if ($entry->nodeName == 'img') {
                if ($entry->hasAttribute('src')) {
                    \SmallSmallRSS\ImageCache::processEntry($entry, $site_url, false);
                }
                if ($strip_images) {
                    $p = $doc->createElement('p');
                    $a = $doc->createElement('a');
                    $a->setAttribute('href', $entry->getAttribute('src'));
                    $a->appendChild(new \DOMText($entry->getAttribute('src')));
                    $a->setAttribute('target', '_blank');
                    $p->appendChild($a);
                    $entry->parentNode->replaceChild($p, $entry);
                }
            }
        }
        if (strtolower($entry->nodeName) == 'a') {
            $entry->setAttribute('target', '_blank');
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
    $xpath = new \DOMXPath($doc);
    $entries = $xpath->query('//*');
    foreach ($entries as $entry) {
        if (!in_array($entry->nodeName, $allowed_elements)) {
            $entry->parentNode->removeChild($entry);
        }
        if ($entry->hasAttributes()) {
            $attrs_to_remove = array();
            foreach ($entry->attributes as $attr) {
                if (strpos($attr->nodeName, 'on') === 0) {
                    $attrs_to_remove[] = $attr;
                }
                if (in_array($attr->nodeName, $disallowed_attributes)) {
                    $attrs_to_remove[] = $attr;
                }
            }
            foreach ($attrs_to_remove as $attr) {
                $entry->removeAttributeNode($attr);
            }
        }
    }
    return $doc;
}

function catchupArticlesById($ids, $mark_mode, $owner_uid)
{
    if (!$ids) {
        \SmallSmallRSS\Logger::log('No ref ids');
        return;
    }
    if ($mark_mode == \SmallSmallRSS\MarkModes::MARK) {
        \SmallSmallRSS\UserEntries::markIdsRead($ids, $owner_uid);
    } elseif ($mark_mode == \SmallSmallRSS\MarkModes::UNMARK) {
        \SmallSmallRSS\UserEntries::markIdsUnread($ids, $owner_uid);
    } elseif ($mark_mode == \SmallSmallRSS\MarkModes::TOGGLE) {
        \SmallSmallRSS\UserEntries::toggleIdsUnread($ids, $owner_uid);
    }
    $feed_ids = \SmallSmallRSS\UserEntries::getMatchingFeeds($ids, $owner_uid);
    foreach ($feed_ids as $feed_id) {
        \SmallSmallRSS\CountersCache::update($feed_id, $owner_uid);
    }
}

function get_article_tags($id, $owner_uid, $tag_cache = false)
{
    $a_id = \SmallSmallRSS\Database::escape_string($id);
    $tags = array();
    if ($tag_cache === false) {
        $tag_cache = \SmallSmallRSS\UserEntries::getCachedTags($id, $owner_uid);
    }
    if ($tag_cache) {
        $tags = explode(',', $tag_cache);
    } else {
        /* do it the hard way */
        $tags = \SmallSmallRSS\Tags::getForRefId($a_id, $owner_uid);
        /* update the cache */
        $tags_str = join(',', $tags);
        \SmallSmallRSS\UserEntries::setCachedTags($id, $owner_uid, $tags_str);
    }
    return $tags;
}

function T_sprintf()
{
    $args = func_get_args();
    return vsprintf(__(array_shift($args)), $args);
}

function format_inline_player($url, $ctype)
{
    ob_start();
    $url = htmlspecialchars($url);
    if (strpos($ctype, 'audio/') === 0) {
        $player = false;
        if ($_SESSION['hasAudio']
            && (strpos($ctype, 'ogg') !== false || $_SESSION['hasMp3'])
        ) {
            echo "<audio preload=\"none\" controls><source type=\"$ctype\" src=\"$url\"></source></audio>";
        } else {
            echo "<object type=\"application/x-shockwave-flash\"
                        data=\"lib/button/musicplayer.swf?song_url=$url\"
                        width=\"17\" height=\"17\" style='float: left; margin-right : 5px;'>
                         <param name=\"movie\" value=\"lib/button/musicplayer.swf?song_url=$url\" />
                       </object>";
        }
        if ($player) {
            echo "&nbsp; <a target=\"_blank\" href=\"$url\">" . basename($url) . '</a>';
        }
    }
    return ob_get_clean();
}

function get_self_url_prefix()
{
    $self_url_path = \SmallSmallRSS\Config::get('SELF_URL_PATH');
    if (strrpos($self_url_path, '/') === strlen($self_url_path) - 1) {
        return substr($self_url_path, 0, strlen($self_url_path) - 1);
    } else {
        return $self_url_path;
    }
}

function load_filters($feed_id, $owner_uid, $action_id = false)
{
    $filters = array();
    $cat_id = \SmallSmallRSS\Feeds::getCategory($feed_id);
    $check_cats = join(
        ',',
        array_merge(\SmallSmallRSS\FeedCategories::getAncestors($cat_id, $owner_uid), array($cat_id))
    );
    $filter_lines = \SmallSmallRSS\Filters::getEnabled($owner_uid);
    foreach ($filter_lines as $filter_line) {
        $filter_id = $filter_line['id'];
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

        while (($rule_line = \SmallSmallRSS\Database::fetch_assoc($result2))) {
            $rule = array();
            $rule['reg_exp'] = $rule_line['reg_exp'];
            $rule['type'] = $rule_line['type_name'];
            $rule['inverse'] = \SmallSmallRSS\Database::fromSQLBool($rule_line['inverse']);
            $rules[] = $rule;
        }

        $result2 = \SmallSmallRSS\Database::query(
            "SELECT a.action_param,t.name AS type_name
             FROM
                 ttrss_filters2_actions AS a,
                 ttrss_filter_actions AS t
             WHERE
                 action_id = t.id AND filter_id = '$filter_id'"
        );

        while (($action_line = \SmallSmallRSS\Database::fetch_assoc($result2))) {
            $action = array();
            $action['type'] = $action_line['type_name'];
            $action['param'] = $action_line['action_param'];
            $actions[] = $action;
        }
        $filter = array();
        $filter['match_any_rule'] = \SmallSmallRSS\Database::fromSQLBool($filter_line['match_any_rule']);
        $filter['inverse'] = \SmallSmallRSS\Database::fromSQLBool($filter_line['inverse']);
        $filter['rules'] = $rules;
        $filter['actions'] = $actions;
        if (count($rules) > 0 && count($actions) > 0) {
            $filters[] = $filter;
        }
    }
    return $filters;
}

function get_score_pic($score)
{
    if ($score > 100) {
        return 'score_high.png';
    } elseif ($score > 0) {
        return 'score_half_high.png';
    } elseif ($score < -100) {
        return 'score_low.png';
    } elseif ($score < 0) {
        return 'score_half_low.png';
    } else {
        return 'score_neutral.png';
    }
}

function format_tags_string($tags, $id)
{
    $tags_str = '';
    if (!is_array($tags) || count($tags) == 0) {
        return __('no tags');
    } else {
        $maxtags = min(5, count($tags));

        for ($i = 0; $i < $maxtags; $i++) {
            $tags_str .= '<a class="tag" href="#" onclick="viewfeed(\'' . $tags[$i] . '\')">' . $tags[$i] . '</a>, ';
        }
        $tags_str = mb_substr($tags_str, 0, mb_strlen($tags_str)-2);
        if (count($tags) > $maxtags) {
            $tags_str .= ', &hellip;';
        }
        return $tags_str;
    }
}

function format_article_labels($labels, $id)
{
    if (!is_array($labels)) {
        return '';
    }
    ob_start();
    foreach ($labels as $l) {
        printf(
            '<span class="hlLabelRef" style="color: %s; background-color: %s">%s</span>',
            $l[2],
            $l[3],
            $l[1]
        );
    }
    return ob_get_clean();

}

function format_article_note($id, $note, $allow_edit = true)
{
    ob_start();
    echo "<div class=\"articleNote\" onclick=\"editArticleNote($id)\">";
    if ($allow_edit) {
        echo "<div class=\"noteEdit\" onclick=\"editArticleNote($id)\">";
        echo __('(edit note)');
        echo "</div>";
    }
    echo $note;
    echo "</div>";
    return ob_get_clean();
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

    if ($url != 'http:///') {
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

function save_email_address($email)
{
    // FIXME: implement persistent storage of emails
    if (!$_SESSION['stored_emails']) {
        $_SESSION['stored_emails'] = array();
    }
    if (!in_array($email, $_SESSION['stored_emails'])) {
        $_SESSION['stored_emails'][] = $email;
    }
}

function get_feeds_from_html($url, $content)
{
    $url = fix_url($url);
    $baseUrl = substr($url, 0, strrpos($url, '/') + 1);

    libxml_use_internal_errors(true);

    $doc = new \DOMDocument();
    $doc->loadHTML($content);
    $xpath = new \DOMXPath($doc);
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


function print_label_select($name, $value, $attributes = '')
{
    $labels = \SmallSmallRSS\Labels::getAll($_SESSION['uid']);
    print "<select default=\"$value\" name=\"" . htmlspecialchars($name) .
        "\" $attributes onchange=\"labelSelectOnChange(this)\" >";
    foreach ($labels as $line) {
        $issel = ($line['caption'] == $value) ? ' selected="selected"' : '';
        echo '<option value="' . htmlspecialchars($line['caption']) . '"';
        echo $issel;
        echo htmlspecialchars($line['caption']);
        echo '</option>';
    }
    print '</select>';
}

function format_article_enclosures(
    $id,
    $always_display_enclosures,
    $article_content,
    $hide_images = false
) {
    $result = \SmallSmallRSS\Enclosures::get($id);
    ob_start();
    if (count($result) > 0) {
        $entries_html = array();
        $entries = array();
        $entries_inline = array();
        foreach ($result as $line) {
            $url = $line['content_url'];
            $ctype = $line['content_type'];
            if (!$ctype) {
                $ctype = __('unknown type');
            }
            $filename = substr($url, strrpos($url, '/') + 1);
            $player = format_inline_player($url, $ctype);
            if ($player) {
                $entries_inline[] = $player;
            }
            $entry_html = "<div onclick=\"window.open('".htmlspecialchars($url)."')\"";
            $entry_html .= " data-dojo-type=\"dijit.MenuItem\">$filename ($ctype)</div>";
            $entries_html[] = $entry_html;

            $entry = array();
            $entry['type'] = $ctype;
            $entry['filename'] = $filename;
            $entry['url'] = $url;
            $entries[] = $entry;
        }
        $image_entries = array();
        if (!\SmallSmallRSS\DBPrefs::read('STRIP_IMAGES') && empty($_SESSION['bw_limit'])) {
            if ($always_display_enclosures
                || !preg_match('/<img/i', $article_content)) {
                foreach ($entries as $entry) {
                    if (preg_match('/image/', $entry['type'])
                        || preg_match("/\.(jpg|png|gif|bmp)/i", $entry['filename'])) {
                        $image_entries[] = $entry;
                    }
                }
            }
        }
        if ($image_entries) {
            echo '<ul class="image_enclosures">';
            foreach ($image_entries as $entry) {
                echo '<li>';
                if (!$hide_images) {
                    echo '<img alt="' . htmlspecialchars($entry['filename']) . '" src="';
                    echo htmlspecialchars($entry['url']);
                    echo '"/>';
                } else {
                    echo '<a target="_blank" href="';
                    echo htmlspecialchars($entry['url']);
                    echo '">';
                    echo htmlspecialchars($entry['filename']);
                    echo '</a>';
                }
                echo '</li>';
            }
            echo '</ul>';
        }
        if (count($entries_inline) > 0) {
            echo '<hr clear="both" />';
            foreach ($entries_inline as $entry) {
                echo $entry;
            };
            echo '<hr clear="both" />';
        }

        echo '<select class="attachments" onchange="openSelectedAttachment(this)">';
        echo '<option value="">';
        echo __('Attachments');
        echo '</option>';

        foreach ($entries as $entry) {
            echo '<option value="';
            echo htmlspecialchars($entry['url']);
            echo '">';
            echo htmlspecialchars($entry['filename']);
            echo '</option>';

        };
        echo '</select>';
    }
    return ob_get_clean();
}

function sphinx_search($query, $offset = 0, $limit = 30)
{
    $sphinxClient = new \Sphinx\SphinxClient();
    $sphinxpair = explode(':', \SmallSmallRSS\Config::get('SPHINX_SERVER'), 2);
    $sphinxClient->setServer($sphinxpair[0], (int) $sphinxpair[1]);
    $sphinxClient->setConnectTimeout(1);
    $sphinxClient->setFieldWeights(
        array(
            'title' => 70,
            'content' => 30,
            'feed_title' => 20
        )
    );
    $sphinxClient->setMatchMode(SPH_MATCH_EXTENDED2);
    $sphinxClient->setRankingMode(SPH_RANK_PROXIMITY_BM25);
    $sphinxClient->setLimits($offset, $limit, 1000);
    $sphinxClient->setArrayResult(false);
    $sphinxClient->setFilter('owner_uid', array($_SESSION['uid']));

    $result = $sphinxClient->query($query, \SmallSmallRSS\Config::get('SPHINX_INDEX'));
    $ids = array();
    if (is_array($result['matches'])) {
        foreach (array_keys($result['matches']) as $int_id) {
            $ref_id = $result['matches'][$int_id]['attrs']['ref_id'];
            $ids[] = $ref_id;
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
    $doc = new \DOMDocument();
    $doc->loadHTML($charset_hack . $html);
    $xpath = new \DOMXPath($doc);
    $entries = $xpath->query('//*/text()');
    foreach ($entries as $entry) {
        if (strstr($entry->wholeText, '://') !== false) {
            $text = preg_replace(
                "/((?<!=.)((http|https|ftp)+):\/\/[^ ,!]+)/i",
                "<a target=\"_blank\" href=\"\\1\">\\1</a>",
                $entry->wholeText
            );
            if ($text != $entry->wholeText) {
                $cdoc = new \DOMDocument();
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
    if (\SmallSmallRSS\Config::get('DB_TYPE') == 'pgsql') {
        $reg_qpart = '~';
    } else {
        $reg_qpart = 'REGEXP';
    }
    foreach ($filter['rules'] as $rule) {
        $regexp_valid = preg_match('/' . $rule['reg_exp'] . '/', $rule['reg_exp']) !== false;
        if ($regexp_valid) {
            $rule['reg_exp'] = \SmallSmallRSS\Database::escape_string($rule['reg_exp']);
            switch ($rule['type']) {
                case 'title':
                    $qpart = "LOWER(ttrss_entries.title) $reg_qpart LOWER('".
                        $rule['reg_exp'] . "')";
                    break;
                case 'content':
                    $qpart = "LOWER(ttrss_entries.content) $reg_qpart LOWER('".
                        $rule['reg_exp'] . "')";
                    break;
                case 'both':
                    $qpart = "LOWER(ttrss_entries.title) $reg_qpart LOWER('".
                        $rule['reg_exp'] . "') OR LOWER(" .
                        "ttrss_entries.content) $reg_qpart LOWER('" . $rule['reg_exp'] . "')";
                    break;
                case 'tag':
                    $qpart = "LOWER(ttrss_user_entries.tag_cache) $reg_qpart LOWER('".
                        $rule['reg_exp'] . "')";
                    break;
                case 'link':
                    $qpart = "LOWER(ttrss_entries.link) $reg_qpart LOWER('".
                        $rule['reg_exp'] . "')";
                    break;
                case 'author':
                    $qpart = "LOWER(ttrss_entries.author) $reg_qpart LOWER('".
                        $rule['reg_exp'] . "')";
                    break;
            }
            if (!empty($rule['inverse'])) {
                $qpart = "NOT ($qpart)";
            }
            if (isset($rule['feed_id']) && $rule['feed_id'] > 0) {
                $qpart .= ' AND feed_id = ' . \SmallSmallRSS\Database::escape_string($rule['feed_id']);
            }
            if (isset($rule['cat_id'])) {
                if ($rule['cat_id'] > 0) {
                    $children = \SmallSmallRSS\FeedCategories::getDescendents($rule['cat_id'], $owner_uid);
                    $children[] = $rule['cat_id'];
                    $children = join(',', $children);
                    $cat_qpart = "cat_id IN ($children)";
                } else {
                    $cat_qpart = 'cat_id IS NULL';
                }
                $qpart .= " AND $cat_qpart";
            }
            $query[] = "($qpart)";
        }
    }
    if (count($query) > 0) {
        $fullquery = '(' . join($filter['match_any_rule'] ? 'OR' : 'AND', $query) . ')';
    } else {
        $fullquery = '(false)';
    }
    if ($filter['inverse']) {
        $fullquery = "(NOT $fullquery)";
    }
    return $fullquery;
}

function calculate_dep_timestamp()
{
    $base = __DIR__ . '/../../web';
    $files = array_merge(glob($base . '/js/*.js'), glob($base . '/css/*.css'));
    $max_ts = -1;
    foreach ($files as $file) {
        if (filemtime($file) > $max_ts) {
            $max_ts = filemtime($file);
        }
    }
    return $max_ts;
}
