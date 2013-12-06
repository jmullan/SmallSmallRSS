<?php
namespace SmallSmallRSS\Handlers;

class API extends Handler
{

    const API_LEVEL = 7;
    const STATUS_OK = 0;
    const STATUS_ERR = 1;

    private $seq;

    public function before($method)
    {
        if (parent::before($method)) {
            header('Content-Type: application/json');
            if (empty($_SESSION['uid']) && $method != 'login' && $method != 'isloggedin') {
                $this->wrap(self::STATUS_ERR, array('error' => 'NOT_LOGGED_IN'));
                return false;
            }

            if (!empty($_SESSION['uid']) && $method != 'logout' && !\SmallSmallRSS\DBPrefs::read('ENABLE_API_ACCESS')) {
                $this->wrap(self::STATUS_ERR, array('error' => 'API_DISABLED'));
                return false;
            }
            $this->seq = (int) (isset($_REQUEST['seq']) ? $_REQUEST['seq'] : 0);
            return true;
        }
        return false;
    }

    public function wrap($status, $reply)
    {
        print json_encode(array('seq' => $this->seq, 'status' => $status, 'content' => $reply));
    }

    public function getVersion()
    {
        $rv = array('version' => VERSION);
        $this->wrap(self::STATUS_OK, $rv);
    }

    public function getApiLevel()
    {
        $rv = array('level' => self::API_LEVEL);
        $this->wrap(self::STATUS_OK, $rv);
    }

    public function login()
    {
        $login = $_REQUEST['user'];
        $password = $_REQUEST['password'];
        $password_base64 = base64_decode($password);
        if (\SmallSmallRSS\Auth::is_single_user_mode()) {
            $login = 'admin';
        }
        $uid = \SmallSmallRSS\Users::findUserByLogin($login);
        if (!$uid) {
            \SmallSmallRss\Logger::log("Could not find user: '$login'");
            $this->wrap(self::STATUS_ERR, array('error' => 'LOGIN_ERROR'));
            return;
        }
        if (!\SmallSmallRSS\DBPrefs::read('ENABLE_API_ACCESS', $uid)) {
            \SmallSmallRss\Logger::log("Api access disabled for: '$login'");
            $this->wrap(self::STATUS_ERR, array('error' => 'API_DISABLED'));
            return;
        }

        session_set_cookie_params(\SmallSmallRSS\Config::get('SESSION_COOKIE_LIFETIME'));
        if (authenticate_user($login, $password)) {
            // try login with normal password
            $this->wrap(self::STATUS_OK, array('session_id' => session_id(),
                                               'api_level' => self::API_LEVEL));
        } elseif (authenticate_user($login, $password_base64)) {
            // else try with base64_decoded password
            $this->wrap(self::STATUS_OK, array('session_id' => session_id(),
                                               'api_level' => self::API_LEVEL));
        } else {
            \SmallSmallRss\Logger::log("Could not log in: '$login'");
            $this->wrap(self::STATUS_ERR, array('error' => 'LOGIN_ERROR'));
        }
    }

    public function logout()
    {
        \SmallSmallRSS\Sessions::logout();
        $this->wrap(self::STATUS_OK, array('status' => 'OK'));
    }

    public function isLoggedIn()
    {
        $this->wrap(self::STATUS_OK, array('status' => $_SESSION['uid'] != ''));
    }

    public function getUnread()
    {
        $feed_id = $this->getSQLEscapedStringFromRequest('feed_id');
        $is_cat = $this->getSQLEscapedStringFromRequest('is_cat');

        if ($feed_id) {
            $this->wrap(
                self::STATUS_OK,
                array('unread' => countUnreadFeedArticles($feed_id, $is_cat, true, $_SESSION['uid']))
            );
        } else {
            $this->wrap(
                self::STATUS_OK,
                array('unread' => \SmallSmallRSS\CountersCache::getGlobalUnread($_SESSION['uid']))
            );
        }
    }

    /* Method added for ttrss-reader for Android */
    public function getCounters()
    {
        $this->wrap(self::STATUS_OK, getAllCounters());
    }

