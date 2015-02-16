<?php
namespace SmallSmallRSS;

class UserEntries
{
    public static function getArticleFeed($ref_id, $owner_uid)
    {
        $result = \SmallSmallRSS\Database::query(
            "SELECT feed_id
             FROM ttrss_user_entries
             WHERE
                 ref_id = '$ref_id'
                 AND owner_uid = $owner_uid"
        );
        if (\SmallSmallRSS\Database::num_rows($result) != 0) {
            $row = \SmallSmallRSS\Database::fetch_assoc($result);
            if (isset($row['feed_id'])) {
                return $row['feed_id'];
            }
        }
        return 0;
    }
    public static function getLastId($owner_uid)
    {
        $result = \SmallSmallRSS\Database::query(
            "SELECT MAX(ref_id) AS id
             FROM ttrss_user_entries
             WHERE owner_uid = $owner_uid"
        );
        if (\SmallSmallRSS\Database::num_rows($result) == 1) {
            $row = \SmallSmallRSS\Database::fetch_assoc($result);
            if (isset($row['id'])) {
                return $row['id'];
            }
        }
        return -1;
    }

    public static function catchupFeed($feed_id, $cat_view, $owner_uid, $max_id = false, $mode = 'all')
    {
        // Todo: all this interval stuff needs some generic generator function
        $date_qpart = 'false';
        switch ($mode) {
            case '1day':
                if (\SmallSmallRSS\Config::get('DB_TYPE') == 'pgsql') {
                    $date_qpart = "date_entered < NOW() - INTERVAL '1 day' ";
                } else {
                    $date_qpart = 'date_entered < DATE_SUB(NOW(), INTERVAL 1 DAY) ';
                }
                break;
            case '1week':
                if (\SmallSmallRSS\Config::get('DB_TYPE') == 'pgsql') {
                    $date_qpart = "date_entered < NOW() - INTERVAL '1 week' ";
                } else {
                    $date_qpart = 'date_entered < DATE_SUB(NOW(), INTERVAL 1 WEEK) ';
                }
                break;
            case '2weeks':
                if (\SmallSmallRSS\Config::get('DB_TYPE') == 'pgsql') {
                    $date_qpart = "date_entered < NOW() - INTERVAL '2 week' ";
                } else {
                    $date_qpart = 'date_entered < DATE_SUB(NOW(), INTERVAL 2 WEEK) ';
                }
                break;
            default:
                $date_qpart = 'true';
        }

        if (is_numeric($feed_id)) {
            if ($cat_view) {
                if ($feed_id >= 0) {
                    if ($feed_id > 0) {
                        $children = \SmallSmallRSS\FeedCategories::getDescendents($feed_id, $owner_uid);
                        array_push($children, $feed_id);
                        $children = join(',', $children);
                        $cat_qpart = "cat_id IN ($children)";
                    } else {
                        $cat_qpart = 'cat_id IS NULL';
                    }
                    \SmallSmallRSS\Database::query(
                        "UPDATE ttrss_user_entries
                         SET unread = false, last_read = NOW()
                         WHERE ref_id IN (
                             SELECT id FROM (
                                 SELECT id
                                 FROM ttrss_entries, ttrss_user_entries
                                 WHERE
                                     ref_id = id
                                     AND owner_uid = $owner_uid
                                     AND unread = true
                                     AND feed_id IN (
                                         SELECT id
                                             FROM ttrss_feeds
                                             WHERE $cat_qpart
                                     )
                                 AND $date_qpart
                             ) as tmp)"
                    );
                } elseif ($feed_id == -2) {
                    \SmallSmallRSS\Database::query(
                        "UPDATE ttrss_user_entries
                         SET unread = false, last_read = NOW()
                         WHERE
                             unread = true
                             AND $date_qpart
                             AND owner_uid = $owner_uid
                             AND (
                                 SELECT COUNT(*)
                                 FROM ttrss_user_labels2
                                 WHERE
                                     article_id = ref_id
                                     AND id = ref_id
                                     AND $date_qpart
                             ) > 0"
                    );
                }
            } elseif ($feed_id > 0) {
                \SmallSmallRSS\Database::query(
                    "UPDATE ttrss_user_entries
                 SET unread = false, last_read = NOW()
                 WHERE ref_id IN (
                     SELECT id
                     FROM (
                         SELECT id
                         FROM ttrss_entries, ttrss_user_entries
                         WHERE
                             ref_id = id
                             AND owner_uid = $owner_uid
                             AND unread = true
                             AND feed_id = $feed_id
                             AND $date_qpart
                     ) as tmp
                 )"
                );
            } elseif ($feed_id < 0 && $feed_id > \SmallSmallRSS\Constants::LABEL_BASE_INDEX) {
                // special, like starred
                if ($feed_id == -1) {
                    \SmallSmallRSS\Database::query(
                        "UPDATE ttrss_user_entries
                     SET unread = false, last_read = NOW()
                     WHERE
                         ref_id IN (
                             SELECT id
                             FROM (
                                 SELECT id
                                 FROM ttrss_entries, ttrss_user_entries
                                 WHERE
                                     ref_id = id
                                     AND owner_uid = $owner_uid
                                     AND unread = true
                                     AND marked = true
                                     AND $date_qpart
                             ) as tmp
                         )"
                    );
                }
                if ($feed_id == -2) {
                    \SmallSmallRSS\Database::query(
                        "UPDATE ttrss_user_entries
                     SET unread = false, last_read = NOW()
                     WHERE ref_id IN (
                         SELECT id
                         FROM (
                             SELECT id
                             FROM ttrss_entries, ttrss_user_entries
                             WHERE
                                 ref_id = id
                                 AND owner_uid = $owner_uid
                                 AND unread = true
                                 AND published = true
                                 AND $date_qpart
                         ) as tmp
                     )"
                    );
                }
                if ($feed_id == -3) {
                    $intl = \SmallSmallRSS\DBPrefs::read('FRESH_ARTICLE_MAX_AGE', $owner_uid);
                    if (\SmallSmallRSS\Config::get('DB_TYPE') == 'pgsql') {
                        $match_part = "date_entered > NOW() - INTERVAL '$intl hour' ";
                    } else {
                        $match_part = "date_entered > DATE_SUB(NOW(), INTERVAL $intl HOUR) ";
                    }
                    \SmallSmallRSS\Database::query(
                        "UPDATE ttrss_user_entries
                     SET unread = false, last_read = NOW()
                     WHERE ref_id IN (
                         SELECT id FROM (
                             SELECT id
                             FROM ttrss_entries, ttrss_user_entries
                             WHERE
                                 ref_id = id
                                 AND owner_uid = $owner_uid
                                 AND unread = true
                                 AND $date_qpart
                                 AND $match_part
                         ) as tmp
                     )"
                    );
                }
                if ($feed_id == -4) {
                    \SmallSmallRSS\Database::query(
                        "UPDATE ttrss_user_entries
                     SET unread = false, last_read = NOW()
                     WHERE ref_id IN (
                         SELECT id
                         FROM (
                             SELECT id
                             FROM ttrss_entries, ttrss_user_entries
                             WHERE
                                 ref_id = id
                                 AND owner_uid = $owner_uid
                                 AND unread = true
                                 AND $date_qpart
                         ) as tmp
                     )"
                    );
                }

            } elseif ($feed_id < \SmallSmallRSS\Constants::LABEL_BASE_INDEX) {
                $label_id = \SmallSmallRSS\Labels::fromFeedId($feed_id);
                \SmallSmallRSS\Database::query(
                    "UPDATE ttrss_user_entries
                     SET unread = false, last_read = NOW()
                     WHERE ref_id IN (
                         SELECT id
                         FROM (
                             SELECT ttrss_entries.id
                             FROM ttrss_entries, ttrss_user_entries, ttrss_user_labels2
                             WHERE
                                 ref_id = id
                                 AND label_id = '$label_id'
                                 AND ref_id = article_id
                                 AND owner_uid = $owner_uid AND unread = true AND $date_qpart
                         ) as tmp
                     )"
                );

            }
            \SmallSmallRSS\CountersCache::update($feed_id, $owner_uid, $cat_view);
        } else {
            \SmallSmallRSS\Database::query(
                "UPDATE ttrss_user_entries
                 SET unread = false, last_read = NOW()
                 WHERE ref_id IN (
                     SELECT id FROM (
                         SELECT ttrss_entries.id
                         FROM ttrss_entries, ttrss_user_entries, ttrss_tags
                         WHERE ref_id = ttrss_entries.id
                             AND post_int_id = int_id
                             AND tag_name = '$feed_id'
                             AND ttrss_user_entries.owner_uid = $owner_uid
                             AND unread = true
                             AND $date_qpart
                     ) as tmp
                 )"
            );
        }
    }

