<?php
namespace SmallSmallRSS\Handlers;
class API extends Handler
{

    const API_LEVEL = 7;

    const STATUS_OK = 0;
    const STATUS_ERR = 1;

    private $seq;

    function request_sql_bool($key)
    {
        $value = '';
        if (isset($_REQUEST[$key])) {
            $value = $_REQUEST[$key];
        }
        return sql_bool_to_bool($value);
    }

    function escape_from_request($key)
    {
        $value = '';
        if (isset($_REQUEST[$key])) {
            $value = $this->dbh->escape_string($_REQUEST[$key]);
        }
        return $value;
    }

    function before($method)
    {
        if (parent::before($method)) {
            header("Content-Type: application/json");
            if (empty($_SESSION['uid']) && $method != "login" && $method != "isloggedin") {
                $this->wrap(self::STATUS_ERR, array("error" => 'NOT_LOGGED_IN'));
                return false;
            }

            if (!empty($_SESSION["uid"]) && $method != "logout" && !\SmallSmallRSS\DBPrefs::read('ENABLE_API_ACCESS')) {
                $this->wrap(self::STATUS_ERR, array("error" => 'API_DISABLED'));
                return false;
            }

            $this->seq = (int) (isset($_REQUEST['seq']) ? $_REQUEST['seq'] : 0);

            return true;
        }
        return false;
    }

    function wrap($status, $reply)
    {
        print json_encode(array("seq" => $this->seq,
                                "status" => $status,
                                "content" => $reply));
    }

    function getVersion()
    {
        $rv = array("version" => VERSION);
        $this->wrap(self::STATUS_OK, $rv);
    }

    function getApiLevel()
    {
        $rv = array("level" => self::API_LEVEL);
        $this->wrap(self::STATUS_OK, $rv);
    }

    function login()
    {
        $login = $this->escape_from_request("user");
        $password = $_REQUEST["password"];
        $password_base64 = base64_decode($password);
        if (SINGLE_USER_MODE) {
            $login = "admin";
        }

        $result = $this->dbh->query("SELECT id FROM ttrss_users WHERE login = '$login'");
        if ($this->dbh->num_rows($result) != 0) {
            $uid = $this->dbh->fetch_result($result, 0, "id");
        }
        if (!$uid) {
            \SmallSmallRss\Logger::log("Could not find user: '$login'");
            $this->wrap(self::STATUS_ERR, array("error" => "LOGIN_ERROR"));
            return;
        }
        if (!\SmallSmallRSS\DBPrefs::read("ENABLE_API_ACCESS", $uid)) {
            \SmallSmallRss\Logger::log("Api access disabled for: '$login'");
            $this->wrap(self::STATUS_ERR, array("error" => "API_DISABLED"));
            return;
        }

        session_set_cookie_params(SESSION_COOKIE_LIFETIME);
        if (authenticate_user($login, $password)) {
            // try login with normal password
            $this->wrap(self::STATUS_OK, array("session_id" => session_id(),
                                               "api_level" => self::API_LEVEL));
        } elseif (authenticate_user($login, $password_base64)) {
            // else try with base64_decoded password
            $this->wrap(self::STATUS_OK, array("session_id" => session_id(),
                                               "api_level" => self::API_LEVEL));
        } else {
            \SmallSmallRss\Logger::log("Could not log in: '$login'");
            $this->wrap(self::STATUS_ERR, array("error" => "LOGIN_ERROR"));
        }
    }

    function logout()
    {
        logout_user();
        $this->wrap(self::STATUS_OK, array("status" => "OK"));
    }

    function isLoggedIn()
    {
        $this->wrap(self::STATUS_OK, array("status" => $_SESSION["uid"] != ''));
    }

