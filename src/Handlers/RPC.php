<?php
namespace SmallSmallRSS\Handlers;

class RPC extends ProtectedHandler
{
    public function ignoreCSRF($method)
    {
        $csrf_ignored = array('sanitycheck', 'completelabels', 'getallcounters');
        return array_search($method, $csrf_ignored) !== false;
    }

    public function setprofile()
    {
        $id = \SmallSmallRSS\Database::escapeString($_REQUEST['id']);
        $_SESSION['profile'] = $id;
    }

    public function remprofiles()
    {
        \SmallSmallRSS\SettingsProfiles::deleteIds(explode(',', $_REQUEST['ids']));
    }

    public function addprofile()
    {
        $title = trim($_REQUEST['title']);
        if ($title) {
            \SmallSmallRSS\Database::query('BEGIN');
            $id = \SmallSmallRSS\SettingsProfiles::findByTitle($title, $_SESSION['uid']);
            if ($id !== false) {
                $id = \SmallSmallRSS\SettingsProfiles::create($title, $_SESSION['uid']);
                if ($id !== false) {
                    \SmallSmallRSS\UserPrefs::initialize($_SESSION['uid'], $id);
                }
            }
            \SmallSmallRSS\Database::query('COMMIT');
        }
    }

    public function saveprofile()
    {
        $id = \SmallSmallRSS\Database::escapeString($_REQUEST['id']);
        $title = \SmallSmallRSS\Database::escapeString(trim($_REQUEST['value']));
        if ($id == 0) {
            echo __('Default profile');
            return;
        }
        if ($title) {
            \SmallSmallRSS\Database::query('BEGIN');
            $existing_title_id = \SmallSmallRSS\SettingsProfiles::findByTitle($title, $_SESSION['uid']);
            if ($existing_title_id === false) {
                \SmallSmallRSS\Database::query(
                    "UPDATE ttrss_settings_profiles
                     SET title = '$title'
                     WHERE
                         id = '$id' AND
                         owner_uid = " . $_SESSION['uid']
                );
            }
            echo $title;
            \SmallSmallRSS\Database::query('COMMIT');
        }
    }

    public function remarchive()
    {
        $ids = explode(',', \SmallSmallRSS\Database::escapeString($_REQUEST['ids']));

        foreach ($ids as $id) {
            $result = \SmallSmallRSS\Database::query(
                "DELETE FROM ttrss_archived_feeds
                 WHERE
                     id = '$id'
                     AND owner_uid = " . $_SESSION['uid'] . "
                     AND (
                         SELECT COUNT(*)
                         FROM ttrss_user_entries
                         WHERE
                             orig_feed_id = '$id'
                 ) = 0"
            );
            $rc = \SmallSmallRSS\Database::affectedRows($result);
        }
    }

    public function addfeed()
    {
        $feed = \SmallSmallRSS\Database::escapeString($_REQUEST['feed']);
        $cat = \SmallSmallRSS\Database::escapeString($_REQUEST['cat']);
        $login = \SmallSmallRSS\Database::escapeString($_REQUEST['login']);
        $pass = trim($_REQUEST['pass']); // escaped later
        $rc = subscribe_to_feed($feed, $cat, $login, $pass);
        echo json_encode(array('result' => $rc));
    }

    public function togglepref()
    {
        $key = \SmallSmallRSS\Database::escapeString($_REQUEST['key']);
        \SmallSmallRSS\DBPrefs::write($key, !\SmallSmallRSS\DBPrefs::read($key));
        $value = \SmallSmallRSS\DBPrefs::read($key);
        echo json_encode(array('param' => $key, 'value' => $value));
    }

    public function setpref()
    {
        $key = $_REQUEST['key'];
        $value = str_replace("\n", '<br/>', $_REQUEST['value']);
        \SmallSmallRSS\DBPrefs::write($key, $value, $_SESSION['uid'], $key != 'USER_STYLESHEET');
        echo json_encode(array('param' => $key, 'value' => $value));
    }

