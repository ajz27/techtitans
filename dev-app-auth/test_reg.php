<?php
require_once('path.inc');
require_once('get_host_info.inc');
require_once('rabbitMQLib.inc');

// test data
$testUser = array(
    'type' => 'register',
    'username' => 'testuser_' . time(),
    'email' => 'test_' . time() . '@example.com',
    'password' => 'testpass123'
);

try {
    $client = new rabbitMQClient("testRabbitMQ.ini", "testServer");
    echo "sending registration request\n";
    
    $response = $client->send_request($testUser);
    
    echo "response received:\n";
    print_r($response);
    
} catch (Exception $e) {
    echo "error: " . $e->getMessage() . "\n";
}
?>