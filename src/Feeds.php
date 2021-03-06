<?php
namespace SmallSmallRSS;

/*
  CREATE TABLE `ttrss_feeds` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `owner_uid` int(11) NOT NULL,
  `title` varchar(200) NOT NULL,
  `cat_id` int(11) DEFAULT NULL,
  `feed_url` text NOT NULL,
  `icon_url` varchar(250) NOT NULL DEFAULT '',
  `update_interval` int(11) NOT NULL DEFAULT '0',
  `purge_interval` int(11) NOT NULL DEFAULT '0',
  `last_updated` datetime DEFAULT '0000-00-00 00:00:00',
  `last_error` varchar(250) NOT NULL DEFAULT '',
  `favicon_avg_color` varchar(11) DEFAULT NULL,
  `site_url` varchar(250) NOT NULL DEFAULT '',
  `auth_login` varchar(250) NOT NULL DEFAULT '',
  `auth_pass` varchar(250) NOT NULL DEFAULT '',
  `parent_feed` int(11) DEFAULT NULL,
  `private` tinyint(1) NOT NULL DEFAULT '0',
  `rtl_content` tinyint(1) NOT NULL DEFAULT '0',
  `hidden` tinyint(1) NOT NULL DEFAULT '0',
  `include_in_digest` tinyint(1) NOT NULL DEFAULT '1',
  `cache_images` tinyint(1) NOT NULL DEFAULT '0',
  `hide_images` tinyint(1) NOT NULL DEFAULT '0',
  `cache_content` tinyint(1) NOT NULL DEFAULT '0',
  `auth_pass_encrypted` tinyint(1) NOT NULL DEFAULT '0',
  `last_viewed` datetime DEFAULT NULL,
  `last_update_started` datetime DEFAULT NULL,
  `always_display_enclosures` tinyint(1) NOT NULL DEFAULT '0',
  `update_method` int(11) NOT NULL DEFAULT '0',
  `order_id` int(11) NOT NULL DEFAULT '0',
  `mark_unread_on_update` tinyint(1) NOT NULL DEFAULT '0',
  `update_on_checksum_change` tinyint(1) NOT NULL DEFAULT '0',
  `strip_images` tinyint(1) NOT NULL DEFAULT '0',
  `view_settings` varchar(250) NOT NULL DEFAULT '',
  `pubsub_state` int(11) NOT NULL DEFAULT '0',
  `favicon_last_checked` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `owner_uid` (`owner_uid`),
  KEY `cat_id` (`cat_id`),
  KEY `parent_feed` (`parent_feed`),
  KEY `ttrss_feeds_owner_uid_index` (`owner_uid`),
  KEY `ttrss_feeds_cat_id_idx` (`cat_id`),
  CONSTRAINT `ttrss_feeds_ibfk_1` FOREIGN KEY
  (`owner_uid`) REFERENCES `ttrss_users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `ttrss_feeds_ibfk_2` FOREIGN KEY (`cat_id`) REFERENCES
  `ttrss_feed_categories` (`id`) ON DELETE SET NULL,
  CONSTRAINT `ttrss_feeds_ibfk_3` FOREIGN KEY (`parent_feed`) REFERENCES
  `ttrss_feeds` (`id`) ON DELETE SET NULL
*/

class Feeds
{
    const RECENTLY_READ = -6;
    const ALL = -4;
    const FRESH = -3;
    const PUBLISHED = -2;
    const STARRED = -1;
    const ARCHIVED = 0;

    public static function getSpecialFeeds()
    {
        return array(
            self::ALL => __('All articles'),
            self::FRESH => __('Fresh articles'),
            self::PUBLISHED => __('Published articles'),
            self::STARRED => __('Starred articles'),
            self::ARCHIVED => __('Archived articles'),
            self::RECENTLY_READ => __('Recently read')
        );
    }

    public static function getSpecialFeedIds()
    {
        return array_keys(self::getSpecialFeeds());
    }

