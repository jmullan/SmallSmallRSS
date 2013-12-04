<?php
namespace SmallSmallRSS\Handlers;

class Feeds extends ProtectedHandler
{
    public function ignoreCSRF($method)
    {
        $csrf_ignored = array('index', 'feedbrowser', 'quickaddfeed', 'search');
        return array_search($method, $csrf_ignored) !== false;
    }

    private function format_headline_subtoolbar(
        $feed_site_url,
        $feed_title,
        $feed_id,
        $is_cat,
        $search,
        $search_mode,
        $view_mode,
        $error,
        $feed_last_updated
    ) {
        $page_prev_link = 'viewFeedGoPage(-1)';
        $page_next_link = 'viewFeedGoPage(1)';
        $page_first_link = 'viewFeedGoPage(0)';

        $catchup_page_link = 'catchupPage()';
        $catchup_feed_link = 'catchupCurrentFeed()';
        $catchup_sel_link = 'catchupSelection()';

        $archive_sel_link = 'archiveSelection()';
        $delete_sel_link = 'deleteSelection()';

        $sel_all_link = "selectArticles('all')";
        $sel_unread_link = "selectArticles('unread')";
        $sel_none_link = "selectArticles('none')";
        $sel_inv_link = "selectArticles('invert')";

        $tog_unread_link = 'selectionToggleUnread()';
        $tog_marked_link = 'selectionToggleMarked()';
        $tog_published_link = 'selectionTogglePublished()';

        $set_score_link = 'setSelectionScore()';

        if ($is_cat) {
            $cat_q = "&is_cat=$is_cat";
        } else {
            $cat_q = '';
        }

        if ($search) {
            $search_q = "&q=$search&smode=$search_mode";
        } else {
            $search_q = '';
        }

        $rss_link = htmlspecialchars(
            get_self_url_prefix()
            . "/public.php?op=rss&id=$feed_id$cat_q$search_q"
        );

        // right part

        ob_start();
        echo "<span class='r'>";
        echo "<span id='selected_prompt'></span>";
        echo "<span id='feed_title'>";

        if ($feed_site_url) {
            $last_updated = T_sprintf('Last updated: %s', $feed_last_updated);

            $target = 'target="_blank"';
            echo "<a title=\"$last_updated\" $target href=\"$feed_site_url\">".
                truncate_string($feed_title, 30) . '</a>';

            if ($error) {
                echo " (<span class=\"error\" title=\"$error\">Error</span>)";
            }

        } else {
            echo $feed_title;
        }
        echo '</span>';
        echo '<a href="#"
           title="';
        echo __('View as RSS feed');
        echo "\" onclick=\"displayDlg('";
        echo __('View as RSS');
        echo "','generatedFeed', '$feed_id:$is_cat:$rss_link')\">";
        echo '<img class="noborder" style="vertical-align : middle" src="images/pub_set.svg"></a>';
        echo '</span>';
        // left part
        echo __('Select:');
        echo "<a href=\"#\" onclick=\"$sel_all_link\">";
        echo __('All');
        echo '</a>,';
        echo "<a href=\"#\" onclick=\"$sel_unread_link\">";
        echo __('Unread');
        echo '</a>,';
        echo "<a href=\"#\" onclick=\"$sel_inv_link\">";
        echo __('Invert');
        echo '</a>,';
        echo "<a href=\"#\" onclick=\"$sel_none_link\">";
        echo __('None');
        echo '</a></li>';
        echo ' ';
        echo '<select data-dojo-type="dijit.form.Select" onchange="headlineActionsChange(this)">';
        echo '<option value="false">';
        echo __('More...');
        echo '</option>';
        echo '<option value="0" disabled="1">';
        echo __('Selection toggle:');
        echo '</option>';
        echo "<option value=\"$tog_unread_link\">";
        echo __('Unread');
        echo '</option>';
        echo "<option value=\"$tog_marked_link\">";
        echo __('Starred');
        echo '</option>';
        echo "<option value=\"$tog_published_link\">";
        echo __('Published');
        echo '</option>';

        echo '<option value="0" disabled="1">';
        echo __('Selection:');
        echo '</option>';

        echo "<option value=\"$catchup_sel_link\">";
        echo __('Mark as read');
        echo '</option>';

        echo "<option value=\"$set_score_link\">";
        echo __('Set score');
        echo '</option>';

        if ($feed_id != '0') {
            echo "<option value=\"$archive_sel_link\">";
            echo __('Archive');
            echo '</option>';
        } else {
            echo "<option value=\"$archive_sel_link\">";
            echo __('Move back');
            echo '</option>';
            echo "<option value=\"$delete_sel_link\">";
            echo __('Delete');
            echo '</option>';

        }
        if (\SmallSmallRSS\PluginHost::getInstance()->get_plugin('mail')) {
            echo '<option value="emailArticle(false)">';
            echo __('Forward by email').
                '</option>';
        }
        if (\SmallSmallRSS\PluginHost::getInstance()->get_plugin('mailto')) {
            echo '<option value="mailtoArticle(false)">';
            echo __('Forward by email').
                '</option>';
        }
        echo '<option value="0" disabled="1">';
        echo __('Feed:');
        echo '</option>';
        echo "<option value=\"displayDlg('";
        echo __('View as RSS');
        echo "','generatedFeed', '$feed_id:$is_cat:$rss_link')\">";
        echo __('View as RSS');
        echo '</option>';
        echo '</select>';

        $args = array('feed_id' => $feed_id, 'is_cat' => $is_cat);
        \SmallSmallRSS\PluginHost::getInstance()->runHooks(
            \SmallSmallRSS\Hooks::RENDER_HEADLINE_TOOLBAR_BUTTON,
            $args
        );
        $reply = ob_get_clean();
        return $reply;
    }

