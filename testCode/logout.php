<?php
// Show errors during development
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();
require_once('path.inc');
require_once('get_host_info.inc');
require_once('rabbitMQLib.inc');

// Get session key before destroying session
$sessionKey = $_SESSION['session_key'] ?? null;

if ($sessionKey) {
    try {
        $client = new rabbitMQClient("testRabbitMQ.ini", "testServer");

        $request = array(
            "type" => "logout",
            "session_key" => $sessionKey
        );

        // Send logout request to backend
        $response = $client->send_request($request);

        // Optional: log or inspect response if needed
        // error_log("Logout response: " . json_encode($response));
    } catch (Exception $e) {
        error_log("Logout RabbitMQ error: " . $e->getMessage());
    }
}

// Destroy session
session_unset();
session_destroy();
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
