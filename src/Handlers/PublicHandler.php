<?php
namespace SmallSmallRSS\Handlers;

class PublicHandler extends Handler
{

    private function generateSyndicatedFeed(
        $owner_uid,
        $feed,
        $is_cat,
        $limit,
        $offset,
        $search,
        $search_mode,
        $view_mode = false,
        $format = 'atom',
        $order = false
    ) {
        if (!$limit) {
            $limit = 60;
        }

        $date_sort_field = 'date_entered DESC, updated DESC';
        $date_check_field = 'date_entered';

        if ($feed == -2 && !$is_cat) {
            $date_sort_field = 'last_published DESC';
            $date_check_field = 'last_published';
        } elseif ($feed == -1 && !$is_cat) {
            $date_sort_field = 'last_marked DESC';
            $date_check_field = 'last_marked';
        }

        switch ($order) {
            case 'title':
                $date_sort_field = 'ttrss_entries.title';
                break;
            case 'date_reverse':
                $date_sort_field = 'date_entered, updated';
                break;
            case 'feed_dates':
                $date_sort_field = 'updated DESC';
                break;
        }

        $qfh_ret = queryFeedHeadlines(
            $feed,
            1,
            $view_mode,
            $is_cat,
            $search,
            $search_mode,
            $date_sort_field,
            $offset,
            $owner_uid,
            false,
            0,
            false,
            true
        );

        $result = $qfh_ret[0];

        if (\SmallSmallRSS\Database::numRows($result) != 0) {
            $ts = strtotime(\SmallSmallRSS\Database::fetchResult($result, 0, $date_check_field));
            if (isset($_SERVER['HTTP_IF_MODIFIED_SINCE'])
                && strtotime($_SERVER['HTTP_IF_MODIFIED_SINCE']) >= $ts) {
                header('HTTP/1.0 304 Not Modified');
                return;
            }

            $last_modified = gmdate('D, d M Y H:i:s', $ts) . ' GMT';
            header("Last-Modified: $last_modified", true);
        }

        $qfh_ret = queryFeedHeadlines(
            $feed,
            $limit,
            $view_mode,
            $is_cat,
            $search,
            $search_mode,
            $date_sort_field,
            $offset,
            $owner_uid,
            false,
            0,
            false,
            true
        );


        $result = $qfh_ret[0];
        $feed_title = htmlspecialchars($qfh_ret[1]);
        $feed_site_url = $qfh_ret[2];
        $last_error = $qfh_ret[3];

        $feed_self_url = (
            get_self_url_prefix()
            . '/public.php?op=rss&id=-2&key='
            . \SmallSmallRSS\AccessKeys::getForFeed(-2, false, $owner_uid)
        );

        if (!$feed_site_url) {
            $feed_site_url = get_self_url_prefix();
        }

        if ($format == 'atom') {
            $tpl = new \MiniTemplator\Engine();

            $tpl->readTemplateFromFile('templates/generated_feed.txt');

            $tpl->setVariable('FEED_TITLE', $feed_title, true);
            $tpl->setVariable('VERSION', VERSION, true);
            $tpl->setVariable('FEED_URL', htmlspecialchars($feed_self_url), true);

            if (\SmallSmallRSS\Config::get('PUBSUBHUBBUB_HUB') && $feed == -2) {
                $tpl->setVariable('HUB_URL', htmlspecialchars(\SmallSmallRSS\Config::get('PUBSUBHUBBUB_HUB')), true);
                $tpl->addBlock('feed_hub');
            }

            $tpl->setVariable('SELF_URL', htmlspecialchars(get_self_url_prefix()), true);

            while ($line = \SmallSmallRSS\Database::fetchAssoc($result)) {
                $tpl->setVariable('ARTICLE_ID', htmlspecialchars($line['link']), true);
                $tpl->setVariable('ARTICLE_LINK', htmlspecialchars($line['link']), true);
                $tpl->setVariable('ARTICLE_TITLE', htmlspecialchars($line['title']), true);
                $tpl->setVariable(
                    'ARTICLE_EXCERPT',
                    \SmallSmallRSS\Utils::truncateString(strip_tags($line['content_preview']), 100, '...'),
                    true
                );

                $content = sanitize($line['content_preview'], $owner_uid);
                $note_style = (
                    'background-color: #fff7d5;
                     border-width: 1px;
                     padding: 5px;
                     border-style: dashed;
                     border-color: #e7d796;
                     margin-bottom: 1em;
                     color: #9a8c59;'
                );
                if ($line['note']) {
                    $content = "<div style=\"$note_style\">Article note: " . $line['note'] . '</div>' .
                        $content;
                    $tpl->setVariable('ARTICLE_NOTE', htmlspecialchars($line['note']), true);
                }

                $tpl->setVariable('ARTICLE_CONTENT', $content, true);

                $tpl->setVariable(
                    'ARTICLE_UPDATED_ATOM',
                    date('c', strtotime($line['updated'])),
                    true
                );
                $tpl->setVariable(
                    'ARTICLE_UPDATED_RFC822',
                    date(DATE_RFC822, strtotime($line['updated'])),
                    true
                );

                $tpl->setVariable('ARTICLE_AUTHOR', htmlspecialchars($line['author']), true);

                $tags = get_article_tags($line['id'], $owner_uid);

                foreach ($tags as $tag) {
                    $tpl->setVariable('ARTICLE_CATEGORY', htmlspecialchars($tag), true);
                    $tpl->addBlock('category');
                }

                $enclosures = \SmallSmallRSS\Enclosures::get($line['id']);

                foreach ($enclosures as $e) {
                    $type = htmlspecialchars($e['content_type']);
                    $url = htmlspecialchars($e['content_url']);
                    $length = $e['duration'];

                    $tpl->setVariable('ARTICLE_ENCLOSURE_URL', $url, true);
                    $tpl->setVariable('ARTICLE_ENCLOSURE_TYPE', $type, true);
                    $tpl->setVariable('ARTICLE_ENCLOSURE_LENGTH', $length, true);

                    $tpl->addBlock('enclosure');
                }

                $tpl->addBlock('entry');
            }

            $tmp = '';

            $tpl->addBlock('feed');
            $tpl->generateOutputToString($tmp);

            if (@!$_REQUEST['noxml']) {
                header('Content-Type: text/xml; charset=utf-8');
            } else {
                header('Content-Type: text/plain; charset=utf-8');
            }
            echo $tmp;
        } elseif ($format == 'json') {
            $feed = array();
            $feed['article_count'] = 0;
            $feed['title'] = $feed_title;
            $feed['version'] = VERSION;
            $feed['feed_url'] = $feed_self_url;
            if (\SmallSmallRSS\Config::get('PUBSUBHUBBUB_HUB') && $feed == -2) {
                $feed['hub_url'] = \SmallSmallRSS\Config::get('PUBSUBHUBBUB_HUB');
            }
            $feed['self_url'] = get_self_url_prefix();
            $feed['articles'] = array();
            while ($line = \SmallSmallRSS\Database::fetchAssoc($result)) {
                $article = array();
                $article['id'] = $line['link'];
                $article['link'] = $line['link'];
                $article['title'] = $line['title'];
                $article['excerpt'] = \SmallSmallRSS\Utils::truncateString(
                    strip_tags($line['content_preview']),
                    100,
                    '...'
                );
                $article['content'] = sanitize($line['content_preview'], $owner_uid);
                $article['updated'] = date('c', strtotime($line['updated']));
                if ($line['note']) {
                    $article['note'] = $line['note'];
                }
                if ($article['author']) {
                    $article['author'] = $line['author'];
                }
                $tags = get_article_tags($line['id'], $owner_uid);
                if (count($tags) > 0) {
                    $article['tags'] = array();
                    foreach ($tags as $tag) {
                        array_push($article['tags'], $tag);
                    }
                }
                $enclosures = \SmallSmallRSS\Enclosures::get($line['id']);
                if (count($enclosures) > 0) {
                    $article['enclosures'] = array();
                    foreach ($enclosures as $e) {
                        $type = $e['content_type'];
                        $url = $e['content_url'];
                        $length = $e['duration'];
                        array_push($article['enclosures'], array('url' => $url, 'type' => $type, 'length' => $length));
                    }
                }
                array_push($feed['articles'], $article);
            }
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode($feed);
        } else {
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(array('error' => array('message' => 'Unknown format')));
        }
    }