    private function formatHeadlinesList(
        $feed,
        $method,
        $view_mode,
        $limit,
        $cat_view,
        $next_unread_feed,
        $offset,
        $vgr_last_feed = false,
        $override_order = false,
        $include_children = false
    ) {
        if (isset($_REQUEST['DevForceUpdate'])) {
            header('Content-Type: text/plain');
        }
        ob_start();
        $disable_cache = false;
        $reply = array();
        $rgba_cache = array();
        $timing_info = microtime(true);
        $topmost_article_ids = array();
        if (!$offset) {
            $offset = 0;
        }
        if ($method == 'undefined') {
            $method = '';
        }
        $method_split = explode(':', $method);
        if ($method == 'ForceUpdate' && $feed > 0 && is_numeric($feed)) {
            // Update the feed if required with some basic flood control
            $substring_for_date = \SmallSmallRSS\Database::getSubstringForDateFunction();
            $result = \SmallSmallRSS\Database::query(
                'SELECT cache_images, '.$substring_for_date."(last_updated,1,19) AS last_updated
                 FROM ttrss_feeds WHERE id = '$feed'"
            );

            if (\SmallSmallRSS\Database::num_rows($result) != 0) {
                $last_updated = strtotime(\SmallSmallRSS\Database::fetch_result($result, 0, 'last_updated'));
                $cache_images = \SmallSmallRSS\Database::fromSQLBool(\SmallSmallRSS\Database::fetch_result($result, 0, 'cache_images'));

                if (!$cache_images && time() - $last_updated > 120 || isset($_REQUEST['DevForceUpdate'])) {
                    \SmallSmallRSS\RSSUpdater::updateFeed($feed, true);
                } else {
                    \SmallSmallRSS\Database::query(
                        "UPDATE ttrss_feeds'
                         SET last_updated = '1970-01-01',
                             last_update_started = '1970-01-01'
                         WHERE id = '$feed'"
                    );
                }
            }
        }

        if ($method_split[0] == 'MarkAllReadGR') {
            \SmallSmallRSS\UserEntries::catchupFeed($method_split[1], false, $_SESSION['uid']);
        }

        // FIXME: might break tag display?
        if (is_numeric($feed) && $feed > 0 && !$cat_view) {
            $result = \SmallSmallRSS\Database::query(
                "SELECT id FROM ttrss_feeds WHERE id = '$feed' LIMIT 1"
            );

            if (\SmallSmallRSS\Database::num_rows($result) == 0) {
                echo "<div align='center'>";
                echo __('Feed not found.');
                echo '</div>';
            }
        }

        $search = $this->getSQLEscapedStringFromRequest('query');

        if ($search) {
            $disable_cache = true;
        }

        $search_mode = $this->getSQLEscapedStringFromRequest('search_mode');

        if ($search_mode == '' && $method != '') {
            $search_mode = $method;
        }

        if (!$cat_view
            && is_numeric($feed)
            && $feed < \SmallSmallRSS\Constants::PLUGIN_FEED_BASE_INDEX
            && $feed > \SmallSmallRSS\Constants::LABEL_BASE_INDEX) {

            $handler = \SmallSmallRSS\PluginHost::getInstance()->get_feed_handler(
                \SmallSmallRSS\PluginHost::feed_to_pfeed_id($feed)
            );

            if ($handler) {
                $options = array(
                    'limit' => $limit,
                    'view_mode' => $view_mode,
                    'cat_view' => $cat_view,
                    'search' => $search,
                    'search_mode' => $search_mode,
                    'override_order' => $override_order,
                    'offset' => $offset,
                    'owner_uid' => $_SESSION['uid'],
                    'filter' => false,
                    'since_id' => 0,
                    'include_children' => $include_children
                );

                $qfh_ret = $handler->get_headlines(
                    \SmallSmallRSS\PluginHost::feed_to_pfeed_id($feed),
                    $options
                );
            }

        } else {
            $qfh_ret = queryFeedHeadlines(
                $feed,
                $limit,
                $view_mode,
                $cat_view,
                $search,
                $search_mode,
                $override_order,
                $offset,
                0,
                false,
                0,
                $include_children
            );
        }

        $result = $qfh_ret[0];
        $feed_title = $qfh_ret[1];
        $feed_site_url = $qfh_ret[2];
        $last_error = $qfh_ret[3];
        $last_updated = (
            (strpos($qfh_ret[4], '1970-') === false)
            ? make_local_datetime($qfh_ret[4], false)
            : __('Never')
        );

        $vgroup_last_feed = $vgr_last_feed;

        $reply['toolbar'] = $this->format_headline_subtoolbar(
            $feed_site_url,
            $feed_title,
            $feed,
            $cat_view,
            $search,
            $search_mode,
            $view_mode,
            $last_error,
            $last_updated
        );

        $headlines_count = \SmallSmallRSS\Database::num_rows($result);
        if (\SmallSmallRSS\Database::num_rows($result) > 0) {
            $lnum = $offset;
            $num_unread = 0;
            $cur_feed_title = '';
            $fresh_intl = \SmallSmallRSS\DBPrefs::read('FRESH_ARTICLE_MAX_AGE') * 60 * 60;
            $expand_cdm = \SmallSmallRSS\DBPrefs::read('CDM_EXPANDED');

            while ($line = \SmallSmallRSS\Database::fetch_assoc($result)) {
                $id = $line['id'];
                $feed_id = $line['feed_id'];
                $label_cache = $line['label_cache'];
                $labels = false;

                if ($label_cache) {
                    $label_cache = json_decode($label_cache, true);

                    if ($label_cache) {
                        if ($label_cache['no-labels'] == 1) {
                            $labels = array();
                        } else {
                            $labels = $label_cache;
                        }
                    }
                }

                if (!is_array($labels)) {
                    $labels = \SmallSmallRSS\Labels::getForArticle($id, $_SESSION['uid']);
                }

                $labels_str = "<span id=\"HLLCTR-$id\">";
                $labels_str .= format_article_labels($labels, $id);
                $labels_str .= '</span>';

                if (count($topmost_article_ids) < 3) {
                    array_push($topmost_article_ids, $id);
                }

                $class = '';

                if (\SmallSmallRSS\Database::fromSQLBool($line['unread'])) {
                    $class .= ' Unread';
                    ++$num_unread;
                }

                if (\SmallSmallRSS\Database::fromSQLBool($line['marked'])) {
                    $marked_pic = "<img
                        src=\"images/mark_set.svg\"
                        class=\"markedPic\" alt=\"Unstar article\"
                        onclick='toggleMark($id)'>";
                    $class .= ' marked';
                } else {
                    $marked_pic = "<img
                        src=\"images/mark_unset.svg\"
                        class=\"markedPic\" alt=\"Star article\"
                        onclick='toggleMark($id)'>";
                }

                if (\SmallSmallRSS\Database::fromSQLBool($line['published'])) {
                    $published_pic = "<img src=\"images/pub_set.svg\"
                        class=\"pubPic\"
                            alt=\"Unpublish article\" onclick='togglePub($id)'>";
                    $class .= ' published';
                } else {
                    $published_pic = "<img src=\"images/pub_unset.svg\"
                        class=\"pubPic\"
                        alt=\"Publish article\" onclick='togglePub($id)'>";
                }
                $updated_fmt = make_local_datetime($line['updated'], false);
                $date_entered_fmt = T_sprintf(
                    'Imported at %s',
                    make_local_datetime($line['date_entered'], false)
                );

                if (\SmallSmallRSS\DBPrefs::read('SHOW_CONTENT_PREVIEW')) {
                    $content_preview = truncate_string(
                        strip_tags($line['content_preview']),
                        250
                    );
                }

                $score = $line['score'];

                $score_pic = 'images/' . get_score_pic($score);

                $score_pic = "<img class='hlScorePic' score='$score' onclick='changeScore($id, this)' src=\"$score_pic\"
                    title=\"$score\">";

                if ($score > 500) {
                    $hlc_suffix = 'H';
                } elseif ($score < -100) {
                    $hlc_suffix = 'L';
                } else {
                    $hlc_suffix = '';
                }

                $entry_author = $line['author'];

                if ($entry_author) {
                    $entry_author = " &mdash; $entry_author";
                }

                $has_feed_icon = \SmallSmallRSS\Feeds::hasIcon($feed_id);
                if ($has_feed_icon) {
                    $feed_icon_img = '<img class="tinyFeedIcon" src="'
                        . \SmallSmallRSS\Config::get('ICONS_URL')
                        . "/$feed_id.ico\" alt=\"\">";
                } else {
                    $feed_icon_img = '<img class="tinyFeedIcon" src="images/pub_set.svg" alt="">';
                }

                $entry_site_url = $line['site_url'];

                //setting feed headline background color, needs to change text color based on dark/light
                $fav_color = $line['favicon_avg_color'];

                if ($fav_color && $fav_color != 'fail') {
                    if (!isset($rgba_cache[$feed_id])) {
                        $rgba_cache[$feed_id] = join(',', \SmallSmallRSS\Colors::unpack($fav_color));
                    }
                }

                if (!\SmallSmallRSS\DBPrefs::read('COMBINED_DISPLAY_MODE')) {

                    if (\SmallSmallRSS\DBPrefs::read('VFEED_GROUP_BY_FEED')) {
                        if ($feed_id != $vgroup_last_feed && $line['feed_title']) {

                            $cur_feed_title = $line['feed_title'];
                            $vgroup_last_feed = $feed_id;

                            $cur_feed_title = htmlspecialchars($cur_feed_title);

                            $vf_catchup_link = (
                                "(<a class='catchup' onclick='catchupFeedInGroup($feed_id);' href='#'>"
                                . __('Mark as read')
                                . '</a>)'
                            );

                            echo "<div class='cdmFeedTitle'>".
                                "<div style=\"float: right\">$feed_icon_img</div>".
                                "<a class='title' href=\"#\" onclick=\"viewfeed($feed_id)\">".
                                $line['feed_title']."</a> $vf_catchup_link</div>";

                        }
                    }

                    $mouseover_attrs = "onmouseover='postMouseIn(event, $id)' onmouseout='postMouseOut($id)'";
                    echo "<div class='hl $class' id='RROW-$id' $mouseover_attrs>";
                    echo "<div class='hlLeft'>";
                    echo "<input data-dojo-type=\"dijit.form.CheckBox\"
                            type=\"checkbox\" onclick=\"toggleSelectRow2(this)\"
                            class='rchk'>";
                    echo "$marked_pic";
                    echo "$published_pic";
                    echo '</div>';
                    echo "<div onclick='return hlClicked(event, $id)'";
                    echo " class=\"hlTitle\"><span class='hlContent$hlc_suffix'>";
                    echo "<a id=\"RTITLE-$id\" class=\"title\" href=\"";
                    echo htmlspecialchars($line['link']);
                    echo '" onclick="">';
                    echo truncate_string($line['title'], 200);
                    if (\SmallSmallRSS\DBPrefs::read('SHOW_CONTENT_PREVIEW')) {
                        if ($content_preview) {
                            echo "<span class=\"contentPreview\"> - $content_preview</span>";
                        }
                    }
                    echo '</a></span>';
                    echo $labels_str;
                    echo '</div>';
                    echo '<span class="hlUpdated">';
                    if (!\SmallSmallRSS\DBPrefs::read('VFEED_GROUP_BY_FEED')) {
                        if ($line['feed_title']) {
                            $rgba = $rgba_cache[$feed_id];
                            echo "<a class=\"hlFeed\" style=\"background: rgba($rgba, 0.3)\"";
                            echo " href=\"#\" onclick=\"viewfeed($feed_id)\">";
                            echo truncate_string($line['feed_title'], 30);
                            echo '</a>';
                        }
                    }
                    echo "<div title='$date_entered_fmt'>$updated_fmt</div>";
                    echo '</span>';
                    echo '<div class="hlRight">';
                    echo $score_pic;
                    if ($line['feed_title'] && !\SmallSmallRSS\DBPrefs::read('VFEED_GROUP_BY_FEED')) {
                        echo "<span onclick=\"viewfeed($feed_id)\"
                            style=\"cursor : pointer\"
                            title=\"".htmlspecialchars($line['feed_title']);
                        echo "\">
                            $feed_icon_img<span>";
                    }
                    echo '</div>';
                    echo '</div>';
                } else {
                    if ($line['tag_cache']) {
                        $tags = explode(',', $line['tag_cache']);
                    } else {
                        $tags = false;
                    }
                    $line['content'] = sanitize(
                        $line['content_preview'],
                        \SmallSmallRSS\Database::fromSQLBool($line['hide_images']),
                        false,
                        $entry_site_url
                    );
                    \SmallSmallRSS\PluginHost::getInstance()->runHooks(
                        \SmallSmallRSS\Hooks::RENDER_ARTICLE_CDM,
                        $line
                    );
                    if (\SmallSmallRSS\DBPrefs::read('VFEED_GROUP_BY_FEED') && $line['feed_title']) {
                        if ($feed_id != $vgroup_last_feed) {
                            $cur_feed_title = $line['feed_title'];
                            $vgroup_last_feed = $feed_id;
                            $cur_feed_title = htmlspecialchars($cur_feed_title);
                            $vf_catchup_link = "(<a class='catchup'"
                                . " onclick='javascript:catchupFeedInGroup($feed_id);' href='#'>"
                                . __('mark as read')
                                .'</a>)';
                            $has_feed_icon = \SmallSmallRSS\Feeds::hasIcon($feed_id);
                            if ($has_feed_icon) {
                                $feed_icon_img = '<img class="tinyFeedIcon" src="'
                                    . \SmallSmallRSS\Config::get('ICONS_URL')
                                    ."/$feed_id.ico\" alt=\"\">";
                            }
                            echo "<div class='cdmFeedTitle'>".
                                "<div style=\"float: right\">$feed_icon_img</div>".
                                "<a href=\"#\" class='title' onclick=\"viewfeed($feed_id)\">".
                                $line['feed_title']."</a> $vf_catchup_link</div>";
                        }
                    }

                    $mouseover_attrs = "onmouseover='postMouseIn(event, $id)'
                        onmouseout='postMouseOut($id)'";

                    $expanded_class = $expand_cdm ? 'expanded' : 'expandable';

                    echo "<div class=\"cdm $expanded_class $class\"
                        id=\"RROW-$id\" $mouseover_attrs>";

                    echo '<div class="cdmHeader">';
                    echo '<div style="vertical-align : middle">';

                    echo "<input data-dojo-type=\"dijit.form.CheckBox\"
                            type=\"checkbox\" onclick=\"toggleSelectRow2(this, false, true)\"
                            class='rchk'>";

                    echo "$marked_pic";
                    echo "$published_pic";

                    echo '</div>';

                    echo "<span id=\"RTITLE-$id\"
                        onclick=\"return cdmClicked(event, $id);\"
                        class=\"titleWrap$hlc_suffix\">
                        <a class=\"title\"
                        target=\"_blank\" href=\"".
                        htmlspecialchars($line['link']);
                    echo '">'.
                        $line['title'] .
                        "</a> <span class=\"author\">$entry_author</span>";

                    echo $labels_str;

                    echo "<span class='collapseBtn' style='display: none'>
                        <img src=\"images/collapse.png\" onclick=\"cdmCollapseArticle(event, $id)\"
                        title=\"";
                    echo __('Collapse article');
                    echo '"/></span>';

                    if (!$expand_cdm) {
                        $content_hidden = 'style="display: none"';
                        $excerpt_hidden = '';
                    } else {
                        $content_hidden = '';
                        $excerpt_hidden = 'style="display: none"';
                    }

                    echo "<span $excerpt_hidden
                        id=\"CEXC-$id\" class=\"cdmExcerpt\"> - $content_preview</span>";
                    echo '</span>';

                    if (!\SmallSmallRSS\DBPrefs::read('VFEED_GROUP_BY_FEED')) {
                        if (@$line['feed_title']) {
                            $rgba = @$rgba_cache[$feed_id];

                            echo "<div class=\"hlFeed\">
                                <a href=\"#\" style=\"background-color: rgba($rgba,0.3)\"
                                onclick=\"viewfeed($feed_id)\">".
                                truncate_string($line['feed_title'], 30);
                            echo '</a>
                            </div>';
                        }
                    }

                    echo "<span class='updated' title='$date_entered_fmt'>
                        $updated_fmt</span>";

                    echo "<div class='scoreWrap' style=\"vertical-align : middle\">";
                    echo "$score_pic";

                    if (!\SmallSmallRSS\DBPrefs::read('VFEED_GROUP_BY_FEED') && $line['feed_title']) {
                        echo '<span style="cursor : pointer"
                            title="'.htmlspecialchars($line['feed_title']);
                        echo "\"
                            onclick=\"viewfeed($feed_id)\">$feed_icon_img</span>";
                    }
                    echo '</div>';

                    echo '</div>';

                    echo "<div class=\"cdmContent\" $content_hidden
                        onclick=\"return cdmClicked(event, $id);\"
                        id=\"CICD-$id\">";

                    echo "<div id=\"POSTNOTE-$id\">";
                    if ($line['note']) {
                        echo format_article_note($id, $line['note']);
                    }
                    echo '</div>';

                    echo '<div class="cdmContentInner">';

                    if ($line['orig_feed_id']) {

                        $tmp_result = \SmallSmallRSS\Database::query(
                            'SELECT * FROM ttrss_archived_feeds
                    WHERE id = '.$line['orig_feed_id']
                        );

                        if (\SmallSmallRSS\Database::num_rows($tmp_result) != 0) {

                            echo "<div clear='both'>";
                            echo __('Originally from:');

                            echo '&nbsp;';

                            $tmp_line = \SmallSmallRSS\Database::fetch_assoc($tmp_result);

                            echo "<a target='_blank'
                                href=' " . htmlspecialchars($tmp_line['site_url']) . "'>" .
                                $tmp_line['title'] . '</a>';

                            echo '&nbsp;';

                            echo "<a target='_blank' href='" . htmlspecialchars($tmp_line['feed_url']) . "'>";
                            echo "<img title='";
                            echo __('Feed URL');
                            echo "'class='tinyFeedIcon' src='images/pub_unset.svg'></a>";

                            echo '</div>';
                        }
                    }

                    echo "<span id=\"CWRAP-$id\">";
                    echo "<span id=\"CENCW-$id\" style=\"display: none\">";
                    echo htmlspecialchars($line['content']);
                    echo '</span.';
                    echo '</span>';
                    $always_display_enclosures = \SmallSmallRSS\Database::fromSQLBool($line['always_display_enclosures']);
                    echo format_article_enclosures(
                        $id,
                        $always_display_enclosures,
                        $line['content'],
                        \SmallSmallRSS\Database::fromSQLBool($line['hide_images'])
                    );
                    echo '</div>';
                    echo '<div class="cdmFooter">';
                    $left_button_hooks = \SmallSmallRSS\PluginHost::getInstance()->get_hooks(
                        \SmallSmallRSS\Hooks::ARTICLE_LEFT_BUTTON
                    );
                    foreach ($left_button_hooks as $p) {
                        echo $p->hookArticleLeftButton($line);
                    }
                    $tags_str = format_tags_string($tags, $id);
                    echo "<img src='images/tag.png' alt='Tags' title='Tags'>";
                    echo "<span id=\"ATSTR-$id\">$tags_str</span>";
                    echo '<a title="';
                    echo __('Edit tags for this article');
                    echo "\" href=\"#\" onclick=\"editArticleTags($id)\">(+)</a>";

                    $num_comments = $line['num_comments'];
                    $entry_comments = '';

                    if ($num_comments > 0) {
                        if ($line['comments']) {
                            $comments_url = htmlspecialchars($line['comments']);
                        } else {
                            $comments_url = htmlspecialchars($line['link']);
                        }
                        $entry_comments = "<a target='_blank' href=\"$comments_url\">$num_comments comments</a>";
                    } else {
                        if ($line['comments'] && $line['link'] != $line['comments']) {
                            $entry_comments = "<a target='_blank' href=\"".htmlspecialchars($line['comments']);
                            echo '">comments</a>';
                        }
                    }

                    if ($entry_comments) {
                        echo "&nbsp;($entry_comments)";
                    }

                    echo '<div style="float: right">';
                    $article_button_hooks = \SmallSmallRSS\PluginHost::getInstance()->get_hooks(
                        \SmallSmallRSS\Hooks::ARTICLE_BUTTON
                    );
                    foreach ($article_button_hooks as $p) {
                        echo $p->hookArticleButton($line);
                    }

                    echo '</div>';
                    echo '</div>';

                    echo '</div><hr/>';

                    echo '</div>';

                }

                $lnum += 1;
            }

        } else {
            $message = '';

            switch ($view_mode) {
                case 'unread':
                    $message = __('No unread articles found to display.');
                    break;
                case 'updated':
                    $message = __('No updated articles found to display.');
                    break;
                case 'marked':
                    $message = __('No starred articles found to display.');
                    break;
                default:
                    if ($feed < \SmallSmallRSS\Constants::LABEL_BASE_INDEX) {
                        $message = __('No articles found to display. You can assign articles to labels manually from article header context menu (applies to all selected articles) or use a filter.');
                    } else {
                        $message = __('No articles found to display.');
                    }
            }

            if (!$offset && $message) {
                echo "<div class=\"whiteBox\">";
                echo $message;
                echo '<p>';
                echo '<span class="insensitive">';
                $substring_for_date = \SmallSmallRSS\Database::getSubstringForDateFunction();
                $result = \SmallSmallRSS\Database::query(
                    'SELECT '.$substring_for_date.'(MAX(last_updated), 1, 19) AS last_updated
                     FROM ttrss_feeds
                     WHERE owner_uid = ' . $_SESSION['uid']
                );
                $last_updated = \SmallSmallRSS\Database::fetch_result($result, 0, 'last_updated');
                $last_updated = make_local_datetime($last_updated, false);
                echo sprintf(__('Feeds last updated at %s'), $last_updated);
                $result = \SmallSmallRSS\Database::query(
                    "SELECT COUNT(id) AS num_errors
                    FROM ttrss_feeds WHERE last_error != '' AND owner_uid = ".$_SESSION['uid']
                );
                $num_errors = \SmallSmallRSS\Database::fetch_result($result, 0, 'num_errors');
                if ($num_errors > 0) {
                    echo '<br/>';
                    echo '<a class="insensitive" href="#" onclick="showFeedsWithErrors()">'.
                        __('Some feeds have update errors (click for details)');
                    echo '</a>';
                }
                echo '</span></p></div>';
            }
        }
        $reply['content'] = ob_get_clean();
        if (false === $reply['content']) {
            \SmallSmallRSS\Logger::log('No content!');
        };
        return array(
            $topmost_article_ids,
            $headlines_count,
            $feed,
            $disable_cache,
            $vgroup_last_feed,
            $reply
        );
    }