    public static function getTitle($feed_id)
    {
        $special_feeds = self::getSpecialFeeds();
        if (isset($special_feeds[$feed_id])) {
            return $special_feeds[$feed_id];
        } elseif ($id < \SmallSmallRSS\Constants::LABEL_BASE_INDEX) {
            $label_id = feed_to_label_id($id);
            $result = \SmallSmallRSS\Database::query(
                "SELECT caption
                 FROM ttrss_labels2
                 WHERE id = '$label_id'"
            );
            if (\SmallSmallRSS\Database::numRows($result) == 1) {
                return \SmallSmallRSS\Database::fetchResult($result, 0, 'caption');
            } else {
                return "Unknown label ($label_id)";
            }
        } elseif (is_numeric($id) && $id > 0) {
            $result = \SmallSmallRSS\Database::query(
                "SELECT title
                 FROM ttrss_feeds
                 WHERE id = '$id'"
            );
            if (\SmallSmallRSS\Database::numRows($result) == 1) {
                return \SmallSmallRSS\Database::fetchResult($result, 0, 'title');
            } else {
                return "Unknown feed ($id)";
            }
        } else {
            return $id;
        }
    }

    public static function purgeInterval($feed_id)
    {
        $result = \SmallSmallRSS\Database::query(
            "SELECT purge_interval, owner_uid
             FROM ttrss_feeds
             WHERE id = '$feed_id'"
        );
        if (\SmallSmallRSS\Database::numRows($result) == 1) {
            $purge_interval = \SmallSmallRSS\Database::fetchResult($result, 0, 'purge_interval');
            $owner_uid = \SmallSmallRSS\Database::fetchResult($result, 0, 'owner_uid');
            if ($purge_interval == 0) {
                $purge_interval = \SmallSmallRSS\DBPrefs::read('PURGE_OLD_DAYS', $owner_uid);
            }
            return $purge_interval;
        } else {
            return -1;
        }
    }

    /**
     * Purge a feed of old posts.
     *
     * @param mixed $feed_id The id of the purged feed.
     * @param mixed $purge_interval Olderness of purged posts.
     * @param boolean $debug Set to True to enable the debug. False by default.
     * @access public
     * @return void
     */
    public static function purge($feed_id, $purge_interval = 0)
    {
        if (!$purge_interval) {
            $purge_interval = self::purgeInterval($feed_id);
        }
        $rows = -1;
        $result = \SmallSmallRSS\Database::query(
            "SELECT owner_uid
             FROM ttrss_feeds
             WHERE id = '$feed_id'"
        );
        $owner_uid = false;
        if (\SmallSmallRSS\Database::numRows($result) == 1) {
            $owner_uid = \SmallSmallRSS\Database::fetchResult($result, 0, 'owner_uid');
        }
        if ($purge_interval == -1 || !$purge_interval) {
            if ($owner_uid) {
                \SmallSmallRSS\CountersCache::update($feed_id, $owner_uid);
            }
            return;
        }

        if (!$owner_uid) {
            return;
        }

        if (\SmallSmallRSS\Config::get('FORCE_ARTICLE_PURGE') == 0) {
            $purge_unread = \SmallSmallRSS\DBPrefs::read('PURGE_UNREAD_ARTICLES', $owner_uid, false);
        } else {
            $purge_unread = true;
            $purge_interval = \SmallSmallRSS\Config::get('FORCE_ARTICLE_PURGE');
        }

        if (!$purge_unread) {
            $query_limit = 'AND unread = false';
        } else {
            $query_limit = '';
        }

        if (\SmallSmallRSS\Config::get('DB_TYPE') == 'pgsql') {
            $pg_version = \SmallSmallRSS\Database::getPostgreSQLVersion();
            if (preg_match("/^7\./", $pg_version) || preg_match("/^8\.0/", $pg_version)) {
                $result = \SmallSmallRSS\Database::query(
                    "DELETE FROM ttrss_user_entries
                     WHERE
                         ttrss_entries.id = ref_id
                         AND marked = false
                         AND feed_id = '$feed_id'
                         AND ttrss_entries.date_updated < NOW() - INTERVAL '$purge_interval days'
                         $query_limit"
                );
            } else {
                $result = \SmallSmallRSS\Database::query(
                    "DELETE FROM ttrss_user_entries
                     USING ttrss_entries
                     WHERE
                         ttrss_entries.id = ref_id
                         AND marked = false AND
                         AND feed_id = '$feed_id'
                         AND ttrss_entries.date_updated < NOW() - INTERVAL '$purge_interval days'
                         $query_limit"
                );
            }

        } else {
            $result = \SmallSmallRSS\Database::query(
                "DELETE FROM ttrss_user_entries
                 USING ttrss_user_entries, ttrss_entries
                 WHERE
                     ttrss_entries.id = ref_id
                     AND marked = false
                     AND feed_id = '$feed_id'
                     AND ttrss_entries.date_updated < DATE_SUB(NOW(), INTERVAL $purge_interval DAY)
                     $query_limit"
            );
        }
        $rows = \SmallSmallRSS\Database::affectedRows($result);
        \SmallSmallRSS\CountersCache::update($feed_id, $owner_uid);
        \SmallSmallRSS\Logger::debug(
            "Purged feed $feed_id ($purge_interval): deleted $rows articles",
            false
        );
        return $rows;

    }

