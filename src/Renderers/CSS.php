<?php
namespace SmallSmallRSS\Renderers;

class CSS
{
    public function renderUserStylesheet()
    {
        $value = \SmallSmallRSS\DBPrefs::read('USER_STYLESHEET', $_SESSION['uid']);
        if ($value) {
            echo '<style type="text/css">';
            echo str_replace('<br/>', "\n", $value);
            echo '</style>';
        }
    }
    public function renderStylesheetTag($filename)
    {
        $real_filename = __DIR__ . "/../../../web/$filename";
        $timestamp = '';
        if (file_exists($real_filename)) {
            $timestamp = filemtime($real_filename);
        }
        echo '<link rel="stylesheet" type="text/css" href="' . $filename . '?' . $timestamp . '"/>';
    }
}
