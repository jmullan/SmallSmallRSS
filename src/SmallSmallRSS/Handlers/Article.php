<?php
namespace SmallSmallRSS\Handlers;

class Article extends ProtectedHandler
{
    public function ignoreCSRF($method)
    {
        $csrf_ignored = array('redirect', 'editarticletags');

        return array_search($method, $csrf_ignored) !== false;
    }

    public function redirect()
    {
        $id = \SmallSmallRSS\Database::escape_string($_REQUEST['id']);
        $result = \SmallSmallRSS\Database::query(
            "SELECT link
             FROM ttrss_entries, ttrss_user_entries
             WHERE id = '$id'
                 AND id = ref_id
                 AND owner_uid = '".$_SESSION['uid']."'
             LIMIT 1"
        );

        if (\SmallSmallRSS\Database::num_rows($result) == 1) {
            $article_url = \SmallSmallRSS\Database::fetch_result($result, 0, 'link');
            $article_url = str_replace("\n", '', $article_url);

            header("Location: $article_url");
            return;

        } else {
            \SmallSmallRSS\Renderers\Messages::renderError(__('Article not found.'));
        }
    }

    public function view()
    {
        $id = \SmallSmallRSS\Database::escape_string($_REQUEST['id']);
        $cids = explode(',', \SmallSmallRSS\Database::escape_string($_REQUEST['cids']));
        $mode = \SmallSmallRSS\Database::escape_string($_REQUEST['mode']);
        $omode = \SmallSmallRSS\Database::escape_string($_REQUEST['omode']);

        // in prefetch mode we only output requested cids, main article
        // just gets marked as read (it already exists in client cache)

        $articles = array();

        if ($mode == '') {
            array_push($articles, format_article($id, false));
        } elseif ($mode == 'zoom') {
            array_push($articles, format_article($id, true, true));
        } elseif ($mode == 'raw') {
            if ($_REQUEST['html']) {
                header('Content-Type: text/html');
                echo '<link rel="stylesheet" type="text/css" href="css/tt-rss.css" />';
            }

            $article = format_article($id, false);
            echo $article['content'];
            return;
        }

        $this->catchupArticlesById($id, \SmallSmallRSS\MarkModes::MARK, $_SESSION['uid']);

        if (!$_SESSION['bw_limit']) {
            foreach ($cids as $cid) {
                if ($cid) {
                    array_push($articles, format_article($cid, false, false));
                }
            }
        }

        echo json_encode($articles);
    }

    public static function createPublishedArticle(
        $title,
        $url,
        $content,
        $labels_str,
        $owner_uid
    ) {
        # TODO: Move this into a model class
        $guid = 'SHA1:' . sha1('ttshared:' . $url . $owner_uid);
        $content_hash = sha1($content);
        if ($labels_str != '') {
            $labels = explode(',', $labels_str);
        } else {
            $labels = array();
        }
        $rc = false;
        if (!$title) {
            $title = $url;
        }
        if (!$title && !$url) {
            return false;
        }
        if (filter_var($url, FILTER_VALIDATE_URL) === false) {
            return false;
        }
        \SmallSmallRSS\Database::query('BEGIN');
        // only check for our user data here, others might have shared this with different content etc
        $result = \SmallSmallRSS\Database::query(
            "SELECT ref_id
             FROM ttrss_entries, ttrss_user_entries
             WHERE
                 link = '$url'
                 AND ref_id = id
                 AND owner_uid = '$owner_uid'
             LIMIT 1"
        );
        if (\SmallSmallRSS\Database::num_rows($result) != 0) {
            $ref_id = \SmallSmallRSS\Database::fetch_result($result, 0, 'ref_id');
            $int_id = \SmallSmallRSS\UserEntries::getIntId($ref_id, $owner_uid);
            if (!is_null($int_id)) {
                \SmallSmallRSS\Database::query(
                    "UPDATE ttrss_entries
                     SET
                         content = '$content',
                         content_hash = '$content_hash'
                     WHERE id = '$ref_id'"
                );
                \SmallSmallRSS\UserEntries::publishIds(array($ref_id), $owner_uid);
            } else {
                \SmallSmallRSS\Database::query(
                    "INSERT INTO ttrss_user_entries
                     (ref_id, uuid, feed_id, orig_feed_id, owner_uid, published, tag_cache, label_cache,
                         last_read, note, unread, last_published)
                     VALUES
                     ('$ref_id', '', NULL, NULL, $owner_uid, true, '', '', NOW(), '', false, NOW())"
                );
            }
            if (count($labels) != 0) {
                foreach ($labels as $label) {
                    \SmallSmallRSS\Labels::addArticle($ref_id, trim($label), $owner_uid);
                }
            }
            $rc = true;
        } else {
            $result = \SmallSmallRSS\Database::query(
                "INSERT INTO ttrss_entries
                 (title, guid, link, updated, content, content_hash, date_entered, date_updated)
                 VALUES
                 ('$title', '$guid', '$url', NOW(), '$content', '$content_hash', NOW(), NOW())"
            );
            $result = \SmallSmallRSS\Database::query("SELECT id FROM ttrss_entries WHERE guid = '$guid'");
            if (\SmallSmallRSS\Database::num_rows($result) != 0) {
                $ref_id = \SmallSmallRSS\Database::fetch_result($result, 0, 'id');
                \SmallSmallRSS\Database::query(
                    "INSERT INTO ttrss_user_entries
                     (ref_id, uuid, feed_id, orig_feed_id, owner_uid, published, tag_cache, label_cache,
                         last_read, note, unread, last_published)
                     VALUES
                     ('$ref_id', '', NULL, NULL, $owner_uid, true, '', '', NOW(), '', false, NOW())"
                );
                if (count($labels) != 0) {
                    foreach ($labels as $label) {
                        \SmallSmallRSS\Labels::addArticle($ref_id, trim($label), $owner_uid);
                    }
                }
                $rc = true;
            }
        }
        \SmallSmallRSS\Database::query('COMMIT');
        return $rc;
    }