    function getUnread()
    {
        $feed_id = $this->escape_from_request("feed_id");
        $is_cat = $this->escape_from_request("is_cat");

        if ($feed_id) {
            $this->wrap(self::STATUS_OK, array("unread" => getFeedUnread($feed_id, $is_cat)));
        } else {
            $this->wrap(self::STATUS_OK, array("unread" => getGlobalUnread()));
        }
    }

    /* Method added for ttrss-reader for Android */
    function getCounters()
    {
        $this->wrap(self::STATUS_OK, getAllCounters());
    }

    function getFeeds()
    {
        $cat_id = $this->escape_from_request("cat_id");
        $unread_only = $this->request_sql_bool("unread_only");
        $limit = (int) $this->escape_from_request("limit");
        $offset = (int) $this->escape_from_request("offset");
        $include_nested = $this->request_sql_bool("include_nested");

        $feeds = $this->api_get_feeds($cat_id, $unread_only, $limit, $offset, $include_nested);

        $this->wrap(self::STATUS_OK, $feeds);
    }

    function getCategories()
    {
        $unread_only = $this->request_sql_bool("unread_only");
        $enable_nested = $this->request_sql_bool("enable_nested");
        $include_empty = $this->request_sql_bool('include_empty');

        // TODO do not return empty categories, return Uncategorized and standard virtual cats

        if ($enable_nested) {
            $nested_qpart = "parent_cat IS NULL";
        }
        else {
            $nested_qpart = "true";
        }

        $result = $this->dbh->query(
            "SELECT
                id, title, order_id, (SELECT COUNT(id) FROM
                ttrss_feeds WHERE
                ttrss_feed_categories.id IS NOT NULL AND cat_id = ttrss_feed_categories.id) AS num_feeds,
            (SELECT COUNT(id) FROM
                ttrss_feed_categories AS c2 WHERE
                c2.parent_cat = ttrss_feed_categories.id) AS num_cats
            FROM ttrss_feed_categories
            WHERE $nested_qpart AND owner_uid = " .
            $_SESSION["uid"]
        );

        $cats = array();

        while ($line = $this->dbh->fetch_assoc($result)) {
            if ($include_empty || $line["num_feeds"] > 0 || $line["num_cats"] > 0) {
                $unread = getFeedUnread($line["id"], true);

                if ($enable_nested) {
                    $unread += getCategoryChildrenUnread($line["id"]);
                }

                if ($unread || !$unread_only) {
                    array_push($cats, array("id" => $line["id"],
                                            "title" => $line["title"],
                                            "unread" => $unread,
                                            "order_id" => (int) $line["order_id"],
                               ));
                }
            }
        }

        foreach (array(-2, -1, 0) as $cat_id) {
            if ($include_empty || !$this->isCategoryEmpty($cat_id)) {
                $unread = getFeedUnread($cat_id, true);

                if ($unread || !$unread_only) {
                    array_push($cats, array("id" => $cat_id,
                                            "title" => getCategoryTitle($cat_id),
                                            "unread" => $unread));
                }
            }
        }

        $this->wrap(self::STATUS_OK, $cats);
    }

    function getHeadlines()
    {
        $feed_id = $this->escape_from_request("feed_id");
        if ($feed_id != "") {

            $limit = (int) $this->escape_from_request("limit");

            if (!$limit || $limit >= 200) {  $limit = 200;
            }

            $offset = (int) $this->escape_from_request("skip");
            $filter = $this->escape_from_request("filter");
            $is_cat = $this->request_sql_bool("is_cat");
            $show_excerpt = $this->request_sql_bool("show_excerpt");
            $show_content = $this->request_sql_bool("show_content");
            /* all_articles, unread, adaptive, marked, updated */
            $view_mode = $this->escape_from_request("view_mode");
            $include_attachments = $this->request_sql_bool("include_attachments");
            $since_id = (int) $this->escape_from_request("since_id");
            $include_nested = $this->request_sql_bool("include_nested");
            $sanitize_content = !isset($_REQUEST["sanitize"]) ||
                $this->request_sql_bool("sanitize");

            $override_order = false;
            switch ($_REQUEST["order_by"]) {
                case "date_reverse":
                    $override_order = "date_entered, updated";
                    break;
                case "feed_dates":
                    $override_order = "updated DESC";
                    break;
            }

            /* do not rely on params below */

            $search = $this->escape_from_request("search");
            $search_mode = $this->escape_from_request("search_mode");

            $headlines = $this->api_get_headlines(
                $feed_id, $limit, $offset,
                $filter, $is_cat, $show_excerpt, $show_content, $view_mode, $override_order,
                $include_attachments, $since_id, $search, $search_mode,
                $include_nested, $sanitize_content
            );

            $this->wrap(self::STATUS_OK, $headlines);
        } else {
            $this->wrap(self::STATUS_ERR, array("error" => 'INCORRECT_USAGE'));
        }
    }

