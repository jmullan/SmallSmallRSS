<?php
namespace SmallSmallRSS\Handlers;

class PrefFeeds extends ProtectedHandler
{

    public function getMode()
    {
        return isset($_REQUEST['mode']) ? $_REQUEST['mode'] : '';
    }

    public function ignoreCSRF($method)
    {
        $csrf_ignored = array(
            'index', 'getfeedtree', 'add', 'editcats', 'editfeed',
            'savefeedorder', 'uploadicon', 'feedswitherrors', 'inactivefeeds',
            'batchsubscribe'
        );

        return array_search($method, $csrf_ignored) !== false;
    }

    public function renderBatchEditCheckbox($elem, $label = false)
    {
        echo '<input type="checkbox" title="';
        echo __('Check to enable field');
        echo "\" onchange=\"dijit.byId('feedEditDlg').toggleField(this, '$elem', '$label')\">";
    }

    public function renamecat()
    {
        $title = $this->getSQLEscapedStringFromRequest('title');
        $id = $this->getSQLEscapedStringFromRequest('id');

        if ($title) {
            \SmallSmallRSS\Database::query(
                "UPDATE ttrss_feed_categories
                 SET title = '$title'
                 WHERE id = '$id' AND owner_uid = " . $_SESSION['uid']
            );
        }
        return;
    }

    private function getCategoryItems($cat_id)
    {

        if ($this->getMode() != 2) {
            $search = isset($_SESSION['prefs_feed_search']) ? $_SESSION['prefs_feed_search'] : '';
        } else {
            $search = '';
        }

        if ($search) {
            $search_qpart = " AND LOWER(title) LIKE LOWER('%$search%')";
        } else {
            $search_qpart = '';
        }

        // first one is set by API
        $show_empty_cats = (!empty($_REQUEST['force_show_empty'])
                            || ($this->getMode() != 2 && !$search));

        $items = array();

        $result = \SmallSmallRSS\Database::query(
            'SELECT id, title FROM ttrss_feed_categories
                WHERE owner_uid = ' . $_SESSION['uid'] . " AND parent_cat = '$cat_id' ORDER BY order_id, title"
        );

        while ($line = \SmallSmallRSS\Database::fetch_assoc($result)) {

            $cat = array();
            $cat['id'] = 'CAT:' . $line['id'];
            $cat['bare_id'] = (int) $line['id'];
            $cat['name'] = $line['title'];
            $cat['items'] = array();
            $cat['checkbox'] = false;
            $cat['type'] = 'category';
            $cat['unread'] = 0;
            $cat['child_unread'] = 0;
            $cat['auxcounter'] = 0;

            $cat['items'] = $this->getCategoryItems($line['id']);

            $num_children = $this->countChildren($cat);
            $cat['param'] = vsprintf(_ngettext('(%d feed)', '(%d feeds)', $num_children), $num_children);

            if ($num_children > 0 || $show_empty_cats) {
                array_push($items, $cat);
            }

        }
        $substring_for_date = \SmallSmallRSS\Database::getSubstringForDateFunction();
        $feed_result = \SmallSmallRSS\Database::query(
            'SELECT id, title, last_error,
            ' . $substring_for_date . "(last_updated,1,19) AS last_updated
             FROM ttrss_feeds
             WHERE
                 cat_id = '$cat_id'
                 AND owner_uid = " . $_SESSION['uid'] . "
                 $search_qpart
             ORDER BY order_id, title"
        );

        while ($feed_line = \SmallSmallRSS\Database::fetch_assoc($feed_result)) {
            $feed = array();
            $feed['id'] = 'FEED:' . $feed_line['id'];
            $feed['bare_id'] = (int) $feed_line['id'];
            $feed['auxcounter'] = 0;
            $feed['name'] = $feed_line['title'];
            $feed['checkbox'] = false;
            $feed['unread'] = 0;
            $feed['error'] = $feed_line['last_error'];
            $feed['icon'] = getFeedIcon($feed_line['id']);
            $feed['param'] = make_local_datetime($feed_line['last_updated'], true, $_SESSION['uid']);

            array_push($items, $feed);
        }

        return $items;
    }

    public function getfeedtree()
    {
        echo json_encode($this->makefeedtree());
    }

    public function makefeedtree()
    {

        if ($this->getMode() != 2 && isset($_SESSION['prefs_feed_search'])) {
            $search = $_SESSION['prefs_feed_search'];
        } else {
            $search = '';
        }

        if ($search) {
            $search_qpart = " AND LOWER(title) LIKE LOWER('%$search%')";
        } else {
            $search_qpart = '';
        }

        $root = array();
        $root['id'] = 'root';
        $root['name'] = __('Feeds');
        $root['items'] = array();
        $root['type'] = 'category';
        $root['param'] = 0;

        $enable_cats = \SmallSmallRSS\DBPrefs::read('ENABLE_FEED_CATS');

        if ($this->getMode() == 2) {

            if ($enable_cats) {
                $cat = $this->initFeedlistCat(-1);
            } else {
                $cat['items'] = array();
            }

            foreach (\SmallSmallRSS\Feeds::getSpecialFeedIds() as $i) {
                array_push($cat['items'], $this->initFeedlistFeed($i));
            }

            /* Plugin feeds for -1 */
            $feeds = \SmallSmallRSS\PluginHost::getInstance()->getFeeds(-1);
            if ($feeds) {
                foreach ($feeds as $feed) {
                    $feed_id = \SmallSmallRSS\PluginHost::pfeed_to_feed_id($feed['id']);
                    $item = array();
                    $item['id'] = 'FEED:' . $feed_id;
                    $item['bare_id'] = (int) $feed_id;
                    $item['auxcounter'] = 0;
                    $item['name'] = $feed['title'];
                    $item['checkbox'] = false;
                    $item['error'] = '';
                    $item['icon'] = $feed['icon'];
                    $item['param'] = '';
                    $item['unread'] = 0; //$feed['sender']->get_unread($feed['id']);
                    $item['type'] = 'feed';
                    array_push($cat['items'], $item);
                }
            }

            if ($enable_cats) {
                array_push($root['items'], $cat);
            } else {
                $root['items'] = array_merge($root['items'], $cat['items']);
            }
            $labels = \SmallSmallRSS\Labels::getAll($_SESSION['uid']);
            if ($labels) {
                if (\SmallSmallRSS\DBPrefs::read('ENABLE_FEED_CATS')) {
                    $cat = $this->initFeedlistCat(-2);
                } else {
                    $cat['items'] = array();
                }
                foreach ($labels as $line) {
                    $label_id = \SmallSmallRSS\Labels::toFeedId($line['id']);
                    $feed = $this->initFeedlistFeed($label_id, false, 0);
                    $feed['fg_color'] = $line['fg_color'];
                    $feed['bg_color'] = $line['bg_color'];
                    array_push($cat['items'], $feed);
                }
                if ($enable_cats) {
                    array_push($root['items'], $cat);
                } else {
                    $root['items'] = array_merge($root['items'], $cat['items']);
                }
            }
        }

        if ($enable_cats) {
            $show_empty_cats = (
                !empty($_REQUEST['force_show_empty'])
                || ($this->getMode() != 2 && !$search)
            );
            $result = \SmallSmallRSS\Database::query(
                'SELECT id, title
                 FROM ttrss_feed_categories
                 WHERE
                     owner_uid = ' . $_SESSION['uid'] . '
                     AND parent_cat IS NULL
                 ORDER BY order_id, title'
            );
            while ($line = \SmallSmallRSS\Database::fetch_assoc($result)) {
                $cat = array();
                $cat['id'] = 'CAT:' . $line['id'];
                $cat['bare_id'] = (int) $line['id'];
                $cat['auxcounter'] = 0;
                $cat['name'] = $line['title'];
                $cat['items'] = array();
                $cat['checkbox'] = false;
                $cat['type'] = 'category';
                $cat['unread'] = 0;
                $cat['child_unread'] = 0;
                $cat['items'] = $this->getCategoryItems($line['id']);
                $num_children = $this->countChildren($cat);
                $cat['param'] = vsprintf(_ngettext('(%d feed)', '(%d feeds)', $num_children), $num_children);
                if ($num_children > 0 || $show_empty_cats) {
                    array_push($root['items'], $cat);
                }
                $root['param'] += count($cat['items']);
            }

            /* Uncategorized is a special case */

            $cat = array();
            $cat['id'] = 'CAT:0';
            $cat['bare_id'] = 0;
            $cat['auxcounter'] = 0;
            $cat['name'] = __('Uncategorized');
            $cat['items'] = array();
            $cat['type'] = 'category';
            $cat['checkbox'] = false;
            $cat['unread'] = 0;
            $cat['child_unread'] = 0;
            $substring_for_date = \SmallSmallRSS\Database::getSubstringForDateFunction();
            $feed_result = \SmallSmallRSS\Database::query(
                'SELECT id, title,last_error,
                ' . $substring_for_date . '(last_updated,1,19) AS last_updated
                 FROM ttrss_feeds
                 WHERE
                     cat_id IS NULL
                     AND owner_uid = ' . $_SESSION['uid'] . "
                     $search_qpart
                 ORDER BY order_id, title"
            );

            while ($feed_line = \SmallSmallRSS\Database::fetch_assoc($feed_result)) {
                $feed = array();
                $feed['id'] = 'FEED:' . $feed_line['id'];
                $feed['bare_id'] = (int) $feed_line['id'];
                $feed['auxcounter'] = 0;
                $feed['name'] = $feed_line['title'];
                $feed['checkbox'] = false;
                $feed['error'] = $feed_line['last_error'];
                $feed['icon'] = getFeedIcon($feed_line['id']);
                $feed['param'] = make_local_datetime($feed_line['last_updated'], true, $_SESSION['uid']);
                $feed['unread'] = 0;
                $feed['type'] = 'feed';

                array_push($cat['items'], $feed);
            }

            $cat['param'] = vsprintf(_ngettext('(%d feed)', '(%d feeds)', count($cat['items'])), count($cat['items']));

            if (count($cat['items']) > 0 || $show_empty_cats) {
                array_push($root['items'], $cat);
            }

            $num_children = $this->countChildren($root);
            $root['param'] = vsprintf(_ngettext('(%d feed)', '(%d feeds)', $num_children), $num_children);

        } else {
            $substring_for_date = \SmallSmallRSS\Database::getSubstringForDateFunction();
            $feed_result = \SmallSmallRSS\Database::query(
                'SELECT id, title, last_error,
                ' . $substring_for_date . '(last_updated,1,19) AS last_updated
                FROM ttrss_feeds
                WHERE
                    owner_uid = ' . $_SESSION['uid'] . "
                    $search_qpart
                ORDER BY order_id, title"
            );

            while ($feed_line = \SmallSmallRSS\Database::fetch_assoc($feed_result)) {
                $feed = array();
                $feed['id'] = 'FEED:' . $feed_line['id'];
                $feed['bare_id'] = (int) $feed_line['id'];
                $feed['auxcounter'] = 0;
                $feed['name'] = $feed_line['title'];
                $feed['checkbox'] = false;
                $feed['error'] = $feed_line['last_error'];
                $feed['icon'] = getFeedIcon($feed_line['id']);
                $feed['param'] = make_local_datetime($feed_line['last_updated'], true, $_SESSION['uid']);
                $feed['unread'] = 0;
                $feed['type'] = 'feed';

                array_push($root['items'], $feed);
            }

            $root['param'] = vsprintf(_ngettext('(%d feed)', '(%d feeds)', count($cat['items'])), count($cat['items']));
        }

        $fl = array();
        $fl['identifier'] = 'id';
        $fl['label'] = 'name';

        if ($this->getMode() != 2) {
            $fl['items'] = array($root);
        } else {
            $fl['items'] =& $root['items'];
        }

        return $fl;
    }

