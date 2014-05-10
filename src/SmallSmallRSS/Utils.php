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
        if (!isset($parts['scheme'])) {
            $parts['scheme'] = null;
        }
        if (!isset($parts['host'])) {
            $parts['host'] = null;
        }
        if (!isset($parts['path'])) {
            $parts['path'] = null;
        }
        return $parts['scheme'] . '://' . $parts['host'] . $parts['path'];
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
        if (strpos($rel_url, 'magnet:') === 0) {
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
        if (strpos($relative_parts['path'], '/') === 0) {
            $source_parts['path'] = $relative_parts['path'];
            return self::buildUrl($source_parts);
        } else {
            $source_parts['path'] .= $relative_parts['path'];
            return self::buildUrl($source_parts);
        }
    }

    public static function isHtml($content)
    {
        return (
            (stripos($content, '<html') !== false)
            || (stripos($content, 'DOCTYPE html') !== false)
        );
    }
    public static function truncateString($str, $max_len, $suffix = '&hellip;')
    {
        if (mb_strlen($str, 'utf-8') > $max_len - 3) {
            return mb_substr($str, 0, $max_len, 'utf-8') . $suffix;
        } else {
            return $str;
        }
    }
    public static function convertTimestamp($timestamp, $source_tz, $dest_tz)
    {
        try {
            $source_tz = new DateTimeZone($source_tz);
        } catch (Exception $e) {
            $source_tz = new DateTimeZone('UTC');
        }
        try {
            $dest_tz = new DateTimeZone($dest_tz);
        } catch (Exception $e) {
            $dest_tz = new DateTimeZone('UTC');
        }
        $dt = new DateTime(date('Y-m-d H:i:s', $timestamp), $source_tz);
        return $dt->format('U') + $dest_tz->getOffset($dt);
    }

    public static function smartDateTime($timestamp, $owner_uid, $tz_offset)
    {
        if (date('Y.m.d', $timestamp) == date('Y.m.d', time() + $tz_offset)) {
            return date('G:i', $timestamp);
        } elseif (date('Y', $timestamp) == date('Y', time() + $tz_offset)) {
            $format = \SmallSmallRSS\DBPrefs::read('SHORT_DATE_FORMAT', $owner_uid);
            return date($format, $timestamp);
        } else {
            $format = \SmallSmallRSS\DBPrefs::read('LONG_DATE_FORMAT', $owner_uid);
            return date($format, $timestamp);
        }
    }
    public static function readStdin()
    {
        $line = null;
        $fp = fopen('php://stdin', 'r');
        if ($fp) {
            $line = trim(fgets($fp));
            fclose($fp);
        }
        return $line;
    }
}