    public function getUnread()
    {
        $login = \SmallSmallRSS\Database::escapeString($_REQUEST['login']);
        $fresh = $_REQUEST['fresh'] == '1';

        $result = \SmallSmallRSS\Database::query("SELECT id FROM ttrss_users WHERE login = '$login'");

        if (\SmallSmallRSS\Database::numRows($result) == 1) {
            $uid = \SmallSmallRSS\Database::fetchResult($result, 0, 'id');

            echo \SmallSmallRSS\CountersCache::getGlobalUnread($uid);

            if ($fresh) {
                echo ';';
                echo countUnreadFeedArticles(-3, false, true, $uid);
            }

        } else {
            echo '-1;User not found';
        }

    }

    public function getProfiles()
    {
        $login = \SmallSmallRSS\Database::escapeString($_REQUEST['login']);

        $result = \SmallSmallRSS\Database::query(
            "SELECT ttrss_settings_profiles.*
             FROM ttrss_settings_profiles,ttrss_users
             WHERE
                 ttrss_users.id = ttrss_settings_profiles.owner_uid
                 AND login = '$login'
             ORDER BY title"
        );
        echo "<select data-dojo-type=\"dijit.form.Select\" style=\"width: 220px; margin: 0px\" name=\"profile\">";
        echo "<option value=\"0\">" . __('Default profile') . '</option>';
        while ($line = \SmallSmallRSS\Database::fetchAssoc($result)) {
            $id = $line['id'];
            $title = $line['title'];
            echo "<option value=\"$id\">$title</option>";
        }
        echo '</select>';
    }

