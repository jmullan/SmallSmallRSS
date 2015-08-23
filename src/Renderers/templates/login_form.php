<?php
$uid = null;
if (isset($_SESSION['uid'])) {
    $uid = $_SESSION['uid'];
}
\SmallSmallRSS\Locale::startupGettext($uid);
$login_error_msg = isset($_SESSION['login_error_msg']) ? $_SESSION['login_error_msg'] : '';
$fake_login = isset($fake_login) ? $fake_login : '';
$fake_password = isset($_SESSION['fake_password']) ? $_SESSION['fake_password'] : '';
$return = urlencode($_SERVER['REQUEST_URI']);
$software_name = \SmallSmallRSS\Config::get('SOFTWARE_NAME');
?>
<!DOCTYPE html>
<html>
<head>
  <title><?php echo $software_name; ?> : Login</title>
  <link rel="stylesheet" type="text/css" href="lib/dijit/themes/claro/claro.css"/>
  <link rel="stylesheet" type="text/css" href="css/tt-rss.css">
  <link rel="stylesheet" type="text/css" href="css/login.css">
  <link rel="shortcut icon" type="image/png" href="images/favicon.png">
  <meta http-equiv="Content-Type" content="text/html; charset=utf-8">
  <script type="text/javascript" src="lib/dojo/dojo.js"></script>
  <script type="text/javascript" src="lib/dojo/tt-rss-layer.js"></script>
  <script type="text/javascript" src="lib/prototype.js"></script>
  <script type="text/javascript" src="js/functions.js"></script>
  <script type="text/javascript" src="js/login.js"></script>
  <script type="text/javascript" charset="utf-8" src="errors.php?mode=js"></script>
</head>

<body id="ttrssLogin" class="claro">

<form action="public.php?return=<?php echo $return ?>"
  data-dojo-type="dijit.form.Form" method="POST" id="loginForm" name="loginForm">
  <input data-dojo-type="dijit.form.TextBox" style="display: none" name="op" value="login" />
  <div class='header'>
    <img src="images/logo_wide.png" />
  </div>
  <div class='form'>
    <fieldset>
<?php
if (!empty($login_error_msg)) { ?>
      <div class="row-error"><?php echo $login_error_msg ?></div>
<?php $login_error_msg = ''; ?>
<?php
}
?>
      <div class="row">
        <label for="login"><?php echo __('Login:') ?></label>
        <input id="login" name="login" class="input"
          onchange="fetchProfiles()" onfocus="fetchProfiles()" onblur="fetchProfiles()"
          required="1"
          value="<?php echo $fake_login ?>" />
    </div>


    <div class="row">
      <label for="password"><?php echo __('Password:') ?></label>
      <input id="password" type="password" name="password" required="1" class="input"
        value="<?php echo $fake_password ?>"/>
<?php
if (strpos(\SmallSmallRSS\Config::get('PLUGINS'), 'auth_internal') !== false) { ?>
      <a class='forgotpass' href="public.php?op=forgotpass"><?php echo __('I forgot my password') ?></a>
    <?php
}
?>
    </div>


    <div class="row">
      <label><?php echo __('Profile:') ?></label>
      <span id='profile_box'>
        <select disabled='disabled' data-dojo-type='dijit.form.Select' style='width: 220px; margin : 0px'>
          <option><?php echo __('Default profile') ?></option>
        </select>
      </span>
    </div>

    <div class="row">
      <label>&nbsp;</label>
      <input data-dojo-type="dijit.form.CheckBox" name="bw_limit" id="bw_limit" type="checkbox"
        onchange="bwLimitChange(this)">
      <label id="bw_limit_label" for="bw_limit"><?php echo __('Use less traffic') ?></label>
    </div>

    <div data-dojo-type="dijit.Tooltip" connectId="bw_limit_label" position="below" style="display:none">
<?php echo __('Does not display images in articles, reduces automatic refreshes.'); ?>
    </div>

    <?php
    if (\SmallSmallRSS\Config::get('SESSION_COOKIE_LIFETIME') > 0) { ?>

    <div class="row">
      <label>&nbsp;</label>
      <input data-dojo-type="dijit.form.CheckBox" name="remember_me" id="remember_me" type="checkbox" checked="checked">
      <label for="remember_me"><?php echo __('Remember me') ?></label>
    </div>

    <?php
    }
    ?>

      <div class="row" style='text-align : right'>
        <button data-dojo-type="dijit.form.Button" type="submit"><?php echo __('Log in') ?></button>
        <?php
        if (\SmallSmallRSS\Config::get('ENABLE_REGISTRATION')) { ?>
        <button onclick="return gotoRegForm()" data-dojo-type="dijit.form.Button">
            <?php echo __('Create new account') ?>
        </button>
        <?php
        }
?>
      </div>
    </fieldset>
  </div>
  <div class="footer">
    <a href="http://tt-rss.org/"><?php echo $software_name; ?></a>
    &copy; 2005&ndash;<?php echo date('Y') ?> <a href="http://fakecake.org/">Andrew Dolgov</a>
  </div>
</form>

<script type="text/javascript">
function bwLimitChange(elem) {
    try {
        var limit_set = elem.checked;
        setCookie("ttrss_bwlimit", limit_set, <?php print \SmallSmallRSS\Config::get('SESSION_COOKIE_LIFETIME') ?>);
    } catch (e) {
        exception_error("bwLimitChange", e);
    }
}
</script>
<script type="text/javascript">
  require({cache:{}});
  Event.observe(window, 'load', function () {
    init();
  });
</script>

</body>
</html>
