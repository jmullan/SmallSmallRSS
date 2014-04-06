<?php
foreach ($lines as $line) {
    $feed_url = htmlspecialchars($line['feed_url']);
    $site_url = htmlspecialchars($line['site_url']);
    $title = htmlspecialchars($line['title']);
    $subscribers = htmlspecialchars($line['subscribers']);
    echo '<li>';
    echo '<input type="checkbox" onclick="toggleSelectListRow2(this);" data-dojo-type="dijit.form.CheckBox"/>';
    echo '<a target="_blank" class="fb_feedUrl" href="';
    echo $feed_url;
    echo '">';
    echo '<img src="images/pub_set.svg" style="vertical-align: middle" />';
    echo '</a>';
    echo '<a target="_blank" href="$site_url">';
    echo '<span class="fb_feedTitle">';
    echo $title;
    echo '</span>';
    echo '</a>';
    echo '<span class="subscribers">(';
    echo $subscribers;
    echo ')</span>';
    echo '</li>';
}
