<?php


// api key and api url
define('VIRUSTOTAL_API_KEY', 'b96af2c606a9552104bb0f6332cca3f6de166a8475280d3bebb4a5a793f4e041');
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