<?php
require_once __DIR__ . '/../src/SmallSmallRSS/bootstrap.php';

$hash = null;
if (isset($_GET['hash'])) {
    $hash = basename($_GET['hash']);
    if (!preg_match('/^[0-9a-f]{40}$/', $hash)) {
        $hash = null;
    }
}
if ($hash && \SmallSmallRSS\ImageCache::hashExists($hash)) {
    $filename = \SmallSmallRSS\ImageCache::hashToFilename($hash);
    /* See if we can use X-Sendfile */
    $xsendfile = false;
    if (function_exists('apache_get_modules')
        && array_search('mod_xsendfile', apache_get_modules())) {
        $xsendfile = true;
    }
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
