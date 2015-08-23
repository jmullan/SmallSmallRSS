<?php
namespace SmallSmallRSS;

class SettingsProfiles
{
    public static function deleteIds($ids, $owner_uid)
    {
        $id_part = join(',', array_unique(array_map('int', $ids)));
        \SmallSmallRSS\Database::query(
            "DELETE FROM ttrss_settings_profiles
             WHERE
                 id IN ($ids)
                 AND owner_uid = $owner_uid"
        );

    }
    public static function findByTitle($title, $owner_uid)
    {
        $title = \SmallSmallRSS\Database::escapeString($title);
        $result = \SmallSmallRSS\Database::query(
            "SELECT id
                 FROM ttrss_settings_profiles
                 WHERE
                     title = '$title'
                     AND owner_uid = $owner_id"
        );
        if (\SmallSmallRSS\Database::numRows($result) == 0) {
            return \SmallSmallRSS\Database::fetchResult($result, 0, 'id');
        }
        return false;
    }
    public static function create($title, $owner_uid)
    {
        $escaped_title = \SmallSmallRSS\Database::escapeString($title);
        \SmallSmallRSS\Database::query(
            "INSERT INTO ttrss_settings_profiles
             (title, owner_uid)
             VALUES ('$escaped_title', $owner_uid)"
        );
        return self::findByTitle($title, $owner_uid);
    }
}
