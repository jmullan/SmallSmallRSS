<?php
namespace SmallSmallRSS;

class Filters
{
    public static function getEnabled($owner_uid)
    {
        $result = \SmallSmallRSS\Database::query(
            "SELECT * FROM ttrss_filters2
             WHERE
                 owner_uid = $owner_uid
                 AND enabled = true
             ORDER BY order_id, title"
        );
        $filters = array();
        while (($line = \SmallSmallRSS\Database::fetch_assoc($result))) {
            $filters[] = $line;
        }
        return $filters;
    }
}
