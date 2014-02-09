<?php

class Instances extends \SmallSmallRSS\Plugin implements \SmallSmallRSS\Handlers\IHandler
{
    private $host;

    const API_VERSION = 2;
    const VERSION = 1.0;
    const NAME = 'Linked tt-rss Instances';
    const DESCRIPTION = 'Support for linking tt-rss instances together and sharing popular feeds.';
    const AUTHOR = 'fox';
    const IS_SYSTEM = true;

    public static $provides = array(
        \SmallSmallRSS\Hooks::RENDER_PREFS_TABS,
        \SmallSmallRSS\Hooks::UPDATE_TASK
    );

    private $status_codes = array(
        0 => "Connection failed",
        1 => "Success",
        2 => "Invalid object received",
        16 => "Access denied");

    public function addCommands()
    {
        $this->host->add_handler("pref-instances", "*", $this);
        $this->host->add_handler("public", "fbexport", $this);
        $this->host->addCommand("get-feeds", "receive popular feeds from linked instances", $this);
    }

    public function hookUpdateTask($args)
    {
        $this->get_linked_feeds();
    }

    // Status codes:
    // -1  - never connected
    // 0   - no data received
    // 1   - data received successfully
    // 2   - did not receive valid data
    // >10 - server error, code + 10 (e.g. 16 means server error 6)

    public function get_linked_feeds($instance_id = false)
    {
        if ($instance_id) {
            $instance_qpart = "id = '$instance_id' AND ";
        } else {
            $instance_qpart = "";
        }
        if (\SmallSmallRSS\Config::get('DB_TYPE') == "pgsql") {
            $date_qpart = "last_connected < NOW() - INTERVAL '6 hours'";
        } else {
            $date_qpart = "last_connected < DATE_SUB(NOW(), INTERVAL 6 HOUR)";
        }

        $result = \SmallSmallRSS\Database::query(
            "SELECT id, access_key, access_url
             FROM ttrss_linked_instances
             WHERE $instance_qpart $date_qpart
             ORDER BY last_connected"
        );

        while ($line = \SmallSmallRSS\Database::fetch_assoc($result)) {
            $id = $line['id'];
            $fetch_url = $line['access_url'] . '/public.php?op=fbexport';
            $post_query = 'key=' . $line['access_key'];
            $feeds = \SmallSmallRSS\Fetcher::simpleFetch($fetch_url, false, false, false, $post_query);
            // try doing it the old way
            if (!$feeds) {
                $fetch_url = $line['access_url'] . '/backend.php?op=fbexport';
                $feeds = \SmallSmallRSS\Fetcher::simpleFetch($fetch_url, false, false, false, $post_query);
            }
            if ($feeds) {
                $feeds = json_decode($feeds, true);
                if ($feeds) {
                    if ($feeds['error']) {
                        $status = $feeds['error']['code'] + 10;
                        // access denied
                        if ($status == 16) {
                            \SmallSmallRSS\Database::query(
                                "DELETE FROM ttrss_linked_feeds
                                 WHERE instance_id = '$id'"
                            );
                        }
                    } else {
                        $status = 1;
                        if (count($feeds['feeds']) > 0) {
                            \SmallSmallRSS\Database::query(
                                "DELETE FROM ttrss_linked_feeds
                                 WHERE instance_id = '$id'"
                            );
                            foreach ($feeds['feeds'] as $feed) {
                                $feed_url = \SmallSmallRSS\Database::escape_string($feed['feed_url']);
                                $title = \SmallSmallRSS\Database::escape_string($feed['title']);
                                $subscribers = \SmallSmallRSS\Database::escape_string($feed['subscribers']);
                                $site_url = \SmallSmallRSS\Database::escape_string($feed['site_url']);

                                \SmallSmallRSS\Database::query(
                                    "INSERT INTO ttrss_linked_feeds
                                     (feed_url, site_url, title, subscribers, instance_id, created, updated)
                                     VALUES
                                     ('$feed_url', '$site_url', '$title', '$subscribers', '$id', NOW(), NOW())"
                                );
                            }
                        } else {
                            // received 0 feeds, this might indicate that
                            // the instance on the other hand is rebuilding feedbrowser cache
                            // we will try again later
                            // TODO: maybe perform expiration based on updated here?
                        }
                    }
                } else {
                    $status = 2;
                }
            } else {
                $status = 0;
            }
            \SmallSmallRSS\Database::query(
                "UPDATE ttrss_linked_instances SET last_status_out = '$status', last_connected = NOW() WHERE id = '$id'"
            );
        }
    }

