<?php
namespace SmallSmallRSS\Renderers;

class CSS
{
    public function renderUserStylesheet()
    {
        $value = \SmallSmallRSS\DBPrefs::read('USER_STYLESHEET');
        if ($value) {
            echo '<style type="text/css">';
            echo str_replace('<br/>', "\n", $value);
            echo '</style>';
        }
    }
    public function renderStylesheetTag($filename)
    {
        $timestamp = filemtime(__DIR__ . "../../$filename");
        echo "<link rel=\"stylesheet\" type=\"text/css\" href=\"$filename?$timestamp\"/>\n";
    }
}
