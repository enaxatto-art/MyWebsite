<?php
require_once('config.php');
?>


<?php
// index.php — Redirect to the split login page
header('Location: auth.php');
exit;
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta http-equiv="refresh" content="0;url=auth.php" />
  <title>Redirecting...</title>
</head>
<body>
  If you are not redirected automatically, <a href="auth.php">continue to Login</a>.
  <script>location.replace('auth.php');</script>
</body>
</html>
