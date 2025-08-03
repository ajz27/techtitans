<?php
// Start session and suppress deprecated warnings
session_start();
error_reporting(E_ALL & ~E_DEPRECATED);
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);

// Include required files
require_once('../path.inc');
require_once('../get_host_info.inc');
require_once('../rabbitMQLib.inc');

// Handle AJAX requests for updating scan review
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_review') {
    header('Content-Type: application/json');
    
    if (!isset($_SESSION['user_id'])) {
        echo json_encode(['success' => false, 'message' => 'Not logged in']);
        exit();
    }
    
    $scanId = $_POST['scan_id'] ?? null;
    $reviewStatus = $_POST['review_status'] ?? null;
    $reviewNotes = $_POST['review_notes'] ?? '';
    $reviewerId = $_SESSION['user_id'];
    
    if (!$scanId || !$reviewStatus) {
        echo json_encode(['success' => false, 'message' => 'Missing required fields']);
        exit();
    }
    
    $client = new rabbitMQClient("../testRabbitMQ.ini", "testServer");
    
    $request = array(
        'type' => 'update_scan_review',
        'scan_id' => $scanId,
        'review_status' => $reviewStatus,
        'review_notes' => $reviewNotes,
        'reviewer_id' => $reviewerId
    );
    
    $response = $client->send_request($request);
    
    // Convert stdClass to array if needed
    if (is_object($response)) {
        $response = json_decode(json_encode($response), true);
    }
    
    echo json_encode($response);
    exit();
}

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.html");
    exit();
}

// Get user information from session
$userId = $_SESSION['user_id'];
$username = $_SESSION['username'] ?? 'User';

// Function to check if user has manager or admin privileges
function isUserManagerOrAdmin($userId) {
    $client = new rabbitMQClient("../testRabbitMQ.ini", "testServer");
    
    $request = array(
        'type' => 'get_user_role',
        'user_id' => $userId
    );
    
    $response = $client->send_request($request);
    
    // Convert stdClass to array if needed
    if (is_object($response)) {
        $response = json_decode(json_encode($response), true);
    }
    
    if (isset($response['success']) && $response['success'] && isset($response['role'])) {
        $roleId = $response['role']['role_id'];
        return ($roleId == 1 || $roleId == 2); // Admin (1) or Manager (2)
    }
    
    return false;
}

// Check if current user has manager or admin privileges
if (!isUserManagerOrAdmin($userId)) {
    // Redirect unauthorized users
    header("Location: ../dashboard.php");
    exit();
}

// Function to get all URL scans from database using RabbitMQ
function getAllUrlScans($limit = 100, $offset = 0) {
    $client = new rabbitMQClient("../testRabbitMQ.ini", "testServer");
    
    $request = array(
        'type' => 'get_all_url_scans',
        'limit' => $limit,
        'offset' => $offset
    );
    
    $response = $client->send_request($request);
    
    // Convert stdClass to array if needed
    if (is_object($response)) {
        $response = json_decode(json_encode($response), true);
    }
    
    // Check if response was successful and return the scan data
    if (isset($response['success']) && $response['success'] && isset($response['scans'])) {
        $scans = $response['scans'];
        // Convert each scan object to array if needed
        if (is_array($scans)) {
            foreach ($scans as &$scan) {
                if (is_object($scan)) {
                    $scan = json_decode(json_encode($scan), true);
                }
            }
        }
        return $scans;
    } else {
        return [];
    }
}

// Pagination parameters
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$perPage = 50;
$offset = ($page - 1) * $perPage;

// Get all scans for the current page
$allScans = getAllUrlScans($perPage, $offset);

