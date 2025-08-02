<?php
session_start();
require_once('rabbitMQLib.inc');

$username = $_SESSION['username'] ?? null;
if (!$username) {
    die("Unauthorized");
}

$data = [
    'type' => 'update_user_profile',
    'username' => $username,
    'name' => $_POST['name'] ?? '',
    'email' => $_POST['email'] ?? '',
    'bio' => $_POST['bio'] ?? '',
    'password' => $_POST['password'] ?? null // Optional
];

try {
    $client = new rabbitMQClient("testRabbitMQ.ini", "testServer");
    $response = $client->send_request($data);
    header("Location: client_profile.php?success=1");
} catch (Exception $e) {
    error_log("Profile update error: " . $e->getMessage());
    header("Location: client_profile.php?error=1");
}