    public function editArticleTags()
    {
        echo __('Tags for this article (separated by commas):');
        echo '<br />';
        $param = $_REQUEST['param'];
        $tags = get_article_tags($param, $_SESSION['uid']);
        $tags_str = join(', ', $tags);
        echo "<input data-dojo-type=\"dijit.form.TextBox\" style=\"display: none\" name=\"id\" value=\"$param\">";
        echo '<input data-dojo-type="dijit.form.TextBox" style="display: none" name="op" value="article">';
        echo '<input data-dojo-type="dijit.form.TextBox" style="display: none" name="method" value="setArticleTags">';
        echo "<table width='100%'><tr><td>";
        echo '<textarea data-dojo-type="dijit.form.SimpleTextarea" rows="4"';
        echo " style='font-size: 12px; width: 100%' id=\"tags_str\"";
        echo ' name="tags_str">';
        echo $tags_str;
        echo '</textarea>';
        echo '<div class="autocomplete" id="tags_choices" style="display: none"></div>';
        echo '</td></tr></table>';
        echo "<div class='dlgButtons'>";
        echo "<button data-dojo-type=\"dijit.form.Button\" onclick=\"dijit.byId('editTagsDlg').execute()\">";
        echo __('Save');
        echo '</button> ';
        echo "<button data-dojo-type=\"dijit.form.Button\" onclick=\"dijit.byId('editTagsDlg').hide()\">";
        echo __('Cancel');
        echo '</button>';
        echo '</div>';

    }
    public function setScore()
    {
        $ids = \SmallSmallRSS\Database::escape_string($_REQUEST['id']);
        $score = (int) \SmallSmallRSS\Database::escape_string($_REQUEST['score']);
        \SmallSmallRSS\Database::query(
            "UPDATE ttrss_user_entries
             SET score = '$score'
             WHERE
                 ref_id IN ($ids)
                 AND owner_uid = " . $_SESSION['uid']
        );
        echo json_encode(array('id' => $ids, 'score_pic' => get_score_pic($score)));
    }


    public function setArticleTags()
    {
        $id = $_REQUEST['id'];
        $tags_str = $_REQUEST['tags_str'];
        $tags = array_unique(array_map('trim', (explode(',', $tags_str))));
        \SmallSmallRSS\Database::query('BEGIN');
        $int_id = \SmallSmallRSS\UserEntries::getIntId($id, $_SESSION['uid']);
        if (!is_null($int_id)) {
            \SmallSmallRSS\Tags::deleteForPost($int_id, $_SESSION['uid']);
            $tags = \SmallSmallRSS\Tags::setForPost($int_id, $_SESSION['uid'], $tags);
            /* update tag cache */
            sort($tags);
            $tags_str = join(',', $tags);
            \SmallSmallRSS\UserEntries::setCachedTags($id, $_SESSION['uid'], $tags_str);
        }
        \SmallSmallRSS\Database::query('COMMIT');
        $tags = get_article_tags($id, $_SESSION['uid']);
        $tags_str = format_tags_string($tags, $id);
        $tags_str_full = join(', ', $tags);
        if (!$tags_str_full) {
            $tags_str_full = __('no tags');
        }
        echo json_encode(
            array(
                'id' => (int) $id,
                'content' => $tags_str,
                'content_full' => $tags_str_full
            )
        );
    }

    public function completeTags()
    {
        $search = \SmallSmallRSS\Database::escape_string($_REQUEST['search']);
        $result = \SmallSmallRSS\Database::query(
            "SELECT DISTINCT tag_name FROM ttrss_tags
                WHERE owner_uid = '".$_SESSION['uid']."' AND
                tag_name LIKE '$search%' ORDER BY tag_name
                LIMIT 10"
        );
        echo '<ul>';
        while ($line = \SmallSmallRSS\Database::fetch_assoc($result)) {
            echo '<li>' . $line['tag_name'] . '</li>';
        }
        echo '</ul>';
    }

    public function assigntolabel()
    {
        return $this->labelops(true);
    }

    public function removefromlabel()
    {
        return $this->labelops(false);
    }

    private function labelops($assign)
    {
        $reply = array();
        $ids = explode(',', \SmallSmallRSS\Database::escape_string($_REQUEST['ids']));
        $label_id = \SmallSmallRSS\Database::escape_string($_REQUEST['lid']);
        $label = \SmallSmallRSS\Database::escape_string(
            \SmallSmallRSS\Labels::findCaption($label_id, $_SESSION['uid'])
        );
        $reply['info-for-headlines'] = array();
        if ($label) {
            foreach ($ids as $id) {
                if ($assign) {
                    \SmallSmallRSS\Labels::addArticle($id, $label, $_SESSION['uid']);
                } else {
                    \SmallSmallRSS\Labels::removeArticle($id, $label, $_SESSION['uid']);
                }
                $labels = \SmallSmallRSS\Labels::getForArticle($id, $_SESSION['uid']);
                array_push(
                    $reply['info-for-headlines'],
                    array(
                        'id' => $id,
                        'labels' => format_article_labels($labels, $id)
                    )
                );
            }
        }
        $reply['message'] = 'UPDATE_COUNTERS';
        echo json_encode($reply);
    }
}
