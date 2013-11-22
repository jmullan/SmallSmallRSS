<?php
namespace SmallSmallRSS\Handlers;
class Article extends ProtectedHandler
{

    function ignoreCSRF($method)
    {
        $csrf_ignored = array("redirect", "editarticletags");

        return array_search($method, $csrf_ignored) !== false;
    }

    function redirect()
    {
        $id = \SmallSmallRSS\Database::escape_string($_REQUEST['id']);

        $result = \SmallSmallRSS\Database::query(
            "SELECT link FROM ttrss_entries, ttrss_user_entries
                        WHERE id = '$id' AND id = ref_id AND owner_uid = '".$_SESSION['uid']."'
                        LIMIT 1"
        );

        if (\SmallSmallRSS\Database::num_rows($result) == 1) {
            $article_url = \SmallSmallRSS\Database::fetch_result($result, 0, 'link');
            $article_url = str_replace("\n", "", $article_url);

            header("Location: $article_url");
            return;

        } else {
             \SmallSmallRSS\Renderers\Messages::renderError(__("Article not found."));
        }
    }

    function view()
    {
        $id = \SmallSmallRSS\Database::escape_string($_REQUEST["id"]);
        $cids = explode(",", \SmallSmallRSS\Database::escape_string($_REQUEST["cids"]));
        $mode = \SmallSmallRSS\Database::escape_string($_REQUEST["mode"]);
        $omode = \SmallSmallRSS\Database::escape_string($_REQUEST["omode"]);

        // in prefetch mode we only output requested cids, main article
        // just gets marked as read (it already exists in client cache)

        $articles = array();

        if ($mode == "") {
            array_push($articles, format_article($id, false));
        } elseif ($mode == "zoom") {
            array_push($articles, format_article($id, true, true));
        } elseif ($mode == "raw") {
            if ($_REQUEST['html']) {
                header("Content-Type: text/html");
                print '<link rel="stylesheet" type="text/css" href="css/tt-rss.css"/>';
            }

            $article = format_article($id, false);
            print $article['content'];
            return;
        }

        $this->catchupArticleById($id, 0);

        if (!$_SESSION["bw_limit"]) {
            foreach ($cids as $cid) {
                if ($cid) {
                    array_push($articles, format_article($cid, false, false));
                }
            }
        }

        print json_encode($articles);
    }

    private function catchupArticleById($id, $cmode)
    {

        if ($cmode == 0) {
            \SmallSmallRSS\Database::query(
                "UPDATE ttrss_user_entries SET
            unread = false,last_read = NOW()
            WHERE ref_id = '$id' AND owner_uid = " . $_SESSION["uid"]
            );
        } elseif ($cmode == 1) {
            \SmallSmallRSS\Database::query(
                "UPDATE ttrss_user_entries SET
            unread = true
            WHERE ref_id = '$id' AND owner_uid = " . $_SESSION["uid"]
            );
        } else {
            \SmallSmallRSS\Database::query(
                "UPDATE ttrss_user_entries SET
            unread = NOT unread,last_read = NOW()
            WHERE ref_id = '$id' AND owner_uid = " . $_SESSION["uid"]
            );
        }

        $feed_id = getArticleFeed($id);
        \SmallSmallRSS\CounterCache::update($feed_id, $_SESSION["uid"]);
    }

