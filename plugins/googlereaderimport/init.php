<?php
class GoogleReaderImport extends \SmallSmallRSS\Plugin
{
    const API_VERSION = 2;
    const VERSION = 1.0;
    const NAME = 'Import Starred and Shared From Google Reader';
    const DESCRIPTION = 'Import starred/shared items from Google Reader takeout';
    const AUTHOR = 'fox';
    const IS_SYSTEM = false;

    public static $provides = array(
        \SmallSmallRSS\Hooks::RENDER_PREFS_TAB
    );

    public function addCommands()
    {
        $this->host->addCommand(
            "greader-import",
            "import data in Google Reader JSON format",
            $this,
            ":",
            "FILE"
        );

    }

    public function greaderImport($args)
    {
        $file = $args['greaderImport'];
        if (!file_exists($file)) {
            \SmallSmallRSS\Logger::debug("file not found: $file");
            return;
        }
        print "please enter your username:";
        $username = \SmallSmallRSS\Database::escape_string(trim(\SmallSmallRSS\Utils::readStdin()));
        $result = \SmallSmallRSS\Database::query(
            "SELECT id FROM ttrss_users
             WHERE login = '$username'"
        );
        if (\SmallSmallRSS\Database::num_rows($result) == 0) {
            \SmallSmallRSS\Logger::debug("user not found.");
            return;
        }
        $owner_uid = \SmallSmallRSS\Database::fetch_result($result, 0, "id");
        $this->import($file, $owner_uid);
    }

    public function getPreferencesJavascript()
    {
        return file_get_contents(__DIR__ . "/init.js");
    }

    public function import($file = false, $owner_uid = 0)
    {
        $imported = 0;
        \SmallSmallRSS\Entries::purgeOrphans();
        if (!$file) {
            header("Content-Type: text/html");
            $owner_uid = $_SESSION["uid"];
            if ($_FILES['starred_file']['error'] != 0) {
                \SmallSmallRSS\Renderers\Messages::renderError(
                    T_sprintf("Upload failed with error code %d", $_FILES['starred_file']['error'])
                );
                return;
            }
            $tmp_file = false;
            if (is_uploaded_file($_FILES['starred_file']['tmp_name'])) {
                $tmp_file = tempnam(\SmallSmallRSS\Config::get('CACHE_DIR') . '/upload', 'starred');
                $result = move_uploaded_file(
                    $_FILES['starred_file']['tmp_name'],
                    $tmp_file
                );
                if (!$result) {
                    \SmallSmallRSS\Renderers\Messages::renderError(__("Unable to move uploaded file."));
                    return;
                }
            } else {
                \SmallSmallRSS\Renderers\Messages::renderError(__('Error: please upload OPML file.'));
                return;
            }
            if (is_file($tmp_file)) {
                $doc = json_decode(file_get_contents($tmp_file), true);
                unlink($tmp_file);
            } else {
                \SmallSmallRSS\Renderers\Messages::renderError(__('No file uploaded.'));
                return;
            }
        } else {
            $doc = json_decode(file_get_contents($file), true);
        }
        if ($file) {
            $sql_set_marked = strtolower(basename($file)) == 'starred.json' ? 'true' : 'false';
        } else {
            $sql_set_marked = strtolower($_FILES['starred_file']['name']) == 'starred.json' ? 'true' : 'false';
        }
        if ($doc) {
            if (isset($doc['items'])) {
                $processed = 0;
                foreach ($doc['items'] as $item) {
                    $guid = \SmallSmallRSS\Database::escape_string(mb_substr($item['id'], 0, 250));
                    $title = \SmallSmallRSS\Database::escape_string($item['title']);
                    $updated = date('Y-m-d h:i:s', $item['updated']);
                    $link = '';
                    $content = '';
                    $author = \SmallSmallRSS\Database::escape_string($item['author']);
                    $tags = array();
                    $orig_feed_data = array();

                    if (is_array($item['alternate'])) {
                        foreach ($item['alternate'] as $alt) {
                            if (isset($alt['type']) && $alt['type'] == 'text/html') {
                                $link = \SmallSmallRSS\Database::escape_string($alt['href']);
                            }
                        }
                    }

                    if (is_array($item['summary'])) {
                        $content = \SmallSmallRSS\Database::escape_string(
                            $item['summary']['content'],
                            false
                        );
                    }

                    if (is_array($item['content'])) {
                        $content = \SmallSmallRSS\Database::escape_string(
                            $item['content']['content'],
                            false
                        );
                    }

                    if (is_array($item['categories'])) {
                        foreach ($item['categories'] as $cat) {
                            if (strstr($cat, "com.google/") === false) {
                                $tags = \SmallSmallRSS\Tags::sanitize($cat);
                            }
                        }
                    }

                    if (is_array($item['origin'])) {
                        if (strpos($item['origin']['streamId'], 'feed/') === 0) {

                            $orig_feed_data['feed_url'] = \SmallSmallRSS\Database::escape_string(
                                mb_substr(preg_replace("/^feed\//", "", $item['origin']['streamId']), 0, 200)
                            );

                            $orig_feed_data['title'] = \SmallSmallRSS\Database::escape_string(
                                mb_substr($item['origin']['title'], 0, 200)
                            );

                            $orig_feed_data['site_url'] = \SmallSmallRSS\Database::escape_string(
                                mb_substr($item['origin']['htmlUrl'], 0, 200)
                            );
                        }
                    }
                    $processed++;
                    $imported += (int) $this->createArticle(
                        $owner_uid,
                        $guid,
                        $title,
                        $link,
                        $updated,
                        $content,
                        $author,
                        $sql_set_marked,
                        $tags,
                        $orig_feed_data
                    );
                }
            } else {
                \SmallSmallRSS\Renderers\Messages::renderError(__('The document has incorrect format.'));
            }
        } else {
            \SmallSmallRSS\Renderers\Messages::renderError(__('Error while parsing document.'));
        }
        if (!$file) {
            print "<div align='center'>";
            print "<button data-dojo-type=\"dijit.form.Button\"
                onclick=\"dijit.byId('starredImportDlg').execute()\">".
                __('Close this window')."</button>";
            print "</div>";
        }
    }

