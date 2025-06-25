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
    $email = trim($_POST['email']);
    $password = trim($_POST['password']);
    $confirmPassword = trim($_POST['confirm_password']);

    // Validate passwords match
    if ($password !== $confirmPassword) {
        header("Location: register.html?error=Passwords do not match");
        exit();
    }

    // Basic validation
    if (empty($username) || empty($email) || empty($password)) {
        header("Location: register.html?error=All fields are required");
        exit();
    }

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        header("Location: register.html?error=Invalid email format");
        exit();
    }

    if (strlen($password) < 6) {
        header("Location: register.html?error=Password must be at least 6 characters");
        exit();
    }

    try {
        // Create RabbitMQ client
        $client = new rabbitMQClient("testRabbitMQ.ini", "testServer");

        // Prepare request for RabbitMQ
        $request = array(
            'type' => 'register',
            'username' => $username,
            'email' => $email,
            'password' => $password
        );

        // Send request to database server via RabbitMQ
       // Send request to database server via RabbitMQ
        $response = $client->send_request($request);

        // Convert response to array if it's an object
        if (is_object($response)) {
            $response = (array) $response;
        }

        if ($response && isset($response['success'])) {
            if ($response['success']) {
                // Registration successful
                $_SESSION['user_id'] = $response['user_id'];
                $_SESSION['username'] = $username;

                echo "<script>
                  localStorage.setItem('loggedIn', 'true');
                  window.location.href = 'index.php';
                </script>";
                exit();
            } else {
                $error = isset($response['message']) ? $response['message'] : "Registration failed";
                header("Location: register.html?error=" . urlencode($error));
                exit();
            }
        } else {
            header("Location: register.html?error=Server communication error");
            exit();
        }

    } catch (Exception $e) {
        error_log("Registration error: " . $e->getMessage());
        header("Location: register.html?error=System error occurred");
        exit();
    }
}
?>