<?php
namespace SmallSmallRSS;

class Entries
{
    public static function purgeOrphans()
    {
        // purge orphaned posts in main content table
        \SmallSmallRSS\Database::query(
            'DELETE FROM ttrss_entries
             WHERE (
                 SELECT COUNT(int_id)
                 FROM ttrss_user_entries
                 WHERE ref_id = id
             ) = 0'
        );

    }
    public static function insert($title, $guid, $url, $content)
    {
        $title = \SmallSmallRSS\Database::escapeString($title);
        $guid = \SmallSmallRSS\Database::escapeString($guid);
        $url = \SmallSmallRSS\Database::escapeString($url);
        $content_hash = sha1($content);
        $content = \SmallSmallRSS\Database::escapeString($content);

        $result = \SmallSmallRSS\Database::query(
            "INSERT INTO ttrss_entries
             (title, guid, link, updated, content, content_hash, date_entered, date_updated)
             VALUES
             ('$title', '$guid', '$url', NOW(), '$content', '$content_hash', NOW(), NOW())"
        );
        $result = \SmallSmallRSS\Database::query("SELECT id FROM ttrss_entries WHERE guid = '$guid'");
        if (\SmallSmallRSS\Database::numRows($result) != 0) {
            return \SmallSmallRSS\Database::fetchResult($result, 0, 'id');
        } else {
            return false;
        }

    }

    public static function updateContent($ref_id, $content)
    {
        $content_hash = sha1($content);
        $content = \SmallSmallRSS\Database::escapeString($content);
        \SmallSmallRSS\Database::query(
            "UPDATE ttrss_entries
             SET
                 content = '$content',
                 content_hash = '$content_hash'
             WHERE id = '$ref_id'"
        );

    }
}
