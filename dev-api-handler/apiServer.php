<?php
require_once('rabbitMQLib.inc');


define('VIRUSTOTAL_API_KEY', 'b96af2c606a9552104bb0f6332cca3f6de166a8475280d3bebb4a5a793f4e041');
define('VIRUSTOTAL_API_URL', 'https://www.virustotal.com/vtapi/v2/url/report');

/**
 * Scan a URL with VirusTotal API
 * 
 * @param string 
 * @param string 
 * @return array|object 
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
    
    
    return json_decode($response);
}

/**
 * 
 * 
 * @param array 
 * @return array|object 
 */
function requestProcessor($request) {
    // echo "Received request...\n";
    
    // Log request data for debuggingw
    // var_dump($request);
    
    
    switch ($request['type'] ?? '') {
        case "virus_scan":
            // Handle URL scan request
            if (!isset($request['url'])) {
                return (object)['error' => 'No URL provided'];
            }
            
            $url = $request['url'];
            // echo "Scanning URL: $url\n";
            
            
            $result = scanUrl($url, VIRUSTOTAL_API_KEY);
            
            // Return the result
            return $result;
            
        default:
            return (object)['error' => 'Unknown request type: ' . ($request['type'] ?? 'undefined')];
    }
}


$server = new rabbitMQServer("apiRabbitMQ.ini", "apiRequest");

echo "API Server started. Waiting for requests...\n";


$server->process_requests('requestProcessor');

echo "API Server stopped.\n";
?>