<?php

class Import_Export extends \SmallSmallRSS\Plugin implements \SmallSmallRSS\Handlers\IHandler
{
    const API_VERSION = 2;
    const VERSION = 1.0;
    const NAME = 'XML Import/Export';
    const DESCRIPTION = 'Imports and exports user data using neutral XML format';
    const AUTHOR = 'fox';
    const IS_SYSTEM = false;

    public static $provides = array(
        \SmallSmallRSS\Hooks::RENDER_PREFS_TAB
    );

    public function addCommands()
    {
        $this->host->addCommand("xml-import", "import articles from XML", $this, ":", "FILE");
    }

    public function xmlImport($args)
    {
        $filename = $args['xml_import'];
        if (!is_file($filename)) {
            print "error: input filename ($filename) doesn't exist.\n";
            return;
        }
        print "please enter your username: ";
        $username = \SmallSmallRSS\Database::escapeString(trim(\SmallSmallRSS\Utils::readStdin()));
        $result = \SmallSmallRSS\Database::query(
            "SELECT id FROM ttrss_users WHERE login = '$username'"
        );
        if (\SmallSmallRSS\Database::numRows($result) == 0) {
            print "error: could not find user $username.\n";
            return;
        }
        $owner_uid = \SmallSmallRSS\Database::fetchResult($result, 0, "id");

        print "importing $filename for user $username...\n";
        $this->performDataImport($filename, $owner_uid);
    }

    public function save()
    {
        $example_value = \SmallSmallRSS\Database::escapeString($_POST["example_value"]);

        echo "Value set to $example_value (not really)";
    }

    public function getPreferencesJavascript()
    {
        return file_get_contents(dirname(__FILE__) . "/import_export.js");
    }

    public function hookRenderPreferencesTab($args)
    {
        if ($args != "prefFeeds") {
            return;
        }
        print "<div data-dojo-type=\"dijit.layout.AccordionPane\" title=\"".__('Import and export')."\">";
        \SmallSmallRSS\Renderers\Messages::renderNotice(
            __("You can export and import your Starred and Archived articles for safekeeping or when migrating between tt-rss instances of same version.")
        );
        print "<p>";
        print "<button data-dojo-type=\"dijit.form.Button\" onclick=\"return exportData()\">".
            __('Export my data')."</button> ";
        print "<hr>";
        print "<iframe id=\"data_upload_iframe\"
            name=\"data_upload_iframe\" onload=\"dataImportComplete(this)\"
            style=\"width: 400px; height: 100px; display: none;\"></iframe>";
        print "<form name=\"import_form\" style='display : block' target=\"data_upload_iframe\"
            enctype=\"multipart/form-data\" method=\"POST\"
            action=\"backend.php\">
            <input id=\"export_file\" name=\"export_file\" type=\"file\">&nbsp;
            <input type=\"hidden\" name=\"op\" value=\"PluginHandler\">
            <input type=\"hidden\" name=\"plugin\" value=\"import_export\">
            <input type=\"hidden\" name=\"method\" value=\"dataimport\">
            <button data-dojo-type=\"dijit.form.Button\" onclick=\"return importData();\" type=\"submit\">" .
            __('Import') . "</button>";

        print "</form>";

        print "</p>";

        print "</div>"; # pane
    }

    public function ignoreCSRF($method)
    {
        return in_array($method, array("exportget"));
    }

    public function before($method)
    {
        return $_SESSION["uid"] != false;
    }

    public function after()
    {
        return true;
    }

    public function exportget()
    {
        $exportname = \SmallSmallRSS\Config::get('CACHE_DIR') . "/export/" .
            sha1($_SESSION['uid'] . $_SESSION['login']) . ".xml";

        if (file_exists($exportname)) {
            header("Content-type: text/xml");

            if (function_exists('gzencode')) {
                header("Content-Disposition: attachment; filename=TinyTinyRSS_exported.xml.gz");
                echo gzencode(file_get_contents($exportname));
            } else {
                header("Content-Disposition: attachment; filename=TinyTinyRSS_exported.xml");
                echo file_get_contents($exportname);
            }
        } else {
            echo "File not found.";
        }
    }

