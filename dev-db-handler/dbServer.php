<?php
require_once('path.inc');
require_once('get_host_info.inc');
require_once('rabbitMQLib.inc');

// Database credentials
$DB_HOST = '10.0.0.30';
$DB_USER = 'root';
$DB_PASS = 'test';
$DB_NAME = 'userDatabase';

function getDBConnection()
{
    global $DB_HOST, $DB_USER, $DB_PASS, $DB_NAME;
    $conn = new mysqli($DB_HOST, $DB_USER, $DB_PASS, $DB_NAME);
    if ($conn->connect_error) {
        error_log("Database connection failed: " . $conn->connect_error);
        return false;
    }
    return $conn;
}

function register($username, $email, $password)
{
    echo "Attempting to register user: $username with email: $email\n";
    
    $conn = getDBConnection();
    if (!$conn) {
        echo "Database connection failed\n";
        return array("success" => false, "message" => "Database connection failed");
    }

    // Check if email already exists
    $stmt = $conn->prepare("SELECT id FROM Users WHERE email = ?");
    if (!$stmt) {
        echo "Prepare statement failed\n";
        $conn->close();
        return array("success" => false, "message" => "Database query error");
    }

    $stmt->bind_param("s", $email);
    $stmt->execute();
    $stmt->store_result();

    if ($stmt->num_rows > 0) {
        echo "Email already exists: $email\n";
        $stmt->close();
        $conn->close();
        return array("success" => false, "message" => "Email already exists");
    }
    $stmt->close();

    // Hash the password
    $hashedPassword = password_hash($password, PASSWORD_BCRYPT);
    echo "Password hashed successfully\n";

    // Insert new user
    $stmt = $conn->prepare("INSERT INTO Users (email, password) VALUES (?, ?)");
    if (!$stmt) {
        echo "Insert prepare failed\n";
        $conn->close();
        return array("success" => false, "message" => "Database prepare error");
    }

    $stmt->bind_param("ss", $email, $hashedPassword);

    if ($stmt->execute()) {
        $userId = $stmt->insert_id;
        echo "User registered successfully with ID: $userId\n";
        $stmt->close();
        $conn->close();

        return array(
            "success" => true,
            "message" => "User registered successfully",
            "user_id" => $userId,
            "username" => $username,
            "email" => $email
        );
    } else {
        $error = $stmt->error;
        echo "Insert failed: $error\n";
        $stmt->close();
        $conn->close();
        return array("success" => false, "message" => "Registration failed: " . $error);
    }
}

function login($username, $password)
{
    $conn = getDBConnection();
    if (!$conn) {
        return array("success" => false, "message" => "Database connection failed");
    }

    $stmt = $conn->prepare("SELECT id, email, password FROM Users WHERE email = ?");
    if (!$stmt) {
        $conn->close();
        return array("success" => false, "message" => "Database query error");
    }

    $stmt->bind_param("s", $username);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 1) {
        $row = $result->fetch_assoc();
        
        if (password_verify($password, $row['password'])) {
            $stmt->close();
            $conn->close();
            return array(
                "success" => true,
                "message" => "Login successful",
                "user_id" => $row['id'],
                "email" => $row['email']
            );
        }
    }

    $stmt->close();
    $conn->close();
    return array("success" => false, "message" => "invalid credentials");
}

function request_processor($request)
{
    echo "received request: " . json_encode($request) . "\n";
    
    if (!isset($request['type'])) {
        return array("success" => false, "message" => "Request type not specified");
    }

    switch ($request['type']) {
        case 'register':
            if (!isset($request['username']) || !isset($request['email']) || !isset($request['password'])) {
                return array("success" => false, "message" => "missing required fields");
            }
            return register($request['username'], $request['email'], $request['password']);
            
        case 'login':
            if (!isset($request['username']) || !isset($request['password'])) {
                return array("success" => false, "message" => "missing username or password");
            }
            return login($request['username'], $request['password']);
            
        default:
            return array("success" => false, "message" => "Unknown request type");
    }
}

// start
$server = new rabbitMQServer("testRabbitMQ.ini", "testServer");

echo "Database RabbitMQ Server Started\n";
$server->process_requests('request_processor');
echo "Database RabbitMQ Server Stopped\n";
exit();
?>