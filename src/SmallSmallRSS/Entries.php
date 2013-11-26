<?php
namespace SmallSmallRSS;

class Entries
{
    public static function purgeOrphans()
    {
        // purge orphaned posts in main content table
        \SmallSmallRSS\Database::query(
            "DELETE FROM ttrss_entries
             WHERE (
                 SELECT COUNT(int_id)
                 FROM ttrss_user_entries
                 WHERE ref_id = id
             ) = 0"
        );

    }
}
