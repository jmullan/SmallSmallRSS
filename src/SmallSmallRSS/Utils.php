<?php
namespace SmallSmallRSS;

class Utils
{
    public static function stripslashesDeep($value)
    {
        $value = (
            is_array($value)
            ? array_map(array(self, 'stripslashesDeep'), $value)
            : stripslashes($value));
        return $value;
    }

    public static function cleanUpStripslashes()
    {
        static $finished = false;
        if (!$finished) {
            if (get_magic_quotes_gpc()) {
                $_POST = array_map(array(self, 'stripslashesDeep'), $_POST);
                $_GET = array_map(array(self, 'stripslashesDeep'), $_GET);
                $_COOKIE = array_map(array(self, 'stripslashesDeep'), $_COOKIE);
                $_REQUEST = array_map(array(self, 'stripslashesDeep'), $_REQUEST);
            }
            $finished = true;
        }
    }

    public static function buildUrl($parts)
    {
        return $parts['scheme'] . "://" . $parts['host'] . $parts['path'];
    }

    /**
     * Converts a (possibly) relative URL to a absolute one.
     *
     * @param string $url     Base URL (i.e. from where the document is)
     * @param string $rel_url Possibly relative URL in the document
     *
     * @return string Absolute URL
     */
    public static function rewriteRelativeUrl($url, $rel_url)
    {
        if (strpos($rel_url, "magnet:") === 0) {
            return $rel_url;
        }
        $source_parts = parse_url($url);
        $source_parts['query'] = '';
        $source_parts['fragment'] = '';
        if (substr($rel_url, 0, 2) == '//') {
            if (isset($source_parts['scheme'])) {
                $rel_url = $source_parts['scheme'] . ':' . $rel_url;
            } else {
                return $rel_url;
            }
        }
        $relative_parts = parse_url($rel_url);
        if (!$relative_parts) {
            return $rel_url;
        }
        $chunks = array('scheme', 'host', 'query');
        foreach ($chunks as $chunk) {
            if (isset($relative_parts[$chunk])) {
                $source_parts[$chunk] = $relative_parts[$chunk];
            }
        }
        if (!isset($source_parts['path'])) {
            $source_parts['path'] = '/';
        }
        if (substr($source_parts['path'], -1) !== '/') {
            $source_parts['path'] = dirname($source_parts['path']) . '/';
        }
        if (!isset($relative_parts['path'])) {
            $relative_parts['path'] = '';
        }
        if (strpos($relative_parts['path'], "/") === 0) {
            $source_parts['path'] = $relative_parts['path'];
            return self::buildUrl($source_parts);
        } else {
            $source_parts['path'] .= $relative_parts['path'];
            return self::buildUrl($source_parts);
        }
    }

    public static function isHtml($string)
    {
        return (
            (stripos($content, '<html') !== false)
            || (stripos($content, 'DOCTYPE html') !== false)
        );
    }
}