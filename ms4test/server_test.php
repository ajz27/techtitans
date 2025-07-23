<?php

require_once('path.inc');
require_once('get_host_info.inc');
require_once('rabbitMQLib.inc');

function greetUser($name, $sender){
	//Process greeting request
	$greeting = "Hello " . $name . "! Greetings from " . $sender;
	return array("return_code"=>'0', "message"=>$greeting, "timestamp"=>date('Y-m-d H:i:s'));
}

function calculateOperation($operation, $num1, $num2){
	//Handle different mathematical operations
	switch($operation){
		case "add":
			$result = $num1 + $num2;
			break;
		case "subtract":
			$result = $num1 - $num2;
			break;
		case "multiply":
			$result = $num1 * $num2;
			break;
		case "divide":
			if($num2 != 0){
				$result = $num1 / $num2;
			} else {
				return array("return_code"=>'1', "error"=>"Division by zero not allowed");
			}
			break;
		default:
			return array("return_code"=>'1', "error"=>"Unsupported operation: " . $operation);
	}
	
	return array("return_code"=>'0', "operation"=>$operation, "num1"=>$num1, "num2"=>$num2, "result"=>$result);
}

function getCurrentTime($timezone = "UTC"){
	//Return current time in specified timezone
	try {
		$tz = new DateTimeZone($timezone);
		$datetime = new DateTime("now", $tz);
		return array("return_code"=>'0', "time"=>$datetime->format('Y-m-d H:i:s T'), "timezone"=>$timezone);
	} catch (Exception $e) {
		return array("return_code"=>'1', "error"=>"Invalid timezone: " . $timezone);
	}
}

function getServiceStatus($service){
	//Mock service status check
	$services = array(
		"web_server" => "running",
		"database" => "running", 
		"cache" => "stopped",
		"queue" => "running"
	);
	
	if(isset($services[$service])){
		return array("return_code"=>'0', "service"=>$service, "status"=>$services[$service], "checked_at"=>date('Y-m-d H:i:s'));
	} else {
		return array("return_code"=>'1', "error"=>"Unknown service: " . $service);
	}
}

function request_processor_v2($req){
	echo "Server 2 - Received Request".PHP_EOL;
	echo "<pre>" . var_dump($req) . "</pre>";
	
	if(!isset($req['type'])){
		return array("return_code"=>'1', "error"=>"unsupported message type");
	}
	
	//Handle different message types
	$type = $req['type'];
	switch($type){
		case "greet":
			$name = isset($req['message']) ? $req['message'] : "Anonymous";
			$sender = isset($req['sender']) ? $req['sender'] : "Unknown";
			return greetUser($name, $sender);
			
		case "calculate":
			if(!isset($req['operation']) || !isset($req['num1']) || !isset($req['num2'])){
				return array("return_code"=>'1', "error"=>"Missing required parameters for calculation");
			}
			return calculateOperation($req['operation'], $req['num1'], $req['num2']);
			
		case "time":
			$timezone = isset($req['timezone']) ? $req['timezone'] : "UTC";
			return getCurrentTime($timezone);
			
		case "status":
			if(!isset($req['service'])){
				return array("return_code"=>'1', "error"=>"Service name required for status check");
			}
			return getServiceStatus($req['service']);
			
		case "echo":
			return array("return_code"=>'0', "message"=>"Server 2 Echo: " .$req["message"]);
			
		default:
			return array("return_code"=>'1', "error"=>"Unsupported message type: " . $type);
	}
}

$server = new rabbitMQServer("testRabbitMQ.ini", "sampleServer");

echo "Rabbit MQ Server 2 Start" . PHP_EOL;
$server->process_requests('request_processor_v2');
echo "Rabbit MQ Server 2 Stop" . PHP_EOL;
exit();
?>