    public function mark()
    {
        $mark = $_REQUEST['mark'];
        $id = \SmallSmallRSS\Database::escapeString($_REQUEST['id']);
        if ($mark == '1') {
            $mark = 'true';
        } else {
            $mark = 'false';
        }
        $result = \SmallSmallRSS\Database::query(
            "UPDATE ttrss_user_entries
             SET marked = $mark,
                 last_marked = NOW()
             WHERE
                 ref_id = '$id'
                 AND owner_uid = " . $_SESSION['uid']
        );
        echo json_encode(array('message' => 'UPDATE_COUNTERS'));
    }

    public function delete()
    {
        $ids = \SmallSmallRSS\Database::escapeString($_REQUEST['ids']);
        $result = \SmallSmallRSS\Database::query(
            "DELETE FROM ttrss_user_entries
             WHERE
                 ref_id IN ($ids)
                 AND owner_uid = " . $_SESSION['uid']
        );
        \SmallSmallRSS\Entries::purgeOrphans();
        echo json_encode(array('message' => 'UPDATE_COUNTERS'));
    }

    public function unarchive()
    {
        $ids = explode(',', $_REQUEST['ids']);
        foreach ($ids as $id) {
            $id = \SmallSmallRSS\Database::escapeString(trim($id));
            \SmallSmallRSS\Database::query('BEGIN');
            $result = \SmallSmallRSS\Database::query(
                "SELECT feed_url, site_url,title
                 FROM ttrss_archived_feeds
                 WHERE id = (
                     SELECT orig_feed_id
                     FROM ttrss_user_entries
                     WHERE ref_id = $id
                     AND owner_uid = " . $_SESSION['uid'] . ')'
            );
            if (\SmallSmallRSS\Database::numRows($result) != 0) {
                $feed_url = \SmallSmallRSS\Database::escapeString(
                    \SmallSmallRSS\Database::fetchResult($result, 0, 'feed_url')
                );
                $site_url = \SmallSmallRSS\Database::escapeString(
                    \SmallSmallRSS\Database::fetchResult($result, 0, 'site_url')
                );
                $title = \SmallSmallRSS\Database::escapeString(
                    \SmallSmallRSS\Database::fetchResult($result, 0, 'title')
                );
                $feed_id = \SmallSmallRSS\UserEntries::getByUrl($feed_url, $_SESSION['uid']);
                if (!$feed_id) {
                    if (!$title) {
                        $title = '[Unknown]';
                    }
                    $result = \SmallSmallRSS\Database::query(
                        'INSERT INTO ttrss_feeds (
                             owner_uid,feed_url,site_url,title,cat_id,auth_login,auth_pass,update_method
                         )
                         VALUES (
                             '.$_SESSION['uid'].",
                             '$feed_url',
                             '$site_url',
                             '$title',
                             NULL,
                             '',
                             '',
                             0
                         )"
                    );
                    $feed_id = \SmallSmallRSS\UserEntries::getByUrl($feed_url, $_SESSION['uid']);
                }
                if ($feed_id) {
                    $result = \SmallSmallRSS\Database::query(
                        "UPDATE ttrss_user_entries
                         SET feed_id = '$feed_id', orig_feed_id = NULL
                         WHERE ref_id = $id AND owner_uid = " . $_SESSION['uid']
                    );
                }
            }
            \SmallSmallRSS\Database::query('COMMIT');
        }
        echo json_encode(array('message' => 'UPDATE_COUNTERS'));
    }

    public function archive()
    {
        $ids = explode(',', \SmallSmallRSS\Database::escapeString($_REQUEST['ids']));

        foreach ($ids as $id) {
            $this->archiveArticle($id, $_SESSION['uid']);
        }

        echo json_encode(array('message' => 'UPDATE_COUNTERS'));
    }

    private function archiveArticle($id, $owner_uid)
    {
        \SmallSmallRSS\Database::query('BEGIN');
        $result = \SmallSmallRSS\Database::query(
            "SELECT feed_id
             FROM ttrss_user_entries
             WHERE
                 ref_id = '$id'
                 AND owner_uid = $owner_uid"
        );
        if (\SmallSmallRSS\Database::numRows($result) != 0) {
            /* prepare the archived table */
            $feed_id = (int) \SmallSmallRSS\Database::fetchResult($result, 0, 'feed_id');
            if ($feed_id) {
                $result = \SmallSmallRSS\Database::query(
                    "SELECT id
                     FROM ttrss_archived_feeds
                     WHERE id = '$feed_id'"
                );
                if (\SmallSmallRSS\Database::numRows($result) == 0) {
                    \SmallSmallRSS\Database::query(
                        "INSERT INTO ttrss_archived_feeds
                         (id, owner_uid, title, feed_url, site_url)
                         SELECT id, owner_uid, title, feed_url, site_url
                         FROM ttrss_feeds
                         WHERE id = '$feed_id'"
                    );
                }
                \SmallSmallRSS\Database::query(
                    "UPDATE ttrss_user_entries
                     SET orig_feed_id = feed_id, feed_id = NULL
                     WHERE ref_id = '$id' AND owner_uid = " . $_SESSION['uid']
                );
            }
        }
        \SmallSmallRSS\Database::query('COMMIT');
    }

