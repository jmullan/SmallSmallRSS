<?php
namespace SmallSmallRSS\Handlers;

class Pref_Filters extends ProtectedHandler
{

    public function ignoreCSRF($method)
    {
        $csrf_ignored = array('index', 'getfiltertree', 'edit', 'newfilter', 'newrule',
                              'newaction', 'savefilterorder');

        return array_search($method, $csrf_ignored) !== false;
    }

    public function filtersortreset()
    {
        \SmallSmallRSS\Database::query(
            'UPDATE ttrss_filters2
             SET order_id = 0
             WHERE owner_uid = ' . $_SESSION['uid']
        );
        return;
    }

    public function savefilterorder()
    {
        $data = json_decode($_POST['payload'], true);

        #file_put_contents("/tmp/saveorder.json", $_POST['payload']);
        #$data = json_decode(file_get_contents("/tmp/saveorder.json"), true);

        if (!is_array($data['items'])) {
            $data['items'] = json_decode($data['items'], true);
        }

        $index = 0;

        if (is_array($data) && is_array($data['items'])) {
            foreach ($data['items'][0]['items'] as $item) {
                $filter_id = (int) str_replace('FILTER:', '', $item['_reference']);

                if ($filter_id > 0) {

                    \SmallSmallRSS\Database::query(
                        "UPDATE ttrss_filters2 SET
                        order_id = $index WHERE id = '$filter_id' AND
                        owner_uid = " .$_SESSION['uid']
                    );

                    ++$index;
                }
            }
        }

        return;
    }


    public function testFilter()
    {
        $filter = array();

        $filter['enabled'] = true;
        $filter['match_any_rule'] = \SmallSmallRSS\Database::fromSQLBool(
            checkbox_to_sql_bool(\SmallSmallRSS\Database::escape_string($_REQUEST['match_any_rule']))
        );
        $filter['inverse'] = \SmallSmallRSS\Database::fromSQLBool(
            checkbox_to_sql_bool(\SmallSmallRSS\Database::escape_string($_REQUEST['inverse']))
        );

        $filter['rules'] = array();

        $result = \SmallSmallRSS\Database::query('SELECT id,name FROM ttrss_filter_types');

        $filter_types = array();
        while ($line = \SmallSmallRSS\Database::fetch_assoc($result)) {
            $filter_types[$line['id']] = $line['name'];
        }

        $rctr = 0;
        foreach ($_REQUEST['rule'] as $r) {
            $rule = json_decode($r, true);

            if ($rule && $rctr < 5) {
                $rule['type'] = $filter_types[$rule['filter_type']];
                unset($rule['filter_type']);

                if (strpos($rule['feed_id'], 'CAT:') === 0) {
                    $rule['cat_id'] = (int) substr($rule['feed_id'], 4);
                    unset($rule['feed_id']);
                }

                array_push($filter['rules'], $rule);

                ++$rctr;
            } else {
                break;
            }
        }

        $qfh_ret = queryFeedHeadlines(
            -4, 30, '', false, false, false,
            'date_entered DESC', 0, $_SESSION['uid'], $filter
        );

        $result = $qfh_ret[0];

        $articles = array();
        $found = 0;

        echo __('Articles matching this filter:');

        echo '<div class="filterTestHolder">';
        echo '<table width="100%" cellspacing="0" id="prefErrorFeedList">';

        while ($line = \SmallSmallRSS\Database::fetch_assoc($result)) {

            $entry_timestamp = strtotime($line['updated']);
            $entry_tags = get_article_tags($line['id'], $_SESSION['uid']);

            $content_preview = truncate_string(
                strip_tags($line['content_preview']), 100, '...'
            );

            if ($line['feed_title']) {
                $feed_title = $line['feed_title'];
            }

            echo '<tr>';

            echo "<td width='5%' align='center'><input
                data-dojo-type=\"dijit.form.CheckBox\" checked=\"1\"
                disabled=\"1\" type=\"checkbox\"></td>";
            echo '<td>';

            echo $line['title'];
            echo '&nbsp;(';
            echo '<b>' . $feed_title . '</b>';
            echo '):&nbsp;';
            echo '<span class="insensitive">' . $content_preview . '</span>';
            echo ' ' . mb_substr($line['date_entered'], 0, 16);

            echo '</td></tr>';

            $found++;
        }

        if ($found == 0) {
            echo "<tr><td align='center'>" .
                __('No recent articles matching this filter have been found.');

            echo "</td></tr><tr><td class='insensitive' align='center'>";

            echo __('Complex expressions might not give results while testing due to issues with database server regexp implementation.');

            echo '</td></tr>';

        }

        echo '</table></div>';

        echo "<div style='text-align : center'>";
        echo "<button data-dojo-type=\"dijit.form.Button\" onclick=\"dijit.byId('filterTestDlg').hide()\">".
            __('Close this window').'</button>';
        echo '</div>';

    }