    public function pubsub()
    {
        $mode = \SmallSmallRSS\Database::escapeString($_REQUEST['hub_mode']);
        $feed_id = (int) \SmallSmallRSS\Database::escapeString($_REQUEST['id']);
        $feed_url = \SmallSmallRSS\Database::escapeString($_REQUEST['hub_topic']);

        if (!\SmallSmallRSS\Config::get('PUBSUBHUBBUB_ENABLED')) {
            header('HTTP/1.0 404 Not Found');
            echo '404 Not found';
            return;
        }

        // TODO: implement hub_verifytoken checking

        $result = \SmallSmallRSS\Database::query("SELECT feed_url FROM ttrss_feeds WHERE id = '$feed_id'");
        if (\SmallSmallRSS\Database::numRows($result) != 0) {
            $check_feed_url = \SmallSmallRSS\Database::fetchResult($result, 0, 'feed_url');
            if ($check_feed_url && ($check_feed_url == $feed_url || !$feed_url)) {
                if ($mode == 'subscribe') {
                    \SmallSmallRSS\Database::query(
                        "UPDATE ttrss_feeds SET pubsub_state = 2
                        WHERE id = '$feed_id'"
                    );
                    echo $_REQUEST['hub_challenge'];
                    return;
                } elseif ($mode == 'unsubscribe') {
                    \SmallSmallRSS\Database::query(
                        "UPDATE ttrss_feeds SET pubsub_state = 0
                         WHERE id = '$feed_id'"
                    );
                    echo $_REQUEST['hub_challenge'];
                    return;

                } elseif (!$mode) {
                    \SmallSmallRSS\Database::query(
                        "UPDATE ttrss_feeds SET
                        last_update_started = '1970-01-01',
                        last_updated = '1970-01-01' WHERE id = '$feed_id'"
                    );
                }
            } else {
                header('HTTP/1.0 404 Not Found');
                echo '404 Not found';
            }
        } else {
            header('HTTP/1.0 404 Not Found');
            echo '404 Not found';
        }

    }
    public function logout()
    {
        \SmallSmallRSS\Sessions::logout();
        header('Location: index.php');
    }

    public function share()
    {
        $uuid = \SmallSmallRSS\Database::escapeString($_REQUEST['key']);
        $result = \SmallSmallRSS\Database::query(
            "SELECT ref_id, owner_uid
             FROM ttrss_user_entries
             WHERE uuid = '$uuid'"
        );
        if (\SmallSmallRSS\Database::numRows($result) != 0) {
            header('Content-Type: text/html');
            $id = \SmallSmallRSS\Database::fetchResult($result, 0, 'ref_id');
            $owner_uid = \SmallSmallRSS\Database::fetchResult($result, 0, 'owner_uid');

            $article = self::formatArticle($id, false, true, $owner_uid);
            print_r($article['content']);
        } else {
            echo 'Article not found.';
        }

    }

    public function rss()
    {
        $feed = \SmallSmallRSS\Database::escapeString($_REQUEST['id']);
        $key = \SmallSmallRSS\Database::escapeString($_REQUEST['key']);
        $is_cat = $_REQUEST['is_cat'] != false;
        $limit = (int) \SmallSmallRSS\Database::escapeString($_REQUEST['limit']);
        $offset = (int) \SmallSmallRSS\Database::escapeString($_REQUEST['offset']);

        $search = \SmallSmallRSS\Database::escapeString($_REQUEST['q']);
        $search_mode = \SmallSmallRSS\Database::escapeString($_REQUEST['smode']);
        $view_mode = \SmallSmallRSS\Database::escapeString($_REQUEST['view-mode']);
        $order = \SmallSmallRSS\Database::escapeString($_REQUEST['order']);

        $format = \SmallSmallRSS\Database::escapeString($_REQUEST['format']);

        if (!$format) {
            $format = 'atom';
        }

        if (\SmallSmallRSS\Auth::isSingleUserMode()) {
            \SmallSmallRSS\Auth::authenticate('admin', null);
        }

        $owner_id = false;

        if ($key) {
            $result = \SmallSmallRSS\Database::query(
                "SELECT owner_uid
                 FROM ttrss_access_keys
                 WHERE
                     access_key = '$key'
                     AND feed_id = '$feed'"
            );

            if (\SmallSmallRSS\Database::numRows($result) == 1) {
                $owner_id = \SmallSmallRSS\Database::fetchResult($result, 0, 'owner_uid');
            }
        }

        if ($owner_id) {
            $this->generateSyndicatedFeed(
                $owner_id,
                $feed,
                $is_cat,
                $limit,
                $offset,
                $search,
                $search_mode,
                $view_mode,
                $format,
                $order
            );
        } else {
            header('HTTP/1.1 403 Forbidden');
        }
    }

