<?php
namespace SmallSmallRSS;

class Feeds {
    static public function get_special_feeds() {
        return array(
            -4 => __('All articles'),
            -3 => __('Fresh articles'),
            -2 => __('Starred articles'),
            -1 => __('Published articles'),
            0 => __('Archived articles'),
            -6 => __('Recently read')
        );
    }
    static public function get_special_feed_ids() {
        return array_keys(self::get_special_feeds());
    }
    static public function getTitle($feed_id)
    {
        $special_feeds = self::get_special_feeds();
        if (isset($special_feeds[$feed_id])) {
            return $special_feeds[$feed_id];
        } elseif ($id < \SmallSmallRSS\Constants::LABEL_BASE_INDEX) {
            $label_id = feed_to_label_id($id);
            $result = \SmallSmallRSS\Database::query("SELECT caption FROM ttrss_labels2 WHERE id = '$label_id'");
            if (\SmallSmallRSS\Database::num_rows($result) == 1) {
                return \SmallSmallRSS\Database::fetch_result($result, 0, "caption");
            } else {
                return "Unknown label ($label_id)";
            }
        } elseif (is_numeric($id) && $id > 0) {
            $result = \SmallSmallRSS\Database::query("SELECT title FROM ttrss_feeds WHERE id = '$id'");
            if (\SmallSmallRSS\Database::num_rows($result) == 1) {
                return \SmallSmallRSS\Database::fetch_result($result, 0, "title");
            } else {
                return "Unknown feed ($id)";
            }
        } else {
            return $id;
        }
    }

    static public function purge_interval($feed_id) {
        $result = \SmallSmallRSS\Database::query(
            "SELECT purge_interval, owner_uid
             FROM ttrss_feeds
             WHERE id = '$feed_id'"
        );
        if (\SmallSmallRSS\Database::num_rows($result) == 1) {
            $purge_interval = \SmallSmallRSS\Database::fetch_result($result, 0, "purge_interval");
            $owner_uid = \SmallSmallRSS\Database::fetch_result($result, 0, "owner_uid");
            if ($purge_interval == 0) {
                $purge_interval = \SmallSmallRSS\DBPrefs::read('PURGE_OLD_DAYS', $owner_uid);
            }
            return $purge_interval;
        } else {
            return -1;
        }
    }

    /**
     * Purge a feed old of posts.
     *
     * @param mixed $feed_id The id of the purged feed.
     * @param mixed $purge_interval Olderness of purged posts.
     * @param boolean $debug Set to True to enable the debug. False by default.
     * @access public
     * @return void
     */
    static public function purge($feed_id, $purge_interval) {
        if (!$purge_interval) {
            $purge_interval = self::purge_interval($feed_id);
        }
        $rows = -1;
        $result = \SmallSmallRSS\Database::query(
            "SELECT owner_uid FROM ttrss_feeds WHERE id = '$feed_id'"
        );
        $owner_uid = false;
        if (\SmallSmallRSS\Database::num_rows($result) == 1) {
            $owner_uid = \SmallSmallRSS\Database::fetch_result($result, 0, "owner_uid");
        }
        if ($purge_interval == -1 || !$purge_interval) {
            if ($owner_uid) {
                \SmallSmallRSS\CounterCache::update($feed_id, $owner_uid);
            }
            return;
        }

        if (!$owner_uid) {
            return;
        }

        if (\SmallSmallRSS\Config::get('FORCE_ARTICLE_PURGE') == 0) {
            $purge_unread = \SmallSmallRSS\DBPrefs::read("PURGE_UNREAD_ARTICLES", $owner_uid, false);
        } else {
            $purge_unread = true;
            $purge_interval = \SmallSmallRSS\Config::get('FORCE_ARTICLE_PURGE');
        }

        if (!$purge_unread) {
            $query_limit = "AND unread = false";
        } else {
            $query_limit = '';
        }

        if (\SmallSmallRSS\Config::get('DB_TYPE') == "pgsql") {
            $pg_version = get_pgsql_version();

            if (preg_match("/^7\./", $pg_version) || preg_match("/^8\.0/", $pg_version)) {

                $result = \SmallSmallRSS\Database::query(
                    "DELETE FROM ttrss_user_entries WHERE
                         ttrss_entries.id = ref_id
                         AND marked = false
                         AND feed_id = '$feed_id'
                         AND ttrss_entries.date_updated < NOW() - INTERVAL '$purge_interval days'
                         $query_limit"
                );

            } else {

                $result = \SmallSmallRSS\Database::query("DELETE FROM ttrss_user_entries
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
        $rows = \SmallSmallRSS\Database::affected_rows($result);
        \SmallSmallRSS\CounterCache::update($feed_id, $owner_uid);
        \SmallSmallRSS\Logger::debug(
            "Purged feed $feed_id ($purge_interval): deleted $rows articles",
            false
        );
        return $rows;

    }

    static public function countFeedSubscribers()
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
        while ($line = \SmallSmallRSS\Database::fetch_assoc($result)) {
            $lines[] = $line;
        }
        return $lines;
    }
}