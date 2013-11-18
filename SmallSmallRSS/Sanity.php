<?php
namespace SmallSmallRss;

class Sanity
{
    public static function getSchemaVersion($nocache = false)
    {
        // Session caching removed due to causing wrong redirects to upgrade
        // script when getSchemaVersion() is called on an obsolete session
        // created on a previous schema version.
        static $schema_version = null;

        if (!$schema_version || $nocache) {
            $result = \SmallSmallRSS\Database::query(
                "SELECT schema_version FROM ttrss_version"
            );
            $schema_version = \SmallSmallRSS\Database::fetch_result(
                $result,
                0,
                "schema_version"
            );
        }
        return $schema_version;
    }
    public static function isSchemaCorrect()
    {
        $schema_version = self::getSchemaVersion(true);
        $expected_version = \SmallSmallRSS\Constants::SCHEMA_VERSION;
        return $schema_version == $expected_version;
    }
    public static function schemaOrDie()
    {
        $schema_version = self::getSchemaVersion();
        $expected_version = \SmallSmallRSS\Constants::SCHEMA_VERSION;
        if ($schema_version != $expected_version) {
            echo "Schema version is wrong, please upgrade the database: $schema_version / $expected_version\n";
            die(255);
        }
    }

    public static function makeSelfURLPath()
    {
        $url_path = (
            ($_SERVER['HTTPS'] != "on" ? 'http://' : 'https://')
            . $_SERVER["HTTP_HOST"]
            . parse_url($_SERVER["REQUEST_URI"], PHP_URL_PATH)
        );
        return $url_path;
    }