    public function getfiltertree()
    {
        $root = array();
        $root['id'] = 'root';
        $root['name'] = __('Filters');
        $root['items'] = array();

        $filter_search = $_SESSION['prefs_filter_search'];

        $result = \SmallSmallRSS\Database::query(
            'SELECT *,
            (SELECT action_param FROM ttrss_filters2_actions
                WHERE filter_id = ttrss_filters2.id ORDER BY id LIMIT 1) AS action_param,
            (SELECT action_id FROM ttrss_filters2_actions
                WHERE filter_id = ttrss_filters2.id ORDER BY id LIMIT 1) AS action_id,
            (SELECT description FROM ttrss_filter_actions
                WHERE id = (SELECT action_id FROM ttrss_filters2_actions
                    WHERE filter_id = ttrss_filters2.id ORDER BY id LIMIT 1)) AS action_name,
            (SELECT reg_exp FROM ttrss_filters2_rules
                WHERE filter_id = ttrss_filters2.id ORDER BY id LIMIT 1) AS reg_exp
            FROM ttrss_filters2 WHERE
            owner_uid = '.$_SESSION['uid'].' ORDER BY order_id, title'
        );


        $action_id = -1;
        $folder = array();
        $folder['items'] = array();

        while ($line = \SmallSmallRSS\Database::fetch_assoc($result)) {
            $name = $this->getFilterName($line['id']);

            $match_ok = false;
            if ($filter_search) {
                $rules_result = \SmallSmallRSS\Database::query(
                    'SELECT reg_exp FROM ttrss_filters2_rules WHERE filter_id = '.$line['id']
                );

                while ($rule_line = \SmallSmallRSS\Database::fetch_assoc($rules_result)) {
                    if (mb_strpos($rule_line['reg_exp'], $filter_search) !== false) {
                        $match_ok = true;
                        break;
                    }
                }
            }

            if ($line['action_id'] == 7) {
                $escaped_caption = \SmallSmallRSS\Database::escape_string($line['action_param']);
                $label_result = \SmallSmallRSS\Database::query(
                    "SELECT fg_color, bg_color
                     FROM ttrss_labels2
                     WHERE
                        caption = '$escaped_caption'
                        AND owner_uid = " . $_SESSION['uid']
                );
                if (\SmallSmallRSS\Database::num_rows($label_result) > 0) {
                    $fg_color = \SmallSmallRSS\Database::fetch_result($label_result, 0, 'fg_color');
                    $bg_color = \SmallSmallRSS\Database::fetch_result($label_result, 0, 'bg_color');

                    $name[1] = "<span class=\"labelColorIndicator\" id=\"label-editor-indicator\" style='color: $fg_color; background-color: $bg_color; margin-right: 4px'>&alpha;</span>" . $name[1];
                }
            }

            $filter = array();
            $filter['id'] = 'FILTER:' . $line['id'];
            $filter['bare_id'] = $line['id'];
            $filter['name'] = $name[0];
            $filter['param'] = $name[1];
            $filter['checkbox'] = false;
            $filter['enabled'] = \SmallSmallRSS\Database::fromSQLBool($line['enabled']);

            if (!$filter_search || $match_ok) {
                array_push($folder['items'], $filter);
            }
        }

        /* if (count($folder['items']) > 0) {
           array_push($root['items'], $folder);
           } */

        $root['items'] = $folder['items'];

        $fl = array();
        $fl['identifier'] = 'id';
        $fl['label'] = 'name';
        $fl['items'] = array($root);

        echo json_encode($fl);
        return;
    }

