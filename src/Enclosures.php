<?php
namespace SmallSmallRSS;

class Enclosures
{
    public static function get($post_id)
    {
        $query = "SELECT * FROM ttrss_enclosures
                  WHERE
                      post_id = '$post_id'
                      AND content_url != ''";
        $rv = array();
        $result = \SmallSmallRSS\Database::query($query);
        if (\SmallSmallRSS\Database::numRows($result) > 0) {
            while ($line = \SmallSmallRSS\Database::fetchAssoc($result)) {
                array_push($rv, $line);
            }
        }
        return $rv;
    }
    public static function add($entry_ref_id, $enclosures)
    {
        \SmallSmallRSS\Database::query('BEGIN');
        foreach ($enclosures as $enc) {
            $enc_url = \SmallSmallRSS\Database::escapeString($enc[0]);
            $enc_type = \SmallSmallRSS\Database::escapeString($enc[1]);
            $enc_dur = \SmallSmallRSS\Database::escapeString($enc[2]);
            $result = \SmallSmallRSS\Database::query(
                "SELECT id
                 FROM ttrss_enclosures
                 WHERE
                     content_url = '$enc_url'
                     AND post_id = '$entry_ref_id'"
            );
            if (\SmallSmallRSS\Database::numRows($result) == 0) {
                \SmallSmallRSS\Database::query(
                    "INSERT INTO ttrss_enclosures
                     (content_url, content_type, title, duration, post_id)
                     VALUES
                     ('$enc_url', '$enc_type', '', '$enc_dur', '$entry_ref_id')"
                );
            }
        }

        \SmallSmallRSS\Database::query('COMMIT');

    }
}