    public function publ()
    {
        $pub = $this->getBooleanFromRequest('pub');
        $id = \SmallSmallRSS\Database::escapeString($_REQUEST['id']);
        if ($pub) {
            \SmallSmallRSS\UserEntries::publishIds(array($id), $_SESSION['uid']);
        } else {
            \SmallSmallRSS\UserEntries::unpublishIds(array($id), $_SESSION['uid']);
        }
        $pubsub_result = false;
        $hub = \SmallSmallRSS\Config::get('PUBSUBHUBBUB_HUB');
        if ($hub) {
            $rss_link = get_self_url_prefix() .
                '/public.php?op=rss&id=-2&key=' .
                \SmallSmallRSS\AccessKeys::getForFeed(-2, false, $_SESSION['uid']);
            $p = new \Pubsubhubbub\Publisher($hub);
            $pubsub_result = $p->publishUpdate($rss_link);
        }

        echo json_encode(
            array('message' => 'UPDATE_COUNTERS',
                  'pubsub_result' => $pubsub_result
            )
        );
    }

    public function getAllCounters()
    {
        $all_start = microtime(true);
        $last_article_id = 0;
        if (!empty($_REQUEST['last_article_id'])) {
            $last_article_id = (int) $_REQUEST['last_article_id'];
        }
        $reply = array('times' => array());
        if (!empty($_REQUEST['seq'])) {
            $reply['seq'] = (int) $_REQUEST['seq'];
        }
        $start = microtime(true);
        if ($last_article_id != \SmallSmallRSS\UserEntries::getLastId($_SESSION['uid'])) {
            $reply['counters'] = getAllCounters($_SESSION['uid']);
            $reply['times']['counters'] = (microtime(true) - $start) * 1000;
        }
        $start = microtime(true);
        $reply['runtime-info'] = make_runtime_info();
        $reply['times']['runtime-info'] = (microtime(true) - $start) * 1000;
        $reply['times']['all'] = (microtime(true) - $all_start) * 1000;
        echo json_encode($reply);
    }

    /* GET["mark_mode"] = 0 - mark as read, 1 - unmark as read, 2 - toggle */
    public function catchupSelected()
    {
        $ids = explode(',', $_REQUEST['ids']);
        $mark_mode = sprintf('%d', $_REQUEST['mark_mode']);
        catchupArticlesById($ids, $mark_mode, $_SESSION['uid']);
        echo json_encode(array('message' => 'UPDATE_COUNTERS', 'ids' => $ids));
    }

    public function markSelected()
    {
        $ids = explode(',', $_REQUEST['ids']);
        $mark_mode = sprintf('%d', $_REQUEST['mark_mode']);
        $this->markArticlesById($ids, $mark_mode);
        echo json_encode(array('message' => 'UPDATE_COUNTERS'));
    }

    public function publishSelected()
    {
        $ids = explode(',', $_REQUEST['ids']);
        $mark_mode = sprintf('%d', $_REQUEST['mark_mode']);
        $this->publishArticlesById($ids, $mark_mode);
        echo json_encode(array('message' => 'UPDATE_COUNTERS'));
    }

