<?php
if (file_exists('install') && !file_exists('config.ini')) {
    header('Location: install/');
}

if (!file_exists('config.ini')) {
    print '<b>Fatal Error</b>:';
    print "You forgot to copy <b>config.ini-dist</b> to <b>config.ini</b> and edit it.\n";
    exit;
}
require_once __DIR__ . '/../src/SmallSmallRSS/bootstrap.php';
\SmallSmallRSS\Sessions::init();

if (!\SmallSmallRSS\PluginHost::init_all()) {
    return;
}
login_sequence();
header('Content-Type: text/html; charset=utf-8');

$css_renderer = new \SmallSmallRSS\Renderers\CSS();
$theme_css = 'default.css';
if ($_SESSION['uid']) {
    $theme = \SmallSmallRSS\DBPrefs::read('USER_CSS_THEME', $_SESSION['uid'], false);
    if ($theme && file_exists("themes/$theme")) {
        $theme_css = "themes/$theme";
    }
}
$js_renderer = new \SmallSmallRSS\Renderers\JS();
$translation_renderer = new \SmallSmallRSS\Renderers\JSTranslations();

?>
<!DOCTYPE html>
<html>
<head>
  <title>Small Small RSS : <?php echo __('Preferences') ?></title>
<?php

$css_renderer->renderStylesheetTag('lib/dijit/themes/claro/claro.css');
$css_renderer->renderStylesheetTag('css/layout.css');
$css_renderer->renderStylesheetTag("themes/$theme");
$stylesheet_renderer = new \SmallSmallRSS\Renderers\CSS();
$stylesheet_renderer->renderUserStyleSheet();
?>
  <link rel="shortcut icon" type="image/png" href="images/favicon.png"/>
  <link rel="icon" type="image/png" sizes="72x72" href="images/favicon-72px.png" />
<?php
foreach (array('lib/prototype.js',
               'lib/scriptaculous/scriptaculous.js?load=effects,dragdrop,controls',
               'lib/dojo/dojo.js',
               'lib/dojo/tt-rss-layer.js',
               'errors.php?mode=js') as $jsfile) {
    $js_renderer = new \SmallSmallRSS\Renderers\JS();
    $js_renderer->render_script_tag($jsfile);
}
?>
<script type="text/javascript">
    require({cache:{}});
<?php
foreach (\SmallSmallRSS\PluginHost::getInstance()->get_plugins() as $n => $p) {
    $js_renderer->render_minified($p->getPreferencesJavascript());
}
$js_renderer->render_minified_js_files(
    array('../lib/CheckBoxTree', 'functions', 'deprecated', 'prefs', 'PrefFeedTree', 'PrefFilterTree', 'PrefLabelTree')
);
$translation_renderer->render();
?>
</script>
<meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
<script type="text/javascript">
  Event.observe(window, 'load', function() {
    init();
  });
</script>
</head>
<body id="ttrssPrefs" class="claro">
<div id="notify" class="notify" style="display: none"></div>
<div id="cmdline" style="display: none"></div>
<div id="overlay">
  <div id="overlay_inner">
    <div class="insensitive"><?php echo __('Loading, please wait...') ?></div>
    <div data-dojo-type="dijit.ProgressBar" places="0" style="width: 300px" id="loading_bar"
      progress="0" maximum="100">
    </div>
    <noscript>
      <br/>
      <?php \SmallSmallRSS\Renderers\Messages::renderError('Javascript is disabled. Please enable it.'); ?>
    </noscript>
    </div>
    </div>

    <div id="header" data-dojo-type="dijit.layout.ContentPane" data-dojo-props="region: 'top'">
      <a href="#" onclick="gotoMain()"><?php echo __('Exit preferences') ?></a>
    </div>

    <div id="main" data-dojo-type="dijit.layout.BorderContainer">

    <div data-dojo-type="dijit.layout.TabContainer" data-dojo-props="region: 'center'" id="pref-tabs">
    <div id="genConfigTab" data-dojo-type="dijit.layout.ContentPane"
    href="backend.php?op=pref-prefs"
    title="<?php echo __('Preferences') ?>"></div>
    <div id="feedConfigTab" data-dojo-type="dijit.layout.ContentPane"
    href="backend.php?op=pref-feeds"
    title="<?php echo __('Feeds') ?>"></div>
    <div id="filterConfigTab" data-dojo-type="dijit.layout.ContentPane"
    href="backend.php?op=pref-filters"
    title="<?php echo __('Filters') ?>"></div>
    <div id="labelConfigTab" data-dojo-type="dijit.layout.ContentPane"
    href="backend.php?op=pref-labels"
    title="<?php echo __('Labels') ?>"></div>
<?php
if ($_SESSION['access_level'] >= 10) {
?>
    <div id="userConfigTab" data-dojo-type="dijit.layout.ContentPane"
     href="backend.php?op=pref-users"
     title="<?php echo __('Users') ?>"></div>
     <div id="systemConfigTab" data-dojo-type="dijit.layout.ContentPane"
     href="backend.php?op=pref-system"
     title="<?php echo __('System') ?>"></div>
<?php
}
?>
<?php
     \SmallSmallRSS\PluginHost::getInstance()->runHooks(\SmallSmallRSS\Hooks::RENDER_PREFS_TABS);
?>
      </div>
      <div id="footer" data-dojo-type="dijit.layout.ContentPane" data-dojo-props="region: 'bottom'">
        <a class="insensitive" target="_blank" href="http://tt-rss.org/">
          Tiny Tiny RSS</a>
          &copy; 2005-<?php echo date('Y') ?>
        <a class="insensitive" target="_blank" href="http://fakecake.org/">Andrew Dolgov</a>
      </div>
    </div>
  </body>
</html>