    public function getFeeds()
    {
        $cat_id = $this->getSQLEscapedStringFromRequest('cat_id');
        $unread_only = $this->getBooleanFromRequest('unread_only');
        $limit = (int) $this->getSQLEscapedStringFromRequest('limit');
        $offset = (int) $this->getSQLEscapedStringFromRequest('offset');
        $include_nested = $this->getBooleanFromRequest('include_nested');
        $feeds = $this->apiGetFeeds($cat_id, $unread_only, $limit, $offset, $include_nested);
        $this->wrap(self::STATUS_OK, $feeds);
    }

    public function getCategories()
    {
        $unread_only = $this->getBooleanFromRequest('unread_only');
        $enable_nested = $this->getBooleanFromRequest('enable_nested');
        $include_empty = $this->getBooleanFromRequest('include_empty');

        // TODO do not return empty categories, return Uncategorized and standard virtual cats

        if ($enable_nested) {
            $nested_qpart = 'parent_cat IS NULL';
        } else {
            $nested_qpart = 'true';
        }

        $result = \SmallSmallRSS\Database::query(
            "SELECT
                 id,
                 title,
                 order_id,
                 (
                     SELECT COUNT(id)
                     FROM ttrss_feeds
                     WHERE
                         ttrss_feed_categories.id IS NOT NULL
                         AND cat_id = ttrss_feed_categories.id
                 ) AS num_feeds,
                 (
                     SELECT COUNT(id)
                     FROM ttrss_feed_categories AS c2
                     WHERE c2.parent_cat = ttrss_feed_categories.id
                 ) AS num_cats
             FROM ttrss_feed_categories
             WHERE
                 $nested_qpart
                 AND owner_uid = " . $_SESSION['uid']
        );
        $cats = array();
        while ($line = \SmallSmallRSS\Database::fetch_assoc($result)) {
            if ($include_empty || $line['num_feeds'] > 0 || $line['num_cats'] > 0) {
                $unread = countUnreadFeedArticles($line['id'], true, true, $_SESSION['uid']);
                if ($enable_nested) {
                    $unread += getCategoryChildrenUnread($line['id']);
                }
                if ($unread || !$unread_only) {
                    $cats[] = array(
                        'id' => $line['id'],
                        'title' => $line['title'],
                        'unread' => $unread,
                        'order_id' => (int) $line['order_id'],
                    );
                }
            }
        }
        foreach (array(-2, -1, 0) as $cat_id) {
            if ($include_empty || !$this->isCategoryEmpty($cat_id)) {
                $unread = countUnreadFeedArticles($cat_id, true, true, $_SESSION['uid']);
                if ($unread || !$unread_only) {
                    $cats[] = array(
                        'id' => $cat_id,
                        'title' => \SmallSmallRSS\FeedCategories::getTitle($cat_id),
                        'unread' => $unread
                    );
                }
            }
        }
        $this->wrap(self::STATUS_OK, $cats);
    }

    public function getHeadlines()
    {
        $feed_id = $this->getSQLEscapedStringFromRequest('feed_id');
        if ($feed_id != '') {
            $limit = (int) $this->getSQLEscapedStringFromRequest('limit');
            if (!$limit || $limit >= 200) {
                $limit = 200;
            }
            $offset = (int) $this->getSQLEscapedStringFromRequest('skip');
            $filter = $this->getSQLEscapedStringFromRequest('filter');

            $is_cat = $this->getBooleanFromRequest('is_cat');
            $show_excerpt = $this->getBooleanFromRequest('show_excerpt');
            $show_content = $this->getBooleanFromRequest('show_content');

            /* all_articles, unread, adaptive, marked, updated */
            $view_mode = $this->getSQLEscapedStringFromRequest('view_mode');
            $include_attachments = $this->getBooleanFromRequest('include_attachments');
            $since_id = (int) $this->getSQLEscapedStringFromRequest('since_id');
            $include_nested = $this->getBooleanFromRequest('include_nested');
            $sanitize_content = !isset($_REQUEST['sanitize']) || $this->getBooleanFromRequest('sanitize');

            $override_order = false;
            switch ($_REQUEST['order_by']) {
                case 'date_reverse':
                    $override_order = 'date_entered, updated';
                    break;
                case 'feed_dates':
                    $override_order = 'updated DESC';
                    break;
            }

            /* do not rely on params below */
            $search = $this->getSQLEscapedStringFromRequest('search');
            $search_mode = $this->getSQLEscapedStringFromRequest('search_mode');
            $headlines = $this->apiGetHeadlines(
                $feed_id,
                $limit,
                $offset,
                $filter,
                $is_cat,
                $show_excerpt,
                $show_content,
                $view_mode,
                $override_order,
                $include_attachments,
                $since_id,
                $search,
                $search_mode,
                $include_nested,
                $sanitize_content
            );
            $this->wrap(self::STATUS_OK, $headlines);
        } else {
            $this->wrap(self::STATUS_ERR, array('error' => 'INCORRECT_USAGE'));
        }
    }