    public function catchupAll()
    {
        \SmallSmallRSS\Database::query(
            'UPDATE ttrss_user_entries
             SET
                 last_read = NOW(),
                 unread = false
             WHERE
                 unread = true
                 AND owner_uid = ' . $_SESSION['uid']
        );
        \SmallSmallRSS\CountersCache::zero_all($_SESSION['uid']);
    }

    public function view()
    {
        $timing_info = microtime(true);
        $reply = array('headlines' => array());
        $omode = $this->getSQLEscapedStringFromRequest('omode');
        $feed = $this->getSQLEscapedStringFromRequest('feed');
        $method = $this->getSQLEscapedStringFromRequest('m');
        $view_mode = $this->getSQLEscapedStringFromRequest('view_mode');
        $limit = 30;
        $cat_view = $_REQUEST['cat'] == 'true';
        $next_unread_feed = $this->getSQLEscapedStringFromRequest('nuf');
        $offset = $this->getSQLEscapedStringFromRequest('skip');
        $vgroup_last_feed = $this->getSQLEscapedStringFromRequest('vgrlf');
        $order_by = $this->getSQLEscapedStringFromRequest('order_by');

        if (is_numeric($feed)) {
            $feed = (int) $feed;
        }

        /* Feed -5 is a special case: it is used to display auxiliary information
         * when there's nothing to load - e.g. no stuff in fresh feed */

        if ($feed == -5) {
            echo json_encode($this->generate_dashboard_feed());
            return;
        }

        $result = false;

        $feed_type = 'Unknown';
        if ($feed < \SmallSmallRSS\Constants::LABEL_BASE_INDEX) {
            $feed_type = 'labels2';
            $label_feed = feed_to_label_id($feed);
            $result = \SmallSmallRSS\Database::query(
                "SELECT id
                 FROM ttrss_labels2
                 WHERE
                     id = '$label_feed'
                     AND owner_uid = " . $_SESSION['uid']
            );
        } elseif (!$cat_view && is_numeric($feed) && $feed > 0) {
            $feed_type = 'feeds';
            $result = \SmallSmallRSS\Database::query(
                "SELECT id
                 FROM ttrss_feeds
                 WHERE
                     id = '$feed'
                     AND owner_uid = " . $_SESSION['uid']
            );
        } elseif ($cat_view && is_numeric($feed) && $feed > 0) {
            $feed_type = 'feed_categories';
            $result = \SmallSmallRSS\Database::query(
                "SELECT id
                 FROM ttrss_feed_categories
                 WHERE
                     id = '$feed'
                     AND owner_uid = " . $_SESSION['uid']
            );
        }

        if ($result && \SmallSmallRSS\Database::num_rows($result) == 0) {
            \SmallSmallRSS\Logger::log("Feed not found: $feed_type $feed");
            echo json_encode($this->generate_error_feed(__('Feed not found.')));
            return;
        }

        /* Updating a label ccache means recalculating all of the caches
         * so for performance reasons we don't do that here */

        if ($feed >= 0) {
            \SmallSmallRSS\CountersCache::update($feed, $_SESSION['uid'], $cat_view);
        }

        \SmallSmallRSS\DBPrefs::write('_DEFAULT_VIEW_MODE', $view_mode);
        \SmallSmallRSS\DBPrefs::write('_DEFAULT_VIEW_ORDER_BY', $order_by);

        /* bump login timestamp if needed */
        if (time() - $_SESSION['last_login_update'] > 3600) {
            \SmallSmallRSS\Database::query(
                'UPDATE ttrss_users SET last_login = NOW() WHERE id = ' .
                $_SESSION['uid']
            );
            $_SESSION['last_login_update'] = time();
        }

        if (!$cat_view && is_numeric($feed) && $feed > 0) {
            \SmallSmallRSS\Database::query(
                "UPDATE ttrss_feeds
                 SET last_viewed = NOW()
                 WHERE id = '$feed' AND owner_uid = ".$_SESSION['uid']
            );
        }
        if (!$next_unread_feed) {
            $reply['headlines']['id'] = $feed;
        } else {
            $reply['headlines']['id'] = $next_unread_feed;
        }
        $reply['headlines']['is_cat'] = (bool) $cat_view;
        $override_order = false;
        switch ($order_by) {
            case 'title':
                $override_order = 'ttrss_entries.title';
                break;
            case 'date_reverse':
                $override_order = 'date_entered, updated';
                break;
            case 'feed_dates':
                $override_order = 'updated DESC';
                break;
        }
        $ret = $this->formatHeadlinesList(
            $feed, $method,
            $view_mode, $limit, $cat_view, $next_unread_feed, $offset,
            $vgroup_last_feed, $override_order, true
        );

        $headlines_count = $ret[1];
        $returned_feed = $ret[2];
        $disable_cache = $ret[3];
        $vgroup_last_feed = $ret[4];

        $reply['headlines']['content'] = $ret[5]['content'];
        $reply['headlines']['toolbar'] = $ret[5]['toolbar'];

        $reply['headlines-info'] = array(
            'count' => (int) $headlines_count,
            'vgroup_last_feed' => $vgroup_last_feed,
            'disable_cache' => (bool) $disable_cache
        );
        $reply['runtime-info'] = make_runtime_info();
        echo json_encode($reply);
    }