    public static function markUnread($ref_id)
    {
        \SmallSmallRSS\Database::query(
            "UPDATE ttrss_user_entries
             SET
                 last_read = null,
                 unread = true
             WHERE ref_id = '$ref_id'"
        );
    }
    public static function markIdsUnread($ref_ids, $owner_uid)
    {
        if (!$ref_ids) {
            return;
        }
        $in_ref_ids = join(', ', $ref_ids);
        \SmallSmallRSS\Database::query(
            "UPDATE ttrss_user_entries
             SET unread = true
             WHERE
                 ref_id IN ($in_ref_ids)
                 AND owner_uid = $owner_uid"
        );
    }
    public static function markIdsRead($ref_ids, $owner_uid)
    {
        if (!$ref_ids) {
            \SmallSmallRSS\Logger::log('No ref ids');
            return;
        }
        $in_ref_ids = join(', ', $ref_ids);
        \SmallSmallRSS\Database::query(
            "UPDATE ttrss_user_entries
             SET
                 unread = false,
                 last_read = NOW()
             WHERE
                 ref_id IN ($in_ref_ids)
                 AND owner_uid = $owner_uid"
        );
    }
    public static function toggleIdsUnread($ref_ids, $owner_uid)
    {
        if (!$ref_ids) {
            return;
        }
        $in_ref_ids = join(', ', $ref_ids);
        \SmallSmallRSS\Database::query(
            "UPDATE ttrss_user_entries
             SET
                 unread = NOT unread,
                 last_read = NOW()
             WHERE
                 ref_id IN ($in_ref_ids)
                 AND owner_uid = $owner_uid"
        );
    }
    public static function countUnread($feed_ids, $owner_uid)
    {
        $unread = 0;
        if ($feed_ids) {
            $in_feed_ids = join(', ', array_map('intval', $feed_ids));
            $result = \SmallSmallRSS\Database::query(
                "SELECT COUNT(int_id) AS unread
                 FROM ttrss_user_entries
                 WHERE
                     unread = true
                     AND feed_id IN($in_feed_ids)
                     AND owner_uid = $owner_uid"
            );
            while (($line = \SmallSmallRSS\Database::fetch_assoc($result))) {
                $unread += $line['unread'];
            }
        }
        return $unread;
    }
    public static function getMatchingFeeds($ref_ids, $owner_uid)
    {
        $in_ref_ids = join(', ', array_map('intval', $ref_ids));
        $result = \SmallSmallRSS\Database::query(
            "SELECT DISTINCT feed_id
             FROM ttrss_user_entries
             WHERE
                 ref_id IN ($in_ref_ids)
                 AND owner_uid = $owner_uid"
        );
        $feed_ids = array();
        while (($line = \SmallSmallRSS\Database::fetch_assoc($result))) {
            $feed_ids[] = $line['feed_id'];
        }
        return $feed_ids;
    }
    public static function getCachedTags($ref_id, $owner_uid)
    {
        $result = \SmallSmallRSS\Database::query(
            "SELECT tag_cache
             FROM ttrss_user_entries
             WHERE
                 ref_id = '$ref_id'
                 AND owner_uid = $owner_uid"
        );
        if ($result) {
            $row = \SmallSmallRSS\Database::fetch_assoc($result);
        }
        if (isset($row['tag_cache'])) {
            return $row['tag_cache'];
        }
        return '';
    }
    public static function setCachedTags($ref_id, $owner_uid, $tags_str)
    {
        $tags_str = \SmallSmallRSS\Database::escape_string($tags_str);
        \SmallSmallRSS\Database::query(
            "UPDATE ttrss_user_entries
             SET tag_cache = '$tags_str'
             WHERE
                 ref_id = '$ref_id'
                 AND owner_uid = $owner_uid"
        );

    }
    public static function markIds($ref_ids, $owner_uid)
    {
        if (!$ref_ids) {
            return;
        }
        $in_ref_ids = join(', ', $ref_ids);
        \SmallSmallRSS\Database::query(
            "UPDATE ttrss_user_entries
             SET marked = true, last_marked = NOW()
             WHERE
                 ref_id IN ($in_ref_ids)
                 AND owner_uid = $owner_uid"
        );
    }

