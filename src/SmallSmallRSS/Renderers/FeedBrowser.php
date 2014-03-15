<?php
namespace SmallSmallRSS\Renderers;

class FeedBrowser extends \SmallSmallRSS\Renderers\Base
{
    const MODE_1 = 1;
    const MODE_2 = 2;

    public function __construct($search, $limit, $mode = self::MODE_1)
    {
        $this->search = $search;
        $this->limit = $limit;
        $this->mode = $mode;
    }

    public function render()
    {
        $owner_uid = $_SESSION['uid'];
        $limit = $this->limit;

        if ($this->search) {
            $search = $this->search;
            $search_qpart = "
                AND (
                    UPPER(feed_url) LIKE UPPER('%$search%')
                    OR UPPER(title) LIKE UPPER('%$search%')
                )";
        } else {
            $search_qpart = '';
        }
        if ($this->mode == self::MODE_1) {
            $result = \SmallSmallRSS\Database::query(
                "SELECT feed_url, site_url, title, SUM(subscribers) AS subscribers
                 FROM (
                     SELECT feed_url, site_url, title, subscribers
                     FROM ttrss_feedbrowser_cache
                     UNION ALL
                     SELECT feed_url, site_url, title, subscribers
                     FROM ttrss_linked_feeds
                 ) AS qqq
                 WHERE (
                     SELECT COUNT(id) = 0 FROM ttrss_feeds AS tf
                     WHERE
                         tf.feed_url = qqq.feed_url
                         AND owner_uid = '$owner_uid'
                 ) $search_qpart
                 GROUP BY feed_url, site_url, title
                 ORDER BY subscribers DESC
                 LIMIT $limit"
            );
        } elseif ($this->mode == self::MODE_2) {
            $result = \SmallSmallRSS\Database::query(
                "SELECT
                 *,
                 (
                     SELECT COUNT(*)
                     FROM ttrss_user_entries
                     WHERE
                         orig_feed_id = ttrss_archived_feeds.id
                 ) AS articles_archived
                 FROM
                     ttrss_archived_feeds
                 WHERE
                     owner_uid = '$owner_uid'
                     AND (
                         SELECT COUNT(*)
                         FROM ttrss_feeds
                         WHERE
                             ttrss_feeds.feed_url = ttrss_archived_feeds.feed_url
                             AND owner_uid = '$owner_uid'
                     ) = 0
                 $search_qpart
                 ORDER BY id DESC
                 LIMIT $limit"
            );
        }

        $feedctr = 0;
        $lines = array();
        while ($line = \SmallSmallRSS\Database::fetch_assoc($result)) {
            $lines[] = $line;
        }
        $params = array('lines' => $lines);
        if (!$lines) {
            echo '<li style="text-align: center">';
            echo '<p>';
            echo __('No feeds found.');
            echo '</p>';
            echo '</li>';
        } elseif ($this->mode == self::MODE_1) {
            $this->renderPHP(__DIR__ . '/templates/feedbrowser_1.php', $params);
        } else {
            $this->renderPHP(__DIR__ . '/templates/feedbrowser_2.php', $params);
        }
    }
}