    public static function countFeedSubscribers()
    {
        $result = \SmallSmallRSS\Database::query(
            "SELECT feed_url, site_url, title, COUNT(id) AS subscribers
             FROM ttrss_feeds
             WHERE (
                 SELECT COUNT(id) = 0
                 FROM ttrss_feeds AS tf
                 WHERE
                     tf.feed_url = ttrss_feeds.feed_url
                     AND (
                         private IS true
                         OR auth_login != ''
                         OR auth_pass != ''
                         OR feed_url LIKE '%:%@%/%'
                     )
             )
             GROUP BY feed_url, site_url, title
             ORDER BY subscribers DESC
             LIMIT 1000"
        );
        $lines = array();
        while ($line = \SmallSmallRSS\Database::fetchAssoc($result)) {
            $lines[] = $line;
        }
        return $lines;
    }

    public static function getFeedUpdateInterval($feed_id)
    {
        $result = \SmallSmallRSS\Database::query(
            "SELECT owner_uid, update_interval
             FROM ttrss_feeds
             WHERE id = '$feed_id'"
        );
        if (\SmallSmallRSS\Database::numRows($result) == 1) {
            $update_interval = \SmallSmallRSS\Database::fetchResult($result, 0, 'update_interval');
            $owner_uid = \SmallSmallRSS\Database::fetchResult($result, 0, 'owner_uid');
            if ($update_interval != 0) {
                return $update_interval;
            } else {
                return \SmallSmallRSS\DBPrefs::read('DEFAULT_UPDATE_INTERVAL', $owner_uid, false);
            }
        } else {
            return -1;
        }
    }

    public static function getCategory($feed_id)
    {
        $result = \SmallSmallRSS\Database::query(
            "SELECT cat_id
             FROM ttrss_feeds
             WHERE id = '$feed_id'"
        );
        if ($result && \SmallSmallRSS\Database::numRows($result) > 0) {
            return (int) \SmallSmallRSS\Database::fetchResult($result, 0, 'cat_id');
        } else {
            return false;
        }
    }