    private static function checkDatabase()
    {
        $error_code = \SmallSmallRSS\Errors::UNSPECIFIED();
        $schema_version = \SmallSmallRSS\Sanity::getSchemaVersion(true);
        if (!\SmallSmallRSS\Sanity::isSchemaCorrect()) {
            $error_code = \SmallSmallRSS\Errors::INCORRECT_DB_SCHEMA();
        }
        if (\SmallSmallRSS\Config::get('DB_TYPE') == 'mysql') {
            $result = \SmallSmallRSS\Database::query('SELECT true', false);
            if (\SmallSmallRSS\Database::numRows($result) != 1) {
                $error_code = \SmallSmallRSS\Errors::OLD_MYSQL();
            }
        }
        if (\SmallSmallRSS\Database::escapeString('testTEST') != 'testTEST') {
            $error_code = \SmallSmallRSS\Errors::SQL_ESCAPE_FAILED();
        }
        return array('code' => $error_code->getOrdinal(), 'message' => $error_code->description());
    }

    private function makeInitParams()
    {
        $prefs = array(
            'ON_CATCHUP_SHOW_NEXT_FEED',
            'HIDE_READ_FEEDS',
            'ENABLE_FEED_CATS',
            'FEEDS_SORT_BY_UNREAD',
            'CONFIRM_FEED_CATCHUP',
            'CDM_AUTO_CATCHUP',
            'FRESH_ARTICLE_MAX_AGE',
            'HIDE_READ_SHOWS_SPECIAL',
            'COMBINED_DISPLAY_MODE'
        );
        $params = array();
        foreach ($prefs as $param) {
            $params[strtolower($param)] = (int) \SmallSmallRSS\DBPrefs::read($param);
        }
        $params['icons_url'] = \SmallSmallRSS\Config::get('ICONS_URL');
        $params['cookie_lifetime'] = \SmallSmallRSS\Config::get('SESSION_COOKIE_LIFETIME');
        $params['default_view_mode'] = \SmallSmallRSS\DBPrefs::read('_DEFAULT_VIEW_MODE');
        $params['default_view_limit'] = (int) \SmallSmallRSS\DBPrefs::read('_DEFAULT_VIEW_LIMIT');
        $params['default_view_order_by'] = \SmallSmallRSS\DBPrefs::read('_DEFAULT_VIEW_ORDER_BY');
        $params['bw_limit'] = (int) $_SESSION['bw_limit'];
        $params['label_base_index'] = (int) \SmallSmallRSS\Constants::LABEL_BASE_INDEX;

        $result = \SmallSmallRSS\Database::query(
            'SELECT
                 MAX(id) AS mid,
                 COUNT(*) AS nf
             FROM ttrss_feeds
             WHERE owner_uid = ' . $_SESSION['uid']
        );
        $max_feed_id = \SmallSmallRSS\Database::fetchResult($result, 0, 'mid');
        $num_feeds = \SmallSmallRSS\Database::fetchResult($result, 0, 'nf');
        $params['max_feed_id'] = (int) $max_feed_id;
        $params['num_feeds'] = (int) $num_feeds;
        $params['hotkeys'] = \SmallSmallRSS\Hotkeys::map();
        $params['csrf_token'] = $_SESSION['csrf_token'];
        if (!empty($_COOKIE['ttrss_widescreen'])) {
            $params['widescreen'] = (int) $_COOKIE['ttrss_widescreen'];
        } else {
            $params['widescreen'] = 0;
        }
        $params['simple_update'] = (bool) \SmallSmallRSS\Config::get('SIMPLE_UPDATE_MODE');
        return $params;
    }

    public function sanityCheck()
    {
        $_SESSION['hasAudio'] = self::getBooleanFromRequest('hasAudio');
        $_SESSION['hasSandbox'] = self::getBooleanFromRequest('hasSandbox');
        $_SESSION['hasMp3'] = self::getBooleanFromRequest('hasMp3');
        $_SESSION['clientTzOffset'] = (
            isset($_REQUEST['clientTzOffset'])
            ? $_REQUEST['clientTzOffset'] : null
        );
        $reply = array();
        $reply['error'] = self::checkDatabase();
        if ($reply['error']['code'] == 0) {
            $reply['init-params'] = self::makeInitParams();
            $reply['runtime-info'] = make_runtime_info();
        }
        echo json_encode($reply);
    }

    public function completeLabels()
    {
        $search = \SmallSmallRSS\Database::escapeString($_REQUEST['search']);
        $result = \SmallSmallRSS\Database::query(
            "SELECT DISTINCT caption
             FROM
                 ttrss_labels2
             WHERE
                 owner_uid = '".$_SESSION['uid']."'
                 AND LOWER(caption) LIKE LOWER('$search%')
             ORDER BY caption
             LIMIT 5"
        );
        echo '<ul>';
        while ($line = \SmallSmallRSS\Database::fetchAssoc($result)) {
            echo '<li>' . $line['caption'] . '</li>';
        }
        echo '</ul>';
    }