    public function edit()
    {

        $filter_id = \SmallSmallRSS\Database::escape_string($_REQUEST['id']);

        $result = \SmallSmallRSS\Database::query(
            "SELECT * FROM ttrss_filters2 WHERE id = '$filter_id' AND owner_uid = " . $_SESSION['uid']
        );

        $enabled = \SmallSmallRSS\Database::fromSQLBool(\SmallSmallRSS\Database::fetch_result($result, 0, 'enabled'));
        $match_any_rule = \SmallSmallRSS\Database::fromSQLBool(\SmallSmallRSS\Database::fetch_result($result, 0, 'match_any_rule'));
        $inverse = \SmallSmallRSS\Database::fromSQLBool(\SmallSmallRSS\Database::fetch_result($result, 0, 'inverse'));
        $title = htmlspecialchars(\SmallSmallRSS\Database::fetch_result($result, 0, 'title'));

        echo "<form id=\"filter_edit_form\" onsubmit='return false'>";

        echo '<input data-dojo-type="dijit.form.TextBox" style="display: none" name="op" value="pref-filters">';
        echo "<input data-dojo-type=\"dijit.form.TextBox\" style=\"display: none\" name=\"id\" value=\"$filter_id\">";
        echo '<input data-dojo-type="dijit.form.TextBox" style="display: none" name="method" value="editSave">';
        echo '<input data-dojo-type="dijit.form.TextBox" style="display: none" name="csrf_token" value="'.$_SESSION['csrf_token'].'">';

        echo '<div class="dlgSec">'.__('Caption').'</div>';

        echo "<input required=\"true\" data-dojo-type=\"dijit.form.ValidationTextBox\" style=\"width: 20em;\" name=\"title\" value=\"$title\">";

        echo '</div>';

        echo '<div class="dlgSec">'.__('Match').'</div>';

        echo '<div data-dojo-type="dijit.Toolbar">';

        echo '<div data-dojo-type="dijit.form.DropDownButton">'.
            '<span>' . __('Select').'</span>';
        echo '<div data-dojo-type="dijit.Menu" style="display: none;">';
        echo "<div onclick=\"dijit.byId('filterEditDlg').selectRules(true)\"
            data-dojo-type=\"dijit.MenuItem\">".__('All').'</div>';
        echo "<div onclick=\"dijit.byId('filterEditDlg').selectRules(false)\"
            data-dojo-type=\"dijit.MenuItem\">".__('None').'</div>';
        echo '</div></div>';

        echo "<button data-dojo-type=\"dijit.form.Button\" onclick=\"return dijit.byId('filterEditDlg').addRule()\">".
            __('Add').'</button> ';

        echo "<button data-dojo-type=\"dijit.form.Button\" onclick=\"return dijit.byId('filterEditDlg').deleteRule()\">".
            __('Delete').'</button> ';

        echo '</div>';

        echo "<ul id='filterDlg_Matches'>";

        $rules_result = \SmallSmallRSS\Database::query(
            "SELECT * FROM ttrss_filters2_rules
            WHERE filter_id = '$filter_id' ORDER BY reg_exp, id"
        );

        while ($line = \SmallSmallRSS\Database::fetch_assoc($rules_result)) {
            if (\SmallSmallRSS\Database::fromSQLBool($line['cat_filter'])) {
                $line['feed_id'] = 'CAT:' . (int) $line['cat_id'];
            }

            unset($line['cat_filter']);
            unset($line['cat_id']);
            unset($line['filter_id']);
            unset($line['id']);
            if (!\SmallSmallRSS\Database::fromSQLBool($line['inverse'])) {  unset($line['inverse']);
            }

            $data = htmlspecialchars(json_encode($line));

            echo "<li><input data-dojo-type='dijit.form.CheckBox' type='checkbox' onclick='toggleSelectListRow2(this)'>".
                "<span onclick=\"dijit.byId('filterEditDlg').editRule(this)\">".$this->getRuleName($line).'</span>'.
                "<input type='hidden' name='rule[]' value=\"$data\"/></li>";
        }

        echo '</ul>';

        echo '</div>';

        echo '<div class="dlgSec">'.__('Apply actions').'</div>';

        echo '<div data-dojo-type="dijit.Toolbar">';

        echo '<div data-dojo-type="dijit.form.DropDownButton">'.
            '<span>' . __('Select').'</span>';
        echo '<div data-dojo-type="dijit.Menu" style="display: none;">';
        echo "<div onclick=\"dijit.byId('filterEditDlg').selectActions(true)\"
            data-dojo-type=\"dijit.MenuItem\">".__('All').'</div>';
        echo "<div onclick=\"dijit.byId('filterEditDlg').selectActions(false)\"
            data-dojo-type=\"dijit.MenuItem\">".__('None').'</div>';
        echo '</div></div>';

        echo "<button data-dojo-type=\"dijit.form.Button\" onclick=\"return dijit.byId('filterEditDlg').addAction()\">".
            __('Add').'</button> ';

        echo "<button data-dojo-type=\"dijit.form.Button\" onclick=\"return dijit.byId('filterEditDlg').deleteAction()\">".
            __('Delete').'</button> ';

        echo '</div>';

        echo "<ul id='filterDlg_Actions'>";

        $actions_result = \SmallSmallRSS\Database::query(
            "SELECT * FROM ttrss_filters2_actions
            WHERE filter_id = '$filter_id' ORDER BY id"
        );

        while ($line = \SmallSmallRSS\Database::fetch_assoc($actions_result)) {
            $line['action_param_label'] = $line['action_param'];

            unset($line['filter_id']);
            unset($line['id']);

            $data = htmlspecialchars(json_encode($line));

            echo "<li><input data-dojo-type='dijit.form.CheckBox' type='checkbox' onclick='toggleSelectListRow2(this)'>".
                "<span onclick=\"dijit.byId('filterEditDlg').editAction(this)\">".$this->getActionName($line).'</span>'.
                "<input type='hidden' name='action[]' value=\"$data\"/></li>";
        }

        echo '</ul>';

        echo '</div>';

        if ($enabled) {
            $checked = 'checked="1"';
        } else {
            $checked = '';
        }

        echo "<input data-dojo-type=\"dijit.form.CheckBox\" type=\"checkbox\" name=\"enabled\" id=\"enabled\" $checked>
                <label for=\"enabled\">".__('Enabled').'</label>';

        if ($match_any_rule) {
            $checked = 'checked="1"';
        } else {
            $checked = '';
        }

        echo "<br/><input data-dojo-type=\"dijit.form.CheckBox\" type=\"checkbox\" name=\"match_any_rule\" id=\"match_any_rule\" $checked>
                <label for=\"match_any_rule\">".__('Match any rule').'</label>';

        if ($inverse) {
            $checked = 'checked="1"';
        } else {
            $checked = '';
        }

        echo "<br/><input data-dojo-type=\"dijit.form.CheckBox\" type=\"checkbox\" name=\"inverse\" id=\"inverse\" $checked>
                <label for=\"inverse\">".__('Inverse matching').'</label>';

        echo '<p/>';

        echo '<div class="dlgButtons">';

        echo '<div style="float: left">';
        echo "<button data-dojo-type=\"dijit.form.Button\" onclick=\"return dijit.byId('filterEditDlg').removeFilter()\">".
            __('Remove').'</button>';
        echo '</div>';

        echo "<button data-dojo-type=\"dijit.form.Button\" onclick=\"return dijit.byId('filterEditDlg').test()\">".
            __('Test').'</button> ';

        echo "<button data-dojo-type=\"dijit.form.Button\" onclick=\"return dijit.byId('filterEditDlg').execute()\">".
            __('Save').'</button> ';

        echo "<button data-dojo-type=\"dijit.form.Button\" onclick=\"return dijit.byId('filterEditDlg').hide()\">".
            __('Cancel').'</button>';

        echo '</div>';
    }

