<?php
namespace SmallSmallRSS\Handlers;

class Pref_System extends ProtectedHandler
{
    public function before($method)
    {
        if (parent::before($method)) {
            if ($_SESSION['access_level'] < 10) {
                print __('Your access level is insufficient to open this tab.');
                return false;
            }
            return true;
        }
        return false;
    }

    public function ignoreCSRF($method)
    {
        $csrf_ignored = array('index');
        return array_search($method, $csrf_ignored) !== false;
    }

    public function clearLog()
    {
        \SmallSmallRSS\Database::query('DELETE FROM ttrss_error_log');
    }

    public function index()
    {
        print '<div data-dojo-type="dijit.layout.AccordionContainer" region="center">';
        print '<div data-dojo-type="dijit.layout.AccordionPane" title="'.__('Error Log').'">';
        if (\SmallSmallRSS\Config::get('LOG_DESTINATION') == 'sql') {
            $result = \SmallSmallRSS\Database::query(
                'SELECT
                     errno, errstr, filename, lineno, created_at, login
                 FROM ttrss_error_log
                 LEFT JOIN ttrss_users ON (owner_uid = ttrss_users.id)
                 ORDER BY ttrss_error_log.id DESC
                 LIMIT 100'
            );
            print '<button data-dojo-type="dijit.form.Button" onclick="updateSystemList()">';
            print __('Refresh');
            print '</button> ';

            print '&nbsp;<button data-dojo-type="dijit.form.Button" onclick="clearSqlLog()">';
            print __('Clear log');
            print '</button> ';

            print '<p>';
            print '<table width="100%" cellspacing="10" class="prefErrorLog">';
            print '<tr class="title">';
            print "<td width='5%'>".__('Error').'</td>';
            print '<td>'.__('Filename').'</td>';
            print '<td>'.__('Message').'</td>';
            print "<td width='5%'>".__('User').'</td>';
            print "<td width='5%'>".__('Date').'</td>';
            print '</tr>';
            while ($line = \SmallSmallRSS\Database::fetch_assoc($result)) {
                print '<tr class="errrow">';
                foreach ($line as $k => $v) {
                    $line[$k] = htmlspecialchars($v);
                }
                print "<td class='errno'>";
                print \SmallSmallRSS\Logger::$errornames[$line['errno']];
                print ' (' . $line['errno'] . ')</td>';
                print "<td class='filename'>" . $line['filename'] . ':' . $line['lineno'] . '</td>';
                print "<td class='errstr'>" . $line['errstr'] . '</td>';
                print "<td class='login'>" . $line['login'] . '</td>';
                print "<td class='timestamp'>";
                print make_local_datetime($line['created_at'], false);
                print '</td>';
                print '</tr>';
            }
            print '</table>';
        } else {

            \SmallSmallRSS\Renderers\Messages::renderNotice(
                "Please set LOG_DESTINATION to 'sql' in config.php to enable database logging."
            );
        }
        print '</div>';
        \SmallSmallRSS\PluginHost::getInstance()->runHooks(\SmallSmallRSS\Hooks::RENDER_PREFS_TAB, 'prefSystem');
        print '</div>'; #container
    }
}
