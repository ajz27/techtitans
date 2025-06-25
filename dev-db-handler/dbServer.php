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
    $stmt = $conn->prepare("INSERT INTO Users (email, password) VALUES (?, ?)");
    if (!$stmt) {
        echo "insert prepare failed\n";
        $conn->close();
        return array("success" => false, "message" => "database prepare error");
    }

    $stmt->bind_param("ss", $email, $hashedPassword);

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

function login($username, $password)
{
    $conn = getDBConnection();
    if (!$conn) {
        return array("success" => false, "message" => "database connection failed");
    }

    $stmt = $conn->prepare("SELECT id, email, password FROM Users WHERE email = ?");
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
            if (!isset($request['username']) || !isset($request['password'])) {
                return array("success" => false, "message" => "missing username or password");
            }
            return login($request['username'], $request['password']);
            
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