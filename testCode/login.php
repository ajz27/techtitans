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
    $emailOrUsername = trim($_POST['username']);
    $password = trim($_POST['password']);

    // Basic validation
    if (empty($emailOrUsername) || empty($password)) {
        header("Location: login.html?error=All fields are required");
        exit();
    }

    try {
        // Create RabbitMQ client
        $client = new rabbitMQClient("testRabbitMQ.ini", "testServer");

        // Prepare request for RabbitMQ
        $request = array(
            'type' => 'login',
            'email' => $emailOrUsername,  // backend uses this as email
            'password' => $password
        );

        // Send request to database server via RabbitMQ
        $response = $client->send_request($request);

        // Convert response to array format
        $responseArray = json_decode(json_encode($response), true);

        if ($responseArray && isset($responseArray['success'])) {
            if ($responseArray['success']) {
                // Login successful
                $_SESSION['user_id'] = $responseArray['user_id'];
                $_SESSION['username'] = $responseArray['username'];
                $_SESSION['session_key'] = $responseArray['session_key'];

                echo "<script>
                    localStorage.setItem('loggedIn', 'true');
                    localStorage.setItem('username', '" . $responseArray['username'] . "');
                    window.location.href = 'index.php';
                </script>";
                exit();
            } else {
                $error = isset($responseArray['message']) ? $responseArray['message'] : "Login failed";
                header("Location: login.html?error=" . urlencode($error));
                exit();
            }
        } else {
            header("Location: login.html?error=Server communication error");
            exit();
        }

    } catch (Exception $e) {
        error_log("login error: " . $e->getMessage());
        header("Location: login.html?error=System error occurred");
        exit();
    }
}
?>