    public static function markUpdateStarted($feed_ids)
    {
        if (!$feed_ids) {
            return;
        }
        $quoted_feed_ids = array();
        foreach ($feed_ids as $feed_id) {
            $quoted_feed_ids[] = "'" . \SmallSmallRSS\Database::escapeString($feed_id) . "'";
        }
        \SmallSmallRSS\Database::query(
            sprintf(
                'UPDATE ttrss_feeds
                 SET last_update_started = NOW()
                 WHERE id IN (%s)',
                implode(',', $quoted_feed_ids)
            )
        );
    }
    public static function markUpdated($feed_id, $last_error = '')
    {
        $last_error = \SmallSmallRSS\Database::escapeString($last_error);
        $feed_id = \SmallSmallRSS\Database::escapeString($feed_id);
        \SmallSmallRSS\Database::query(
            "UPDATE ttrss_feeds
             SET
                 last_updated = NOW(),
                 last_error = '$last_error'
             WHERE id = '$feed_id'"
        );
    }

    public static function resetAll()
    {
        \SmallSmallRSS\Database::query(
            "UPDATE ttrss_feeds
             SET
                 last_update_started = '1970-01-01',
                 last_updated = '1970-01-01'"
        );
    }

    /**
     * this is called after user is created to initialize
     * default feeds, labels or whatever else user preferences
     * are checked on every login, not here
     */
    public static function newUser($uid)
    {
        \SmallSmallRSS\Database::query(
            "INSERT INTO ttrss_feeds
             (owner_uid,title,feed_url)
             VALUES (
                 '$uid',
                 'Tiny Tiny RSS: New Releases',
                 'http://tt-rss.org/releases.rss'
             )"
        );

        \SmallSmallRSS\Database::query(
            "INSERT INTO ttrss_feeds
             (owner_uid,title,feed_url)
             VALUES (
                 '$uid',
                 'Tiny Tiny RSS: Forum',
                 'http://tt-rss.org/forum/rss.php'
             )"
        );
    }

    public static function count($owner_uid)
    {
        $owner_uid = \SmallSmallRSS\Database::escapeString($owner_uid);
        $result = \SmallSmallRSS\Database::query(
            'SELECT COUNT(id) AS fn
             FROM ttrss_feeds
             WHERE owner_uid = ' . $owner_uid
        );
        return (int) \SmallSmallRSS\Database::fetchResult($result, 0, 'fn');
    }

    public static function countWithNullCat($owner_uid)
    {
        $owner_uid = \SmallSmallRSS\Database::escapeString($owner_uid);
        $result = \SmallSmallRSS\Database::query(
            'SELECT COUNT(id) AS fn
             FROM ttrss_feeds
             WHERE
                 cat_id IS NULL
                 AND owner_uid = ' . $owner_uid
        );
        return (int) \SmallSmallRSS\Database::fetchResult($result, 0, 'fn');
    }

