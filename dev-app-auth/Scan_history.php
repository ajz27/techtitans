<?php
session_start();
require_once('session_check.inc');
require_once('rabbitMQLib.inc');

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.html?error=Please log in to view scan history");
    exit();
}

$userId = $_SESSION['user_id'];
$username = $_SESSION['username'] ?? 'User';
$error = "";
$scans = array();

// Handle AJAX requests for scan details
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['action'])) {
    header('Content-Type: application/json');
    
    if ($_POST['action'] == 'get_scan_details' && isset($_POST['scan_id'])) {
        try {
            $client = new rabbitMQClient("testRabbitMQ.ini", "testServer");
            
            $request = array(
                'type' => 'get_url_scan_details',
                'scan_id' => $_POST['scan_id'],
                'user_id' => $userId
            );
            
            $response = $client->send_request($request);
            echo json_encode($response);
            exit();
            
        } catch (Exception $e) {
            echo json_encode(array("success" => false, "message" => "Error retrieving scan details: " . $e->getMessage()));
            exit();
        }
    }
}

// Get scan history
try {
    $client = new rabbitMQClient("testRabbitMQ.ini", "testServer");
    
    $request = array(
        'type' => 'get_url_scan_history',
        'user_id' => $userId,
        'limit' => 50,
        'offset' => 0
    );
    
    $response = $client->send_request($request);
    
    if ($response && isset($response['success']) && $response['success']) {
        $scans = $response['scans'] ?? array();
    } else {
        $error = "Failed to load scan history: " . ($response['message'] ?? 'Unknown error');
    }
    
} catch (Exception $e) {
    $error = "Error connecting to database: " . $e->getMessage();
}

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Scan History | Tech Titans</title>
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

        .navbar-brand, .nav-link {
            color: #fff !important;
            font-weight: 500;
        }

        .nav-link:hover {
            color: #ffd700 !important;
        }

        .card {
            border-radius: 1rem;
            box-shadow: 0 12px 32px rgba(0, 0, 0, 0.2);
            margin-bottom: 2rem;
        }

        .scan-row {
            cursor: pointer;
            transition: background-color 0.3s ease;
        }

        .scan-row:hover {
            background-color: rgba(0, 123, 255, 0.1);
        }

        .status-safe { color: #198754; font-weight: 600; }
        .status-warning { color: #ffc107; font-weight: 600; }
        .status-danger { color: #dc3545; font-weight: 600; }

        .modal-lg { max-width: 90%; }
        
        .vendor-result {
            padding: 0.25rem 0.5rem;
            border-radius: 0.25rem;
            font-size: 0.875rem;
            margin: 0.125rem;
            display: inline-block;
        }
        
        .vendor-clean { background-color: #d1edff; color: #0c5460; }
        .vendor-detected { background-color: #f8d7da; color: #721c24; }
        .vendor-unrated { background-color: #e2e3e5; color: #41464b; }

        .loading {
            text-align: center;
            padding: 2rem;
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
                <li class="nav-item"><a class="nav-link" href="dashboard.html">Dashboard</a></li>
                <li class="nav-item"><a class="nav-link active" href="Scan_history.php">Scan History</a></li>
                <li class="nav-item"><a class="nav-link text-danger" href="logout.php">Logout</a></li>
            </ul>
        </div>
    </div>
</nav>

<!-- Main Content -->
<div class="container py-5">
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center mb-4">
                        <h3>🕒 Scan History</h3>
                        <div>
                            <a href="dashboard.html" class="btn btn-outline-primary">New Scan</a>
                        </div>
                    </div>

                    <?php if ($error): ?>
                        <div class="alert alert-danger">
                            <strong>Error:</strong> <?php echo htmlspecialchars($error); ?>
                        </div>
                    <?php endif; ?>

                    <?php if (empty($scans) && !$error): ?>
                        <div class="text-center py-5">
                            <h5 class="text-muted">No scan history found</h5>
                            <p class="text-muted">Your URL scans will appear here once you perform them.</p>
                            <a href="dashboard.html" class="btn btn-primary">Perform Your First Scan</a>
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead class="table-dark">
                                    <tr>
                                        <th>URL</th>
                                        <th>Scan Date</th>
                                        <th>Status</th>
                                        <th>Detections</th>
                                        <th>Risk Level</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($scans as $scan): ?>
                                        <?php
                                            $positives = intval($scan['positives'] ?? 0);
                                            $total = intval($scan['total'] ?? 0);
                                            $statusClass = 'status-safe';
                                            $riskLevel = 'Safe';
                                            
                                            if ($positives > 0) {
                                                if ($positives <= 3) {
                                                    $statusClass = 'status-warning';
                                                    $riskLevel = 'Suspicious';
                                                } else {
                                                    $statusClass = 'status-danger';
                                                    $riskLevel = 'Dangerous';
                                                }
                                            }
                                            
                                            $scanDate = $scan['scan_date'] ?? $scan['created_at'] ?? 'Unknown';
                                            $displayUrl = htmlspecialchars($scan['url'] ?? 'Unknown URL');
                                            if (strlen($displayUrl) > 50) {
                                                $displayUrl = substr($displayUrl, 0, 47) . '...';
                                            }
                                        ?>
                                        <tr class="scan-row" data-scan-id="<?php echo htmlspecialchars($scan['scan_id'] ?? ''); ?>">
                                            <td>
                                                <span title="<?php echo htmlspecialchars($scan['url'] ?? ''); ?>">
                                                    <?php echo $displayUrl; ?>
                                                </span>
                                            </td>
                                            <td><?php echo date('M j, Y H:i', strtotime($scanDate)); ?></td>
                                            <td><span class="<?php echo $statusClass; ?>">●</span> Scanned</td>
                                            <td><?php echo $positives; ?> / <?php echo $total; ?></td>
                                            <td><span class="<?php echo $statusClass; ?>"><?php echo $riskLevel; ?></span></td>
                                            <td>
                                                <button class="btn btn-sm btn-outline-primary view-details" 
                                                        data-scan-id="<?php echo htmlspecialchars($scan['scan_id'] ?? ''); ?>">
                                                    View Details
                                                </button>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Scan Details Modal -->
<div class="modal fade" id="scanDetailsModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Scan Details</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="scanDetailsContent">
                <div class="loading">
                    <div class="spinner-border" role="status">
                        <span class="visually-hidden">Loading...</span>
                    </div>
                    <p>Loading scan details...</p>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    const modal = new bootstrap.Modal(document.getElementById('scanDetailsModal'));
    
    // Handle view details button clicks
    document.querySelectorAll('.view-details').forEach(button => {
        button.addEventListener('click', function(e) {
            e.stopPropagation();
            const scanId = this.getAttribute('data-scan-id');
            if (scanId) {
                loadScanDetails(scanId);
            }
        });
    });
    
    // Handle row clicks
    document.querySelectorAll('.scan-row').forEach(row => {
        row.addEventListener('click', function() {
            const scanId = this.getAttribute('data-scan-id');
            if (scanId) {
                loadScanDetails(scanId);
            }
        });
    });
    
    function loadScanDetails(scanId) {
        // Show modal with loading state
        document.getElementById('scanDetailsContent').innerHTML = `
            <div class="loading">
                <div class="spinner-border" role="status">
                    <span class="visually-hidden">Loading...</span>
                </div>
                <p>Loading scan details...</p>
            </div>
        `;
        modal.show();
        
        // Fetch scan details
        const formData = new FormData();
        formData.append('action', 'get_scan_details');
        formData.append('scan_id', scanId);
        
        fetch('Scan_history.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success && data.scan) {
                displayScanDetails(data.scan);
            } else {
                document.getElementById('scanDetailsContent').innerHTML = `
                    <div class="alert alert-danger">
                        <strong>Error:</strong> ${data.message || 'Failed to load scan details'}
                    </div>
                `;
            }
        })
        .catch(error => {
            document.getElementById('scanDetailsContent').innerHTML = `
                <div class="alert alert-danger">
                    <strong>Error:</strong> Failed to fetch scan details. Please try again.
                </div>
            `;
            console.error('Error:', error);
        });
    }
    
    function displayScanDetails(scan) {
        const positives = parseInt(scan.positives) || 0;
        const total = parseInt(scan.total) || 0;
        const statusClass = positives === 0 ? 'status-safe' : (positives <= 3 ? 'status-warning' : 'status-danger');
        const riskLevel = positives === 0 ? 'Safe' : (positives <= 3 ? 'Suspicious' : 'Dangerous');
        
        let vendorResults = '';
        if (scan.scans && typeof scan.scans === 'object') {
            vendorResults = '<h6 class="mt-4">Vendor Results:</h6><div class="vendor-results">';
            for (const [vendor, result] of Object.entries(scan.scans)) {
                const detected = result.detected || false;
                const resultText = result.result || 'Unknown';
                const vendorClass = detected ? 'vendor-detected' : (resultText.includes('unrated') ? 'vendor-unrated' : 'vendor-clean');
                const icon = detected ? '❌' : (resultText.includes('unrated') ? '❓' : '✅');
                
                vendorResults += `
                    <span class="vendor-result ${vendorClass}" title="${vendor}: ${resultText}">
                        ${icon} ${vendor}
                    </span>
                `;
            }
            vendorResults += '</div>';
        }
        
        const content = `
            <div class="row">
                <div class="col-md-8">
                    <h6>URL:</h6>
                    <p class="text-break">${scan.url || 'Unknown'}</p>
                    
                    <h6>Scan Information:</h6>
                    <p><strong>Scan ID:</strong> ${scan.scan_id || 'N/A'}</p>
                    <p><strong>Scan Date:</strong> ${scan.scan_date || 'N/A'}</p>
                    <p><strong>Response Code:</strong> ${scan.response_code || 'N/A'}</p>
                    ${scan.verbose_msg ? `<p><strong>Message:</strong> ${scan.verbose_msg}</p>` : ''}
                </div>
                <div class="col-md-4">
                    <div class="card bg-light">
                        <div class="card-body text-center">
                            <h5 class="${statusClass}">${riskLevel}</h5>
                            <h3>${positives} / ${total}</h3>
                            <p class="mb-0">Security vendors flagged this URL</p>
                        </div>
                    </div>
                </div>
            </div>
            
            ${vendorResults}
            
            ${scan.permalink ? `
                <div class="mt-4">
                    <a href="${scan.permalink}" target="_blank" class="btn btn-outline-primary">
                        View Full Report on VirusTotal
                    </a>
                </div>
            ` : ''}
        `;
        
        document.getElementById('scanDetailsContent').innerHTML = content;
    }
});
</script>

</body>
</html>
