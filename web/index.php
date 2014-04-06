<?php
if (file_exists(__DIR__ . '/install') && !file_exists(__DIR__ . '/../config.ini')) {
    header('Location: install/');
}

if (!file_exists(__DIR__ . '/../config.ini')) {
    print "<b>Fatal Error</b>: You forgot to copy
           <b>config.ini-dist</b> to <b>config.ini</b> and edit it.\n";
    exit;
}

if (version_compare(PHP_VERSION, '5.3.0', '<')) {
    print "<b>Fatal Error</b>: PHP version 5.3.0 or newer required.\n";
    exit;
}
require_once __DIR__ . '/../src/SmallSmallRSS/bootstrap.php';
\SmallSmallRSS\Sessions::init();
$mobile = new \Mobile_Detect();
if (!\SmallSmallRSS\PluginHost::initAll()) {
    return;
}

if (empty($_REQUEST['mobile'])) {
    if ($mobile->isTablet()
        && \SmallSmallRSS\PluginHost::getInstance()->getPlugin('digest')) {
        header('Location: backend.php?op=digest');
        exit;
    } elseif ($mobile->isMobile()
              && \SmallSmallRSS\PluginHost::getInstance()->getPlugin('mobile')) {
        header('Location: backend.php?op=mobile');
        exit;
    } elseif ($mobile->isMobile()
              && \SmallSmallRSS\PluginHost::getInstance()->getPlugin('digest')) {
        header('Location: backend.php?op=digest');
        exit;
    }
}

login_sequence();

header('Content-Type: text/html; charset=utf-8');

$theme_css = 'themes/default.css';
if ($_SESSION['uid']) {
    $theme = \SmallSmallRSS\DBPrefs::read('USER_CSS_THEME', $_SESSION['uid'], false);
    if ($theme && file_exists("themes/$theme")) {
        $theme_css = "themes/$theme";
    }
}
$js_renderer = new \SmallSmallRSS\Renderers\JS();
$js_files = array(
    'lib/prototype.js',
    'lib/scriptaculous/scriptaculous.js?load=effects,dragdrop,controls',
    'lib/dojo/dojo.js',
    'lib/dojo/tt-rss-layer.js',
    'errors.php?mode=js'
);
?>
<!DOCTYPE html>
<html>
<head>
  <title>Small Small RSS</title>
<?php
$stylesheet_tag_renderer = new \SmallSmallRSS\Renderers\CSS();
$stylesheet_tag_renderer->renderStylesheetTag('lib/dijit/themes/claro/claro.css');
$stylesheet_tag_renderer->renderStylesheetTag('css/layout.css');
$stylesheet_tag_renderer->renderStylesheetTag($theme_css);

$stylesheet_renderer = new \SmallSmallRSS\Renderers\CSS();
$stylesheet_renderer->renderUserStyleSheet();
?>

  <style type="text/css">
<?php
foreach (\SmallSmallRSS\PluginHost::getInstance()->getPlugins() as $p) {
    echo $p->getCSS();
}
?>
  </style>
  <link rel="shortcut icon" type="image/png" href="images/favicon.png"/>
  <link rel="icon" type="image/png" sizes="72x72" href="images/favicon-72px.png" />
<?php
foreach ($js_files as $jsfile) {
    echo "<!-- $jsfile -->";
    $js_renderer->render_script_tag($jsfile);
}
?>
  <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
</head>

<body id="ttrssMain" class="claro">

<div id="overlay">
  <div id="overlay_inner">
    <div class="insensitive"><?php echo __('Loading, please wait...'); ?></div>
    <div id="loading_bar" data-dojo-type="dijit.ProgressBar" places="0" progress="0" maximum="100"></div>
    <noscript><br/><?php \SmallSmallRSS\Renderers\Messages::renderError('Javascript is disabled. Please enable it.') ?></noscript>
  </div>
</div>
<div id="notify" class="notify" style="display: none"></div>
<div id="cmdline" style="display: none"></div>
<div id="headlines-tmp" style="display: none"></div>
<div id="main" data-dojo-type="dijit.layout.BorderContainer">
  <div id="feeds-holder" data-dojo-type="dijit.layout.ContentPane"
    data-dojo-props="region: 'leading', splitter: true">
    <div id="feedlistLoading">
      <img src="images/indicator_tiny.gif" />
      <?php echo  __('Loading, please wait...'); ?>
    </div>
    <div id="feedTree"></div>
  </div>
  <div data-dojo-type="dijit.layout.BorderContainer" data-dojo-props="region: 'center', gutters: false" id="header-wrap">
    <div data-dojo-type="dijit.layout.BorderContainer" data-dojo-props="region: 'center'" id="content-wrap">
      <div id="toolbar" data-dojo-type="dijit.layout.ContentPane" data-dojo-props="region: 'top'">
        <div id="main-toolbar" data-dojo-type="dijit.Toolbar">
          <form id="main_toolbar_form" action="" onsubmit="return false;">
          <button id="collapse_feeds_btn" data-dojo-type="dijit.form.Button"
            onclick="collapse_feedlist()"
            title="<?php echo __('Collapse feedlist') ?>">
            &lt;&lt;
          </button>
          <select name="view_mode" title="<?php echo __('Show articles') ?>"
            onchange="viewModeChanged()"
            data-dojo-type="dijit.form.Select">
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
            data-dojo-type="dijit.form.Select" name="order_by">
            <option selected="selected" value="default"><?php echo __('Default') ?></option>
            <option value="feed_dates"><?php echo __('Newest first') ?></option>
            <option value="date_reverse"><?php echo __('Oldest first') ?></option>
            <option value="title"><?php echo __('Title') ?></option>
          </select>
          <div data-dojo-type="dijit.form.ComboButton" onclick="catchupCurrentFeed()">
            <span><?php echo __('Mark as read') ?></span>
            <div data-dojo-type="dijit.DropDownMenu">
              <div data-dojo-type="dijit.MenuItem" onclick="catchupCurrentFeed('1day')">
                <?php echo __('Older than one day') ?>
              </div>
              <div data-dojo-type="dijit.MenuItem" onclick="catchupCurrentFeed('1week')">
                <?php echo __('Older than one week') ?>
              </div>
              <div data-dojo-type="dijit.MenuItem" onclick="catchupCurrentFeed('2week')">
                <?php echo __('Older than two weeks') ?>
              </div>
            </div>
          </div>
        </form>
        <div class="actionChooser">