    public function exportrun()
    {
        $offset = (int) \SmallSmallRSS\Database::escapeString($_REQUEST['offset']);
        $exported = 0;
        $limit = 250;

        if ($offset < 10000 && is_writable(\SmallSmallRSS\Config::get('CACHE_DIR') . "/export")) {
            $result = \SmallSmallRSS\Database::query(
                "SELECT
                    ttrss_entries.guid,
                    ttrss_entries.title,
                    content,
                    marked,
                    published,
                    score,
                    note,
                    link,
                    tag_cache,
                    label_cache,
                    ttrss_feeds.title AS feed_title,
                    ttrss_feeds.feed_url AS feed_url,
                    ttrss_entries.updated
                FROM
                    ttrss_user_entries LEFT JOIN ttrss_feeds ON (ttrss_feeds.id = feed_id),
                    ttrss_entries
                WHERE
                    (marked = true OR feed_id IS NULL) AND
                    ref_id = ttrss_entries.id AND
                    ttrss_user_entries.owner_uid = " . $_SESSION['uid'] . "
                ORDER BY ttrss_entries.id LIMIT $limit OFFSET $offset"
            );

            $exportname = sha1($_SESSION['uid'] . $_SESSION['login']);

            if ($offset == 0) {
                $fp = fopen(\SmallSmallRSS\Config::get('CACHE_DIR') . "/export/$exportname.xml", "w");
                fputs($fp, '<articles schema-version="' . \SmallSmallRSS\Constants::SCHEMA_VERSION . '">');
            } else {
                $fp = fopen(\SmallSmallRSS\Config::get('CACHE_DIR') . "/export/$exportname.xml", "a");
            }

            if ($fp) {

                while ($line = \SmallSmallRSS\Database::fetchAssoc($result)) {
                    fputs($fp, "<article>");

                    foreach ($line as $k => $v) {
                        $v = str_replace("]]>", "]]]]><![CDATA[>", $v);
                        fputs($fp, "<$k><![CDATA[$v]]></$k>");
                    }

                    fputs($fp, "</article>");
                }

                $exported = \SmallSmallRSS\Database::numRows($result);

                if ($exported < $limit && $exported > 0) {
                    fputs($fp, "</articles>");
                }

                fclose($fp);
            }

        }

        print json_encode(array("exported" => $exported));
    }

    public function performDataImport($filename, $owner_uid)
    {

        $num_imported = 0;
        $num_processed = 0;
        $num_feeds_created = 0;

        $doc = @DOMDocument::load($filename);

        if (!$doc) {
            $contents = file_get_contents($filename);

            if ($contents) {
                $data = @gzuncompress($contents);
            }

            if (!$data) {
                $data = @gzdecode($contents);
            }

            if ($data) {
                $doc = DOMDocument::loadXML($data);
            }
        }

        if ($doc) {
            $xpath = new DOMXpath($doc);

            $container = $doc->firstChild;

            if ($container && $container->hasAttribute('schema-version')) {
                $schema_version = $container->getAttribute('schema-version');

                if ($schema_version != \SmallSmallRSS\Constants::SCHEMA_VERSION) {
                    print "<p>" .__("Could not import: incorrect schema version.") . "</p>";
                    return;
                }

            } else {
                print "<p>" . __("Could not import: unrecognized document format.") . "</p>";
                return;
            }

            $articles = $xpath->query("//article");

            foreach ($articles as $article_node) {
                if ($article_node->childNodes) {

                    $ref_id = 0;

                    $article = array();

                    foreach ($article_node->childNodes as $child) {
                        if ($child->nodeName != 'label_cache') {
                            $article[$child->nodeName] = \SmallSmallRSS\Database::escapeString($child->nodeValue);
                        } else {
                            $article[$child->nodeName] = $child->nodeValue;
                        }
                    }

                    if ($article['guid']) {
                        $num_processed += 1;
                        $result = \SmallSmallRSS\Database::query(
                            "SELECT id
                             FROM ttrss_entries
                             WHERE guid = '".$article['guid']."'"
                        );
                        if (\SmallSmallRSS\Database::numRows($result) == 0) {
                            $result = \SmallSmallRSS\Database::query(
                                "INSERT INTO ttrss_entries
                                    (title,
                                    guid,
                                    link,
                                    updated,
                                    content,
                                    content_hash,
                                    no_orig_date,
                                    date_updated,
                                    date_entered,
                                    comments,
                                    num_comments,
                                    author)
                                VALUES
                                    ('".$article['title']."',
                                    '".$article['guid']."',
                                    '".$article['link']."',
                                    '".$article['updated']."',
                                    '".$article['content']."',
                                    '".sha1($article['content'])."',
                                    false,
                                    NOW(),
                                    NOW(),
                                    '',
                                    '0',
                                    '')"
                            );

                            $result = \SmallSmallRSS\Database::query(
                                "SELECT id FROM ttrss_entries
                                 WHERE guid = '".$article['guid']."'"
                            );

                            if (\SmallSmallRSS\Database::numRows($result) != 0) {
                                $ref_id = \SmallSmallRSS\Database::fetchResult($result, 0, "id");
                            }

                        } else {
                            $ref_id = \SmallSmallRSS\Database::fetchResult($result, 0, "id");
                        }

                        //print "Got ref ID: $ref_id\n";

                        if ($ref_id) {

                            $feed_url = $article['feed_url'];
                            $feed_title = $article['feed_title'];

                            $feed = 'NULL';

                            if ($feed_url && $feed_title) {
                                $result = \SmallSmallRSS\Database::query(
                                    "SELECT id FROM ttrss_feeds
                                     WHERE feed_url = '$feed_url' AND owner_uid = '$owner_uid'"
                                );

                                if (\SmallSmallRSS\Database::numRows($result) != 0) {
                                    $feed = \SmallSmallRSS\Database::fetchResult($result, 0, "id");
                                } else {
                                    // try autocreating feed in Uncategorized...
                                    $result = \SmallSmallRSS\Database::query(
                                        "INSERT INTO ttrss_feeds (owner_uid,
                                         feed_url, title) VALUES ($owner_uid, '$feed_url', '$feed_title')"
                                    );
                                    $result = \SmallSmallRSS\Database::query(
                                        "SELECT id FROM ttrss_feeds
                                         WHERE feed_url = '$feed_url' AND owner_uid = '$owner_uid'"
                                    );
                                    if (\SmallSmallRSS\Database::numRows($result) != 0) {
                                        ++$num_feeds_created;

                                        $feed = \SmallSmallRSS\Database::fetchResult($result, 0, "id");
                                    }
                                }
                            }

                            if ($feed != 'NULL') {
                                $feed_qpart = "feed_id = $feed";
                            } else {
                                $feed_qpart = "feed_id IS NULL";
                            }
                            $result = \SmallSmallRSS\Database::query(
                                "SELECT int_id FROM ttrss_user_entries
                                 WHERE ref_id = '$ref_id' AND owner_uid = '$owner_uid' AND $feed_qpart"
                            );

                            if (\SmallSmallRSS\Database::numRows($result) == 0) {

                                $marked = \SmallSmallRSS\Database::toSQLBool(\SmallSmallRSS\Database::fromSQLBool($article['marked']));
                                $published = \SmallSmallRSS\Database::toSQLBool(\SmallSmallRSS\Database::fromSQLBool($article['published']));
                                $score = (int) $article['score'];

                                $tag_cache = $article['tag_cache'];
                                $label_cache = \SmallSmallRSS\Database::escapeString($article['label_cache']);
                                $note = $article['note'];

                                //print "Importing " . $article['title'] . "<br/>";

                                ++$num_imported;

                                $result = \SmallSmallRSS\Database::query(
                                    "INSERT INTO ttrss_user_entries (
                                         ref_id, owner_uid, feed_id, unread, last_read, marked,
                                         published, score, tag_cache, label_cache, uuid, note)
                                     VALUES (
                                         $ref_id, $owner_uid, $feed, false,
                                         NULL, $marked, $published, $score, '$tag_cache',
                                         '$label_cache', '', '$note'
                                     )"
                                );

                                $label_cache = json_decode($label_cache, true);

                                if (is_array($label_cache) && $label_cache["no-labels"] != 1) {
                                    foreach ($label_cache as $label) {
                                        \SmallSmallRSS\Labels::create(
                                            $label[1],
                                            $owner_uid,
                                            $label[2],
                                            $label[3]
                                        );

                                        \SmallSmallRSS\Labels::addArticle($ref_id, $label[1], $owner_uid);

                                    }
                                }

                                //\SmallSmallRSS\Database::query("COMMIT");
                            }
                        }
                    }
                }
            }

