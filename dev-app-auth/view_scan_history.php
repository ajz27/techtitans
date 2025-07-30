<?php
// Start session to check if user is logged in
session_start();
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Include required files
require_once('path.inc');
require_once('get_host_info.inc');
require_once('rabbitMQLib.inc');

// Redirect to login if not logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.html");
    exit();
}

// Get user information from session
$userId = $_SESSION['user_id'];
$username = $_SESSION['username'] ?? 'User';

// Function to get scan history from database using RabbitMQ
function getUserScanHistory($userId, $limit = 50) {
    // Create a new RabbitMQ client
    $client = new rabbitMQClient("testRabbitMQ.ini", "testServer");
    
    // Prepare the request
    $request = array(
        'type' => 'get_user_url_scans',
        'user_id' => $userId,
        'limit' => $limit
    );
    
    // Send request to the database server
    $response = $client->send_request($request);
    
    // Check if the response is an object and convert it to array if needed
    if (is_object($response)) {
        $response = (array)$response;
    }
    
    // Check if response was successful and return the scan data
    if (isset($response['success']) && $response['success'] && isset($response['scans'])) {
        // If scans is an object, convert it to array
        $scans = $response['scans'];
        if (is_object($scans)) {
            $scans = (array)$scans;
        }
        return $scans;
    } else {
        return [];
    }
}

// Get scan history for the current user
$scanHistory = getUserScanHistory($userId);

// Ensure $scanHistory is an array
if (!is_array($scanHistory)) {
    $scanHistory = [];
}

// Define function to get severity class based on positive detections
function getSeverityClass($positives, $total) {
    if ($total === 0) return 'neutral';
    
    $ratio = $positives / $total;
    if ($ratio >= 0.7) return 'high-risk';
    if ($ratio >= 0.3) return 'medium-risk';
    if ($ratio > 0) return 'low-risk';
    return 'safe';
}

// Define function to format date
function formatDate($dateString) {
    if (empty($dateString)) return "N/A";
    $date = new DateTime($dateString);
    return $date->format('Y-m-d H:i:s');
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>URL Scan History - TechTitans Security</title>
    <link rel="stylesheet" href="css/style.css">
    <style>
        body {
            font-family: 'Segoe UI', Arial, sans-serif;
            background: #f4f7f9;
            margin: 0;
            padding: 0;
            color: #333;
        }

        .container {
            max-width: 1200px;
            margin: 20px auto;
            padding: 20px;
            background-color: #fff;
            border-radius: 8px;
            box-shadow: 0 0 10px rgba(0, 0, 0, 0.1);
        }
        
        h1 {
            color: #004080;
            border-bottom: 2px solid #ddd;
            padding-bottom: 10px;
            margin-top: 0;
        }
        
        .scan-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
            border-radius: 5px;
            overflow: hidden;
        }
        
        .scan-table th, .scan-table td {
            padding: 12px 15px;
            text-align: left;
            border-bottom: 1px solid #ddd;
        }
        
        .scan-table th {
            background-color: #004080;
            color: white;
            font-weight: bold;
        }
        
        .scan-table tr:hover {
            background-color: #f9f9f9;
        }
        
        .scan-url {
            max-width: 300px;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }
        
        .safe { 
            color: green;
            font-weight: bold;
        }
        
        .low-risk { 
            color: #e6b800;
            font-weight: bold;
        }
        
        .medium-risk { 
            color: orange;
            font-weight: bold;
        }
        
        .high-risk { 
            color: red;
            font-weight: bold;
        }
        
        .neutral { 
            color: gray;
            font-weight: bold;
        }
        
        .back-button {
            display: inline-block;
            padding: 10px 20px;
            background-color: #004080;
            color: white;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            text-decoration: none;
            margin-top: 20px;
            font-size: 16px;
        }
        
        .back-button:hover {
            background-color: #002a57;
        }
        
        .no-scans {
            text-align: center;
            padding: 30px;
            background: #f8f9fa;
            border-radius: 5px;
            margin: 20px 0;
        }
        
        .scan-details-link {
            color: #004080;
            text-decoration: none;
            display: inline-block;
            padding: 6px 12px;
            border: 1px solid #004080;
            border-radius: 4px;
            font-size: 14px;
        }
        
        .scan-details-link:hover {
            background-color: #004080;
            color: white;
        }
        
        .welcome-message {
            background-color: #e8f4fc;
            padding: 10px 15px;
            border-radius: 5px;
            border-left: 4px solid #004080;
            margin-bottom: 20px;
        }
        
        .navbar {
            background-color: #004080;
            color: white;
            padding: 10px 0;
        }
        
        .navbar-container {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 0 20px;
            max-width: 1200px;
            margin: 0 auto;
        }
        
        .logo {
            font-size: 24px;
            font-weight: bold;
        }
        
        .nav-links a {
            color: white;
            text-decoration: none;
            margin-left: 20px;
            padding: 8px 12px;
            border-radius: 4px;
        }
        
        .nav-links a:hover, .nav-links a.active {
            background-color: rgba(255, 255, 255, 0.2);
        }
        
        footer {
            text-align: center;
            padding: 20px;
            background-color: #004080;
            color: white;
            margin-top: 40px;
        }
    </style>
