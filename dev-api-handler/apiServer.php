<?php
require_once('rabbitMQLib.inc');

// API key and API URL for VirusTotal
define('VIRUSTOTAL_API_KEY', 'b96af2c606a9552104bb0f6332cca3f6de166a8475280d3bebb4a5a793f4e041');
define('VIRUSTOTAL_API_URL', 'https://www.virustotal.com/vtapi/v2/url/report');

/**
 * Scan a URL with VirusTotal API
 * 
 * @param string $url URL to scan
 * @param string $apiKey VirusTotal API key
 * @return array|object The scan results
 */
function scanUrl($url, $apiKey) {
    $postData = http_build_query(['apikey' => $apiKey, 'resource' => $url]);
    
    $curl = curl_init();
    curl_setopt_array($curl, [
        CURLOPT_URL => VIRUSTOTAL_API_URL,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => $postData,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 30
    ]);
    
    $response = curl_exec($curl);
    $httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
    curl_close($curl);
    
    if ($httpCode !== 200) {
        return (object)['error' => "HTTP Error: $httpCode"];
    }
    
    // Return the full JSON response as an object to send back to the client
    return json_decode($response);
}

/**
 * Process request from RabbitMQ
 * 
 * @param array $request Request data
 * @return array|object Response data
 */
function requestProcessor($request) {
    echo "Received request...\n";
    
    // Log request data for debugging
    var_dump($request);
    
    // Handle different request types
    switch ($request['type'] ?? '') {
        case 'virus_scan':
            // Handle URL scan request
            if (!isset($request['url'])) {
                return (object)['error' => 'No URL provided'];
            }
            
            $url = $request['url'];
            echo "Scanning URL: $url\n";
            
            // Call VirusTotal API
            $result = scanUrl($url, VIRUSTOTAL_API_KEY);
            
            // Return the result
            return $result;
            
        default:
            return (object)['error' => 'Unknown request type'];
    }
}

// Create RabbitMQ server instance
// Use a separate queue for API requests
$server = new rabbitMQServer("testRabbitMQ.ini", "apiRequest");

echo "API Server started. Waiting for requests...\n";

// Start processing requests
$server->process_requests('requestProcessor');

echo "API Server stopped.\n";
?>