<?php
/**
 * Test script to demonstrate URL scan saving to database
 */

require_once('path.inc');
require_once('get_host_info.inc');
require_once('rabbitMQLib.inc');

echo "=== URL Scan Database Save Test ===\n\n";

// Test data - simulate a logged-in user
$testUserId = 1; // Assuming user with ID 1 exists
$testUrl = "https://google.com";

echo "Testing URL scan for user ID: $testUserId\n";
echo "URL to scan: $testUrl\n\n";

try {
    // Step 1: Send virus scan request to API server
    echo "Step 1: Sending virus scan request to API server...\n";
    $apiClient = new rabbitMQClient("apiRabbitMQ.ini", "apiRequest");
    
    $request = [
        'type' => 'virus_scan',
        'url' => $testUrl,
        'user_id' => $testUserId
    ];
    
    $response = $apiClient->send_request($request);
    
    if (isset($response->error)) {
        echo "API Error: " . $response->error . "\n";
        exit(1);
    }
    
    echo "✅ API scan completed successfully!\n";
    echo "Scan ID: " . ($response->scan_id ?? 'N/A') . "\n";
    echo "Positive detections: " . ($response->positives ?? 'N/A') . "\n";
    echo "Total engines: " . ($response->total ?? 'N/A') . "\n\n";
    
    // Step 2: Check if scan was saved to database
    echo "Step 2: Checking if scan was saved to database...\n";
    $dbClient = new rabbitMQClient("testRabbitMQ.ini", "testServer");
    
    $dbRequest = [
        'type' => 'get_user_url_scans',
        'user_id' => $testUserId,
        'limit' => 5
    ];
    
    $dbResponse = $dbClient->send_request($dbRequest);
    
    if (isset($dbResponse['success']) && $dbResponse['success']) {
        echo "✅ Successfully retrieved user scans from database!\n";
        echo "Number of scans found: " . count($dbResponse['scans']) . "\n";
        
        if (!empty($dbResponse['scans'])) {
            $latestScan = $dbResponse['scans'][0];
            echo "Latest scan details:\n";
            echo "  - Database ID: " . $latestScan['id'] . "\n";
            echo "  - Scanned URL: " . $latestScan['scanned_url'] . "\n";
            echo "  - Scan timestamp: " . $latestScan['scan_timestamp'] . "\n";
            echo "  - Total engines: " . $latestScan['total_engines'] . "\n";
            echo "  - Positive detections: " . $latestScan['positive_detections'] . "\n";
            echo "  - Status: " . $latestScan['status'] . "\n";
        }
    } else {
        echo "❌ Failed to retrieve scans from database\n";
        echo "Error: " . ($dbResponse['message'] ?? 'Unknown error') . "\n";
    }
    
} catch (Exception $e) {
    echo "❌ Test failed with exception: " . $e->getMessage() . "\n";
}

echo "\n=== Test completed ===\n";
?>