    public static function unmarkIds($ref_ids, $owner_uid)
    {
        if (!$ref_ids) {
            return;
        }
        $in_ref_ids = join(', ', $ref_ids);
        \SmallSmallRSS\Database::query(
            "UPDATE ttrss_user_entries
             SET marked = false, last_marked = NOW()
             WHERE
                 ref_id IN ($in_ref_ids)
                 AND owner_uid = $owner_uid"
        );
    }
    public static function toggleMarkIds($ref_ids, $owner_uid)
    {
        if (!$ref_ids) {
            return;
        }
        $in_ref_ids = join(', ', $ref_ids);
        \SmallSmallRSS\Database::query(
            "UPDATE ttrss_user_entries
             SET marked = NOT marked,
                 last_marked = NOW()
             WHERE
                 ref_id IN ($in_ref_ids)
                 AND owner_uid = $owner_uid"
        );
    }

    public static function publishIds($ref_ids, $owner_uid)
    {
        if (!$ref_ids) {
            return;
        }
        $in_ref_ids = join(', ', $ref_ids);
        \SmallSmallRSS\Database::query(
            "UPDATE ttrss_user_entries
             SET published = true, last_published = NOW()
             WHERE
                 ref_id IN ($in_ref_ids)
                 AND owner_uid = $owner_uid"
        );
    }