    private function getRuleName($rule)
    {
        if (!$rule) {  $rule = json_decode($_REQUEST['rule'], true);
        }

        $feed_id = $rule['feed_id'];

        if (strpos($feed_id, 'CAT:') === 0) {
            $feed_id = (int) substr($feed_id, 4);
            $feed = \SmallSmallRSS\FeedCategories::getTitle($feed_id);
        } else {
            $feed_id = (int) $feed_id;
            if ($rule['feed_id']) {
                $feed = \SmallSmallRSS\Feeds::getTitle((int) $rule['feed_id']);
            } else {
                $feed = __('All feeds');
            }
        }

        $result = \SmallSmallRSS\Database::query(
            'SELECT description FROM ttrss_filter_types
            WHERE id = '. (int) $rule['filter_type']
        );
        $filter_type = \SmallSmallRSS\Database::fetch_result($result, 0, 'description');

        return T_sprintf(
            '%s on %s in %s %s', strip_tags($rule['reg_exp']),
            $filter_type, $feed, isset($rule['inverse']) ? __('(inverse)') : ''
        );
    }

    public function printRuleName()
    {
        echo $this->getRuleName(json_decode($_REQUEST['rule'], true));
    }

    private function getActionName($action)
    {
        $result = \SmallSmallRSS\Database::query(
            'SELECT description FROM
            ttrss_filter_actions WHERE id = ' . (int) $action['action_id']
        );

        $title = __(\SmallSmallRSS\Database::fetch_result($result, 0, 'description'));

        if ($action['action_id'] == 4 || $action['action_id'] == 6 ||
            $action['action_id'] == 7) {
            $title .= ': ' . $action['action_param'];
        }

        return $title;
    }

    public function printActionName()
    {
        echo $this->getActionName(json_decode($_REQUEST['action'], true));
    }

    public function editSave()
    {
        if ($_REQUEST['savemode'] && $_REQUEST['savemode'] == 'test') {
            return $this->testFilter();
        }

        #        print_r($_REQUEST);

        $filter_id = \SmallSmallRSS\Database::escape_string($_REQUEST['id']);
        $enabled = checkbox_to_sql_bool(\SmallSmallRSS\Database::escape_string($_REQUEST['enabled']));
        $match_any_rule = checkbox_to_sql_bool(\SmallSmallRSS\Database::escape_string($_REQUEST['match_any_rule']));
        $inverse = checkbox_to_sql_bool(\SmallSmallRSS\Database::escape_string($_REQUEST['inverse']));
        $title = \SmallSmallRSS\Database::escape_string($_REQUEST['title']);

        $result = \SmallSmallRSS\Database::query(
            "UPDATE ttrss_filters2 SET enabled = $enabled,
            match_any_rule = $match_any_rule,
            inverse = $inverse,
            title = '$title'
            WHERE id = '$filter_id'
            AND owner_uid = ". $_SESSION['uid']
        );

        $this->saveRulesAndActions($filter_id);

    }

    public function remove()
    {

        $ids = explode(',', \SmallSmallRSS\Database::escape_string($_REQUEST['ids']));

        foreach ($ids as $id) {
            \SmallSmallRSS\Database::query("DELETE FROM ttrss_filters2 WHERE id = '$id' AND owner_uid = ". $_SESSION['uid']);
        }
    }

    private function saveRulesAndActions($filter_id)
    {

        \SmallSmallRSS\Database::query("DELETE FROM ttrss_filters2_rules WHERE filter_id = '$filter_id'");
        \SmallSmallRSS\Database::query("DELETE FROM ttrss_filters2_actions WHERE filter_id = '$filter_id'");

        if ($filter_id) {
            /* create rules */

            $rules = array();
            $actions = array();

            foreach ($_REQUEST['rule'] as $rule) {
                $rule = json_decode($rule, true);
                unset($rule['id']);

                if (array_search($rule, $rules) === false) {
                    array_push($rules, $rule);
                }
            }

            foreach ($_REQUEST['action'] as $action) {
                $action = json_decode($action, true);
                unset($action['id']);

                if (array_search($action, $actions) === false) {
                    array_push($actions, $action);
                }
            }

            foreach ($rules as $rule) {
                if ($rule) {

                    $reg_exp = strip_tags(\SmallSmallRSS\Database::escape_string(trim($rule['reg_exp'])));
                    $inverse = isset($rule['inverse']) ? 'true' : 'false';

                    $filter_type = (int) \SmallSmallRSS\Database::escape_string(trim($rule['filter_type']));
                    $feed_id = \SmallSmallRSS\Database::escape_string(trim($rule['feed_id']));

                    if (strpos($feed_id, 'CAT:') === 0) {

                        $cat_filter = \SmallSmallRSS\Database::toSQLBool(true);
                        $cat_id = (int) substr($feed_id, 4);
                        $feed_id = 'NULL';

                        if (!$cat_id) {  $cat_id = 'NULL'; // Uncategorized
                        }
                    } else {
                        $cat_filter = \SmallSmallRSS\Database::toSQLBool(false);
                        $feed_id = (int) $feed_id;
                        $cat_id = 'NULL';

                        if (!$feed_id) {  $feed_id = 'NULL'; // Uncategorized
                        }
                    }

                    $query = "INSERT INTO ttrss_filters2_rules
                        (filter_id, reg_exp,filter_type,feed_id,cat_id,cat_filter,inverse) VALUES
                        ('$filter_id', '$reg_exp', '$filter_type', $feed_id, $cat_id, $cat_filter, $inverse)";

                    \SmallSmallRSS\Database::query($query);
                }
            }

            foreach ($actions as $action) {
                if ($action) {

                    $action_id = (int) \SmallSmallRSS\Database::escape_string($action['action_id']);
                    $action_param = \SmallSmallRSS\Database::escape_string($action['action_param']);
                    $action_param_label = \SmallSmallRSS\Database::escape_string($action['action_param_label']);

                    if ($action_id == 7) {
                        $action_param = $action_param_label;
                    }

                    if ($action_id == 6) {
                        $action_param = (int) str_replace('+', '', $action_param);
                    }

                    $query = "INSERT INTO ttrss_filters2_actions
                        (filter_id, action_id, action_param) VALUES
                        ('$filter_id', '$action_id', '$action_param')";

                    \SmallSmallRSS\Database::query($query);
                }
            }
        }


    }