    public function purge()
    {
        $ids = explode(',', \SmallSmallRSS\Database::escapeString($_REQUEST['ids']));
        $purge_interval = sprintf('%d', $_REQUEST['days']);
        foreach ($ids as $id) {
            $result = \SmallSmallRSS\Database::query(
                "SELECT id
                 FROM ttrss_feeds
                 WHERE
                     id = '$id'
                     AND owner_uid = " . $_SESSION['uid']
            );
            if (\SmallSmallRSS\Database::numRows($result) == 1) {
                \SmallSmallRSS\Feeds::purge($feed_id, $purge_interval);
            }
        }
    }

    public function updateFeedBrowser()
    {
        $search = \SmallSmallRSS\Database::escapeString($_REQUEST['search']);
        $limit = \SmallSmallRSS\Database::escapeString($_REQUEST['limit']);
        $mode = (int) \SmallSmallRSS\Database::escapeString($_REQUEST['mode']);
        ob_start();
        $feedbrowser = \SmallSmallRSS\Renderers\FeedBrower($search, $limit, $mode);
        $feedbrowser->render();
        $buffer = ob_get_clean();
        echo json_encode(array('content' => $buffer, 'mode' => $mode));
    }

    // Silent
    public function massSubscribe()
    {
        $payload = json_decode($_REQUEST['payload'], false);
        $mode = $_REQUEST['mode'];
        if (!$payload || !is_array($payload)) {
            return;
        }
        if ($mode == 1) {
            foreach ($payload as $feed) {
                $title = \SmallSmallRSS\Database::escapeString($feed[0]);
                $feed_url = \SmallSmallRSS\Database::escapeString($feed[1]);
                $result = \SmallSmallRSS\Database::query(
                    "SELECT id
                     FROM ttrss_feeds
                     WHERE
                         feed_url = '$feed_url'
                         AND owner_uid = " . $_SESSION['uid']
                );

                if (\SmallSmallRSS\Database::numRows($result) == 0) {
                    $result = \SmallSmallRSS\Database::query(
                        "INSERT INTO ttrss_feeds
                         (owner_uid,feed_url,title,cat_id,site_url)
                         VALUES ('".$_SESSION['uid']."',
                             '$feed_url', '$title', NULL, '')"
                    );
                }
            }
        } elseif ($mode == 2) {
            // feed archive
            foreach ($payload as $id) {
                $result = \SmallSmallRSS\Database::query(
                    "SELECT *
                     FROM ttrss_archived_feeds
                     WHERE
                         id = '$id'
                         AND owner_uid = " . $_SESSION['uid']
                );

                if (\SmallSmallRSS\Database::numRows($result) != 0) {
                    $site_url = \SmallSmallRSS\Database::escapeString(
                        \SmallSmallRSS\Database::fetchResult($result, 0, 'site_url')
                    );
                    $feed_url = \SmallSmallRSS\Database::escapeString(
                        \SmallSmallRSS\Database::fetchResult($result, 0, 'feed_url')
                    );
                    $title = \SmallSmallRSS\Database::escapeString(
                        \SmallSmallRSS\Database::fetchResult($result, 0, 'title')
                    );
                    $result = \SmallSmallRSS\Database::query(
                        "SELECT id
                         FROM ttrss_feeds
                         WHERE
                             feed_url = '$feed_url'
                             AND owner_uid = " . $_SESSION['uid']
                    );
                    if (\SmallSmallRSS\Database::numRows($result) == 0) {
                        $result = \SmallSmallRSS\Database::query(
                            "INSERT INTO ttrss_feeds
                             (owner_uid,feed_url,title,cat_id,site_url)
                             VALUES ('$id','".$_SESSION['uid']."',
                                 '$feed_url', '$title', NULL, '$site_url')"
                        );
                    }
                }
            }
        }
    }