</head>
<body>
    <!-- Navigation bar -->
    <div class="navbar">
        <div class="navbar-container">
            <div class="logo">TechTitans Security</div>
            <div class="nav-links">
                <a href="dashboard.html">Dashboard</a>
                <a href="check-url.php">URL Scanner</a>
                <a href="view_scan_history.php" class="active">Scan History</a>
                <a href="profile.php">Profile</a>
                <a href="logout.php">Logout</a>
            </div>
        </div>
    </div>
    
    <!-- Main content -->
    <div class="container">
        <h1>URL Scan History</h1>
        
        <div class="welcome-message">
            <p>Welcome, <?php echo htmlspecialchars($username); ?>! Here's your URL scan history.</p>
        </div>
        
        <?php if (empty($scanHistory)): ?>
            <div class="no-scans">
                <h3>No Scan History Found</h3>
                <p>You haven't performed any URL scans yet.</p>
                <p>Go to the <a href="check-url.php">URL Scanner</a> to scan a URL.</p>
            </div>
        <?php else: ?>
            <table class="scan-table">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Scanned URL</th>
                        <th>Scan Date</th>
                        <th>Result</th>
                        <th>Detection Ratio</th>
                        <th>Details</th>
                    </tr>
                </thead>
                <tbody>
                    <?php 
                    $counter = 1;
                    foreach ($scanHistory as $scan): 
                        $severityClass = getSeverityClass($scan['positive_detections'], $scan['total_engines']);
                    ?>
                        <tr>
                            <td><?php echo $counter++; ?></td>
                            <td class="scan-url" title="<?php echo htmlspecialchars($scan['scanned_url']); ?>">
                                <?php echo htmlspecialchars($scan['scanned_url']); ?>
                            </td>
                            <td><?php echo formatDate($scan['scan_timestamp']); ?></td>
                            <td class="<?php echo $severityClass; ?>">
                                <?php
                                if ($scan['positive_detections'] == 0) {
                                    echo 'Safe';
                                } else if ($scan['positive_detections'] < 3) {
                                    echo 'Low Risk';
                                } else if ($scan['positive_detections'] < 10) {
                                    echo 'Medium Risk';
                                } else {
                                    echo 'High Risk';
                                }
                                ?>
                            </td>
                            <td><?php echo $scan['positive_detections'] . '/' . $scan['total_engines']; ?></td>
                            <td>
                                <?php if (!empty($scan['permalink'])): ?>
                                    <a href="<?php echo htmlspecialchars($scan['permalink']); ?>" target="_blank" class="scan-details-link">
                                        View Report
                                    </a>
                                <?php else: ?>
                                    Report Not Available
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
        
        <a href="dashboard.html" class="back-button">Back to Dashboard</a>
    </div>
    
    <!-- Footer -->
    <footer>
        <p>&copy; 2025 TechTitans Security. All rights reserved.</p>
    </footer>
</body>
</html>
