<?php
namespace SmallSmallRSS;

class DbPrefs
{
    private static $cache = array();
    public static function cache()
    {
        $profile = false;
        $user_id = (!empty($_SESSION["uid"]) ? $_SESSION["uid"] : null);
        $profile = (!empty($_SESSION["profile"]) ? $_SESSION["profile"] : null);
        if ($profile) {
            $profile_qpart = "profile = '$profile' AND";
        } else {
            $profile_qpart = "profile IS NULL AND";
        }
        if (\SmallSmallRSS\Sanity::getSchemaVersion() < 63) {
            $profile_qpart = "";
        }
        $result = \SmallSmallRSS\Database::query(
            "SELECT
                 value,
                 ttrss_prefs_types.type_name as type_name,
                 ttrss_prefs.pref_name AS pref_name
             FROM
                 ttrss_user_prefs,ttrss_prefs,ttrss_prefs_types
             WHERE
                 $profile_qpart
                 ttrss_prefs.pref_name NOT LIKE '_MOBILE%' AND
                 ttrss_prefs_types.id = type_id AND
                 owner_uid = '$user_id' AND
                 ttrss_user_prefs.pref_name = ttrss_prefs.pref_name"
        );
        while ($line = \SmallSmallRSS\Database::fetch_assoc($result)) {

            if ($user_id == $_SESSION["uid"]) {
                $pref_name = $line["pref_name"];
                self::$cache[$pref_name]["type"] = $line["type_name"];
                self::$cache[$pref_name]["value"] = $line["value"];
            }
        }
    }

    public static function read($pref_name, $user_id = false, $die_on_error = false)
    {

        $pref_name = \SmallSmallRSS\Database::escape_string($pref_name);
        $profile = false;

        if (!$user_id) {
            $user_id = $_SESSION["uid"];
            @$profile = $_SESSION["profile"];
        } else {
            $user_id = sprintf("%d", $user_id);
        }

        if (isset(self::$cache[$pref_name])) {
            $tuple = self::$cache[$pref_name];
            return self::convert($tuple["value"], $tuple["type"]);
        }

        if ($profile) {
            $profile_qpart = "profile = '$profile' AND";
        } else {
            $profile_qpart = "profile IS NULL AND";
        }

        if (\SmallSmallRSS\Sanity::getSchemaVersion() < 63) {
            $profile_qpart = "";
        }

        $result = \SmallSmallRSS\Database::query(
            "SELECT
                 value,
                 ttrss_prefs_types.type_name as type_name
             FROM
                 ttrss_user_prefs,ttrss_prefs,ttrss_prefs_types
             WHERE
                 $profile_qpart
                 ttrss_user_prefs.pref_name = '$pref_name'
                 AND ttrss_prefs_types.id = type_id
                 AND owner_uid = '$user_id'
                 AND ttrss_user_prefs.pref_name = ttrss_prefs.pref_name"
        );

        if (\SmallSmallRSS\Database::num_rows($result) > 0) {
            $value = \SmallSmallRSS\Database::fetch_result($result, 0, "value");
            $type_name = \SmallSmallRSS\Database::fetch_result($result, 0, "type_name");

            if (!empty($_SESSION["uid"]) && $user_id == $_SESSION["uid"]) {
                self::$cache[$pref_name]["type"] = $type_name;
                self::$cache[$pref_name]["value"] = $value;
            }

            return self::convert($value, $type_name);
        } else {
            user_error(
                "Fatal error, unknown preferences key: $pref_name (owner: $user_id)",
                $die_on_error ? E_USER_ERROR : E_USER_WARNING
            );
            return null;
        }
    }

    public static function convert($value, $type_name)
    {
        if ($type_name == "bool") {
            return $value == "true";
        } elseif ($type_name == "integer") {
            return (int) $value;
        } else {
            return $value;
        }
    }

    public static function write($pref_name, $value, $user_id = false, $strip_tags = true)
    {
        $pref_name = \SmallSmallRSS\Database::escape_string($pref_name);
        $value = \SmallSmallRSS\Database::escape_string($value, $strip_tags);

        if (!$user_id) {
            $user_id = $_SESSION["uid"];
            @$profile = $_SESSION["profile"];
        } else {
            $user_id = sprintf("%d", $user_id);
            $prefs_cache = false;
        }

        if ($profile) {
            $profile_qpart = "AND profile = '$profile'";
        } else {
            $profile_qpart = "AND profile IS NULL";
        }

        if (\SmallSmallRSS\Sanity::getSchemaVersion() < 63) {
            $profile_qpart = "";
        }

        $type_name = "";
        $current_value = "";

        if (isset(self::$cache[$pref_name])) {
            $type_name = self::$cache[$pref_name]["type"];
            $current_value = self::$cache[$pref_name]["value"];
        }

        if (!$type_name) {
            $result = \SmallSmallRSS\Database::query(
                "SELECT type_name
                 FROM
                     ttrss_prefs,
                     ttrss_prefs_types
                 WHERE
                     pref_name = '$pref_name'
                     AND type_id = ttrss_prefs_types.id"
            );

            if (\SmallSmallRSS\Database::num_rows($result) > 0) {
                $type_name = \SmallSmallRSS\Database::fetch_result($result, 0, "type_name");
            }
        } elseif ($current_value == $value) {
            return;
        }

        if ($type_name) {
            if ($type_name == "bool") {
                if ($value == "1" || $value == "true") {
                    $value = "true";
                } else {
                    $value = "false";
                }
            } elseif ($type_name == "integer") {
                $value = sprintf("%d", $value);
            }

            if ($pref_name == 'USER_TIMEZONE' && $value == '') {
                $value = 'UTC';
            }

            \SmallSmallRSS\Database::query(
                "UPDATE ttrss_user_prefs
                 SET value = '$value'
                 WHERE
                     pref_name = '$pref_name'
                     $profile_qpart
                     AND owner_uid = " . $_SESSION["uid"]
            );

            if ($user_id == $_SESSION["uid"]) {
                self::$cache[$pref_name]["type"] = $type_name;
                self::$cache[$pref_name]["value"] = $value;
            }
        }
    }
}