    public function globalUpdateFeeds()
    {
        \SmallSmallRSS\RSSUpdater::updateBatch();
        \SmallSmallRSS\RSSUpdater::housekeeping();
        \SmallSmallRSS\PluginHost::getInstance()->runHooks(\SmallSmallRSS\Hooks::UPDATE_TASK);
    }

    public function sharepopup()
    {
        if (\SmallSmallRSS\Auth::isSingleUserMode()) {
            login_sequence();
        }

        header('Content-Type: text/html; charset=utf-8');
        echo '<!DOCTYPE html>';
        echo '<html>';
        echo '<head>';
        echo '<title>';
        echo \SmallSmallRSS\Config::get('SOFTWARE_NAME');
        echo '</title>';
        $renderer = new \SmallSmallRSS\Renderers\CSS();
        $renderer->renderStylesheetTag('css/utility.css');
        $js_renderer = new \SmallSmallRSS\Renderers\JS();
        $js_renderer->renderScriptTag('lib/prototype.js');
        $js_renderer->renderScriptTag('lib/scriptaculous/scriptaculous.js?load=effects,dragdrop,controls');
        echo "\n<meta http-equiv=\"Content-Type\" content=\"text/html; charset=utf-8\"/>";
        echo "\n</head>";
        echo "\n<body id=\"sharepopup\">";

        $action = $_REQUEST['action'];

        if ($_SESSION['uid']) {
            if ($action == 'share') {
                $title = \SmallSmallRSS\Database::escapeString(strip_tags($_REQUEST['title']));
                $url = \SmallSmallRSS\Database::escapeString(strip_tags($_REQUEST['url']));
                $content = \SmallSmallRSS\Database::escapeString(strip_tags($_REQUEST['content']));
                $labels = \SmallSmallRSS\Database::escapeString(strip_tags($_REQUEST['labels']));

                Article::createPublishedArticle(
                    $title,
                    $url,
                    $content,
                    $labels,
                    $_SESSION['uid']
                );

                echo "<script type=\"text/javascript\">";
                echo 'window.close();';
                echo '</script>';

            } else {
                $title = htmlspecialchars($_REQUEST['title']);
                $url = htmlspecialchars($_REQUEST['url']);

                echo '<table height="100%" width="100%">';
                echo '<tr>';
                echo '<td colspan="2">';
                echo '<h1>';
                echo __('Share with Tiny Tiny RSS');
                echo '</h1>';
                echo '</td>';
                echo '</tr>';
                echo '<form id="share_form" name="share_form">';
                echo '<input type="hidden" name="op" value="sharepopup" />';
                echo '<input type="hidden" name="action" value="share">';
                echo '<tr>';
                echo '<th>';
                echo __('Title:');
                echo '</th>';
                echo '<td width="80%">';
                echo '<input name="title" value="';
                echo $title;
                echo '">';
                echo '</td>';
                echo '</tr>';
                echo '<tr>';
                echo '<th>';
                echo __('URL:');
                echo '</th>';
                echo '<td>';
                echo '<input name="url" value="';
                echo $url;
                echo '"/>';
                echo '</td>';
                echo '</tr>';
                echo '<tr>';
                echo '<th>';
                echo __('Content:');
                echo '</th>';
                echo '<td>';
                echo '<input name="content" value="">';
                echo '</td>';
                echo '</tr>';
                echo '<tr>';
                echo '<th>';
                echo __('Labels:');
                echo '</th>';
                echo '<td>';
                echo '<input name="labels" id="labels_value" placeholder="Alpha, Beta, Gamma" value="" />';
                echo '</td>';
                echo '</tr>';
                echo '<tr>';
                echo '<td>';
                echo '<div class="autocomplete" id="labels_choices"';
                echo ' style="display : block">';
                echo '</div>';
                echo '</td>';
                echo '</tr>';
                echo '<script type="text/javascript">document.forms[0].title.focus();</script>';
                echo '<script type="text/javascript:>';
                ?>
                new Ajax.Autocompleter(
                    'labels_value', 'labels_choices',
                    "backend.php?op=rpc&method=completeLabels",
                    {tokens: ',', paramName: "search"});
                <?php
                echo '</script>';
                echo '<tr>';
                echo '<td colspan="2">';
                echo '<div style="float: right" class="insensitive-small">';
                echo __('Shared article will appear in the Published feed.');
                echo '</div>';
                echo '<button type="submit">';
                echo __('Share');
                echo '</button>';
                echo '<button onclick="return window.close()">';
                echo __('Cancel');
                echo '</button>';
                echo '</div>';
                echo '</form>';
                echo '</td>';
                echo '</tr>';
                echo '</table>';
                echo '</body>';
                echo '</html>';
            }
        } else {
            $return = urlencode($_SERVER['REQUEST_URI']);
            echo '<form action="public.php?return=';
            echo $return;
            echo '" method="POST" id="loginForm" name="loginForm">';
            echo '<input type="hidden" name="op" value="login" />';
            echo '<table>';
            echo '<tr>';
            echo '<td colspan="2">';
            echo '<h1>';
            echo __('Not logged in');
            echo '</h1>';
            echo '</td>';
            echo '</tr>';
            echo '<tr>';
            echo '<th>';
            echo __('Login:');
            echo '</th>';
            echo '<th>';
            echo '<input name="login" value="';
            echo $_SESSION['fake_login'];
            echo '" />';
            echo '</th>';
            echo '</tr>';
            echo '<tr>';
            echo '<th>';
            echo __('Password:');
            echo '</th>';
            echo '<td>';
            echo '<input type="password" name="password" value="';
            echo $_SESSION['fake_password'];
            echo '">';
            echo '</td>';
            echo '</tr>';
            echo '<tr>';
            echo '<td colspan="2">';
            echo '<button type="submit">';
            echo __('Log in');
            echo '</button>';
            echo '<button onclick="return window.close()">';
            echo __('Cancel');
            echo '</button>';
            echo '</td>';
            echo '</tr>';
            echo '</table>';
            echo '</form>';
        }
    }

