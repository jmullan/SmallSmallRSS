<?php
if (file_exists("install") && !file_exists("config.php")) {
    header("Location: install/");
}

if (!file_exists("config.php")) {
    print "<b>Fatal Error</b>: You forgot to copy
                <b>config.php-dist</b> to <b>config.php</b> and edit it.\n";
    exit;
}

if (version_compare(PHP_VERSION, '5.3.0', '<')) {
    print "<b>Fatal Error</b>: PHP version 5.3.0 or newer required.\n";
    exit;
}
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/SmallSmallRSS/bootstrap.php';

\SmallSmallRSS\Session::init();
\SmallSmallRSS\Sanity::initialCheck();
require_once "lib/Mobile_Detect.php";

$mobile = new Mobile_Detect();

if (!init_plugins()) {
    return;
}

if (empty($_REQUEST['mobile'])) {
    if ($mobile->isTablet()
        && \SmallSmallRSS\PluginHost::getInstance()->get_plugin("digest")) {
        header('Location: backend.php?op=digest');
        exit;
    } elseif ($mobile->isMobile()
              && \SmallSmallRSS\PluginHost::getInstance()->get_plugin("mobile")) {
        header('Location: backend.php?op=mobile');
        exit;
    } elseif ($mobile->isMobile()
              && \SmallSmallRSS\PluginHost::getInstance()->get_plugin("digest")) {
        header('Location: backend.php?op=digest');
        exit;
    }
}

login_sequence();

header('Content-Type: text/html; charset=utf-8');



require 'lib/jshrink/Minifier.php';


$theme_css = 'themes/default.css';
if ($_SESSION["uid"]) {
    $theme = \SmallSmallRSS\DBPrefs::read("USER_CSS_THEME", $_SESSION["uid"], false);
    if ($theme && file_exists("themes/$theme")) {
        $theme_css = "themes/$theme";
    }
}
$toolbar_plugins = \SmallSmallRSS\PluginHost::getInstance()->get_hooks(
    \SmallSmallRSS\PluginHost::HOOK_TOOLBAR_BUTTON
);
$action_items = \SmallSmallRSS\PluginHost::getInstance()->get_hooks(
    \SmallSmallRSS\PluginHost::HOOK_ACTION_ITEM
);

?>
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html>
<head>
  <title>Small Small RSS</title>
  <?php stylesheet_tag("lib/dijit/themes/claro/claro.css"); ?>
  <?php stylesheet_tag("css/layout.css"); ?>
  <?php stylesheet_tag($theme_css); ?>
  <?php print_user_stylesheet() ?>
  <style type="text/css">
<?php
foreach (\SmallSmallRSS\PluginHost::getInstance()->get_plugins() as $n => $p) {
    if (method_exists($p, "get_css")) {
        echo $p->get_css();
    }
}
?>
  </style>
  <link rel="shortcut icon" type="image/png" href="images/favicon.png"/>
  <link rel="icon" type="image/png" sizes="72x72" href="images/favicon-72px.png" />
  <?php
foreach (array("lib/prototype.js",
               "lib/scriptaculous/scriptaculous.js?load=effects,dragdrop,controls",
               "lib/dojo/dojo.js",
               "lib/dojo/tt-rss-layer.js",
               "errors.php?mode=js") as $jsfile) {
    javascript_tag($jsfile);
}
?>
  <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
</head>

<body id="ttrssMain" class="claro">

<div id="overlay" style="display: block">
  <div id="overlay_inner">
    <div class="insensitive"><?php echo __("Loading, please wait...") ?></div>
    <div dojoType="dijit.ProgressBar" places="0" style="width : 300px" id="loading_bar" progress="0" maximum="100"></div>
    <noscript><br/><?php \SmallSmallRSS\Renderers\Messages::renderError('Javascript is disabled. Please enable it.') ?></noscript>
  </div>
</div>
<div id="notify" class="notify" style="display : none"></div>
<div id="cmdline" style="display : none"></div>
<div id="headlines-tmp" style="display : none"></div>
<div id="main" dojoType="dijit.layout.BorderContainer">
  <div id="feeds-holder" dojoType="dijit.layout.ContentPane" region="leading" style="width : 20%" splitter="true">
    <div id="feedlistLoading">
      <img src="images/indicator_tiny.gif" />
      <?php echo  __("Loading, please wait..."); ?>
    </div>
    <div id="feedTree"></div>
  </div>
  <div dojoType="dijit.layout.BorderContainer" region="center" id="header-wrap" gutters="false">
    <div dojoType="dijit.layout.BorderContainer" region="center" id="content-wrap">
      <div id="toolbar" dojoType="dijit.layout.ContentPane" region="top">
        <div id="main-toolbar" dojoType="dijit.Toolbar">
          <form id="main_toolbar_form" action="" onsubmit="return false;">
          <button dojoType="dijit.form.Button" id="collapse_feeds_btn"
            onclick="collapse_feedlist()"
            title="<?php echo __('Collapse feedlist') ?>" style="display : inline">
            &lt;&lt;
          </button>
          <select name="view_mode" title="<?php echo __('Show articles') ?>"
            onchange="viewModeChanged()"
            dojoType="dijit.form.Select">
            <option selected="selected" value="adaptive"><?php echo __('Adaptive') ?></option>
            <option value="all_articles"><?php echo __('All Articles') ?></option>
            <option value="marked"><?php echo __('Starred') ?></option>
            <option value="published"><?php echo __('Published') ?></option>
            <option value="unread"><?php echo __('Unread') ?></option>
            <option value="unread_first"><?php echo __('Unread First') ?></option>
            <option value="has_note"><?php echo __('With Note') ?></option>
          </select>
          <select title="<?php echo __('Sort articles') ?>"
            onchange="viewModeChanged()"
            dojoType="dijit.form.Select" name="order_by">
            <option selected="selected" value="default"><?php echo __('Default') ?></option>
            <option value="feed_dates"><?php echo __('Newest first') ?></option>
            <option value="date_reverse"><?php echo __('Oldest first') ?></option>
            <option value="title"><?php echo __('Title') ?></option>
          </select>
          <div dojoType="dijit.form.ComboButton" onclick="catchupCurrentFeed()">
            <span><?php echo __('Mark as read') ?></span>
            <div dojoType="dijit.DropDownMenu">
              <div dojoType="dijit.MenuItem" onclick="catchupCurrentFeed('1day')">
                <?php echo __('Older than one day') ?>
              </div>
              <div dojoType="dijit.MenuItem" onclick="catchupCurrentFeed('1week')">
                <?php echo __('Older than one week') ?>
              </div>
              <div dojoType="dijit.MenuItem" onclick="catchupCurrentFeed('2week')">
                <?php echo __('Older than two weeks') ?>
              </div>
            </div>
          </div>
        </form>
        <div class="actionChooser">
