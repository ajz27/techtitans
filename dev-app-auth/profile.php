<?php
require_once('session_check.inc');
requireLogin();

// Include RabbitMQ library for database communication
require_once('path.inc');
require_once('get_host_info.inc');
require_once('rabbitMQLib.inc');

$user = getSessionUser();
$username = htmlspecialchars($user['username']);
$email = htmlspecialchars($user['email']);
$userId = $user['user_id'];

// Handle profile update
$updateMessage = '';
$updateSuccess = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profile'])) {
    $newUsername = trim($_POST['username']);
    $newEmail = trim($_POST['email']);
    
    // Basic validation
    if (empty($newUsername) || empty($newEmail)) {
        $updateMessage = 'Username and email are required.';
    } elseif (strlen($newUsername) > 30) {
        $updateMessage = 'Username must be 30 characters or less.';
    } elseif (!filter_var($newEmail, FILTER_VALIDATE_EMAIL)) {
        $updateMessage = 'Please enter a valid email address.';
    } elseif (strlen($newEmail) > 100) {
        $updateMessage = 'Email must be 100 characters or less.';
    } else {
        // Send update request via RabbitMQ
        $client = new rabbitMQClient("testRabbitMQ.ini", "testServer");
        $request = array(
            'type' => 'update_user_profile',
            'user_id' => $userId,
            'username' => $newUsername,
            'email' => $newEmail
        );
        
        $response = $client->send_request($request);
        
        // Convert response to array if it's an object
        if (is_object($response)) {
            $response = (array)$response;
        }
        
        if (isset($response['success']) && $response['success']) {
            $updateSuccess = true;
            $updateMessage = 'Profile updated successfully!';
            
            // Update session variables
            $_SESSION['username'] = $newUsername;
            $_SESSION['email'] = $newEmail;
            
            // Update local variables
            $username = htmlspecialchars($newUsername);
            $email = htmlspecialchars($newEmail);
        } else {
            $updateMessage = isset($response['message']) ? $response['message'] : 'Failed to update profile.';
        }
    }
}

// Handle pagination for scan history
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$limit = 10;
$offset = ($page - 1) * $limit;

// Function to get scan history with pagination
function getUserScanHistoryPaginated($userId, $limit, $offset) {
    $client = new rabbitMQClient("testRabbitMQ.ini", "testServer");
    
    $request = array(
        'type' => 'get_user_url_scans_paginated',
        'user_id' => $userId,
        'limit' => $limit,
        'offset' => $offset
    );
    
    $response = $client->send_request($request);
    
    // Convert response to array if it's an object
    if (is_object($response)) {
        $response = (array)$response;
    }
    
    if (isset($response['success']) && $response['success']) {
        $scans = $response['scans'];
        if (is_object($scans)) {
            $scans = (array)$scans;
        }
        
        return array(
            'scans' => $scans,
            'total' => $response['total'],
            'limit' => $response['limit'],
            'offset' => $response['offset']
        );
    }
    
    return array('scans' => [], 'total' => 0, 'limit' => $limit, 'offset' => $offset);
}