    private function generate_dashboard_feed()
    {
        $reply = array('headlines' => array());
        $reply['headlines']['label'] = __('Dashboard Feed');
        $reply['headlines']['id'] = -5;
        $reply['headlines']['is_cat'] = false;
        $reply['headlines']['toolbar'] = '';

        $substring_for_date = \SmallSmallRSS\Database::getSubstringForDateFunction();
        $result = \SmallSmallRSS\Database::query(
            'SELECT ' . $substring_for_date . '(MAX(last_updated), 1, 19) AS last_updated
             FROM ttrss_feeds
             WHERE owner_uid = ' . $_SESSION['uid']
        );

        $last_updated = \SmallSmallRSS\Database::fetch_result($result, 0, 'last_updated');
        $last_updated = make_local_datetime($last_updated, false);


        $result = \SmallSmallRSS\Database::query(
            "SELECT COUNT(id) AS num_errors
             FROM ttrss_feeds
             WHERE
                 last_error != ''
                 AND owner_uid = " . $_SESSION['uid']
        );
        $num_errors = \SmallSmallRSS\Database::fetch_result($result, 0, 'num_errors');

        ob_start();
        echo '<div class="whiteBox">';
        echo __('No feed selected.');
        echo '<p><span class="insensitive">';
        echo sprintf(__('Feeds last updated at %s'), $last_updated);
        if ($num_errors > 0) {
            echo '<br/>';
            echo '<a class="insensitive" href="#" onclick="showFeedsWithErrors()">'.
                __('Some feeds have update errors (click for details)');
            echo '</a>';
        }
        echo '</span></p>';
        $reply['headlines']['content'] = ob_get_clean();

        $reply['headlines-info'] = array(
            'count' => 0,
            'vgroup_last_feed' => '',
            'unread' => 0,
            'disable_cache' => true
        );
        return $reply;
    }