    static function create_published_article($title, $url, $content, $labels_str,
                                             $owner_uid) {
        $guid = 'SHA1:' . sha1("ttshared:" . $url . $owner_uid);
        $content_hash = sha1($content);

        if ($labels_str != "") {
            $labels = explode(",", $labels_str);
        } else {
            $labels = array();
        }

        $rc = false;

        if (!$title) {  $title = $url;
        }
        if (!$title && !$url) {  return false;
        }

        if (filter_var($url, FILTER_VALIDATE_URL) === false) {  return false;
        }

        \SmallSmallRSS\Database::query("BEGIN");

        // only check for our user data here, others might have shared this with different content etc
        $result = \SmallSmallRSS\Database::query(
            "SELECT id FROM ttrss_entries, ttrss_user_entries WHERE
            link = '$url' AND ref_id = id AND owner_uid = '$owner_uid' LIMIT 1"
        );

        if (\SmallSmallRSS\Database::num_rows($result) != 0) {
            $ref_id = \SmallSmallRSS\Database::fetch_result($result, 0, "id");

            $result = \SmallSmallRSS\Database::query(
                "SELECT int_id FROM ttrss_user_entries WHERE
                ref_id = '$ref_id' AND owner_uid = '$owner_uid' LIMIT 1"
            );

            if (\SmallSmallRSS\Database::num_rows($result) != 0) {
                $int_id = \SmallSmallRSS\Database::fetch_result($result, 0, "int_id");

                \SmallSmallRSS\Database::query(
                    "UPDATE ttrss_entries SET
                    content = '$content', content_hash = '$content_hash' WHERE id = '$ref_id'"
                );

                \SmallSmallRSS\Database::query(
                    "UPDATE ttrss_user_entries SET published = true,
                        last_published = NOW() WHERE
                        int_id = '$int_id' AND owner_uid = '$owner_uid'"
                );
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
                $ref_id = \SmallSmallRSS\Database::fetch_result($result, 0, "id");

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

        \SmallSmallRSS\Database::query("COMMIT");

        return $rc;
    }

    function editArticleTags()
    {

        print __("Tags for this article (separated by commas):")."<br>";

        $param = \SmallSmallRSS\Database::escape_string($_REQUEST['param']);

        $tags = get_article_tags(\SmallSmallRSS\Database::escape_string($param));

        $tags_str = join(", ", $tags);

        print "<input dojoType=\"dijit.form.TextBox\" style=\"display: none\" name=\"id\" value=\"$param\">";
        print "<input dojoType=\"dijit.form.TextBox\" style=\"display: none\" name=\"op\" value=\"article\">";
        print "<input dojoType=\"dijit.form.TextBox\" style=\"display: none\" name=\"method\" value=\"setArticleTags\">";

        print "<table width='100%'><tr><td>";

        print "<textarea dojoType=\"dijit.form.SimpleTextarea\" rows='4'
            style='font-size: 12px; width: 100%' id=\"tags_str\"
            name='tags_str'>$tags_str</textarea>
        <div class=\"autocomplete\" id=\"tags_choices\"
                style=\"display:none\"></div>";

        print "</td></tr></table>";

        print "<div class='dlgButtons'>";

        print "<button dojoType=\"dijit.form.Button\"
            onclick=\"dijit.byId('editTagsDlg').execute()\">".__('Save')."</button> ";
        print "<button dojoType=\"dijit.form.Button\"
            onclick=\"dijit.byId('editTagsDlg').hide()\">".__('Cancel')."</button>";
        print "</div>";

    }

    function setScore()
    {
        $ids = \SmallSmallRSS\Database::escape_string($_REQUEST['id']);
        $score = (int) \SmallSmallRSS\Database::escape_string($_REQUEST['score']);

        \SmallSmallRSS\Database::query(
            "UPDATE ttrss_user_entries SET
            score = '$score' WHERE ref_id IN ($ids) AND owner_uid = " . $_SESSION["uid"]
        );

        print json_encode(array("id" => $ids,
                                "score_pic" => get_score_pic($score)));
    }


    function setArticleTags()
    {

        $id = \SmallSmallRSS\Database::escape_string($_REQUEST["id"]);

        $tags_str = \SmallSmallRSS\Database::escape_string($_REQUEST["tags_str"]);
        $tags = array_unique(trim_array(explode(",", $tags_str)));

        \SmallSmallRSS\Database::query("BEGIN");

        $result = \SmallSmallRSS\Database::query(
            "SELECT int_id FROM ttrss_user_entries WHERE
                ref_id = '$id' AND owner_uid = '".$_SESSION["uid"]."' LIMIT 1"
        );

        if (\SmallSmallRSS\Database::num_rows($result) == 1) {

            $tags_to_cache = array();

            $int_id = \SmallSmallRSS\Database::fetch_result($result, 0, "int_id");

            \SmallSmallRSS\Database::query(
                "DELETE FROM ttrss_tags WHERE
                post_int_id = $int_id AND owner_uid = '".$_SESSION["uid"]."'"
            );

            foreach ($tags as $tag) {
                $tag = sanitize_tag($tag);

                if (!tag_is_valid($tag)) {
                    continue;
                }

                if (preg_match("/^[0-9]*$/", $tag)) {
                    continue;
                }

                //                    print "<!-- $id : $int_id : $tag -->";

                if ($tag != '') {
                    \SmallSmallRSS\Database::query(
                        "INSERT INTO ttrss_tags
                                (post_int_id, owner_uid, tag_name) VALUES ('$int_id', '".$_SESSION["uid"]."', '$tag')"
                    );
                }

                array_push($tags_to_cache, $tag);
            }

            /* update tag cache */

            sort($tags_to_cache);
            $tags_str = join(",", $tags_to_cache);

            \SmallSmallRSS\Database::query(
                "UPDATE ttrss_user_entries
                SET tag_cache = '$tags_str' WHERE ref_id = '$id'
                        AND owner_uid = " . $_SESSION["uid"]
            );
        }

        \SmallSmallRSS\Database::query("COMMIT");

        $tags = get_article_tags($id);
        $tags_str = format_tags_string($tags, $id);
        $tags_str_full = join(", ", $tags);

        if (!$tags_str_full) {  $tags_str_full = __("no tags");
        }

        print json_encode(array("id" => (int) $id,
                    "content" => $tags_str, "content_full" => $tags_str_full));
    }


    function completeTags()
    {
        $search = \SmallSmallRSS\Database::escape_string($_REQUEST["search"]);

        $result = \SmallSmallRSS\Database::query(
            "SELECT DISTINCT tag_name FROM ttrss_tags
                WHERE owner_uid = '".$_SESSION["uid"]."' AND
                tag_name LIKE '$search%' ORDER BY tag_name
                LIMIT 10"
        );

        print "<ul>";
        while ($line = \SmallSmallRSS\Database::fetch_assoc($result)) {
            print "<li>" . $line["tag_name"] . "</li>";
        }
        print "</ul>";
    }

    function assigntolabel()
    {
        return $this->labelops(true);
    }

    function removefromlabel()
    {
        return $this->labelops(false);
    }

    private function labelops($assign)
    {
        $reply = array();
        $ids = explode(",", \SmallSmallRSS\Database::escape_string($_REQUEST["ids"]));
        $label_id = \SmallSmallRSS\Database::escape_string($_REQUEST["lid"]);
        $label = \SmallSmallRSS\Database::escape_string(\SmallSmallRSS\Labels::findCaption($label_id, $_SESSION["uid"]));
        $reply["info-for-headlines"] = array();
        if ($label) {
            foreach ($ids as $id) {
                if ($assign) {
                    \SmallSmallRSS\Labels::addArticle($id, $label, $_SESSION["uid"]);
                } else {
                    \SmallSmallRSS\Labels::removeArticle($id, $label, $_SESSION["uid"]);
                }
                $labels = \SmallSmallRSS\Labels::getForArticle($id, $_SESSION["uid"]);
                array_push(
                    $reply["info-for-headlines"],
                    array(
                        "id" => $id,
                        "labels" => format_article_labels($labels, $id)
                    )
                );
            }
        }
        $reply["message"] = "UPDATE_COUNTERS";
        print json_encode($reply);
    }
}