    // expects ESCAPED data
    private function createArticle(
        $owner_uid,
        $guid,
        $title,
        $link,
        $updated,
        $content,
        $author,
        $marked,
        $tags,
        $orig_feed_data
    ) {
        if (!$guid) {
            $guid = sha1($link);
        }
        $create_archived_feeds = true;
        $guid = "$owner_uid,$guid";
        $content_hash = sha1($content);
        if (filter_var(FILTER_VALIDATE_URL) === false) {
            return false;
        }
        \SmallSmallRSS\Database::query("BEGIN");
        $feed_id = 'NULL';

        // let's check for archived feed entry
        $feed_inserted = false;

        // before dealing with archived feeds we must check ttrss_feeds to maintain id consistency
        if ($orig_feed_data['feed_url'] && $create_archived_feeds) {
            $result = \SmallSmallRSS\Database::query(
                "SELECT id FROM ttrss_feeds
                 WHERE
                     feed_url = '".$orig_feed_data['feed_url']."'
                     AND owner_uid = $owner_uid"
            );

            if (\SmallSmallRSS\Database::num_rows($result) != 0) {
                $feed_id = \SmallSmallRSS\Database::fetch_result($result, 0, "id");
            } else {
                // let's insert it
                if (!$orig_feed_data['title']) {
                    $orig_feed_data['title'] = '[Unknown]';
                }

                $result = \SmallSmallRSS\Database::query(
                    "INSERT INTO ttrss_feeds
                     (owner_uid,feed_url,site_url,title,cat_id,auth_login,auth_pass,update_method)
                     VALUES (
                         $owner_uid,
                         '".$orig_feed_data['feed_url']."',
                         '".$orig_feed_data['site_url']."',
                         '".$orig_feed_data['title']."',
                         NULL, '', '', 0
                     )"
                );

                $result = \SmallSmallRSS\Database::query(
                    "SELECT id FROM ttrss_feeds
                     WHERE feed_url = '".$orig_feed_data['feed_url']."'
                         AND owner_uid = $owner_uid"
                );

                if (\SmallSmallRSS\Database::num_rows($result) != 0) {
                    $feed_id = \SmallSmallRSS\Database::fetch_result($result, 0, "id");
                    $feed_inserted = true;
                }
            }
        }

        if ($feed_id && $feed_id != 'NULL') {
            // locate archived entry to file entries in, we don't want to file them in actual feeds because of purging
            // maybe file marked in real feeds because eh

            $result = \SmallSmallRSS\Database::query(
                "SELECT id FROM ttrss_archived_feeds WHERE
                 feed_url = '".$orig_feed_data['feed_url']."' AND owner_uid = $owner_uid"
            );

            if (\SmallSmallRSS\Database::num_rows($result) != 0) {
                $orig_feed_id = \SmallSmallRSS\Database::fetch_result($result, 0, "id");
            } else {
                \SmallSmallRSS\Database::query(
                    "INSERT INTO ttrss_archived_feeds
                     (id, owner_uid, title, feed_url, site_url)
                     SELECT id, owner_uid, title, feed_url, site_url from ttrss_feeds
                     WHERE id = '$feed_id'"
                );

                $result = \SmallSmallRSS\Database::query(
                    "SELECT id FROM ttrss_archived_feeds
                     WHERE feed_url = '" . $orig_feed_data['feed_url'] . "' AND owner_uid = $owner_uid"
                );

                if (\SmallSmallRSS\Database::num_rows($result) != 0) {
                    $orig_feed_id = \SmallSmallRSS\Database::fetch_result($result, 0, "id");
                }
            }
        }

        // delete temporarily inserted feed
        if ($feed_id && $feed_inserted) {
            \SmallSmallRSS\Database::query("DELETE FROM ttrss_feeds WHERE id = $feed_id");
        }

        if (!$orig_feed_id) {
            $orig_feed_id = 'NULL';
        }

        $result = \SmallSmallRSS\Database::query(
            "SELECT id FROM ttrss_entries, ttrss_user_entries WHERE
             guid = '$guid' AND ref_id = id AND owner_uid = '$owner_uid' LIMIT 1"
        );

        if (\SmallSmallRSS\Database::num_rows($result) == 0) {
            $result = \SmallSmallRSS\Database::query(
                "INSERT INTO ttrss_entries
                 (title, guid, link, updated, content, content_hash, date_entered, date_updated, author)
                 VALUES
                 ('$title', '$guid', '$link', '$updated', '$content', '$content_hash', NOW(), NOW(), '$author')"
            );

            $result = \SmallSmallRSS\Database::query(
                "SELECT id
                 FROM ttrss_entries
                 WHERE guid = '$guid'"
            );

            if (\SmallSmallRSS\Database::num_rows($result) != 0) {
                $ref_id = \SmallSmallRSS\Database::fetch_result($result, 0, "id");
                \SmallSmallRSS\Database::query(
                    "INSERT INTO ttrss_user_entries
                     (ref_id, uuid, feed_id, orig_feed_id, owner_uid, marked, tag_cache, label_cache,
                      last_read, note, unread, last_marked)
                     VALUES
                     ('$ref_id', '', NULL, $orig_feed_id, $owner_uid, $marked, '', '', NOW(), '', false, NOW())"
                );

                $result = \SmallSmallRSS\Database::query(
                    "SELECT int_id
                     FROM ttrss_user_entries, ttrss_entries
                     WHERE
                         owner_uid = $owner_uid
                         AND ref_id = id
                         AND ref_id = $ref_id"
                );
                if (\SmallSmallRSS\Database::num_rows($result) != 0 && is_array($tags)) {
                    $entry_int_id = \SmallSmallRSS\Database::fetch_result($result, 0, "int_id");
                    $tags_to_cache = array();
                    foreach ($tags as $tag) {
                        $tag = \SmallSmallRSS\Tags::sanitize($tag);
                        if (!\SmallSmallRSS\Tags::isValid($tag)) {
                            continue;
                        }
                        if (!\SmallSmallRSS\Tags::postHas($entry_int_id, $owner_uid, $tag)) {
                            \SmallSmallRSS\Tags::setForPost($entry_int_id, $owner_uid, array($tag));
                        }

                        $tags_to_cache[] = $tag;
                    }

                    /* update the cache */

                    $tags_to_cache = array_unique($tags_to_cache);
                    $tags_str = join(",", $tags_to_cache);
                    \SmallSmallRSS\UserEntries::setCachedTags($ref_id, $owner_uid, $tags_str);
                }
                $rc = true;
            }
        }
        \SmallSmallRSS\Database::query("COMMIT");
        return $rc;
    }

