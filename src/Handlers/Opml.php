<?php
namespace SmallSmallRSS\Handlers;

class Opml extends ProtectedHandler
{

    public function ignoreCSRF($method)
    {
        $csrf_ignored = array('export', 'import');
        return array_search($method, $csrf_ignored) !== false;
    }

    public function export()
    {
        $output_name = $_REQUEST['filename'];
        if (!$output_name) {
            $output_name = 'TinyTinyRSS.opml';
        }
        $show_settings = $_REQUEST['settings'];
        $owner_uid = $_SESSION['uid'];
        return $this->renderExport($output_name, $owner_uid, false, ($show_settings == 1));
    }

    public function import()
    {
        $owner_uid = $_SESSION['uid'];
        header('Content-Type: text/html; charset=utf-8');
        echo '<!DOCTYPE html>';
        echo '<html>';
        echo '<head>';
        echo '<link rel="stylesheet" href="css/utility.css" type="text/css">';
        echo '<title>'.__('OPML Utility').'</title>';
        echo '<meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>';
        echo '</head>';
        echo '<body>';
        echo '<div class="floatingLogo"><img src="images/logo_small.png"></div>';
        echo '<h1>'.__('OPML Utility').'</h1>';
        echo "<div class='content'>";
        \SmallSmallRSS\FeedCategories::add($owner_uid, 'Imported feeds');
        $this->notice(__('Importing OPML...'));
        $this->renderImport($owner_uid);
        echo '<br />';
        echo '<form method="GET" action="prefs.php">';
        echo '<input type="submit" value="'.__('Return to preferences').'" />';
        echo '</form>';
        echo '</div>';
        echo '</body>';
        echo '</html>';


    }

    private function exportCategory($owner_uid, $cat_id, $hide_private_feeds = false)
    {
        if ($cat_id) {
            $feed_cat_qpart = "cat_id = '$cat_id'";
        } else {
            $feed_cat_qpart = 'cat_id IS NULL';
        }
        if ($hide_private_feeds) {
            $hide_qpart = "(private IS false AND auth_login = '' AND auth_pass = '')";
        } else {
            $hide_qpart = 'true';
        }
        $out = '';
        $cat_title = '';
        if ($cat_id) {
            $cat_title = htmlspecialchars(\SmallSmallRSS\FeedCategories::getTitle($cat_id));
        }
        if ($cat_title) {
            $out .= "<outline text=\"$cat_title\">\n";
        }

        $cat_ids = \SmallSmallRSS\FeedCategories::getChildrenForOPML($cat_id);

        foreach ($cat_ids as $cat_id) {
            $out .= $this->exportCategory($owner_uid, $line['id'], $hide_private_feeds);
        }

        $feeds_result = \SmallSmallRSS\Database::query(
            "SELECT title, feed_url, site_url
             FROM ttrss_feeds
             WHERE
                 $feed_cat_qpart
                 AND owner_uid = '$owner_uid'
                 AND $hide_qpart
             ORDER BY order_id, title"
        );

        while ($fline = \SmallSmallRSS\Database::fetchAssoc($feeds_result)) {
            $title = htmlspecialchars($fline['title']);
            $url = htmlspecialchars($fline['feed_url']);
            $site_url = htmlspecialchars($fline['site_url']);

            if ($site_url) {
                $html_url_qpart = "htmlUrl=\"$site_url\"";
            } else {
                $html_url_qpart = '';
            }

            $out .= "<outline text=\"$title\" xmlUrl=\"$url\" $html_url_qpart/>\n";
        }

        if ($cat_title) {
            $out .= "</outline>\n";
        }

        return $out;
    }

