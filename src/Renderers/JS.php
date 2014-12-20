<?php
namespace SmallSmallRSS\Renderers;

class JS extends \SmallSmallRSS\Renderers\Base
{
    public static $minify = false;

    public function renderMinifiedJsFiles($basenames)
    {
        $rv = '';
        foreach ($basenames as $basename) {
            $this->renderMinifiedJsFile($basename);
        }
    }

    public function renderMinifiedJsFile($js)
    {
        if (self::$minify) {
            $cached_file = \SmallSmallRSS\Config::get('CACHE_DIR') . '/js/' . basename($js) . '.js';
            if (file_exists($cached_file)
                && is_readable($cached_file)
                && filemtime($cached_file) >= filemtime("js/$js.js")) {
                echo file_get_contents($cached_file);
            } else {
                $minified = \JShrink\Minifier::minify(file_get_contents("js/$js.js"));
                file_put_contents($cached_file, $minified);
                echo $minified;
            }
        } else {
            echo "\n// $js\n";
            echo file_get_contents("js/$js.js");
        }
    }
    public function renderScriptTag($filename)
    {
        $parts = parse_url($filename);
        $query = '';
        if (isset($parts['query'])) {
            $query = $parts['query'];
        }
        if (strpos($filename, '?') !== false) {
            $filename = substr($filename, 0, strpos($filename, '?'));
        }
        $timestamp = filemtime($filename);
        if ($query) {
            $timestamp .= "&$query";
        }
        echo '<script type="text/javascript" charset="utf-8" src="' . $filename . '?' . $timestamp . '"></script>';
        echo "\n";
    }

    public function renderMinified($js)
    {
        if (self::$minify) {
            echo \JShrink\Minifier::minify($js);
        } else {
            echo $js;
        }
    }
}
