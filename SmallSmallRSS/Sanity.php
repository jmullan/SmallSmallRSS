<?php
namespace SmallSmallRss;
class Sanity {
    public static function get_schema_version($nocache = false) {
        // Session caching removed due to causing wrong redirects to upgrade
        // script when get_schema_version() is called on an obsolete session
        // created on a previous schema version.
        static $schema_version = null;

        if (!$schema_version || $nocache) {
            $result = \SmallSmallRSS\Database::query(
                "SELECT schema_version FROM ttrss_version");
            $schema_version = \SmallSmallRSS\Database::fetch_result(
                $result, 0, "schema_version");
        }
        return $schema_version;
    }
    public static function is_schema_correct() {
        $schema_version = self::get_schema_version(true);
        $expected_version = \SmallSmallRSS\Constants::SCHEMA_VERSION;
        return $schema_version == $expected_version;
    }
    public static function schema_or_die() {
        $schema_version = self::get_schema_version();
        $expected_version = \SmallSmallRSS\Constants::SCHEMA_VERSION;
        if ($schema_version != $expected_version) {
            echo "Schema version is wrong, please upgrade the database: $schema_version / $expected_version\n";
            die(255);
        }
    }
}