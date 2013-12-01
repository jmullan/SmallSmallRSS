<?php
foreach ($lines as $line) {
    $feed_url = htmlspecialchars($line["feed_url"]);
    $site_url = htmlspecialchars($line["site_url"]);
    $title = htmlspecialchars($line["title"]);
    $subscribers = htmlspecialchars($line["subscribers"]);

?>
<li>
<input type="checkbox" onclick="toggleSelectListRow2(this);" data-dojo-type="dijit.form.CheckBox"/>
<a target="_blank" class="fb_feedUrl" href="<?php echo $feed_url; ?>">
  <img src="images/pub_set.svg" style="vertical-align: middle" />
</a>
<a target="_blank" href="$site_url">
  <span class="fb_feedTitle"><?php echo $title; ?></span>
</a>
<span class="subscribers">(<?php echo $subscribers; ?>)</span>
</li>
<?php
}
