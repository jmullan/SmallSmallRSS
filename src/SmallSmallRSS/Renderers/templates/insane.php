<!DOCTYPE html>
<html>
<head>
  <title>Startup failed</title>
  <meta http-equiv="Content-Type" content="text/html; charset=utf-8">
  <link rel="stylesheet" type="text/css" href="css/utility.css">
</head>
<body>
  <div class="floatingLogo"><img src="images/logo_small.png"></div>
  <div class="content">
  <h1>Startup failed</h1>
  <p>
     Tiny Tiny RSS was unable to start properly. This usually means a misconfiguration or an incomplete upgrade.
     Please fix errors indicated by the following messages:
  </p>
<?php
foreach ($errors as $error) {
     \SmallSmallRSS\Renderers\Messages::renderError($error);
}
?>
  <p>
    You might want to check tt-rss <a href="http://tt-rss.org/wiki">wiki</a>
    or the <a href="http://tt-rss.org/forum">forums</a> for more information.
    Please search the forums before creating new topic for your question.
  </p>
</div>
</body>
</html>