    public function catchupFeed()
    {
        $feed_id = \SmallSmallRSS\Database::escapeString($_REQUEST['feed_id']);
        $is_cat = \SmallSmallRSS\Database::escapeString($_REQUEST['is_cat']) == 'true';
        $mode = \SmallSmallRSS\Database::escapeString($_REQUEST['mode']);
        \SmallSmallRSS\UserEntries::catchupFeed($feed_id, $is_cat, $_SESSION['uid'], false, $mode);
        echo json_encode(array('message' => 'UPDATE_COUNTERS'));
    }

    public function quickAddCat()
    {
        $cat = \SmallSmallRSS\Database::escapeString($_REQUEST['cat']);
        \SmallSmallRSS\FeedCategories::add($_SESSION['uid'], $cat);
        $result = \SmallSmallRSS\Database::query(
            "SELECT id
             FROM ttrss_feed_categories
             WHERE
                 title = '$cat'
                 AND owner_uid = " . $_SESSION['uid']
        );
        if (\SmallSmallRSS\Database::numRows($result) == 1) {
            $id = \SmallSmallRSS\Database::fetchResult($result, 0, 'id');
        } else {
            $id = 0;
        }
        print_feed_cat_select('cat_id', $id, '');
    }

    // Silent
    public function clearArticleKeys()
    {
        \SmallSmallRSS\Database::query(
            "UPDATE ttrss_user_entries SET uuid = '' WHERE
            owner_uid = " . $_SESSION['uid']
        );
        return;
    }

    public function setpanelmode()
    {
        $wide = (int) $_REQUEST['wide'];
        setcookie(
            'ttrss_widescreen',
            $wide,
            time() + \SmallSmallRSS\Constants::COOKIE_LIFETIME_LONG
        );
        echo json_encode(array('wide' => $wide));
    }

