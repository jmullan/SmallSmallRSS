<?php
namespace SmallSmallRSS;
class Constants {
    const EXPECTED_CONFIG_VERSION = 26;
    const SCHEMA_VERSION = 121;
    const LABEL_BASE_INDEX = -1024;
    const PLUGIN_FEED_BASE_INDEX = -128;

    # one year in seconds
    const COOKIE_LIFETIME_LONG = 31536000;

    static public function purge_intervals() {
        return array(
            0 => __("Use default"),
            -1 => __("Never purge"),
            5 => __("1 week old"),
            14 => __("2 weeks old"),
            31 => __("1 month old"),
            60 => __("2 months old"),
            90 => __("3 months old")
        );
    }

    static public function update_intervals() {
        return array(
            0 => __("Default interval"),
            -1 => __("Disable updates"),
            15 => __("Each 15 minutes"),
            30 => __("Each 30 minutes"),
            60 => __("Hourly"),
            240 => __("Each 4 hours"),
            720 => __("Each 12 hours"),
            1440 => __("Daily"),
            10080 => __("Weekly")
        );
    }
    static public function update_intervals_nodefault() {
        return array(
            -1 => __("Disable updates"),
            15 => __("Each 15 minutes"),
            30 => __("Each 30 minutes"),
            60 => __("Hourly"),
            240 => __("Each 4 hours"),
            720 => __("Each 12 hours"),
            1440 => __("Daily"),
            10080 => __("Weekly")
        );
    }
    static public function access_level_names() {
        return array(
            0 => __("User"),
            5 => __("Power User"),
            10 => __("Administrator")
        );
     }
}