    public function hookRenderPreferencesTab($args)
    {
        if ($args != "prefFeeds") {
            return;
        }
        print "<div data-dojo-type=\"dijit.layout.AccordionPane\" title=\""
            . __("Import starred or shared items from Google Reader")
            . "\">";

        \SmallSmallRSS\Renderers\Messages::renderNotice(
            "Your imported articles will appear in Starred (in file is named starred.json) and Archived feeds."
        );
        print "<p>".__("Paste your starred.json or shared.json into the form below."). "</p>";
        print "<iframe id=\"starred_upload_iframe\"
            name=\"starred_upload_iframe\" onload=\"starredImportComplete(this)\"
            style=\"width: 400px; height: 100px; display: none;\"></iframe>";
        print "<form name=\"starred_form\" style='display : block' target=\"starred_upload_iframe\"
            enctype=\"multipart/form-data\" method=\"POST\"
            action=\"backend.php\">
            <input id=\"starred_file\" name=\"starred_file\" type=\"file\">&nbsp;
            <input type=\"hidden\" name=\"op\" value=\"PluginHandler\">
            <input type=\"hidden\" name=\"method\" value=\"import\">
            <input type=\"hidden\" name=\"plugin\" value=\"googlereaderimport\">
            <button data-dojo-type=\"dijit.form.Button\" onclick=\"return starredImport();\" type=\"submit\">" .
            __('Import my Starred items') . "</button>";
        print "</form>";
        print "</div>"; #pane
    }
}
