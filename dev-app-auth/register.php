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
        // create rabbitmq client
        $client = new rabbitMQClient("testRabbitMQ.ini", "testServer");

        // prepare request for rabbitmq
        $request = array(
            'type' => 'register',
            'username' => $username,
            'email' => $email,
            'password' => $password
        );

        // send request to database server via rabbitmq
        $response = $client->send_request($request);

        // convert to array format consistently to avoid stdclass errors
        $responseArray = json_decode(json_encode($response), true);

        if ($responseArray && isset($responseArray['success'])) {
            if ($responseArray['success']) {
                // registration successful - set session data
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
                $error = isset($responseArray['message']) ? $responseArray['message'] : "registration failed";
                header("Location: register.html?error=" . urlencode($error));
                exit();
            }
        } else {
            header("Location: register.html?error=server communication error");
            exit();
        }

    } catch (Exception $e) {
        error_log("registration error: " . $e->getMessage());
        header("Location: register.html?error=system error occurred");
        exit();
    }
}


require_once('path.inc');
require_once('get_host_info.inc');
require_once('rabbitMQLib.inc');
require_once __DIR__ . '/vendor/autoload.php';
use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Message\AMQPMessage;
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Sanitize and hash input
    $username = trim($_POST['username'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = password_hash($_POST['password'] ?? '', PASSWORD_BCRYPT);

    // Prepare data array
    $data = [
        'type'     => 'register',
        'username' => $username,
        'email'    => $email,
        'password' => $password,
    ];

    // MQ connection setup
    $host     = '192.168.193.5';
    $port     = 5672;
    $user     = 'remote';
    $pass     = 'remote123';
    $exchange = 'testExchange';

    $connection = new AMQPStreamConnection($host, 5672, $user, $pass);
    $channel = $connection->channel();

        // Declare exchange (fanout for broadcasting to all bound queues)
        $channel->exchange_declare($exchange, 'fanout', false, false, false);

        // Send the message
        $message = new AMQPMessage(json_encode($data));
        $channel->basic_publish($message, $exchange);

        // Cleanup
        $channel->close();
        $connection->close();

        echo "Registration data sent to MQ.";
    }
?>

