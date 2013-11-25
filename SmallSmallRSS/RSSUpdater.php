<?php
namespace SmallSmallRSS;

class RSSUpdater
{
    /**
     * Update a feed batch.
     * Used by daemons to update n feeds by run.
     * Only update feed needing a update, and not being processed
     * by another process.
     *
     * @param mixed $link Database link
     * @param integer $limit Maximum number of feeds in update batch. Default to DAEMON_FEED_LIMIT
     * @return void
     */
    public static function updateBatch($limit)
    {
        if (is_null($limit)) {
            $limit = \SmallSmallRSS\Config::get('DAEMON_FEED_LIMIT');
        }
        // Process all other feeds using last_updated and interval parameters
        \SmallSmallRSS\Sanity::schemaOrDie();

        $feeds_ids = \SmallSmallRSS\RSSUpdater::getUpdateableFeeds($limit);

        // Here is a little cache magic in order to minimize risk of double feed updates.
        // We update the feed last update started date before anything else.
        // There is no lag due to feed contents downloads
        // It prevents another process from updating the same feed.
        \SmallSmallRSS\Feeds::markUpdated($feeds_to_update);

        $nf = 0;
        update_rss_feed($tline["id"], true);
        \SmallSmallRSS\Digest::send_headlines();
        return $nf;
    }
    public static function getUpdateableFeeds($limit = null)
    {
        // Test if the user has loggued in recently. If not, it does not update
        // its feeds.
        $single_user = \SmallSmallRSS\Auth::is_single_user_mode();
        $daemon_update_login_limit = \SmallSmallRSS\Config::get('DAEMON_UPDATE_LOGIN_LIMIT');
        if (!$single_user && $daemon_update_login_limit > 0) {
            if (\SmallSmallRSS\Config::get('DB_TYPE') == "pgsql") {
                $login_thresh_qpart = (
                    "AND ttrss_users.last_login >= NOW() - INTERVAL"
                    . " '" . $daemon_update_login_limit . " days'"
                );
            } else {
                $login_thresh_qpart = (
                    "AND ttrss_users.last_login >= DATE_SUB("
                    . " NOW(), INTERVAL " . $daemon_update_login_limit . " DAY"
                    . ")"
                );
            }
        } else {
            $login_thresh_qpart = "";
        }

        // Test if the feed need a update (update interval exceded).
        if (\SmallSmallRSS\Config::get('DB_TYPE') == "pgsql") {
            $update_limit_qpart = "
            AND
            (
                (
                    ttrss_feeds.update_interval = 0
                    AND ttrss_user_prefs.value != '-1'
                    AND ttrss_feeds.last_updated < NOW() - CAST((ttrss_user_prefs.value || ' minutes') AS INTERVAL)
                )
                OR
                (
                    ttrss_feeds.update_interval > 0
                    AND ttrss_feeds.last_updated < NOW() - CAST((ttrss_feeds.update_interval || ' minutes') AS INTERVAL)
                )
                OR ttrss_feeds.last_updated IS NULL
                OR last_updated = '1970-01-01 00:00:00'
            )";
        } else {
            $update_limit_qpart = "
            AND
            (
                (
                    ttrss_feeds.update_interval = 0
                    AND ttrss_user_prefs.value != '-1'
                    AND ttrss_feeds.last_updated < DATE_SUB(
                        NOW(),
                        INTERVAL CONVERT(ttrss_user_prefs.value, SIGNED INTEGER) MINUTE
                    )
                ) OR (
                    ttrss_feeds.update_interval > 0
                    AND ttrss_feeds.last_updated < DATE_SUB(
                        NOW(),
                        INTERVAL ttrss_feeds.update_interval MINUTE
                    )
                ) OR ttrss_feeds.last_updated IS NULL
                OR last_updated = '1970-01-01 00:00:00')";
        }

        // Test if feed is currently being updated by another process.
        if (\SmallSmallRSS\Config::get('DB_TYPE') == "pgsql") {
            $updstart_thresh_qpart = "
             AND (
                 ttrss_feeds.last_update_started IS NULL
                 OR ttrss_feeds.last_update_started < NOW() - INTERVAL '10 minutes'
             )";
        } else {
            $updstart_thresh_qpart = "
             AND (
                 ttrss_feeds.last_update_started IS NULL
                 OR ttrss_feeds.last_update_started < DATE_SUB(NOW(), INTERVAL 10 MINUTE)
             )";
        }
        // Test if there is a limit to number of updated feeds
        $query_limit = "";
        if ($limit > 0) {
            $query_limit = sprintf("LIMIT %d", $limit);
        }

        $query = "SELECT DISTINCT ttrss_feeds.id AS feed_id
                  FROM ttrss_feeds, ttrss_users, ttrss_user_prefs
                  WHERE
                      ttrss_feeds.owner_uid = ttrss_users.id
                      AND ttrss_users.id = ttrss_user_prefs.owner_uid
                      AND ttrss_user_prefs.pref_name = 'DEFAULT_UPDATE_INTERVAL'
                      $login_thresh_qpart
                      $update_limit_qpart
                      $updstart_thresh_qpart
                  ORDER BY last_updated
                  $query_limit";
        // We search for feed needing update.
        $result = \SmallSmallRSS\Database::query($query);
        $feed_ids = array();
        while ($line = \SmallSmallRSS\Database::fetch_assoc($result)) {
            $feed_ids[] = \SmallSmallRSS\Database::escape_string($line['feed_id']);
        }
        return $feeds_to_update;
    }

    public static function housekeeping()
    {
        \SmallSmallRSS\FileCache::clearExpired();
        \SmallSmallRSS\Lockfiles::unlinkExpired();
        \SmallSmallRSS\Logger::clearExpired();
        \SmallSmallRSS\FeedbrowserCache::update(\SmallSmallRSS\Feeds::countFeedSubscribers());
        purge_orphans(true);
        \SmallSmallRSS\Tags::clearExpired(14);
    }
}
