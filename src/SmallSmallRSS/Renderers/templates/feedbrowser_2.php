<?php
foreach ($lines as $line) {
    $id = $line["id"];
    $feed_url = htmlspecialchars($line["feed_url"]);
    $site_url = htmlspecialchars($line["site_url"]);
    $title = htmlspecialchars($line["title"]);

    $archived = sprintf(
        ngettext(
            "%d archived article",
            "%d archived articles",
            $line['articles_archived']
        ),
        $line['articles_archived']
    );
?>
<li id="FBROW-<?php echo $id; ?>">
  <input onclick="toggleSelectListRow2(this);" dojoType="dijit.form.CheckBox" type="checkbox" />
  <a target="_blank" class="fb_feedUrl" href="<?php echo $feed_url; ?>">
    <img src="images/pub_set.svg" style="vertical-align: middle" />
  </a>
  <a target="_blank" href="$site_url">
    <span class="fb_feedTitle"><?php echo $title; ?></span>
  </a>
<?php
  if ($line['articles_archived'] > 0) {
?>
    <span class='subscribers'>(<?php echo $archived; ?>)</span>
<?php
  }
?>
</li>
<?php
}