    public function updateArticle()
    {
        $article_ids = array_filter(explode(',', $this->getSQLEscapedStringFromRequest('article_ids')), 'is_numeric');
        $mode = (int) $this->getSQLEscapedStringFromRequest('mode');
        $data = $this->getSQLEscapedStringFromRequest('data');
        $field_raw = (int) $this->getSQLEscapedStringFromRequest('field');

        $field = '';
        $set_to = '';

        switch ($field_raw) {
            case 0:
                $field = 'marked';
                $additional_fields = ',last_marked = NOW()';
                break;
            case 1:
                $field = 'published';
                $additional_fields = ',last_published = NOW()';
                break;
            case 2:
                $field = 'unread';
                $additional_fields = ',last_read = NOW()';
                break;
            case 3:
                $field = 'note';
        };

        switch ($mode) {
            case 1:
                $set_to = 'true';
                break;
            case 0:
                $set_to = 'false';
                break;
            case 2:
                $set_to = "NOT $field";
                break;
        }

        if ($field == 'note') {
            $set_to = "'$data'";
        }

        if ($field && $set_to && count($article_ids) > 0) {
            $in_article_ids = join(', ', $article_ids);
            $result = \SmallSmallRSS\Database::query(
                "UPDATE ttrss_user_entries
                 SET
                     $field = $set_to
                     $additional_fields
                 WHERE
                     ref_id IN ($in_article_ids)
                     AND owner_uid = " . $_SESSION['uid']
            );

            $num_updated = \SmallSmallRSS\Database::affected_rows($result);
            if ($num_updated > 0 && $field == 'unread') {
                $feed_ids = \SmallSmallRSS\UserEntries::getMatchingFeeds($article_ids);
                foreach ($feed_ids as $feed_id) {
                    \SmallSmallRSS\CountersCache::update($line['feed_id'], $_SESSION['uid']);
                }
            }
            if ($num_updated > 0 && $field == 'published') {
                $hub = \SmallSmallRSS\Config::get('PUBSUBHUBBUB_HUB');
                if ($hub) {
                    $rss_link = (
                        get_self_url_prefix()
                        . '/public.php?op=rss&id=-2&key='
                        . \SmallSmallRSS\AccessKeys::getForFeed(-2, false, $_SESSION['uid'])
                    );
                    $p = new \Pubsubhubbub\Publisher($hub);
                    $pubsub_result = $p->publishUpdate($rss_link);
                }
            }
            $this->wrap(self::STATUS_OK, array('status' => 'OK',
                                               'updated' => $num_updated));

        } else {
            $this->wrap(self::STATUS_ERR, array('error' => 'INCORRECT_USAGE'));
        }

    }

