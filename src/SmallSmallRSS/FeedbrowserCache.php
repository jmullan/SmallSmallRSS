<?php
namespace SmallSmallRSS;

class FeedbrowserCache
{
    public function update($lines)
    {
        \SmallSmallRSS\Database::query('BEGIN');
        \SmallSmallRSS\Database::query('DELETE FROM ttrss_feedbrowser_cache');
        $count = 0;
        foreach ($lines as $line) {
            $subscribers = \SmallSmallRSS\Database::escape_string($line['subscribers']);
            $feed_url = \SmallSmallRSS\Database::escape_string($line['feed_url']);
            $title = \SmallSmallRSS\Database::escape_string($line['title']);
            $site_url = \SmallSmallRSS\Database::escape_string($line['site_url']);
            $tmp_result = \SmallSmallRSS\Database::query(
                "SELECT subscribers
                 FROM ttrss_feedbrowser_cache
                 WHERE feed_url = '$feed_url'"
            );
            if (\SmallSmallRSS\Database::num_rows($tmp_result) == 0) {
                \SmallSmallRSS\Database::query(
                    "INSERT INTO ttrss_feedbrowser_cache
                     (feed_url, site_url, title, subscribers)
                     VALUES ('$feed_url', '$site_url', '$title', '$subscribers')"
                );
                $count += 1;
            }
        }
        \SmallSmallRSS\Database::query('COMMIT');
        return $count;
    }
}
