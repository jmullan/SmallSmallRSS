<?php
namespace SmallSmallRSS;

class Labels
{

    public static function toFeedId($label_id)
    {
        return \SmallSmallRSS\Constants::LABEL_BASE_INDEX - 1 - abs($label);
    }

    public static function fromFeedId($feed_id)
    {
        return \SmallSmallRSS\Constants::LABEL_BASE_INDEX - 1 + abs($feed_id);
    }

    public static function findId($label, $owner_uid)
    {
        $result = \SmallSmallRSS\Database::query(
            "SELECT id
             FROM ttrss_labels2
             WHERE
                 caption = '$label'
                 AND owner_uid = '$owner_uid'
             LIMIT 1"
        );
        if (\SmallSmallRSS\Database::num_rows($result) == 1) {
            return \SmallSmallRSS\Database::fetch_result($result, 0, 'id');
        } else {
            return 0;
        }
    }

    public static function getForArticle($id, $owner_uid)
    {
        $rv = array();
        $result = \SmallSmallRSS\Database::query(
            "SELECT label_cache
             FROM ttrss_user_entries
             WHERE
                 ref_id = '$id'
                 AND owner_uid = '$owner_uid'"
        );
        if (\SmallSmallRSS\Database::num_rows($result) > 0) {
            $label_cache = \SmallSmallRSS\Database::fetch_result($result, 0, 'label_cache');
            if ($label_cache) {
                $label_cache = json_decode($label_cache, true);
                if ($label_cache['no-labels'] == 1) {
                    return $rv;
                } else {
                    return $label_cache;
                }
            }
        }
        $result = \SmallSmallRSS\Database::query(
            "SELECT DISTINCT
                 label_id,
                 caption,
                 fg_color,
                 bg_color
             FROM
                 ttrss_labels2,
                 ttrss_user_labels2
             WHERE
                 id = label_id
                 AND article_id = '$id'
                 AND owner_uid = $owner_uid
             ORDER BY caption"
        );
        while ($line = \SmallSmallRSS\Database::fetch_assoc($result)) {
            $rk = array(
                $line['label_id'],
                $line['caption'],
                $line['fg_color'],
                $line['bg_color']
            );
            array_push($rv, $rk);
        }
        if (count($rv) > 0) {
            self::updateCache($owner_uid, $id, $rv);
        } else {
            self::updateCache($owner_uid, $id, array('no-labels' => 1));
        }
        return $rv;
    }


    public static function findCaption($label, $owner_uid)
    {
        $result = \SmallSmallRSS\Database::query(
            "SELECT caption
             FROM ttrss_labels2
             WHERE
                 id = '$label'
                 AND owner_uid = '$owner_uid'
             LIMIT 1"
        );
        if (\SmallSmallRSS\Database::num_rows($result) == 1) {
            return \SmallSmallRSS\Database::fetch_result($result, 0, 'caption');
        } else {
            return '';
        }
    }

    public static function updateCache($owner_uid, $id, $labels = false, $force = false)
    {
        if ($force) {
            self::clearCache($id);
        }
        if (!$labels) {
            $labels = self::getForArticle($id, $owner_uid);
        }
        $labels = \SmallSmallRSS\Database::escape_string(json_encode($labels));
        \SmallSmallRSS\Database::query(
            "UPDATE ttrss_user_entries
             SET label_cache = '$labels'
             WHERE
                 ref_id = '$id'
                 AND owner_uid = '$owner_uid'"
        );
    }

    public static function clearCache($id)
    {
        \SmallSmallRSS\Database::query(
            "UPDATE ttrss_user_entries
             SET label_cache = ''
             WHERE ref_id = '$id'"
        );
    }

    public static function removeArticle($article_id, $label, $owner_uid)
    {
        $label_id = self::findID($label, $owner_uid);
        if (!$label_id) {
            return;
        }
        $result = \SmallSmallRSS\Database::query(
            "DELETE FROM ttrss_user_labels2
             WHERE
                 label_id = '$label_id'
                 AND article_id = '$article_id'"
        );
        self::clearCache($article_id);
    }

