<?php
// Show all errors for debugging
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();
<<<<<<< HEAD
include 'db.php';
=======
require_once('path.inc');
require_once('get_host_info.inc');
require_once('rabbitMQLib.inc');
require_once('db.php');
>>>>>>> 4935d601b39611b6f8e46987eae07284e6ca35e7

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

    // Check if username/email already exists
    $stmt = $conn->prepare("SELECT id FROM users WHERE username = ? OR email = ?");
    $stmt->bind_param("ss", $username, $email);
    $stmt->execute();
    $stmt->store_result();

    if ($stmt->num_rows > 0) {
        header("Location: register.html?error=Username or email already exists");
        exit();
    }

    // Hash the password
    $hashedPassword = password_hash($password, PASSWORD_BCRYPT);

    // Insert new user
    $stmt = $conn->prepare("INSERT INTO users (username, email, password) VALUES (?, ?, ?)");
    $stmt->bind_param("sss", $username, $email, $hashedPassword);

    if ($stmt->execute()) {
        $_SESSION['user_id'] = $stmt->insert_id;
        $_SESSION['username'] = $username;

        // Set localStorage and redirect
        echo "<script>
          localStorage.setItem('loggedIn', 'true');
          window.location.href = 'index.php';
        </script>";
        exit();
    } else {
        echo "Error: " . $stmt->error;
    }
}
?>
<?php
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

