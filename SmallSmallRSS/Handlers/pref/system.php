<?php
namespace SmallSmallRSS\Handlers;
class Pref_System extends ProtectedHandler
{
    function before($method)
    {
        if (parent::before($method)) {
            if ($_SESSION["access_level"] < 10) {
                print __("Your access level is insufficient to open this tab.");
                return false;
            }
            return true;
        }
        return false;
    }

    function csrf_ignore($method)
    {
        $csrf_ignored = array("index");

        return array_search($method, $csrf_ignored) !== false;
    }

    function clearLog()
    {
        \SmallSmallRSS\Database::query("DELETE FROM ttrss_error_log");
    }

    function index()
    {

        print "<div dojoType=\"dijit.layout.AccordionContainer\" region=\"center\">";
        print "<div dojoType=\"dijit.layout.AccordionPane\" title=\"".__('Error Log')."\">";

        if (LOG_DESTINATION == "sql") {

            $result = \SmallSmallRSS\Database::query(
                "SELECT errno, errstr, filename, lineno,
                created_at, login FROM ttrss_error_log
                LEFT JOIN ttrss_users ON (owner_uid = ttrss_users.id)
                ORDER BY ttrss_error_log.id DESC
                LIMIT 100"
            );

            print "<button dojoType=\"dijit.form.Button\"
                onclick=\"updateSystemList()\">".__('Refresh')."</button> ";

            print "&nbsp;<button dojoType=\"dijit.form.Button\"
                onclick=\"clearSqlLog()\">".__('Clear log')."</button> ";

            print "<p><table width=\"100%\" cellspacing=\"10\" class=\"prefErrorLog\">";

            print "<tr class=\"title\">
                <td width='5%'>".__("Error")."</td>
                <td>".__("Filename")."</td>
                <td>".__("Message")."</td>
                <td width='5%'>".__("User")."</td>
                <td width='5%'>".__("Date")."</td>
                </tr>";

            while ($line = \SmallSmallRSS\Database::fetch_assoc($result)) {
                print "<tr class=\"errrow\">";

                foreach ($line as $k => $v) {
                    $line[$k] = htmlspecialchars($v);
                }

                print "<td class='errno'>";
                print \SmallSmallRSS\Logger::$errornames[$line["errno"]];
                print " (" . $line["errno"] . ")</td>";
                print "<td class='filename'>" . $line["filename"] . ":" . $line["lineno"] . "</td>";
                print "<td class='errstr'>" . $line["errstr"] . "</td>";
                print "<td class='login'>" . $line["login"] . "</td>";

                print "<td class='timestamp'>" .
                    make_local_datetime(
                        $line["created_at"], false
                    ) . "</td>";

                print "</tr>";
            }

            print "</table>";
        } else {

            \SmallSmallRSS\Renderers\Messages::renderNotice(
                "Please set LOG_DESTINATION to 'sql' in config.php to enable database logging."
            );

        }

        print "</div>";

        \SmallSmallRSS\PluginHost::getInstance()->run_hooks(
            \SmallSmallRSS\PluginHost::HOOK_PREFS_TAB,
            "hook_prefs_tab", "prefSystem"
        );

        print "</div>"; #container
    }
}
