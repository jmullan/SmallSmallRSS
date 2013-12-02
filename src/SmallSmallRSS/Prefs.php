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
        while (($line = \SmallSmallRSS\Database::fetch_assoc($result))) {
            $pref_name = $line['pref_name'];
            $value = $line['def_value'];
            $prefs[$pref_name] = $value;
        }
        return $prefs;
    }
}