    function updateArticle()
    {
        $article_ids = array_filter(explode(",", $this->escape_from_request("article_ids")), 'is_numeric');
        $mode = (int) $this->escape_from_request("mode");
        $data = $this->escape_from_request("data");
        $field_raw = (int) $this->escape_from_request("field");

        $field = "";
        $set_to = "";

        switch ($field_raw) {
            case 0:
                $field = "marked";
                $additional_fields = ",last_marked = NOW()";
                break;
            case 1:
                $field = "published";
                $additional_fields = ",last_published = NOW()";
                break;
            case 2:
                $field = "unread";
                $additional_fields = ",last_read = NOW()";
                break;
            case 3:
                $field = "note";
        };

        switch ($mode) {
            case 1:
                $set_to = "true";
                break;
            case 0:
                $set_to = "false";
                break;
            case 2:
                $set_to = "NOT $field";
                break;
        }

        if ($field == "note") {  $set_to = "'$data'";
        }

        if ($field && $set_to && count($article_ids) > 0) {

            $article_ids = join(", ", $article_ids);

            $result = $this->dbh->query("UPDATE ttrss_user_entries SET $field = $set_to $additional_fields WHERE ref_id IN ($article_ids) AND owner_uid = " . $_SESSION["uid"]);

            $num_updated = $this->dbh->affected_rows($result);

            if ($num_updated > 0 && $field == "unread") {
                $result = $this->dbh->query(
                    "SELECT DISTINCT feed_id FROM ttrss_user_entries
                    WHERE ref_id IN ($article_ids)"
                );

                while ($line = $this->dbh->fetch_assoc($result)) {
                    \SmallSmallRSS\CounterCache::update($line["feed_id"], $_SESSION["uid"]);
                }
            }

            if ($num_updated > 0 && $field == "published") {
                if (PUBSUBHUBBUB_HUB) {
                    $rss_link = get_self_url_prefix() .
                        "/public.php?op=rss&id=-2&key=" .
                        get_feed_access_key(-2, false);

                    $p = new Publisher(PUBSUBHUBBUB_HUB);
                    $pubsub_result = $p->publish_update($rss_link);
                }
            }

            $this->wrap(self::STATUS_OK, array("status" => "OK",
                                               "updated" => $num_updated));

        } else {
            $this->wrap(self::STATUS_ERR, array("error" => 'INCORRECT_USAGE'));
        }

    }