    public function updaterandomfeed()
    {
        // Test if the feed need a update (update interval exceded).
        if (\SmallSmallRSS\Config::get('DB_TYPE') == 'pgsql') {
            $update_limit_qpart = "AND ((
                    ttrss_feeds.update_interval = 0
                    AND ttrss_feeds.last_updated < NOW() - CAST((ttrss_user_prefs.value || ' minutes') AS INTERVAL)
                ) OR (
                    ttrss_feeds.update_interval > 0
                    AND ttrss_feeds.last_updated < NOW() - CAST((ttrss_feeds.update_interval || ' minutes') AS INTERVAL)
                ) OR ttrss_feeds.last_updated IS NULL
                OR last_updated = '1970-01-01 00:00:00')";
        } else {
            $update_limit_qpart = "AND ((
                    ttrss_feeds.update_interval = 0
                    AND ttrss_feeds.last_updated < DATE_SUB(
                        NOW(), INTERVAL CONVERT(ttrss_user_prefs.value, SIGNED INTEGER) MINUTE
                    )
                ) OR (
                    ttrss_feeds.update_interval > 0
                    AND ttrss_feeds.last_updated < DATE_SUB(NOW(), INTERVAL ttrss_feeds.update_interval MINUTE)
                ) OR ttrss_feeds.last_updated IS NULL
                OR last_updated = '1970-01-01 00:00:00')";
        }

        // Test if feed is currently being updated by another process.
        if (\SmallSmallRSS\Config::get('DB_TYPE') == 'pgsql') {
            $updstart_thresh_qpart = "
                 AND (
                     ttrss_feeds.last_update_started IS NULL
                     OR ttrss_feeds.last_update_started < NOW() - INTERVAL '5 minutes'
                 )";
        } else {
            $updstart_thresh_qpart = '
                 AND (
                     ttrss_feeds.last_update_started IS NULL OR
                     ttrss_feeds.last_update_started < DATE_SUB(NOW(), INTERVAL 5 MINUTE)
                 )';
        }
        $random_qpart = \SmallSmallRSS\Database::getRandomFunction();
        // We search for feed needing update.
        $result = \SmallSmallRSS\Database::query(
            "SELECT ttrss_feeds.feed_url,ttrss_feeds.id
            FROM
                ttrss_feeds, ttrss_users, ttrss_user_prefs
            WHERE
                ttrss_feeds.owner_uid = ttrss_users.id
                AND ttrss_users.id = ttrss_user_prefs.owner_uid
                AND ttrss_user_prefs.pref_name = 'DEFAULT_UPDATE_INTERVAL'
                AND ttrss_feeds.owner_uid = " . $_SESSION['uid'] . "
                $update_limit_qpart
                $updstart_thresh_qpart
            ORDER BY $random_qpart
            LIMIT 30"
        );

        $feed_id = -1;
        $num_updated = 0;
        $tstart = time();
        while ($line = \SmallSmallRSS\Database::fetchAssoc($result)) {
            $feed_id = $line['id'];
            if (time() - $tstart < ini_get('max_execution_time') * 0.7) {
                \SmallSmallRSS\RSSUpdater::updateFeed($feed_id);
                ++$num_updated;
            } else {
                break;
            }
        }
        // Purge orphans and cleanup tags
        \SmallSmallRSS\Entries::purgeOrphans();
        \SmallSmallRSS\Tags::clearExpired(14);
        if ($num_updated > 0) {
            echo json_encode(
                array(
                    'message' => 'UPDATE_COUNTERS',
                    'num_updated' => $num_updated
                )
            );
        } else {
            echo json_encode(array('message' => 'NOTHING_TO_UPDATE'));
        }

    }

    private function markArticlesById($ids, $mark_mode)
    {
        if ($mark_mode == \SmallSmallRSS\MarkModes::MARK) {
            \SmallSmallRSS\UserEntries::markIds($ids, $_SESSION['uid']);
        } elseif ($mark_mode == \SmallSmallRSS\MarkModes::UNMARK) {
            \SmallSmallRSS\UserEntries::unmarkIds($ids, $_SESSION['uid']);
        } else {
            \SmallSmallRSS\UserEntries::toggleMarkIds($ids, $_SESSION['uid']);
        }
    }

    private function publishArticlesById($ids, $mark_mode)
    {

        $tmp_ids = array();

        foreach ($ids as $id) {
            array_push($tmp_ids, "ref_id = '$id'");
        }

        $ids_qpart = join(' OR ', $tmp_ids);

        if ($mark_mode == 0) {
            \SmallSmallRSS\Database::query(
                "UPDATE ttrss_user_entries
                 SET published = false,last_published = NOW()
                 WHERE ($ids_qpart) AND owner_uid = " . $_SESSION['uid']
            );
        } elseif ($mark_mode == 1) {
            \SmallSmallRSS\Database::query(
                "UPDATE ttrss_user_entries
                 SET published = true,last_published = NOW()
                 WHERE ($ids_qpart) AND owner_uid = " . $_SESSION['uid']
            );
        } else {
            \SmallSmallRSS\Database::query(
                "UPDATE ttrss_user_entries
                 SET published = NOT published,last_published = NOW()
                 WHERE ($ids_qpart) AND owner_uid = " . $_SESSION['uid']
            );
        }
        $hub = \SmallSmallRSS\Config::get('PUBSUBHUBBUB_HUB');
        if ($hub) {
            $rss_link = get_self_url_prefix() .
                '/public.php?op=rss&id=-2&key=' .
                \SmallSmallRSS\AccessKeys::getForFeed(-2, false, $_SESSION['uid']);
            $p = new \Pubsubhubbub\Publisher($hub);
            $pubsub_result = $p->publishUpdate($rss_link);
        }
    }

    public function getlinktitlebyid()
    {
        $id = \SmallSmallRSS\Database::escapeString($_REQUEST['id']);

        $result = \SmallSmallRSS\Database::query(
            "SELECT link, title FROM ttrss_entries, ttrss_user_entries
             WHERE ref_id = '$id' AND ref_id = id AND owner_uid = ". $_SESSION['uid']
        );

        if (\SmallSmallRSS\Database::numRows($result) != 0) {
            $link = \SmallSmallRSS\Database::fetchResult($result, 0, 'link');
            $title = \SmallSmallRSS\Database::fetchResult($result, 0, 'title');
            echo json_encode(array('link' => $link, 'title' => $title));
        } else {
            echo json_encode(array('error' => 'ARTICLE_NOT_FOUND'));
        }
    }

    public function log()
    {
        $logmsg = \SmallSmallRSS\Database::escapeString($_REQUEST['logmsg']);
        if ($logmsg) {
            \SmallSmallRSS\Logger::logError(E_USER_WARNING, $logmsg, '[client-js]', 0, false);
        }
        echo json_encode(array('message' => 'HOST_ERROR_LOGGED'));

    }
}
