<?php
namespace SmallSmallRSS;

class Tags
{
    public static function clearExpired($days = 14)
    {
        $limit = 50000;
        if (\SmallSmallRSS\Config::get('DB_TYPE') == 'pgsql') {
            $before_date = "NOW() - INTERVAL '$days days'";
        } elseif (\SmallSmallRSS\Config::get('DB_TYPE') == 'mysql') {
            $before_date = "DATE_SUB(NOW(), INTERVAL $days DAY)";
        }
        $tags_deleted = 0;
        while ($limit > 0) {
            $limit_part = 500;
            $query = "SELECT ttrss_tags.id AS id
                      FROM ttrss_tags, ttrss_user_entries, ttrss_entries
                      WHERE
                          post_int_id = int_id
                          AND date_updated < $before_date
                          AND ref_id = ttrss_entries.id AND tag_cache != ''
                      ORDER BY date_updated ASC
                      LIMIT $limit_part";
            $result = \SmallSmallRSS\Database::query($query);
            $ids = array();
            while ($line = \SmallSmallRSS\Database::fetch_assoc($result)) {
                array_push($ids, $line['id']);
            }
            if (count($ids) > 0) {
                $ids = join(',', $ids);
                $tmp_result = \SmallSmallRSS\Database::query("DELETE FROM ttrss_tags WHERE id IN ($ids)");
                $tags_deleted += \SmallSmallRSS\Database::affected_rows($tmp_result);
            } else {
                break;
            }
            $limit -= $limit_part;
        }
        return $tags_deleted;
    }
    public static function isValidUTF8($string)
    {
        return (
            preg_match('/^.{1}/usS', $string)
            && !preg_match("/[\xC0\xC1\xF5-\xFF]/S", $string)
        );
    }

    public static function isValid($tag)
    {
        if ($tag == '') {
            return false;
        }
        if (preg_match("/^[0-9]*$/", $tag)) {
            return false;
        }
        if (!self::isValidUTF8($tag)) {
            return false;
        }
        if (mb_strlen($tag) > 250) {
            return false;
        }
        if (!$tag) {
            return false;
        }
        return true;
    }
    public static function getForRefId($ref_id, $owner_uid)
    {
        $tags = array();
        /* do it the hard way */
        $query = "SELECT DISTINCT tag_name
                  FROM
                      ttrss_tags
                  WHERE post_int_id = (
                      SELECT int_id
                      FROM ttrss_user_entries
                      WHERE
                          ref_id = '$ref_id'
                          AND owner_uid = '$owner_uid'
                      LIMIT 1
                  )
                  ORDER BY tag_name";
        $result = \SmallSmallRSS\Database::query($query);
        while (($line = \SmallSmallRSS\Database::fetch_assoc($result))) {
            $tags[] = $line['tag_name'];
        }
        return $tags;
    }
    public static function deleteForPost($post_int_id, $owner_uid)
    {
        $post_int_id = \SmallSmallRSS\Database::escape_string($post_int_id);
        $owner_uid = \SmallSmallRSS\Database::escape_string($owner_uid);
        \SmallSmallRSS\Database::query(
            "DELETE FROM ttrss_tags
             WHERE
                 post_int_id = $post_int_id
                 AND owner_uid = '$owner_uid'"
        );

    }
    public static function postHas($post_int_id, $owner_uid, $tag)
    {
        $result = \SmallSmallRSS\Database::query(
            "SELECT id FROM ttrss_tags
             WHERE
                 tag_name = '$tag'
                 AND post_int_id = '$entry_int_id'
                 AND owner_uid = '$owner_uid'
             LIMIT 1"
        );
        return $result && \SmallSmallRSS\Database::num_rows($result);

    }
    public static function setForPost($post_int_id, $owner_uid, $tags)
    {
        $filtered_tags = array();
        $escaped_tags = array();
        foreach ($tags as $tag) {
            $tag = self::sanitize($tag);
            if (!self::isValid($tag)) {
                continue;
            }
            $filtered_tags[] = $tag;
            $escaped_tags[] = \SmallSmallRSS\Database::escape_string($tag);
        }
        ob_start();
        echo "INSERT INTO ttrss_tags (post_int_id, owner_uid, tag_name)";
        foreach ($escaped_tags as $tag) {
            echo "VALUES ('$post_int_id', '$owner_uid', '$tag')";
        }
        $query = ob_get_clean();
        \SmallSmallRSS\Database::query($query);
        return $filtered_tags;
    }
    public static function sanitize($tag)
    {
        $tag = trim($tag);
        $tag = mb_strtolower($tag, 'utf-8');
        $tag = preg_replace('/[\'\"\+\>\<]/', '', $tag);
        $tag = str_replace('technorati tag: ', '', $tag);
        return $tag;
    }
}