            print "<p>" .
                __("Finished: ").
                vsprintf(ngettext("%d article processed, ", "%d articles processed, ", $num_processed), $num_processed).
                vsprintf(ngettext("%d imported, ", "%d imported, ", $num_imported), $num_imported).
                vsprintf(ngettext("%d feed created.", "%d feeds created.", $num_feeds_created), $num_feeds_created).
                "</p>";

        } else {

            print "<p>" . __("Could not load XML document.") . "</p>";

        }
    }

    public function exportData()
    {

        print "<p style='text-align : center' id='export_status_message'>";
        print "You need to prepare exported data first by clicking the button below.</p>";

        print "<div align='center'>";
        print "<button data-dojo-type=\"dijit.form.Button\"
            onclick=\"dijit.byId('dataExportDlg').prepare()\">".
            __('Prepare data')."</button>";

        print "<button data-dojo-type=\"dijit.form.Button\"
            onclick=\"dijit.byId('dataExportDlg').hide()\">".
            __('Close this window')."</button>";

        print "</div>";


    }

    public function dataImport()
    {
        header("Content-Type: text/html"); # required for iframe
        print "<div style='text-align : center'>";
        if ($_FILES['export_file']['error'] != 0) {
            \SmallSmallRSS\Renderers\Messages::renderError(
                T_sprintf(
                    "Upload failed with error code %d",
                    $_FILES['export_file']['error']
                )
            );
            return;
        }

        $tmp_file = false;

        if (is_uploaded_file($_FILES['export_file']['tmp_name'])) {
            $tmp_file = tempnam(\SmallSmallRSS\Config::get('CACHE_DIR') . '/upload', 'export');

            $result = move_uploaded_file(
                $_FILES['export_file']['tmp_name'],
                $tmp_file
            );

            if (!$result) {
                \SmallSmallRSS\Renderers\Messages::renderError(
                    __("Unable to move uploaded file.")
                );
                return;
            }
        } else {
            \SmallSmallRSS\Renderers\Messages::renderError(__('Error: please upload OPML file.'));
            return;
        }

        if (is_file($tmp_file)) {
            $this->performDataImport($tmp_file, $_SESSION['uid']);
            unlink($tmp_file);
        } else {
            \SmallSmallRSS\Renderers\Messages::renderError(__('No file uploaded.'));
            return;
        }

        print "<button data-dojo-type=\"dijit.form.Button\" onclick=\"dijit.byId('dataImportDlg').hide()\">"
            . __('Close this window')
            ."</button>";

        print "</div>";

    }
}
