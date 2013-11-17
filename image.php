<?php
require_once __DIR__ . '/SmallSmallRSS/bootstrap.php';

$hash = null;
if (isset($_GET['hash'])) {
  $hash = basename($_GET['hash']);
}
if ($hash) {
    $filename = \SmallSmallRSS\Config::get('CACHE_DIR') . '/images/' . $hash . '.png';
    if (file_exists($filename)) {
        /* See if we can use X-Sendfile */
        $xsendfile = false;
        if (function_exists('apache_get_modules')
            && array_search('mod_xsendfile', apache_get_modules()))
            $xsendfile = true;
        if ($xsendfile) {
            header("X-Sendfile: $filename");
            header("Content-type: application/octet-stream");
            header('Content-Disposition: attachment; filename="' . basename($filename) . '"');
        } else {
            header("Content-type: image/png");
            $stamp = gmdate("D, d M Y H:i:s", filemtime($filename)). " GMT";
            header("Last-Modified: $stamp", true);
            readfile($filename);
        }
    } else {
        header($_SERVER["SERVER_PROTOCOL"] . " 404 Not Found");
        echo "File not found.";
    }
}
