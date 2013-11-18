<?php
namespace SmallSmallRSS;

class Feeds {
    static public function get_special_feeds() {
        return array(
            -4 => __('All articles'),
            -3 => __('Fresh articles'),
            -2 => __('Starred articles'),
            -1 => __('Published articles'),
            0 => __('Archived articles'),
            -6 => __('Recently read')
        );
    }
    static public function get_special_feed_ids() {
        return array_keys(self::get_special_feeds());
    }
    static public function getTitle($feed_id)
    {
        $special_feeds = self::get_special_feeds();
        if (isset($special_feeds[$feed_id])) {
            return $special_feeds[$feed_id];
        } elseif ($id < \SmallSmallRSS\Constants::LABEL_BASE_INDEX) {
            $label_id = feed_to_label_id($id);
            $result = \SmallSmallRSS\Database::query("SELECT caption FROM ttrss_labels2 WHERE id = '$label_id'");
            if (\SmallSmallRSS\Database::num_rows($result) == 1) {
                return \SmallSmallRSS\Database::fetch_result($result, 0, "caption");
            } else {
                return "Unknown label ($label_id)";
            }
        } elseif (is_numeric($id) && $id > 0) {
            $result = \SmallSmallRSS\Database::query("SELECT title FROM ttrss_feeds WHERE id = '$id'");
            if (\SmallSmallRSS\Database::num_rows($result) == 1) {
                return \SmallSmallRSS\Database::fetch_result($result, 0, "title");
            } else {
                return "Unknown feed ($id)";
            }
        } else {
            return $id;
        }
    }
}