    public function getFeeds()
    {
        $this->get_linked_feeds(false);
    }

    public function getPreferencesJavascript()
    {
        return file_get_contents(dirname(__FILE__) . "/instances.js");
    }

    public function hookRenderPreferencesTabs($args)
    {
        if ($_SESSION["access_level"] >= 10 || \SmallSmallRSS\Auth::is_single_user_mode()) {
            echo '<div id="instanceConfigTab" data-dojo-type="dijit.layout.ContentPane"';
            echo ' href="backend.php?op=pref-instances"';
            echo ' title="';
            echo __('Linked');
            echo '"></div>';
        }
    }

    public function ignoreCSRF($method)
    {
        $csrf_ignored = array("index", "edit");
        return array_search($method, $csrf_ignored) !== false;
    }

    public function before($method)
    {
        if ($_SESSION["uid"]) {
            if ($_SESSION["access_level"] < 10) {
                echo __("Your access level is insufficient to open this tab.");
                return false;
            }
            return true;
        }
        return false;
    }

    public function after()
    {
        return true;
    }

    public function remove()
    {
        $ids = \SmallSmallRSS\Database::escape_string($_REQUEST['ids']);
        \SmallSmallRSS\Database::query(
            "DELETE FROM ttrss_linked_instances WHERE id IN ($ids)"
        );
    }

    public function add()
    {
        $id = \SmallSmallRSS\Database::escape_string($_REQUEST["id"]);
        $access_url = \SmallSmallRSS\Database::escape_string($_REQUEST["access_url"]);
        $access_key = \SmallSmallRSS\Database::escape_string($_REQUEST["access_key"]);

        \SmallSmallRSS\Database::query("BEGIN");

        $result = \SmallSmallRSS\Database::query(
            "SELECT id FROM ttrss_linked_instances
             WHERE access_url = '$access_url'"
        );

        if (\SmallSmallRSS\Database::num_rows($result) == 0) {
            \SmallSmallRSS\Database::query(
                "INSERT INTO ttrss_linked_instances
                 (access_url, access_key, last_connected, last_status_in, last_status_out)
                 VALUES
                 ('$access_url', '$access_key', '1970-01-01', -1, -1)"
            );

        }

        \SmallSmallRSS\Database::query("COMMIT");
    }

    public function edit()
    {
        $id = \SmallSmallRSS\Database::escape_string($_REQUEST["id"]);
        $result = \SmallSmallRSS\Database::query(
            "SELECT * FROM ttrss_linked_instances WHERE id = '$id'"
        );
        echo "<input data-dojo-type=\"dijit.form.TextBox\" style=\"display: none\"  name=\"id\" value=\"$id\" />";
        echo '<input data-dojo-type="dijit.form.TextBox" style="display: none"  name="op" value="pref-instances" />';
        echo '<input data-dojo-type="dijit.form.TextBox" style="display: none" name="method" value="editSave" />';
        echo '<div class="dlgSec">"';
        echo __("Instance");
        echo '"</div>';
        echo '<div class="dlgSecCont">';
        /* URL */
        $access_url = htmlspecialchars(\SmallSmallRSS\Database::fetch_result($result, 0, "access_url"));
        echo __("URL:") . " ";
        echo "<input data-dojo-type=\"dijit.form.ValidationTextBox\" required=\"1\"
            placeHolder=\"".__("Instance URL")."\"
            regExp='^(http|https)://.*'
            style=\"font-size: 16px; width: 20em\" name=\"access_url\"
            value=\"$access_url\">";
        echo "<hr/>";
        $access_key = htmlspecialchars(\SmallSmallRSS\Database::fetch_result($result, 0, "access_key"));

        /* Access key */
        echo __("Access key:") . " ";
        echo "<input data-dojo-type=\"dijit.form.ValidationTextBox\" required=\"1\"
            placeHolder=\"".__("Access key")."\" regExp='\w{40}'
            style=\"width: 20em\" name=\"access_key\" id=\"instance_edit_key\"
            value=\"$access_key\">";
        echo "<p class='insensitive'>" . __("Use one access key for both linked instances.");
        echo "</div>";
        echo "<div class=\"dlgButtons\">
            <div style='float: left'>
                <button data-dojo-type=\"dijit.form.Button\"
                    onclick=\"return dijit.byId('instanceEditDlg').regenKey()\">";
        echo __('Generate new key');
        echo "</button>";
        echo '</div>';
        echo '<button data-dojo-type="dijit.form.Button" onclick="return dijit.byId(\'instanceEditDlg\').execute();">';
        echo __('Save');
        echo '</button>';
        echo '<button data-dojo-type="dijit.form.Button" onclick="return dijit.byId(\'instanceEditDlg\').hide();">';
        echo __('Cancel');
        echo '</button>';
        echo '</div>';
    }

