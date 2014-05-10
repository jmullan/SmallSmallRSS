<?php
namespace SmallSmallRSS;

class UserPrefs
{
    public static function getActive($owner_uid, $profile = false)
    {
        $owner_uid = \SmallSmallRSS\Database::quote_string($owner_uid);
        if (!$profile) {
            $profile = 'NULL';
            $profile_qpart = 'AND profile IS NULL';
        } else {
            $profile = \SmallSmallRSS\Database::quote_string($profile);
            $profile_qpart = "AND profile = $profile";
        }
        if (\SmallSmallRSS\Sanity::getSchemaVersion() < 63) {
            $profile_qpart = '';
        }
        $u_result = \SmallSmallRSS\Database::query(
            "SELECT pref_name, value
             FROM ttrss_user_prefs
             WHERE owner_uid = $owner_uid
             $profile_qpart"
        );
        $active_prefs = array();
        while (($line = \SmallSmallRSS\Database::fetch_assoc($u_result))) {
            $active_prefs[$line['pref_name']] = $line['value'];
        }
    }
    public static function insert($pref_name, $value, $owner_uid, $profile = false)
    {
        $owner_uid = \SmallSmallRSS\Database::quote_string($owner_uid);
        $value = \SmallSmallRSS\Database::quote_string($value);
        $pref_name = \SmallSmallRSS\Database::quote_string($pref_name);
        if ($profile) {
            $profile = \SmallSmallRSS\Database::quote_string($profile);
        } else {
            $profile = 'null';
        }

        if (\SmallSmallRSS\Sanity::getSchemaVersion() < 63) {
            \SmallSmallRSS\Database::query(
                "INSERT INTO ttrss_user_prefs
                 (owner_uid, pref_name, value)
                 VALUES
                 ($owner_uid, " . $pref_name . "," . $value . ")"
            );
        } else {
            \SmallSmallRSS\Database::query(
                "INSERT INTO ttrss_user_prefs
                 (owner_uid, pref_name, value, profile)
                 VALUES
                 ($owner_uid, $pref_name, $value, $profile)"
            );
        }
    }
    public static function initialize($uid, $profile)
    {
        \SmallSmallRSS\Database::query('BEGIN');
        $active_prefs = \SmallSmallRSS\UserPrefs::getActive($uid, $profile);
        $default_prefs = \SmallSmallRSS\Prefs::getAll();
        foreach ($default_prefs as $pref_name => $value) {
            if (!isset($active_prefs[$pref_name])) {
                \SmallSmallRSS\UserPrefs::insert($pref_name, $value, $uid, $profile);
            }
        }
        \SmallSmallRSS\Database::query('COMMIT');

    }
}
