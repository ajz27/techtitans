<?php
// Start session to check if user is logged in
session_start();
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL & ~E_DEPRECATED);

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
    // Handle case where total is 0 or null to prevent division by zero
    if ($total === 0 || $total === null || !is_numeric($total)) {
        return 'neutral';
    }
    
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
    <title>URL Scan History - Tech Titans</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body {
            background: linear-gradient(135deg, #f0eff2, #66a6ff);
            font-family: 'Segoe UI', sans-serif;
            margin: 0;
            min-height: 100vh;
        }

        .navbar {
            background-color: rgba(0, 0, 0, 0.85);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.2);
        }

        .navbar-brand,
        .nav-link {
            color: #fff !important;
            font-weight: 500;
        }

        .nav-link:hover {
            color: #ffd700 !important;
        }

        .nav-link.active {
            color: #ffd700 !important;
            font-weight: bold;
        }

        .manager-link {
            background: linear-gradient(45deg, #28a745, #20c997);
            border-radius: 0.375rem;
            padding: 0.375rem 0.75rem !important;
            margin: 0 0.25rem;
        }

        .admin-link {
            background: linear-gradient(45deg, #dc3545, #fd7e14);
            border-radius: 0.375rem;
            padding: 0.375rem 0.75rem !important;
            margin: 0 0.25rem;
        }

        .card {
            border-radius: 1rem;
            box-shadow: 0 12px 32px rgba(0, 0, 0, 0.2);
            padding: 2rem;
            margin-bottom: 2rem;
        }

        .welcome-card {
            background: rgba(255, 255, 255, 0.9);
            border-left: 5px solid #007bff;
        }

        .table-card {
            background: rgba(255, 255, 255, 0.9);
        }

        .scan-url {
            max-width: 300px;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }
        
        .safe { 
            color: #198754;
            font-weight: bold;
        }
        
        .low-risk { 
            color: #ffc107;
            font-weight: bold;
        }
        
        .medium-risk { 
            color: #fd7e14;
            font-weight: bold;
        }
        
        .high-risk { 
            color: #dc3545;
            font-weight: bold;
        }
        
        .neutral { 
            color: #6c757d;
            font-weight: bold;
        }
        
        .no-scans {
            text-align: center;
            padding: 30px;
            background: rgba(255, 255, 255, 0.8);
            border-radius: 0.5rem;
            margin: 20px 0;
        }
        
        .scan-details-link {
            color: #fff;
            text-decoration: none;
            background-color: #007bff;
            padding: 0.375rem 0.75rem;
            border-radius: 0.25rem;
            transition: background-color 0.3s ease;
            display: inline-block;
        }
        
        .scan-details-link:hover {
            background-color: #0056b3;
            color: #fff;
        }
    </style>
</head>
<body>
    <!-- Navbar -->
    <nav class="navbar navbar-expand-lg">
        <div class="container">
            <a class="navbar-brand" href="#">Tech Titans</a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ms-auto">
                    <li class="nav-item"><a class="nav-link" href="index.php">Home</a></li>
                    <li class="nav-item"><a class="nav-link" href="profile.php">Profile</a></li>
                    <li class="nav-item"><a class="nav-link" href="check-url.php">URL Scan</a></li>
                    <li class="nav-item"><a class="nav-link" href="check-domain.php">Domain Scan</a></li>
                    <li class="nav-item"><a class="nav-link active" href="view_scan_history.php">Scan History</a></li>
                    <li class="nav-item"><a class="nav-link manager-link" href="manager/list_all_scans.php">📋 Manager Panel</a></li>
                    <li class="nav-item"><a class="nav-link admin-link" href="admin/list_all_users.php">👑 Admin Panel</a></li>
                    <li class="nav-item"><a class="nav-link text-danger" href="logout.php">Logout</a></li>
                </ul>
            </div>
        </div>
    </nav>
    
    <!-- Main content -->
    <div class="container py-5">
        <div class="row justify-content-center">
            <div class="col-lg-10">
                <div class="card welcome-card mb-4">
                    <h3 class="text-center mb-4">URL Scan History</h3>
                    <div class="alert alert-info">
                        <p class="mb-0">Welcome, <?php echo htmlspecialchars($username); ?>! Here's your URL scan history.</p>
                    </div>
        
        <?php if (empty($scanHistory)): ?>
                    <div class="no-scans">
                        <h4 class="mb-3">No Scan History Found</h4>
                        <p>You haven't performed any URL scans yet.</p>
                        <a href="check-url.php" class="btn btn-primary mt-3">Go to URL Scanner</a>
                    </div>
                <?php else: ?>
                    <div class="card table-card">
                        <div class="table-responsive">
                            <table class="table table-striped table-hover">
                                <thead class="table-dark">
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
                                        // Convert each scan to an array if it's an object
                                        if (is_object($scan)) {
                                            $scan = (array)$scan;
                                        }
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
                                                    echo '✅ Safe';
                                                } else if ($scan['positive_detections'] < 3) {
                                                    echo '⚠️ Low Risk';
                                                } else if ($scan['positive_detections'] < 10) {
                                                    echo '⚠️ Medium Risk';
                                                } else {
                                                    echo '❌ High Risk';
                                                }
                                                ?>
                                            </td>
                                            <td><?php echo $scan['positive_detections'] . '/' . $scan['total_engines']; ?></td>
                                            <td>
                                                <?php if (!empty($scan['permalink'])): ?>
                                                    <a href="<?php echo htmlspecialchars($scan['permalink']); ?>" target="_blank" class="btn btn-sm btn-primary">
                                                        View Report
                                                    </a>
                                                <?php else: ?>
                                                    <span class="text-muted">Report Not Available</span>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                <?php endif; ?>
                
                <div class="text-center mt-4">
                    <a href="index.php" class="btn btn-outline-secondary">Back to Home</a>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
