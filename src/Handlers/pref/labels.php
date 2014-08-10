<?php
namespace SmallSmallRSS\Handlers;

class Pref_Labels extends ProtectedHandler
{

    public function ignoreCSRF($method)
    {
        $csrf_ignored = array('index', 'getlabeltree', 'edit');

        return array_search($method, $csrf_ignored) !== false;
    }

    public function edit()
    {
        $label_id = \SmallSmallRSS\Database::escape_string($_REQUEST['id']);

        $result = \SmallSmallRSS\Database::query(
            "SELECT * FROM ttrss_labels2 WHERE
            id = '$label_id' AND owner_uid = " . $_SESSION['uid']
        );

        $line = \SmallSmallRSS\Database::fetch_assoc($result);

        echo "<input data-dojo-type=\"dijit.form.TextBox\" style=\"display: none\" name=\"id\" value=\"$label_id\">";
        echo '<input data-dojo-type="dijit.form.TextBox" style="display: none" name="op" value="pref-labels">';
        echo '<input data-dojo-type="dijit.form.TextBox" style="display: none" name="method" value="save">';
        echo '<div class="dlgSec">'.__('Caption').'</div>';
        echo '<div class="dlgSecCont">';
        $fg_color = $line['fg_color'];
        $bg_color = $line['bg_color'];
        echo "<span class=\"labelColorIndicator\" id=\"label-editor-indicator\"";
        echo " style='color: $fg_color; background-color: $bg_color; margin-bottom: 4px; margin-right: 4px'>";
        echo "&alpha;";
        echo "</span>";
        echo '<input style="font-size: 16px" name="caption"
            value="'.htmlspecialchars($line['caption']).'"
            required="true"
            data-dojo-type="dijit.form.ValidationTextBox">';

        echo '</div>';
        echo '<div class="dlgSec">' . __('Colors') . '</div>';
        echo '<div class="dlgSecCont">';
        echo '<table cellspacing="0">';
        echo '<tr>';
        echo '<td>';
        echo __('Foreground:');
        echo '</td>';
        echo '<td>'.__('Background:');
        echo '</td></tr>';
        echo "<tr>";
        echo '<td style="padding-right: 10px">';

        echo "<input data-dojo-type=\"dijit.form.TextBox\"";
        echo " style=\"display: none\" id=\"labelEdit_fgColor\"";
        echo " name=\"fg_color\" value=\"$fg_color\">";
        echo "<input data-dojo-type=\"dijit.form.TextBox\"";
        echo " style=\"display: none\" id=\"labelEdit_bgColor\"";
        echo " name=\"bg_color\" value=\"$bg_color\">";
        echo "<div data-dojo-type=\"dijit.ColorPalette\">";
        echo "<script type=\"dojo/method\" event=\"onChange\" args=\"fg_color\">";
        echo " dijit.byId(\"labelEdit_fgColor\").attr('value', fg_color);";
        echo " $('abel-editor-indicator').setStyle({color: fg_color});";
        echo '</script>';
        echo "</div>";
        echo '</div>';
        echo '</td>';
        echo '<td>';
        echo "<div data-dojo-type=\"dijit.ColorPalette\">";
        echo "<script type=\"dojo/method\" event=\"onChange\" args=\"bg_color\">";
        echo " dijit.byId(\"labelEdit_bgColor\").attr('value', bg_color);";
        echo " $('label-editor-indicator').setStyle({backgroundColor: bg_color});";
        echo "</script>";
        echo "</div>";
        echo '</div>';
        echo '</td></tr></table>';
        echo '</div>';
        echo '<div class="dlgButtons">';
        echo "<button data-dojo-type=\"dijit.form.Button\" onclick=\"dijit.byId('labelEditDlg').execute()\">";
        echo __('Save');
        echo '</button>';
        echo "<button data-dojo-type=\"dijit.form.Button\" onclick=\"dijit.byId('labelEditDlg').hide()\">";
        echo __('Cancel');
        echo '</button>';
        echo '</div>';
    }

    public function getlabeltree()
    {
        $root = array();
        $root['id'] = 'root';
        $root['name'] = __('Labels');
        $root['items'] = array();

        $result = \SmallSmallRSS\Database::query(
            'SELECT *
             FROM ttrss_labels2
             WHERE owner_uid = '.$_SESSION['uid'].'
             ORDER BY caption'
        );
        while ($line = \SmallSmallRSS\Database::fetch_assoc($result)) {
            $label = array();
            $label['id'] = 'LABEL:' . $line['id'];
            $label['bare_id'] = $line['id'];
            $label['name'] = $line['caption'];
            $label['fg_color'] = $line['fg_color'];
            $label['bg_color'] = $line['bg_color'];
            $label['type'] = 'label';
            $label['checkbox'] = false;
            array_push($root['items'], $label);
        }
        $fl = array();
        $fl['identifier'] = 'id';
        $fl['label'] = 'name';
        $fl['items'] = array($root);
        echo json_encode($fl);
        return;
    }

