<?php
session_start();
require_once('path.inc');
require_once('get_host_info.inc');
require_once('rabbitMQLib.inc');

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (!isset($_FILES['scanFile']) || $_FILES['scanFile']['error'] != 0) {
        die("Error uploading file.");
    }

    $fileData = file_get_contents($_FILES['scanFile']['tmp_name']);
    $fileName = $_FILES['scanFile']['name'];
    $description = $_POST['description'] ?? '';

    $client = new rabbitMQClient("testRabbitMQ.ini", "testServer");

    $request = array(
        "type" => "submit_scan",
        "filename" => $fileName,
        "filedata" => base64_encode($fileData), // encode to safely send
        "description" => $description,
        "username" => $_SESSION['username'] ?? 'guest'
    );

    $response = $client->send_request($request);

    if ($response['status'] == 'success') {
        echo "Scan submitted successfully!";
    } else {
        echo "Scan submission failed: " . $response['message'];
    }
}
?>