    public static function initialCheck()
    {
        $errors = array();

        if (!file_exists("config.ini")) {
            array_push(
                $errors,
                "Configuration file not found."
                . " Looks like you forgot to copy config.ini-dist to config.ini and edit it."
            );
        } else {

            if (file_exists("install") && !file_exists("config.ini")) {
                array_push(
                    $errors,
                    "Please copy config.ini-dist to config.ini or run the installer in install/"
                );
            }

            if (strpos(\SmallSmallRSS\Config::get('PLUGINS'), "auth_") === false) {
                array_push(
                    $errors,
                    "Please enable at least one authentication module via PLUGINS constant in config.ini"
                );
            }

            if (function_exists('posix_getuid') && posix_getuid() == 0) {
                array_push($errors, "Please don't run this script as root.");
            }

            if (version_compare(PHP_VERSION, '5.3.0', '<')) {
                array_push($errors, "PHP version 5.3.0 or newer required.");
            }

            if (\SmallSmallRSS\Config::get('CONFIG_VERSION')
                != \SmallSmallRSS\Constants::EXPECTED_CONFIG_VERSION) {
                array_push(
                    $errors,
                    "Configuration file (config.ini) has incorrect version."
                    . " Update it with new options from config.ini-dist and set CONFIG_VERSION"
                    . " to the correct value."
                );
            }

            $cache_dir = \SmallSmallRSS\Config::get('CACHE_DIR');
            if (!is_writable($cache_dir . "/images")) {
                array_push(
                    $errors,
                    "Image cache is not writable (chmod -R 777 ".$cache_dir."/images)"
                );
            }

            if (!is_writable($cache_dir . "/upload")) {
                array_push(
                    $errors,
                    "Upload cache is not writable (chmod -R 777 ".$cache_dir."/upload)"
                );
            }

            if (!is_writable($cache_dir . "/export")) {
                array_push(
                    $errors,
                    "Data export cache is not writable (chmod -R 777 ".$cache_dir."/export)"
                );
            }

            if (!is_writable($cache_dir . "/js")) {
                array_push(
                    $errors,
                    "Javascript cache is not writable (chmod -R 777 ".$cache_dir."/js)"
                );
            }

            $feed_crypt_key = \SmallSmallRSS\Config::get('FEED_CRYPT_KEY');
            if (strlen($feed_crypt_key) > 0 && strlen($feed_crypt_key) != 24) {
                array_push($errors, "$feed_crypt_key should be exactly 24 characters in length.");
            }

            if (strlen($feed_crypt_key) > 0 && !function_exists("mcrypt_decrypt")) {
                array_push(
                    $errors,
                    "$feed_crypt_key requires mcrypt functions which are not found."
                );
            }

            if (\SmallSmallRSS\Auth::is_single_user_mode()) {
                $result = \SmallSmallRSS\Database::query("SELECT id FROM ttrss_users WHERE id = 1");

                if (\SmallSmallRSS\Database::num_rows($result) != 1) {
                    array_push(
                        $errors,
                        "SINGLE_USER_MODE is enabled in config.ini but default admin account is not found."
                    );
                }
            }

            if (\SmallSmallRSS\Config::get('SELF_URL_PATH') == "http://yourserver/tt-rss/") {
                $urlpath = preg_replace("/\w+\.php$/", "", self::makeSelfURLPath());

                array_push(
                    $errors,
                    "Please set SELF_URL_PATH to the correct value for your server (possible value: <b>$urlpath</b>)"
                );
            }

            if (!is_writable(\SmallSmallRSS\Config::get('ICONS_DIR'))) {
                array_push(
                    $errors,
                    "ICONS_DIR defined in config.ini is not writable (chmod -R 777 "
                    . \SmallSmallRSS\Config::get('ICONS_DIR') . ").\n"
                );
            }

            if (!is_writable(\SmallSmallRSS\Config::get('LOCK_DIRECTORY'))) {
                array_push(
                    $errors,
                    "\SmallSmallRSS\Config::get('LOCK_DIRECTORY') defined in config.ini is not writable (chmod -R 777 "
                    . \SmallSmallRSS\Config::get('LOCK_DIRECTORY') . ").\n"
                );
            }

            if (!function_exists("curl_init") && !ini_get("allow_url_fopen")) {
                array_push(
                    $errors,
                    "PHP configuration option allow_url_fopen is disabled, and CURL functions are not present."
                    . " Either enable allow_url_fopen or install PHP extension for CURL."
                );
            }

            if (!function_exists("json_encode")) {
                array_push($errors, "PHP support for JSON is required, but was not found.");
            }

            if (\SmallSmallRSS\Config::get('DB_TYPE') == "mysql"
                && !function_exists("mysql_connect")
                && !function_exists("mysqli_connect")) {
                array_push($errors, "PHP support for MySQL is required for configured DB_TYPE in config.ini.");
            }

            if (\SmallSmallRSS\Config::get('DB_TYPE') == "pgsql"
                && !function_exists("pg_connect")) {
                array_push($errors, "PHP support for PostgreSQL is required for configured DB_TYPE in config.ini");
            }

            if (!function_exists("mb_strlen")) {
                array_push($errors, "PHP support for mbstring functions is required but was not found.");
            }

            if (!function_exists("hash")) {
                array_push($errors, "PHP support for hash() function is required but was not found.");
            }

            if (!function_exists("ctype_lower")) {
                array_push($errors, "PHP support for ctype functions are required by HTMLPurifier.");
            }

            if (!function_exists("iconv")) {
                array_push($errors, "PHP support for iconv is required to handle multiple charsets.");
            }

            if (ini_get("safe_mode")) {
                array_push($errors, "PHP safe mode setting is not supported.");
            }

            if ((\SmallSmallRSS\Config::get('PUBSUBHUBBUB_HUB') || \SmallSmallRSS\Config::get('PUBSUBHUBBUB_ENABLED'))
                && !function_exists("curl_init")) {
                array_push($errors, "PHP support for CURL is required for PubSubHubbub.");
            }

            if (!class_exists("DOMDocument")) {
                array_push($errors, "PHP support for DOMDocument is required, but was not found.");
            }
        }

        if (count($errors) > 0 && $_SERVER['REQUEST_URI']) {
            $insane = new \SmallSmallRSS\Renderers\Insane($errors);
            $insane->render();
            exit;
        } elseif (count($errors) > 0) {
            echo "Tiny Tiny RSS was unable to start properly. This usually means a misconfiguration";
            echo " or an incomplete upgrade.\n";
            echo "Please fix errors indicated by the following messages:\n\n";

            foreach ($errors as $error) {
                echo " * $error\n";
            }

            echo "\nYou might want to check tt-rss wiki or the forums for more information.\n";
            echo "Please search the forums before creating new topic for your question.\n";
            exit(-1);
        }
    }
}
