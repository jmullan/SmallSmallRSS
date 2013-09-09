<?php
namespace SmallSmallRSS;

require_once __DIR__ . '/../include/db.php';

class CounterCache {
    static public function zero_all($owner_uid) {
        db_query("UPDATE ttrss_counters_cache SET
			value = 0 WHERE owner_uid = '$owner_uid'");

        db_query("UPDATE ttrss_cat_counters_cache SET
			value = 0 WHERE owner_uid = '$owner_uid'");
    }
    static public function remove($feed_id, $owner_uid, $is_cat = false) {

        if (!$is_cat) {
            $table = "ttrss_counters_cache";
        } else {
            $table = "ttrss_cat_counters_cache";
        }

        db_query("DELETE FROM $table WHERE
			feed_id = '$feed_id' AND owner_uid = '$owner_uid'");

    }

    static public function update_all($owner_uid) {

        if (get_pref('ENABLE_FEED_CATS', $owner_uid)) {

            $result = db_query("SELECT feed_id FROM ttrss_cat_counters_cache
				WHERE feed_id > 0 AND owner_uid = '$owner_uid'");

            while ($line = db_fetch_assoc($result)) {
                self::update($line["feed_id"], $owner_uid, true);
            }

            /* We have to manually include category 0 */

            self::update(0, $owner_uid, true);

        } else {
            $result = db_query("SELECT feed_id FROM ttrss_counters_cache
				WHERE feed_id > 0 AND owner_uid = '$owner_uid'");

            while ($line = db_fetch_assoc($result)) {
                print self::update($line["feed_id"], $owner_uid);

            }

        }
    }

    static public function find($feed_id, $owner_uid, $is_cat = false,
                         $no_update = false) {

        if (!is_numeric($feed_id)) return;

        if (!$is_cat) {
            $table = "ttrss_counters_cache";
        } else {
            $table = "ttrss_cat_counters_cache";
        }

        if (DB_TYPE == "pgsql") {
            $date_qpart = "updated > NOW() - INTERVAL '15 minutes'";
        } elseif (DB_TYPE == "mysql") {
            $date_qpart = "updated > DATE_SUB(NOW(), INTERVAL 15 MINUTE)";
        }

        $result = db_query("SELECT value FROM $table
			WHERE owner_uid = '$owner_uid' AND feed_id = '$feed_id'
			LIMIT 1");

        if (db_num_rows($result) == 1) {
            return db_fetch_result($result, 0, "value");
        } else {
            if ($no_update) {
                return -1;
            } else {
                return self::update($feed_id, $owner_uid, $is_cat);
            }
        }

    }

    static public function update($feed_id, $owner_uid, $is_cat = false,
                           $update_pcat = true) {

        if (!is_numeric($feed_id)) {
            return;
        }
        $prev_unread = self::find($feed_id, $owner_uid, $is_cat, true);

        /* When updating a label, all we need to do is recalculate feed counters
         * because labels are not cached */

        if ($feed_id < 0) {
            self::update_all($owner_uid);
            return;
        }

        if (!$is_cat) {
            $table = "ttrss_counters_cache";
        } else {
            $table = "ttrss_cat_counters_cache";
        }

        if ($is_cat && $feed_id >= 0) {
            if ($feed_id != 0) {
                $cat_qpart = "cat_id = '$feed_id'";
            } else {
                $cat_qpart = "cat_id IS NULL";
            }

            /* Recalculate counters for child feeds */

            $result = db_query("SELECT id FROM ttrss_feeds
						WHERE owner_uid = '$owner_uid' AND $cat_qpart");

            while ($line = db_fetch_assoc($result)) {
                self::update($line["id"], $owner_uid, false, false);
            }

            $result = db_query("SELECT SUM(value) AS sv
				FROM ttrss_counters_cache, ttrss_feeds
				WHERE id = feed_id AND $cat_qpart AND
				ttrss_feeds.owner_uid = '$owner_uid'");

            $unread = (int) db_fetch_result($result, 0, "sv");

        } else {
            $unread = (int) getFeedArticles($feed_id, $is_cat, true, $owner_uid);
        }

        db_query("BEGIN");

        $result = db_query("SELECT feed_id FROM $table
			WHERE owner_uid = '$owner_uid' AND feed_id = '$feed_id' LIMIT 1");

        if (db_num_rows($result) == 1) {
            db_query("UPDATE $table SET
				value = '$unread', updated = NOW() WHERE
				feed_id = '$feed_id' AND owner_uid = '$owner_uid'");

        } else {
            db_query("INSERT INTO $table
				(feed_id, value, owner_uid, updated)
				VALUES
				($feed_id, $unread, $owner_uid, NOW())");
        }

        db_query("COMMIT");

        if ($feed_id > 0 && $prev_unread != $unread) {

            if (!$is_cat) {

                /* Update parent category */

                if ($update_pcat) {

                    $result = db_query("SELECT cat_id FROM ttrss_feeds
						WHERE owner_uid = '$owner_uid' AND id = '$feed_id'");

                    $cat_id = (int) db_fetch_result($result, 0, "cat_id");

                    self::update($cat_id, $owner_uid, true);

                }
            }
        } elseif ($feed_id < 0) {
            self::update_all($owner_uid);
        }

        return $unread;
    }
}