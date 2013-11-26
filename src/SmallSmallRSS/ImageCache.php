<?php
namespace SmallSmallRSS;

class ImageCache
{
    public static function processImages($html, $site_url, $download_if_missing = true)
    {
        $cache_dir = \SmallSmallRSS\Config::get('CACHE_DIR') . "/images";
        libxml_use_internal_errors(true);
        $charset_hack = '<head><meta http-equiv="Content-Type" content="text/html; charset=utf-8"/></head>';
        $doc = new DOMDocument();
        $doc->loadHTML($charset_hack . $html);
        $xpath = new DOMXPath($doc);
        $entries = $xpath->query('(//img[@src])');
        foreach ($entries as $entry) {
            self::processEntry($entry);
        }
        $node = $doc->getElementsByTagName('body')->item(0);
        return $doc->saveXML($node);
    }

    public static function processEntry($entry, $download_if_missing = true)
    {
        if (!$entry->hasAttribute('src')) {
            return;
        }
        $src = $entry->getAttribute('src');
        if (0 === strpos($src, \SmallSmallRSS\Config::get('SELF_URL_PATH'))) {
            return;
        }
        $src = \SmallSmallRSS\Utils::rewriteRelativeUrl($site_url, $path);
        $cached = self::isCached($site_url, $path, $download_if_missing);
        if ($cached) {
            $src = \SmallSmallRSS\Config::get('SELF_URL_PATH') . '/image.php?hash=' . sha1($src);
        }
        $entry->setAttribute('src', $src);
    }

    public static function hashExists()
    {
        $filename = \SmallSmallRSS\Config::get('CACHE_DIR') . '/images/' . $hash . '.png';
        return file_exists($filename);
    }

    public static function isCached($src, $download_if_missing = true)
    {
        $local_filename = \SmallSmallRSS\Config::get('CACHE_DIR') . "/images/" . sha1($src) . ".png";
        if (!file_exists($local_filename)) {
            $file_content = \SmallSmallRSS\Fetcher::fetch($src);
            if ($file_content && strlen($file_content) > 1024) {
                file_put_contents($local_filename, $file_content);
                return true;
            }
        }
        return false;
    }
}