<?php
$toolbar_plugins = \SmallSmallRSS\PluginHost::getInstance()->runHooks(
    \SmallSmallRSS\Hooks::RENDER_TOOLBAR_BUTTON
);
?>
          <button id="net-alert" data-dojo-type="dijit.form.Button" style="display: none" disabled="true"
            title="<?php echo __('Communication problem with server.') ?>">
          <img src="images/alert.png" />
        </button>
        <div data-dojo-type="dijit.form.DropDownButton">
          <span><?php echo __('Actions...') ?></span>
          <div data-dojo-type="dijit.Menu" style="display: none">
            <div data-dojo-type="dijit.MenuItem" onclick="quickMenuGo('qmcPrefs')"><?php echo __('Preferences...') ?></div>
            <div data-dojo-type="dijit.MenuItem" onclick="quickMenuGo('qmcSearch')"><?php echo __('Search...') ?></div>
            <div data-dojo-type="dijit.MenuItem" disabled="1"><?php echo __('Feed actions:') ?></div>
            <div data-dojo-type="dijit.MenuItem" onclick="quickMenuGo('qmcAddFeed')"><?php echo __('Subscribe to feed...') ?></div>
            <div data-dojo-type="dijit.MenuItem" onclick="quickMenuGo('qmcEditFeed')"><?php echo __('Edit this feed...') ?></div>
            <div data-dojo-type="dijit.MenuItem" onclick="quickMenuGo('qmcRemoveFeed')"><?php echo __('Unsubscribe') ?></div>
            <div data-dojo-type="dijit.MenuItem" disabled="1"><?php echo __('All feeds:') ?></div>
            <div data-dojo-type="dijit.MenuItem" onclick="quickMenuGo('qmcCatchupAll')"><?php echo __('Mark as read') ?></div>
            <div data-dojo-type="dijit.MenuItem" onclick="quickMenuGo('qmcShowOnlyUnread')"><?php echo __('(Un)hide read feeds') ?></div>
            <div data-dojo-type="dijit.MenuItem" disabled="1"><?php echo __('Other actions:') ?></div>
            <div data-dojo-type="dijit.MenuItem" onclick="quickMenuGo('qmcToggleWidescreen')"><?php echo __('Toggle widescreen mode') ?></div>
            <div data-dojo-type="dijit.MenuItem" onclick="quickMenuGo('qmcTagSelect')"><?php echo __('Select by tags...') ?></div>
            <div data-dojo-type="dijit.MenuItem" onclick="quickMenuGo('qmcHKhelp')"><?php echo __('Keyboard shortcuts help') ?></div>
<?php
\SmallSmallRSS\PluginHost::getInstance()->runHooks(\SmallSmallRSS\Hooks::RENDER_ACTION_ITEM);
?>

<?php
if (empty($_SESSION['hide_logout'])) {
?>
    <div data-dojo-type="dijit.MenuItem" onclick="quickMenuGo('qmcLogout')"><?php echo __('Logout'); ?></div>
<?php
}
?>
          </div>
        </div>
      </div>
    </div> <!-- toolbar -->
  </div> <!-- toolbar pane -->
  <div id="headlines-wrap-inner" data-dojo-type="dijit.layout.BorderContainer" data-dojo-props="region: 'center'">
    <div id="headlines-toolbar" data-dojo-type="dijit.layout.ContentPane" data-dojo-props="region: 'top'"></div>
      <div id="floatingTitle" style="display: none"></div>
        <div id="headlines-frame" data-dojo-type="dijit.layout.ContentPane"
          onscroll="headlines_scroll_handler(this)" data-dojo-props="region: 'center'">
          <div id="headlinesInnerContainer">
            <div class="whiteBox"><?php echo __('Loading, please wait...'); ?></div>
          </div>
        </div>
        <div id="content-insert" data-dojo-type="dijit.layout.ContentPane"
          data-dojo-props="region: 'bottom', splitter: true">
        </div>
      </div>
    </div>
  </div>
</div>


<script type="text/javascript">
require({cache:{}});
<?php
$js_renderer->render_minified_js_files(
    array('tt-rss', 'functions', 'feedlist', 'viewfeed', 'FeedTree', 'PluginHost')
);
foreach (\SmallSmallRSS\PluginHost::getInstance()->getPlugins() as $n => $p) {
    $js_renderer->render_minified($p->getJavascript());
}
$translation_renderer = new \SmallSmallRSS\Renderers\JSTranslations();
$translation_renderer->render();
?>
</script>
<script type="text/javascript">
  Event.observe(window, 'load', function() {
    init();
  });
</script>

</body>
</html>
