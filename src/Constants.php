<?php
namespace SmallSmallRSS;
class Constants
{
    const VERSION = '1.8';
    const EXPECTED_CONFIG_VERSION = 26;
    const SCHEMA_VERSION = 121;
    const LABEL_BASE_INDEX = -1024;
    const PLUGIN_FEED_BASE_INDEX = -128;

    # one year in seconds
    const COOKIE_LIFETIME_LONG = 31536000;

    public static function purge_intervals()
    {
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

    public static function update_intervals()
    {
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
    public static function update_intervals_nodefault()
    {
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
    public static function access_level_names()
    {
        return array(
            0 => __("User"),
            5 => __("Power User"),
            10 => __("Administrator")
        );
    }
    public static function errors()
    {
        $ERRORS = array(
            0 => "",
            1 => __(
                "This program requires XmlHttpRequest"
                . " to function properly. Your browser doesn't seem to support it."
            ),
            2 => __(
                "This program requires cookies"
                . " to function properly. Your browser doesn't seem to support them."
            ),
            3 => __("Backend sanity check failed."),
            4 => __("Frontend sanity check failed."),
            5 => __(
                "Incorrect database schema version. &lt,a href='db-updater.php'&gt,"
                . " Please update&lt,/a&gt,."
            ),
            6 => __("Request not authorized."),
            7 => __("No operation to perform."),
            8 => __(
                "Could not display feed: query failed. Please check label match syntax"
                . " or local configuration."
            ),
            8 => __("Denied. Your access level is insufficient to access this page."),
            9 => __("Configuration check failed"),
            10 => __(
                "Your version of MySQL is not currently supported. Please see official"
                . " site for more information."
            ),
            11 => "[This error is not returned by server]",
            12 => __("SQL escaping test failed, check your database and PHP configuration")
        );
        return $ERRORS;
    }
    public static function error_description($error_code)
    {
        $errors = self::errors();
        return $errors[$error_code];
    }
}
