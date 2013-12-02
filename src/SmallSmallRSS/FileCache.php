<?php
namespace SmallSmallRSS;

class FileCache
{
    private static $default_caches = array('simplepie', 'images', 'export', 'upload');

    public static function clearExpired()
    {
        $num_deleted = 0;
        foreach (self::$default_caches as $subdir) {
            $num_deleted += self::clearExpiredDirContents($subdir);
        }
        return $num_deleted;
    }

    public static function clearExpiredDirContents($subdir)
    {
        // TODO sanitize input
        $cache_dir = \SmallSmallRSS\Config::get('CACHE_DIR') . "/$subdir";
        if (!is_writable($cache_dir)) {
            \SmallSmallRSS\Logger::debug("$cache_dir is not writable");
            return 0;
        }
        $cache_lifetime = \SmallSmallRSS\Config::get('CACHE_LIFETIME');
        $files = glob("$cache_dir/*");
        $num_deleted = 0;
        if ($files) {
            foreach ($files as $file) {
                if (time() - filemtime($file) > $cache_lifetime) {
                    unlink($file);
                    $num_deleted += 1;
                }
            }
        }
        return $num_deleted;
    }
}
