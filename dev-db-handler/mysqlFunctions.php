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

// === Submit Scan ===
function submitScan($userId, $scanType, $inputValue) {
    $conn = getDBConnection();
    $client = new rabbitMQClient("testRabbitMQ.ini","testServer");

    $stmt = $conn->prepare("INSERT INTO scans (user_id, scan_type, input_value, status, submitted_at, is_deleted) VALUES (?, ?, ?, 'pending', NOW(), FALSE)");
    $stmt->bind_param("iss", $userId, $scanType, $inputValue);
    $stmt->execute();
    $scanId = $stmt->insert_id;
    $stmt->close();
    $conn->close();
    return $scanId;
}

// === View Scan Result ===
function viewScanResult($scanId) {
    $conn = getDBConnection();
    $client = new rabbitMQClient("testRabbitMQ.ini","testServer");

    $stmt = $conn->prepare("SELECT * FROM scans WHERE id = ? AND is_deleted = FALSE");
    $stmt->bind_param("i", $scanId);
    $stmt->execute();
    $result = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    $conn->close();
    return $result;
}

// === Scan History ===
function getScanHistory($userId, $limit = 10, $offset = 0) {
    $conn = getDBConnection();
    $client = new rabbitMQClient("testRabbitMQ.ini","testServer");

    $stmt = $conn->prepare("SELECT * FROM scans WHERE user_id = ? AND is_deleted = FALSE ORDER BY submitted_at DESC LIMIT ? OFFSET ?");
    $stmt->bind_param("iii", $userId, $limit, $offset);
    $stmt->execute();
    $result = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    $conn->close();
    return $result;
}

// === Manager Review Scans ===
function getClientScansForReview($limit = 10, $offset = 0) {
    $conn = getDBConnection();
    $client = new rabbitMQClient("testRabbitMQ.ini","testServer");

    $stmt = $conn->prepare("SELECT s.*, u.username FROM scans s JOIN users u ON s.user_id = u.id WHERE s.is_deleted = FALSE ORDER BY s.submitted_at DESC LIMIT ? OFFSET ?");
    $stmt->bind_param("ii", $limit, $offset);
    $stmt->execute();
    $result = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    $conn->close();
    return $result;
}

function flagScan($scanId, $userId, $reason) {
    $conn = getDBConnection();
    $client = new rabbitMQClient("testRabbitMQ.ini","testServer");

    $stmt = $conn->prepare("INSERT INTO scan_flags (scan_id, flagged_by, reason, status, created) VALUES (?, ?, ?, 'under_review', NOW())");
    $stmt->bind_param("iis", $scanId, $userId, $reason);
    $result = $stmt->execute();
    $stmt->close();
    $conn->close();
    return $result;
}

// === Manager Alerts Page ===
function getFlaggedScans() {
    $conn = getDBConnection();
    $client = new rabbitMQClient("testRabbitMQ.ini","testServer");

    $query = "SELECT sf.*, s.input_value, s.scan_type, u.username FROM scan_flags sf
              JOIN scans s ON sf.scan_id = s.id
              JOIN users u ON sf.flagged_by = u.id
              WHERE sf.status = 'under_review'
              ORDER BY sf.created DESC";
    $result = $conn->query($query)->fetch_all(MYSQLI_ASSOC);
    $conn->close();
    return $result;
}

// === Admin Oversight Page ===
function getAllScans($limit = 10, $offset = 0) {
    $conn = getDBConnection();
    $client = new rabbitMQClient("testRabbitMQ.ini","testServer");

    $stmt = $conn->prepare("SELECT s.*, u.username FROM scans s JOIN users u ON s.user_id = u.id WHERE s.is_deleted = FALSE ORDER BY s.submitted_at DESC LIMIT ? OFFSET ?");
    $stmt->bind_param("ii", $limit, $offset);
    $stmt->execute();
    $result = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    $conn->close();
    return $result;
}

function findDuplicateSubmissions($inputValue) {
    $conn = getDBConnection();
    $client = new rabbitMQClient("testRabbitMQ.ini","testServer");
    
    $stmt = $conn->prepare("SELECT * FROM scans WHERE input_value = ? AND is_deleted = FALSE ORDER BY submitted_at DESC");
    $stmt->bind_param("s", $inputValue);
    $stmt->execute();
    $result = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    $conn->close();
    return $result;
}
?>