    public function getArticle()
    {
        $article_ids = array_filter(explode(',', $_REQUEST['article_id']), 'is_numeric');
        if ($article_ids) {
            $in_article_ids = join(', ', array_map('intval', $article_ids));
            $substring_for_date = \SmallSmallRSS\Database::getSubstringForDateFunction();
            $query = 'SELECT
                          id,
                          title,
                          link,
                          content,
                          cached_content,
                          feed_id,
                          comments,
                          int_id,
                          marked,unread,published,score,
                          '.$substring_for_date."(updated,1,16) as updated,
                          author,
                          (
                               SELECT title
                               FROM ttrss_feeds WHERE id = feed_id
                          ) AS feed_title
                      FROM ttrss_entries,ttrss_user_entries
                      WHERE
                          id IN ($in_article_ids)
                          AND ref_id = id
                          AND owner_uid = " . $_SESSION['uid'];
            $result = \SmallSmallRSS\Database::query($query);
            $articles = array();
            if (\SmallSmallRSS\Database::num_rows($result) != 0) {
                while ($line = \SmallSmallRSS\Database::fetch_assoc($result)) {
                    $attachments = \SmallSmallRSS\Enclosures::get($line['id']);
                    $article = array(
                        'id' => $line['id'],
                        'title' => $line['title'],
                        'link' => $line['link'],
                        'labels' => \SmallSmallRSS\Labels::getForArticle($line['id'], $_SESSION['uid']),
                        'unread' => \SmallSmallRSS\Database::fromSQLBool($line['unread']),
                        'marked' => \SmallSmallRSS\Database::fromSQLBool($line['marked']),
                        'published' => \SmallSmallRSS\Database::fromSQLBool($line['published']),
                        'comments' => $line['comments'],
                        'author' => $line['author'],
                        'updated' => (int) strtotime($line['updated']),
                        'content' => trim($line['cached_content']) != '' ? $line['cached_content'] : $line['content'],
                        'feed_id' => $line['feed_id'],
                        'attachments' => $attachments,
                        'score' => (int) $line['score'],
                        'feed_title' => $line['feed_title']
                    );
                    $hooks = \SmallSmallRSS\PluginHost::getInstance()->getHooks(
                        \SmallSmallRSS\Hooks::FILTER_ARTICLE_API
                    );
                    foreach ($hooks as $p) {
                        $article = $p->hookFilterArticleApi(array('article' => $article));
                    }
                    if (!$article['content']) {
                        \SmallSmallRSS\Logger::log('No content for article');
                    }
                    $articles[] = $article;
                }
            }
            $this->wrap(self::STATUS_OK, $articles);
        } else {
            $this->wrap(self::STATUS_ERR, array('error' => 'INCORRECT_USAGE'));
        }
    }

    public function getConfig()
    {
        $config = array(
            'icons_dir' => \SmallSmallRSS\Config::get('ICONS_DIR'),
            'icons_url' => \SmallSmallRSS\Config::get('ICONS_URL')
        );
        $config['daemon_is_running'] = \SmallSmallRSS\Lockfiles::is_locked('update_daemon.lock');
        $result = \SmallSmallRSS\Database::query(
            'SELECT COUNT(*) AS cf FROM
            ttrss_feeds WHERE owner_uid = ' . $_SESSION['uid']
        );
        $num_feeds = \SmallSmallRSS\Database::fetch_result($result, 0, 'cf');
        $config['num_feeds'] = (int) $num_feeds;
        $this->wrap(self::STATUS_OK, $config);
    }

    public function updateFeed()
    {
        $feed_id = (int) $this->getSQLEscapedStringFromRequest('feed_id');
        \SmallSmallRSS\RSSUpdater::updateFeed($feed_id);
        $this->wrap(self::STATUS_OK, array('status' => 'OK'));
    }

    public function catchupFeed()
    {
        $feed_id = $this->getSQLEscapedStringFromRequest('feed_id');
        $is_cat = $this->getSQLEscapedStringFromRequest('is_cat');
        \SmallSmallRSS\UserEntries::catchupFeed($feed_id, $is_cat, $_SESSION['uid']);
        $this->wrap(self::STATUS_OK, array('status' => 'OK'));
    }

    public function getPref()
    {
        $pref_name = $this->getSQLEscapedStringFromRequest('pref_name');
        $this->wrap(self::STATUS_OK, array('value' => \SmallSmallRSS\DBPrefs::read($pref_name)));
    }

    public function getLabels()
    {
        $owner_id = $_SESSION['uid'];
        $article_id = (int) $_REQUEST['article_id'];
        $this->wrap(
            self::STATUS_OK,
            \SmallSmallRSS\Labels::getOwnerLabels($owner_id, $article_id)
        );
    }