    private function generate_error_feed($error)
    {
        $reply = array();
        $reply['headlines']['label'] = __('Error Feed');
        $reply['headlines']['id'] = -6;
        $reply['headlines']['is_cat'] = false;
        $reply['headlines']['toolbar'] = '';
        $reply['headlines']['content'] = '<div class="whiteBox">'. $error . '</div>';

        $reply['headlines-info'] = array(
            'count' => 0,
            'vgroup_last_feed' => '',
            'unread' => 0,
            'disable_cache' => true
        );

        return $reply;
    }

    public function quickAddFeed()
    {
        echo '<input data-dojo-type="dijit.form.TextBox" style="display: none" name="op" value="rpc">';
        echo '<input data-dojo-type="dijit.form.TextBox" style="display: none" name="method" value="addfeed">';
        echo '<div class="dlgSec">';
        echo __('Feed or site URL');
        echo '</div>';
        echo '<div class="dlgSecCont">';
        echo "<div style='float: right'>";
        echo "<img style='display: none' id='feed_add_spinner' src='images/indicator_white.gif'></div>";
        echo '<input style="font-size: 16px; width: 20em;" placeHolder="';
        echo __('Feed or site URL');
        echo '" data-dojo-type="dijit.form.ValidationTextBox" required="1" name="feed" id="feedDlg_feedUrl">';
        echo '<hr/>';
        if (\SmallSmallRSS\DBPrefs::read('ENABLE_FEED_CATS')) {
            echo __('Place in category:') . ' ';
            print_feed_cat_select('cat', false, 'data-dojo-type="dijit.form.Select"');
        }
        echo '</div>';
        echo '<div id="feedDlg_feedsContainer" style="display: none">';
        echo '<div class="dlgSec">' . __('Available feeds') . '</div>';
        echo '<div class="dlgSecCont">';
        echo '<select id="feedDlg_feedContainerSelect" data-dojo-type="dijit.form.Select" size="3">';
        echo '<script type="dojo/method" event="onChange" args="value">';
        echo 'dijit.byId("feedDlg_feedUrl").attr("value", value);';
        echo '</script>';
        echo '</select>';
        echo '</div>';
        echo '</div>';
        echo "<div id='feedDlg_loginContainer' style='display: none'>";
        echo '<div class="dlgSec">';
        echo __('Authentication');
        echo '</div>';
        echo '<div class="dlgSecCont">';
        echo " <input data-dojo-type=\"dijit.form.TextBox\" name='login'\"
                    placeHolder=\"";
        echo __('Login');
        echo '" style="width: 10em;">';
        echo ' <input placeHolder="';
        echo __('Password');
        echo "\" data-dojo-type=\"dijit.form.TextBox\" type='password'";
        echo "style=\"width: 10em;\" name='pass'\">";
        echo '</div>';
        echo '</div>';
        echo '<div style="clear: both">';
        echo '<input type="checkbox" name="need_auth" data-dojo-type="dijit.form.CheckBox" id="feedDlg_loginCheck"';
        echo " onclick='checkboxToggleElement(this, \"feedDlg_loginContainer\")'>";
        echo '<label for="feedDlg_loginCheck">';
        echo __('This feed requires authentication.');
        echo '</div>';
        echo '</form>';
        echo '<div class="dlgButtons">';
        echo "<button data-dojo-type=\"dijit.form.Button\" onclick=\"return dijit.byId('feedAddDlg').execute()\">";
        echo __('Subscribe');
        echo '</button>';
        if (!(defined('_DISABLE_FEED_BROWSER') && _DISABLE_FEED_BROWSER)) {
            echo '<button data-dojo-type="dijit.form.Button" onclick="return feedBrowser()">';
            echo __('More feeds');
            echo '</button>';
        }
        echo "<button data-dojo-type=\"dijit.form.Button\" onclick=\"return dijit.byId('feedAddDlg').hide()\">";
        echo __('Cancel');
        echo '</button>';
        echo '</div>';
    }

