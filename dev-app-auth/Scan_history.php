<?php 
// Show all errors for debugging
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
require_once('path.inc');
require_once('getHostInfo.inc');
require_once('rabbitMQLib.inc');
error_reporting(E_ALL);
session_start();
require 'db.php';


if (isset($_SESSION['user_id'])) {
    $userId = $_SESSION['user_id'];
} else {
    $userId = null;
}

if (!$userId) {
    http_response_code(401);
    echo json_encode(['error' => 'User not logged in']);
    exit;
}
if (isset($_SESSION['role'])) {
    $userRole = $_SESSION['role'];
} else {
    $userRole = null;
}

if (!$userRole) {
    http_response_code(403);
    echo json_encode(array('error' => 'User role not found'));
    exit;
}

try {
  
    $client = new rabbitMQClient("testRabbitMQ.ini", "testServer");

  
    $request = [
        'type' => 'get_scan_history',
        'user_id' => $userId,
        'role' => $userRole,
        'order' => 'ASC', 
    ];


    $response = $client->send_request($request);

  
    $responseArray = json_decode(json_encode($response), true);

    if ($responseArray && isset($responseArray['success']) && $responseArray['success']) {
    
        header('Content-Type: application/json');
        echo json_encode($responseArray['scans']); 
        exit;
    } else {
       
        $errorMsg = $responseArray['message'] ?? 'Failed to fetch scan history';
        http_response_code(500);
        echo json_encode(['error' => $errorMsg]);
        exit;
    }

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Internal server error: ' . $e->getMessage()]);
    exit;
}

?>
