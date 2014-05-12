<?php
namespace SmallSmallRSS;

class CountersCache
{
    public static function getGlobalUnread($owner_uid)
    {
        $result = \SmallSmallRSS\Database::query(
            "SELECT SUM(value) AS c_id
             FROM ttrss_counters_cache
             WHERE
                 owner_uid = '$owner_uid'
                 AND feed_id > 0"
        );
        $c_id = \SmallSmallRSS\Database::fetch_result($result, 0, 'c_id');
        return $c_id;
    }
    public static function cleanup($owner_uid)
    {
        \SmallSmallRSS\Database::query(
            "DELETE FROM ttrss_counters_cache
             WHERE
                 owner_uid = '$owner_uid'
                 AND (
                    SELECT COUNT(id)
                    FROM ttrss_feeds
                    WHERE ttrss_feeds.id = feed_id
                 ) = 0"
        );
    }

    public static function zeroAll($owner_uid)
    {
        \SmallSmallRSS\Database::query(
            "UPDATE ttrss_counters_cache SET
             value = 0 WHERE owner_uid = '$owner_uid'"
        );

        \SmallSmallRSS\Database::query(
            "UPDATE ttrss_cat_counters_cache SET
             value = 0 WHERE owner_uid = '$owner_uid'"
        );
    }

    public static function remove($feed_id, $owner_uid, $is_cat = false)
    {

        if (!$is_cat) {
            $table = 'ttrss_counters_cache';
        } else {
            $table = 'ttrss_cat_counters_cache';
        }

        \SmallSmallRSS\Database::query(
            "DELETE FROM $table WHERE
             feed_id = '$feed_id' AND owner_uid = '$owner_uid'"
        );

    }

    public static function updateAll($owner_uid)
    {

        if (\SmallSmallRSS\DBPrefs::read('ENABLE_FEED_CATS', $owner_uid)) {

            $result = \SmallSmallRSS\Database::query(
                "SELECT feed_id FROM ttrss_cat_counters_cache
                 WHERE feed_id > 0 AND owner_uid = '$owner_uid'"
            );

            while ($line = \SmallSmallRSS\Database::fetch_assoc($result)) {
                self::update($line['feed_id'], $owner_uid, true);
            }

            /* We have to manually include category 0 */

            self::update(0, $owner_uid, true);

        } else {
            $result = \SmallSmallRSS\Database::query(
                "SELECT feed_id FROM ttrss_counters_cache
                 WHERE feed_id > 0 AND owner_uid = '$owner_uid'"
            );

            while ($line = \SmallSmallRSS\Database::fetch_assoc($result)) {
                print self::update($line['feed_id'], $owner_uid);

            }

        }
    }

    public static function find(
        $feed_id,
        $owner_uid,
        $is_cat = false,
        $no_update = false
    )
    {

        if (!is_numeric($feed_id)) {
            return;
        }

        if (!$is_cat) {
            $table = 'ttrss_counters_cache';
        } else {
            $table = 'ttrss_cat_counters_cache';
        }

        if (\SmallSmallRSS\Config::get('DB_TYPE') == 'pgsql') {
            $date_qpart = "updated > NOW() - INTERVAL '15 minutes'";
        } elseif (\SmallSmallRSS\Config::get('DB_TYPE') == 'mysql') {
            $date_qpart = 'updated > DATE_SUB(NOW(), INTERVAL 15 MINUTE)';
        }

        $result = \SmallSmallRSS\Database::query(
            "SELECT value FROM $table
             WHERE
                 owner_uid = '$owner_uid'
                 AND feed_id = '$feed_id'
             LIMIT 1"
        );

        if (\SmallSmallRSS\Database::num_rows($result) == 1) {
            return \SmallSmallRSS\Database::fetch_result($result, 0, 'value');
        } else {
            if ($no_update) {
                return -1;
            } else {
                return self::update($feed_id, $owner_uid, $is_cat);
            }
        }

    }

    public static function update(
        $feed_id,
        $owner_uid,
        $is_cat = false,
        $update_pcat = true
    ) {

        if (!is_numeric($feed_id)) {
            return;
        }
        $prev_unread = self::find($feed_id, $owner_uid, $is_cat, true);

        /* When updating a label, all we need to do is recalculate feed counters
         * because labels are not cached */

        if ($feed_id < 0) {
            self::updateAll($owner_uid);
            return;
        }

        if (!$is_cat) {
            $table = 'ttrss_counters_cache';
        } else {
            $table = 'ttrss_cat_counters_cache';
        }

        $unread = 0;
        if ($is_cat && $feed_id >= 0) {
            if ($feed_id != 0) {
                $cat_qpart = "cat_id = '$feed_id'";
            } else {
                $cat_qpart = 'cat_id IS NULL';
            }

            /* Recalculate counters for child feeds */

            $result = \SmallSmallRSS\Database::query(
                "SELECT id FROM ttrss_feeds
                        WHERE owner_uid = '$owner_uid' AND $cat_qpart"
            );

            while ($line = \SmallSmallRSS\Database::fetch_assoc($result)) {
                self::update($line['id'], $owner_uid, false, false);
            }

            $result = \SmallSmallRSS\Database::query(
                "SELECT SUM(value) AS sv
                 FROM ttrss_counters_cache, ttrss_feeds
                 WHERE id = feed_id AND $cat_qpart AND
                 ttrss_feeds.owner_uid = '$owner_uid'"
            );
            if ($result) {
                $row = \SmallSmallRSS\Database::fetch_assoc($result);
                if (isset($row['sv'])) {
                    $unread = (int) $row['sv'];
                }
            }
        } else {
            $unread = (int) countUnreadFeedArticles($feed_id, $is_cat, true, $owner_uid);
        }

        \SmallSmallRSS\Database::query('BEGIN');
        $result = \SmallSmallRSS\Database::query(
            "SELECT feed_id FROM $table
             WHERE owner_uid = '$owner_uid' AND feed_id = '$feed_id' LIMIT 1"
        );

        if (\SmallSmallRSS\Database::num_rows($result) == 1) {
            \SmallSmallRSS\Database::query(
                "UPDATE $table
                 SET value = '$unread', updated = NOW()
                 WHERE feed_id = '$feed_id' AND owner_uid = '$owner_uid'"
            );
        } else {
            \SmallSmallRSS\Database::query(
                "INSERT INTO $table
                 (feed_id, value, owner_uid, updated)
                 VALUES
                 ($feed_id, $unread, $owner_uid, NOW())"
            );
        }
        \SmallSmallRSS\Database::query('COMMIT');
        if ($feed_id > 0 && $prev_unread != $unread) {
            if (!$is_cat) {
                /* Update parent category */
                if ($update_pcat) {
                    $result = \SmallSmallRSS\Database::query(
                        "SELECT cat_id FROM ttrss_feeds
                         WHERE owner_uid = '$owner_uid' AND id = '$feed_id'"
                    );
                    $cat_id = (int) \SmallSmallRSS\Database::fetch_result($result, 0, 'cat_id');
                    self::update($cat_id, $owner_uid, true);
                }
            }
        } elseif ($feed_id < 0) {
            self::updateAll($owner_uid);
        }
        return $unread;
    }
}