    public function editSave()
    {
        $id = \SmallSmallRSS\Database::escape_string($_REQUEST["id"]);
        $access_url = \SmallSmallRSS\Database::escape_string($_REQUEST["access_url"]);
        $access_key = \SmallSmallRSS\Database::escape_string($_REQUEST["access_key"]);

        \SmallSmallRSS\Database::query(
            "UPDATE ttrss_linked_instances
             SET access_key = '$access_key',
                 access_url = '$access_url',
                 last_connected = '1970-01-01'
             WHERE id = '$id'"
        );

    }

    public function index()
    {
        if (!function_exists('curl_init')) {
            echo "<div style='padding : 1em'>";
            \SmallSmallRSS\Renderers\Messages::renderError(
                "This functionality requires CURL functions."
                . " Please enable CURL in your PHP configuration"
                . " (you might also want to disable open_basedir in php.ini)"
                . " and reload this page."
            );
            echo "</div>";
        }
        echo "<div id=\"pref-instance-wrap\" data-dojo-type=\"dijit.layout.BorderContainer\" gutters=\"false\">";
        echo "<div id=\"pref-instance-header\" data-dojo-type=\"dijit.layout.ContentPane\" region=\"top\">";
        echo "<div id=\"pref-instance-toolbar\" data-dojo-type=\"dijit.Toolbar\">";
        $sort = \SmallSmallRSS\Database::escape_string($_REQUEST["sort"]);
        if (!$sort || $sort == "undefined") {
            $sort = "access_url";
        }
        echo "<div data-dojo-type=\"dijit.form.DropDownButton\">".
            "<span>" . __('Select')."</span>";
        echo "<div data-dojo-type=\"dijit.Menu\" style=\"display: none;\">";
        echo "<div onclick=\"selectTableRows('prefInstanceList', 'all')\"
            data-dojo-type=\"dijit.MenuItem\">".__('All')."</div>";
        echo "<div onclick=\"selectTableRows('prefInstanceList', 'none')\"
            data-dojo-type=\"dijit.MenuItem\">".__('None')."</div>";
        echo "</div></div>";

        echo "<button data-dojo-type=\"dijit.form.Button\" onclick=\"addInstance()\">".__('Link instance')."</button>";
        echo "<button data-dojo-type=\"dijit.form.Button\" onclick=\"editSelectedInstance()\">".__('Edit')."</button>";
        echo "<button data-dojo-type=\"dijit.form.Button\" onclick=\"removeSelectedInstances()\">".__('Remove')."</button>";

        echo "</div>"; #toolbar

        $result = \SmallSmallRSS\Database::query(
            "SELECT
                 *,
                 (SELECT COUNT(*) FROM ttrss_linked_feeds
                  WHERE instance_id = ttrss_linked_instances.id) AS num_feeds
             FROM ttrss_linked_instances
             ORDER BY $sort"
        );

        echo "<p class=\"insensitive\" style='margin-left : 1em;'>"
            . __("You can connect other instances of Tiny Tiny RSS to this one to share Popular feeds. Link to this instance of Tiny Tiny RSS by using this URL:");
        echo " <a href=\"#\" onclick=\"alert('".htmlspecialchars(get_self_url_prefix())."')\">(display url)</a>";
        echo "<p><table width='100%' id='prefInstanceList' class='prefInstanceList' cellspacing='0'>";
        echo "<tr class=\"title\">
            <td align='center' width=\"5%\">&nbsp;</td>
            <td width=''><a href=\"#\" onclick=\"updateInstanceList('access_url')\">".__('Instance URL')."</a></td>
            <td width='20%'><a href=\"#\" onclick=\"updateInstanceList('access_key')\">".__('Access key')."</a></td>
            <td width='10%'><a href=\"#\" onclick=\"updateUsersList('last_connected')\">".__('Last connected')."</a></td>
            <td width='10%'><a href=\"#\" onclick=\"updateUsersList('last_status_out')\">".__('Status')."</a></td>
            <td width='10%'><a href=\"#\" onclick=\"updateUsersList('num_feeds')\">".__('Stored feeds')."</a></td>
            </tr>";

        $lnum = 0;

        while ($line = \SmallSmallRSS\Database::fetch_assoc($result)) {
            $class = ($lnum % 2) ? "even" : "odd";

            $id = $line['id'];
            $this_row_id = "id=\"LIRR-$id\"";

            $line["last_connected"] = make_local_datetime($line["last_connected"], false);

            echo "<tr class=\"$class\" $this_row_id>";

            echo "<td align='center'><input onclick='toggleSelectRow(this);'
                type=\"checkbox\" id=\"LICHK-$id\"></td>";

            $onclick = "onclick='editInstance($id, event)' title='".__('Click to edit')."'";

            $access_key = mb_substr($line['access_key'], 0, 4) . '...' .
                mb_substr($line['access_key'], -4);

            echo "<td $onclick>" . htmlspecialchars($line['access_url']) . "</td>";
            echo "<td $onclick>" . htmlspecialchars($access_key) . "</td>";
            echo "<td $onclick>" . htmlspecialchars($line['last_connected']) . "</td>";
            echo "<td $onclick>" . $this->status_codes[$line['last_status_out']] . "</td>";
            echo "<td $onclick>" . htmlspecialchars($line['num_feeds']) . "</td>";

            echo "</tr>";

            ++$lnum;
        }
        echo "</table>";
        echo "</div>"; #pane
        \SmallSmallRSS\PluginHost::getInstance()->runHooks(\SmallSmallRSS\Hooks::RENDER_PREFS_TAB, "prefInstances");
        echo "</div>"; #container

    }

