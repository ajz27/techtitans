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
        // Use the database server configuration instead of test configuration
        $dbClient = new rabbitMQClient("testRabbitMQ.ini", "testServer");
        
        $request = [
            'type' => 'save_url_scan',
            'data' => [
                'user_id' => $userId,
                'scan_id' => $scanResult->scan_id ?? null,
                'scan_date' => $scanResult->scan_date ?? date('Y-m-d H:i:s'),
                'url' => $url,
                'resource' => $scanResult->resource ?? $url,
                'positives' => $scanResult->positives ?? 0,
                'total' => $scanResult->total ?? 0,
                'permalink' => $scanResult->permalink ?? '',
                'response_code' => $scanResult->response_code ?? 0,
                'verbose_msg' => $scanResult->verbose_msg ?? '',
                'scans_json' => json_encode($scanResult->scans ?? [])
            ]
        ];
        
        // Use send_request instead of publish (more reliable)
        // Note: This will wait for a response but ensures the data is saved
        $response = $dbClient->send_request($request);
        
        // Log success for debugging (optional)
        if (isset($response->success) && $response->success) {
            error_log("Scan result saved successfully to database");
        }
        
    } catch (Exception $e) {
        // Log error but don't interrupt the main flow
        error_log("Failed to save scan result to database: " . $e->getMessage());
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