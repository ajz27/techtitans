<?php
require_once('path.inc');
require_once('get_host_info.inc');
require_once('rabbitMQLib.inc');

$client = new RabbitMQClient('testRabbitMQ.ini', 'testServer');

// Different message types for the second sample
if(isset($argv[1])){
	$msg = $argv[1];
}
else{
	// Default message with different content
	$msg = array("message"=>"Hello from Client 2!", "type"=>"greet", "sender"=>"Sample Client 2");
}

$response = $client->send_request($msg);

echo "Client 2 received response: " . PHP_EOL;
print_r($response);
echo "\n\n";

// Send additional sample messages
echo "Sending additional sample messages..." . PHP_EOL;

// Send a calculation request
$calcMsg = array("type"=>"calculate", "operation"=>"add", "num1"=>15, "num2"=>25);
$calcResponse = $client->send_request($calcMsg);
echo "Calculation response: " . PHP_EOL;
print_r($calcResponse);
echo "\n";

// Send a time request
$timeMsg = array("type"=>"time", "timezone"=>"America/New_York");
$timeResponse = $client->send_request($timeMsg);
echo "Time response: " . PHP_EOL;
print_r($timeResponse);
echo "\n";

// Send a status request
$statusMsg = array("type"=>"status", "service"=>"web_server");
$statusResponse = $client->send_request($statusMsg);
echo "Status response: " . PHP_EOL;
print_r($statusResponse);
echo "\n";

if(isset($argv[0]))
echo $argv[0] . " END".PHP_EOL;
