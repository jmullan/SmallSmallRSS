<?php
namespace SmallSmallRSS;

class DBPrefs
{
    private static $cache = array();

    private static function cachePref($user_id, $pref_name, $type_name, $value)
    {
        if (!isset(self::$cache[$user_id])) {
            self::$cache[$user_id] = array();
        }
        self::$cache[$user_id][$pref_name] = array('type' => $type_name, 'value' => $value);
    }

    public static function read($pref_name, $user_id = false, $die_on_error = false)
    {

        $pref_name = \SmallSmallRSS\Database::escapeString($pref_name);
        $profile = null;

        if (!$user_id) {
            $user_id = (!empty($_SESSION['uid']) ? $_SESSION['uid'] : null);
            $profile = (!empty($_SESSION['profile']) ? $_SESSION['profile'] : null);
        }
        $user_id = sprintf('%d', $user_id);

        if (isset(self::$cache[$user_id][$pref_name])) {
            $tuple = self::$cache[$user_id][$pref_name];
            return self::convert($tuple['value'], $tuple['type']);
        }

        if ($profile) {
            $profile_qpart = "profile = '$profile' AND";
        } else {
            $profile_qpart = 'profile IS NULL AND';
        }

        if (\SmallSmallRSS\Sanity::getSchemaVersion() < 63) {
            $profile_qpart = '';
        }

        $result = \SmallSmallRSS\Database::query(
            "SELECT
                 value,
                 ttrss_prefs_types.type_name as type_name
             FROM
                 ttrss_user_prefs,
                 ttrss_prefs,
                 ttrss_prefs_types
             WHERE
                 $profile_qpart
                 ttrss_user_prefs.pref_name = '$pref_name'
                 AND ttrss_prefs_types.id = type_id
                 AND owner_uid = '$user_id'
                 AND ttrss_user_prefs.pref_name = ttrss_prefs.pref_name"
        );

        if (\SmallSmallRSS\Database::numRows($result) > 0) {
            $pref = \SmallSmallRSS\Database::fetchAssoc($result);
            $value = $pref['value'];
            $type_name = $pref['type_name'];
            self::cachePref($user_id, $pref_name, $type_name, $value);
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
        if ($type_name == 'bool') {
            return $value === 'true' || $value === 1 || $value === '1' || $value === 'y' || $value === 'yes';
        } elseif ($type_name == 'integer') {
            return (int) $value;
        } else {
            return $value;
        }
    }

    public static function write($pref_name, $value, $user_id = false, $strip_tags = true)
    {
        $pref_name = \SmallSmallRSS\Database::escapeString($pref_name);
        $value = \SmallSmallRSS\Database::escapeString($value, $strip_tags);
        $profile = null;

        if (!$user_id) {
            $user_id = $_SESSION['uid'];
            if (isset($_SESSION['profile'])) {
                $profile = $_SESSION['profile'];
            }
        } else {
            $user_id = sprintf('%d', $user_id);
            $prefs_cache = false;
        }

        $profile = \SmallSmallRSS\Database::quoteString($value, $strip_tags);
        if ($profile) {
            $profile_qpart = "AND profile = $profile";
        } else {
            $profile_qpart = 'AND profile IS NULL';
        }

        if (\SmallSmallRSS\Sanity::getSchemaVersion() < 63) {
            $profile_qpart = '';
        }

        $type_name = '';
        $current_value = '';

        if (isset(self::$cache[$user_id][$pref_name])) {
            $type_name = self::$cache[$user_id][$pref_name]['type'];
            $current_value = self::$cache[$user_id][$pref_name]['value'];
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

            if (\SmallSmallRSS\Database::numRows($result) > 0) {
                $type_name = \SmallSmallRSS\Database::fetchResult($result, 0, 'type_name');
            }
        } elseif ($current_value == $value) {
            return;
        }

        if ($type_name) {
            if ($type_name == 'bool') {
                if ($value == '1' || $value == 'true') {
                    $value = 'true';
                } else {
                    $value = 'false';
                }
            } elseif ($type_name == 'integer') {
                $value = sprintf('%d', $value);
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
                     AND owner_uid = " . $user_id
            );
            self::cachePref($user_id, $pref_name, $type_name, $value);
        }
    }
}