    public function login()
    {
        $login = \SmallSmallRSS\Database::escapeString($_POST['login']);
        $password = $_POST['password'];
        $remember_me = !empty($_POST['remember_me']);

        if (\SmallSmallRSS\Auth::isSingleUserMode()) {
            $login = 'admin';
        }

        if (!\SmallSmallRSS\Auth::isSingleUserMode()) {
            if ($remember_me) {
                session_set_cookie_params(\SmallSmallRSS\Config::get('SESSION_COOKIE_LIFETIME'));
            } else {
                session_set_cookie_params(0);
            }

            if (\SmallSmallRSS\Auth::authenticate($login, $password)) {
                $_POST['password'] = '';
                if (\SmallSmallRSS\Sanity::getSchemaVersion() >= 120) {
                    $_SESSION['language'] = \SmallSmallRSS\DBPrefs::read('USER_LANGUAGE', $_SESSION['uid']);
                }
                $_SESSION['ref_schema_version'] = \SmallSmallRSS\Sanity::getSchemaVersion(true);
                $_SESSION['bw_limit'] = !empty($_POST['bw_limit']);
                if (!empty($_POST['profile'])) {
                    $profile = \SmallSmallRSS\Database::escapeString($_POST['profile']);
                    $result = \SmallSmallRSS\Database::query(
                        "SELECT id
                         FROM ttrss_settings_profiles
                         WHERE
                             id = '$profile'
                             AND owner_uid = " . $_SESSION['uid']
                    );

                    if (\SmallSmallRSS\Database::numRows($result) != 0) {
                        $_SESSION['profile'] = $profile;
                    }
                }
            } else {
                $_SESSION['login_error_msg'] = __('Incorrect username or password');
            }

            if ($_REQUEST['return']) {
                header('Location: ' . $_REQUEST['return']);
            } else {
                header('Location: ' . \SmallSmallRSS\Config::get('SELF_URL_PATH'));
            }
        }
    }

    public static function getSubscribeUrl()
    {
        return get_self_url_prefix() . '/public.php?op=subscribe&feed_url=%s';
    }

