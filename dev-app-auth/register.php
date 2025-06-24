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

    try {
        $client = new rabbitMQClient("testRabbitMQ.ini", "testServer");

        // new registration request
        $request = array(
            "type" => "register",
            "username" => $username,
            "email" => $email,
            "password" => $password
        );

        $response = $client->send_request($request);

        echo "received";

    } catch (Exception $e) {
        error_log("rabbitmq error" . $e->getMessage());
        exit();
    }

}


?>
