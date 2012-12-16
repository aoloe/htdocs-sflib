<?php
$template_head = <<<EOT
<!DOCTYPE html>
<html>
<head>
  <meta charset="utf-8">
  <title>Comments demo</title>
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

include_once('comment.php');
$comment = new Comment();
$comment->set_uri('demo');
// $comment->set_user_rights(COMMENT_USER_RIGHTS_EDITOR);
$comment->process();
echo($template_head);
echo($template_body_start);
echo $comment->get_rendering();
echo($template_body_end);
