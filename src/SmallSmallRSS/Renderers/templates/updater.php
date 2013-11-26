<html>
<head>
    <title>Tiny Tiny RSS data update script.</title>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8">
    <link rel="stylesheet" type="text/css" href="css/utility.css">
</head>
<body>
    <div class="floatingLogo"><img src="images/logo_small.png"></div>
    <h1><?php echo __("Tiny Tiny RSS data update script.") ?></h1>
<?php
\SmallSmallRSS\Renderers\Messages::renderError(
    'Please run this script from the command line.'
    . ' Use option "-help" to display command help if this error is displayed erroneously.'
);
?>
</body>
</html>