    public function setArticleLabel()
    {

        $article_ids = array_filter(explode(',', $this->getSQLEscapedStringFromRequest('article_ids')), 'is_numeric');
        $label_id = (int) $this->getSQLEscapedStringFromRequest('label_id');
        $assign = (bool) $this->getSQLEscapedStringFromRequest('assign') == 'true';
        $label = \SmallSmallRSS\Database::escape_string(
            \SmallSmallRSS\Labels::findCaption($label_id, $_SESSION['uid'])
        );
        $num_updated = 0;
        if ($label) {
            foreach ($article_ids as $id) {
                if ($assign) {
                    \SmallSmallRSS\Labels::addArticle($id, $label, $_SESSION['uid']);
                } else {
                    \SmallSmallRSS\Labels::removeArticle($id, $label, $_SESSION['uid']);
                }
                $num_updated += 1;
            }
        }
        $this->wrap(
            self::STATUS_OK,
            array('status' => 'OK', 'updated' => $num_updated)
        );
    }

    public function index($method)
    {
        $plugin = \SmallSmallRSS\PluginHost::getInstance()->get_api_method(strtolower($method));
        if ($plugin && method_exists($plugin, $method)) {
            $reply = $plugin->$method();
            $this->wrap($reply[0], $reply[1]);
        } else {
            $reply = array(
                'methods' => array(
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
                )
            );
            $this->wrap(self::STATUS_OK, $reply);
        }
    }

    public function shareToPublished()
    {
        # TODO: Move escaping into createPublishedArticle
        $title = \SmallSmallRSS\Database::escape_string(strip_tags($_REQUEST['title']));
        $url = \SmallSmallRSS\Database::escape_string(strip_tags($_REQUEST['url']));
        $content = \SmallSmallRSS\Database::escape_string(strip_tags($_REQUEST['content']));
        if (\SmallSmallRSS\Handlers\Article::createPublishedArticle($title, $url, $content, '', $_SESSION['uid'])) {
            $this->wrap(self::STATUS_OK, array('status' => 'OK'));
        } else {
            $this->wrap(self::STATUS_ERR, array('error' => 'Publishing failed'));
        }
    }