    function getArticle()
    {

        $article_id = join(",", array_filter(explode(",", $this->escape_from_request("article_id")), 'is_numeric'));

        if ($article_id) {

            $query = "SELECT id,title,link,content,cached_content,feed_id,comments,int_id,
                marked,unread,published,score,
                ".SUBSTRING_FOR_DATE."(updated,1,16) as updated,
                author,(SELECT title FROM ttrss_feeds WHERE id = feed_id) AS feed_title
                FROM ttrss_entries,ttrss_user_entries
                WHERE    id IN ($article_id) AND ref_id = id AND owner_uid = " .
                $_SESSION["uid"];

            $result = $this->dbh->query($query);

            $articles = array();

            if ($this->dbh->num_rows($result) != 0) {

                while ($line = $this->dbh->fetch_assoc($result)) {

                    $attachments = get_article_enclosures($line['id']);

                    $article = array(
                        "id" => $line["id"],
                        "title" => $line["title"],
                        "link" => $line["link"],
                        "labels" => \SmallSmallRSS\Labels::getForArticle($line['id']),
                        "unread" => sql_bool_to_bool($line["unread"]),
                        "marked" => sql_bool_to_bool($line["marked"]),
                        "published" => sql_bool_to_bool($line["published"]),
                        "comments" => $line["comments"],
                        "author" => $line["author"],
                        "updated" => (int) strtotime($line["updated"]),
                        "content" => $line["cached_content"] != "" ? $line["cached_content"] : $line["content"],
                        "feed_id" => $line["feed_id"],
                        "attachments" => $attachments,
                        "score" => (int) $line["score"],
                        "feed_title" => $line["feed_title"]
                    );

                    foreach (\SmallSmallRSS\PluginHost::getInstance()->get_hooks(\SmallSmallRSS\PluginHost::HOOK_RENDER_ARTICLE_API) as $p) {
                        $article = $p->hook_render_article_api(array("article" => $article));
                    }


                    array_push($articles, $article);

                }
            }

            $this->wrap(self::STATUS_OK, $articles);
        } else {
            $this->wrap(self::STATUS_ERR, array("error" => 'INCORRECT_USAGE'));
        }
    }

    function getConfig()
    {
        $config = array(
            "icons_dir" => ICONS_DIR,
            "icons_url" => ICONS_URL);

        $config["daemon_is_running"] = file_is_locked("update_daemon.lock");

        $result = $this->dbh->query(
            "SELECT COUNT(*) AS cf FROM
            ttrss_feeds WHERE owner_uid = " . $_SESSION["uid"]
        );

        $num_feeds = $this->dbh->fetch_result($result, 0, "cf");

        $config["num_feeds"] = (int) $num_feeds;

        $this->wrap(self::STATUS_OK, $config);
    }

    function updateFeed()
    {
        require_once "include/rssfuncs.php";
        $feed_id = (int) $this->escape_from_request("feed_id");
        update_rss_feed($feed_id);
        $this->wrap(self::STATUS_OK, array("status" => "OK"));
    }

    function catchupFeed()
    {
        $feed_id = $this->escape_from_request("feed_id");
        $is_cat = $this->escape_from_request("is_cat");

        catchup_feed($feed_id, $is_cat);

        $this->wrap(self::STATUS_OK, array("status" => "OK"));
    }

    function getPref()
    {
        $pref_name = $this->escape_from_request("pref_name");

        $this->wrap(self::STATUS_OK, array("value" => \SmallSmallRSS\DBPrefs::read($pref_name)));
    }

    function getLabels()
    {
        $article_id = (int) $_REQUEST['article_id'];

        $rv = array();

        $result = $this->dbh->query(
            "SELECT id, caption, fg_color, bg_color
            FROM ttrss_labels2
            WHERE owner_uid = '".$_SESSION['uid']."' ORDER BY caption"
        );

        if ($article_id) {
            $article_labels = \SmallSmallRSS\Labels::getForArticle($article_id);
        }
        else {
            $article_labels = array();
        }

        while ($line = $this->dbh->fetch_assoc($result)) {

            $checked = false;
            foreach ($article_labels as $al) {
                if ($al[0] == $line['id']) {
                    $checked = true;
                    break;
                }
            }

            array_push($rv, array(
                           "id" => (int) $line['id'],
                           "caption" => $line['caption'],
                           "fg_color" => $line['fg_color'],
                           "bg_color" => $line['bg_color'],
                           "checked" => $checked));
        }

        $this->wrap(self::STATUS_OK, $rv);
    }