$scanData = getUserScanHistoryPaginated($userId, $limit, $offset);
$scanHistory = $scanData['scans'];
$totalScans = $scanData['total'];
$totalPages = ceil($totalScans / $limit);

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
    try {
        $date = new DateTime($dateString);
        return $date->format('Y-m-d H:i:s');
    } catch (Exception $e) {
        return "N/A";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <title>Your Profile</title>
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet" />
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons/font/bootstrap-icons.css" rel="stylesheet" />
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

    .profile-card, .scan-history-card {
      background: rgba(255, 255, 255, 0.95);
      padding: 2rem;
      border-radius: 1rem;
      box-shadow: 0 20px 40px rgba(0, 0, 0, 0.15);
      margin-bottom: 2rem;
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
    .pagination {
      justify-content: center;
    }
    .alert {
      margin-bottom: 1rem;
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
    <div class="collapse navbar-collapse justify-content-end" id="navbarNav">
      <ul class="navbar-nav">
        <li class="nav-item"><a class="nav-link" href="index.php">Home</a></li>
        <li class="nav-item"><a class="nav-link active" href="profile.php">Profile</a></li>
        <li class="nav-item"><a class="nav-link" href="check-url.php">URL Scan</a></li>
        <li class="nav-item"><a class="nav-link" href="check-domain.php">Domain Scan</a></li>
        <li class="nav-item"><a class="nav-link" href="view_scan_history.php">Scan History</a></li>
        <li class="nav-item"><a class="nav-link manager-link" href="manager/list_all_scans.php">📋 Manager Panel</a></li>
        <li class="nav-item"><a class="nav-link admin-link" href="admin/list_all_users.php">👑 Admin Panel</a></li>
        <li class="nav-item"><a class="nav-link text-danger" href="logout.php">Logout</a></li>
      </ul>
    </div>
  </div>
</nav>

<div class="container py-5">
  <div class="row">
    <!-- Profile Information and Update Form -->
    <div class="col-lg-6">
      <div class="profile-card">
        <h3 class="text-center mb-4">Profile Information</h3>
        
        <?php if ($updateMessage): ?>
          <div class="alert <?= $updateSuccess ? 'alert-success' : 'alert-danger' ?>" role="alert">
            <?= htmlspecialchars($updateMessage) ?>
          </div>
        <?php endif; ?>
        
        <form method="POST" action="">
          <div class="mb-3">
            <label for="username" class="form-label">Username:</label>
            <input type="text" class="form-control" id="username" name="username" value="<?= $username ?>" maxlength="30" required>
            <div class="form-text">Maximum 30 characters</div>
          </div>
          
          <div class="mb-3">
            <label for="email" class="form-label">Email:</label>
            <input type="email" class="form-control" id="email" name="email" value="<?= $email ?>" maxlength="100" required>
            <div class="form-text">Maximum 100 characters</div>
          </div>
          
          <div class="text-center">
            <button type="submit" name="update_profile" class="btn btn-primary">Update Profile</button>
          </div>
        </form>
      </div>
    </div>
    
    <!-- Scan History -->
    <div class="col-lg-6">
      <div class="scan-history-card">
        <h3 class="text-center mb-4">Recent URL Scans</h3>
        
        <?php if (empty($scanHistory)): ?>
          <div class="text-center">
            <p class="text-muted">No scan history found.</p>
            <a href="check-url.php" class="btn btn-primary">Go to URL Scanner</a>
          </div>
        <?php else: ?>
          <div class="table-responsive">
            <table class="table table-striped table-sm">
              <thead>
                <tr>
                  <th>URL</th>
                  <th>Date</th>
                  <th>Result</th>
                  <th>Ratio</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($scanHistory as $scan): 
                  // Convert each scan to an array if it's an object
                  if (is_object($scan)) {
                    $scan = (array)$scan;
                  }
                  $severityClass = getSeverityClass($scan['positive_detections'], $scan['total_engines']);
                ?>
                  <tr>
                    <td class="scan-url" title="<?= htmlspecialchars($scan['scanned_url']) ?>">
                      <?= htmlspecialchars(substr($scan['scanned_url'], 0, 30)) ?><?= strlen($scan['scanned_url']) > 30 ? '...' : '' ?>
                    </td>
                    <td><?= formatDate($scan['scan_timestamp']) ?></td>
                    <td class="<?= $severityClass ?>">
                      <?php
                      if ($scan['positive_detections'] == 0) {
                        echo '✅';
                      } else if ($scan['positive_detections'] < 3) {
                        echo '⚠️';
                      } else if ($scan['positive_detections'] < 10) {
                        echo '🔶';
                      } else {
                        echo '❌';
                      }
                      ?>
                    </td>
                    <td><?= $scan['positive_detections'] ?>/<?= $scan['total_engines'] ?></td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
          
          <!-- Pagination -->
          <?php if ($totalPages > 1): ?>
            <nav aria-label="Scan history pagination">
              <ul class="pagination pagination-sm">
                <!-- Previous button -->
                <?php if ($page > 1): ?>
                  <li class="page-item">
                    <a class="page-link" href="?page=<?= $page - 1 ?>">Previous</a>
                  </li>
                <?php endif; ?>
                
                <!-- Page numbers -->
                <?php for ($i = max(1, $page - 2); $i <= min($totalPages, $page + 2); $i++): ?>
                  <li class="page-item <?= $i == $page ? 'active' : '' ?>">
                    <a class="page-link" href="?page=<?= $i ?>"><?= $i ?></a>
                  </li>
                <?php endfor; ?>
                
                <!-- Next button -->
                <?php if ($page < $totalPages): ?>
                  <li class="page-item">
                    <a class="page-link" href="?page=<?= $page + 1 ?>">Next</a>
                  </li>
                <?php endif; ?>
              </ul>
            </nav>
          <?php endif; ?>
          
          <div class="text-center mt-3">
            <a href="view_scan_history.php" class="btn btn-outline-primary btn-sm">View Full History</a>
          </div>
        <?php endif; ?>
      </div>
    </div>
  </div>
  
  <div class="text-center mt-4">
    <p>Use the navigation bar above to explore the portal.</p>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