// Ensure $allScans is an array
if (!is_array($allScans)) {
    $allScans = [];
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

// Function to get user role badge
function getUserRoleBadge($userId) {
    $client = new rabbitMQClient("../testRabbitMQ.ini", "testServer");
    
    $request = array(
        'type' => 'get_user_role',
        'user_id' => $userId
    );
    
    $response = $client->send_request($request);
    
    // Convert stdClass to array if needed
    if (is_object($response)) {
        $response = json_decode(json_encode($response), true);
    }
    
    if (isset($response['success']) && $response['success'] && isset($response['role'])) {
        $roleId = $response['role']['role_id'];
        switch ($roleId) {
            case 1: return '<span class="badge bg-danger">Admin</span>';
            case 2: return '<span class="badge bg-warning">Manager</span>';
            case 3: return '<span class="badge bg-secondary">User</span>';
            default: return '<span class="badge bg-light">Unknown</span>';
        }
    }
    
    return '<span class="badge bg-light">No Role</span>';
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>All URL Scans - Manager Panel | TechTitans Security</title>
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

        .card {
            border-radius: 1rem;
            box-shadow: 0 12px 32px rgba(0, 0, 0, 0.2);
            padding: 2rem;
            margin-bottom: 2rem;
            background: rgba(255, 255, 255, 0.9);
        }

        .welcome-card {
            background: rgba(255, 255, 255, 0.9);
            border-left: 5px solid #fd7e14;
        }

        .table-card {
            background: rgba(255, 255, 255, 0.9);
        }

        .scan-url {
            max-width: 250px;
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

        .stats-card {
            background: rgba(255, 255, 255, 0.8);
            border-radius: 0.5rem;
            padding: 1rem;
            margin-bottom: 1rem;
        }

        .stat-item {
            text-align: center;
        }

        .stat-number {
            font-size: 2rem;
            font-weight: bold;
            color: #fd7e14;
        }

        .stat-label {
            color: #6c757d;
            font-size: 0.9em;
        }

        .pagination {
            justify-content: center;
        }

        .pagination .page-link {
            color: #007bff;
            border-color: #dee2e6;
        }

        .pagination .page-item.active .page-link {
            background-color: #007bff;
            border-color: #007bff;
        }

        .table {
            background: white;
            border-radius: 0.5rem;
            overflow: hidden;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        }

        .table thead th {
            background-color: #343a40;
            color: white;
            border: none;
            font-weight: 600;
            padding: 15px;
        }

        .table tbody td {
            padding: 12px 15px;
            vertical-align: middle;
        }

        .table tbody tr:hover {
            background-color: #f8f9fa;
        }

        .user-info {
            font-size: 0.9em;
        }

        .username {
            font-weight: 600;
            color: #212529;
        }

        .email {
            color: #6c757d;
            font-size: 0.8em;
        }

        .review-status {
            font-size: 0.85em;
            padding: 0.25rem 0.5rem;
            border-radius: 0.25rem;
            font-weight: 600;
        }

        .review-status.pending {
            background-color: #fff3cd;
            color: #856404;
            border: 1px solid #ffeaa7;
        }

        .review-status.approved {
            background-color: #d1ecf1;
            color: #0c5460;
            border: 1px solid #bee5eb;
        }

        .review-status.flagged {
            background-color: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }

        .review-status.archived {
            background-color: #e2e3e5;
            color: #41464b;
            border: 1px solid #d1d3d4;
        }

        .review-form {
            padding: 0.75rem;
            background-color: #f8f9fa;
            border-radius: 0.5rem;
            margin-top: 0.5rem;
        }

        .review-form select, .review-form textarea {
            font-size: 0.85em;
        }

        .review-form .btn {
            font-size: 0.8em;
            padding: 0.25rem 0.75rem;
        }

        .review-notes {
            font-style: italic;
            color: #6c757d;
            font-size: 0.8em;
            max-width: 200px;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .reviewer-info {
            font-size: 0.75em;
            color: #6c757d;
        }
    </style>
</head>
<body>
    <!-- Navbar -->
    <nav class="navbar navbar-expand-lg">
        <div class="container">
            <a class="navbar-brand" href="#">Tech Titans - Manager Panel</a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ms-auto">
                    <li class="nav-item"><a class="nav-link" href="../index.php">Home</a></li>
                    <li class="nav-item"><a class="nav-link" href="../profile.php">Profile</a></li>
                    <li class="nav-item"><a class="nav-link" href="../dashboard.php">Dashboard</a></li>
                    <li class="nav-item"><a class="nav-link" href="../view_scan_history.php">My Scans</a></li>
                    <li class="nav-item"><a class="nav-link active" href="list_all_scans.php">All Scans</a></li>
                    <li class="nav-item"><a class="nav-link" href="../admin/list_all_users.php">User Management</a></li>
                    <li class="nav-item"><a class="nav-link text-danger" href="../logout.php">Logout</a></li>
                </ul>
            </div>
        </div>
    </nav>
    
    <!-- Main content -->
    <div class="container py-5">
        <div class="row justify-content-center">
            <div class="col-lg-12">
                <div class="card welcome-card mb-4">
                    <h3 class="text-center mb-4">All URL Scans - Manager View</h3>
                    <div class="alert alert-info">
                        <p class="mb-0">Welcome, <?php echo htmlspecialchars($username); ?>! Here you can view all URL scans performed on the platform.</p>
                    </div>
                </div>

                <!-- Statistics -->
                <?php if (!empty($allScans)): ?>
                    <div class="row mb-4">
                        <div class="col-md-3">
                            <div class="stats-card">
                                <div class="stat-item">
                                    <div class="stat-number"><?php echo count($allScans); ?></div>
                                    <div class="stat-label">Scans on This Page</div>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="stats-card">
                                <div class="stat-item">
                                    <div class="stat-number">
                                        <?php 
                                        $safeScans = array_filter($allScans, function($scan) {
                                            return $scan['positive_detections'] == 0;
                                        });
                                        echo count($safeScans);
                                        ?>
                                    </div>
                                    <div class="stat-label">Safe URLs</div>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="stats-card">
                                <div class="stat-item">
                                    <div class="stat-number">
                                        <?php 
                                        $approvedScans = array_filter($allScans, function($scan) {
                                            return ($scan['review_status'] ?? 'pending') === 'approved';
                                        });
                                        echo count($approvedScans);
                                        ?>
                                    </div>
                                    <div class="stat-label">Approved</div>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="stats-card">
                                <div class="stat-item">
                                    <div class="stat-number">
                                        <?php 
                                        $pendingScans = array_filter($allScans, function($scan) {
                                            return ($scan['review_status'] ?? 'pending') === 'pending';
                                        });
                                        echo count($pendingScans);
                                        ?>
                                    </div>
                                    <div class="stat-label">Pending Review</div>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>
        
                <?php if (empty($allScans)): ?>
                    <div class="no-scans">
                        <h4 class="mb-3">No Scans Found</h4>
                        <p>No URL scans have been performed yet on this platform.</p>
                        <a href="../check-url.php" class="btn btn-primary mt-3">Go to URL Scanner</a>
                    </div>
                <?php else: ?>
                    <div class="card table-card">
                        <div class="table-responsive">
                            <table class="table table-striped table-hover">
                                <thead class="table-dark">
                                    <tr>
                                        <th>ID</th>
                                        <th>User</th>
                                        <th>Scanned URL</th>
                                        <th>Scan Date</th>
                                        <th>Result</th>
                                        <th>Detection Ratio</th>
                                        <th>Review Status</th>
                                        <th>Review Actions</th>
                                        <th>Details</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($allScans as $scan): 
                                        $severityClass = getSeverityClass($scan['positive_detections'], $scan['total_engines']);
                                        $reviewStatus = $scan['review_status'] ?? 'pending';
                                    ?>
                                        <tr>
                                            <td class="text-muted">#<?php echo htmlspecialchars($scan['id']); ?></td>
                                            <td class="user-info">
                                                <div class="username"><?php echo htmlspecialchars($scan['username'] ?? 'Unknown'); ?></div>
                                                <div class="email"><?php echo htmlspecialchars($scan['email'] ?? 'N/A'); ?></div>
                                                <?php echo getUserRoleBadge($scan['user_id']); ?>
                                            </td>
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
                                                <span class="review-status <?php echo $reviewStatus; ?>">
                                                    <?php echo ucfirst($reviewStatus); ?>
                                                </span>
                                                <?php if (!empty($scan['review_notes'])): ?>
                                                    <div class="review-notes" title="<?php echo htmlspecialchars($scan['review_notes']); ?>">
                                                        <?php echo htmlspecialchars($scan['review_notes']); ?>
                                                    </div>
                                                <?php endif; ?>
                                                <?php if (!empty($scan['reviewed_by'])): ?>
                                                    <div class="reviewer-info">
                                                        Reviewed by: <?php echo htmlspecialchars($scan['reviewer_username'] ?? 'Unknown'); ?>
                                                        <?php if (!empty($scan['reviewed_at'])): ?>
                                                            <br>on <?php echo formatDate($scan['reviewed_at']); ?>
                                                        <?php endif; ?>
                                                    </div>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <button class="btn btn-sm btn-outline-primary" onclick="toggleReviewForm(<?php echo $scan['id']; ?>)">
                                                    Update Review
                                                </button>
                                                <div id="reviewForm<?php echo $scan['id']; ?>" class="review-form" style="display: none;">
                                                    <form onsubmit="updateReview(event, <?php echo $scan['id']; ?>)">
                                                        <div class="mb-2">
                                                            <select class="form-select form-select-sm" name="review_status" required>
                                                                <option value="pending" <?php echo $reviewStatus === 'pending' ? 'selected' : ''; ?>>Pending</option>
                                                                <option value="approved" <?php echo $reviewStatus === 'approved' ? 'selected' : ''; ?>>Approved</option>
                                                                <option value="flagged" <?php echo $reviewStatus === 'flagged' ? 'selected' : ''; ?>>Flagged</option>
                                                                <option value="archived" <?php echo $reviewStatus === 'archived' ? 'selected' : ''; ?>>Archived</option>
                                                            </select>
                                                        </div>
                                                        <div class="mb-2">
                                                            <textarea class="form-control form-control-sm" name="review_notes" placeholder="Review notes (optional)" rows="2"><?php echo htmlspecialchars($scan['review_notes'] ?? ''); ?></textarea>
                                                        </div>
                                                        <div class="d-flex gap-1">
                                                            <button type="submit" class="btn btn-success btn-sm">Save</button>
                                                            <button type="button" class="btn btn-secondary btn-sm" onclick="toggleReviewForm(<?php echo $scan['id']; ?>)">Cancel</button>
                                                        </div>
                                                    </form>
                                                </div>
                                            </td>
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
                        
                        <!-- Pagination -->
                        <nav aria-label="Scan history pagination" class="mt-4">
                            <ul class="pagination">
                                <?php if ($page > 1): ?>
                                    <li class="page-item">
                                        <a class="page-link" href="?page=<?php echo $page - 1; ?>" aria-label="Previous">
                                            <span aria-hidden="true">&laquo;</span>
                                        </a>
                                    </li>
                                <?php endif; ?>
                                
                                <?php for ($i = max(1, $page - 2); $i <= $page + 2; $i++): ?>
                                    <li class="page-item <?php echo ($i == $page) ? 'active' : ''; ?>">
                                        <a class="page-link" href="?page=<?php echo $i; ?>"><?php echo $i; ?></a>
                                    </li>
                                <?php endfor; ?>
                                
                                <?php if (count($allScans) == $perPage): ?>
                                    <li class="page-item">
                                        <a class="page-link" href="?page=<?php echo $page + 1; ?>" aria-label="Next">
                                            <span aria-hidden="true">&raquo;</span>
                                        </a>
                                    </li>
                                <?php endif; ?>
                            </ul>
                        </nav>
                    </div>
                <?php endif; ?>
                
                <div class="text-center mt-4">
                    <a href="../dashboard.php" class="btn btn-outline-secondary">Back to Dashboard</a>
                    <a href="../admin/list_all_users.php" class="btn btn-outline-primary">User Management</a>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        function toggleReviewForm(scanId) {
            const form = document.getElementById('reviewForm' + scanId);
            if (form.style.display === 'none') {
                form.style.display = 'block';
            } else {
                form.style.display = 'none';
            }
        }

        function updateReview(event, scanId) {
            event.preventDefault();
            
            const form = event.target;
            const formData = new FormData(form);
            formData.append('action', 'update_review');
            formData.append('scan_id', scanId);

            // Show loading state
            const submitBtn = form.querySelector('button[type="submit"]');
            const originalText = submitBtn.textContent;
            submitBtn.textContent = 'Saving...';
            submitBtn.disabled = true;

            fetch(window.location.href, {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Show success message
                    alert('Review updated successfully!');
                    // Reload the page to show updated data
                    window.location.reload();
                } else {
                    alert('Error updating review: ' + (data.message || 'Unknown error'));
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('An error occurred while updating the review.');
            })
            .finally(() => {
                // Reset button state
                submitBtn.textContent = originalText;
                submitBtn.disabled = false;
            });
        }
    </script>
</body>
</html>
