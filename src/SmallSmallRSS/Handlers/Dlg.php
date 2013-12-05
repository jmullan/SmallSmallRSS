<?php
namespace SmallSmallRSS\Handlers;
class Dlg extends ProtectedHandler
{
    private $param;

    public function before($method)
    {
        if (parent::before($method)) {
            header('Content-Type: text/html'); # required for iframe
            $this->param = \SmallSmallRSS\Database::escape_string($_REQUEST['param']);
            return true;
        }
        return false;
    }

    public function importOpml()
    {
        echo __('If you have imported labels and/or filters, you might need to reload preferences to see your new data.') . '</p>';
        echo '<div class="prefFeedOPMLHolder">';
        $owner_uid = $_SESSION['uid'];
        \SmallSmallRSS\Database::query('BEGIN');
        echo "<ul class='nomarks'>";
        $opml = new Opml($_REQUEST);
        $opml->opml_import($_SESSION['uid']);
        \SmallSmallRSS\Database::query('COMMIT');
        echo '</ul>';
        echo '</div>';
        echo "<div align='center'>";
        echo "<button data-dojo-type=\"dijit.form.Button\"
            onclick=\"dijit.byId('opmlImportDlg').execute()\">".
            __('Close this window').'</button>';
        echo '</div>';
        echo '</div>';
    }

    public function pubOPMLUrl()
    {
        $url_path = Opml::opml_publish_url();
        echo __('Your Public OPML URL is:');
        echo '<div class="tagCloudContainer">';
        echo "<a id='pub_opml_url' href='$url_path' target='_blank'>$url_path</a>";
        echo '</div>';
        echo "<div align='center'>";
        echo '<button data-dojo-type="dijit.form.Button" onclick="return opmlRegenKey()">'
            . __('Generate new URL').'</button> ';
        echo '<button data-dojo-type="dijit.form.Button" onclick="return closeInfoBox()">'
            . __('Close this window').'</button>';
        echo '</div>';
    }

    public function explainError()
    {
        echo '<div class="errorExplained">';
        if ($this->param == 1) {
            echo __('Update daemon is enabled in configuration, but daemon process is not running, which prevents all feeds from updating. Please start the daemon process or contact instance owner.');
            $stamp = (int) \SmallSmallRSS\Lockfiles::whenStamped('update_daemon');
            echo '<p>' . __('Last update:') . ' ' . date('Y.m.d, G:i', $stamp) . '</p>';

        }
        if ($this->param == 3) {
            echo __('Update daemon is taking too long to perform a feed update.  This could indicate a problem like crash or a hang. Please check the daemon process or contact instance owner.');
            $stamp = (int) \SmallSmallRSS\Lockfiles::whenStamped('update_daemon');
            echo '<p>' . __('Last update:') . ' ' . date('Y.m.d, G:i', $stamp) . '</p>';
        }
        echo '</div>';
        echo "<div align='center'>";
        echo '<button onclick="return closeInfoBox()">';
        echo __('Close this window');
        echo '</button>';
        echo '</div>';


    }