    function setArticleLabel()
    {

        $article_ids = array_filter(explode(",", $this->escape_from_request("article_ids")), 'is_numeric');
        $label_id = (int) $this->escape_from_request('label_id');
        $assign = (bool) $this->escape_from_request('assign') == "true";

        $label = $this->dbh->escape_string(\SmallSmallRSS\Labels::findCaption(
            $label_id, $_SESSION["uid"]
        ));

        $num_updated = 0;

        if ($label) {

            foreach ($article_ids as $id) {

                if ($assign) {
                    \SmallSmallRSS\Labels::addArticle($id, $label, $_SESSION["uid"]);
                }
                else {
                    \SmallSmallRSS\Labels::removeArticle($id, $label, $_SESSION["uid"]);
                }

                $num_updated += 1;

            }
        }

        $this->wrap(self::STATUS_OK, array("status" => "OK",
                                           "updated" => $num_updated));

    }

    function index($method)
    {
        $plugin = \SmallSmallRSS\PluginHost::getInstance()->get_api_method(strtolower($method));
        if ($plugin && method_exists($plugin, $method)) {
            $reply = $plugin->$method();
            $this->wrap($reply[0], $reply[1]);
        } else {
            $methods = array(
                'getVersion' => '',
                'getApiLevel' => '',
                'getUnread' => '',
                'getCounters' => '',
                'getFeeds' => '',
                'getCategories' => '',
                'getHeadlines' => '',
                'getArticle' => '',
                'getConfig' => '',
                'getPref' => '',
                'getLabels' => '',
                'getFeedTree' => ''
            );
            $this->wrap(self::STATUS_OK, array('methods' => $methods));
        }
    }

    function shareToPublished()
    {
        $title = $this->dbh->escape_string(strip_tags($_REQUEST["title"]));
        $url = $this->dbh->escape_string(strip_tags($_REQUEST["url"]));
        $content = $this->dbh->escape_string(strip_tags($_REQUEST["content"]));

        if (Article::create_published_article($title, $url, $content, "", $_SESSION["uid"])) {
            $this->wrap(self::STATUS_OK, array("status" => 'OK'));
        } else {
            $this->wrap(self::STATUS_ERR, array("error" => 'Publishing failed'));
        }
    }