    public static function addArticle($article_id, $label, $owner_uid)
    {
        $label_id = self::findID($label, $owner_uid);
        if (!$label_id) {
            return;
        }
        $result = \SmallSmallRSS\Database::query(
            "SELECT
                 article_id
             FROM
                 ttrss_labels2,
                 ttrss_user_labels2
             WHERE
                 label_id = id
                 AND label_id = '$label_id'
                 AND article_id = '$article_id'
                 AND owner_uid = '$owner_uid'
             LIMIT 1"
        );
        if (\SmallSmallRSS\Database::num_rows($result) == 0) {
            \SmallSmallRSS\Database::query(
                "INSERT INTO ttrss_user_labels2
                 (label_id, article_id)
                 VALUES ('$label_id', '$article_id')"
            );
        }
        self::clearCache($article_id);
    }

    public static function remove($id, $owner_uid)
    {
        \SmallSmallRSS\Database::query('BEGIN');
        $result = \SmallSmallRSS\Database::query(
            "SELECT caption
             FROM ttrss_labels2
             WHERE id = '$id'"
        );
        $caption = \SmallSmallRSS\Database::fetch_result($result, 0, 'caption');
        $result = \SmallSmallRSS\Database::query(
            "DELETE FROM ttrss_labels2
             WHERE id = '$id'
             AND owner_uid = $owner_uid"
        );
        if (\SmallSmallRSS\Database::affected_rows($result) != 0 && $caption) {
            $ext_id = \SmallSmallRSS\Constants::LABEL_BASE_INDEX - 1 - $id;
            \SmallSmallRSS\AccessKeys::delete($ext_id, $owner_uid);
            \SmallSmallRSS\Database::query(
                "UPDATE ttrss_user_entries
                 SET label_cache = ''
                 WHERE
                     label_cache LIKE '%$caption%'
                     AND owner_uid = $owner_uid"
            );
        }
        \SmallSmallRSS\Database::query('COMMIT');
    }

    public static function create($caption, $owner_uid, $fg_color = '', $bg_color = '')
    {
        \SmallSmallRSS\Database::query('BEGIN');
        $result = \SmallSmallRSS\Database::query(
            "SELECT id
             FROM ttrss_labels2
             WHERE
                 caption = '$caption' AND owner_uid = $owner_uid"
        );
        if (\SmallSmallRSS\Database::num_rows($result) == 0) {
            $result = \SmallSmallRSS\Database::query(
                "INSERT INTO ttrss_labels2
                 (caption, owner_uid, fg_color, bg_color)
                 VALUES
                 ('$caption', '$owner_uid', '$fg_color', '$bg_color')"
            );
            $result = \SmallSmallRSS\Database::affected_rows($result) != 0;
        }
        \SmallSmallRSS\Database::query('COMMIT');
        return $result;
    }

    public static function getOwnerLabels($owner_uid, $article_id = null)
    {
        if ($article_id) {
            $article_labels = \SmallSmallRSS\Labels::getForArticle($article_id, $owner_uid);
        } else {
            $article_labels = array();
        }

        $result = \SmallSmallRSS\Database::query(
            "SELECT id, caption, fg_color, bg_color
             FROM ttrss_labels2
             WHERE owner_uid = '$owner_uid'
             ORDER BY caption"
        );
        $rv = array();
        while ($line = \SmallSmallRSS\Database::fetch_assoc($result)) {
            $checked = false;
            foreach ($article_labels as $al) {
                if (self::fromFeedId($al[0]) == $line['id']) {
                    $checked = true;
                    break;
                }
            }
            array_push(
                $rv,
                array(
                    'id' => (int) self::toFeedId($line['id']),
                    'caption' => $line['caption'],
                    'fg_color' => $line['fg_color'],
                    'bg_color' => $line['bg_color'],
                    'checked' => $checked
                )
            );
        }

    }

    public static function getAll($owner_uid, $order_by = 'caption')
    {
        $result = \SmallSmallRSS\Database::query(
            "SELECT *
             FROM ttrss_labels2
             WHERE owner_uid = $owner_uid
             ORDER BY $order_by"
        );
        $rv = array();
        while ($line = \SmallSmallRSS\Database::fetch_assoc($result)) {
            $rv[] = $line;
        }
        return $rv;
    }

    public static function containsCaption($labels, $caption)
    {
        foreach ($labels as $label) {
            if ($label[1] == $caption) {
                return true;
            }
        }
        return false;
    }
    public static function count($owner_uid)
    {
        $result = \SmallSmallRSS\Database::query(
            'SELECT COUNT(*) AS count
             FROM ttrss_labels2
             WHERE owner_uid = ' . $owner_uid
        );
        return (int) \SmallSmallRSS\Database::fetch_result($result, 0, 'count');
    }
}
