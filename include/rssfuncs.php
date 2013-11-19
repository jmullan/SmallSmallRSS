<?php
function update_feedbrowser_cache()
{
    $result = \SmallSmallRSS\Database::query(
        "SELECT feed_url, site_url, title, COUNT(id) AS subscribers
         FROM ttrss_feeds
         WHERE (
             SELECT COUNT(id) = 0 FROM ttrss_feeds AS tf
             WHERE tf.feed_url = ttrss_feeds.feed_url
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
    \SmallSmallRSS\Database::query("BEGIN");
    \SmallSmallRSS\Database::query("DELETE FROM ttrss_feedbrowser_cache");

    $count = 0;

    while ($line = \SmallSmallRSS\Database::fetch_assoc($result)) {
        $subscribers = \SmallSmallRSS\Database::escape_string($line["subscribers"]);
        $feed_url = \SmallSmallRSS\Database::escape_string($line["feed_url"]);
        $title = \SmallSmallRSS\Database::escape_string($line["title"]);
        $site_url = \SmallSmallRSS\Database::escape_string($line["site_url"]);

        $tmp_result = \SmallSmallRSS\Database::query(
            "SELECT subscribers FROM
                ttrss_feedbrowser_cache WHERE feed_url = '$feed_url'"
        );

        if (\SmallSmallRSS\Database::num_rows($tmp_result) == 0) {
            \SmallSmallRSS\Database::query(
                "INSERT INTO ttrss_feedbrowser_cache
                 (feed_url, site_url, title, subscribers)
                 VALUES ('$feed_url', '$site_url', '$title', '$subscribers')"
            );
            $count += 1;
        }
    }
    \SmallSmallRSS\Database::query("COMMIT");
    return $count;
}


/**
 * Update a feed batch.
 * Used by daemons to update n feeds by run.
 * Only update feed needing a update, and not being processed
 * by another process.
 *
 * @param mixed $link Database link
 * @param integer $limit Maximum number of feeds in update batch. Default to \SmallSmallRSS\Config::get('DAEMON_FEED_LIMIT').
 * @param boolean $from_http Set to true if you call this function from http to disable cli specific code.
 * @param boolean $debug Set to false to disable debug output. Default to true.
 * @return void
 */
function update_daemon_common($limit = null, $from_http = false, $debug = true)
{
    if (is_null($limit)) {
        $limit = \SmallSmallRSS\Config::get('DAEMON_FEED_LIMIT');
    }
    // Process all other feeds using last_updated and interval parameters
    \SmallSmallRSS\Sanity::schemaOrDie();
    // Test if the user has loggued in recently. If not, it does not update its feeds.
    if (!\SmallSmallRSS\Auth::is_single_user_mode() && \SmallSmallRSS\Config::get('DAEMON_UPDATE_LOGIN_LIMIT') > 0) {
        if (\SmallSmallRSS\Config::get('DB_TYPE') == "pgsql") {
            $login_thresh_qpart = (
                "AND ttrss_users.last_login >= NOW() - INTERVAL"
                . " '" . \SmallSmallRSS\Config::get('DAEMON_UPDATE_LOGIN_LIMIT') . " days'"
            );
        } else {
            $login_thresh_qpart = (
                "AND ttrss_users.last_login >= DATE_SUB("
                . " NOW(), INTERVAL " . \SmallSmallRSS\Config::get('DAEMON_UPDATE_LOGIN_LIMIT') . " DAY"
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

    $query = "SELECT DISTINCT ttrss_feeds.feed_url, ttrss_feeds.last_updated
              FROM ttrss_feeds, ttrss_users, ttrss_user_prefs
              WHERE
                  ttrss_feeds.owner_uid = ttrss_users.id
                  AND ttrss_users.id = ttrss_user_prefs.owner_uid
                  AND ttrss_user_prefs.pref_name = 'DEFAULT_UPDATE_INTERVAL'
                  $login_thresh_qpart $update_limit_qpart
                  $updstart_thresh_qpart
              ORDER BY last_updated
              $query_limit";
    // We search for feed needing update.
    $result = \SmallSmallRSS\Database::query($query);

    _debug(sprintf("Scheduled %d feeds to update...", \SmallSmallRSS\Database::num_rows($result)), $debug);

    // Here is a little cache magic in order to minimize risk of double feed updates.
    $feeds_to_update = array();
    while ($line = \SmallSmallRSS\Database::fetch_assoc($result)) {
        array_push($feeds_to_update, \SmallSmallRSS\Database::escape_string($line['feed_url']));
    }

    // We update the feed last update started date before anything else.
    // There is no lag due to feed contents downloads
    // It prevents another process to update the same feed.

    if (count($feeds_to_update) > 0) {
        $feeds_quoted = array();
        foreach ($feeds_to_update as $feed) {
            array_push($feeds_quoted, "'" . \SmallSmallRSS\Database::escape_string($feed) . "'");
        }
        \SmallSmallRSS\Database::query(sprintf(
            "UPDATE ttrss_feeds SET last_update_started = NOW()
             WHERE feed_url IN (%s)", implode(',', $feeds_quoted)
        ));
    }

    $nf = 0;

    // For each feed, we call the feed update function.
    foreach ($feeds_to_update as $feed) {
        $query = "SELECT DISTINCT
                      ttrss_feeds.id,
                      last_updated,
                      ttrss_feeds.owner_uid
                  FROM ttrss_feeds, ttrss_users, ttrss_user_prefs
                  WHERE
                      ttrss_user_prefs.owner_uid = ttrss_feeds.owner_uid
                      AND ttrss_users.id = ttrss_user_prefs.owner_uid
                      AND ttrss_user_prefs.pref_name = 'DEFAULT_UPDATE_INTERVAL'
                      AND feed_url = '" . \SmallSmallRSS\Database::escape_string($feed) . "'
                      AND (
                          ttrss_feeds.update_interval > 0
                          OR ttrss_user_prefs.value != '-1'
                      )
                  $login_thresh_qpart
                  ORDER BY ttrss_feeds.id
                  $query_limit";
        $tmp_result = \SmallSmallRSS\Database::query($query);
        if (\SmallSmallRSS\Database::num_rows($tmp_result) > 0) {
            while ($tline = \SmallSmallRSS\Database::fetch_assoc($tmp_result)) {
                update_rss_feed($tline["id"], true);
                ++$nf;
            }
        }
    }
    \SmallSmallRSS\Digest::send_headlines($debug);
    return $nf;
}

function update_rss_feed($feed, $no_cache = false)
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

    $last_updated = \SmallSmallRSS\Database::fetch_result($result, 0, "last_updated");
    $last_article_timestamp = @strtotime(\SmallSmallRSS\Database::fetch_result($result, 0, "last_article_timestamp"));
    if (defined('_DISABLE_HTTP_304')) {
        $last_article_timestamp = 0;
    }
    $owner_uid = \SmallSmallRSS\Database::fetch_result($result, 0, "owner_uid");
    $mark_unread_on_update = sql_bool_to_bool(\SmallSmallRSS\Database::fetch_result(
        $result,
        0, "mark_unread_on_update"
    ));
    $pubsub_state = \SmallSmallRSS\Database::fetch_result($result, 0, "pubsub_state");
    $auth_pass_encrypted = sql_bool_to_bool(\SmallSmallRSS\Database::fetch_result(
        $result,
        0, "auth_pass_encrypted"
    ));

    \SmallSmallRSS\Database::query(
        "UPDATE ttrss_feeds
         SET last_update_started = NOW()
         WHERE id = '$feed'"
    );

    $auth_login = \SmallSmallRSS\Database::fetch_result($result, 0, "auth_login");
    $auth_pass = \SmallSmallRSS\Database::fetch_result($result, 0, "auth_pass");

    if ($auth_pass_encrypted) {
        $auth_pass = \SmallSmallRSS\Crypt::de($auth_pass);
    }

    $cache_images = sql_bool_to_bool(\SmallSmallRSS\Database::fetch_result($result, 0, "cache_images"));
    $fetch_url = \SmallSmallRSS\Database::fetch_result($result, 0, "feed_url");

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
        $feed_fetching_hooks = $pluginhost->get_hooks(\SmallSmallRSS\PluginHost::HOOK_FETCH_FEED);
        if (!$feed_fetching_hooks) {
            _debug('No feed fetching hooks');
        }
        foreach ($feed_fetching_hooks as $plugin) {
            if (!$feed_data) {
                $feed_data = $plugin->hook_fetch_feed($feed_data, $fetch_url, $owner_uid, $feed);
            }
        }
        $fetch_last_error = false;
        if (!$feed_data) {
            _debug("fetching [$fetch_url]...");
            _debug("If-Modified-Since: ".gmdate('D, d M Y H:i:s \G\M\T', $last_article_timestamp));
            $fetcher = new \SmallSmallRSS\Fetcher();
            $feed_data = $fetcher->getFileContents(
                $fetch_url, false,
                $auth_login, $auth_pass, false,
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
    $fetched_feed_hooks = $pluginhost->get_hooks(\SmallSmallRSS\PluginHost::HOOK_FEED_FETCHED);
    if (!$fetched_feed_hooks) {
        _debug('No fetched feed hooks');
    }
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
    $pluginhost->run_hooks(\SmallSmallRSS\PluginHost::HOOK_FEED_PARSED, "hook_feed_parsed", $rss);
    if (\SmallSmallRSS\Config::get('DB_TYPE') == "pgsql") {
        $favicon_interval_qpart = "favicon_last_checked < NOW() - INTERVAL '12 hour'";
    } else {
        $favicon_interval_qpart = "favicon_last_checked < DATE_SUB(NOW(), INTERVAL 12 HOUR)";
    }

    $result = \SmallSmallRSS\Database::query(
        "SELECT title,site_url,owner_uid,favicon_avg_color,
             (favicon_last_checked IS NULL OR $favicon_interval_qpart) AS favicon_needs_check
             FROM ttrss_feeds
             WHERE id = '$feed'"
    );

    $registered_title = \SmallSmallRSS\Database::fetch_result($result, 0, "title");
    $orig_site_url = \SmallSmallRSS\Database::fetch_result($result, 0, "site_url");
    $favicon_needs_check = sql_bool_to_bool(\SmallSmallRSS\Database::fetch_result(
        $result, 0,
        "favicon_needs_check"
    ));
    $favicon_avg_color = \SmallSmallRSS\Database::fetch_result($result, 0, "favicon_avg_color");

    $owner_uid = \SmallSmallRSS\Database::fetch_result($result, 0, "owner_uid");

    $site_url = \SmallSmallRSS\Database::escape_string(mb_substr(rewrite_relative_url($fetch_url, $rss->get_link()), 0, 245));

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
        if (file_exists($favicon_file) && function_exists("imagecreatefromstring") && $favicon_avg_color == '') {
            \SmallSmallRSS\Database::query(
                "UPDATE ttrss_feeds SET favicon_avg_color = 'fail' WHERE
                            id = '$feed'"
            );

            $favicon_color = \SmallSmallRSS\Database::escape_string(
                \SmallSmallRSS\Colors::calculateAverage($favicon_file)
            );

            $favicon_colorstring = ",favicon_avg_color = '".$favicon_color."'";
        } elseif ($favicon_avg_color == 'fail') {
            _debug("floicon failed on this file, not trying to recalculate avg color");
        }

        \SmallSmallRSS\Database::query(
            "UPDATE ttrss_feeds SET favicon_last_checked = NOW()
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
        \SmallSmallRSS\Database::query(
            "UPDATE ttrss_feeds
             SET last_updated = NOW(), last_error = ''
             WHERE id = '$feed'"
        );
        return;
    }

    if ($pubsub_state != 2 && \SmallSmallRSS\Config::get('PUBSUBHUBBUB_ENABLED')) {
        _debug("checking for PUSH hub...");
        $feed_hub_url = false;
        $links = $rss->get_links('hub');
        if ($links && is_array($links)) {
            foreach ($links as $l) {
                $feed_hub_url = $l;
                break;
            }
        }
        _debug("feed hub url: $feed_hub_url");
        if ($feed_hub_url && function_exists('curl_init')
            && !ini_get("open_basedir")) {
            require_once 'lib/pubsubhubbub/subscriber.php';
            $callback_url = get_self_url_prefix() .
                "/public.php?op=pubsub&id=$feed";
            $s = new Subscriber($feed_hub_url, $callback_url);
            $rc = $s->subscribe($fetch_url);
            _debug("feed hub url found, subscribe request sent.");
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
            $entry_guid = make_guid_from_title($item->get_title());
        }
        $hooks = $pluginhost->get_hooks(\SmallSmallRSS\PluginHost::HOOK_GUID_FILTER);
        foreach ($hooks as $plugin) {
            $entry_guid = $plugin->hook_guid_filter($item, $entry_guid);
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
        $entry_link = rewrite_relative_url($site_url, $item->get_link());
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

        foreach ($pluginhost->get_hooks(\SmallSmallRSS\PluginHost::HOOK_ARTICLE_FILTER) as $plugin) {
            $article = $plugin->hook_article_filter($article);
        }

        $entry_tags = $article["tags"];
        $entry_guid = \SmallSmallRSS\Database::escape_string($entry_guid);
        $entry_title = \SmallSmallRSS\Database::escape_string($article["title"]);
        $entry_author = \SmallSmallRSS\Database::escape_string($article["author"]);
        $entry_link = \SmallSmallRSS\Database::escape_string($article["link"]);
        $entry_plugin_data = \SmallSmallRSS\Database::escape_string($article["plugin_data"]);
        $entry_content = $article["content"]; // escaped below


        _debug("plugin data: $entry_plugin_data");

        if ($cache_images && is_writable(\SmallSmallRSS\Config::get('CACHE_DIR') . '/images')) {
            cache_images($entry_content, $site_url);
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
                "UPDATE ttrss_entries SET date_updated = NOW()
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

            _debug("base guid found, checking for user record");

            // this will be used below in update handler
            $orig_content_hash = \SmallSmallRSS\Database::fetch_result($result, 0, "content_hash");
            $orig_title = \SmallSmallRSS\Database::fetch_result($result, 0, "title");
            $orig_num_comments = \SmallSmallRSS\Database::fetch_result($result, 0, "num_comments");
            $orig_date_updated = strtotime(\SmallSmallRSS\Database::fetch_result(
                $result,
                0, "date_updated"
            ));
            $orig_plugin_data = \SmallSmallRSS\Database::fetch_result($result, 0, "plugin_data");

            $ref_id = \SmallSmallRSS\Database::fetch_result($result, 0, "id");
            $entry_ref_id = $ref_id;

            /* $stored_guid = \SmallSmallRSS\Database::fetch_result($result, 0, "guid");
               if ($stored_guid != $entry_guid_hashed) {
               if ($debug_enabled) _debug("upgrading compat guid to hashed one");

               \SmallSmallRSS\Database::query("UPDATE ttrss_entries SET guid = '$entry_guid_hashed' WHERE
               id = '$ref_id'");
               } */

            // check for user post link to main table

            // do we allow duplicate posts with same GUID in different feeds?
            if (\SmallSmallRSS\DBPrefs::read("ALLOW_DUPLICATE_POSTS", $owner_uid, false)) {
                $dupcheck_qpart = "AND (feed_id = '$feed' OR feed_id IS NULL)";
            } else {
                $dupcheck_qpart = "";
            }

            /* Collect article tags here so we could filter by them: */

            $article_filters = get_article_filters(
                $filters, $entry_title,
                $entry_content, $entry_link, $entry_timestamp, $entry_author,
                $entry_tags
            );

            if ($debug_enabled) {
                _debug("article filters: ");
                if (count($article_filters) != 0) {
                    print_r($article_filters);
                }
            }

            if (find_article_filter($article_filters, "filter")) {
                \SmallSmallRSS\Database::query("COMMIT"); // close transaction in progress
                continue;
            }

            $score = calculate_article_score($article_filters);

            _debug("initial score: $score");

            $query = "SELECT ref_id, int_id
                      FROM ttrss_user_entries
                      WHERE
                          ref_id = '$ref_id'
                          AND owner_uid = '$owner_uid'
                          $dupcheck_qpart";

            $result = \SmallSmallRSS\Database::query($query);

            // okay it doesn't exist - create user entry
            if (\SmallSmallRSS\Database::num_rows($result) == 0) {

                _debug("user record not found, creating...");

                if ($score >= -500 && !find_article_filter($article_filters, 'catchup')) {
                    $unread = 'true';
                    $last_read_qpart = 'NULL';
                } else {
                    $unread = 'false';
                    $last_read_qpart = 'NOW()';
                }

                if (find_article_filter($article_filters, 'mark') || $score > 1000) {
                    $marked = 'true';
                } else {
                    $marked = 'false';
                }

                if (find_article_filter($article_filters, 'publish')) {
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

                    _debug("N-gram similar results: $ngram_similar");

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
                    $pubsub_result = $p->publish_update($rss_link);
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
                _debug("user record FOUND");

                $entry_ref_id = \SmallSmallRSS\Database::fetch_result($result, 0, "ref_id");
                $entry_int_id = \SmallSmallRSS\Database::fetch_result($result, 0, "int_id");
            }

            _debug("RID: $entry_ref_id, IID: $entry_int_id");

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

                //                        print "<!-- post $orig_title needs update : $post_needs_update -->";

                \SmallSmallRSS\Database::query(
                    "UPDATE ttrss_entries
                            SET title = '$entry_title', content = '$entry_content',
                                content_hash = '$content_hash',
                                updated = '$entry_timestamp_fmt',
                                num_comments = '$num_comments',
                                plugin_data = '$entry_plugin_data'
                            WHERE id = '$ref_id'"
                );

                if (!$update_insignificant) {
                    if ($mark_unread_on_update) {
                        \SmallSmallRSS\Database::query(
                            "UPDATE ttrss_user_entries
                                    SET last_read = null, unread = true WHERE ref_id = '$ref_id'"
                        );
                    }
                }
            }
        }

        \SmallSmallRSS\Database::query("COMMIT");

        _debug("assigning labels...");

        assign_article_to_label_filters(
            $entry_ref_id, $article_filters,
            $owner_uid, $article_labels
        );

        _debug("looking for enclosures...");

        // enclosures

        $enclosures = array();

        $encs = $item->get_enclosures();

        if (is_array($encs)) {
            foreach ($encs as $e) {
                $e_item = array(
                    $e->link, $e->type, $e->length);
                array_push($enclosures, $e_item);
            }
        }

        if ($debug_enabled) {
            _debug("article enclosures:");
            print_r($enclosures);
        }

        \SmallSmallRSS\Database::query("BEGIN");

        foreach ($enclosures as $enc) {
            $enc_url = \SmallSmallRSS\Database::escape_string($enc[0]);
            $enc_type = \SmallSmallRSS\Database::escape_string($enc[1]);
            $enc_dur = \SmallSmallRSS\Database::escape_string($enc[2]);

            $result = \SmallSmallRSS\Database::query(
                "SELECT id FROM ttrss_enclosures
                        WHERE content_url = '$enc_url' AND post_id = '$entry_ref_id'"
            );

            if (\SmallSmallRSS\Database::num_rows($result) == 0) {
                \SmallSmallRSS\Database::query(
                    "INSERT INTO ttrss_enclosures
                            (content_url, content_type, title, duration, post_id) VALUES
                            ('$enc_url', '$enc_type', '', '$enc_dur', '$entry_ref_id')"
                );
            }
        }

        \SmallSmallRSS\Database::query("COMMIT");

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

        $boring_tags = trim_array(explode(",", mb_strtolower(\SmallSmallRSS\DBPrefs::read(
            'BLACKLISTED_TAGS', $owner_uid, ''
        ), 'utf-8')));

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

        if ($debug_enabled) {
            _debug("filtered article tags:");
            print_r($filtered_tags);
        }

        // Save article tags in the database

        if (count($filtered_tags) > 0) {

            \SmallSmallRSS\Database::query("BEGIN");

            foreach ($filtered_tags as $tag) {

                $tag = sanitize_tag($tag);
                $tag = \SmallSmallRSS\Database::escape_string($tag);

                if (!tag_is_valid($tag)) {
                    continue;
                }

                $result = \SmallSmallRSS\Database::query(
                    "SELECT id FROM ttrss_tags
                            WHERE tag_name = '$tag' AND post_int_id = '$entry_int_id' AND
                            owner_uid = '$owner_uid' LIMIT 1"
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
            _debug("auto-assigning labels...");
            foreach ($labels as $label) {
                $caption = preg_quote($label["caption"]);
                if (strlen($caption)) {
                    $matched = preg_match(
                        "/\b$caption\b/i",
                        "$tags_str " . strip_tags($entry_content) . " $entry_title"
                    );
                    if ($matched && !labels_contains_caption($article_labels, $caption)) {
                        \SmallSmallRSS\Labels::addArticle($entry_ref_id, $caption, $owner_uid);
                    }
                }
            }
        }

        _debug("article processed");
    }

    if (!$last_updated) {
        _debug("new feed, catching it up...");
        catchup_feed($feed, false, $owner_uid);
    }

    _debug("purging feed...");
    \SmallSmallRSS\Feeds::purge($feed);
    \SmallSmallRSS\Database::query(
        "UPDATE ttrss_feeds
         SET
             last_updated = NOW(),
             last_error = ''
         WHERE id = '$feed'"
    );
    unset($rss);
    _debug("done");
}

function cache_images($html, $site_url, $debug)
{
    $cache_dir = \SmallSmallRSS\Config::get('CACHE_DIR') . "/images";
    libxml_use_internal_errors(true);
    $charset_hack = '<head><meta http-equiv="Content-Type" content="text/html; charset=utf-8"/></head>';
    $doc = new DOMDocument();
    $doc->loadHTML($charset_hack . $html);
    $xpath = new DOMXPath($doc);
    $entries = $xpath->query('(//img[@src])');
    foreach ($entries as $entry) {
        if ($entry->hasAttribute('src')) {
            $src = rewrite_relative_url($site_url, $entry->getAttribute('src'));
            $local_filename = \SmallSmallRSS\Config::get('CACHE_DIR') . "/images/" . sha1($src) . ".png";
            _debug("cache_images: downloading: $src to $local_filename", $debug, $debug);
            if (!file_exists($local_filename)) {
                $file_content = \SmallSmallRSS\Fetcher::fetch($src);
                if ($file_content && strlen($file_content) > 1024) {
                    file_put_contents($local_filename, $file_content);
                }
            }
            if (file_exists($local_filename)) {
                $entry->setAttribute(
                    'src', \SmallSmallRSS\Config::get('SELF_URL_PATH') . '/image.php?url=' .
                    base64_encode($src)
                );
            }
        }
    }
    $node = $doc->getElementsByTagName('body')->item(0);
    return $doc->saveXML($node);
}

function expire_error_log($debug)
{
    _debug("Removing old error log entries...", $debug, $debug);
    if (\SmallSmallRSS\Config::get('DB_TYPE') == "pgsql") {
        \SmallSmallRSS\Database::query(
            "DELETE FROM ttrss_error_log
             WHERE created_at < NOW() - INTERVAL '7 days'"
        );
    } else {
        \SmallSmallRSS\Database::query(
            "DELETE FROM ttrss_error_log
             WHERE created_at < DATE_SUB(NOW(), INTERVAL 7 DAY)"
        );
    }

}

function expire_lock_files($debug)
{
    \SmallSmallRSS\Lockfiles::unlink_expired($debug);
}

function expire_cached_files($debug)
{
    foreach (array("simplepie", "images", "export", "upload") as $dir) {
        $cache_dir = \SmallSmallRSS\Config::get('CACHE_DIR') . "/$dir";
        $num_deleted = 0;
        if (!is_writable($cache_dir)) {
            _debug("$cache_dir is not writable");
            return;
        }
        $files = glob("$cache_dir/*");
        if ($files) {
            foreach ($files as $file) {
                if (time() - filemtime($file) > 86400*7) {
                    unlink($file);
                    ++$num_deleted;
                }
            }
        }
        _debug("$cache_dir: removed $num_deleted files.", $debug, $debug);
    }
}

/**
 * Source: http://www.php.net/manual/en/function.parse-url.php#104527
 * Returns the url query as associative array
 *
 * @param    string    query
 * @return    array    params
 */
function convertUrlQuery($query)
{
    $queryParts = explode('&', $query);
    $params = array();
    foreach ($queryParts as $param) {
        $item = explode('=', $param);
        $params[$item[0]] = $item[1];
    }
    return $params;
}

function get_article_filters($filters, $title, $content, $link, $timestamp, $author, $tags)
{
    $matches = array();

    foreach ($filters as $filter) {
        $match_any_rule = $filter["match_any_rule"];
        $inverse = $filter["inverse"];
        $filter_match = false;

        foreach ($filter["rules"] as $rule) {
            $match = false;
            $reg_exp = str_replace('/', '\/', $rule["reg_exp"]);
            $rule_inverse = $rule["inverse"];

            if (!$reg_exp) {
                continue;
            }

            switch ($rule["type"]) {
                case "title":
                    $match = @preg_match("/$reg_exp/i", $title);
                    break;
                case "content":
                    // we don't need to deal with multiline regexps
                    $content = preg_replace("/[\r\n\t]/", "", $content);

                    $match = @preg_match("/$reg_exp/i", $content);
                    break;
                case "both":
                    // we don't need to deal with multiline regexps
                    $content = preg_replace("/[\r\n\t]/", "", $content);

                    $match = (@preg_match("/$reg_exp/i", $title) || @preg_match("/$reg_exp/i", $content));
                    break;
                case "link":
                    $match = @preg_match("/$reg_exp/i", $link);
                    break;
                case "author":
                    $match = @preg_match("/$reg_exp/i", $author);
                    break;
                case "tag":
                    foreach ($tags as $tag) {
                        if (@preg_match("/$reg_exp/i", $tag)) {
                            $match = true;
                            break;
                        }
                    }
                    break;
            }

            if ($rule_inverse) {
                $match = !$match;
            }

            if ($match_any_rule) {
                if ($match) {
                    $filter_match = true;
                    break;
                }
            } else {
                $filter_match = $match;
                if (!$match) {
                    break;
                }
            }
        }

        if ($inverse) {
            $filter_match = !$filter_match;
        }

        if ($filter_match) {
            foreach ($filter["actions"] as $action) {
                array_push($matches, $action);
                // if Stop action encountered, perform no further processing
                if ($action["type"] == "stop") {
                    return $matches;
                }
            }
        }
    }
    return $matches;
}

function find_article_filter($filters, $filter_name)
{
    foreach ($filters as $f) {
        if ($f["type"] == $filter_name) {
            return $f;
        };
    }
    return false;
}

function find_article_filters($filters, $filter_name)
{
    $results = array();
    foreach ($filters as $f) {
        if ($f["type"] == $filter_name) {
            array_push($results, $f);
        };
    }
    return $results;
}

function calculate_article_score($filters)
{
    $score = 0;
    foreach ($filters as $f) {
        if ($f["type"] == "score") {
            $score += $f["param"];
        };
    }
    return $score;
}

function labels_contains_caption($labels, $caption)
{
    foreach ($labels as $label) {
        if ($label[1] == $caption) {
            return true;
        }
    }
    return false;
}

function assign_article_to_label_filters($id, $filters, $owner_uid, $article_labels)
{
    foreach ($filters as $f) {
        if ($f["type"] == "label") {
            if (!labels_contains_caption($article_labels, $f["param"])) {
                \SmallSmallRSS\Labels::addArticle($id, $f["param"], $owner_uid);
            }
        }
    }
}

function make_guid_from_title($title)
{
    return preg_replace(
        "/[ \"\',.:;]/",
        "-",
        mb_strtolower(strip_tags($title), 'utf-8')
    );
}

function housekeeping_common($debug)
{
    expire_cached_files($debug);
    expire_lock_files($debug);
    expire_error_log($debug);

    $count = update_feedbrowser_cache();
    _debug("Feedbrowser updated, $count feeds processed.");

    purge_orphans(true);
    $rc = cleanup_tags(14, 50000);

    _debug("Cleaned $rc cached tags.");
}