    public function add()
    {
        if ($_REQUEST['savemode'] && $_REQUEST['savemode'] == 'test') {
            return $this->testFilter();
        }

        #        print_r($_REQUEST);

        $enabled = checkbox_to_sql_bool($_REQUEST['enabled']);
        $match_any_rule = checkbox_to_sql_bool($_REQUEST['match_any_rule']);
        $title = \SmallSmallRSS\Database::escape_string($_REQUEST['title']);

        \SmallSmallRSS\Database::query('BEGIN');

        /* create base filter */

        $result = \SmallSmallRSS\Database::query(
            'INSERT INTO ttrss_filters2
            (owner_uid, match_any_rule, enabled, title) VALUES
            ('.$_SESSION['uid'].",$match_any_rule,$enabled, '$title')"
        );

        $result = \SmallSmallRSS\Database::query(
            'SELECT MAX(id) AS id FROM ttrss_filters2
            WHERE owner_uid = '.$_SESSION['uid']
        );

        $filter_id = \SmallSmallRSS\Database::fetch_result($result, 0, 'id');

        $this->saveRulesAndActions($filter_id);

        \SmallSmallRSS\Database::query('COMMIT');
    }

    public function index()
    {

        $sort = \SmallSmallRSS\Database::escape_string($_REQUEST['sort']);

        if (!$sort || $sort == 'undefined') {
            $sort = 'reg_exp';
        }

        $filter_search = \SmallSmallRSS\Database::escape_string($_REQUEST['search']);

        if (array_key_exists('search', $_REQUEST)) {
            $_SESSION['prefs_filter_search'] = $filter_search;
        } else {
            $filter_search = $_SESSION['prefs_filter_search'];
        }

        echo '<div id="pref-filter-wrap" data-dojo-type="dijit.layout.BorderContainer" gutters="false">';
        echo '<div id="pref-filter-header" data-dojo-type="dijit.layout.ContentPane" region="top">';
        echo '<div id="pref-filter-toolbar" data-dojo-type="dijit.Toolbar">';

        $filter_search = \SmallSmallRSS\Database::escape_string($_REQUEST['search']);

        if (array_key_exists('search', $_REQUEST)) {
            $_SESSION['prefs_filter_search'] = $filter_search;
        } else {
            $filter_search = $_SESSION['prefs_filter_search'];
        }

        echo "<div style='float: right; padding-right : 4px;'>
            <input data-dojo-type=\"dijit.form.TextBox\" id=\"filter_search\" size=\"20\" type=\"search\"
                value=\"$filter_search\">
            <button data-dojo-type=\"dijit.form.Button\" onclick=\"updateFilterList()\">".
            __('Search').'</button>
            </div>';

        echo '<div data-dojo-type="dijit.form.DropDownButton">'.
            '<span>' . __('Select').'</span>';
        echo '<div data-dojo-type="dijit.Menu" style="display: none;">';
        echo "<div onclick=\"dijit.byId('filterTree').model.setAllChecked(true)\"
            data-dojo-type=\"dijit.MenuItem\">".__('All').'</div>';
        echo "<div onclick=\"dijit.byId('filterTree').model.setAllChecked(false)\"
            data-dojo-type=\"dijit.MenuItem\">".__('None').'</div>';
        echo '</div></div>';

        echo '<button data-dojo-type="dijit.form.Button" onclick="return quickAddFilter()">'.
            __('Create filter').'</button> ';

        echo '<button data-dojo-type="dijit.form.Button" onclick="return joinSelectedFilters()">'.
            __('Combine').'</button> ';

        echo '<button data-dojo-type="dijit.form.Button" onclick="return editSelectedFilter()">'.
            __('Edit').'</button> ';

        echo '<button data-dojo-type="dijit.form.Button" onclick="return resetFilterOrder()">'.
            __('Reset sort order').'</button> ';


        echo '<button data-dojo-type="dijit.form.Button" onclick="return removeSelectedFilters()">'.
            __('Remove').'</button> ';

        if (defined('_ENABLE_FEED_DEBUGGING')) {
            echo '<button data-dojo-type="dijit.form.Button" onclick="rescore_all_feeds()">'.
                __('Rescore articles').'</button> ';
        }

        echo '</div>'; # toolbar
        echo '</div>'; # toolbar-frame
        echo '<div id="pref-filter-content" data-dojo-type="dijit.layout.ContentPane" region="center">';

        echo "<div id=\"filterlistLoading\">
        <img src='images/indicator_tiny.gif'>".
            __('Loading, please wait...').'</div>';

        echo "<div data-dojo-type=\"fox.PrefFilterStore\" jsId=\"filterStore\"
            url=\"backend.php?op=pref-filters&method=getfiltertree\">
        </div>
        <div data-dojo-type=\"lib.CheckBoxStoreModel\" jsId=\"filterModel\" store=\"filterStore\"
            query=\"{id:'root'}\" rootId=\"root\" rootLabel=\"Filters\"
            childrenAttrs=\"items\" checkboxStrict=\"false\" checkboxAll=\"false\">
        </div>
        <div data-dojo-type=\"fox.PrefFilterTree\" id=\"filterTree\"
            dndController=\"dijit.tree.dndSource\"
            betweenThreshold=\"5\"
            model=\"filterModel\" openOnClick=\"true\">
        <script type=\"dojo/method\" event=\"onLoad\" args=\"item\">
            Element.hide(\"filterlistLoading\");
        </script>
        <script type=\"dojo/method\" event=\"onClick\" args=\"item\">
            var id = String(item.id);
            var bare_id = id.substr(id.indexOf(':')+1);

            if (id.match('FILTER:')) {
                editFilter(bare_id);
            }
        </script>

        </div>";

        echo '</div>'; #pane

        \SmallSmallRSS\PluginHost::getInstance()->runHooks(
            \SmallSmallRSS\Hooks::RENDER_PREFS_TAB,
            'prefFilters'
        );

        echo '</div>'; #container

    }

    public function newfilter()
    {

        echo "<form name='filter_new_form' id='filter_new_form'>";

        echo '<input data-dojo-type="dijit.form.TextBox" style="display: none" name="op" value="pref-filters">';
        echo '<input data-dojo-type="dijit.form.TextBox" style="display: none" name="method" value="add">';
        echo '<input data-dojo-type="dijit.form.TextBox" style="display: none" name="csrf_token" value="'.$_SESSION['csrf_token'].'">';

        echo '<div class="dlgSec">'.__('Caption').'</div>';

        echo '<input required="true" data-dojo-type="dijit.form.ValidationTextBox" style="width: 20em;" name="title" value="">';

        echo '<div class="dlgSec">'.__('Match').'</div>';

        echo '<div data-dojo-type="dijit.Toolbar">';

        echo '<div data-dojo-type="dijit.form.DropDownButton">'.
            '<span>' . __('Select').'</span>';
        echo '<div data-dojo-type="dijit.Menu" style="display: none;">';
        echo "<div onclick=\"dijit.byId('filterEditDlg').selectRules(true)\"
            data-dojo-type=\"dijit.MenuItem\">".__('All').'</div>';
        echo "<div onclick=\"dijit.byId('filterEditDlg').selectRules(false)\"
            data-dojo-type=\"dijit.MenuItem\">".__('None').'</div>';
        echo '</div></div>';

        echo "<button data-dojo-type=\"dijit.form.Button\" onclick=\"return dijit.byId('filterEditDlg').addRule()\">".
            __('Add').'</button> ';

        echo "<button data-dojo-type=\"dijit.form.Button\" onclick=\"return dijit.byId('filterEditDlg').deleteRule()\">".
            __('Delete').'</button> ';

        echo '</div>';

        echo "<ul id='filterDlg_Matches'>";
        #        echo "<li>No rules</li>";
        echo '</ul>';

        echo '</div>';

        echo '<div class="dlgSec">'.__('Apply actions').'</div>';

        echo '<div data-dojo-type="dijit.Toolbar">';

        echo '<div data-dojo-type="dijit.form.DropDownButton">'.
            '<span>' . __('Select').'</span>';
        echo '<div data-dojo-type="dijit.Menu" style="display: none;">';
        echo "<div onclick=\"dijit.byId('filterEditDlg').selectActions(true)\"
            data-dojo-type=\"dijit.MenuItem\">".__('All').'</div>';
        echo "<div onclick=\"dijit.byId('filterEditDlg').selectActions(false)\"
            data-dojo-type=\"dijit.MenuItem\">".__('None').'</div>';
        echo '</div></div>';

        echo "<button data-dojo-type=\"dijit.form.Button\" onclick=\"return dijit.byId('filterEditDlg').addAction()\">".
            __('Add').'</button> ';

        echo "<button data-dojo-type=\"dijit.form.Button\" onclick=\"return dijit.byId('filterEditDlg').deleteAction()\">".
            __('Delete').'</button> ';

        echo '</div>';

        echo "<ul id='filterDlg_Actions'>";
        #        echo "<li>No actions</li>";
        echo '</ul>';

        /*        echo "<div class=\"dlgSec\">".__("Options")."</div>";
                  echo "<div class=\"dlgSecCont\">"; */

        echo '<input data-dojo-type="dijit.form.CheckBox" type="checkbox" name="enabled" id="enabled" checked="1">
                <label for="enabled">'.__('Enabled').'</label>';

        echo '<br/><input data-dojo-type="dijit.form.CheckBox" type="checkbox" name="match_any_rule" id="match_any_rule">
                <label for="match_any_rule">'.__('Match any rule').'</label>';

        echo '<br/><input data-dojo-type="dijit.form.CheckBox" type="checkbox" name="inverse" id="inverse">
                <label for="inverse">'.__('Inverse matching').'</label>';

        //        echo "</div>";

        echo '<div class="dlgButtons">';

        echo "<button data-dojo-type=\"dijit.form.Button\" onclick=\"return dijit.byId('filterEditDlg').test()\">".
            __('Test').'</button> ';

        echo "<button data-dojo-type=\"dijit.form.Button\" onclick=\"return dijit.byId('filterEditDlg').execute()\">".
            __('Create').'</button> ';

        echo "<button data-dojo-type=\"dijit.form.Button\" onclick=\"return dijit.byId('filterEditDlg').hide()\">".
            __('Cancel').'</button>';

        echo '</div>';

    }

    public function newrule()
    {
        $rule = json_decode($_REQUEST['rule'], true);

        if ($rule) {
            $reg_exp = htmlspecialchars($rule['reg_exp']);
            $filter_type = $rule['filter_type'];
            $feed_id = $rule['feed_id'];
            $inverse_checked = isset($rule['inverse']) ? 'checked' : '';
        } else {
            $reg_exp = '';
            $filter_type = 1;
            $feed_id = 0;
            $inverse_checked = '';
        }

        if (strpos($feed_id, 'CAT:') === 0) {
            $feed_id = substr($feed_id, 4);
            $cat_filter = true;
        } else {
            $cat_filter = false;
        }


        echo "<form name='filter_new_rule_form' id='filter_new_rule_form'>";

        $result = \SmallSmallRSS\Database::query(
            'SELECT id,description
            FROM ttrss_filter_types WHERE id != 5 ORDER BY description'
        );

        $filter_types = array();

        while ($line = \SmallSmallRSS\Database::fetch_assoc($result)) {
            $filter_types[$line['id']] = __($line['description']);
        }

        echo '<div class="dlgSec">'.__('Match').'</div>';

        echo '<div class="dlgSecCont">';

        echo "<input data-dojo-type=\"dijit.form.ValidationTextBox\"
             required=\"true\" id=\"filterDlg_regExp\"
             style=\"font-size: 16px; width: 20em;\"
             name=\"reg_exp\" value=\"$reg_exp\"/>";

        echo '<hr/>';
        echo "<input id=\"filterDlg_inverse\" data-dojo-type=\"dijit.form.CheckBox\"
             name=\"inverse\" $inverse_checked/>";
        echo '<label for="filterDlg_inverse">'.__('Inverse regular expression matching').'</label>';

        echo '<hr/>' .  __('on field') . ' ';
        $form_elements_renderer = new \SmallSmallRSS\Renderers\FormElements();
        $form_elements_renderer->renderSelect(
            'filter_type', $filter_type, $filter_types,
            'data-dojo-type="dijit.form.Select"'
        );

        echo '<hr/>';

        echo __('in') . ' ';

        echo "<span id='filterDlg_feeds'>";
        print_feed_select(
            'feed_id',
            $cat_filter ? "CAT:$feed_id" : $feed_id,
            'data-dojo-type="dijit.form.FilteringSelect"'
        );
        echo '</span>';

        echo '</div>';

        echo '<div class="dlgButtons">';

        echo "<button data-dojo-type=\"dijit.form.Button\" onclick=\"return dijit.byId('filterNewRuleDlg').execute()\">".
            ($rule ? __('Save rule') : __('Add rule')).'</button> ';

        echo "<button data-dojo-type=\"dijit.form.Button\" onclick=\"return dijit.byId('filterNewRuleDlg').hide()\">".
            __('Cancel').'</button>';

        echo '</div>';

        echo '</form>';
    }

    public function newaction()
    {
        $action = json_decode($_REQUEST['action'], true);

        if ($action) {
            $action_param = \SmallSmallRSS\Database::escape_string($action['action_param']);
            $action_id = (int) $action['action_id'];
        } else {
            $action_param = '';
            $action_id = 0;
        }

        echo "<form name='filter_new_action_form' id='filter_new_action_form'>";

        echo '<div class="dlgSec">'.__('Perform Action').'</div>';

        echo '<div class="dlgSecCont">';

        echo '<select name="action_id" data-dojo-type="dijit.form.Select"
            onchange="filterDlgCheckAction(this)">';

        $result = \SmallSmallRSS\Database::query(
            'SELECT id,description FROM ttrss_filter_actions
            ORDER BY name'
        );

        while ($line = \SmallSmallRSS\Database::fetch_assoc($result)) {
            $is_selected = ($line['id'] == $action_id) ? "selected='1'" : '';
            printf("<option $is_selected value='%d'>%s</option>", $line['id'], __($line['description']));
        }

        echo '</select>';

        $param_box_hidden = ($action_id == 7 || $action_id == 4 || $action_id == 6) ?
            '' : 'display: none';

        $param_hidden = ($action_id == 4 || $action_id == 6) ?
            '' : 'display: none';

        $label_param_hidden = ($action_id == 7) ?    '' : 'display: none';

        echo "<span id=\"filterDlg_paramBox\" style=\"$param_box_hidden\">";
        echo ' ' . __('with parameters:') . ' ';
        echo "<input data-dojo-type=\"dijit.form.TextBox\"
            id=\"filterDlg_actionParam\" style=\"$param_hidden\"
            name=\"action_param\" value=\"$action_param\">";
        print_label_select(
            'action_param_label', $action_param,
            "id=\"filterDlg_actionParamLabel\" style=\"$label_param_hidden\"
            data-dojo-type=\"dijit.form.Select\""
        );
        echo '</span>';
        echo '&nbsp;'; // tiny layout hack
        echo '</div>';
        echo '<div class="dlgButtons">';
        echo "<button data-dojo-type=\"dijit.form.Button\" onclick=\"return dijit.byId('filterNewActionDlg').execute()\">".
            ($action ? __('Save action') : __('Add action')).'</button> ';
        echo "<button data-dojo-type=\"dijit.form.Button\" onclick=\"return dijit.byId('filterNewActionDlg').hide()\">".
            __('Cancel').'</button>';
        echo '</div>';
        echo '</form>';
    }

    private function getFilterName($id)
    {

        $result = \SmallSmallRSS\Database::query(
            "SELECT title,COUNT(DISTINCT r.id) AS num_rules,COUNT(DISTINCT a.id) AS num_actions
                FROM ttrss_filters2 AS f LEFT JOIN ttrss_filters2_rules AS r
                    ON (r.filter_id = f.id)
                        LEFT JOIN ttrss_filters2_actions AS a
                            ON (a.filter_id = f.id) WHERE f.id = '$id' GROUP BY f.title"
        );

        $title = \SmallSmallRSS\Database::fetch_result($result, 0, 'title');
        $num_rules = \SmallSmallRSS\Database::fetch_result($result, 0, 'num_rules');
        $num_actions = \SmallSmallRSS\Database::fetch_result($result, 0, 'num_actions');

        if (!$title) {
            $title = __('[No caption]');
        }

        $title = sprintf(_ngettext('%s (%d rule)', '%s (%d rules)', $num_rules), $title, $num_rules);

        $result = \SmallSmallRSS\Database::query(
            "SELECT * FROM ttrss_filters2_actions WHERE filter_id = '$id' ORDER BY id LIMIT 1"
        );

        $actions = '';

        if (\SmallSmallRSS\Database::num_rows($result) > 0) {
            $line = \SmallSmallRSS\Database::fetch_assoc($result);
            $actions = $this->getActionName($line);

            $num_actions -= 1;
        }

        if ($num_actions > 0) {
            $actions = sprintf(_ngettext('%s (+%d action)', '%s (+%d actions)', $num_actions), $actions, $num_actions);
        }

        return array($title, $actions);
    }

    public function join()
    {
        $ids = explode(',', \SmallSmallRSS\Database::escape_string($_REQUEST['ids']));

        if (count($ids) > 1) {
            $base_id = array_shift($ids);
            $ids_str = join(',', $ids);

            \SmallSmallRSS\Database::query('BEGIN');
            \SmallSmallRSS\Database::query(
                "UPDATE ttrss_filters2_rules
                SET filter_id = '$base_id' WHERE filter_id IN ($ids_str)"
            );
            \SmallSmallRSS\Database::query(
                "UPDATE ttrss_filters2_actions
                SET filter_id = '$base_id' WHERE filter_id IN ($ids_str)"
            );

            \SmallSmallRSS\Database::query("DELETE FROM ttrss_filters2 WHERE id IN ($ids_str)");
            \SmallSmallRSS\Database::query("UPDATE ttrss_filters2 SET match_any_rule = true WHERE id = '$base_id'");

            \SmallSmallRSS\Database::query('COMMIT');

            $this->optimizeFilter($base_id);

        }
    }

    private function optimizeFilter($id)
    {
        \SmallSmallRSS\Database::query('BEGIN');
        $result = \SmallSmallRSS\Database::query(
            "SELECT * FROM ttrss_filters2_actions
            WHERE filter_id = '$id'"
        );

        $tmp = array();
        $dupe_ids = array();

        while ($line = \SmallSmallRSS\Database::fetch_assoc($result)) {
            $id = $line['id'];
            unset($line['id']);

            if (array_search($line, $tmp) === false) {
                array_push($tmp, $line);
            } else {
                array_push($dupe_ids, $id);
            }
        }

        if (count($dupe_ids) > 0) {
            $ids_str = join(',', $dupe_ids);
            \SmallSmallRSS\Database::query(
                "DELETE FROM ttrss_filters2_actions
                WHERE id IN ($ids_str)"
            );
        }

        $result = \SmallSmallRSS\Database::query(
            "SELECT * FROM ttrss_filters2_rules
            WHERE filter_id = '$id'"
        );

        $tmp = array();
        $dupe_ids = array();

        while ($line = \SmallSmallRSS\Database::fetch_assoc($result)) {
            $id = $line['id'];
            unset($line['id']);

            if (array_search($line, $tmp) === false) {
                array_push($tmp, $line);
            } else {
                array_push($dupe_ids, $id);
            }
        }

        if (count($dupe_ids) > 0) {
            $ids_str = join(',', $dupe_ids);
            \SmallSmallRSS\Database::query(
                "DELETE FROM ttrss_filters2_rules
                WHERE id IN ($ids_str)"
            );
        }

        \SmallSmallRSS\Database::query('COMMIT');
    }
}
