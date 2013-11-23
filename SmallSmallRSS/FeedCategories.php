<?php
namespace SmallSmallRSS;

class FeedCategories
{
    public static function get($feed_cat, $parent_cat_id = false)
    {
        if ($parent_cat_id) {
            $parent_qpart = "parent_cat = '$parent_cat_id'";
            $parent_insert = "'$parent_cat_id'";
        } else {
            $parent_qpart = "parent_cat IS NULL";
            $parent_insert = "NULL";
        }

        $result = \SmallSmallRSS\Database::query(
            "SELECT id FROM ttrss_feed_categories
             WHERE $parent_qpart AND title = '$feed_cat' AND owner_uid = ".$_SESSION["uid"]
        );
        if (\SmallSmallRSS\Database::num_rows($result) == 0) {
            return false;
        } else {
            return \SmallSmallRSS\Database::fetch_result($result, 0, "id");
        }
    }

    public static function add($feed_cat, $parent_cat_id = false)
    {
        if (!$feed_cat) {
            return false;
        }

        \SmallSmallRSS\Database::query("BEGIN");

        if ($parent_cat_id) {
            $parent_qpart = "parent_cat = '$parent_cat_id'";
            $parent_insert = "'$parent_cat_id'";
        } else {
            $parent_qpart = "parent_cat IS NULL";
            $parent_insert = "NULL";
        }

        $feed_cat = mb_substr($feed_cat, 0, 250);

        $result = \SmallSmallRSS\Database::query(
            "SELECT id FROM ttrss_feed_categories
            WHERE $parent_qpart AND title = '$feed_cat' AND owner_uid = ".$_SESSION["uid"]
        );

        if (\SmallSmallRSS\Database::num_rows($result) == 0) {

            $result = \SmallSmallRSS\Database::query(
                "INSERT INTO ttrss_feed_categories (owner_uid,title,parent_cat)
                VALUES ('".$_SESSION["uid"]."', '$feed_cat', $parent_insert)"
            );
            \SmallSmallRSS\Database::query("COMMIT");
            return true;
        }
        return false;
    }
}