    public function subscribe()
    {
        if (\SmallSmallRSS\Auth::isSingleUserMode()) {
            login_sequence();
        }
        $feed_urls = false;
        if (!empty($_SESSION['uid'])) {
            $feed_url = \SmallSmallRSS\Database::escapeString(trim($_REQUEST['feed_url']));

            header('Content-Type: text/html; charset=utf-8');
            echo '<!DOCTYPE html>';
            echo '<html>';
            echo '<head>';
            echo '<title>';
            echo \SmallSmallRSS\Config::get('SOFTWARE_NAME');
            echo '</title>';
            echo '<link rel="stylesheet" type="text/css" href="css/utility.css">';
            echo '<meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>';
            echo '</head>';
            echo '<body>';
            echo '<img class="floatingLogo" src="images/logo_small.png"';
            echo ' alt="';
            echo \SmallSmallRSS\Config::get('SOFTWARE_NAME');
            echo '"/>';
            echo '<h1>'.__('Subscribe to feed...')."</h1><div class='content'>";
            $rc = subscribe_to_feed($feed_url);
            switch ($rc['code']) {
                case 0:
                    \SmallSmallRSS\Renderers\Messages::renderWarning(
                        T_sprintf('Already subscribed to <b>%s</b>.', $feed_url)
                    );
                    break;
                case 1:
                    \SmallSmallRSS\Renderers\Messages::renderNotice(
                        T_sprintf('Subscribed to <b>%s</b>.', $feed_url)
                    );
                    break;
                case 2:
                    \SmallSmallRSS\Renderers\Messages::renderError(
                        T_sprintf('Could not subscribe to <b>%s</b>.', $feed_url)
                    );
                    break;
                case 3:
                    \SmallSmallRSS\Renderers\Messages::renderError(
                        T_sprintf('No feeds found in <b>%s</b>.', $feed_url)
                    );
                    break;
                case 4:
                    \SmallSmallRSS\Renderers\Messages::renderNotice(__('Multiple feed URLs found.'));
                    $feed_urls = $rc['feeds'];
                    break;
                case 5:
                    \SmallSmallRSS\Renderers\Messages::renderError(
                        T_sprintf("Could not subscribe to <b>%s</b>.<br>Can't download the Feed URL.", $feed_url)
                    );
                    break;
            }
            if ($feed_urls) {
                echo '<form action="public.php">';
                echo '<input type="hidden" name="op" value="subscribe">';
                echo '<select name="feed_url">';
                foreach ($feed_urls as $url => $name) {
                    $url = htmlspecialchars($url);
                    $name = htmlspecialchars($name);
                    echo "<option value=\"$url\">$name</option>";
                }
                echo '<input type="submit" value="' . __('Subscribe to selected feed') . '">';
                echo '</form>';
            }
            $tp_uri = get_self_url_prefix() . '/prefs.php';
            $tt_uri = get_self_url_prefix();
            if ($rc['code'] <= 2) {
                $result = \SmallSmallRSS\Database::query(
                    "SELECT id FROM ttrss_feeds WHERE
                    feed_url = '$feed_url' AND owner_uid = " . $_SESSION['uid']
                );
                $feed_id = \SmallSmallRSS\Database::fetchResult($result, 0, 'id');
            } else {
                $feed_id = 0;
            }
            echo '<p>';
            if ($feed_id) {
                echo "<form method=\"GET\" style='display: inline'
                    action=\"$tp_uri\">
                    <input type=\"hidden\" name=\"tab\" value=\"feedConfig\">
                    <input type=\"hidden\" name=\"method\" value=\"editFeed\">
                    <input type=\"hidden\" name=\"methodparam\" value=\"$feed_id\">
                    <input type=\"submit\" value=\"".__('Edit subscription options').'">
                    </form>';
            }
            echo "<form style='display: inline' method=\"GET\" action=\"$tt_uri\">";
            echo '<input type="submit" value="'.__('Return to Tiny Tiny RSS').'">';
            echo '</form>';
            echo '</p>';
            echo '</div>';
            echo '</body>';
            echo '</html>';

        } else {
            $renderer = new \SmallSmallRSS\Renderers\LoginPage();
            $renderer->render();
        }
    }

    public function subscribe2()
    {
        $feed_url = \SmallSmallRSS\Database::escapeString(trim($_REQUEST['feed_url']));
        $cat_id = \SmallSmallRSS\Database::escapeString($_REQUEST['cat_id']);
        $from = \SmallSmallRSS\Database::escapeString($_REQUEST['from']);
        $feed_urls = array();

        /* only read authentication information from POST */

        $auth_login = \SmallSmallRSS\Database::escapeString(trim($_POST['auth_login']));
        $auth_pass = \SmallSmallRSS\Database::escapeString(trim($_POST['auth_pass']));

        $rc = subscribe_to_feed($feed_url, $cat_id, $auth_login, $auth_pass);

        switch ($rc) {
            case 1:
                \SmallSmallRSS\Renderers\Messages::renderNotice(
                    T_sprintf('Subscribed to <b>%s</b>.', $feed_url)
                );
                break;
            case 2:
                \SmallSmallRSS\Renderers\Messages::renderError(
                    T_sprintf('Could not subscribe to <b>%s</b>.', $feed_url)
                );
                break;
            case 3:
                \SmallSmallRSS\Renderers\Messages::renderError(
                    T_sprintf('No feeds found in <b>%s</b>.', $feed_url)
                );
                break;
            case 0:
                \SmallSmallRSS\Renderers\Messages::renderWarning(
                    T_sprintf('Already subscribed to <b>%s</b>.', $feed_url)
                );
                break;
            case 4:
                \SmallSmallRSS\Renderers\Messages::renderNotice(__('Multiple feed URLs found.'));
                $contents = @\SmallSmallRSS\Fetcher::simpleFetch($url, false, $auth_login, $auth_pass);
                if (\SmallSmallRSS\Utils::isHtml($contents)) {
                    $feed_urls = get_feeds_from_html($url, $contents);
                }
                break;
            case 5:
                \SmallSmallRSS\Renderers\Messages::renderError(
                    T_sprintf("Could not subscribe to <b>%s</b>.<br>Can't download the Feed URL.", $feed_url)
                );
                break;
        }

        if ($feed_urls) {
            echo '<form action="backend.php">';
            echo '<input type="hidden" name="op" value="PrefFeeds">';
            echo '<input type="hidden" name="quiet" value="1">';
            echo '<input type="hidden" name="method" value="add">';

            echo '<select name="feed_url">';

            foreach ($feed_urls as $url => $name) {
                $url = htmlspecialchars($url);
                $name = htmlspecialchars($name);
                echo "<option value=\"$url\">$name</option>";
            }

            echo '<input type="submit" value="'.__('Subscribe to selected feed').'">';
            echo '</form>';
        }

        $tp_uri = get_self_url_prefix() . '/prefs.php';
        $tt_uri = get_self_url_prefix();

        if ($rc <= 2) {
            $result = \SmallSmallRSS\Database::query(
                "SELECT id FROM ttrss_feeds WHERE
                feed_url = '$feed_url' AND owner_uid = " . $_SESSION['uid']
            );

            $feed_id = \SmallSmallRSS\Database::fetchResult($result, 0, 'id');
        } else {
            $feed_id = 0;
        }

        echo '<p>';

        if ($feed_id) {
            echo "<form method=\"GET\" style='display: inline'
                action=\"$tp_uri\">
                <input type=\"hidden\" name=\"tab\" value=\"feedConfig\">
                <input type=\"hidden\" name=\"method\" value=\"editFeed\">
                <input type=\"hidden\" name=\"methodparam\" value=\"$feed_id\">
                <input type=\"submit\" value=\"".__('Edit subscription options').'">
                </form>';
        }

        echo "<form style='display: inline' method=\"GET\" action=\"$tt_uri\">
            <input type=\"submit\" value=\"".__('Return to Tiny Tiny RSS').'">
            </form></p>';

        echo '</body></html>';
    }

