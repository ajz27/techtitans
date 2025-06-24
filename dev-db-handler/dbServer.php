<?php
require_once('path.inc');
require_once('getHostInfo.inc');
require_once('rabbitMQLib.inc');

// database credentials
$DB_HOST = '192.168.193.13';
$DB_USER = 'testUser';
$DB_PASS = 'Password123**';
$DB_NAME = 'userDatabase';


function getDBConnection()
{
    global $DB_HOST, $DB_USER, $DB_PASS, $DB_NAME;
    $conn = new mysqli($DB_HOST, $DB_USER, $DB_PASS, $DB_NAME);
    if ($conn->connect_error) {
        die("Database connection failed: " . $conn->connect_error);
    }
    return $conn;
}



function register($username, $email, $password)
{
    

    $conn = getDBConnection();
    if (!$conn) {
        return array("success" => false, "message" => "error");
    }

    // password
    $hashedPassword = password_hash($password, PASSWORD_BCRYPT);

    // insert new user
    $stmt = $conn->prepare("INSERT INTO Users (email, password) VALUES (?, ?)");
    if (!$stmt) {
        $conn->close();
        return array ("success" => false, "message" => "error");
    }

    $stmt->bind_param("ss", $email, $hashedPassword);

    if ($stmt->execute()) {
        $userId = $stmt->insert_id;
        $stmt->close();
        $conn->close();

        return array(
            "success" => true,
            "message" => "registered succesfully",
            "user_id" => $userId,
            "username" => $username,
            "email" => $email
        );

    } else {
        $error = $stmt->error;
        $stmt->close();
        $conn->close();
        return array("success" => false, "message" => "failed" . $error);
    }


}







$server = new rabbitMQServer("testRabbitMQ.ini", "testServer");