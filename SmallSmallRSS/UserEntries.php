<?php
namespace SmallSmallRSS;

class UserEntries
{
    public static function getArticleFeed($id, $owner_uid)
    {
        $result = \SmallSmallRSS\Database::query(
            "SELECT feed_id
             FROM ttrss_user_entries
             WHERE
                 ref_id = '$id'
                 AND owner_uid = $owner_uid"
        );
        if (\SmallSmallRSS\Database::num_rows($result) != 0) {
            return \SmallSmallRSS\Database::fetch_result($result, 0, "feed_id");
        } else {
            return 0;
        }
    }
    public static function getLastId($owner_uid)
    {
        $result = \SmallSmallRSS\Database::query(
            "SELECT MAX(ref_id) AS id FROM ttrss_user_entries
             WHERE owner_uid = $owner_uid"
        );
        if (\SmallSmallRSS\Database::num_rows($result) == 1) {
            return \SmallSmallRSS\Database::fetch_result($result, 0, "id");
        } else {
            return -1;
        }
    }
}