<?php
foreach ($toolbar_plugins as $p) {
    echo $p->hook_toolbar_button();
}
?>
          <button id="net-alert" dojoType="dijit.form.Button" style="display : none" disabled="true"
            title="<?php echo __("Communication problem with server.") ?>">
          <img src="images/alert.png" />
        </button>
        <div dojoType="dijit.form.DropDownButton">
          <span><?php echo __('Actions...') ?></span>
          <div dojoType="dijit.Menu" style="display: none">
            <div dojoType="dijit.MenuItem" onclick="quickMenuGo('qmcPrefs')"><?php echo __('Preferences...') ?></div>
            <div dojoType="dijit.MenuItem" onclick="quickMenuGo('qmcSearch')"><?php echo __('Search...') ?></div>
            <div dojoType="dijit.MenuItem" disabled="1"><?php echo __('Feed actions:') ?></div>
            <div dojoType="dijit.MenuItem" onclick="quickMenuGo('qmcAddFeed')"><?php echo __('Subscribe to feed...') ?></div>
            <div dojoType="dijit.MenuItem" onclick="quickMenuGo('qmcEditFeed')"><?php echo __('Edit this feed...') ?></div>
            <div dojoType="dijit.MenuItem" onclick="quickMenuGo('qmcRemoveFeed')"><?php echo __('Unsubscribe') ?></div>
            <div dojoType="dijit.MenuItem" disabled="1"><?php echo __('All feeds:') ?></div>
            <div dojoType="dijit.MenuItem" onclick="quickMenuGo('qmcCatchupAll')"><?php echo __('Mark as read') ?></div>
            <div dojoType="dijit.MenuItem" onclick="quickMenuGo('qmcShowOnlyUnread')"><?php echo __('(Un)hide read feeds') ?></div>
            <div dojoType="dijit.MenuItem" disabled="1"><?php echo __('Other actions:') ?></div>
            <div dojoType="dijit.MenuItem" onclick="quickMenuGo('qmcToggleWidescreen')"><?php echo __('Toggle widescreen mode') ?></div>
            <div dojoType="dijit.MenuItem" onclick="quickMenuGo('qmcTagSelect')"><?php echo __('Select by tags...') ?></div>
            <div dojoType="dijit.MenuItem" onclick="quickMenuGo('qmcHKhelp')"><?php echo __('Keyboard shortcuts help') ?></div>
<?php
foreach ($action_items as $p) {
    echo $p->hook_action_item();
}
?>

<?php
if (empty($_SESSION["hide_logout"])) {
?>
            <div dojoType="dijit.MenuItem" onclick="quickMenuGo('qmcLogout')"><?php echo __('Logout') ?></div>
<?php
}
?>
          </div>
        </div>
      </div>
    </div> <!-- toolbar -->
  </div> <!-- toolbar pane -->
  <div id="headlines-wrap-inner" dojoType="dijit.layout.BorderContainer" region="center">
    <div id="headlines-toolbar" dojoType="dijit.layout.ContentPane" region="top"></div>
      <div id="floatingTitle" style="display : none"></div>
        <div id="headlines-frame" dojoType="dijit.layout.ContentPane"
          onscroll="headlines_scroll_handler(this)" region="center">
          <div id="headlinesInnerContainer">
            <div class="whiteBox"><?php echo __('Loading, please wait...') ?></div>
          </div>
        </div>
        <div id="content-insert" dojoType="dijit.layout.ContentPane" region="bottom" style="height : 50%" splitter="true">
        </div>
      </div>
    </div>
  </div>
</div>


<script type="text/javascript">
require({cache:{}});
<?php
print get_minified_js(array("tt-rss", "functions", "feedlist", "viewfeed", "FeedTree", "PluginHost"));
foreach (\SmallSmallRSS\PluginHost::getInstance()->get_plugins() as $n => $p) {
    if (method_exists($p, "get_js")) {
        echo JShrink\Minifier::minify($p->get_js());
    }
}
init_js_translations();
?>
</script>
<script type="text/javascript">
  Event.observe(window, 'load', function() {
    init();
  });
</script>

</body>
</html>
