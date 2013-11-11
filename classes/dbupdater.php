<?php
class DbUpdater {
    private $db_type;
    private $need_version;

    function __construct($db_type, $need_version) {
        $this->db_type = $db_type;
        $this->need_version = (int) $need_version;
    }

    function getSchemaVersion() {
        $result = \SmallSmallRSS\Database::query("SELECT schema_version FROM ttrss_version");
        return (int) \SmallSmallRSS\Database::fetch_result($result, 0, "schema_version");
    }

    function isUpdateRequired() {
        return $this->getSchemaVersion() < $this->need_version;
    }

    function getSchemaLines($version) {
        $filename = "schema/versions/" . $this->db_type . "/$version.sql";

        if (file_exists($filename)) {
            return explode(";", preg_replace("/[\r\n]/", "", file_get_contents($filename)));
        } else {
            return false;
        }
    }

    function performUpdateTo($version) {
        if ($this->getSchemaVersion() == $version - 1) {

            $lines = $this->getSchemaLines($version);

            if (is_array($lines)) {

                \SmallSmallRSS\Database::query("BEGIN");

                foreach ($lines as $line) {
                    if (strpos($line, "--") !== 0 && $line) {
                        \SmallSmallRSS\Database::query($line);
                    }
                }

                $db_version = $this->getSchemaVersion();

                if ($db_version == $version) {
                    \SmallSmallRSS\Database::query("COMMIT");
                    return true;
                } else {
                    \SmallSmallRSS\Database::query("ROLLBACK");
                    return false;
                }
            } else {
                return true;
            }
        } else {
            return false;
        }
    }
}