<?php
foreach ($lines as $line) {
    $id = $line['id'];
    $feed_url = htmlspecialchars($line['feed_url']);
    $site_url = htmlspecialchars($line['site_url']);
    $title = htmlspecialchars($line['title']);

    $archived = sprintf(
        ngettext(
            '%d archived article',
            '%d archived articles',
            $line['articles_archived']
        ),
        $line['articles_archived']
    );
    echo '<li id="FBROW-';
    echo $id;
    echo '">';
    echo '<input type="checkbox" onclick="toggleSelectListRow2(this);" data-dojo-type="dijit.form.CheckBox" />';
    echo '<a href="';
    echo $feed_url;
    echo '" target="_blank" class="fb_feedUrl">';
    echo '<img src="images/pub_set.svg" style="vertical-align: middle" />';
    echo '</a>';
    echo '<a href="';
    echo  $site_url;
    echo '" target="_blank">';
    echo '<span class="fb_feedTitle">';
    echo $title;
    echo '</span>';
    echo '</a>';
    if ($line['articles_archived'] > 0) {
        echo '<span class="subscribers">(';
        echo $archived;
        echo ')</span>';
    }
    echo '</li>';
}
