<?php
require_once('path.inc');
require_once('get_host_info.inc');
require_once('rabbitMQLib.inc');

// database credentials
$DB_HOST = '10.0.0.30';
$DB_USER = 'root';
$DB_PASS = 'test';
$DB_NAME = 'userDatabase';

function getDBConnection()
{
    global $DB_HOST, $DB_USER, $DB_PASS, $DB_NAME;
    $conn = new mysqli($DB_HOST, $DB_USER, $DB_PASS, $DB_NAME);
    if ($conn->connect_error) {
        error_log("database connection failed: " . $conn->connect_error);
        return false;
    }
    return $conn;
}

function register($username, $email, $password)
{
    echo "attempting to register user: $username with email: $email\n";
    
    $conn = getDBConnection();
    if (!$conn) {
        echo "database connection failed\n";
        return array("success" => false, "message" => "database connection failed");
    }

    // check if email already exists
    $stmt = $conn->prepare("SELECT id FROM Users WHERE email = ?");
    if (!$stmt) {
        echo "prepare statement failed\n";
        $conn->close();
        return array("success" => false, "message" => "database query error");
    }

    $stmt->bind_param("s", $email);
    $stmt->execute();
    $stmt->store_result();

    if ($stmt->num_rows > 0) {
        echo "email already exists: $email\n";
        $stmt->close();
        $conn->close();
        return array("success" => false, "message" => "email already exists");
    }
    $stmt->close();

    // hash the password
    $hashedPassword = password_hash($password, PASSWORD_BCRYPT);
    echo "password hashed successfully\n";

    // insert new user
    // edited this to take username (jg79)
    $stmt = $conn->prepare("INSERT INTO Users (username, email, password) VALUES (?, ?, ?)");
    if (!$stmt) {
        echo "insert prepare failed\n";
        $conn->close();
        return array("success" => false, "message" => "database prepare error");
    }

    $stmt->bind_param("sss", $username, $email, $hashedPassword);

    if ($stmt->execute()) {
        $userId = $stmt->insert_id;
        echo "user registered successfully with id: $userId\n";
        $stmt->close();
        $conn->close();

        return array(
            "success" => true,
            "message" => "user registered successfully",
            "user_id" => $userId,
            "username" => $username,
            "email" => $email
        );
    } else {
        $error = $stmt->error;
        echo "insert failed: $error\n";
        $stmt->close();
        $conn->close();
        return array("success" => false, "message" => "registration failed: " . $error);
    }
}

function login($email, $password)
{
    $conn = getDBConnection();
    if (!$conn) {
        return array("success" => false, "message" => "database connection failed");
    }
    // edited this to take username
    $stmt = $conn->prepare("SELECT id, username, email, password FROM Users WHERE email = ?");
    if (!$stmt) {
        $conn->close();
        return array("success" => false, "message" => "database query error");
    }

    $stmt->bind_param("s", $username);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 1) {
        $row = $result->fetch_assoc();
        
        if (password_verify($password, $row['password'])) {

                // Generate session key
            $sessionKey = bin2hex(random_bytes(32));

            // Store session key
            $update = $conn->prepare("UPDATE Users SET session_key = ? WHERE id = ?");
            $update->bind_param("si", $sessionKey, $row['id']);
            $update->execute();
            $update->close();

            // Start session
            session_start();
            $_SESSION['user_id'] = $row['id'];
            $_SESSION['email'] = $row['email'];
            $_SESSION['username'] = $row['username'];
            $_SESSION['session_key'] = $sessionKey;

            // Write message to RabbitMQ
            $client = new rabbitMQClient("testRabbitMQ.ini", "testServer");
            $loginMessage = array(
                "type" => "login_event",
                "user_id" => $row['id'],
                "username" => $row['username'],
                "email" => $row['email'],
                "session_key" => $sessionKey,
                "timestamp" => date("Y-m-d H:i:s")
            );
            $client->send_request($loginMessage);
            $stmt->close();
            $conn->close();
            return array(
                "success" => true,
                "message" => "login successful",
                "user_id" => $row['id'],
                "email" => $row['email']
            );
        }
    }

    $stmt->close();
    $conn->close();
    return array("success" => false, "message" => "invalid credentials");
}

function logout($sessionKey)
{
    $conn = getDBConnection();
    if (!$conn) {
        return array("success" => false, "message" => "Database connection failed");
    }

    // Clean the session key input
    $sessionKey = trim($sessionKey);
    if (empty($sessionKey)) {
        return array("success" => false, "message" => "Session key is required");
    }

    // Validate the session key
    $stmt = $conn->prepare("SELECT id, username, email FROM Users WHERE session_key = ?");
    $stmt->bind_param("s", $sessionKey);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result && $result->num_rows === 1) {
        $user = $result->fetch_assoc();

        // Remove session key in DB
        $update = $conn->prepare("UPDATE Users SET session_key = NULL WHERE id = ?");
        $update->bind_param("i", $user['id']);
        $update->execute();
        $update->close();

        // Destroy PHP session
        session_start();
        session_unset();
        session_destroy();

        // Send logout message to RabbitMQ
        $client = new rabbitMQClient("testRabbitMQ.ini", "testServer");
        $logoutMessage = array(
            "type" => "logout_event",
            "user_id" => $user['id'],
            "username" => $user['username'],
            "email" => $user['email'],
            "timestamp" => date("Y-m-d H:i:s")
        );
        $client->send_request($logoutMessage);

        $stmt->close();
        $conn->close();

        return array("success" => true, "message" => "Logout successful");
    }

    $stmt->close();
    $conn->close();
    return array("success" => false, "message" => "Invalid or expired session key");
}

function request_processor($request)
{
    echo "received request: " . json_encode($request) . "\n";
    
    if (!isset($request['type'])) {
        return array("success" => false, "message" => "request type not specified");
    }

    switch ($request['type']) {
        case 'register':
            if (!isset($request['username']) || !isset($request['email']) || !isset($request['password'])) {
                return array("success" => false, "message" => "missing required fields");
            }
            return register($request['username'], $request['email'], $request['password']);
            
        case 'login':
            if (!isset($request['email']) || !isset($request['password'])) {
                return array("success" => false, "message" => "missing username or password");
            }
            return login($request['username'], $request['password']);

        case 'logout':
            if (!isset($request['session_key'])) {
                return array("success" => false, "message" => "Missing session key");
            }
            return logout($request['session_key']);
            
        default:
            return array("success" => false, "message" => "unknown request type");
    }
}

// start
$server = new rabbitMQServer("testRabbitMQ.ini", "testServer");

echo "database rabbitmq server started\n";
$server->process_requests('request_processor');
echo "database rabbitmq server stopped\n";
exit();
?>
