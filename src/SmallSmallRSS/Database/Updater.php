<?php
namespace SmallSmallRSS\Database;

class Updater
{
    public function getSchemaVersion()
    {
        $result = \SmallSmallRSS\Database::query("SELECT schema_version FROM ttrss_version");
        return (int) \SmallSmallRSS\Database::fetch_result($result, 0, "schema_version");
    }

    public function isUpdateRequired()
    {
        return $this->getSchemaVersion() < \SmallSmallRSS\Constants::SCHEMA_VERSION;
    }

    public function getSchemaLines($version)
    {
        $filename = __DIR__ . "/schema/versions/" . \SmallSmallRSS\Config::get('DB_TYPE') . "/$version.sql";
        if (file_exists($filename)) {
            return explode(";", preg_replace("/[\r\n]/", "", file_get_contents($filename)));
        } else {
            return false;
        }
    }

    public function performUpdateTo($version)
    {
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

    public static function dropIndexes()
    {
        if (\SmallSmallRSS\Config::get('DB_TYPE') == "pgsql") {
            $result = \SmallSmallRSS\Database::query(
                "SELECT relname
                 FROM pg_catalog.pg_class
                 WHERE
                     relname LIKE 'ttrss_%'
                     AND relname NOT LIKE '%_pkey'
                     AND relkind = 'i'"
            );
        } else {
            $result = \SmallSmallRSS\Database::query(
                "SELECT index_name,table_name
                 FROM information_schema.statistics
                 WHERE index_name LIKE 'ttrss_%'"
            );
        }

        while (($line = \SmallSmallRSS\Database::fetch_assoc($result))) {
            if (\SmallSmallRSS\Config::get('DB_TYPE') == "pgsql") {
                $statement = "DROP INDEX " . $line["relname"];
            } else {
                $statement = "ALTER TABLE " . $line['table_name'] . " DROP INDEX ".$line['index_name'];
            }
            \SmallSmallRSS\Database::query($statement, false);
        }
    }

    public static function createIndexes()
    {
        $fp = fopen(__DIR__ . "/schema/ttrss_schema_" . \SmallSmallRSS\Config::get('DB_TYPE') . ".sql", "r");
        if ($fp) {
            while (($line = fgets($fp))) {
                $matches = array();
                if (preg_match("/^create index ([^ ]+) on ([^ ]+)$/i", $line, $matches)) {
                    $index = $matches[1];
                    $table = $matches[2];
                    $statement = "CREATE INDEX $index ON $table";
                    \SmallSmallRSS\Logger::debug($statement);
                    \SmallSmallRSS\Database::query($statement);
                }
            }
            fclose($fp);
        } else {
            \SmallSmallRSS\Logger::debug("unable to open schema file.");
        }
    }
}
