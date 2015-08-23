<?php
namespace SmallSmallRSS;

class Filters
{
    public static function getEnabled($owner_uid)
    {
        $owner_uid = \SmallSmallRSS\Database::escapeString($owner_uid);
        $result = \SmallSmallRSS\Database::query(
            "SELECT * FROM ttrss_filters2
             WHERE
                 owner_uid = $owner_uid
                 AND enabled = true
             ORDER BY order_id, title"
        );
        $filters = array();
        while (($line = \SmallSmallRSS\Database::fetchAssoc($result))) {
            $filters[] = $line;
        }
        return $filters;
    }
    public static function resetFileters($owner_uid)
    {
        $owner_uid = \SmallSmallRSS\Database::escapeString($owner_uid);
        \SmallSmallRSS\Database::query(
            "UPDATE ttrss_filters2
             SET order_id = 0
             WHERE owner_uid = $owner_uid"
        );

    }
    public static function updateOrder($index, $filter_id, $owner_uid)
    {
        $owner_uid = \SmallSmallRSS\Database::escapeString($owner_uid);
        $filter_id = (int) $filter_id;
        $index = (int) $index;
        \SmallSmallRSS\Database::query(
            "UPDATE ttrss_filters2
             SET
                 order_id = $index
             WHERE
                 id = '$filter_id'
                 AND owner_uid = $owner_uid"
        );
    }
    public static function update($filter_id, $enabled, $match_any_rule, $inverse, $title, $owner_uid)
    {
        $filter_id = (int) $filter_id;

        $enabled = \SmallSmallRSS\Database::toSQLBool($enabled);
        $match_any_rule = \SmallSmallRSS\Database::toSQLBool($match_any_rule);
        $inverse = \SmallSmallRSS\Database::toSQLBool($inverse);

        $title = \SmallSmallRSS\Database::escapeString($title);
        $owner_uid = \SmallSmallRSS\Database::escapeString($owner_uid);
        $result = \SmallSmallRSS\Database::query(
            "UPDATE ttrss_filters2
             SET enabled = $enabled,
                 match_any_rule = $match_any_rule,
                 inverse = $inverse,
                 title = '$title'
             WHERE
                 id = '$filter_id'
                 AND owner_uid = $owner_uid"
        );
    }
    public static function deleteIds($filter_ids, $owner_uid)
    {
        if (!$filter_ids) {
            return;
        }
        $filter_ids = join(',', $filter_ids);
        $owner_uid = \SmallSmallRSS\Database::escapeString($owner_uid);
        \SmallSmallRSS\Database::query(
            "DELETE FROM ttrss_filters2
             WHERE
                 id IN ($in_filter_ids)
                 AND owner_uid = $owner_uid"
        );
    }

    public static function add($enabled, $match_any_rule, $inverse, $title, $owner_uid)
    {
        $filter_id = (int) $filter_id;

        $enabled = \SmallSmallRSS\Database::toSQLBool($enabled);
        $match_any_rule = \SmallSmallRSS\Database::toSQLBool($match_any_rule);
        $inverse = \SmallSmallRSS\Database::toSQLBool($inverse);

        $title = \SmallSmallRSS\Database::escapeString($title);
        $owner_uid = \SmallSmallRSS\Database::escapeString($owner_uid);

        $result = \SmallSmallRSS\Database::query(
            "INSERT INTO ttrss_filters2
            (owner_uid, match_any_rule, enabled, title)
            VALUES
            ($owner_uid, $match_any_rule, $enabled, '$title')"
        );
        $result = \SmallSmallRSS\Database::query(
            "SELECT
                 MAX(id) AS max_id
             FROM ttrss_filters2
             WHERE owner_uid = $owner_uid"
        );
        return \SmallSmallRSS\Database::fetchResult($result, 0, 'max_id');

    }
}