    public function colorset()
    {
        $kind = \SmallSmallRSS\Database::escape_string($_REQUEST['kind']);
        $ids = explode(',', \SmallSmallRSS\Database::escape_string($_REQUEST['ids']));
        $color = \SmallSmallRSS\Database::escape_string($_REQUEST['color']);
        $fg = \SmallSmallRSS\Database::escape_string($_REQUEST['fg']);
        $bg = \SmallSmallRSS\Database::escape_string($_REQUEST['bg']);

        foreach ($ids as $id) {

            if ($kind == 'fg' || $kind == 'bg') {
                \SmallSmallRSS\Database::query(
                    "UPDATE ttrss_labels2 SET
                    ${kind}_color = '$color' WHERE id = '$id'
                    AND owner_uid = " . $_SESSION['uid']
                );
            } else {
                \SmallSmallRSS\Database::query(
                    "UPDATE ttrss_labels2
                     SET
                         fg_color = '$fg',
                         bg_color = '$bg'
                     WHERE
                         id = '$id'
                         AND owner_uid = " . $_SESSION['uid']
                );
            }

            $caption = \SmallSmallRSS\Database::escape_string(\SmallSmallRSS\Labels::findCaption($id, $_SESSION['uid']));

            /* Remove cached data */

            \SmallSmallRSS\Database::query(
                "UPDATE ttrss_user_entries
                 SET label_cache = ''
                 WHERE
                     label_cache LIKE '%$caption%'
                     AND owner_uid = " . $_SESSION['uid']
            );

        }

        return;
    }

    public function colorreset()
    {
        $ids = explode(',', \SmallSmallRSS\Database::escape_string($_REQUEST['ids']));
        foreach ($ids as $id) {
            \SmallSmallRSS\Database::query(
                "UPDATE ttrss_labels2
                 SET
                     fg_color = '',
                     bg_color = ''
                 WHERE
                     id = '$id'
                     AND owner_uid = " . $_SESSION['uid']
            );

            $caption = \SmallSmallRSS\Database::escape_string(\SmallSmallRSS\Labels::findCaption($id, $_SESSION['uid']));

            /* Remove cached data */

            \SmallSmallRSS\Database::query(
                "UPDATE ttrss_user_entries
                 SET label_cache = ''
                 WHERE
                     label_cache LIKE '%$caption%'
                     AND owner_uid = " . $_SESSION['uid']
            );
        }

    }