    public static function hasIcon($feed_id)
    {
        $file = \SmallSmallRSS\Config::get('ICONS_DIR') . "/$feed_id.ico";
        return (is_file($file) && filesize($file));
    }
    public static function getForCategory($cat_id, $owner_uid)
    {
        if ($cat_id != 0) {
            $cat_query = "cat_id = '$cat_id'";
        } else {
            $cat_query = 'cat_id IS NULL';
        }
        $result = \SmallSmallRSS\Database::query(
            "SELECT id
             FROM ttrss_feeds
             WHERE
                 $cat_query
                 AND owner_uid = " . $owner_uid
        );
        $feed_ids = array();
        while (($line = \SmallSmallRSS\Database::fetchAssoc($result))) {
            $feed_ids[] = $line['id'];
        }
        return $feed_ids;
    }
    public static function getForSelectByCategory($cat_id, $owner_uid)
    {
        if ($cat != 0) {
            $cat_query = "cat_id = '$cat'";
        } else {
            $cat_query = 'cat_id IS NULL';
        }
        $result = \SmallSmallRSS\Database::query(
            "SELECT id, title
             FROM ttrss_feeds
             WHERE
                 $cat_query
                 AND owner_uid = $owner_uid
             ORDER BY title ASC"
        );
        $feeds = array();
        while (($line = \SmallSmallRSS\Database::fetchAssoc($result))) {
            $feeds[] = $line;
        }
        return $feeds;
    }
    public static function getAllForSelectByCategory($owner_uid)
    {
        $result = \SmallSmallRSS\Database::query(
            "SELECT id, title
             FROM ttrss_feeds
             WHERE owner_uid = $owner_uid
             ORDER BY title ASC"
        );
        $feeds = array();
        while (($line = \SmallSmallRSS\Database::fetchAssoc($result))) {
            $feeds[] = $line;
        }
        return $feeds;
    }
    public static function getByUrl($url, $owner_uid)
    {
        $result = \SmallSmallRSS\Database::query(
            "SELECT id
             FROM ttrss_feeds
             WHERE
                 feed_url = '$url'
                 AND owner_uid = $owner_uid"
        );
        $feed_id = false;
        while (($line = \SmallSmallRSS\Database::fetchAssoc($result))) {
            $feed_id = $line['id'];
        }
        return $feed_id;
    }
    public static function add($url, $cat_id, $owner_uid, $auth_login, $auth_pass)
    {
        if ($cat_id == '0' || !$cat_id) {
            $cat_qpart = 'NULL';
        } else {
            $cat_qpart = "'$cat_id'";
        }
        if (\SmallSmallRSS\Crypt::isEnabled()) {
            $auth_pass = substr(\SmallSmallRSS\Crypt::en($auth_pass), 0, 250);
            $auth_pass_encrypted = 'true';
        } else {
            $auth_pass_encrypted = 'false';
        }
        $auth_pass = \SmallSmallRSS\Database::escapeString($auth_pass);
        $result = \SmallSmallRSS\Database::query(
            "INSERT INTO ttrss_feeds
             (owner_uid,feed_url,title,cat_id, auth_login,auth_pass,update_method,auth_pass_encrypted)
             VALUES (
                 '$owner_uid', '$url',
                 '[Unknown]', $cat_qpart, '$auth_login', '$auth_pass', 0, $auth_pass_encrypted)"
        );

    }
    public static function getCounts($owner_uid)
    {
        $counts = array(
            'max_feed_id' => 0,
            'num_feeds' => 0
        );
        $result = \SmallSmallRSS\Database::query(
            "SELECT
                 MAX(id) AS max_feed_id,
                 COUNT(*) AS num_feeds
             FROM ttrss_feeds
             WHERE owner_uid = $owner_uid"
        );
        while (($line = \SmallSmallRSS\Database::fetchAssoc($result))) {
            $counts = $line;
        }
        return $counts;

    }
    public static function getCategoryTitle($feed_id)
    {
        if ($feed_id == -1) {
            return __('Special');
        } elseif ($feed_id < \SmallSmallRSS\Constants::LABEL_BASE_INDEX) {
            return __('Labels');
        } elseif ($feed_id > 0) {
            $result = \SmallSmallRSS\Database::query(
                "SELECT ttrss_feed_categories.title
                 FROM
                     ttrss_feed_categories,
                     ttrss_feeds
                 WHERE
                     ttrss_feeds.id = '$feed_id'
                     AND cat_id = ttrss_feed_categories.id"
            );
            if (\SmallSmallRSS\Database::numRows($result) == 1) {
                return \SmallSmallRSS\Database::fetchResult($result, 0, 'title');
            } else {
                return __('Uncategorized');
            }
        } else {
            return "getCategoryTitle($feed_id) failed";
        }
    }
    public static function checkOwner($feed_id, $owner_uid)
    {
        $feed_id = \SmallSmallRSS\Database::escapeString($feed_id);
        $result = \SmallSmallRSS\Database::query(
            "SELECT id
             FROM ttrss_feeds
             WHERE
                 id = '$feed_id'
                 AND owner_uid = " . $_SESSION['uid']
        );
        return \SmallSmallRSS\Database::numRows($result) != 0;
    }
}