    public function printTagCloud()
    {
        echo '<div class="tagCloudContainer">';
        // from here: http://www.roscripts.com/Create_tag_cloud-71.html
        $owner_uid = $_SESSION['uid'];
        $query = 'SELECT
                      tag_name,
                      COUNT(post_int_id) AS tag_count
                  FROM ttrss_tags
                  WHERE owner_uid = ' . $owner_uid . '
                  GROUP BY tag_name
                  ORDER BY tag_count DESC
                  LIMIT 50';

        $result = \SmallSmallRSS\Database::query($query);
        $tags = array();
        while ($line = \SmallSmallRSS\Database::fetch_assoc($result)) {
            $tags[$line['tag_name']] = $line['tag_count'];
        }
        if (!$tags) {
            return;
        }
        ksort($tags);
        $max_size = 32; // max font size in pixels
        $min_size = 11; // min font size in pixels
        // largest and smallest array values
        $max_qty = max(array_values($tags));
        $min_qty = min(array_values($tags));
        // find the range of values
        $spread = $max_qty - $min_qty;
        if ($spread == 0) { // we don't want to divide by zero
            $spread = 1;
        }

        // set the font-size increment
        $step = ($max_size - $min_size) / ($spread);

        // loop through the tag array
        foreach ($tags as $key => $value) {
            // calculate font-size
            // find the $value in excess of $min_qty
            // multiply by the font-size increment ($size)
            // and add the $min_size set above
            $size = round($min_size + (($value - $min_qty) * $step));
            $key_escaped = str_replace("'", "\\'", $key);
            echo "<a href=\"javascript:viewfeed('$key_escaped') \"";
            echo ' style="font-size: ' . $size . 'px"';
            echo " title=\"$value articles tagged with " . $key . '">';
            echo htmlspecialchars($key);
            echo '</a>';
        }
        echo '</div>';
        echo "<div align='center'>";
        echo '<button data-dojo-type="dijit.form.Button" onclick="return closeInfoBox()">';
        echo __('Close this window');
        echo '</button>';
        echo '</div>';
    }

    public function printTagSelect()
    {
        echo __('Match:');
        echo '&nbsp;';
        echo '<input class="noborder" type="radio" checked="checked" value="any" name="tag_mode"';
        echo ' id="tag_mode_any" data-dojo-type="dijit.form.RadioButton" />';
        echo '<label for="tag_mode_any">';
        echo __('Any');
        echo '</label>';
        echo '&nbsp;';
        echo '<input class="noborder" type="radio" value="all" name="tag_mode" id="tag_mode_all"';
        echo ' data-dojo-type="dijit.form.RadioButton" />';
        echo '<label for="tag_mode_all">'.__('All tags.').'</input>';
        echo '<select id="all_tags" name="all_tags" title="';
        echo __('Which Tags?');
        echo '" multiple="multiple" size="10" style="width: 100%">';
        $result = \SmallSmallRSS\Database::query(
            'SELECT DISTINCT tag_name
             FROM ttrss_tags
             WHERE
                 owner_uid = ' . $_SESSION['uid'] . '
                 AND LENGTH(tag_name) <= 30
             ORDER BY tag_name ASC'
        );
        while ($row = \SmallSmallRSS\Database::fetch_assoc($result)) {
            $tag_name = htmlspecialchars($row['tag_name']);
            echo '<option value="' . str_replace(' ', '%20', $tagname) . '">';
            echo $tag_name;
            echo '</option>';
        }
        echo '</select>';
        echo "<div align='right'>";
        echo '<button data-dojo-type="dijit.form.Button"';
        echo "onclick=\"viewfeed(get_all_tags($('all_tags')), get_radio_checked($('tag_mode')));\">";
        echo __('Display entries');
        echo '</button>';
        echo '&nbsp;';
        echo '<button data-dojo-type="dijit.form.Button" onclick="return closeInfoBox()">';
        echo __('Close this window');
        echo '</button>';
        echo '</div>';
    }

    public function generatedFeed()
    {
        $this->params = explode(':', $this->param, 3);
        $feed_id = \SmallSmallRSS\Database::escape_string($this->params[0]);
        $is_cat = (bool) $this->params[1];
        $key = \SmallSmallRSS\AccessKeys::getForFeed($feed_id, $is_cat, $_SESSION['uid']);
        $url_path = htmlspecialchars($this->params[2]) . '&key=' . $key;
        echo '<h2>'.__('You can view this feed as RSS using the following URL:').'</h2>';
        echo '<div class="tagCloudContainer">';
        echo "<a id='gen_feed_url' href='$url_path' target='_blank'>$url_path</a>";
        echo '</div>';
        echo "<div align='center'>";
        echo "<button data-dojo-type=\"dijit.form.Button\" onclick=\"return genUrlChangeKey('$feed_id', '$is_cat')\">";
        echo __('Generate new URL');
        echo '</button> ';
        echo '<button data-dojo-type="dijit.form.Button" onclick="return closeInfoBox()">';
        echo __('Close this window');
        echo '</button>';
        echo '</div>';
    }
}
