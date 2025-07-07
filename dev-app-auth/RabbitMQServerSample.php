<?php
require_once('path.inc');
require_once('get_host_info.inc');
require_once('rabbitMQLib.inc');
require_once('db.php'); // includes insertUser(), doLogin(), etc.

function requestProcessor($request)
{
    echo "Received request:\n";
    print_r($request);

    if (!isset($request['type'])) {
        return ['success' => 0, 'message' => 'Missing request type'];
    }

    switch ($request['type']) {
        case "register":
            $result = insertUser($request['email'], $request['password'], $request['username']);
            if ($result === true) {
                return ['success' => 1, 'message' => 'User registered successfully'];
            } else {
                return ['success' => 0, 'message' => $result];
            }

case "login":
    $loginKey = isset($request['email']) ? $request['email'] : $request['username'];

    $result = doLogin($loginKey, $request['password']);

    if (is_array($result) && isset($result['success']) && $result['success'] === 1) {
        return $result;
    } else {
        return ['success' => 0, 'message' => $result];
    }

            

        case "echo":
            return ['success' => 1, 'message' => "Echo: " . $request['message']];

        default:
            return ['success' => 0, 'message' => "Unknown request type: " . $request['type']];
    }
}

$server = new rabbitMQServer("testRabbitMQ.ini", "testServer");

echo "Starting RabbitMQ Server...\n";
$server->process_requests('requestProcessor');
echo "RabbitMQServerSample.php END\n";