    public function catsortreset()
    {
        \SmallSmallRSS\Database::query(
            'UPDATE ttrss_feed_categories
             SET order_id = 0
             WHERE owner_uid = ' . $_SESSION['uid']
        );
        return;
    }

    public function feedsortreset()
    {
        \SmallSmallRSS\Database::query(
            'UPDATE ttrss_feeds
             SET order_id = 0
             WHERE owner_uid = ' . $_SESSION['uid']
        );
        return;
    }

    private function processCategoryOrder(&$data_map, $item_id, $parent_id = false, $nest_level = 0)
    {
        $prefix = '';
        for ($i = 0; $i < $nest_level; $i++) {
            $prefix .= '   ';
        }

        $bare_item_id = substr($item_id, strpos($item_id, ':')+1);

        if ($item_id != 'root') {
            if ($parent_id && $parent_id != 'root') {
                $parent_bare_id = substr($parent_id, strpos($parent_id, ':')+1);
                $parent_qpart = \SmallSmallRSS\Database::escape_string($parent_bare_id);
            } else {
                $parent_qpart = 'NULL';
            }

            \SmallSmallRSS\Database::query(
                "UPDATE ttrss_feed_categories
                 SET parent_cat = $parent_qpart
                 WHERE
                     id = '$bare_item_id'
                     AND owner_uid = " . $_SESSION['uid']
            );
        }

        $order_id = 0;

        $cat = $data_map[$item_id];

        if ($cat && is_array($cat)) {
            foreach ($cat as $item) {
                $id = $item['_reference'];
                $bare_id = substr($id, strpos($id, ':')+1);
                if ($item['_reference']) {
                    if (strpos($id, 'FEED') === 0) {

                        $cat_id = ($item_id != 'root') ?
                            \SmallSmallRSS\Database::escape_string($bare_item_id) : 'NULL';

                        $cat_qpart = ($cat_id != 0) ? "cat_id = '$cat_id'" :
                            'cat_id = NULL';

                        \SmallSmallRSS\Database::query(
                            "UPDATE ttrss_feeds
                             SET order_id = $order_id, $cat_qpart
                             WHERE
                                 id = '$bare_id'
                                 AND owner_uid = " . $_SESSION['uid']
                        );

                    } elseif (strpos($id, 'CAT:') === 0) {
                        $this->processCategoryOrder(
                            $data_map,
                            $item['_reference'],
                            $item_id,
                            $nest_level + 1
                        );

                        if ($item_id != 'root') {
                            $parent_qpart = \SmallSmallRSS\Database::escape_string($bare_id);
                        } else {
                            $parent_qpart = 'NULL';
                        }

                        \SmallSmallRSS\Database::query(
                            "UPDATE ttrss_feed_categories
                             SET order_id = '$order_id'
                             WHERE id = '$bare_id'
                             AND owner_uid = " . $_SESSION['uid']
                        );
                    }
                }
                $order_id += 1;
            }
        }
    }

    public function savefeedorder()
    {
        $data = json_decode($_POST['payload'], true);
        if (!is_array($data['items'])) {
            $data['items'] = json_decode($data['items'], true);
        }
        if (is_array($data) && is_array($data['items'])) {
            $cat_order_id = 0;
            $data_map = array();
            $root_item = false;
            foreach ($data['items'] as $item) {
                if (is_array($item['items'])) {
                    if (isset($item['items']['_reference'])) {
                        $data_map[$item['id']] = array($item['items']);
                    } else {
                        $data_map[$item['id']] =& $item['items'];
                    }
                }
                if ($item['id'] == 'root') {
                    $root_item = $item['id'];
                }
            }
            $this->processCategoryOrder($data_map, $root_item);
        }
    }

    public function removeicon()
    {
        $feed_id = $this->getSQLEscapedStringFromRequest('feed_id');
        $result = \SmallSmallRSS\Database::query(
            "SELECT id
            FROM ttrss_feeds
            WHERE
                id = '$feed_id'
                AND owner_uid = ". $_SESSION['uid']
        );
        if (\SmallSmallRSS\Database::num_rows($result) != 0) {
            @unlink(\SmallSmallRSS\Config::get('ICONS_DIR') . "/$feed_id.ico");
            \SmallSmallRSS\Database::query(
                "UPDATE ttrss_feeds
                 SET favicon_avg_color = NULL
                 WHERE id = '$feed_id'"
            );
        }

        return;
    }

    public function uploadicon()
    {
        header('Content-type: text/html');
        $tmp_file = false;
        if (is_uploaded_file($_FILES['icon_file']['tmp_name'])) {
            $tmp_file = tempnam(\SmallSmallRSS\Config::get('CACHE_DIR') . '/upload', 'icon');
            $result = move_uploaded_file(
                $_FILES['icon_file']['tmp_name'],
                $tmp_file
            );
            if (!$result) {
                return;
            }
        } else {
            return;
        }
        $icon_file = $tmp_file;
        $feed_id = $this->getSQLEscapedStringFromRequest('feed_id');
        if (is_file($icon_file) && $feed_id) {
            if (filesize($icon_file) < 20000) {
                $result = \SmallSmallRSS\Database::query(
                    "SELECT id
                     FROM ttrss_feeds
                     WHERE
                         id = '$feed_id'
                         AND owner_uid = ". $_SESSION['uid']
                );

                if (\SmallSmallRSS\Database::num_rows($result) != 0) {
                    @unlink(\SmallSmallRSS\Config::get('ICONS_DIR') . "/$feed_id.ico");
                    if (rename($icon_file, \SmallSmallRSS\Config::get('ICONS_DIR') . "/$feed_id.ico")) {
                        \SmallSmallRSS\Database::query(
                            "UPDATE ttrss_feeds
                             SET favicon_avg_color = ''
                             WHERE id = '$feed_id'"
                        );

                        $rc = 0;
                    }
                } else {
                    $rc = 2;
                }
            } else {
                $rc = 1;
            }
        } else {
            $rc = 2;
        }
        unlink($icon_file);
        echo '<script type="text/javascript">';
        echo "\nparent.uploadIconHandler($rc);\n";
        echo '</script>';
        return;
    }

