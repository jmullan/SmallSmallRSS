namespace SmallSmallRSS;

class Tags
{
    function clearExpired($days = 14)
    {
        $limit = 50000;
        if (\SmallSmallRSS\Config::get('DB_TYPE') == "pgsql") {
            $before_date = "NOW() - INTERVAL '$days days'";
        } elseif (\SmallSmallRSS\Config::get('DB_TYPE') == "mysql") {
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
                $ids = join(",", $ids);
                $tmp_result = \SmallSmallRSS\Database::query("DELETE FROM ttrss_tags WHERE id IN ($ids)");
                $tags_deleted += \SmallSmallRSS\Database::affected_rows($tmp_result);
            } else {
                break;
            }
            $limit -= $limit_part;
        }
        return $tags_deleted;
    }

}