    public function save()
    {

        $id = \SmallSmallRSS\Database::escape_string($_REQUEST['id']);
        $caption = \SmallSmallRSS\Database::escape_string(trim($_REQUEST['caption']));

        \SmallSmallRSS\Database::query('BEGIN');

        $result = \SmallSmallRSS\Database::query(
            "SELECT caption FROM ttrss_labels2
            WHERE id = '$id' AND owner_uid = ". $_SESSION['uid']
        );

        if (\SmallSmallRSS\Database::num_rows($result) != 0) {
            $old_caption = \SmallSmallRSS\Database::fetch_result($result, 0, 'caption');

            $result = \SmallSmallRSS\Database::query(
                "SELECT id FROM ttrss_labels2
                WHERE caption = '$caption' AND owner_uid = ". $_SESSION['uid']
            );

            if (\SmallSmallRSS\Database::num_rows($result) == 0) {
                if ($caption) {
                    $result = \SmallSmallRSS\Database::query(
                        "UPDATE ttrss_labels2 SET
                        caption = '$caption' WHERE id = '$id' AND
                        owner_uid = " . $_SESSION['uid']
                    );

                    /* Update filters that reference label being renamed */

                    $old_caption = \SmallSmallRSS\Database::escape_string($old_caption);

                    \SmallSmallRSS\Database::query(
                        "UPDATE ttrss_filters2_actions SET
                        action_param = '$caption' WHERE action_param = '$old_caption'
                        AND action_id = 7
                        AND filter_id IN (SELECT id FROM ttrss_filters2 WHERE owner_uid = ".$_SESSION['uid'].')'
                    );

                    echo $_REQUEST['value'];
                } else {
                    echo $old_caption;
                }
            } else {
                echo $old_caption;
            }
        }

        \SmallSmallRSS\Database::query('COMMIT');

        return;
    }

    public function remove()
    {

        $ids = explode(',', \SmallSmallRSS\Database::escape_string($_REQUEST['ids']));

        foreach ($ids as $id) {
            \SmallSmallRSS\Labels::remove($id, $_SESSION['uid']);
        }

    }

    public function add()
    {
        $caption = \SmallSmallRSS\Database::escape_string($_REQUEST['caption']);
        $output = \SmallSmallRSS\Database::escape_string($_REQUEST['output']);
        if ($caption) {
            if (\SmallSmallRSS\Labels::create($caption, $_SESSION['uid'])) {
                if (!$output) {
                    echo T_sprintf('Created label <b>%s</b>', htmlspecialchars($caption));
                }
            }
            if ($output == 'select') {
                header('Content-Type: text/xml');
                echo '<rpc-reply><payload>';
                print_label_select('select_label', $caption, '');
                echo '</payload></rpc-reply>';
            }
        }
        return;
    }

    public function index()
    {

        $sort = \SmallSmallRSS\Database::escape_string($_REQUEST['sort']);

        if (!$sort || $sort == 'undefined') {
            $sort = 'caption';
        }

        $label_search = \SmallSmallRSS\Database::escape_string($_REQUEST['search']);

        if (array_key_exists('search', $_REQUEST)) {
            $_SESSION['prefs_label_search'] = $label_search;
        } else {
            $label_search = $_SESSION['prefs_label_search'];
        }

        echo '<div id="pref-label-wrap" data-dojo-type="dijit.layout.BorderContainer" gutters="false">';
        echo '<div id="pref-label-header" data-dojo-type="dijit.layout.ContentPane" region="top">';
        echo '<div id="pref-label-toolbar" data-dojo-type="dijit.Toolbar">';

        echo '<div data-dojo-type="dijit.form.DropDownButton">'.
            '<span>' . __('Select').'</span>';
        echo '<div data-dojo-type="dijit.Menu" style="display: none;">';
        echo "<div onclick=\"dijit.byId('labelTree').model.setAllChecked(true)\"
            data-dojo-type=\"dijit.MenuItem\">".__('All').'</div>';
        echo "<div onclick=\"dijit.byId('labelTree').model.setAllChecked(false)\"
            data-dojo-type=\"dijit.MenuItem\">".__('None').'</div>';
        echo '</div></div>';

        print'<button data-dojo-type="dijit.form.Button" onclick="return addLabel()">'.
            __('Create label').'</button data-dojo-type="dijit.form.Button"> ';

        echo '<button data-dojo-type="dijit.form.Button" onclick="removeSelectedLabels()">'.
            __('Remove').'</button data-dojo-type="dijit.form.Button"> ';

        echo '<button data-dojo-type="dijit.form.Button" onclick="labelColorReset()">'.
            __('Clear colors').'</button data-dojo-type="dijit.form.Button">';


        echo '</div>'; #toolbar
        echo '</div>'; #pane
        echo '<div id="pref-label-content" data-dojo-type="dijit.layout.ContentPane" region="center">';

        echo "<div id=\"labellistLoading\">";
        echo "<img src=\"images/indicator_tiny.gif\" alt=\"\" />";
        echo __('Loading, please wait...');
        echo '</div>';

        echo "<div data-dojo-type=\"dojo.data.ItemFileWriteStore\" jsId=\"labelStore\"";
        echo " url=\"backend.php?op=pref-labels&method=getlabeltree\">";
        echo "</div>";
        echo "<div data-dojo-type=\"lib.CheckBoxStoreModel\" jsId=\"labelModel\" store=\"labelStore\"";
        echo " query=\"{id:'root'}\" rootId=\"root\"";
        echo " childrenAttrs=\"items\" checkboxStrict=\"false\" checkboxAll=\"false\">";
        echo "</div>";
        echo "<div data-dojo-type=\"fox.PrefLabelTree\" id=\"labelTree\"";
        echo " model=\"labelModel\" openOnClick=\"true\">";
        echo "<script type=\"dojo/method\" event=\"onLoad\" args=\"item\">";
        echo "\n Element.hide('labellistLoading');\n";
        echo "</script>";
        echo "<script type=\"dojo/method\" event=\"onClick\" args=\"item\">\n";
        echo "var id = String(item.id);\n";
        echo "var bare_id = id.substr(id.indexOf(':')+1);\n";
        echo "if (id.match('LABEL:')) {\n";
        echo "editLabel(bare_id);\n";
        echo "}\n";
        echo "</script>";
        echo "</div>";
        echo '</div>';
        \SmallSmallRSS\PluginHost::getInstance()->runHooks(
            \SmallSmallRSS\Hooks::RENDER_PREFS_TAB,
            'prefLabels'
        );
        echo '</div>'; #container

    }
}
