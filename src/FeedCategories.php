<?php
namespace SmallSmallRSS;

class FeedCategories
{
    const NONE = 0;
    const SPECIAL = -1;
    const LABELS = -2;

    public static function getTitle($cat_id)
    {
        if ($cat_id == self::SPECIAL) {
            return __('Special');
        } elseif ($cat_id == self::LABELS) {
            return __('Labels');
        } else {
            $result = \SmallSmallRSS\Database::query(
                "SELECT title
                 FROM ttrss_feed_categories
                 WHERE id = '$cat_id'"
            );
            if (\SmallSmallRSS\Database::num_rows($result) == 1) {
                return \SmallSmallRSS\Database::fetch_result($result, 0, 'title');
            } else {
                return __('Uncategorized');
            }
        }
    }

    public static function get($owner_uid, $feed_cat, $parent_cat_id = false)
    {
        if ($parent_cat_id) {
            $parent_qpart = "parent_cat = '$parent_cat_id'";
        } else {
            $parent_qpart = 'parent_cat IS NULL';
        }

        $result = \SmallSmallRSS\Database::query(
            "SELECT id
             FROM ttrss_feed_categories
             WHERE
                 $parent_qpart
                 AND title = '$feed_cat'
                 AND owner_uid = $owner_uid"
        );
        if (\SmallSmallRSS\Database::num_rows($result) == 0) {
            return false;
        } else {
            return \SmallSmallRSS\Database::fetch_result($result, 0, 'id');
        }
    }

    public static function add($owner_uid, $feed_cat, $parent_cat_id = false)
    {
        if (!$feed_cat) {
            return false;
        }

        \SmallSmallRSS\Database::query('BEGIN');

        if ($parent_cat_id) {
            $parent_qpart = "parent_cat = '$parent_cat_id'";
            $parent_insert = "'$parent_cat_id'";
        } else {
            $parent_qpart = 'parent_cat IS NULL';
            $parent_insert = 'NULL';
        }

        $feed_cat = mb_substr($feed_cat, 0, 250);

        $result = \SmallSmallRSS\Database::query(
            "SELECT id
             FROM ttrss_feed_categories
             WHERE
                 $parent_qpart
                 AND title = '$feed_cat'
                 AND owner_uid = $owner_uid"
        );

        if (\SmallSmallRSS\Database::num_rows($result) == 0) {

            $result = \SmallSmallRSS\Database::query(
                "INSERT INTO ttrss_feed_categories (owner_uid,title,parent_cat)
                VALUES ('$owner_uid', '$feed_cat', $parent_insert)"
            );
            \SmallSmallRSS\Database::query('COMMIT');
            return true;
        }
        return false;
    }

    public static function getChildrenForOPML($parent_cat_id, $owner_uid)
    {

        if ($parent_cat_id) {
            $cat_qpart = "parent_cat = '$parent_cat_id'";
        } else {
            $cat_qpart = 'parent_cat IS NULL';
        }
        $result = \SmallSmallRSS\Database::query(
            "SELECT id
             FROM ttrss_feed_categories
             WHERE
                 $cat_qpart
                 AND owner_uid = '$owner_uid'
             ORDER BY order_id"
        );
        $cat_ids = array();
        while ($line = \SmallSmallRSS\Database::fetch_assoc($result)) {
            $cat_ids[] = $line['id'];
        }
    }

    public static function getChildrenForSelect($root_id, $owner_uid)
    {
        if ($root_id) {
            $parent_qpart = "parent_cat = '$root_id'";
        } else {
            $parent_qpart = 'parent_cat IS NULL';
        }
        $result = \SmallSmallRSS\Database::query(
            "SELECT
                 id,
                 title,
                 (
                      SELECT COUNT(id)
                      FROM ttrss_feed_categories AS c2
                      WHERE c2.parent_cat = ttrss_feed_categories.id
                 ) AS num_children
             FROM ttrss_feed_categories
             WHERE
                 owner_uid = $owner_uid
                 AND $parent_qpart
             ORDER BY title ASC"
        );
        $children = array();
        while (($line = \SmallSmallRSS\Database::fetch_assoc($result))) {
            $children[] = $line;
        }
        return $children;
    }

    public static function getParents($cat, $owner_uid)
    {
        if (!$cat) {
            return array();
        }
        $result = \SmallSmallRSS\Database::query(
            "SELECT parent_cat
             FROM ttrss_feed_categories
             WHERE
                  id = '$cat'
                  AND parent_cat IS NOT NULL
                  AND owner_uid = $owner_uid"
        );
        $parents = array();
        while (($line = \SmallSmallRSS\Database::fetch_assoc($result))) {
            $parents[] = $line['parent_cat'];
        }
        return $parents;
    }

    public static function getChildren($cat_ids, $owner_uid)
    {
        $ids = array();
        if (is_array($cat_ids)) {
            if (!$cat_ids) {
                return $ids;
            }
            $qpart = "parent_cat IN($escaped_cat_ids)";
        } else {
            $qpart = "parent_cat = '$cat'";
        }
        $result = \SmallSmallRSS\Database::query(
            "SELECT id
             FROM ttrss_feed_categories
             WHERE
                 $qpart
                 AND owner_uid = $owner_uid"
        );
        while (($line = \SmallSmallRSS\Database::fetch_assoc($result))) {
            $ids[] = $line['id'];
        }
        return $ids;
    }

    public static function getDescendents($cat_ids, $owner_uid, $seen = array())
    {
        # TODO: make this more efficient
        $seen = array_merge($seen, $cat_ids);
        $descendents = self::getChildren($cat_ids, $owner_uid);
        $grandchildren = array_diff($descendents, $seen);
        $seen = array_merge($seen, $grandchildren);
        if ($grandchildren) {
            $descendents = array_merge($descendents, self::getDescendents($grandchildren, $owner_uid, $seen));
        }
        return array_unique($descendents);
    }

    public static function getAncestors($cat, $owner_uid)
    {
        # TODO: make this more efficient
        $parents = self::getParents($cat, $owner_uid);
        $ancestors = $parents;
        foreach ($parents as $parent_id) {
            $ancestors = array_merge($ancestors, self::getAncestors($parent_id, $owner_uid));
        }
        return array_unique($ancestors);
    }

    public static function getWithChildCounts($enable_nested, $owner_uid)
    {
        // TODO do not return empty categories, return Uncategorized and standard virtual cats
        if ($enable_nested) {
            $nested_qpart = 'AND parent_cat IS NULL';
        } else {
            $nested_qpart = '';
        }
        $result = \SmallSmallRSS\Database::query(
            "SELECT
                 id,
                 title,
                 order_id,
                 (
                     SELECT COUNT(id)
                     FROM ttrss_feeds
                     WHERE
                         ttrss_feed_categories.id IS NOT NULL
                         AND cat_id = ttrss_feed_categories.id
                 ) AS num_feeds,
                 (
                     SELECT COUNT(id)
                     FROM ttrss_feed_categories AS c2
                     WHERE c2.parent_cat = ttrss_feed_categories.id
                 ) AS num_cats
             FROM ttrss_feed_categories
             WHERE
                 owner_uid = $owner_uid
                 $nested_qpart
            "
        );
        $cats = array();
        while ($line = \SmallSmallRSS\Database::fetch_assoc($result)) {
            $cats[] = $line;
        }
        return $cats;
    }
}