    public function renderExport($name, $owner_uid, $hide_private_feeds = false, $include_settings = true)
    {
        if (!$owner_uid) {
            return;
        }
        if (!isset($_REQUEST['debug'])) {
            header('Content-type: application/xml+opml');
            header('Content-Disposition: attachment; filename=' . $name);
        } else {
            header('Content-type: text/xml');
        }
        $software_name = \SmallSmallRSS\Config::get('SOFTWARE_NAME');
        $out = '<?xml version="1.0" encoding="utf-8"?'.'>';
        $out .= '<opml version="1.0">';
        $out .= '<head>';
        $out .= '<dateCreated>' . date('r', time()) . '</dateCreated>';
        $out .= "<title>$software_name : Feed Export</title>";
        $out .= '</head>';
        $out .= '<body>';
        $out .= $this->exportCategory($owner_uid, false, $hide_private_feeds);

        # export tt-rss settings

        if ($include_settings) {
            $out .= '<outline text="tt-rss-prefs" schema-version="'.\SmallSmallRSS\Constants::SCHEMA_VERSION.'">';

            $result = \SmallSmallRSS\Database::query(
                'SELECT pref_name, value
                 FROM ttrss_user_prefs
                 WHERE
                     profile IS NULL
                     AND owner_uid = ' . $_SESSION['uid'] . '
                     ORDER BY pref_name'
            );

            while ($line = \SmallSmallRSS\Database::fetchAssoc($result)) {
                $name = $line['pref_name'];
                $value = htmlspecialchars($line['value']);

                $out .= "<outline pref-name=\"$name\" value=\"$value\"/>";
            }

            $out .= '</outline>';

            $out .= '<outline text="tt-rss-labels" schema-version="'.\SmallSmallRSS\Constants::SCHEMA_VERSION.'">';

            $result = \SmallSmallRSS\Database::query(
                'SELECT *
                 FROM ttrss_labels2
                 WHERE
                     owner_uid = ' . $_SESSION['uid']
            );

            while ($line = \SmallSmallRSS\Database::fetchAssoc($result)) {
                $name = htmlspecialchars($line['caption']);
                $fg_color = htmlspecialchars($line['fg_color']);
                $bg_color = htmlspecialchars($line['bg_color']);

                $out .= "<outline label-name=\"$name\" label-fg-color=\"$fg_color\" label-bg-color=\"$bg_color\"/>";

            }

            $out .= '</outline>';

            $out .= '<outline text="tt-rss-filters" schema-version="'.\SmallSmallRSS\Constants::SCHEMA_VERSION.'">';

            $result = \SmallSmallRSS\Database::query(
                'SELECT *
                 FROM ttrss_filters2
                 WHERE owner_uid = ' . $_SESSION['uid'] . '
                 ORDER BY id'
            );

            while ($line = \SmallSmallRSS\Database::fetchAssoc($result)) {
                foreach (array('enabled', 'match_any_rule', 'inverse') as $b) {
                    $line[$b] = \SmallSmallRSS\Database::fromSQLBool($line[$b]);
                }

                $line['rules'] = array();
                $line['actions'] = array();

                $tmp_result = \SmallSmallRSS\Database::query(
                    'SELECT * FROM ttrss_filters2_rules
                    WHERE filter_id = '.$line['id']
                );

                while ($tmp_line = \SmallSmallRSS\Database::fetchAssoc($tmp_result)) {
                    unset($tmp_line['id']);
                    unset($tmp_line['filter_id']);

                    $cat_filter = \SmallSmallRSS\Database::fromSQLBool($tmp_line['cat_filter']);

                    if ($cat_filter && $tmp_line['cat_id'] || $tmp_line['feed_id']) {
                        if ($cat_filter) {
                            $tmp_line['feed'] = \SmallSmallRSS\FeedCategories::getTitle($id);
                        } else {
                            $tmp_line['feed'] = \SmallSmallRSS\Feeds::getTitle($tmp_line['feed_id']);
                        }
                    } else {
                        $tmp_line['feed'] = '';
                    }

                    $tmp_line['cat_filter'] = \SmallSmallRSS\Database::fromSQLBool($tmp_line['cat_filter']);

                    unset($tmp_line['feed_id']);
                    unset($tmp_line['cat_id']);

                    array_push($line['rules'], $tmp_line);
                }

                $tmp_result = \SmallSmallRSS\Database::query(
                    'SELECT *
                     FROM ttrss_filters2_actions
                     WHERE filter_id = '.$line['id']
                );

                while ($tmp_line = \SmallSmallRSS\Database::fetchAssoc($tmp_result)) {
                    unset($tmp_line['id']);
                    unset($tmp_line['filter_id']);
                    array_push($line['actions'], $tmp_line);
                }
                unset($line['id']);
                unset($line['owner_uid']);
                $filter = json_encode($line);
                $out .= "<outline filter-type=\"2\"><![CDATA[$filter]]></outline>";
            }
            $out .= '</outline>';
        }
        $out .= '</body></opml>';

        // Format output.
        $doc = new \DOMDocument();
        $doc->formatOutput = true;
        $doc->preserveWhiteSpace = false;
        $doc->loadXML($out);
        $xpath = new \DOMXpath($doc);
        $outlines = $xpath->query('//outline[@title]');

        // cleanup empty categories
        foreach ($outlines as $node) {
            if ($node->getElementsByTagName('outline')->length == 0) {
                $node->parentNode->removeChild($node);
            }
        }
        echo $doc->saveXML();
    }

