<?php
// Show all errors for debugging
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();
require_once('path.inc');
require_once('get_host_info.inc');
require_once('rabbitMQLib.inc');

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $username = trim($_POST['username']);
    $password = trim($_POST['password']);

    // Basic validation
    if (empty($username) || empty($password)) {
        header("Location: login.html?error=All fields are required");
        exit();
    }

    try {
        // create rabbitmq client
        $client = new rabbitMQClient("loginRabbitMQ.ini", "testServer");

        // prepare request for rabbitmq
        $request = array(
            'type' => 'login',
            'username' => $username,
            'password' => $password
        );

        // send request to database server via rabbitmq
        $response = $client->send_request($request);

        // convert to array format consistently to avoid stdclass errors
        $responseArray = json_decode(json_encode($response), true);

        if ($responseArray && isset($responseArray['success'])) {
            if ($responseArray['success']) {
                // login successful - set session data
                $_SESSION['user_id'] = $responseArray['user_id'];
                $_SESSION['username'] = $responseArray['username'];
                $_SESSION['email'] = $responseArray['email'];
                $_SESSION['logged_in'] = true;

                echo "<script>
                  localStorage.setItem('loggedIn', 'true');
                  localStorage.setItem('username', '" . addslashes($responseArray['username']) . "');
                  window.location.href = 'index.php';
                </script>";
                exit();
            } else {
                $error = isset($responseArray['message']) ? $responseArray['message'] : "login failed";
                header("Location: login.html?error=" . urlencode($error));
                exit();
            }
        } else {
            header("Location: login.html?error=server communication error");
            exit();
        }

    } catch (Exception $e) {
        error_log("login error: " . $e->getMessage());
        header("Location: login.html?error=system error occurred");
        exit();
    }
}
?>
