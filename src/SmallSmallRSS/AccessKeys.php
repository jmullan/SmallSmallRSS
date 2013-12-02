<?php
namespace SmallSmallRSS;

class AccessKeys
{
    public static function delete($feed_id, $owner_uid)
    {
        \SmallSmallRSS\Database::query(
            "DELETE FROM ttrss_access_keys
             WHERE
                 feed_id = '$feed_id'
                 AND owner_uid = $owner_uid"
        );
    }

    public static function getForFeed($feed_id, $is_cat, $owner_uid)
    {
        $sql_is_cat = \SmallSmallRSS\Database::toSQLBool($is_cat);
        $result = \SmallSmallRSS\Database::query(
            "SELECT access_key FROM ttrss_access_keys
            WHERE feed_id = '$feed_id' AND is_cat = $sql_is_cat
            AND owner_uid = $owner_uid"
        );
        if (\SmallSmallRSS\Database::num_rows($result) == 1) {
            return \SmallSmallRSS\Database::fetch_result($result, 0, 'access_key');
        } else {
            $key = \SmallSmallRSS\Database::escape_string(sha1(uniqid(rand(), true)));
            $result = \SmallSmallRSS\Database::query(
                "INSERT INTO ttrss_access_keys
                (access_key, feed_id, is_cat, owner_uid)
                VALUES ('$key', '$feed_id', $sql_is_cat, '$owner_uid')"
            );
            return $key;
        }
    }
    public static function clearForOwner($owner_uid)
    {
        \SmallSmallRSS\Database::query(
            "DELETE FROM ttrss_access_keys
             WHERE owner_uid = $owner_uid"
        );
    }
    public static function getOwner($key)
    {
        $key = \SmallSmallRSS\Database::escape_string($key);
        $result = \SmallSmallRSS\Database::query(
            "SELECT owner_uid
             FROM ttrss_access_keys
             WHERE
                 access_key = '$key'
                 AND feed_id = 'OPML:Publish'"
        );
        if (\SmallSmallRSS\Database::num_rows($result) == 1) {
            return \SmallSmallRSS\Database::fetch_result($result, 0, 'owner_uid');
        }
        return false;
    }
}