    // Import

    private function importFeed($doc, $node, $cat_id, $owner_uid)
    {
        $attrs = $node->attributes;
        $feed_title = \SmallSmallRSS\Database::escapeString(
            mb_substr($attrs->getNamedItem('text')->nodeValue, 0, 250)
        );
        if (!$feed_title) {
            $feed_title = \SmallSmallRSS\Database::escapeString(
                mb_substr($attrs->getNamedItem('title')->nodeValue, 0, 250)
            );
        }
        $feed_url = \SmallSmallRSS\Database::escapeString(
            mb_substr($attrs->getNamedItem('xmlUrl')->nodeValue, 0, 250)
        );
        if (!$feed_url) {
            $feed_url = \SmallSmallRSS\Database::escapeString(
                mb_substr($attrs->getNamedItem('xmlURL')->nodeValue, 0, 250)
            );
        }

        $site_url = \SmallSmallRSS\Database::escapeString(
            mb_substr($attrs->getNamedItem('htmlUrl')->nodeValue, 0, 250)
        );

        if ($feed_url && $feed_title) {
            $result = \SmallSmallRSS\Database::query(
                "SELECT id
                 FROM ttrss_feeds
                 WHERE
                     feed_url = '$feed_url'
                     AND owner_uid = '$owner_uid'"
            );

            if (\SmallSmallRSS\Database::numRows($result) == 0) {
                $this->notice(T_sprintf('Adding feed: %s', $feed_title));

                if (!$cat_id) {
                    $cat_id = 'NULL';
                }

                $query = "INSERT INTO ttrss_feeds
                          (title, feed_url, owner_uid, cat_id, site_url, order_id)
                          VALUES
                          ('$feed_title', '$feed_url', '$owner_uid',
                           $cat_id, '$site_url', 0)";
                \SmallSmallRSS\Database::query($query);
            } else {
                $this->notice(T_sprintf('Duplicate feed: %s', $feed_title));
            }
        }
    }

    private function importLabel($doc, $node, $owner_uid)
    {
        $attrs = $node->attributes;
        $label_name = \SmallSmallRSS\Database::escapeString($attrs->getNamedItem('label-name')->nodeValue);
        if ($label_name) {
            $fg_color = \SmallSmallRSS\Database::escapeString($attrs->getNamedItem('label-fg-color')->nodeValue);
            $bg_color = \SmallSmallRSS\Database::escapeString($attrs->getNamedItem('label-bg-color')->nodeValue);
            if (!\SmallSmallRSS\Labels::findID($label_name, $owner_uid)) {
                $this->notice(T_sprintf('Adding label %s', htmlspecialchars($label_name)));
                \SmallSmallRSS\Labels::create($label_name, $owner_uid, $fg_color, $bg_color);
            } else {
                $this->notice(T_sprintf('Duplicate label: %s', htmlspecialchars($label_name)));
            }
        }
    }

    private function importPreference($doc, $node, $owner_uid)
    {
        $attrs = $node->attributes;
        $pref_name = \SmallSmallRSS\Database::escapeString($attrs->getNamedItem('pref-name')->nodeValue);

        if ($pref_name) {
            $pref_value = \SmallSmallRSS\Database::escapeString($attrs->getNamedItem('value')->nodeValue);
            $this->notice(
                T_sprintf('Setting preference key %s to %s', $pref_name, $pref_value)
            );
            \SmallSmallRSS\DBPrefs::write($pref_name, $pref_value);
        }
    }

