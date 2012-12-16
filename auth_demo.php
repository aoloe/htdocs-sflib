<?php
$template_head = <<<EOT
<!DOCTYPE html>
<html>
<head>
  <meta charset="utf-8">
  <title>Authentication demo</title>
  <link rel="stylesheet" href="css/style.css"> 
</head>
EOT;
$template_body_start = <<<EOT
<body>
EOT;
$template_body_end = <<<EOT
</body>
</html>
EOT;

include_once('auth.php');
$auth = new Auth();
$auth->set_uri('demo');
// $auth->set_auth_rights(COMMENT_USER_RIGHTS_EDITOR);
$auth->process();
echo($template_head);
echo($template_body_start);
echo $auth->get_rendering_login();
if ($auth->wants_registering()) {
    echo $auth->get_rendering_registering();
}
echo($template_body_end);