    public function fbexport()
    {
        $access_key = \SmallSmallRSS\Database::escape_string($_POST["key"]);

        // TODO: rate limit checking using last_connected
        $result = \SmallSmallRSS\Database::query(
            "SELECT id
             FROM ttrss_linked_instances
             WHERE access_key = '$access_key'"
        );

        if (\SmallSmallRSS\Database::num_rows($result) == 1) {

            $instance_id = \SmallSmallRSS\Database::fetch_result($result, 0, "id");

            $result = \SmallSmallRSS\Database::query(
                "SELECT feed_url, site_url, title, subscribers
                 FROM ttrss_feedbrowser_cache ORDER BY subscribers DESC LIMIT 100"
            );

            $feeds = array();

            while ($line = \SmallSmallRSS\Database::fetch_assoc($result)) {
                array_push($feeds, $line);
            }

            \SmallSmallRSS\Database::query(
                "UPDATE ttrss_linked_instances
                 SET last_status_in = 1
                 WHERE id = '$instance_id'"
            );

            echo json_encode(array("feeds" => $feeds));
        } else {
            $renderer = new \SmallSmallRSS\Renderers\JSONError(6);
            $renderer->render();
        }
    }

    public function addInstance()
    {
        echo "<input data-dojo-type=\"dijit.form.TextBox\" style=\"display: none\"  name=\"op\" value=\"pref-instances\">";
        echo "<input data-dojo-type=\"dijit.form.TextBox\" style=\"display: none\"  name=\"method\" value=\"add\">";
        echo "<div class=\"dlgSec\">".__("Instance")."</div>";
        echo "<div class=\"dlgSecCont\">";

        /* URL */
        echo __("URL:") . " ";
        echo "<input data-dojo-type=\"dijit.form.ValidationTextBox\" required=\"1\"
            placeHolder=\"".__("Instance URL")."\"
            regExp='^(http|https)://.*'
            style=\"font-size: 16px; width: 20em\" name=\"access_url\">";
        echo "<hr/>";

        $access_key = sha1(uniqid(rand(), true));

        /* Access key */
        echo __("Access key:") . " ";
        echo "<input data-dojo-type=\"dijit.form.ValidationTextBox\" required=\"1\"
            placeHolder=\"".__("Access key")."\" regExp='\w{40}'
            style=\"width: 20em\" name=\"access_key\" id=\"instance_add_key\"
            value=\"$access_key\">";
        echo "<p class='insensitive'>" . __("Use one access key for both linked instances.");
        echo "</div>";
        echo "<div class=\"dlgButtons\">
            <div style='float: left'>
                <button data-dojo-type=\"dijit.form.Button\"
                    onclick=\"return dijit.byId('instanceAddDlg').regenKey()\">"
            . __('Generate new key')
            . "</button>
            </div>
            <button data-dojo-type=\"dijit.form.Button\"
                onclick=\"return dijit.byId('instanceAddDlg').execute()\">"
            . __('Create link')."</button>
            <button data-dojo-type=\"dijit.form.Button\"
                onclick=\"return dijit.byId('instanceAddDlg').hide()\"\">"
            . __('Cancel')."</button></div>";
    }

    public function genHash()
    {
        $hash = sha1(uniqid(rand(), true));
        echo json_encode(array("hash" => $hash));
    }
}
