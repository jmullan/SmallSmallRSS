<?php
namespace SmallSmallRSS;

class Labels
{
    public static function findId($label, $owner_uid)
    {
        $result = db_query(
            "SELECT id
             FROM ttrss_labels2
             WHERE caption = '$label'
             AND owner_uid = '$owner_uid' LIMIT 1"
        );
        if (db_num_rows($result) == 1) {
            return db_fetch_result($result, 0, "id");
        } else {
            return 0;
        }
    }

    public static function getForArticle($id, $owner_uid = false)
    {
        $rv = array();
        if (!$owner_uid) {
            $owner_uid = $_SESSION["uid"];
        }
        $result = db_query(
            "SELECT label_cache
            FROM ttrss_user_entries
            WHERE ref_id = '$id' AND owner_uid = '$owner_uid'"
        );
        if (db_num_rows($result) > 0) {
            $label_cache = db_fetch_result($result, 0, "label_cache");
            if ($label_cache) {
                $label_cache = json_decode($label_cache, true);
                if ($label_cache["no-labels"] == 1) {
                    return $rv;
                } else {
                    return $label_cache;
                }
            }
        }
        $result = db_query(
            "SELECT DISTINCT label_id,caption,fg_color,bg_color
                FROM ttrss_labels2, ttrss_user_labels2
            WHERE
                id = label_id
                AND article_id = '$id'
                AND owner_uid = $owner_uid
            ORDER BY caption"
        );
        while ($line = db_fetch_assoc($result)) {
            $rk = array($line["label_id"], $line["caption"], $line["fg_color"], $line["bg_color"]);
            array_push($rv, $rk);
        }
        if (count($rv) > 0) {
            self::updateCache($owner_uid, $id, $rv);
        } else {
            self::updateCache($owner_uid, $id, array("no-labels" => 1));
        }
        return $rv;
    }


    public static function findCaption($label, $owner_uid)
    {
        $result = db_query(
            "SELECT caption
             FROM ttrss_labels2
             WHERE
                 id = '$label'
                 AND owner_uid = '$owner_uid'
             LIMIT 1"
        );
        if (db_num_rows($result) == 1) {
            return db_fetch_result($result, 0, "caption");
        } else {
            return "";
        }
    }

    public static function getAll($owner_uid)
    {
        $rv = array();
        $result = db_query("SELECT fg_color, bg_color, caption FROM ttrss_labels2 WHERE owner_uid = " . $owner_uid);
        while ($line = db_fetch_assoc($result)) {
            array_push($rv, $line);
        }
        return $rv;
    }

    public static function updateCache($owner_uid, $id, $labels = false, $force = false)
    {
        if ($force) {
            self::clearCache($id);
        }
        if (!$labels) {
            $labels = self::getForArticle($id);
        }
        $labels = db_escape_string(json_encode($labels));
        db_query(
            "UPDATE ttrss_user_entries SET
            label_cache = '$labels' WHERE ref_id = '$id' AND  owner_uid = '$owner_uid'"
        );
    }

    public static function clearCache($id)
    {
        db_query(
            "UPDATE ttrss_user_entries SET
            label_cache = '' WHERE ref_id = '$id'"
        );
    }

    public static function removeArticle($id, $label, $owner_uid)
    {
        $label_id = self::findID($label, $owner_uid);
        if (!$label_id) {
            return;
        }
        $result = db_query(
            "DELETE FROM ttrss_user_labels2
            WHERE
                label_id = '$label_id'
                AND article_id = '$id'"
        );
        self::clearCache($id);
    }

    public static function addArticle($id, $label, $owner_uid)
    {
        $label_id = self::findID($label, $owner_uid);
        if (!$label_id) {
            return;
        }
        $result = db_query(
            "SELECT
                article_id
            FROM
                ttrss_labels2,
                ttrss_user_labels2
            WHERE
                label_id = id
                AND label_id = '$label_id'
                AND article_id = '$id'
                AND owner_uid = '$owner_uid'
            LIMIT 1"
        );
        if (db_num_rows($result) == 0) {
            db_query(
                "INSERT INTO ttrss_user_labels2
                (label_id, article_id)
                VALUES ('$label_id', '$id')"
            );
        }
       self::clearCache($id);
    }

    public static function remove($id, $owner_uid)
    {
        if (!$owner_uid) {
            $owner_uid = $_SESSION["uid"];
        }
        db_query("BEGIN");
        $result = db_query(
            "SELECT caption
             FROM ttrss_labels2
             WHERE id = '$id'"
        );
        $caption = db_fetch_result($result, 0, "caption");
        $result = db_query(
            "DELETE FROM ttrss_labels2
             WHERE id = '$id'
             AND owner_uid = $owner_uid"
        );
        if (db_affected_rows($result) != 0 && $caption) {
            /* Remove access key for the label */
            $ext_id = LABEL_BASE_INDEX - 1 - $id;
            db_query(
                "DELETE FROM ttrss_access_keys
                 WHERE
                     feed_id = '$ext_id'
                     AND owner_uid = $owner_uid"
            );
            /* Remove cached data */
            db_query(
                "UPDATE ttrss_user_entries
                 SET label_cache = ''
                 WHERE
                     label_cache LIKE '%$caption%'
                     AND owner_uid = $owner_uid"
            );
        }
        db_query("COMMIT");
    }

    public static function create($caption, $fg_color = '', $bg_color = '', $owner_uid = false)
    {
        if (!$owner_uid) {
            $owner_uid = $_SESSION['uid'];
        }
        db_query("BEGIN");
        $result = false;
        $result = db_query(
            "SELECT id
             FROM ttrss_labels2
             WHERE
                 caption = '$caption'
                 AND owner_uid = $owner_uid"
        );
        if (db_num_rows($result) == 0) {
            $result = db_query(
                "INSERT INTO ttrss_labels2
                 (caption, owner_uid, fg_color, bg_color)
                 VALUES
                 ('$caption', '$owner_uid', '$fg_color', '$bg_color')"
            );
            $result = db_affected_rows($result) != 0;
        }
        db_query("COMMIT");
        return $result;
    }
}