    private function importFilter($doc, $node, $owner_uid)
    {
        $attrs = $node->attributes;

        $filter_type = \SmallSmallRSS\Database::escapeString($attrs->getNamedItem('filter-type')->nodeValue);

        if ($filter_type == '2') {
            $filter = json_decode($node->nodeValue, true);

            if ($filter) {
                $match_any_rule = \SmallSmallRSS\Database::toSQLBool($filter['match_any_rule']);
                $enabled = \SmallSmallRSS\Database::toSQLBool($filter['enabled']);
                $inverse = \SmallSmallRSS\Database::toSQLBool($filter['inverse']);
                $title = \SmallSmallRSS\Database::escapeString($filter['title']);

                \SmallSmallRSS\Database::query('BEGIN');

                \SmallSmallRSS\Database::query(
                    "INSERT INTO ttrss_filters2
                     (match_any_rule,enabled,inverse,title,owner_uid)
                     VALUES ($match_any_rule, $enabled, $inverse, '$title',
                         ".$_SESSION['uid'].')'
                );

                $result = \SmallSmallRSS\Database::query(
                    'SELECT MAX(id) AS id
                     FROM ttrss_filters2
                     WHERE owner_uid = '.$_SESSION['uid']
                );
                $filter_id = \SmallSmallRSS\Database::fetchResult($result, 0, 'id');

                if ($filter_id) {
                    $this->notice(T_sprintf('Adding filter...'));

                    foreach ($filter['rules'] as $rule) {
                        $feed_id = 'NULL';
                        $cat_id = 'NULL';

                        if (!$rule['cat_filter']) {
                            $tmp_result = \SmallSmallRSS\Database::query(
                                "SELECT id FROM ttrss_feeds
                                 WHERE
                                     title = '".\SmallSmallRSS\Database::escapeString($rule['feed']) . "'
                                     AND owner_uid = ".$_SESSION['uid']
                            );
                            if (\SmallSmallRSS\Database::numRows($tmp_result) > 0) {
                                $feed_id = \SmallSmallRSS\Database::fetchResult($tmp_result, 0, 'id');
                            }
                        } else {
                            $tmp_result = \SmallSmallRSS\Database::query(
                                "SELECT id FROM ttrss_feed_categories
                                 WHERE
                                     title = '".\SmallSmallRSS\Database::escapeString($rule['feed'])."'
                                     AND owner_uid = " . $_SESSION['uid']
                            );
                            if (\SmallSmallRSS\Database::numRows($tmp_result) > 0) {
                                $cat_id = \SmallSmallRSS\Database::fetchResult($tmp_result, 0, 'id');
                            }
                        }
                        $cat_filter = \SmallSmallRSS\Database::toSQLBool($rule['cat_filter']);
                        $reg_exp = \SmallSmallRSS\Database::escapeString($rule['reg_exp']);
                        $filter_type = (int) $rule['filter_type'];

                        \SmallSmallRSS\Database::query(
                            "INSERT INTO ttrss_filters2_rules
                             (feed_id,cat_id,filter_id,filter_type,reg_exp,cat_filter)
                             VALUES
                             ($feed_id, $cat_id, $filter_id, $filter_type, '$reg_exp', $cat_filter)"
                        );
                    }

                    foreach ($filter['actions'] as $action) {
                        $action_id = (int) $action['action_id'];
                        $action_param = \SmallSmallRSS\Database::escapeString($action['action_param']);
                        \SmallSmallRSS\Database::query(
                            "INSERT INTO ttrss_filters2_actions
                             (filter_id,action_id,action_param)
                             VALUES ($filter_id, $action_id, '$action_param')"
                        );
                    }
                }

                \SmallSmallRSS\Database::query('COMMIT');
            }
        }
    }

    private function importCategory($doc, $root_node, $owner_uid, $parent_id)
    {
        $body = $doc->getElementsByTagName('body');

        $default_cat_id = (int) \SmallSmallRSS\FeedCategories::get($owner_uid, 'Imported feeds', false);

        if ($root_node) {
            $cat_title = \SmallSmallRSS\Database::escapeString(
                mb_substr($root_node->attributes->getNamedItem('text')->nodeValue, 0, 250)
            );
            if (!$cat_title) {
                $cat_title = \SmallSmallRSS\Database::escapeString(
                    mb_substr($root_node->attributes->getNamedItem('title')->nodeValue, 0, 250)
                );
            }

            if (!in_array($cat_title, array('tt-rss-filters', 'tt-rss-labels', 'tt-rss-prefs'))) {
                $cat_id = \SmallSmallRSS\FeedCategories::get($owner_uid, $cat_title, $parent_id);
                \SmallSmallRSS\Database::query('BEGIN');
                if ($cat_id === false) {
                    \SmallSmallRSS\FeedCategories::add($_SESSION['uid'], $cat_title, $parent_id);
                    $cat_id = \SmallSmallRSS\FeedCategories::get($owner_uid, $cat_title, $parent_id);
                }
                \SmallSmallRSS\Database::query('COMMIT');
            } else {
                $cat_id = 0;
            }

            $outlines = $root_node->childNodes;

        } else {
            $xpath = new \DOMXpath($doc);
            $outlines = $xpath->query('//opml/body/outline');

            $cat_id = 0;
        }

        #$this->notice("[CAT] $cat_title id: $cat_id P_id: $parent_id");
        $this->notice(T_sprintf('Processing category: %s', $cat_title ? $cat_title : __('Uncategorized')));

        foreach ($outlines as $node) {
            if ($node->hasAttributes() && strtolower($node->tagName) == 'outline') {
                $attrs = $node->attributes;
                $node_cat_title = \SmallSmallRSS\Database::escapeString($attrs->getNamedItem('text')->nodeValue);

                if (!$node_cat_title) {
                    $node_cat_title = \SmallSmallRSS\Database::escapeString($attrs->getNamedItem('title')->nodeValue);
                }

                $node_feed_url = \SmallSmallRSS\Database::escapeString($attrs->getNamedItem('xmlUrl')->nodeValue);

                if ($node_cat_title && !$node_feed_url) {
                    $this->importCategory($doc, $node, $owner_uid, $cat_id);
                } else {
                    if (!$cat_id) {
                        $dst_cat_id = $default_cat_id;
                    } else {
                        $dst_cat_id = $cat_id;
                    }

                    switch ($cat_title) {
                        case 'tt-rss-prefs':
                            $this->importPreference($doc, $node, $owner_uid);
                            break;
                        case 'tt-rss-labels':
                            $this->importLabel($doc, $node, $owner_uid);
                            break;
                        case 'tt-rss-filters':
                            $this->importFilter($doc, $node, $owner_uid);
                            break;
                        default:
                            $this->importFeed($doc, $node, $dst_cat_id, $owner_uid);
                    }
                }
            }
        }
    }

    public function renderImport($owner_uid)
    {
        if (!$owner_uid) {
            return;
        }

        $debug = isset($_REQUEST['debug']);
        $doc = false;

        if ($_FILES['opml_file']['error'] != 0) {
            \SmallSmallRSS\Renderers\Messages::renderError(
                T_sprintf('Upload failed with error code %d', $_FILES['opml_file']['error'])
            );
            return;
        }

        $tmp_file = false;

        if (is_uploaded_file($_FILES['opml_file']['tmp_name'])) {
            $tmp_file = tempnam(\SmallSmallRSS\Config::get('CACHE_DIR') . '/upload', 'opml');
            $result = move_uploaded_file(
                $_FILES['opml_file']['tmp_name'],
                $tmp_file
            );
            if (!$result) {
                \SmallSmallRSS\Renderers\Messages::renderError(__('Unable to move uploaded file.'));
                return;
            }
        } else {
            \SmallSmallRSS\Renderers\Messages::renderError(__('Error: please upload OPML file.'));
            return;
        }

        if (is_file($tmp_file)) {
            $doc = new \DOMDocument();
            $doc->load($tmp_file);
            unlink($tmp_file);
        } elseif (!$doc) {
            \SmallSmallRSS\Renderers\Messages::renderError(__('Error: unable to find moved OPML file.'));
            return;
        }
        if ($doc) {
            $this->importCategory($doc, false, $owner_uid, false);
        } else {
            \SmallSmallRSS\Renderers\Messages::renderError(__('Error while parsing document.'));
        }
    }

    private function notice($msg)
    {
        echo "$msg<br/>";
    }

    public static function publishUrl($owner_uid)
    {
        $url_path = get_self_url_prefix();
        $url_path .= '/opml.php?op=publish&key=';
        $url_path .= \SmallSmallRSS\AccessKeys::getForFeed('OPML:Publish', false, $owner_uid);
        return $url_path;
    }
}
