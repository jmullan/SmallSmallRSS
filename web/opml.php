<?php
require_once __DIR__ . '/../src/SmallSmallRSS/bootstrap.php';
\SmallSmallRSS\Sessions::init();
if (!\SmallSmallRSS\PluginHost::init_all()) {
    return;
}
$op = $_REQUEST['op'];
if ($op == 'publish') {
    $owner_uid = \SmallSmallRSS\AccessKeys::getOwner($_REQUEST['key']);
    if ($owner_uid !== false) {
        $opml = new Opml($_REQUEST);
        $opml->opml_export('', $owner_uid, true, false);
    } else {
        print '<error>User not found</error>';
    }
}
