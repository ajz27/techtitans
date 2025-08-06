<?php
require_once('rabbitMQLib.inc');


define('VIRUSTOTAL_API_KEY', 'b96af2c606a9552104bb0f6332cca3f6de166a8475280d3bebb4a5a793f4e041');
define('VIRUSTOTAL_API_URL', 'https://www.virustotal.com/vtapi/v2/url/report');
define('VIRUSTOTAL_V3_API_URL', 'https://www.virustotal.com/api/v3/domains/');

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
 * Scan a domain with VirusTotal v3 API
 * 
 * @param string $domain
 * @param string $apiKey
 * @return array
 */
function scanDomain($domain, $apiKey) {
    $url = VIRUSTOTAL_V3_API_URL . urlencode($domain);
    
    $curl = curl_init();
    curl_setopt_array($curl, [
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 30,
        CURLOPT_HTTPHEADER => [
            'X-Apikey: ' . $apiKey,
            'Content-Type: application/json'
        ]
    ]);
    
    $response = curl_exec($curl);
    $httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
    curl_close($curl);
    
    if ($httpCode === 404) {
        return ['error' => "Domain not found in VirusTotal database"];
    }
    
    if ($httpCode !== 200) {
        return ['error' => "HTTP Error: $httpCode"];
    }
    
    return json_decode($response, true);
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
    $dbClient = null;
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
        
        // Properly close the connection
        if (method_exists($dbClient, 'close')) {
            $dbClient->close();
        }
        
        return isset($response->success) && $response->success;
        
    } catch (Exception $e) {
        error_log("Failed to save scan to database: " . $e->getMessage());
        
        // Ensure connection is closed even on error
        if ($dbClient && method_exists($dbClient, 'close')) {
            $dbClient->close();
        }
        
        return false;
    }
}

/**
 * Save domain scan results to database via RabbitMQ
 * 
 * @param int $userId
 * @param string $scannedDomain
 * @param array $scanResult
 * @return bool
 */
function saveDomainScanToDatabase($userId, $scannedDomain, $scanResult) {
    $dbClient = null;
    try {
        // Create database client
        $dbClient = new rabbitMQClient("testRabbitMQ.ini", "testServer");
        
        // Extract statistics from VirusTotal v3 API response
        $totalEngines = 0;
        $positiveDetections = 0;
        $harmlessCount = 0;
        $maliciousCount = 0;
        $suspiciousCount = 0;
        $undetectedCount = 0;
        $reputationScore = 0;
        $vtPermalink = '';
        $scanStatus = 'completed';
        
        if (isset($scanResult['data']['attributes']['last_analysis_stats'])) {
            $stats = $scanResult['data']['attributes']['last_analysis_stats'];
            $harmlessCount = $stats['harmless'] ?? 0;
            $maliciousCount = $stats['malicious'] ?? 0;
            $suspiciousCount = $stats['suspicious'] ?? 0;
            $undetectedCount = $stats['undetected'] ?? 0;
            $totalEngines = $harmlessCount + $maliciousCount + $suspiciousCount + $undetectedCount;
            $positiveDetections = $maliciousCount + $suspiciousCount;
        }
        
        if (isset($scanResult['data']['attributes']['reputation'])) {
            $reputationScore = $scanResult['data']['attributes']['reputation'];
        }
        
        if (isset($scanResult['data']['links']['self'])) {
            $vtPermalink = $scanResult['data']['links']['self'];
        }
        
        if (isset($scanResult['error'])) {
            $scanStatus = 'error';
        }
        
        // Prepare domain scan data in the format expected by the database
        $scanData = [
            'scan_timestamp' => date('Y-m-d H:i:s'),
            'scanned_domain' => $scannedDomain,
            'total_engines' => $totalEngines,
            'positive_detections' => $positiveDetections,
            'harmless_count' => $harmlessCount,
            'malicious_count' => $maliciousCount,
            'suspicious_count' => $suspiciousCount,
            'undetected_count' => $undetectedCount,
            'reputation_score' => $reputationScore,
            'vt_permalink' => $vtPermalink,
            'scan_status' => $scanStatus,
            'raw_response' => json_encode($scanResult)
        ];
        
        // Prepare request for database server
        $request = [
            'type' => 'save_domain_scan',
            'user_id' => $userId,
            'scan_data' => $scanData
        ];
        
        // Send request to database server
        $response = $dbClient->send_request($request);
        
        // Properly close the connection
        if (method_exists($dbClient, 'close')) {
            $dbClient->close();
        }
        
        return isset($response->success) && $response->success;
        
    } catch (Exception $e) {
        error_log("Failed to save domain scan to database: " . $e->getMessage());
        
        // Ensure connection is closed even on error
        if ($dbClient && method_exists($dbClient, 'close')) {
            $dbClient->close();
        }
        
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
            
        case "domain_scan":
            // Handle domain scan request
            if (!isset($request['domain'])) {
                return ['error' => 'No domain provided'];
            }
            
            $domain = $request['domain'];
            $userId = $request['user_id'] ?? null;
            echo "Scanning domain: $domain\n";
            
            $result = scanDomain($domain, VIRUSTOTAL_API_KEY);
            
            // If scan was successful and we have a user ID, save to database
            if (!isset($result['error']) && $userId) {
                $saved = saveDomainScanToDatabase($userId, $domain, $result);
                if ($saved) {
                    echo "Domain scan results saved to database for user $userId\n";
                } else {
                    echo "Failed to save domain scan results to database\n";
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