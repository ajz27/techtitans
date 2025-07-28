<?php
require_once('rabbitMQLib.inc');


define('VIRUSTOTAL_API_KEY', 'b96af2c606a9552104bb0f6332cca3f6de166a8475280d3bebb4a5a793f4e041');
define('VIRUSTOTAL_API_URL', 'https://www.virustotal.com/vtapi/v2/url/report');

/**
 * Scan a URL with VirusTotal API
 * 
 * @param string 
 * @param string 
 * @return array 
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
        return ['error' => "HTTP Error: $httpCode"]; // Return array instead of object
    }
    
    
    return json_decode($response, true); // true parameter returns associative array instead of object
}

/**
 * Save scan results to database via RabbitMQ
 * 
 * @param int $userId
 * @param string $scannedUrl
 * @param array $scanResult
 * @return bool
 */
function saveScanToDatabase($userId, $scannedUrl, $scanResult) {
    try {
        // Create database client
        $dbClient = new rabbitMQClient("testRabbitMQ.ini", "testServer");
        
        // Prepare scan data in the format expected by the database
        $scanData = [
            'scan_timestamp' => date('Y-m-d H:i:s'),
            'scanned_url' => $scannedUrl,
            'scan_result' => $scanResult // Already an array now
        ];
        
        // Prepare request for database server
        $request = [
            'type' => 'save_url_scan',
            'user_id' => $userId,
            'scan_data' => $scanData
        ];
        
        // Send request to database server
        $response = $dbClient->send_request($request);
        
        return isset($response->success) && $response->success;
        
    } catch (Exception $e) {
        error_log("Failed to save scan to database: " . $e->getMessage());
        return false;
    }
}

/**
 * 
 * 
 * @param array 
 * @return array 
 */
function requestProcessor($request) {
    // echo "Received request...\n";
    
    // Log request data for debuggingw
    // var_dump($request);
    
    
    switch ($request['type'] ?? '') {
        case "virus_scan":
            // Handle URL scan request
            if (!isset($request['url'])) {
                return ['error' => 'No URL provided']; // Return array instead of object
            }
            
            $url = $request['url'];
            $userId = $request['user_id'] ?? null; // Get user ID from request
            // echo "Scanning URL: $url\n";
            
            
            $result = scanUrl($url, VIRUSTOTAL_API_KEY);
            
            // If scan was successful and we have a user ID, save to database
            if (!isset($result['error']) && $userId) {
                $saved = saveScanToDatabase($userId, $url, $result);
                if ($saved) {
                    echo "Scan results saved to database for user $userId\n";
                } else {
                    echo "Failed to save scan results to database\n";
                }
            }
            
            // Return the result
            return $result;
            
        default:
            return ['error' => 'Unknown request type: ' . ($request['type'] ?? 'undefined')]; // Return array instead of object
    }
}


$server = new rabbitMQServer("apiRabbitMQ.ini", "apiRequest");

echo "API Server started. Waiting for requests...\n";


$server->process_requests('requestProcessor');

echo "API Server stopped.\n";
?>