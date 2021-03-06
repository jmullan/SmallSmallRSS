<?php
namespace SmallSmallRSS;

class Prefs
{
    public static function getAll()
    {
        $result = \SmallSmallRSS\Database::query(
            'SELECT pref_name, def_value
             FROM ttrss_prefs'
        );
        $prefs = array();
        while (($line = \SmallSmallRSS\Database::fetchAssoc($result))) {
            $pref_name = $line['pref_name'];
            $value = $line['def_value'];
            $prefs[$pref_name] = $value;
        }
        return $prefs;
    }

    public static function getHelp($pref_name)
    {
        $pref_name = \SmallSmallRSS\Database::escapeString($pref_name);
        $result = \SmallSmallRSS\Database::query(
            "SELECT help_text
             FROM ttrss_prefs
             WHERE pref_name = '$pref_name'"
        );
        if (\SmallSmallRSS\Database::numRows($result) > 0) {
            return \SmallSmallRSS\Database::fetchResult($result, 0, 'help_text');
        } else {
            return false;
        }
    }
}
