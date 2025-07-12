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
 * Save scan results to database via RabbitMQ (fire-and-forget)
 * 
 * @param object $scanResult The VirusTotal scan result
 * @param string $url The scanned URL
 * @param int|null $userId Optional user ID to associate with the scan
 */
function saveScanResultAsync($scanResult, $url, $userId = null) {
    try {
        // Create RabbitMQ client for database communication
        $dbClient = new rabbitMQClient("testRabbitMQ.ini", "testServer");
        
        // Prepare data for database storage
        $dbRequest = array(
            'type' => 'save_url_scan',
            'user_id' => $userId,
            'scan_id' => $scanResult->scan_id ?? null,
            'scan_date' => $scanResult->scan_date ?? null,
            'url' => $url,
            'resource' => $scanResult->resource ?? null,
            'positives' => $scanResult->positives ?? 0,
            'total' => $scanResult->total ?? 0,
            'permalink' => $scanResult->permalink ?? null,
            'response_code' => $scanResult->response_code ?? null,
            'verbose_msg' => $scanResult->verbose_msg ?? null,
            'scans_json' => isset($scanResult->scans) ? json_encode($scanResult->scans) : null
        );
        
        // Send to database server - we intentionally don't wait for response
        // to keep this operation non-blocking for the frontend
        $response = $dbClient->send_request($dbRequest);
        echo "Scan result sent to database for storage\n";
        
    } catch (Exception $e) {
        // Log error but don't let it affect the API response to frontend
        error_log("Failed to save scan result to database: " . $e->getMessage());
        echo "Warning: Could not save scan result to database: " . $e->getMessage() . "\n";
    }
}

/**
 * Process incoming requests
 * 
 * @param array 
 * @return array|object 
 */
function requestProcessor($request) {
    // echo "Received request...\n";
    
    // Log request data for debugging
    // var_dump($request);
    
    switch ($request['type'] ?? '') {
        case "virus_scan":
            // Handle URL scan request
            if (!isset($request['url'])) {
                return (object)['error' => 'No URL provided'];
            }
            
            $url = $request['url'];
            $userId = $request['user_id'] ?? null; // Optional user ID
            // echo "Scanning URL: $url\n";
            
            // Get scan result from VirusTotal
            $result = scanUrl($url, VIRUSTOTAL_API_KEY);
            
            // If scan was successful, save to database asynchronously
            if (!isset($result->error) && isset($result->scan_id)) {
                saveScanResultAsync($result, $url, $userId);
            }
            
            // Return the result to frontend immediately
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