    public static function apiGetFeeds($cat_id, $unread_only, $limit, $offset, $include_nested = false)
    {
        $feeds = array();
        /* Labels */
        if ($cat_id == -4 || $cat_id == -2) {
            $counters = getLabelCounters(true);
            foreach (array_values($counters) as $cv) {
                $unread = $cv['counter'];
                if ($unread || !$unread_only) {
                    $row = array(
                        'id' => $cv['id'],
                        'title' => $cv['description'],
                        'unread' => $cv['counter'],
                        'cat_id' => -2,
                    );
                    $feeds[] = $row;
                }
            }
        }

        /* Virtual feeds */
        if ($cat_id == -4 || $cat_id == -1) {
            foreach (array(-1, -2, -3, -4, -6, 0) as $i) {
                $unread = countUnreadFeedArticles($i, false, true, $_SESSION['uid']);
                if ($unread || !$unread_only) {
                    $title = \SmallSmallRSS\Feeds::getTitle($i);
                    $row = array(
                        'id' => $i,
                        'title' => $title,
                        'unread' => $unread,
                        'cat_id' => -1,
                    );
                    $feeds[] = $row;
                }

            }
        }

        /* Child cats */
        if ($include_nested && $cat_id) {
            $result = \SmallSmallRSS\Database::query(
                "SELECT
                    id, title
                 FROM ttrss_feed_categories
                 WHERE
                     parent_cat = '$cat_id'
                     AND owner_uid = " . $_SESSION['uid'] . '
                 ORDER BY id, title'
            );

            while ($line = \SmallSmallRSS\Database::fetch_assoc($result)) {
                $unread = (
                    countUnreadFeedArticles($line['id'], true, true, $_SESSION['uid'])
                    + getCategoryChildrenUnread($line['id'])
                );
                if ($unread || !$unread_only) {
                    $row = array(
                        'id' => $line['id'],
                        'title' => $line['title'],
                        'unread' => $unread,
                        'is_cat' => true,
                    );
                    array_push($feeds, $row);
                }
            }
        }

        /* Real feeds */
        if ($limit) {
            $limit_qpart = "LIMIT $limit OFFSET $offset";
        } else {
            $limit_qpart = '';
        }
        $substring_for_date = \SmallSmallRSS\Database::getSubstringForDateFunction();
        if ($cat_id == -4 || $cat_id == -3) {
            $result = \SmallSmallRSS\Database::query(
                'SELECT
                    id, feed_url, cat_id, title, order_id, ' .
                    $substring_for_date . '(last_updated,1,19) AS last_updated
                 FROM ttrss_feeds
                 WHERE owner_uid = ' . $_SESSION['uid'] . "
                 ORDER BY cat_id, title
                 $limit_qpart"
            );
        } else {
            if ($cat_id) {
                $cat_qpart = "cat_id = '$cat_id'";
            } else {
                $cat_qpart = 'cat_id IS NULL';
            }
            $result = \SmallSmallRSS\Database::query(
                'SELECT
                    id, feed_url, cat_id, title, order_id, '.
                $substring_for_date . "(last_updated,1,19) AS last_updated
                 FROM ttrss_feeds
                 WHERE
                     $cat_qpart
                     AND owner_uid = " . $_SESSION['uid'] . "
                 ORDER BY cat_id, title
                 $limit_qpart"
            );
        }
        while ($line = \SmallSmallRSS\Database::fetch_assoc($result)) {
            $unread = countUnreadFeedArticles($line['id'], false, true, $_SESSION['uid']);
            $has_icon = \SmallSmallRSS\Feeds::hasIcon($line['id']);
            if ($unread || !$unread_only) {
                $row = array(
                    'feed_url' => $line['feed_url'],
                    'title' => $line['title'],
                    'id' => (int) $line['id'],
                    'unread' => (int) $unread,
                    'has_icon' => $has_icon,
                    'cat_id' => (int) $line['cat_id'],
                    'last_updated' => (int) strtotime($line['last_updated']),
                    'order_id' => (int) $line['order_id'],
                );
                array_push($feeds, $row);
            }
        }
        return $feeds;
    }

    public static function apiGetHeadlines(
        $feed_id,
        $limit,
        $offset,
        $filter,
        $is_cat,
        $show_excerpt,
        $show_content,
        $view_mode,
        $order,
        $include_attachments,
        $since_id,
        $search = '',
        $search_mode = '',
        $include_nested = false,
        $sanitize_content = true
    ) {
        $qfh_ret = queryFeedHeadlines(
            $feed_id,
            $limit,
            $view_mode,
            $is_cat,
            $search,
            $search_mode,
            $order,
            $offset,
            0,
            false,
            $since_id,
            $include_nested
        );

        $result = $qfh_ret[0];
        $feed_title = $qfh_ret[1];

        $headlines = array();

        while ($line = \SmallSmallRSS\Database::fetch_assoc($result)) {
            $unread = \SmallSmallRSS\Database::fromSQLBool($line['unread']);
            $is_updated = ($line['last_read'] == '' && !$unread);

            $tags = explode(',', $line['tag_cache']);
            $labels = json_decode($line['label_cache'], true);

            $headline_row = array(
                'id' => (int) $line['id'],
                'unread' => $unread,
                'marked' => \SmallSmallRSS\Database::fromSQLBool($line['marked']),
                'published' => \SmallSmallRSS\Database::fromSQLBool($line['published']),
                'updated' => (int) strtotime($line['updated']),
                'is_updated' => $is_updated,
                'title' => $line['title'],
                'link' => $line['link'],
                'feed_id' => $line['feed_id'],
                'tags' => $tags,
            );

            if ($include_attachments) {
                $headline_row['attachments'] = \SmallSmallRSS\Enclosures::get($line['id']);
            }

            if ($show_excerpt) {
                $excerpt = \SmallSmallRSS\Utils::truncateString(strip_tags($line['content_preview']), 100);
                $headline_row['excerpt'] = $excerpt;
            }

            if ($show_content) {
                if ($line['cached_content'] != '') {
                    $line['content_preview'] = $line['cached_content'];
                }
                if ($sanitize_content) {
                    $headline_row['content'] = sanitize(
                        $line['content_preview'],
                        \SmallSmallRSS\Database::fromSQLBool($line['hide_images']),
                        false,
                        $line['site_url']
                    );
                } else {
                    $headline_row['content'] = $line['content_preview'];
                }
                if (!$headline_row['content']) {
                    \SmallSmallRSS\Logger::log('No content');
                }
            }

            // unify label output to ease parsing
            if ($labels['no-labels'] == 1) {
                $labels = array();
            }

            $headline_row['labels'] = $labels;
            $headline_row['feed_title'] = (
                $line['feed_title']
                ? $line['feed_title']
                : $feed_title
            );
            $headline_row['comments_count'] = (int) $line['num_comments'];
            $headline_row['comments_link'] = $line['comments'];
            $headline_row['always_display_attachments'] = \SmallSmallRSS\Database::fromSQLBool(
                $line['always_display_enclosures']
            );
            $headline_row['author'] = $line['author'];
            $headline_row['score'] = (int) $line['score'];
            $hooks = \SmallSmallRSS\PluginHost::getInstance()->get_hooks(
                \SmallSmallRSS\Hooks::FILTER_ARTICLE_API
            );
            foreach ($hooks as $p) {
                $headline_row = $p->hookFilterArticleApi(array('headline' => $headline_row));
            }
            $headlines[] = $headline_row;
        }
        return $headlines;
    }