    static function api_get_feeds($cat_id, $unread_only, $limit, $offset, $include_nested = false)
    {

        $feeds = array();

        /* Labels */

        if ($cat_id == -4 || $cat_id == -2) {
            $counters = getLabelCounters(true);

            foreach (array_values($counters) as $cv) {

                $unread = $cv["counter"];

                if ($unread || !$unread_only) {

                    $row = array(
                        "id" => $cv["id"],
                        "title" => $cv["description"],
                        "unread" => $cv["counter"],
                        "cat_id" => -2,
                    );

                    array_push($feeds, $row);
                }
            }
        }

        /* Virtual feeds */

        if ($cat_id == -4 || $cat_id == -1) {
            foreach (array(-1, -2, -3, -4, -6, 0) as $i) {
                $unread = getFeedUnread($i);

                if ($unread || !$unread_only) {
                    $title = getFeedTitle($i);

                    $row = array(
                        "id" => $i,
                        "title" => $title,
                        "unread" => $unread,
                        "cat_id" => -1,
                    );
                    array_push($feeds, $row);
                }

            }
        }

        /* Child cats */

        if ($include_nested && $cat_id) {
            $result = \SmallSmallRSS\Database::query(
                "SELECT
                    id, title FROM ttrss_feed_categories
                    WHERE parent_cat = '$cat_id' AND owner_uid = " . $_SESSION["uid"] .
                " ORDER BY id, title"
            );

            while ($line = \SmallSmallRSS\Database::fetch_assoc($result)) {
                $unread = getFeedUnread($line["id"], true) +
                    getCategoryChildrenUnread($line["id"]);

                if ($unread || !$unread_only) {
                    $row = array(
                        "id" => $line["id"],
                        "title" => $line["title"],
                        "unread" => $unread,
                        "is_cat" => true,
                    );
                    array_push($feeds, $row);
                }
            }
        }

        /* Real feeds */

        if ($limit) {
            $limit_qpart = "LIMIT $limit OFFSET $offset";
        } else {
            $limit_qpart = "";
        }

        if ($cat_id == -4 || $cat_id == -3) {
            $result = \SmallSmallRSS\Database::query(
                "SELECT
                    id, feed_url, cat_id, title, order_id, ".
                SUBSTRING_FOR_DATE."(last_updated,1,19) AS last_updated
                        FROM ttrss_feeds WHERE owner_uid = " . $_SESSION["uid"] .
                " ORDER BY cat_id, title " . $limit_qpart
            );
        } else {

            if ($cat_id) {
                $cat_qpart = "cat_id = '$cat_id'";
            }
            else {
                $cat_qpart = "cat_id IS NULL";
            }

            $result = \SmallSmallRSS\Database::query(
                "SELECT
                    id, feed_url, cat_id, title, order_id, ".
                SUBSTRING_FOR_DATE."(last_updated,1,19) AS last_updated
                        FROM ttrss_feeds WHERE
                        $cat_qpart AND owner_uid = " . $_SESSION["uid"] .
                " ORDER BY cat_id, title " . $limit_qpart
            );
        }

        while ($line = \SmallSmallRSS\Database::fetch_assoc($result)) {

            $unread = getFeedUnread($line["id"]);

            $has_icon = feed_has_icon($line['id']);

            if ($unread || !$unread_only) {

                $row = array(
                    "feed_url" => $line["feed_url"],
                    "title" => $line["title"],
                    "id" => (int) $line["id"],
                    "unread" => (int) $unread,
                    "has_icon" => $has_icon,
                    "cat_id" => (int) $line["cat_id"],
                    "last_updated" => (int) strtotime($line["last_updated"]),
                    "order_id" => (int) $line["order_id"],
                );

                array_push($feeds, $row);
            }
        }

        return $feeds;
    }

    static function api_get_headlines($feed_id, $limit, $offset,
                                      $filter, $is_cat, $show_excerpt, $show_content, $view_mode, $order,
                                      $include_attachments, $since_id,
                                      $search = "", $search_mode = "",
                                      $include_nested = false, $sanitize_content = true) {

        $qfh_ret = queryFeedHeadlines(
            $feed_id, $limit,
            $view_mode, $is_cat, $search, $search_mode,
            $order, $offset, 0, false, $since_id, $include_nested
        );

        $result = $qfh_ret[0];
        $feed_title = $qfh_ret[1];

        $headlines = array();

        while ($line = \SmallSmallRSS\Database::fetch_assoc($result)) {
            $is_updated = ($line["last_read"] == "" &&
                           ($line["unread"] != "t" && $line["unread"] != "1"));

            $tags = explode(",", $line["tag_cache"]);
            $labels = json_decode($line["label_cache"], true);

            //if (!$tags) $tags = get_article_tags($line["id"]);
            //if (!$labels) $labels = \SmallSmallRSS\Labels::getForArticle($line["id"]);

            $headline_row = array(
                "id" => (int) $line["id"],
                "unread" => sql_bool_to_bool($line["unread"]),
                "marked" => sql_bool_to_bool($line["marked"]),
                "published" => sql_bool_to_bool($line["published"]),
                "updated" => (int) strtotime($line["updated"]),
                "is_updated" => $is_updated,
                "title" => $line["title"],
                "link" => $line["link"],
                "feed_id" => $line["feed_id"],
                "tags" => $tags,
            );

            if ($include_attachments) {
                $headline_row['attachments'] = get_article_enclosures(
                    $line['id']
                );
            }

            if ($show_excerpt) {
                $excerpt = truncate_string(strip_tags($line["content_preview"]), 100);
                $headline_row["excerpt"] = $excerpt;
            }

            if ($show_content) {

                if ($line["cached_content"] != "") {
                    $line["content_preview"] =& $line["cached_content"];
                }

                if ($sanitize_content) {
                    $headline_row["content"] = sanitize(
                        $line["content_preview"],
                        sql_bool_to_bool($line['hide_images']),
                        false, $line["site_url"]
                    );
                } else {
                    $headline_row["content"] = $line["content_preview"];
                }
            }

            // unify label output to ease parsing
            if ($labels["no-labels"] == 1) {  $labels = array();
            }

            $headline_row["labels"] = $labels;

            $headline_row["feed_title"] = $line["feed_title"] ? $line["feed_title"] :
                $feed_title;

            $headline_row["comments_count"] = (int) $line["num_comments"];
            $headline_row["comments_link"] = $line["comments"];

            $headline_row["always_display_attachments"] = sql_bool_to_bool($line["always_display_enclosures"]);

            $headline_row["author"] = $line["author"];
            $headline_row["score"] = (int) $line["score"];

            foreach (\SmallSmallRSS\PluginHost::getInstance()->get_hooks(\SmallSmallRSS\PluginHost::HOOK_RENDER_ARTICLE_API) as $p) {
                $headline_row = $p->hook_render_article_api(array("headline" => $headline_row));
            }

            array_push($headlines, $headline_row);
        }

        return $headlines;
    }

