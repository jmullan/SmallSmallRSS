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
        \SmallSmallRSS\Feeds::markUpdateStarted($feeds_to_update);
        $nf = 0;
        self::updateFeed($tline["id"], true);
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
            $feed_ids[] = $line['feed_id'];
        }
        return $feed_ids;
    }

    public static function housekeeping()
    {
        \SmallSmallRSS\FileCache::clearExpired();
        \SmallSmallRSS\Lockfiles::unlinkExpired();
        \SmallSmallRSS\Logger::clearExpired();
        \SmallSmallRSS\FeedbrowserCache::update(\SmallSmallRSS\Feeds::countFeedSubscribers());
        \SmallSmallRSS\Entries::purgeOrphans();
        \SmallSmallRSS\Tags::clearExpired(14);
    }

    public static function updateFeed($feed, $no_cache = false)
    {
        $result = \SmallSmallRSS\Database::query(
            "SELECT
             id,
             update_interval,
             auth_login,
             feed_url,
             auth_pass,
             cache_images,
             last_updated,
             mark_unread_on_update,
             owner_uid,
             pubsub_state,
             auth_pass_encrypted,
             (
                  SELECT max(date_entered)
                  FROM
                      ttrss_entries,
                      ttrss_user_entries
                  WHERE
                      ref_id = id
                      AND feed_id = '$feed'
             ) AS last_article_timestamp
             FROM ttrss_feeds WHERE id = '$feed'"
        );

        if (\SmallSmallRSS\Database::num_rows($result) == 0) {
            _debug("feed $feed NOT FOUND/SKIPPED");
            return false;
        }

        $row = \SmallSmallRSS\Database::fetch_assoc($result);

        $last_updated = $row["last_updated"];
        $last_article_timestamp = strtotime($row["last_article_timestamp"]);
        if (\SmallSmallRSS\Configs::get('DISABLE_HTTP_304')) {
            $last_article_timestamp = 0;
        }
        $owner_uid = $row["owner_uid"];
        $mark_unread_on_update = sql_bool_to_bool($row["mark_unread_on_update"]);
        $pubsub_state = $row["pubsub_state"];
        $auth_pass_encrypted = sql_bool_to_bool($row["auth_pass_encrypted"]);
        $auth_login = $row["auth_login"];
        $auth_pass = $row["auth_pass"];
        if ($auth_pass_encrypted) {
            $auth_pass = \SmallSmallRSS\Crypt::de($auth_pass);
        }
        $cache_images = sql_bool_to_bool($row["cache_images"]);
        $fetch_url = $row["feed_url"];
        \SmallSmallRSS\Feeds::markUpdateStarted(array($feed));
        $feed = \SmallSmallRSS\Database::escape_string($feed);
        $date_feed_processed = date('Y-m-d H:i');
        $cache_filename = \SmallSmallRSS\Config::get('CACHE_DIR') . "/simplepie/" . sha1($fetch_url) . ".xml";
        $pluginhost = new \SmallSmallRSS\PluginHost();
        $pluginhost->set_debug(true);
        $pluginhost->init_all();
        $pluginhost->init_user($owner_uid);
        $pluginhost->load_data();

        $rss = false;
        $rss_hash = false;

        $force_refetch = isset($_REQUEST["force_refetch"]);
        $feed_data = '';
        if (file_exists($cache_filename)
            && is_readable($cache_filename)
            && !$auth_login && !$auth_pass
            && filemtime($cache_filename) > time() - 30) {
            $feed_data = file_get_contents($cache_filename);
            if ($feed_data) {
                $rss_hash = sha1($feed_data);
            }

        }
        if (!$rss) {
            $feed_fetching_hooks = $pluginhost->get_hooks(\SmallSmallRSS\Hooks::FETCH_FEED);
            foreach ($feed_fetching_hooks as $plugin) {
                if (!$feed_data) {
                    $feed_data = $plugin->hook_fetch_feed($feed_data, $fetch_url, $owner_uid, $feed);
                }
            }
            $fetch_last_error = false;
            if (!$feed_data) {
                _debug("fetching [$fetch_url]...");
                $fetcher = new \SmallSmallRSS\Fetcher();
                $feed_data = $fetcher->getFileContents(
                    $fetch_url,
                    false,
                    $auth_login,
                    $auth_pass,
                    false,
                    ($no_cache
                     ? \SmallSmallRSS\Config::get('FEED_FETCH_NO_CACHE_TIMEOUT')
                     : \SmallSmallRSS\Config::get('FEED_FETCH_TIMEOUT')),
                    $force_refetch ? 0 : $last_article_timestamp
                );

                $feed_data = trim($feed_data);
                $fetch_last_error = $fetcher->last_error;
                $fetch_last_error_code = $fetcher->last_error_code;
            }
            if ($fetch_last_error) {
                _debug("unable to fetch: $fetch_last_error [$fetch_last_error_code]");
                $error_escaped = '';
                // If-Modified-Since
                if ($fetch_last_error_code != 304) {
                    $error_escaped = \SmallSmallRSS\Database::escape_string($fetch_last_error);
                }
                \SmallSmallRSS\Database::query(
                    "UPDATE ttrss_feeds
                 SET last_error = '$error_escaped', last_updated = NOW() WHERE id = '$feed'"
                );
                return;
            }
        }
        $fetched_feed_hooks = $pluginhost->get_hooks(\SmallSmallRSS\Hooks::FEED_FETCHED);
        foreach ($fetched_feed_hooks as $plugin) {
            $feed_data = $plugin->hook_feed_fetched($feed_data, $fetch_url, $owner_uid, $feed);
        }

        // set last update to now so if anything *simplepie* crashes later we won't be
        // continuously failing on the same feed

        if (!$rss) {
            $rss = new \SmallSmallRSS\FeedParser($feed_data);
            $rss->init();
        }

        $feed = \SmallSmallRSS\Database::escape_string($feed);
        if ($rss->error()) {
            $error_msg = \SmallSmallRSS\Database::escape_string(mb_substr($rss->error(), 0, 245));
            _debug("error fetching feed: $error_msg");
            \SmallSmallRSS\Database::query(
                "UPDATE ttrss_feeds
             SET
                 last_error = '$error_msg',
                 last_updated = NOW()
             WHERE id = '$feed'"
            );
            return;
        }

        // cache data for later
        if (!$auth_pass && !$auth_login) {
            if (is_writable(\SmallSmallRSS\Config::get('CACHE_DIR') . "/simplepie")) {
                $new_rss_hash = md5($feed_data);
                if ($new_rss_hash != $rss_hash && count($rss->get_items()) > 0) {
                    _debug("saving $cache_filename");
                    @file_put_contents($cache_filename, $feed_data);
                }
            } else {
                _debug("$cache_filename is not writable");
            }
        }

        // We use local pluginhost here because we need to load different per-user feed plugins
        $pluginhost->runHooks(\SmallSmallRSS\Hooks::FEED_PARSED, $rss);
        if (\SmallSmallRSS\Config::get('DB_TYPE') == "pgsql") {
            $favicon_interval_qpart = "favicon_last_checked < NOW() - INTERVAL '12 hour'";
        } else {
            $favicon_interval_qpart = "favicon_last_checked < DATE_SUB(NOW(), INTERVAL 12 HOUR)";
        }

        $result = \SmallSmallRSS\Database::query(
            "SELECT
                 title,
                 site_url,
                 owner_uid,
                 favicon_avg_color,
                 (favicon_last_checked IS NULL OR $favicon_interval_qpart) AS favicon_needs_check
             FROM ttrss_feeds
             WHERE id = '$feed'"
        );

        $registered_title = \SmallSmallRSS\Database::fetch_result($result, 0, "title");
        $orig_site_url = \SmallSmallRSS\Database::fetch_result($result, 0, "site_url");
        $favicon_needs_check = sql_bool_to_bool(
            \SmallSmallRSS\Database::fetch_result($result, 0, "favicon_needs_check")
        );
        $favicon_avg_color = \SmallSmallRSS\Database::fetch_result($result, 0, "favicon_avg_color");

        $owner_uid = \SmallSmallRSS\Database::fetch_result($result, 0, "owner_uid");

        $site_url = \SmallSmallRSS\Database::escape_string(
            mb_substr(\SmallSmallRSS\Utils::rewriteRelativeUrl($fetch_url, $rss->get_link()), 0, 245)
        );

        if ($favicon_needs_check || $force_refetch) {

            /* terrible hack: if we crash on floicon shit here, we won't check
             * the icon avgcolor again (unless the icon got updated) */

            $favicon_file = \SmallSmallRSS\Config::get('ICONS_DIR') . "/$feed.ico";
            $favicon_modified = @filemtime($favicon_file);
            check_feed_favicon($site_url, $feed);
            $favicon_modified_new = @filemtime($favicon_file);

            if ($favicon_modified_new > $favicon_modified) {
                $favicon_avg_color = '';
            }

            $favicon_colorstring = '';
            if (file_exists($favicon_file)
                && function_exists("imagecreatefromstring")
                && $favicon_avg_color == '') {
                \SmallSmallRSS\Database::query(
                    "UPDATE ttrss_feeds
                     SET favicon_avg_color = 'fail'
                     WHERE id = '$feed'"
                );
                $favicon_color = \SmallSmallRSS\Database::escape_string(
                    \SmallSmallRSS\Colors::calculateAverage($favicon_file)
                );
                $favicon_colorstring = ",favicon_avg_color = '".$favicon_color."'";
            } elseif ($favicon_avg_color == 'fail') {
                _debug("floicon failed on this file, not trying to recalculate avg color");
            }

            \SmallSmallRSS\Database::query(
                "UPDATE ttrss_feeds
                 SET favicon_last_checked = NOW()
                     $favicon_colorstring
                 WHERE id = '$feed'"
            );
        }

        if (!$registered_title || $registered_title == "[Unknown]") {
            $feed_title = \SmallSmallRSS\Database::escape_string($rss->get_title());
            if ($feed_title) {
                \SmallSmallRSS\Database::query(
                    "UPDATE ttrss_feeds
                     SET title = '$feed_title'
                     WHERE id = '$feed'"
                );
            }
        }

        if ($site_url && $orig_site_url != $site_url) {
            \SmallSmallRSS\Database::query(
                "UPDATE ttrss_feeds
                 SET site_url = '$site_url'
                 WHERE id = '$feed'"
            );
        }

        $filters = load_filters($feed, $owner_uid);
        $labels = \SmallSmallRSS\Labels::getAll($owner_uid);
        $items = $rss->get_items();
        if (!is_array($items)) {
            \SmallSmallRSS\Feeds::markUpdated($feed);
            return;
        }

        if ($pubsub_state != 2 && \SmallSmallRSS\Config::get('PUBSUBHUBBUB_ENABLED')) {
            $feed_hub_url = false;
            $links = $rss->get_links('hub');
            if ($links && is_array($links)) {
                foreach ($links as $l) {
                    $feed_hub_url = $l;
                    break;
                }
            }
            if ($feed_hub_url && function_exists('curl_init')
                && !ini_get("open_basedir")) {
                $callback_url = get_self_url_prefix() . "/public.php?op=pubsub&id=$feed";
                $s = new \Pubsubhubbub\Subscriber($feed_hub_url, $callback_url);
                $rc = $s->subscribe($fetch_url);
                \SmallSmallRSS\Database::query("UPDATE ttrss_feeds SET pubsub_state = 1 WHERE id = '$feed'");
            }
        }

        foreach ($items as $item) {
            if (!empty($_REQUEST['xdebug']) && $_REQUEST['xdebug'] == 3) {
                print_r($item);
            }

            $entry_guid = $item->get_id();
            if (!$entry_guid) {
                $entry_guid = $item->get_link();
            }
            if (!$entry_guid) {
                $entry_guid = self::makeGUIDFromTitle($item->get_title());
            }
            $hooks = $pluginhost->get_hooks(\SmallSmallRSS\Hooks::GUID_FILTER);
            foreach ($hooks as $plugin) {
                $args = array('item' => $item, 'guid' => $entry_guid);
                $entry_guid = $plugin->hookGuidFilter($args);
            }
            if (!$entry_guid) {
                continue;
            }

            $entry_guid = "$owner_uid,$entry_guid";
            $entry_guid_hashed = \SmallSmallRSS\Database::escape_string('SHA1:' . sha1($entry_guid));
            $entry_timestamp = $item->get_date();
            if ($entry_timestamp == -1 || !$entry_timestamp || $entry_timestamp > time()) {
                $entry_timestamp = time();
                $no_orig_date = 'true';
            } else {
                $no_orig_date = 'false';
            }
            $entry_timestamp_fmt = strftime("%Y/%m/%d %H:%M:%S", $entry_timestamp);
            $entry_title = $item->get_title();
            $entry_link = \SmallSmallRSS\Utils::rewriteRelativeUrl($site_url, $item->get_link());
            if (!$entry_title) {
                $entry_title = date("Y-m-d H:i:s", $entry_timestamp);
            };
            $entry_content = $item->get_content();
            if (!$entry_content) {
                $entry_content = $item->get_description();
            }
            if (!empty($_REQUEST['xdebug']) && $_REQUEST["xdebug"] == 2) {
                print "content: ";
                print $entry_content;
                print "\n";
            }
            $entry_comments = $item->get_comments_url();
            $entry_author = $item->get_author();
            $entry_guid = \SmallSmallRSS\Database::escape_string(mb_substr($entry_guid, 0, 245));
            $entry_comments = \SmallSmallRSS\Database::escape_string(mb_substr(trim($entry_comments), 0, 245));
            $entry_author = \SmallSmallRSS\Database::escape_string(mb_substr(trim($entry_author), 0, 245));
            $num_comments = (int) $item->get_comments_count();

            // parse <category> entries into tags
            $additional_tags = array();

            $additional_tags_src = $item->get_categories();

            if (is_array($additional_tags_src)) {
                foreach ($additional_tags_src as $tobj) {
                    array_push($additional_tags, $tobj);
                }
            }

            $entry_tags = array_unique($additional_tags);

            for ($i = 0; $i < count($entry_tags); $i++) {
                $entry_tags[$i] = mb_strtolower($entry_tags[$i], 'utf-8');
            }

            // TODO: less memory-hungry implementation
            // FIXME not sure if owner_uid is a good idea here, we may have a base
            // entry (?)
            $result = \SmallSmallRSS\Database::query(
                "SELECT plugin_data,title,content,link,tag_cache,author
             FROM ttrss_entries, ttrss_user_entries
             WHERE
                 ref_id = id
                 AND owner_uid = $owner_uid
                 AND (
                     guid = '" . \SmallSmallRSS\Database::escape_string($entry_guid) . "'
                     OR guid = '$entry_guid_hashed'
                 )"
            );

            if (\SmallSmallRSS\Database::num_rows($result) != 0) {
                $entry_plugin_data = \SmallSmallRSS\Database::fetch_result($result, 0, "plugin_data");
                $stored_article = array(
                    "title" => \SmallSmallRSS\Database::fetch_result($result, 0, "title"),
                    "content" => \SmallSmallRSS\Database::fetch_result($result, 0, "content"),
                    "link" => \SmallSmallRSS\Database::fetch_result($result, 0, "link"),
                    "tags" => explode(",", \SmallSmallRSS\Database::fetch_result($result, 0, "tag_cache")),
                    "author" => \SmallSmallRSS\Database::fetch_result($result, 0, "author")
                );
            } else {
                $entry_plugin_data = "";
                $stored_article = array();
            }

            $article = array(
                "owner_uid" => $owner_uid, // read only
                "guid" => $entry_guid, // read only
                "title" => $entry_title,
                "content" => $entry_content,
                "link" => $entry_link,
                "tags" => $entry_tags,
                "plugin_data" => $entry_plugin_data,
                "author" => $entry_author,
                "stored" => $stored_article
            );

            foreach ($pluginhost->get_hooks(\SmallSmallRSS\Hooks::ARTICLE_FILTER) as $plugin) {
                $article = $plugin->hookArticleFilter($article);
            }

            $entry_tags = $article["tags"];
            $entry_guid = \SmallSmallRSS\Database::escape_string($entry_guid);
            $entry_title = \SmallSmallRSS\Database::escape_string($article["title"]);
            $entry_author = \SmallSmallRSS\Database::escape_string($article["author"]);
            $entry_link = \SmallSmallRSS\Database::escape_string($article["link"]);
            $entry_plugin_data = \SmallSmallRSS\Database::escape_string($article["plugin_data"]);
            $entry_content = $article["content"]; // escaped below

            if ($cache_images && is_writable(\SmallSmallRSS\Config::get('CACHE_DIR') . '/images')) {
                $entry_content = \SmallSmallRSS\ImageCache::processImages($entry_content, $site_url);
            }

            $entry_content = \SmallSmallRSS\Database::escape_string($entry_content, false);

            $content_hash = "SHA1:" . sha1($entry_content);

            \SmallSmallRSS\Database::query("BEGIN");

            $result = \SmallSmallRSS\Database::query(
                "SELECT id FROM ttrss_entries
                 WHERE (guid = '$entry_guid' OR guid = '$entry_guid_hashed')"
            );

            if (\SmallSmallRSS\Database::num_rows($result) == 0) {
                _debug("base guid [$entry_guid] not found");
                // base post entry does not exist, create it
                $query = "
                INSERT INTO ttrss_entries (
                    title,
                    guid,
                    link,
                    updated,
                    content,
                    content_hash,
                    cached_content,
                    no_orig_date,
                    date_updated,
                    date_entered,
                    comments,
                    num_comments,
                    plugin_data,
                    author
                )
                VALUES (
                    '$entry_title',
                    '$entry_guid_hashed',
                    '$entry_link',
                    '$entry_timestamp_fmt',
                    '$entry_content',
                    '$content_hash',
                    '',
                    $no_orig_date,
                    NOW(),
                   '$date_feed_processed',
                   '$entry_comments',
                   '$num_comments',
                   '$entry_plugin_data',
                   '$entry_author')";
                $result = \SmallSmallRSS\Database::query($query);
                $article_labels = array();
            } else {
                // we keep encountering the entry in feeds, so we need to
                // update date_updated column so that we don't get horrible
                // dupes when the entry gets purged and reinserted again e.g.
                // in the case of SLOW SLOW OMG SLOW updating feeds

                $base_entry_id = \SmallSmallRSS\Database::fetch_result($result, 0, "id");

                \SmallSmallRSS\Database::query(
                    "UPDATE ttrss_entries
                     SET
                         date_updated = NOW()
                     WHERE id = '$base_entry_id'"
                );

                $article_labels = \SmallSmallRSS\Labels::getForArticle($base_entry_id, $owner_uid);
            }

            // now it should exist, if not - bad luck then
            $substring_for_date = \SmallSmallRSS\Config::get('SUBSTRING_FOR_DATE');
            $result = \SmallSmallRSS\Database::query(
                "SELECT
                     id,
                     content_hash, no_orig_date, title, plugin_data,guid,
                     " . $substring_for_date . "(date_updated,1,19) as date_updated,
                     " . $substring_for_date . "(updated,1,19) as updated,
                     num_comments
                 FROM
                     ttrss_entries
                 WHERE
                     guid = '$entry_guid'
                     OR guid = '$entry_guid_hashed'"
            );

            $entry_ref_id = 0;
            $entry_int_id = 0;

            if (\SmallSmallRSS\Database::num_rows($result) == 1) {
                // this will be used below in update handler
                $orig_content_hash = \SmallSmallRSS\Database::fetch_result($result, 0, "content_hash");
                $orig_title = \SmallSmallRSS\Database::fetch_result($result, 0, "title");
                $orig_num_comments = \SmallSmallRSS\Database::fetch_result($result, 0, "num_comments");
                $orig_date_updated = strtotime(
                    \SmallSmallRSS\Database::fetch_result($result, 0, "date_updated")
                );
                $orig_plugin_data = \SmallSmallRSS\Database::fetch_result($result, 0, "plugin_data");

                $ref_id = \SmallSmallRSS\Database::fetch_result($result, 0, "id");
                $entry_ref_id = $ref_id;

                // check for user post link to main table

                // do we allow duplicate posts with same GUID in different feeds?
                if (\SmallSmallRSS\DBPrefs::read("ALLOW_DUPLICATE_POSTS", $owner_uid, false)) {
                    $dupcheck_qpart = "AND (feed_id = '$feed' OR feed_id IS NULL)";
                } else {
                    $dupcheck_qpart = "";
                }

                /* Collect article tags here so we could filter by them: */

                $article_filters = \SmallSmallRSS\ArticleFilters::get(
                    $filters,
                    $entry_title,
                    $entry_content,
                    $entry_link,
                    $entry_timestamp,
                    $entry_author,
                    $entry_tags
                );

                if (\SmallSmallRSS\ArticleFilters::matchArticle($article_filters, "filter")) {
                    \SmallSmallRSS\Database::query("COMMIT"); // close transaction in progress
                    continue;
                }

                $score = \SmallSmallRSS\ArticleFilters::calculateScore($article_filters);

                $query = "SELECT ref_id, int_id
                      FROM ttrss_user_entries
                      WHERE
                          ref_id = '$ref_id'
                          AND owner_uid = '$owner_uid'
                          $dupcheck_qpart";

                $result = \SmallSmallRSS\Database::query($query);

                // okay it doesn't exist - create user entry
                if (\SmallSmallRSS\Database::num_rows($result) == 0) {

                    if ($score >= -500 && !\SmallSmallRSS\ArticleFilters::matchArticle($article_filters, 'catchup')) {
                        $unread = 'true';
                        $last_read_qpart = 'NULL';
                    } else {
                        $unread = 'false';
                        $last_read_qpart = 'NOW()';
                    }

                    if (\SmallSmallRSS\ArticleFilters::matchArticle($article_filters, 'mark') || $score > 1000) {
                        $marked = 'true';
                    } else {
                        $marked = 'false';
                    }

                    if (\SmallSmallRSS\ArticleFilters::matchArticle($article_filters, 'publish')) {
                        $published = 'true';
                    } else {
                        $published = 'false';
                    }

                    // N-grams

                    if (\SmallSmallRSS\Config::get('DB_TYPE') == "pgsql" and defined('_NGRAM_TITLE_DUPLICATE_THRESHOLD')) {

                        $result = \SmallSmallRSS\Database::query(
                            "SELECT COUNT(*) AS similar
                         FROM ttrss_entries, ttrss_user_entries
                         WHERE
                             ref_id = id
                             AND updated >= NOW() - INTERVAL '7 day'
                             AND similarity(title, '$entry_title') >= "._NGRAM_TITLE_DUPLICATE_THRESHOLD."
                             AND owner_uid = $owner_uid"
                        );

                        $ngram_similar = \SmallSmallRSS\Database::fetch_result($result, 0, "similar");

                        if ($ngram_similar > 0) {
                            $unread = 'false';
                        }
                    }

                    $last_marked = ($marked == 'true') ? 'NOW()' : 'NULL';
                    $last_published = ($published == 'true') ? 'NOW()' : 'NULL';

                    $result = \SmallSmallRSS\Database::query(
                        "INSERT INTO ttrss_user_entries
                                (ref_id, owner_uid, feed_id, unread, last_read, marked,
                                published, score, tag_cache, label_cache, uuid,
                                last_marked, last_published)
                            VALUES ('$ref_id', '$owner_uid', '$feed', $unread,
                                $last_read_qpart, $marked, $published, '$score', '', '',
                                '', $last_marked, $last_published)"
                    );

                    if (\SmallSmallRSS\Config::get('PUBSUBHUBBUB_HUB') && $published == 'true') {
                        $rss_link = get_self_url_prefix() .
                            "/public.php?op=rss&id=-2&key=" .
                            get_feed_access_key(-2, false, $owner_uid);

                        $p = new Publisher(\SmallSmallRSS\Config::get('PUBSUBHUBBUB_HUB'));
                        $pubsub_result = $p->publishUpdate($rss_link);
                    }

                    $result = \SmallSmallRSS\Database::query(
                        "SELECT int_id
                     FROM ttrss_user_entries
                     WHERE
                         ref_id = '$ref_id'
                         AND owner_uid = '$owner_uid'
                         AND feed_id = '$feed'
                     LIMIT 1"
                    );

                    if (\SmallSmallRSS\Database::num_rows($result) == 1) {
                        $entry_int_id = \SmallSmallRSS\Database::fetch_result($result, 0, "int_id");
                    }
                } else {
                    $entry_ref_id = \SmallSmallRSS\Database::fetch_result($result, 0, "ref_id");
                    $entry_int_id = \SmallSmallRSS\Database::fetch_result($result, 0, "int_id");
                }
                $post_needs_update = false;
                $update_insignificant = false;

                if ($orig_num_comments != $num_comments) {
                    $post_needs_update = true;
                    $update_insignificant = true;
                }

                if ($entry_plugin_data != $orig_plugin_data) {
                    $post_needs_update = true;
                    $update_insignificant = true;
                }

                if ($content_hash != $orig_content_hash) {
                    $post_needs_update = true;
                    $update_insignificant = false;
                }

                if (\SmallSmallRSS\Database::escape_string($orig_title) != $entry_title) {
                    $post_needs_update = true;
                    $update_insignificant = false;
                }

                // if post needs update, update it and mark all user entries
                // linking to this post as updated
                if ($post_needs_update) {

                    if (defined('DAEMON_EXTENDED_DEBUG')) {
                        _debug("post $entry_guid_hashed needs update...");
                    }

                    \SmallSmallRSS\Database::query(
                        "UPDATE ttrss_entries
                            SET title = '$entry_title',
                                content = '$entry_content',
                                content_hash = '$content_hash',
                                updated = '$entry_timestamp_fmt',
                                num_comments = '$num_comments',
                                plugin_data = '$entry_plugin_data'
                            WHERE id = '$ref_id'"
                    );
                    if (!$update_insignificant &&$mark_unread_on_update) {
                        \SmallSmallRSS\UserEntries::markUnread($ref_id);
                    }
                }
            }
            \SmallSmallRSS\Database::query("COMMIT");
            \SmallSmallRSS\ArticleFilters::assignArticleToLabel(
                $entry_ref_id,
                $article_filters,
                $owner_uid,
                $article_labels
            );
            // enclosures
            $enclosures = array();
            $encs = $item->get_enclosures();
            if (is_array($encs)) {
                foreach ($encs as $e) {
                    $e_item = array($e->link, $e->type, $e->length);
                    array_push($enclosures, $e_item);
                }
            }
            \SmallSmallRSS\Enclosures::add($enclosures);
            // check for manual tags (we have to do it here since they're loaded from filters)
            foreach ($article_filters as $f) {
                if ($f["type"] == "tag") {
                    $manual_tags = trim_array(explode(",", $f["param"]));
                    foreach ($manual_tags as $tag) {
                        if (tag_is_valid($tag)) {
                            array_push($entry_tags, $tag);
                        }
                    }
                }
            }

            // Skip boring tags
            $boring_tags = trim_array(
                explode(
                    ",",
                    mb_strtolower(
                        \SmallSmallRSS\DBPrefs::read(
                            'BLACKLISTED_TAGS',
                            $owner_uid,
                            ''
                        ),
                        'utf-8'
                    )
                )
            );

            $filtered_tags = array();
            $tags_to_cache = array();

            if ($entry_tags && is_array($entry_tags)) {
                foreach ($entry_tags as $tag) {
                    if (array_search($tag, $boring_tags) === false) {
                        array_push($filtered_tags, $tag);
                    }
                }
            }

            $filtered_tags = array_unique($filtered_tags);

            // Save article tags in the database
            if (count($filtered_tags) > 0) {
                \SmallSmallRSS\Database::query("BEGIN");
                foreach ($filtered_tags as $tag) {
                    $tag = sanitize_tag($tag);
                    if (!tag_is_valid($tag)) {
                        continue;
                    }
                    $tag = \SmallSmallRSS\Database::escape_string($tag);
                    $result = \SmallSmallRSS\Database::query(
                        "SELECT id
                         FROM ttrss_tags
                         WHERE
                             tag_name = '$tag'
                             AND post_int_id = '$entry_int_id'
                             AND owner_uid = '$owner_uid' LIMIT 1"
                    );

                    if ($result && \SmallSmallRSS\Database::num_rows($result) == 0) {
                        \SmallSmallRSS\Database::query(
                            "INSERT INTO ttrss_tags
                         (owner_uid,tag_name,post_int_id)
                         VALUES ('$owner_uid', '$tag', '$entry_int_id')"
                        );
                    }

                    array_push($tags_to_cache, $tag);
                }

                /* update the cache */

                $tags_to_cache = array_unique($tags_to_cache);

                $tags_str = \SmallSmallRSS\Database::escape_string(join(",", $tags_to_cache));

                \SmallSmallRSS\Database::query(
                    "UPDATE ttrss_user_entries
                 SET tag_cache = '$tags_str'
                 WHERE ref_id = '$entry_ref_id'
                 AND owner_uid = $owner_uid"
                );

                \SmallSmallRSS\Database::query("COMMIT");
            }

            if (\SmallSmallRSS\DBPrefs::read("AUTO_ASSIGN_LABELS", $owner_uid, false)) {
                foreach ($labels as $label) {
                    $caption = preg_quote($label["caption"]);
                    if (strlen($caption)) {
                        $matched = preg_match(
                            "/\b$caption\b/i",
                            "$tags_str " . strip_tags($entry_content) . " $entry_title"
                        );
                        if ($matched && !\SmallSmallRSS\Labels::containsCaption($article_labels, $caption)) {
                            \SmallSmallRSS\Labels::addArticle($entry_ref_id, $caption, $owner_uid);
                        }
                    }
                }
            }
        }

        if (!$last_updated) {
            \SmallSmallRSS\UserEntries::catchupFeed($feed, false, $owner_uid);
        }

        \SmallSmallRSS\Feeds::purge($feed);
        \SmallSmallRSS\Feeds::markUpdated($feed);
        unset($rss);
    }

    public static function makeGUIDFromTitle($title)
    {
        return preg_replace(
            "/[ \"\',.:;]/",
            "-",
            mb_strtolower(strip_tags($title), 'utf-8')
        );
    }
}