    public function editfeed()
    {
        $feed_id = $this->getSQLEscapedStringFromRequest('id');

        $result = \SmallSmallRSS\Database::query(
            "SELECT *
             FROM ttrss_feeds
             WHERE
                 id = '$feed_id' AND
                 owner_uid = " . $_SESSION['uid']
        );

        $auth_pass_encrypted = \SmallSmallRSS\Database::fromSQLBool(
            \SmallSmallRSS\Database::fetch_result(
                $result,
                0,
                'auth_pass_encrypted'
            )
        );

        $title = htmlspecialchars(\SmallSmallRSS\Database::fetch_result($result, 0, 'title'));

        echo "<input data-dojo-type=\"dijit.form.TextBox\" style=\"display: none\" name=\"id\" value=\"$feed_id\" />";
        echo '<input data-dojo-type="dijit.form.TextBox" style="display: none" name="op" value="pref-feeds" />';
        echo '<input data-dojo-type="dijit.form.TextBox" style="display: none" name="method" value="editSave" />';
        echo '<div class="dlgSec">';
        echo __('Feed');
        echo '</div>';
        echo '<div class="dlgSecCont">';

        /* Title */
        echo '<input data-dojo-type="dijit.form.ValidationTextBox" required="1" placeHolder="';
        echo __('Feed Title');
        echo "\" style=\"font-size: 16px; width: 20em\" name=\"title\" value=\"$title\">";

        /* Feed URL */
        $feed_url = htmlspecialchars(
            \SmallSmallRSS\Database::fetch_result($result, 0, 'feed_url')
        );
        echo '<hr/>';
        echo __('URL:') . ' ';
        echo '<input data-dojo-type="dijit.form.ValidationTextBox" required="1" placeHolder="';
        echo __('Feed URL');
        echo '\" regExp="^(http|https)://.*" name="feed_url"';
        echo " value=\"$feed_url\">";
        $last_error = \SmallSmallRSS\Database::fetch_result($result, 0, 'last_error');
        if ($last_error) {
            echo '&nbsp;<span title="';
            echo htmlspecialchars($last_error);
            echo '" class="feed_error">(error)</span>';
        }
        /* Category */
        if (\SmallSmallRSS\DBPrefs::read('ENABLE_FEED_CATS')) {
            $cat_id = \SmallSmallRSS\Database::fetch_result($result, 0, 'cat_id');
            echo '<hr/>';
            echo __('Place in category:') . ' ';
            print_feed_cat_select(
                'cat_id',
                $cat_id,
                'data-dojo-type="dijit.form.Select"'
            );
        }
        echo '</div>';
        echo '<div class="dlgSec">';
        echo __('Update');
        echo '</div>';
        echo '<div class="dlgSecCont">';
        /* Update Interval */
        $update_interval = \SmallSmallRSS\Database::fetch_result($result, 0, 'update_interval');
        $form_elements_renderer = new \SmallSmallRSS\Renderers\FormElements();
        $form_elements_renderer->renderSelect(
            'update_interval',
            $update_interval,
            \SmallSmallRSS\Constants::update_intervals(),
            'data-dojo-type="dijit.form.Select"'
        );
        /* Purge intl */
        $purge_interval = \SmallSmallRSS\Database::fetch_result($result, 0, 'purge_interval');
        echo '<hr/>';
        echo __('Article purging:') . ' ';
        $form_elements_renderer->renderSelect(
            'purge_interval',
            $purge_interval,
            \SmallSmallRSS\Constants::purge_intervals(),
            'data-dojo-type="dijit.form.Select" '
            . ((\SmallSmallRSS\Config::get('FORCE_ARTICLE_PURGE') == 0) ? '' : 'disabled="1"')
        );
        echo '</div>';
        echo '<div class="dlgSec">';
        echo __('Authentication');
        echo '</div>';
        echo '<div class="dlgSecCont">';
        $auth_login = htmlspecialchars(\SmallSmallRSS\Database::fetch_result($result, 0, 'auth_login'));
        echo '<input data-dojo-type="dijit.form.TextBox" id="feedEditDlg_login"';
        echo ' placeHolder="';
        echo __('Login');
        echo "\" name=\"auth_login\" value=\"$auth_login\">";
        echo "<hr/>";
        $auth_pass = \SmallSmallRSS\Database::fetch_result($result, 0, 'auth_pass');
        if ($auth_pass_encrypted) {
            $auth_pass = \SmallSmallRSS\Crypt::de($auth_pass);
        }
        $auth_pass = htmlspecialchars($auth_pass);
        echo '<input data-dojo-type="dijit.form.TextBox" type="password" name="auth_pass"';
        echo ' placeHolder="';
        echo __('Password');
        echo "\" value=\"$auth_pass\">";
        echo '<div data-dojo-type="dijit.Tooltip" connectId="feedEditDlg_login" position="below">';
        echo __('<b>Hint:</b> you need to fill in your login information if your feed requires authentication, except for Twitter feeds.');
        echo '</div>';
        echo '</div>';
        echo '<div class="dlgSec">';
        echo __('Options');
        echo '</div>';
        echo '<div class="dlgSecCont">';
        $private = \SmallSmallRSS\Database::fromSQLBool(\SmallSmallRSS\Database::fetch_result($result, 0, 'private'));
        if ($private) {
            $checked = ' checked="checked"';
        } else {
            $checked = '';
        }
        echo "<input data-dojo-type=\"dijit.form.CheckBox\" type=\"checkbox\" name=\"private\" id=\"private\"";
        echo " $checked>";
        echo "&nbsp;";
        echo "<label for=\"private\">";
        echo __('Hide from Popular feeds');
        echo '</label>';

        $include_in_digest = \SmallSmallRSS\Database::fromSQLBool(
            \SmallSmallRSS\Database::fetch_result($result, 0, 'include_in_digest')
        );

        if ($include_in_digest) {
            $checked = ' checked="checked"';
        } else {
            $checked = '';
        }

        echo "<hr/>";
        echo "<input data-dojo-type=\"dijit.form.CheckBox\" type=\"checkbox\" id=\"include_in_digest\"
            name=\"include_in_digest\"
            $checked>&nbsp;<label for=\"include_in_digest\">";
        echo __('Include in e-mail digest');
        echo '</label>';
        $always_display_enclosures = \SmallSmallRSS\Database::fromSQLBool(
            \SmallSmallRSS\Database::fetch_result($result, 0, 'always_display_enclosures')
        );

        if ($always_display_enclosures) {
            $checked = ' checked="checked"';
        } else {
            $checked = '';
        }

        echo "<hr/>";
        echo "<input type=\"checkbox\" id=\"always_display_enclosures\" name=\"always_display_enclosures\"";
        echo " $checked data-dojo-type=\"dijit.form.CheckBox\">";
        echo "&nbsp;";
        echo "<label for=\"always_display_enclosures\">";
        echo __('Always display image attachments');
        echo '</label>';

        $hide_images = \SmallSmallRSS\Database::fromSQLBool(
            \SmallSmallRSS\Database::fetch_result($result, 0, 'hide_images')
        );
        if ($hide_images) {
            $checked = ' checked="checked"';
        } else {
            $checked = '';
        }

        echo "<hr />";
        echo "<input type=\"checkbox\" id=\"hide_images\" name=\"hide_images\" $checked";
        echo " data-dojo-type=\"dijit.form.CheckBox\"> ";
        echo "&nbsp;";
        echo "<label for=\"hide_images\">";
        echo __('Do not embed images');
        echo '</label>';

        $cache_images = \SmallSmallRSS\Database::fromSQLBool(
            \SmallSmallRSS\Database::fetch_result($result, 0, 'cache_images')
        );

        if ($cache_images) {
            $checked = ' checked="checked"';
        } else {
            $checked = '';
        }

        echo "<hr />";
        echo "<input type=\"checkbox\" id=\"cache_images\" name=\"cache_images\" $checked";
        echo "  data-dojo-type=\"dijit.form.CheckBox\">";
        echo "&nbsp;";
        echo "<label for=\"cache_images\">";
        echo __('Cache images locally');
        echo '</label>';

        $mark_unread_on_update = \SmallSmallRSS\Database::fromSQLBool(
            \SmallSmallRSS\Database::fetch_result($result, 0, 'mark_unread_on_update')
        );

        if ($mark_unread_on_update) {
            $checked = 'checked';
        } else {
            $checked = '';
        }

        echo "<hr />";
        echo "<input data-dojo-type=\"dijit.form.CheckBox\" type=\"checkbox\" id=\"mark_unread_on_update\"
            name=\"mark_unread_on_update\"
            $checked>&nbsp;<label for=\"mark_unread_on_update\">";
        echo __('Mark updated articles as unread');
        echo '</label>';
        echo '</div>';

        /* Icon */

        echo '<div class="dlgSec">';
        echo __('Icon');
        echo '</div>';
        echo '<div class="dlgSecCont">';

        echo '<iframe name="icon_upload_iframe"
            style="width: 400px; height: 100px; display: none;"></iframe>';

        echo "<form style='display : block' target=\"icon_upload_iframe\"
            enctype=\"multipart/form-data\" method=\"POST\"
            action=\"backend.php\">
            <input id=\"icon_file\" size=\"10\" name=\"icon_file\" type=\"file\">
            <input type=\"hidden\" name=\"op\" value=\"pref-feeds\">
            <input type=\"hidden\" name=\"feed_id\" value=\"$feed_id\">
            <input type=\"hidden\" name=\"method\" value=\"uploadicon\">
            <button data-dojo-type=\"dijit.form.Button\" onclick=\"return uploadFeedIcon();\"
                type=\"submit\">";
        echo __('Replace');
        echo "</button>
            <button data-dojo-type=\"dijit.form.Button\" onclick=\"return removeFeedIcon($feed_id);\"
                type=\"submit\">";
        echo __('Remove');
        echo '</button>
            </form>';

        echo '</div>';

        \SmallSmallRSS\PluginHost::getInstance()->runHooks(
            \SmallSmallRSS\Hooks::PREFS_EDIT_FEED,
            $feed_id
        );

        $title = htmlspecialchars($title, ENT_QUOTES);

        echo "<div class='dlgButtons'>
            <div style=\"float: left\">
            <button data-dojo-type=\"dijit.form.Button\" onclick='return unsubscribeFeed($feed_id, \"$title\")'>".
            __('Unsubscribe');
        echo '</button>';

        if (\SmallSmallRSS\Config::get('PUBSUBHUBBUB_ENABLED')) {
            $pubsub_state = \SmallSmallRSS\Database::fetch_result($result, 0, 'pubsub_state');
            $pubsub_btn_disabled = ($pubsub_state == 2) ? '' : 'disabled="1"';

            echo "<button data-dojo-type=\"dijit.form.Button\" id=\"pubsubReset_Btn\" $pubsub_btn_disabled
                    onclick='return resetPubSub($feed_id, \"$title\")'>";
            echo __('Resubscribe to push updates').
                '</button>';
        }

        echo '</div>';

        echo '<div data-dojo-type="dijit.Tooltip" connectId="pubsubReset_Btn" position="below">';
        echo __('Resets PubSubHubbub subscription status for push-enabled feeds.');
        echo '</div>';

        echo "<button data-dojo-type=\"dijit.form.Button\" onclick=\"return dijit.byId('feedEditDlg').execute()\">";
        echo __('Save');
        echo "</button>";
        echo "<button data-dojo-type=\"dijit.form.Button\" onclick=\"return dijit.byId('feedEditDlg').hide()\">";
        echo __('Cancel');
        echo '</button>
        </div>';

        return;
    }

