<?php
/**
 * Simple PHP script to test VirusTotal API and save results to file
 * This script will be used to test the functionality before integrating with the database
 */

// VirusTotal API Configuration
define('VIRUSTOTAL_API_KEY', 'b96af2c606a9552104bb0f6332cca3f6de166a8475280d3bebb4a5a793f4e041');
define('VIRUSTOTAL_API_URL', 'https://www.virustotal.com/vtapi/v2/url/report');

/**
 * Scan a URL with VirusTotal API
 * 
 * @param string $url The URL to scan
 * @param string $apiKey The VirusTotal API key
 * @return array|object The scan result or error
 */
function scanUrlVirusTotal($url, $apiKey) {
    echo "Scanning URL: $url\n";
    
    $postData = http_build_query([
        'apikey' => $apiKey, 
        'resource' => $url
    ]);
    
    $curl = curl_init();
    curl_setopt_array($curl, [
        CURLOPT_URL => VIRUSTOTAL_API_URL,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => $postData,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 30,
        CURLOPT_USERAGENT => 'TechTitans-URLScanner/1.0'
    ]);
    
    $response = curl_exec($curl);
    $httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
    $curlError = curl_error($curl);
    curl_close($curl);
    
    if ($curlError) {
        return [
            'error' => "CURL Error: $curlError",
            'http_code' => $httpCode
        ];
    }
    
    if ($httpCode !== 200) {
        return [
            'error' => "HTTP Error: $httpCode",
            'response' => $response
        ];
    }
    
    $decodedResponse = json_decode($response, true);
    
    if (json_last_error() !== JSON_ERROR_NONE) {
        return [
            'error' => 'JSON decode error: ' . json_last_error_msg(),
            'raw_response' => $response
        ];
    }
    
    return $decodedResponse;
}

/**
 * Save scan results to a file
 * 
 * @param string $url The scanned URL
 * @param array $scanResult The scan result
 * @param string $filename The output filename
 */
function saveScanResultToFile($url, $scanResult, $filename = null) {
    if ($filename === null) {
        $filename = 'scan_results_' . date('Y-m-d_H-i-s') . '.json';
    }
    
    $dataToSave = [
        'scan_timestamp' => date('Y-m-d H:i:s'),
        'scanned_url' => $url,
        'scan_result' => $scanResult,
        'summary' => [
            'total_engines' => $scanResult['total'] ?? 0,
            'positive_detections' => $scanResult['positives'] ?? 0,
            'scan_date' => $scanResult['scan_date'] ?? 'N/A',
            'permalink' => $scanResult['permalink'] ?? 'N/A'
        ]
    ];
    
    $jsonData = json_encode($dataToSave, JSON_PRETTY_PRINT);
    
    if (file_put_contents($filename, $jsonData)) {
        echo "Results saved to: $filename\n";
        echo "File size: " . filesize($filename) . " bytes\n";
        return true;
    } else {
        echo "Error: Could not save results to file\n";
        return false;
    }
}

/**
 * Display scan summary
 * 
 * @param array $scanResult The scan result
 */
function displayScanSummary($scanResult) {
    echo "\n=== SCAN SUMMARY ===\n";
    
    if (isset($scanResult['error'])) {
        echo "Error: " . $scanResult['error'] . "\n";
        return;
    }
    
    $positives = $scanResult['positives'] ?? 0;
    $total = $scanResult['total'] ?? 0;
    $scanDate = $scanResult['scan_date'] ?? 'N/A';
    
    echo "Scan Date: $scanDate\n";
    echo "Detection Ratio: $positives/$total\n";
    
    if ($positives == 0) {
        echo "Status: ✅ CLEAN - No threats detected\n";
    } elseif ($positives <= 3) {
        echo "Status: ⚠️  SUSPICIOUS - Low threat level\n";
    } else {
        echo "Status: ❌ MALICIOUS - High threat level\n";
    }
    
    echo "Permalink: " . ($scanResult['permalink'] ?? 'N/A') . "\n";
    echo "==================\n\n";
}

// Main execution
if ($argc < 2) {
    echo "Usage: php virus_scan_test.php <URL>\n";
    echo "Example: php virus_scan_test.php https://example.com\n";
    exit(1);
}

$urlToScan = $argv[1];

// Validate URL
if (!filter_var($urlToScan, FILTER_VALIDATE_URL)) {
    echo "Error: Invalid URL format\n";
    exit(1);
}

echo "VirusTotal URL Scanner Test\n";
echo "===========================\n";
echo "URL to scan: $urlToScan\n";
echo "API Key: " . substr(VIRUSTOTAL_API_KEY, 0, 8) . "...\n\n";

// Perform the scan
$scanResult = scanUrlVirusTotal($urlToScan, VIRUSTOTAL_API_KEY);

// Display summary
displayScanSummary($scanResult);

// Save results to file
$filename = 'scan_' . date('Y-m-d_H-i-s') . '_' . preg_replace('/[^a-zA-Z0-9]/', '_', parse_url($urlToScan, PHP_URL_HOST)) . '.json';
saveScanResultToFile($urlToScan, $scanResult, $filename);

echo "Test completed!\n";
?>