    public function feedBrowser()
    {
        if (defined('_DISABLE_FEED_BROWSER') && _DISABLE_FEED_BROWSER) {
            return;
        }
        $browser_search = $this->getSQLEscapedStringFromRequest('search');
        echo '<input data-dojo-type="dijit.form.TextBox" style="display: none" name="op" value="rpc">';
        echo '<input data-dojo-type="dijit.form.TextBox" style="display: none" name="method" value="updateFeedBrowser">';

        echo "<div data-dojo-type=\"dijit.Toolbar\">
            <div style='float: right'>
            <img style='display: none'
                id='feed_browser_spinner' src='images/indicator_white.gif'>
            <input name=\"search\" data-dojo-type=\"dijit.form.TextBox\" size=\"20\" type=\"search\"
                onchange=\"dijit.byId('feedBrowserDlg').update()\" value=\"$browser_search\">
            <button data-dojo-type=\"dijit.form.Button\" onclick=\"dijit.byId('feedBrowserDlg').update()\">";
        echo __('Search');
        echo '</button>
        </div>';

        echo " <select name=\"mode\" data-dojo-type=\"dijit.form.Select\" onchange=\"dijit.byId('feedBrowserDlg').update()\">
            <option value='1'>" . __('Popular feeds') . "</option>
            <option value='2'>" . __('Feed archive') . '</option>
            </select> ';

        echo __('limit:');

        echo " <select data-dojo-type=\"dijit.form.Select\" name=\"limit\" onchange=\"dijit.byId('feedBrowserDlg').update()\">";

        foreach (array(25, 50, 100, 200) as $l) {
            $issel = ($l == $limit) ? 'selected="1"' : '';
            echo "<option $issel value=\"$l\">$l</option>";
        }

        echo '</select> ';

        echo '</div>';

        $owner_uid = $_SESSION['uid'];

        echo "<ul class='browseFeedList' id='browseFeedList'>";
        $feedbrowser = \SmallSmallRSS\Renderers\FeedBrower(
            $search,
            25,
            \SmallSmallRSS\Renderers\FeedBrower::MODE_1
        );
        $feedbrowser->render();
        echo '</ul>';
        echo '<div align="center">';
        echo "<button data-dojo-type=\"dijit.form.Button\" onclick=\"dijit.byId('feedBrowserDlg').execute()\">";
        echo __('Subscribe');
        echo '</button>';
        echo '<button data-dojo-type="dijit.form.Button" style="display: none" id="feed_archive_remove"';
        echo " onclick=\"dijit.byId('feedBrowserDlg').removeFromArchive()\">";
        echo __('Remove');
        echo '</button>';
        echo "<button data-dojo-type=\"dijit.form.Button\" onclick=\"dijit.byId('feedBrowserDlg').hide()\" >";
        echo __('Cancel');
        echo '</button>';
        echo '</div>';
    }

