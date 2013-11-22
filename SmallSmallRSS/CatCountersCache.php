<?php
namespace SmallSmallRSS;

class CatCountersCache
{
    public static function cleanup($owner_uid)
    {
        \SmallSmallRSS\Database::query(
            "DELETE FROM ttrss_cat_counters_cache
             WHERE
                 owner_uid = $owner_uid
                 AND (
                     SELECT COUNT(id)
                     FROM ttrss_feed_categories
                     WHERE ttrss_feed_categories.id = feed_id
                 ) = 0"
        );
    }
}