    public function unsubscribeFeed()
    {
        $feed_id = (int) $this->getSQLEscapedStringFromRequest('feed_id');
        $result = \SmallSmallRSS\Database::query(
            "SELECT id
             FROM ttrss_feeds
             WHERE
                 id = '$feed_id'
                 AND owner_uid = ".$_SESSION['uid']
        );
        if (\SmallSmallRSS\Database::num_rows($result) != 0) {
            Pref_Feeds::removeFeed($feed_id, $_SESSION['uid']);
            $this->wrap(self::STATUS_OK, array('status' => 'OK'));
        } else {
            $this->wrap(self::STATUS_ERR, array('error' => 'FEED_NOT_FOUND'));
        }
    }

    public function subscribeToFeed()
    {
        $feed_url = $this->getSQLEscapedStringFromRequest('feed_url');
        $category_id = (int) $this->getSQLEscapedStringFromRequest('category_id');
        $login = $this->getSQLEscapedStringFromRequest('login');
        $password = $this->getSQLEscapedStringFromRequest('password');
        if ($feed_url) {
            $rc = subscribe_to_feed($feed_url, $category_id, $login, $password);
            $this->wrap(self::STATUS_OK, array('status' => $rc));
        } else {
            $this->wrap(self::STATUS_ERR, array('error' => 'INCORRECT_USAGE'));
        }
    }

    public function getFeedTree()
    {
        $include_empty = $this->getBooleanFromRequest('include_empty');
        $pf = new Pref_Feeds($_REQUEST);
        $_REQUEST['mode'] = 2;
        $_REQUEST['force_show_empty'] = $include_empty;
        if ($pf) {
            $data = $pf->makefeedtree();
            $this->wrap(self::STATUS_OK, array('categories' => $data));
        } else {
            $this->wrap(self::STATUS_ERR, array('error' => 'UNABLE_TO_INSTANTIATE_OBJECT'));
        }
    }

    // only works for labels or uncategorized for the time being
    private function isCategoryEmpty($id)
    {
        if ($id == -2) {
            $result = \SmallSmallRSS\Database::query(
                'SELECT COUNT(*) AS count
                 FROM ttrss_labels2
                 WHERE owner_uid = ' . $_SESSION['uid']
            );
            return \SmallSmallRSS\Database::fetch_result($result, 0, 'count') == 0;
        } elseif ($id == 0) {
            $result = \SmallSmallRSS\Database::query(
                'SELECT COUNT(*) AS count
                 FROM ttrss_feeds
                 WHERE
                     cat_id IS NULL
                     AND owner_uid = ' . $_SESSION['uid']
            );
            return \SmallSmallRSS\Database::fetch_result($result, 0, 'count') == 0;
        }
        return false;
    }
}
