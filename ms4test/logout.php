<?php
require_once('session_check.inc');
destroySession();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <title>Logging Out...</title>
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" />
  <script>
    // Clear frontend auth state and redirect immediately
    window.onload = function () {
      localStorage.removeItem('loggedIn');
      localStorage.removeItem('username');
      window.location.href = 'login.html';
    };
  </script>
  <noscript>
    <meta http-equiv="refresh" content="3;url=login.html">
  </noscript>
</head>
<body class="d-flex justify-content-center align-items-center vh-100 bg-light">
  <div class="text-center">
    <div class="spinner-border text-primary" role="status"></div>
    <p class="mt-3">Logging out... Redirecting you to login.</p>
    <p><a href="login.html" class="text-muted small">Click here if you are not redirected automatically.</a></p>
  </div>
</body>
</html>