    function unsubscribeFeed()
    {
        $feed_id = (int) $this->escape_from_request("feed_id");

        $result = $this->dbh->query(
            "SELECT id FROM ttrss_feeds WHERE
            id = '$feed_id' AND owner_uid = ".$_SESSION["uid"]
        );

        if ($this->dbh->num_rows($result) != 0) {
            Pref_Feeds::remove_feed($feed_id, $_SESSION["uid"]);
            $this->wrap(self::STATUS_OK, array("status" => "OK"));
        } else {
            $this->wrap(self::STATUS_ERR, array("error" => "FEED_NOT_FOUND"));
        }
    }

    function subscribeToFeed()
    {
        $feed_url = $this->escape_from_request("feed_url");
        $category_id = (int) $this->escape_from_request("category_id");
        $login = $this->escape_from_request("login");
        $password = $this->escape_from_request("password");

        if ($feed_url) {
            $rc = subscribe_to_feed($feed_url, $category_id, $login, $password);

            $this->wrap(self::STATUS_OK, array("status" => $rc));
        } else {
            $this->wrap(self::STATUS_ERR, array("error" => 'INCORRECT_USAGE'));
        }
    }

    function getFeedTree()
    {
        $include_empty = $this->request_sql_bool('include_empty');

        $pf = new Pref_Feeds($_REQUEST);

        $_REQUEST['mode'] = 2;
        $_REQUEST['force_show_empty'] = $include_empty;

        if ($pf) {
            $data = $pf->makefeedtree();
            $this->wrap(self::STATUS_OK, array("categories" => $data));
        } else {
            $this->wrap(self::STATUS_ERR, array("error" =>
                                                'UNABLE_TO_INSTANTIATE_OBJECT'));
        }

    }

    // only works for labels or uncategorized for the time being
    private function isCategoryEmpty($id)
    {

        if ($id == -2) {
            $result = $this->dbh->query(
                "SELECT COUNT(*) AS count FROM ttrss_labels2
                WHERE owner_uid = " . $_SESSION["uid"]
            );

            return $this->dbh->fetch_result($result, 0, "count") == 0;

        } elseif ($id == 0) {
            $result = $this->dbh->query(
                "SELECT COUNT(*) AS count FROM ttrss_feeds
                WHERE cat_id IS NULL AND owner_uid = " . $_SESSION["uid"]
            );

            return $this->dbh->fetch_result($result, 0, "count") == 0;

        }

        return false;
    }
}
