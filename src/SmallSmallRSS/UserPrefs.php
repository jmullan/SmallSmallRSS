<?php
namespace SmallSmallRSS;

class UserPrefs
{
    public static function getActive($owner_uid, $profile = false)
    {
        $owner_uid = \SmallSmallRSS\Database::escape_string($owner_uid);
        if (!$profile) {
            $profile = 'NULL';
            $profile_qpart = 'AND profile IS NULL';
        } else {
            $profile = \SmallSmallRSS\Database::escape_string($profile);
            $profile_qpart = "AND profile = '$profile'";
        }
        if (\SmallSmallRSS\Sanity::getSchemaVersion() < 63) {
            $profile_qpart = '';
        }
        $u_result = \SmallSmallRSS\Database::query(
            "SELECT pref_name, value
             FROM ttrss_user_prefs
             WHERE owner_uid = '$owner_uid'
             $profile_qpart"
        );
        $active_prefs = array();
        while (($line = \SmallSmallRSS\Database::fetch_assoc($u_result))) {
            $active_prefs[$line['pref_name']] = $line['value'];
        }
    }
    public static function insert($pref_name, $value, $owner_uid, $profile = false)
    {
        $owner_uid = \SmallSmallRSS\Database::escape_string($owner_uid);
        $profile = \SmallSmallRSS\Database::escape_string($profile);
        $value = \SmallSmallRSS\Database::escape_string($value);
        $pref_name = \SmallSmallRSS\Database::escape_string($pref_name);

        if (\SmallSmallRSS\Sanity::getSchemaVersion() < 63) {
            \SmallSmallRSS\Database::query(
                "INSERT INTO ttrss_user_prefs
                 (owner_uid, pref_name, value)
                 VALUES
                 ('$owner_uid', '" . $pref_name . "','" . $value . "')"
            );
        } else {
            \SmallSmallRSS\Database::query(
                "INSERT INTO ttrss_user_prefs
                 (owner_uid, pref_name, value, profile)
                 VALUES
                 ('$owner_uid', '" . $pref_name . "','" . $value . "', $profile)"
            );
        }
    }
}