    public function editfeeds()
    {

        $feed_ids = $this->getSQLEscapedStringFromRequest('ids');

        \SmallSmallRSS\Renderers\Messages::renderNotice(
            'Enable the options you wish to apply using checkboxes on the right:'
        );

        echo '<p>';

        echo "<input data-dojo-type=\"dijit.form.TextBox\" style=\"display: none\" name=\"ids\" value=\"$feed_ids\">";
        echo '<input data-dojo-type="dijit.form.TextBox" style="display: none" name="op" value="pref-feeds">';
        echo '<input data-dojo-type="dijit.form.TextBox" style="display: none" name="method" value="batchEditSave">';

        echo '<div class="dlgSec">';
        echo __('Feed');
        echo '</div>';
        echo '<div class="dlgSecCont">';

        /* Title */

        echo '<input data-dojo-type="dijit.form.ValidationTextBox"
            disabled="1" style="font-size: 16px; width: 20em;" required="1"
            name="title" value="">';

        $this->renderBatchEditCheckbox('title');

        /* Feed URL */

        echo '<br/>';

        echo __('URL:') . ' ';
        echo "<input data-dojo-type=\"dijit.form.ValidationTextBox\" disabled=\"1\"
            required=\"1\" regExp='^(http|https)://.*' style=\"width: 20em\"
            name=\"feed_url\" value=\"\">";

        $this->renderBatchEditCheckbox('feed_url');

        /* Category */

        if (\SmallSmallRSS\DBPrefs::read('ENABLE_FEED_CATS')) {
            echo '<br/>';
            echo __('Place in category:') . ' ';
            print_feed_cat_select(
                'cat_id',
                false,
                'disabled="1" data-dojo-type="dijit.form.Select"'
            );
            $this->renderBatchEditCheckbox('cat_id');
        }
        echo '</div>';
        echo '<div class="dlgSec">';
        echo __('Update');
        echo '</div>';
        echo '<div class="dlgSecCont">';

        /* Update Interval */

        $form_elements_renderer = new \SmallSmallRSS\Renderers\FormElements();
        $form_elements_renderer->renderSelect(
            'update_interval',
            '',
            \SmallSmallRSS\Constants::update_intervals(),
            'disabled="1" data-dojo-type="dijit.form.Select"'
        );

        $this->renderBatchEditCheckbox('update_interval');

        /* Purge intl */

        if (\SmallSmallRSS\Config::get('FORCE_ARTICLE_PURGE') == 0) {
            echo '<br/>';
            echo __('Article purging:') . ' ';
            $renderer = new \SmallSmallRSS\Renderers\FormElements();
            $renderer->renderSelect(
                'purge_interval',
                '',
                \SmallSmallRSS\Constants::purge_intervals(),
                'disabled="1" data-dojo-type="dijit.form.Select"'
            );

            $this->renderBatchEditCheckbox('purge_interval');
        }

        echo '</div>';
        echo '<div class="dlgSec">';
        echo __('Authentication');
        echo '</div>';
        echo '<div class="dlgSecCont">';

        echo '<input data-dojo-type="dijit.form.TextBox"
            placeHolder="';
        echo __('Login');
        echo '" disabled="1"
            name="auth_login" value="">';

        $this->renderBatchEditCheckbox('auth_login');

        echo '<br/>';
        echo '<input data-dojo-type="dijit.form.TextBox" type="password" name="auth_pass" placeHolder="';
        echo __('Password');
        echo '" disabled="1" value="">';

        $this->renderBatchEditCheckbox('auth_pass');

        echo '</div>';
        echo '<div class="dlgSec">';
        echo __('Options');
        echo '</div>';
        echo '<div class="dlgSecCont">';

        echo "<input disabled=\"1\" type=\"checkbox\" name=\"private\" id=\"private\"
            data-dojo-type=\"dijit.form.CheckBox\">&nbsp;<label id=\"private_l\" class='insensitive' for=\"private\">";
        echo __('Hide from Popular feeds');
        echo '</label>';

        echo '&nbsp;';
        $this->renderBatchEditCheckbox('private', 'private_l');

        echo "<br/><input disabled=\"1\" type=\"checkbox\" id=\"include_in_digest\"
            name=\"include_in_digest\"
            data-dojo-type=\"dijit.form.CheckBox\">&nbsp;<label id=\"include_in_digest_l\"
            class=\"insensitive\" for=\"include_in_digest\">";
        echo __('Include in e-mail digest');
        echo '</label>';

        echo '&nbsp;';
        $this->renderBatchEditCheckbox('include_in_digest', 'include_in_digest_l');

        echo "<br/><input disabled=\"1\" type=\"checkbox\" id=\"always_display_enclosures\"
            name=\"always_display_enclosures\"
            data-dojo-type=\"dijit.form.CheckBox\">";
        echo "&nbsp;<label id=\"always_display_enclosures_l\" class='insensitive' for=\"always_display_enclosures\">";
        echo __('Always display image attachments');
        echo '</label>';

        echo '&nbsp;';
        $this->renderBatchEditCheckbox('always_display_enclosures', 'always_display_enclosures_l');

        echo "<br/><input disabled=\"1\" type=\"checkbox\" id=\"hide_images\"
            name=\"hide_images\"
            data-dojo-type=\"dijit.form.CheckBox\">&nbsp;<label class='insensitive' id=\"hide_images_l\"
            for=\"hide_images\">".
            __('Do not embed images');
        echo '</label>';

        echo '&nbsp;';
        $this->renderBatchEditCheckbox('hide_images', 'hide_images_l');

        echo "<br/><input disabled=\"1\" type=\"checkbox\" id=\"cache_images\"
            name=\"cache_images\"
            data-dojo-type=\"dijit.form.CheckBox\">&nbsp;<label class='insensitive' id=\"cache_images_l\"
            for=\"cache_images\">".
            __('Cache images locally');
        echo '</label>';

        echo '&nbsp;';
        $this->renderBatchEditCheckbox('cache_images', 'cache_images_l');

        echo "<br/><input disabled=\"1\" type=\"checkbox\" id=\"mark_unread_on_update\"
            name=\"mark_unread_on_update\"
            data-dojo-type=\"dijit.form.CheckBox\">";
        echo "&nbsp;";
        echo "<label id=\"mark_unread_on_update_l\" class='insensitive' for=\"mark_unread_on_update\">";
        echo __('Mark updated articles as unread');
        echo '</label>';

        echo '&nbsp;';
        $this->renderBatchEditCheckbox('mark_unread_on_update', 'mark_unread_on_update_l');

        echo '</div>';

        echo "<div class='dlgButtons'>
            <button data-dojo-type=\"dijit.form.Button\"
                onclick=\"return dijit.byId('feedEditDlg').execute()\">".
            __('Save');
        echo "</button>
            <button data-dojo-type=\"dijit.form.Button\"
            onclick=\"return dijit.byId('feedEditDlg').hide()\">".
            __('Cancel');
        echo '</button>
            </div>';

        return;
    }

    public function batchEditSave()
    {
        return $this->editsaveops(true);
    }

    public function editSave()
    {
        return $this->editsaveops(false);
    }

    public function editsaveops($batch)
    {

        $feed_title = \SmallSmallRSS\Database::escape_string(trim($_POST['title']));
        $feed_link = \SmallSmallRSS\Database::escape_string(trim($_POST['feed_url']));
        $upd_intl = (int) \SmallSmallRSS\Database::escape_string($_POST['update_interval']);
        $purge_intl = (int) \SmallSmallRSS\Database::escape_string($_POST['purge_interval']);
        $feed_id = (int) \SmallSmallRSS\Database::escape_string($_POST['id']); /* editSave */
        $feed_ids = \SmallSmallRSS\Database::escape_string($_POST['ids']); /* batchEditSave */
        $cat_id = (int) \SmallSmallRSS\Database::escape_string($_POST['cat_id']);
        $auth_login = \SmallSmallRSS\Database::escape_string(trim($_POST['auth_login']));
        $auth_pass = trim($_POST['auth_pass']);


        $private = \SmallSmallRSS\Database::toSQLBool(self::checkBoxToBool($_POST['private']));
        $include_in_digest = \SmallSmallRSS\Database::toSQLBool(self::checkBoxToBool($_POST['include_in_digest']));
        $cache_images = \SmallSmallRSS\Database::toSQLBool(self::checkBoxToBool($_POST['cache_images']));
        $hide_images = \SmallSmallRSS\Database::toSQLBool(self::checkBoxToBool($_POST['hide_images']));
        $always_display_enclosures = \SmallSmallRSS\Database::toSQLBool(
            self::checkBoxToBool($_POST['always_display_enclosures'])
        );
        $mark_unread_on_update = \SmallSmallRSS\Database::toSQLBool(
            self::checkBoxToBool($_POST['mark_unread_on_update'])
        );

        if (\SmallSmallRSS\Crypt::is_enabled()) {
            $auth_pass = substr(\SmallSmallRSS\Crypt::en($auth_pass), 0, 250);
            $auth_pass_encrypted = 'true';
        } else {
            $auth_pass_encrypted = 'false';
        }

        $auth_pass = \SmallSmallRSS\Database::escape_string($auth_pass);

        if (\SmallSmallRSS\DBPrefs::read('ENABLE_FEED_CATS')) {
            if ($cat_id && $cat_id != 0) {
                $category_qpart = "cat_id = '$cat_id',";
                $category_qpart_nocomma = "cat_id = '$cat_id'";
            } else {
                $category_qpart = 'cat_id = NULL,';
                $category_qpart_nocomma = 'cat_id = NULL';
            }
        } else {
            $category_qpart = '';
            $category_qpart_nocomma = '';
        }

        if (!$batch) {
            $result = \SmallSmallRSS\Database::query(
                "UPDATE ttrss_feeds
                 SET
                     $category_qpart
                     title = '$feed_title',
                     feed_url = '$feed_link',
                     update_interval = '$upd_intl',
                     purge_interval = '$purge_intl',
                     auth_login = '$auth_login',
                     auth_pass = '$auth_pass',
                     auth_pass_encrypted = $auth_pass_encrypted,
                     private = $private,
                     cache_images = $cache_images,
                     hide_images = $hide_images,
                     include_in_digest = $include_in_digest,
                     always_display_enclosures = $always_display_enclosures,
                     mark_unread_on_update = $mark_unread_on_update
                 WHERE
                     id = '$feed_id'
                     AND owner_uid = " . $_SESSION['uid']
            );

            \SmallSmallRSS\PluginHost::getInstance()->runHooks(
                \SmallSmallRSS\Hooks::PREFS_SAVE_FEED,
                $feed_id
            );

        } else {
            $feed_data = array();
            foreach (array_keys($_POST) as $k) {
                if ($k != 'op' && $k != 'method' && $k != 'ids') {
                    $feed_data[$k] = $_POST[$k];
                }
            }
            \SmallSmallRSS\Database::query('BEGIN');
            foreach (array_keys($feed_data) as $k) {
                $qpart = '';
                switch ($k) {
                    case 'title':
                        $qpart = "title = '$feed_title'";
                        break;

                    case 'feed_url':
                        $qpart = "feed_url = '$feed_link'";
                        break;

                    case 'update_interval':
                        $qpart = "update_interval = '$upd_intl'";
                        break;

                    case 'purge_interval':
                        $qpart = "purge_interval = '$purge_intl'";
                        break;

                    case 'auth_login':
                        $qpart = "auth_login = '$auth_login'";
                        break;

                    case 'auth_pass':
                        $qpart = "auth_pass = '$auth_pass',
                                  auth_pass_encrypted = $auth_pass_encrypted";
                        break;

                    case 'private':
                        $qpart = "private = $private";
                        break;

                    case 'include_in_digest':
                        $qpart = "include_in_digest = $include_in_digest";
                        break;

                    case 'always_display_enclosures':
                        $qpart = "always_display_enclosures = $always_display_enclosures";
                        break;

                    case 'mark_unread_on_update':
                        $qpart = "mark_unread_on_update = $mark_unread_on_update";
                        break;

                    case 'cache_images':
                        $qpart = "cache_images = $cache_images";
                        break;

                    case 'hide_images':
                        $qpart = "hide_images = $hide_images";
                        break;

                    case 'cat_id':
                        $qpart = $category_qpart_nocomma;
                        break;

                }

                if ($qpart) {
                    \SmallSmallRSS\Database::query(
                        "UPDATE ttrss_feeds
                         SET $qpart
                         WHERE
                             id IN ($feed_ids)
                             AND owner_uid = " . $_SESSION['uid']
                    );
                }
            }
            \SmallSmallRSS\Database::query('COMMIT');
        }
        return;
    }

