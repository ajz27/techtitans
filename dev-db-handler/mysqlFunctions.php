<?php
require_once('path.inc');
require_once('getHostInfo.inc');
require_once('rabbitMQLib.inc');

// Database credentials
$DB_HOST = '192.168.193.13';
$DB_USER = 'jg69';
$DB_PASS = 'Ilovemanpreet<3';
$DB_NAME = 'userDatabase';

// Establish DB connection
function getDBConnection()
{
    global $DB_HOST, $DB_USER, $DB_PASS, $DB_NAME;
    $conn = new mysqli($DB_HOST, $DB_USER, $DB_PASS, $DB_NAME);
    if ($conn->connect_error) {
        die("Database connection failed: " . $conn->connect_error);
    }
    return $conn;
}

// INSERT new user with bcrypt password
function insertUser($email, $password, $username = null)
{
    $conn = getDBConnection();
    $client = new rabbitMQClient("testRabbitMQ.ini","testServer");

    $hashedPassword = password_hash($password, PASSWORD_BCRYPT);

    $stmt = $conn->prepare("INSERT INTO Users (email, password, username) VALUES (?, ?, ?)");
    if (!$stmt) {
        return "Prepare failed: " . $conn->error;
    }

    $stmt->bind_param("sss", $email, $hashedPassword, $username);

    if ($stmt->execute()) {
        $response = "User inserted successfully with ID " . $stmt->insert_id;
    } else {
        $response = "Insert failed: " . $stmt->error;
    }

    $stmt->close();
    $conn->close();
    return $response;
}

// DELETE user by ID
function deleteUser($id)
{
    $conn = getDBConnection();
    $client = new rabbitMQClient("testRabbitMQ.ini","testServer");

    $stmt = $conn->prepare("DELETE FROM Users WHERE id = ?");
    if (!$stmt) {
        return "Prepare failed: " . $conn->error;
    }

    $stmt->bind_param("i", $id);

    if ($stmt->execute()) {
        $response = ($stmt->affected_rows > 0) ? "User deleted successfully." : "No user found with ID $id.";
    } else {
        $response = "Delete failed: " . $stmt->error;
    }

    $stmt->close();
    $conn->close();
    return $response;
}

// REGISTER user (wraps insertUser)
function register($username, $password, $email)
{
    return insertUser($email, $password, $username);
}

// LOGIN: validate password and set session hash
function checkLogin($username, $password, $hash)
{
    $conn = getDBConnection();
    $client = new rabbitMQClient("testRabbitMQ.ini","testServer");

    $stmt = $conn->prepare("SELECT password FROM Users WHERE username = ?");
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $stmt->bind_result($hashedPasswordFromDB);
    $stmt->fetch();
    $stmt->close();

    if ($hashedPasswordFromDB && password_verify($password, $hashedPasswordFromDB)) {
        return setHash($username, $hash);
    } else {
        return "Login FAILED";
    }
}

// Set session key (hash) for user
function setHash($username, $hash)
{
    $conn = getDBConnection();
    $client = new rabbitMQClient("testRabbitMQ.ini","testServer");

    $stmt = $conn->prepare("UPDATE Users SET session_key = ? WHERE username = ?");
    $stmt->bind_param("ss", $hash, $username);
    $result = $stmt->execute();

    $stmt->close();
    $conn->close();

    return $result ? $hash : "error";
}

// Logout: clear session key
function logout($sessionKey)
{
    $conn = getDBConnection();
    $client = new rabbitMQClient("testRabbitMQ.ini","testServer");
    $cleanSession = preg_replace('/[^a-zA-Z0-9]/', '', $sessionKey);

    $stmt = $conn->prepare("UPDATE Users SET session_key = NULL WHERE session_key = ?");
    $stmt->bind_param("s", $cleanSession);
    $result = $stmt->execute();

    $stmt->close();
    $conn->close();

    return $result ? "loggedOut" : "error";
}
?>
