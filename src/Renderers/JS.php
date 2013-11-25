<?php
namespace SmallSmallRSS\Renderers;

require_once __DIR__ . '/../../lib/jshrink/Minifier.php';

class JS extends \SmallSmallRSS\Renderers\Base
{
    public static $minify = false;

    public function render_minified_js_files($basenames)
    {
        $rv = '';
        foreach ($basenames as $basename) {
            $this->render_minified_js_file($basename);
        }
    }

    public function render_minified_js_file($js)
    {
        if (self::$minify) {
            $cached_file = \SmallSmallRSS\Config::get('CACHE_DIR') . "/js/" . basename($js) . ".js";
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
    public function render_script_tag($filename)
    {
        $parts = parse_url($filename);
        $query = $parts['query'];
        $query = "";
        if (!(strpos($filename, "?") === false)) {
            $query = substr($filename, strpos($filename, "?") + 1);
            $filename = substr($filename, 0, strpos($filename, "?"));
        }
        $timestamp = filemtime($filename);
        if ($query) {
            $timestamp .= "&$query";
        }
        echo '<script type="text/javascript" charset="utf-8" src="' . $filename . '?' . $timestamp . '"></script>';
        echo "\n";
    }

    public function render_minified($js)
    {
        if (self::$minify) {
            echo \JShrink\Minifier::minify($js);
        } else {
            echo $js;
        }
    }
}