    public function resetPubSub()
    {

        $ids = $this->getSQLEscapedStringFromRequest('ids');

        \SmallSmallRSS\Database::query(
            "UPDATE ttrss_feeds
             SET pubsub_state = 0 WHERE id IN ($ids)
             AND owner_uid = " . $_SESSION['uid']
        );

        return;
    }

    public function remove()
    {

        $ids = explode(',', $this->getSQLEscapedStringFromRequest('ids'));

        foreach ($ids as $id) {
            self::removeFeed($id, $_SESSION['uid']);
        }

        return;
    }

    public function clear()
    {
        $id = $this->getSQLEscapedStringFromRequest('id');
        $this->clearFeedArticles($id);
    }

    public function rescore()
    {
        $ids = explode(',', $this->getSQLEscapedStringFromRequest('ids'));

        foreach ($ids as $id) {

            $filters = load_filters($id, $_SESSION['uid'], 6);
            $substring_for_date = \SmallSmallRSS\Database::getSubstringForDateFunction();
            $result = \SmallSmallRSS\Database::query(
                'SELECT
                     title, content, link, ref_id, author,
                 ' . $substring_for_date . "(updated, 1, 19) AS updated
                 FROM
                     ttrss_user_entries, ttrss_entries
                 WHERE
                     ref_id = id
                     AND feed_id = '$id'
                     AND owner_uid = " . $_SESSION['uid']
            );
            $scores = array();
            while ($line = \SmallSmallRSS\Database::fetch_assoc($result)) {
                $tags = get_article_tags($line['ref_id'], $_SESSION['uid']);
                $article_filters = \SmallSmallRSS\ArticleFilters::get(
                    $filters,
                    $line['title'],
                    $line['content'],
                    $line['link'],
                    strtotime($line['updated']),
                    $line['author'],
                    $tags
                );

                $new_score = \SmallSmallRSS\ArticleFilters::calculateScore($article_filters);

                if (!$scores[$new_score]) {
                    $scores[$new_score] = array();
                }

                array_push($scores[$new_score], $line['ref_id']);
            }

            foreach (array_keys($scores) as $s) {
                if ($s > 1000) {
                    \SmallSmallRSS\Database::query(
                        "UPDATE ttrss_user_entries SET score = '$s',
                        marked = true WHERE
                        ref_id IN (" . join(',', $scores[$s]) . ')'
                    );
                } elseif ($s < -500) {
                    \SmallSmallRSS\Database::query(
                        "UPDATE ttrss_user_entries SET score = '$s',
                        unread = false WHERE
                        ref_id IN (" . join(',', $scores[$s]) . ')'
                    );
                } else {
                    \SmallSmallRSS\Database::query(
                        "UPDATE ttrss_user_entries SET score = '$s' WHERE
                        ref_id IN (" . join(',', $scores[$s]) . ')'
                    );
                }
            }
        }

        echo __('All done.');

    }

    public function rescoreAll()
    {

        $result = \SmallSmallRSS\Database::query(
            'SELECT id FROM ttrss_feeds WHERE owner_uid = ' . $_SESSION['uid']
        );

        while ($feed_line = \SmallSmallRSS\Database::fetch_assoc($result)) {
            $id = $feed_line['id'];
            $filters = load_filters($id, $_SESSION['uid'], 6);
            $substring_for_date = \SmallSmallRSS\Database::getSubstringForDateFunction();
            $tmp_result = \SmallSmallRSS\Database::query(
                'SELECT
                     title, content, link, ref_id, author,
                 ' . $substring_for_date . "(updated, 1, 19) AS updated
                 FROM
                     ttrss_user_entries, ttrss_entries
                 WHERE
                     ref_id = id
                     AND feed_id = '$id'
                     AND owner_uid = " . $_SESSION['uid']
            );
            $scores = array();
            while ($line = \SmallSmallRSS\Database::fetch_assoc($tmp_result)) {
                $tags = get_article_tags($line['ref_id'], $_SESSION['uid']);
                $article_filters = \SmallSmallRSS\ArticleFilters::get(
                    $filters,
                    $line['title'],
                    $line['content'],
                    $line['link'],
                    strtotime($line['updated']),
                    $line['author'],
                    $tags
                );
                $new_score = \SmallSmallRSS\ArticleFilters::calculateScore($article_filters);
                if (!$scores[$new_score]) {
                    $scores[$new_score] = array();
                }
                array_push($scores[$new_score], $line['ref_id']);
            }

            foreach (array_keys($scores) as $s) {
                if ($s > 1000) {
                    \SmallSmallRSS\Database::query(
                        "UPDATE ttrss_user_entries SET score = '$s',
                        marked = true WHERE
                        ref_id IN (" . join(',', $scores[$s]) . ')'
                    );
                } else {
                    \SmallSmallRSS\Database::query(
                        "UPDATE ttrss_user_entries SET score = '$s' WHERE
                        ref_id IN (" . join(',', $scores[$s]) . ')'
                    );
                }
            }
        }

        echo __('All done.');

    }

    public function categorize()
    {
        $ids = explode(',', $this->getSQLEscapedStringFromRequest('ids'));
        $cat_id = $this->getSQLEscapedStringFromRequest('cat_id');
        if ($cat_id == 0) {
            $cat_id_qpart = 'NULL';
        } else {
            $cat_id_qpart = "'$cat_id'";
        }
        \SmallSmallRSS\Database::query('BEGIN');
        foreach ($ids as $id) {
            \SmallSmallRSS\Database::query(
                "UPDATE ttrss_feeds
                 SET cat_id = $cat_id_qpart
                 WHERE
                     id = '$id'
                     AND owner_uid = " . $_SESSION['uid']
            );
        }
        \SmallSmallRSS\Database::query('COMMIT');
    }

    public function removeCat()
    {
        $ids = explode(',', $this->getSQLEscapedStringFromRequest('ids'));
        foreach ($ids as $id) {
            $this->removeFeedCategory($id, $_SESSION['uid']);
        }
    }

    public function addCat()
    {
        $feed_cat = \SmallSmallRSS\Database::escape_string(trim($_REQUEST['cat']));
        \SmallSmallRSS\FeedCategories::add($_SESSION['uid'], $feed_cat);
    }

    public function index()
    {

        echo '<div data-dojo-type="dijit.layout.AccordionContainer" region="center">';
        echo '<div id="pref-feeds-feeds" data-dojo-type="dijit.layout.AccordionPane" title="';
        echo __('Feeds');
        echo '">';
        $result = \SmallSmallRSS\Database::query(
            "SELECT COUNT(id) AS num_errors
             FROM ttrss_feeds
             WHERE
                 last_error != ''
                 AND owner_uid = " . $_SESSION['uid']
        );
        $num_errors = \SmallSmallRSS\Database::fetch_result($result, 0, 'num_errors');
        if ($num_errors > 0) {
            $error_button = '<button data-dojo-type="dijit.form.Button"
                      onclick="showFeedsWithErrors()" id="errorButton">' .
                __('Feeds with errors') . '</button>';
        }

        if (\SmallSmallRSS\Config::get('DB_TYPE') == 'pgsql') {
            $interval_qpart = "NOW() - INTERVAL '3 months'";
        } else {
            $interval_qpart = 'DATE_SUB(NOW(), INTERVAL 3 MONTH)';
        }

        $result = \SmallSmallRSS\Database::query(
            "SELECT COUNT(*) AS num_inactive
             FROM ttrss_feeds
             WHERE (
                 SELECT MAX(updated)
                 FROM ttrss_entries, ttrss_user_entries
                 WHERE
                     ttrss_entries.id = ref_id
                     AND ttrss_user_entries.feed_id = ttrss_feeds.id) < $interval_qpart
                     AND ttrss_feeds.owner_uid = " . $_SESSION['uid']
        );

        $num_inactive = \SmallSmallRSS\Database::fetch_result($result, 0, 'num_inactive');

        $inactive_button = '';
        if ($num_inactive) {
            $inactive_button = (
                '<button data-dojo-type="dijit.form.Button" onclick="showInactiveFeeds()">'
                . __('Inactive feeds')
                . '</button>'
            );
        }

        $feed_search = $this->getSQLEscapedStringFromRequest('search');

        if (array_key_exists('search', $_REQUEST)) {
            $_SESSION['prefs_feed_search'] = $feed_search;
        } elseif (isset($_SESSION['prefs_feed_search'])) {
            $feed_search = $_SESSION['prefs_feed_search'];
        }

        echo '<div data-dojo-type="dijit.layout.BorderContainer" gutters="false">';

        echo "<div region='top' data-dojo-type=\"dijit.Toolbar\">"; #toolbar

        echo "<div style='float: right; padding-right : 4px;'>";
        echo "<input data-dojo-type=\"dijit.form.TextBox\" id=\"feed_search\"";
        echo " size=\"20\" type=\"search\" value=\"$feed_search\">";
        echo "<button data-dojo-type=\"dijit.form.Button\" onclick=\"updateFeedList()\">";
        echo __('Search');
        echo '</button>';
        echo '</div>';

        echo '<div data-dojo-type="dijit.form.DropDownButton">';
        echo '<span>';
        echo __('Select');
        echo '</span>';
        echo '<div data-dojo-type="dijit.Menu" style="display: none;">';
        echo "<div onclick=\"dijit.byId('feedTree').model.setAllChecked(true)\" data-dojo-type=\"dijit.MenuItem\">";
        echo __('All');
        echo '</div>';
        echo "<div onclick=\"dijit.byId('feedTree').model.setAllChecked(false)\" data-dojo-type=\"dijit.MenuItem\">";
        echo __('None');
        echo '</div>';
        echo '</div>';
        echo '</div>';

        echo '<div data-dojo-type="dijit.form.DropDownButton">';
        echo '<span>';
        echo __('Feeds');
        echo '</span>';
        echo '<div data-dojo-type="dijit.Menu" style="display: none;">';
        echo '<div onclick="quickAddFeed()" data-dojo-type="dijit.MenuItem">';
        echo __('Subscribe to feed');
        echo '</div>';
        echo '<div onclick="editSelectedFeed()" data-dojo-type="dijit.MenuItem">';
        echo __('Edit selected feeds');
        echo '</div>';
        echo '<div onclick="resetFeedOrder()" data-dojo-type="dijit.MenuItem">';
        echo __('Reset sort order');
        echo '</div>';
        echo '<div onclick="batchSubscribe()" data-dojo-type="dijit.MenuItem">';
        echo __('Batch subscribe');
        echo '</div>';
        echo '<div data-dojo-type="dijit.MenuItem" onclick="removeSelectedFeeds()">'
            .__('Unsubscribe');
        echo '</div> ';
        echo '</div></div>';

        if (\SmallSmallRSS\DBPrefs::read('ENABLE_FEED_CATS')) {
            echo '<div data-dojo-type="dijit.form.DropDownButton">'.
                '<span>' . __('Categories');
            echo '</span>';
            echo '<div data-dojo-type="dijit.Menu" style="display: none;">';
            echo '<div onclick="createCategory()"
                data-dojo-type="dijit.MenuItem">';
            echo __('Add category');
            echo '</div>';
            echo '<div onclick="resetCatOrder()"
                data-dojo-type="dijit.MenuItem">';
            echo __('Reset sort order');
            echo '</div>';
            echo '<div onclick="removeSelectedCategories()"
                data-dojo-type="dijit.MenuItem">';
            echo __('Remove selected');
            echo '</div>';
            echo '</div></div>';

        }

        echo $error_button;
        echo $inactive_button;

        if (defined('_ENABLE_FEED_DEBUGGING')) {

            echo '<select id="feedActionChooser" onchange="feedActionChange()">
                <option value="facDefault" selected>';
            echo __('More actions...');
            echo '</option>';

            if (\SmallSmallRSS\Config::get('FORCE_ARTICLE_PURGE') == 0) {
                echo '<option value="facPurge">';
                echo __('Manual purge');
                echo '</option>';
            }

            echo '<option value="facClear">';
            echo __('Clear feed data');
            echo '</option>';
            echo '<option value="facRescore">';
            echo __('Rescore articles');
            echo '</option>';

            echo '</select>';

        }
        echo '</div>'; # toolbar
        echo '<div data-dojo-type="dijit.layout.ContentPane" data-dojo-props="region: \'center\'">';
        echo "<div id=\"feedlistLoading\">";
        echo "<img src=\"images/indicator_tiny.gif\" />";
        echo __('Loading, please wait...');
        echo '</div>';
        echo "<div data-dojo-type=\"fox.PrefFeedStore\" jsId=\"feedStore\"
            url=\"backend.php?op=pref-feeds&method=getfeedtree\">
        </div>";
        echo "<div data-dojo-type=\"lib.CheckBoxStoreModel\" jsId=\"feedModel\" store=\"feedStore\"
        query=\"{id:'root'}\" rootId=\"root\" rootLabel=\"Feeds\"
            childrenAttrs=\"items\" checkboxStrict=\"false\" checkboxAll=\"false\">
        </div>";
        echo "<div data-dojo-type=\"fox.PrefFeedTree\" id=\"feedTree\"
            dndController=\"dijit.tree.dndSource\"
            betweenThreshold=\"5\"
            model=\"feedModel\" openOnClick=\"false\">";
        echo "<script type=\"dojo/method\" event=\"onClick\" args=\"item\">
            var id = String(item.id);
            var bare_id = id.substr(id.indexOf(':')+1);

            if (id.match('FEED:')) {
                editFeed(bare_id);
            } elseif (id.match('CAT:')) {
                editCat(bare_id, item);
            }
        </script>";
        echo "<script type=\"dojo/method\" event=\"onLoad\" args=\"item\">
            Element.hide('feedlistLoading');
        </script>";
        echo "</div>";
        echo '</div>';
        echo '</div>';
        echo '</div>';
        echo '<div data-dojo-type="dijit.layout.AccordionPane" title="';
        echo __('OPML');
        echo '">';

        \SmallSmallRSS\Renderers\Messages::renderNotice(
            __('Using OPML you can export and import your feeds, filters, labels and Tiny Tiny RSS settings.')
            . __('Only main settings profile can be migrated using OPML.')
        );

        echo '<iframe id="upload_iframe"
            name="upload_iframe" onload="opmlImportComplete(this)"
            style="width: 400px; height: 100px; display: none;"></iframe>';

        echo "<form  name=\"opml_form\" style='display : block' target=\"upload_iframe\"
            enctype=\"multipart/form-data\" method=\"POST\"
            action=\"backend.php\">";
        echo "<input id=\"opml_file\" name=\"opml_file\" type=\"file\">&nbsp;";
        echo "<input type=\"hidden\" name=\"op\" value=\"dlg\">";
        echo "<input type=\"hidden\" name=\"method\" value=\"importOpml\">";
        echo "<button data-dojo-type=\"dijit.form.Button\" onclick=\"return opmlImport();\" type=\"submit\">";
        echo __('Import my OPML');
        echo '</button>';
        echo '<hr />';
        echo '<p>';
        echo __('Filename:');
        echo '<input type="text" id="filename" value="TinyTinyRSS.opml" />&nbsp;';
        echo __('Include settings');
        echo '<input type="checkbox" id="settings" checked="1"/>';
        echo '</p>';
        echo '<button data-dojo-type="dijit.form.Button"';
        echo ' onclick="gotoExportOpml(document.opml_form.filename.value, document.opml_form.settings.checked)" >';
        echo __('Export OPML');
        echo '</button>';
        echo '</p>';
        echo '</form>';
        echo '<hr/>';
        echo '<p>';
        echo __('Your OPML can be published publicly and can be subscribed to by anyone who knows the URL below.');
        echo __('Published OPML does not include your RSS settings, feeds that require authentication, or feeds hidden from Popular feeds.');
        echo '</p>';
        echo "<button data-dojo-type=\"dijit.form.Button\" onclick=\"return displayDlg('";
        echo __('Public OPML URL');
        echo "','pubOPMLUrl')\">";
        echo __('Display published OPML URL');
        echo '</button> ';

        \SmallSmallRSS\PluginHost::getInstance()->runHooks(
            \SmallSmallRSS\Hooks::RENDER_PREFS_TAB_SECTION,
            'prefFeedsOPML'
        );
        echo '</div>';
        if (strpos($_SERVER['HTTP_USER_AGENT'], 'Firefox') !== false) {
            echo '<div data-dojo-type="dijit.layout.AccordionPane" title="';
            echo __('Firefox integration');
            echo '">';
            \SmallSmallRSS\Renderers\Messages::renderNotice(
                __('This Tiny Tiny RSS site can be used as a Firefox Feed Reader by clicking the link below.')
            );
            echo '<p>';
            echo '<button onclick="window.navigator.registerContentHandler(\'' .
                '"application/vnd.mozilla.maybe.feed", "'
                . \SmallSmallRSS\Handlers\PublicHandler::getSubscribeUrl()
                . '", '
                . ' "Tiny Tiny RSS")">'
                . __('Click here to register this site as a feed reader.')
                . '</button>';
            echo '</p>';
            echo '</div>';
        }
        echo '<div data-dojo-type="dijit.layout.AccordionPane" title="';
        echo __('Published & shared articles / Generated feeds');
        echo '">';
        \SmallSmallRSS\Renderers\Messages::renderNotice(
            __('Published articles are exported as a public RSS feed and can be subscribed to by anyone who knows the URL.')
        );
        $rss_url = '-2::' . htmlspecialchars(
            get_self_url_prefix() .
            '/public.php?op=rss&id=-2&view-mode=all_articles'
        );
        echo '<p>';
        echo "<button data-dojo-type=\"dijit.form.Button\" onclick=\"return displayDlg('";
        echo __('View as RSS')."','generatedFeed', '$rss_url')\">".
            __('Display URL');
        echo '</button> ';
        echo '<button data-dojo-type="dijit.form.Button" onclick="return clearFeedAccessKeys()">';
        echo __('Clear all generated URLs');
        echo '</button> ';
        echo '</p>';
        \SmallSmallRSS\Renderers\Messages::renderWarning(
            __('You can disable all articles shared by unique URLs here.')
        );
        echo '<p>';
        echo '<button data-dojo-type="dijit.form.Button" onclick="return clearArticleAccessKeys()">';
        echo __('Unshare all articles');
        echo '</button> ';
        echo '</p>';
        \SmallSmallRSS\PluginHost::getInstance()->runHooks(
            \SmallSmallRSS\Hooks::RENDER_PREFS_TAB_SECTION,
            'prefFeedsPublishedGenerated'
        );
        echo '</div>'; #pane
        \SmallSmallRSS\PluginHost::getInstance()->runHooks(
            \SmallSmallRSS\Hooks::RENDER_PREFS_TAB,
            'prefFeeds'
        );
        echo '</div>'; #container
    }

    private function initFeedlistCat($cat_id)
    {
        $obj = array();
        $cat_id = (int) $cat_id;

        if ($cat_id > 0) {
            $cat_unread = \SmallSmallRSS\CountersCache::find($cat_id, $_SESSION['uid'], true);
        } elseif ($cat_id == 0 || $cat_id == -2) {
            $cat_unread = getCategoryUnread($cat_id, $_SESSION['uid']);
        } else {
            $cat_unread = 0;
        }

        $obj['id'] = 'CAT:' . $cat_id;
        $obj['items'] = array();
        $obj['name'] = \SmallSmallRSS\FeedCategories::getTitle($cat_id);
        $obj['type'] = 'category';
        $obj['unread'] = (int) $cat_unread;
        $obj['bare_id'] = $cat_id;

        return $obj;
    }

    private function initFeedlistFeed($feed_id, $title = false, $unread = false, $error = '', $updated = '')
    {
        $obj = array();
        $feed_id = (int) $feed_id;

        if (!$title) {
            $title = \SmallSmallRSS\Feeds::getTitle($feed_id);
        }

        if ($unread === false) {
            $unread = countUnreadFeedArticles($feed_id, false, true, $_SESSION['uid']);
        }

        $obj['id'] = 'FEED:' . $feed_id;
        $obj['name'] = $title;
        $obj['unread'] = (int) $unread;
        $obj['type'] = 'feed';
        $obj['error'] = $error;
        $obj['updated'] = $updated;
        $obj['icon'] = getFeedIcon($feed_id);
        $obj['bare_id'] = $feed_id;
        $obj['auxcounter'] = 0;

        return $obj;
    }

    public function inactiveFeeds()
    {

        if (\SmallSmallRSS\Config::get('DB_TYPE') == 'pgsql') {
            $interval_qpart = "NOW() - INTERVAL '3 months'";
        } else {
            $interval_qpart = 'DATE_SUB(NOW(), INTERVAL 3 MONTH)';
        }

        $result = \SmallSmallRSS\Database::query(
            "SELECT ttrss_feeds.title, ttrss_feeds.site_url,
                  ttrss_feeds.feed_url, ttrss_feeds.id, MAX(updated) AS last_article
            FROM ttrss_feeds, ttrss_entries, ttrss_user_entries WHERE
                (SELECT MAX(updated) FROM ttrss_entries, ttrss_user_entries WHERE
                    ttrss_entries.id = ref_id AND
                        ttrss_user_entries.feed_id = ttrss_feeds.id) < $interval_qpart
            AND ttrss_feeds.owner_uid = ".$_SESSION['uid'].' AND
                ttrss_user_entries.feed_id = ttrss_feeds.id AND
                ttrss_entries.id = ref_id
            GROUP BY ttrss_feeds.title, ttrss_feeds.id, ttrss_feeds.site_url, ttrss_feeds.feed_url
            ORDER BY last_article'
        );

        echo '<p' .__('These feeds have not been updated with new content for 3 months (oldest first):') . '</p>';

        echo '<div data-dojo-type="dijit.Toolbar">';
        echo '<div data-dojo-type="dijit.form.DropDownButton">'.
            '<span>' . __('Select');
        echo '</span>';
        echo '<div data-dojo-type="dijit.Menu" style="display: none;">';
        echo "<div onclick=\"selectTableRows('prefInactiveFeedList', 'all')\"
            data-dojo-type=\"dijit.MenuItem\">";
        echo __('All');
        echo '</div>';
        echo "<div onclick=\"selectTableRows('prefInactiveFeedList', 'none')\"
            data-dojo-type=\"dijit.MenuItem\">";
        echo __('None');
        echo '</div>';
        echo '</div></div>';
        echo '</div>'; #toolbar

        echo '<div class="inactiveFeedHolder">';

        echo '<table width="100%" cellspacing="0" id="prefInactiveFeedList">';

        $lnum = 1;

        while ($line = \SmallSmallRSS\Database::fetch_assoc($result)) {

            $feed_id = $line['id'];
            $this_row_id = "id=\"FUPDD-$feed_id\"";

            # class needed for selectTableRows()
            echo "<tr class=\"placeholder\" $this_row_id>";

            $edit_title = htmlspecialchars($line['title']);

            # id needed for selectTableRows()
            echo "<td width='5%' align='center'><input
                onclick='toggleSelectRow2(this);' data-dojo-type=\"dijit.form.CheckBox\"
                type=\"checkbox\" id=\"FUPDC-$feed_id\"></td>";
            echo '<td>';

            echo '<a class="visibleLink" href="#" '.
                'title="';
            echo __('Click to edit feed');
            echo '" '.
                'onclick="editFeed('.$line['id'].')">'.
                htmlspecialchars($line['title']);
            echo '</a>';

            echo "</td><td class=\"insensitive\" align='right'>";
            echo make_local_datetime($line['last_article'], false, $_SESSION['uid']);
            echo '</td>';
            echo '</tr>';

            ++$lnum;
        }

        echo '</table>';
        echo '</div>';

        echo '<div class="dlgButtons">';
        echo '<div style="float: left">';
        echo "<button data-dojo-type=\"dijit.form.Button\" onclick=\"dijit.byId('inactiveFeedsDlg').removeSelected()\">";
        echo __('Unsubscribe from selected feeds');
        echo '</button> ';
        echo '</div>';

        echo "<button data-dojo-type=\"dijit.form.Button\" onclick=\"dijit.byId('inactiveFeedsDlg').hide()\">".
            __('Close this window');
        echo '</button>';

        echo '</div>';

    }

    public function feedsWithErrors()
    {
        $result = \SmallSmallRSS\Database::query(
            "SELECT id,title,feed_url,last_error,site_url
             FROM ttrss_feeds WHERE last_error != '' AND owner_uid = " . $_SESSION['uid']
        );

        echo '<div data-dojo-type="dijit.Toolbar">';
        echo '<div data-dojo-type="dijit.form.DropDownButton">'.
            '<span>' . __('Select');
        echo '</span>';
        echo '<div data-dojo-type="dijit.Menu" style="display: none;">';
        echo "<div onclick=\"selectTableRows('prefErrorFeedList', 'all')\"
            data-dojo-type=\"dijit.MenuItem\">";
        echo __('All');
        echo '</div>';
        echo "<div onclick=\"selectTableRows('prefErrorFeedList', 'none')\"
            data-dojo-type=\"dijit.MenuItem\">";
        echo __('None');
        echo '</div>';
        echo '</div></div>';
        echo '</div>'; #toolbar

        echo '<div class="inactiveFeedHolder">';

        echo '<table width="100%" cellspacing="0" id="prefErrorFeedList">';

        $lnum = 1;

        while ($line = \SmallSmallRSS\Database::fetch_assoc($result)) {

            $feed_id = $line['id'];
            $this_row_id = "id=\"FERDD-$feed_id\"";

            # class needed for selectTableRows()
            echo "<tr class=\"placeholder\" $this_row_id>";

            $edit_title = htmlspecialchars($line['title']);

            # id needed for selectTableRows()
            echo "<td width='5%' align='center'><input
                onclick='toggleSelectRow2(this);' data-dojo-type=\"dijit.form.CheckBox\"
                type=\"checkbox\" id=\"FERDC-$feed_id\"></td>";
            echo '<td>';

            echo '<a class="visibleLink" href="#" '.
                'title="';
            echo __('Click to edit feed');
            echo '" '.
                'onclick="editFeed('.$line['id'].')">'.
                htmlspecialchars($line['title']);
            echo '</a>: ';

            echo '<span class="insensitive">';
            echo htmlspecialchars($line['last_error']);
            echo '</span>';

            echo '</td>';
            echo '</tr>';

            ++$lnum;
        }

        echo '</table>';
        echo '</div>';

        echo '<div class="dlgButtons">';
        echo '<div style="float: left">';
        echo "<button data-dojo-type=\"dijit.form.Button\" onclick=\"dijit.byId('errorFeedsDlg').removeSelected()\">"
            .__('Unsubscribe from selected feeds');
        echo '</button> ';
        echo '</div>';

        echo "<button data-dojo-type=\"dijit.form.Button\" onclick=\"dijit.byId('errorFeedsDlg').hide()\">".
            __('Close this window');
        echo '</button>';

        echo '</div>';
    }

    /**
     * Purge a feed contents, marked articles excepted.
     *
     * @param mixed $link The database connection.
     * @param integer $id The id of the feed to purge.
     * @return void
     */
    private function clearFeedArticles($id)
    {

        if ($id != 0) {
            $result = \SmallSmallRSS\Database::query(
                "DELETE FROM ttrss_user_entries
            WHERE feed_id = '$id' AND marked = false AND owner_uid = " . $_SESSION['uid']
            );
        } else {
            $result = \SmallSmallRSS\Database::query(
                'DELETE FROM ttrss_user_entries
            WHERE feed_id IS NULL AND marked = false AND owner_uid = ' . $_SESSION['uid']
            );
        }

        $result = \SmallSmallRSS\Database::query(
            'DELETE FROM ttrss_entries WHERE
            (SELECT COUNT(int_id) FROM ttrss_user_entries WHERE ref_id = id) = 0'
        );

        \SmallSmallRSS\CountersCache::update($id, $_SESSION['uid']);
    }

    private function removeFeedCategory($id, $owner_uid)
    {

        \SmallSmallRSS\Database::query(
            "DELETE FROM ttrss_feed_categories
            WHERE id = '$id' AND owner_uid = $owner_uid"
        );

        \SmallSmallRSS\CountersCache::remove($id, $owner_uid, true);
    }

    public static function removeFeed($id, $owner_uid)
    {
        if ($id > 0) {
            /* save starred articles in Archived feed */
            \SmallSmallRSS\Database::query('BEGIN');
            /* prepare feed if necessary */
            $result = \SmallSmallRSS\Database::query(
                "SELECT feed_url
                 FROM ttrss_feeds
                 WHERE
                     id = $id
                     AND owner_uid = $owner_uid"
            );
            $feed_url = \SmallSmallRSS\Database::escape_string(
                \SmallSmallRSS\Database::fetch_result($result, 0, 'feed_url')
            );
            $result = \SmallSmallRSS\Database::query(
                "SELECT id
                 FROM ttrss_archived_feeds
                 WHERE
                     feed_url = '$feed_url'
                     AND owner_uid = $owner_uid"
            );
            if (\SmallSmallRSS\Database::num_rows($result) == 0) {
                $result = \SmallSmallRSS\Database::query('SELECT MAX(id) AS id FROM ttrss_archived_feeds');
                $new_feed_id = (int) \SmallSmallRSS\Database::fetch_result($result, 0, 'id') + 1;
                \SmallSmallRSS\Database::query(
                    "INSERT INTO ttrss_archived_feeds
                     (id, owner_uid, title, feed_url, site_url)
                     SELECT $new_feed_id, owner_uid, title, feed_url, site_url
                     FROM ttrss_feeds
                     WHERE id = '$id'"
                );
                $archive_id = $new_feed_id;
            } else {
                $archive_id = \SmallSmallRSS\Database::fetch_result($result, 0, 'id');
            }
            \SmallSmallRSS\Database::query(
                "UPDATE ttrss_user_entries
                 SET feed_id = NULL,
                     orig_feed_id = '$archive_id'
                 WHERE
                     feed_id = '$id'
                     AND marked = true
                     AND owner_uid = $owner_uid"
            );

            /* Remove access key for the feed */
            \SmallSmallRSS\AccessKeys::delete($id, $owner_uid);
            /* remove the feed */
            \SmallSmallRSS\Database::query(
                "DELETE FROM ttrss_feeds
                 WHERE id = '$id' AND owner_uid = $owner_uid"
            );
            \SmallSmallRSS\Database::query('COMMIT');
            if (file_exists(\SmallSmallRSS\Config::get('ICONS_DIR') . "/$id.ico")) {
                unlink(\SmallSmallRSS\Config::get('ICONS_DIR') . "/$id.ico");
            }
            \SmallSmallRSS\CountersCache::remove($id, $owner_uid);
        } else {
            $label_id = \SmallSmallRSS\Labels::fromFeedId($id);
            \SmallSmallRSS\Labels::remove($label_id, $owner_uid);
        }
    }

    public function batchSubscribe()
    {
        echo '<input data-dojo-type="dijit.form.TextBox" style="display: none" name="op" value="pref-feeds">';
        echo '<input data-dojo-type="dijit.form.TextBox" style="display: none" name="method" value="batchaddfeeds">';

        echo '<table width="100%">';
        echo '<tr>';
        echo '<td>';
        echo __('Add one valid RSS feed per line (no feed detection is done)');
        echo '</td>';
        echo '<td align="right">';
        if (\SmallSmallRSS\DBPrefs::read('ENABLE_FEED_CATS')) {
            echo __('Place in category:') . ' ';
            print_feed_cat_select('cat', false, 'data-dojo-type="dijit.form.Select"');
        }
        echo '</td>';
        echo '</tr>';
        echo '<tr>';
        echo '<td colspan="2">';
        echo '<textarea style="font-size: 12px; width: 100%; height: 200px;"';
        echo ' placeHolder="';
        echo __('Feeds to subscribe, One per line');
        echo '"data-dojo-type="dijit.form.SimpleTextarea" required="1" name="feeds">';
        echo '</textarea>';
        echo '</td>';
        echo '</tr>';
        echo '<tr>';
        echo '<td colspan="2">';
        echo '<div id="feedDlg_loginContainer" style="display: none">';
        echo '<input data-dojo-type="dijit.form.TextBox" name="login" placeHolder="';
        echo __('Login');
        echo '" style="width: 10em;">';
        echo '<input type="password"  name="pass" placeHolder="';
        echo __('Password');
        echo '" data-dojo-type="dijit.form.TextBox" style="width: 10em;" />';
        echo '</div>';
        echo '</td></tr><tr><td colspan="2">';

        echo '<div style="clear : both">';
        echo '<input type="checkbox" name="need_auth"';
        echo ' data-dojo-type="dijit.form.CheckBox" id="feedDlg_loginCheck"';
        echo " onclick='checkboxToggleElement(this, \"feedDlg_loginContainer\")'>";
        echo '<label for="feedDlg_loginCheck">';
        echo __('Feeds require authentication.');
        echo '</div>';

        echo '</form>';

        echo '</td></tr></table>';

        echo '<div class="dlgButtons">';
        echo "<button data-dojo-type=\"dijit.form.Button\" onclick=\"return dijit.byId('batchSubDlg').execute()\">";
        echo __('Subscribe');
        echo '</button>';
        echo "<button data-dojo-type=\"dijit.form.Button\" onclick=\"return dijit.byId('batchSubDlg').hide()\">";
        echo __('Cancel');
        echo '</button>';
        echo '</div>';
    }

    public function batchAddFeeds()
    {
        $cat_id = $this->getSQLEscapedStringFromRequest('cat');
        $feeds = explode("\n", $_REQUEST['feeds']);
        $login = $this->getSQLEscapedStringFromRequest('login');
        $pass = trim($_REQUEST['pass']);

        foreach ($feeds as $feed) {
            $feed = \SmallSmallRSS\Database::escape_string(trim($feed));

            if (validate_feed_url($feed)) {

                \SmallSmallRSS\Database::query('BEGIN');

                if ($cat_id == '0' || !$cat_id) {
                    $cat_qpart = 'NULL';
                } else {
                    $cat_qpart = "'$cat_id'";
                }

                $result = \SmallSmallRSS\Database::query(
                    "SELECT id FROM ttrss_feeds
                    WHERE feed_url = '$feed' AND owner_uid = ".$_SESSION['uid']
                );

                if (\SmallSmallRSS\Crypt::is_enabled()) {
                    $pass = substr(\SmallSmallRSS\Crypt::en($pass), 0, 250);
                    $auth_pass_encrypted = 'true';
                } else {
                    $auth_pass_encrypted = 'false';
                }

                $pass = \SmallSmallRSS\Database::escape_string($pass);

                if (\SmallSmallRSS\Database::num_rows($result) == 0) {
                    $result = \SmallSmallRSS\Database::query(
                        "INSERT INTO ttrss_feeds
                            (owner_uid,feed_url,title,cat_id,auth_login,auth_pass,update_method,auth_pass_encrypted)
                        VALUES ('" . $_SESSION['uid'] . "', '$feed',
                            '[Unknown]', $cat_qpart, '$login', '$pass', 0, $auth_pass_encrypted)"
                    );
                }
                \SmallSmallRSS\Database::query('COMMIT');
            }
        }
    }

    public function regenOPMLKey()
    {
        $this->updateFeedAccessKey('OPML:Publish', false, $_SESSION['uid']);
        $new_link = Opml::opml_publish_url();
        echo json_encode(array('link' => $new_link));
    }

    public function regenFeedKey()
    {
        $feed_id = $this->getSQLEscapedStringFromRequest('id');
        $is_cat = $this->getSQLEscapedStringFromRequest('is_cat') == 'true';
        $new_key = $this->updateFeedAccessKey($feed_id, $is_cat, $_SESSION['uid']);
        echo json_encode(array('link' => $new_key));
    }


    private function updateFeedAccessKey($feed_id, $is_cat, $owner_uid)
    {
        $sql_is_cat = \SmallSmallRSS\Database::toSQLBool($is_cat);
        $result = \SmallSmallRSS\Database::query(
            "SELECT access_key FROM ttrss_access_keys
             WHERE
                 feed_id = '$feed_id'
                 AND is_cat = $sql_is_cat
                 AND owner_uid = " . $owner_uid
        );
        if (\SmallSmallRSS\Database::num_rows($result) == 1) {
            $key = \SmallSmallRSS\Database::escape_string(sha1(uniqid(rand(), true)));
            \SmallSmallRSS\Database::query(
                "UPDATE ttrss_access_keys
                 SET access_key = '$key'
                 WHERE
                     feed_id = '$feed_id'
                     AND is_cat = $sql_is_cat
                     AND owner_uid = " . $owner_uid
            );
            return $key;
        } else {
            return \SmallSmallRSS\AccessKeys::getForFeed($feed_id, $is_cat, $owner_uid);
        }
    }

    public function clearKeys()
    {
        \SmallSmallRSS\AccessKeys::clearForOwner($_SESSION['uid']);
    }

    private function countChildren($cat)
    {
        $c = 0;
        foreach ($cat['items'] as $child) {
            if (isset($child['type']) && $child['type'] == 'category') {
                $c += $this->countChildren($child);
            } else {
                $c += 1;
            }
        }
        return $c;
    }
}