    public function index()
    {
        $renderer = new \SmallSmallRSS\Renderers\JSONError(\SmallSmallRSS\Errors::NOTHING_TO_DO());
        $renderer->render();
    }

    public function forgotpass()
    {
        \SmallSmallRSS\Locale::startupGettext($_SESSION['uid']);

        header('Content-Type: text/html; charset=utf-8');
        echo '<!DOCTYPE html>';
        echo '<html>';
        echo '<head>';
        echo '<title>';
        echo \SmallSmallRSS\Config::get('SOFTWARE_NAME');
        echo '</title>';
        $renderer = new \SmallSmallRSS\Renderers\CSS();
        $renderer->renderStylesheetTag('css/utility.css');
        $js_renderer = new \SmallSmallRSS\Renderers\JS();
        $js_renderer->renderScriptTag('lib/prototype.js');

        echo "<meta http-equiv=\"Content-Type\" content=\"text/html; charset=utf-8\"/>
            </head><body id='forgotpass'>";

        echo '<div class="floatingLogo"><img src="images/logo_small.png"></div>';
        echo '<h1>'.__('Password recovery').'</h1>';
        echo "<div class='content'>";

        @$method = $_POST['method'];

        if (!$method) {
            \SmallSmallRSS\Renderers\Messages::renderNotice(
                __('You will need to provide valid account name and email.')
                . __('New password will be sent to your email address.')
            );

            echo "<form method='POST' action='public.php'>";
            echo "<input type='hidden' name='method' value='do'>";
            echo "<input type='hidden' name='op' value='forgotpass'>";

            echo '<fieldset>';
            echo '<label>'.__('Login:').'</label>';
            echo "<input type='text' name='login' value='' required>";
            echo '</fieldset>';

            echo '<fieldset>';
            echo '<label>'.__('Email:').'</label>';
            echo "<input type='email' name='email' value='' required>";
            echo '</fieldset>';

            echo '<fieldset>';
            echo '<label>'.__('How much is two plus two:').'</label>';
            echo "<input type='text' name='test' value='' required>";
            echo '</fieldset>';

            echo '<p/>';
            echo "<button type='submit'>".__('Reset password').'</button>';

            echo '</form>';
        } elseif ($method == 'do') {
            $login = \SmallSmallRSS\Database::escapeString($_POST['login']);
            $email = \SmallSmallRSS\Database::escapeString($_POST['email']);
            $test = \SmallSmallRSS\Database::escapeString($_POST['test']);

            if (($test != 4 && $test != 'four') || !$email || !$login) {
                \SmallSmallRSS\Renderers\Messages::renderError(
                    __('Some of the required form parameters are missing or incorrect.')
                );
                echo '<form method="GET" action="public.php">';
                echo '<input type="hidden" name="op" value="forgotpass" />';
                echo '<input type="submit" value="'.__('Go back').'" />';
                echo '</form>';
            } else {
                $result = \SmallSmallRSS\Database::query(
                    "SELECT id
                     FROM ttrss_users
                     WHERE
                         login = '$login'
                         AND email = '$email'"
                );
                if (\SmallSmallRSS\Database::numRows($result) != 0) {
                    $id = \SmallSmallRSS\Database::fetchResult($result, 0, 'id');
                    PrefUsers::resetUserPassword($id, false);
                    echo '<p>';
                    echo '<p>'.'Completed.'.'</p>';
                    echo '<form method="GET" action="index.php">';
                    echo '<input type="submit" value="'.__('Return to Tiny Tiny RSS').'">';
                    echo '</form>';
                } else {
                    \SmallSmallRSS\Renderers\Messages::renderError(
                        __('Sorry, login and email combination not found.')
                    );
                    echo '<form method="GET" action="public.php">';
                    echo '<input type="hidden" name="op" value="forgotpass">';
                    echo '<input type="submit" value="'.__('Go back').'">';
                    echo '</form>';
                }
            }

        }
        echo '</div>';
        echo '</body>';
        echo '</html>';
    }