    public function search()
    {
        $this->params = explode(':', $this->getSQLEscapedStringFromRequest('param'), 2);
        $active_feed_id = sprintf('%d', $this->params[0]);
        $is_cat = $this->params[1] != 'false';
        echo '<div class="dlgSec">';
        echo __('Look for');
        echo '</div>';
        echo '<div class="dlgSecCont">';
        echo "<input data-dojo-type=\"dijit.form.ValidationTextBox\"
            style=\"font-size: 16px; width: 20em;\"
            required=\"1\" name=\"query\" type=\"search\" value=''>";
        echo '<hr/>';
        echo __('Limit search to:');
        echo ' ';
        echo '<select name="search_mode" data-dojo-type="dijit.form.Select">
            <option value="all_feeds">';
        echo __('All feeds');
        echo '</option>';
        $feed_title = self::getTitle($active_feed_id);
        if (!$is_cat) {
            $feed_cat_title = \SmallSmallRSS\Feeds::getCategoryTitle($active_feed_id);
        } else {
            $feed_cat_title = \SmallSmallRSS\FeedCategories::getTitle($active_feed_id);
        }
        if ($active_feed_id && !$is_cat) {
            echo "<option selected=\"1\" value=\"this_feed\">$feed_title</option>";
        } else {
            echo '<option disabled="1" value="false">';
            echo __('This feed');
            echo '</option>';
        }
        if ($is_cat) {
            $cat_preselected = 'selected="1"';
        }
        if (\SmallSmallRSS\DBPrefs::read('ENABLE_FEED_CATS') && ($active_feed_id > 0 || $is_cat)) {
            echo "<option $cat_preselected value=\"this_cat\">$feed_cat_title</option>";
        }
        echo '</select>';
        echo '</div>';
        echo '<div class="dlgButtons">';
        if (!\SmallSmallRSS\Config::get('SPHINX_ENABLED')) {
            echo '<div style="float: left">';
            echo '<a class="visibleLink" target="_blank" href="http://tt-rss.org/wiki/SearchSyntax">Search syntax</a>';
            echo '</div>';
        }
        echo "<button data-dojo-type=\"dijit.form.Button\" onclick=\"dijit.byId('searchDlg').execute()\">";
        echo __('Search');
        echo '</button>';
        echo "<button data-dojo-type=\"dijit.form.Button\" onclick=\"dijit.byId('searchDlg').hide()\">";
        echo __('Cancel');
        echo '</button>';
        echo '</div>';
    }

}
