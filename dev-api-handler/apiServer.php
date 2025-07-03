<?php
// quick 

// load environment variables from project root
function loadEnv($filePath) {
    if (!file_exists($filePath)) {
        die("env file not found: $filePath");
    }
    
    $lines = file($filePath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (strpos(trim($line), '#') === 0) continue; 
        
        list($name, $value) = explode('=', $line, 2);
        $_ENV[trim($name)] = trim($value);
    }
}

// load .env from project root 
loadEnv(__DIR__ . '/../.env');

// get API key from env
define('VIRUSTOTAL_API_KEY', $_ENV['VIRUSTOTAL_API_KEY'] ?? '');
define('VIRUSTOTAL_API_URL', 'https://www.virustotal.com/vtapi/v2/url/report');


// test urls
$testUrls = [
    'https://www.google.com',
    'https://www.github.com'
];

function checkUrl($url, $apiKey) {
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
        return ['url' => $url, 'error' => "HTTP $httpCode"];
    }
    
    $data = json_decode($response, true);
    return [
        'url' => $url,
        'detections' => $data['positives'] ?? 0,
        'total_scans' => $data['total'] ?? 0,
        'status' => ($data['positives'] ?? 0) == 0 ? 'Clean' : 'Flagged'
    ];
}

echo "running test\n";

$results = [];
foreach ($testUrls as $url) {
    echo "Testing: $url\n";
    $result = checkUrl($url, VIRUSTOTAL_API_KEY);
    $results[] = $result;
    
    if (isset($result['error'])) {
        echo "  error: {$result['error']}\n";
    } else {
        echo "  status: {$result['status']} ({$result['detections']}/{$result['total_scans']})\n";
    }
    
    sleep(15); // for rate limiting
}

// saves the results in a json file
file_put_contents('test_results.json', json_encode($results, JSON_PRETTY_PRINT));

?>