    public function dbupdate()
    {
        \SmallSmallRSS\Locale::startupGettext($_SESSION['uid']);

        if (!\SmallSmallRSS\Auth::isSingleUserMode() && $_SESSION['access_level'] < 10) {
            $_SESSION['login_error_msg'] = __('Your access level is insufficient to run this script.');
            $renderer = new \SmallSmallRSS\Renderers\LoginPage();
            $renderer->render();
            exit;
        }
        echo '<!DOCTYPE html>';
        echo '<html>';
        echo '<head>';
        echo '<title>Database Updater</title>';
        echo '<meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>';
        echo '<link rel="stylesheet" type="text/css" href="css/utility.css"/>';
        echo '</head>';
        echo '<style type="text/css">';
        echo '<div class="floatingLogo"><img src="images/logo_small.png" /></div>';
        echo '<h1>';
        echo __('Database Updater');
        echo '</h1>';
        echo '<div class="content">';
        @$op = $_REQUEST['subop'];
        $updater = new \SmallSmallRSS\Database\Updater();
        if ($op == 'performupdate') {
            if ($updater->isUpdateRequired()) {
                echo '<h2>Performing updates</h2>';
                echo '<h3>Updating to schema version ' . \SmallSmallRSS\Constants::SCHEMA_VERSION . '</h3>';
                echo '<ul>';
                for ($i = $updater->getSchemaVersion() + 1; $i <= \SmallSmallRSS\Constants::SCHEMA_VERSION; $i++) {
                    echo "<li>Performing update up to version $i...";
                    $result = $updater->performUpdateTo($i);
                    if (!$result) {
                        echo "<span class='err'>FAILED!</span></li></ul>";
                        \SmallSmallRSS\Renderers\Messages::renderWarning(
                            'One of the updates failed. Either retry the process or perform updates manually.'
                        );
                        echo '<p><form method="GET" action="index.php">
                                <input type="submit" value="'.__('Return to Tiny Tiny RSS').'">
                                </form>';
                        break;
                    } else {
                        echo "<span class='ok'>OK!</span></li>";
                    }
                }
                echo '</ul>';
                \SmallSmallRSS\Renderers\Messages::renderNotice(
                    'Your Tiny Tiny RSS database is now updated to the latest version.'
                );
                echo '<p><form method="GET" action="index.php">
                        <input type="submit" value="'.__('Return to Tiny Tiny RSS').'">
                        </form>';
            } else {
                echo '<h2>Your database is up to date.</h2>';
                echo '<p><form method="GET" action="index.php">
                        <input type="submit" value="'.__('Return to Tiny Tiny RSS').'">
                        </form>';
            }
        } else {
            if ($updater->isUpdateRequired()) {
                echo '<h2>Database update required</h2>';
                echo '<h3>';
                printf(
                    'Your Tiny Tiny RSS database needs update to the latest version: %d to %d.',
                    $updater->getSchemaVersion(),
                    \SmallSmallRSS\Constants::SCHEMA_VERSION
                );
                echo '</h3>';
                \SmallSmallRSS\Renderers\Messages::renderWarning(
                    'Please backup your database before proceeding.'
                );
                echo "<form method='POST'>";
                echo "<input type='hidden' name='subop' value='performupdate'>";
                echo "<input type='submit' onclick='return confirm(\"Update the database?\")'";
                echo " value='".__('Perform updates')."'>";
                echo '</form>';
            } else {
                \SmallSmallRSS\Renderers\Messages::renderNotice('Tiny Tiny RSS database is up to date.');
                echo '<p><form method="GET" action="index.php">';
                echo '<input type="submit" value="'.__('Return to Tiny Tiny RSS').'">';
                echo '</form>';
            }
        }
        echo '</div>';
        echo '</body>';
        echo '</html>';
    }
}