    public static function unpublishIds($ref_ids, $owner_uid)
    {
        if (!$ref_ids) {
            return;
        }
        $in_ref_ids = join(', ', $ref_ids);
        \SmallSmallRSS\Database::query(
            "UPDATE ttrss_user_entries
             SET published = false, last_published = NOW()
             WHERE
                 ref_id IN ($in_ref_ids)
                 AND owner_uid = $owner_uid"
        );
    }
    public static function togglePublishIds($ref_ids, $owner_uid)
    {
        if (!$ref_ids) {
            return;
        }
        $in_ref_ids = join(', ', $ref_ids);
        \SmallSmallRSS\Database::query(
            "UPDATE ttrss_user_entries
             SET published = NOT published,
                 last_published = NOW()
             WHERE
                 ref_id IN ($in_ref_ids)
                 AND owner_uid = $owner_uid"
        );
    }
    public static function getIntId($ref_id, $owner_uid)
    {
        $ref_id = \SmallSmallRSS\Database::escape_string($ref_id);
        $owner_uid = \SmallSmallRSS\Database::escape_string($owner_uid);
        $result = \SmallSmallRSS\Database::query(
            "SELECT int_id
             FROM ttrss_user_entries
             WHERE
                 ref_id = '$ref_id'
                 AND owner_uid = '$owner_uid'
             LIMIT 1"
        );
        if (\SmallSmallRSS\Database::num_rows($result) != 0) {
            return \SmallSmallRSS\Database::fetch_result($result, 0, 'int_id');
        } else {
            return null;
        }
    }
    public static function setNote($ref_id, $owner_uid, $note)
    {
        $ref_id = \SmallSmallRSS\Database::escape_string($ref_id);
        $owner_uid = \SmallSmallRSS\Database::escape_string($owner_uid);
        $note = \SmallSmallRSS\Database::escape_string(trim(strip_tags($note)));
        \SmallSmallRSS\Database::query(
            "UPDATE ttrss_user_entries
             SET note = '$note'
             WHERE
                 ref_id = '$ref_id'
                 AND owner_uid = $owner_uid"
        );

    }

    public static function getNote($ref_id, $owner_uid)
    {
        $ref_id = \SmallSmallRSS\Database::escape_string($ref_id);
        $owner_uid = \SmallSmallRSS\Database::escape_string($owner_uid);
        $result = \SmallSmallRSS\Database::query(
            "SELECT note
             FROM ttrss_user_entries
             WHERE
                 ref_id = '$ref_id'
                 AND owner_uid = $owner_uid"
        );
        if (\SmallSmallRSS\Database::num_rows($result) != 0) {
            return \SmallSmallRSS\Database::fetch_result($result, 0, "note");
        } else {
            return '';
        }
    }
    public static function unescapedUpdateFields($owner_uid, $fields, $article_ids)
    {
        $in_article_ids = join(', ', $article_ids);
        $field_pairs = array();
        foreach ($fields as $field => $set_to) {
            $field_pairs[] = "$field = $set_to";
        }
        $field_settings = join(', ', $field_pairs);
        $result = \SmallSmallRSS\Database::query(
            "UPDATE ttrss_user_entries
                 SET
                     $field_settings
                 WHERE
                     ref_id IN ($in_article_ids)
                     AND owner_uid = $owner_uid"
        );
        return \SmallSmallRSS\Database::affected_rows($result);
    }
}
