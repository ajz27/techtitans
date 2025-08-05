<?php
// Show all errors for debugging
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();
require_once('session_check.inc');
require_once('path.inc');
require_once('get_host_info.inc');
require_once('rabbitMQLib.inc');

// Check user role
$user = getSessionUser();
$isAdmin = ($user && $user['role_id'] == 1);
$isManager = ($user && $user['role_id'] == 2);
$isUser = ($user && $user['role_id'] == 3);

// Extract filters and limits from URL params
$limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 10;
$filter_status = $_GET['status'] ?? '';
$filter_type = $_GET['scan_type'] ?? '';

// Prepare RabbitMQ request
$request = [
    'type' => 'get_scans',
    'limit' => $limit,
    'status' => $filter_status,
    'scan_type' => $filter_type
];

try {
    $client = new rabbitMQClient("testRabbitMQ.ini", "testServer");
    $response = $client->send_request($request);

    // Safely decode and extract data
    $scans = [];

    if (isset($response['success']) && $response['success'] === true && isset($response['data']) && is_array($response['data'])) {
        $scans = $response['data'];
    } else {
        $error_message = isset($response['message']) ? $response['message'] : 'Unexpected response from server.';
        error_log("Scan fetch error: $error_message");
    }

} catch (Exception $e) {
    error_log("RabbitMQ error: " . $e->getMessage());
    $scans = [];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Review Submitted Scans | Tech Titans</title>
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

        .container {
            background: rgba(255, 255, 255, 0.95);
            border-radius: 1rem;
            box-shadow: 0 12px 32px rgba(0, 0, 0, 0.15);
            padding: 2rem;
            margin-top: 2rem;
        }

        table { 
            border-collapse: collapse; 
            width: 100%; 
            margin-top: 20px; 
        }
        
        th, td { 
            padding: 8px; 
            border: 1px solid #ccc; 
            text-align: left; 
        }
        
        .form-section { 
            margin-bottom: 20px; 
            padding: 1rem;
            background: rgba(248, 249, 250, 0.8);
            border-radius: 0.5rem;
        }
    </style>
</head>
<body>

  <!-- Navbar -->
  <nav class="navbar navbar-expand-lg">
    <div class="container-fluid">
      <a class="navbar-brand" href="#">Tech Titans</a>
      <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
        <span class="navbar-toggler-icon"></span>
      </button>
      <div class="collapse navbar-collapse" id="navbarNav">
        <ul class="navbar-nav ms-auto">
          <li class="nav-item"><a class="nav-link" href="index.php">Home</a></li>
          <li class="nav-item"><a class="nav-link" href="profile.php">Profile</a></li>
          <li class="nav-item"><a class="nav-link" href="dashboard.php">Dashboard</a></li>
          <li class="nav-item"><a class="nav-link" href="view_scan_history.php">Scan History</a></li>
          <?php if ($isManager || $isAdmin): ?>
          <li class="nav-item"><a class="nav-link manager-link" href="manager/list_all_scans.php">📋 Manager Panel</a></li>
          <?php endif; ?>
          <?php if ($isAdmin): ?>
          <li class="nav-item"><a class="nav-link admin-link" href="admin/list_all_users.php">👑 Admin Panel</a></li>
          <?php endif; ?>
          <li class="nav-item"><a class="nav-link text-danger" href="logout.php">Logout</a></li>
        </ul>
      </div>
    </div>
  </nav>

  <div class="container">
    <h2 class="mb-4">Review Submitted Scans</h2>

    <div class="form-section">
        <form method="GET" class="row g-3">
            <div class="col-md-3">
                <label class="form-label">Status:</label>
                <select name="status" class="form-select">
                    <option value="">All</option>
                    <option value="flagged" <?= $filter_status === 'flagged' ? 'selected' : '' ?>>Flagged</option>
                    <option value="approved" <?= $filter_status === 'approved' ? 'selected' : '' ?>>Approved</option>
                    <option value="archived" <?= $filter_status === 'archived' ? 'selected' : '' ?>>Archived</option>
                    <option value="pending" <?= $filter_status === 'pending' ? 'selected' : '' ?>>Pending</option>
                </select>
            </div>

            <div class="col-md-3">
                <label class="form-label">Scan Type:</label>
                <select name="scan_type" class="form-select">
                    <option value="">All</option>
                    <option value="url" <?= $filter_type === 'url' ? 'selected' : '' ?>>URL</option>
                    <option value="ip" <?= $filter_type === 'ip' ? 'selected' : '' ?>>IP</option>
                    <option value="domain" <?= $filter_type === 'domain' ? 'selected' : '' ?>>Domain</option>
                </select>
            </div>

            <div class="col-md-3">
                <label class="form-label">Show:</label>
                <select name="limit" class="form-select">
                    <?php foreach ([10, 25, 50, 100] as $l): ?>
                        <option value="<?= $l ?>" <?= $limit === $l ? 'selected' : '' ?>><?= $l ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="col-md-3 d-flex align-items-end">
                <button type="submit" class="btn btn-primary">Apply</button>
            </div>
        </form>
    </div>

    <div class="table-responsive">
        <table class="table table-striped table-hover">
            <thead class="table-dark">
                <tr>
                    <th>ID</th>
                    <th>Submitted By</th>
                    <th>Type</th>
                    <th>Value</th>
                    <th>Status</th>
                    <th>Safety</th>
                    <th>Risk Score</th>
                    <th>Antivirus</th>
                    <th>Notes</th>
                    <th>Timestamp</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
            <?php if (!empty($scans)): ?>
                <?php foreach ($scans as $row): ?>
                    <tr>
                        <td><?= htmlspecialchars($row['id']) ?></td>
                        <td><?= htmlspecialchars($row['submitted_by']) ?></td>
                        <td><?= htmlspecialchars($row['scan_type']) ?></td>
                        <td><?= htmlspecialchars($row['scan_value']) ?></td>
                        <td><?= htmlspecialchars($row['status']) ?></td>
                        <td><?= htmlspecialchars($row['safety']) ?></td>
                        <td><?= htmlspecialchars($row['risk_score']) ?></td>
                        <td><?= htmlspecialchars($row['antivirus_results']) ?></td>
                        <td><?= htmlspecialchars($row['notes']) ?></td>
                        <td><?= htmlspecialchars($row['timestamp']) ?></td>
                        <td>
                            <form action="update_scan.php" method="POST" style="display:inline;">
                                <input type="hidden" name="scan_id" value="<?= htmlspecialchars($row['id']) ?>">
                                <select name="status">
                                    <option value="approved">Approve</option>
                                    <option value="flagged">Flag</option>
                                    <option value="archived">Archive</option>
                                </select>
                                <input type="text" name="notes" placeholder="Add note" class="form-control form-control-sm d-inline-block" style="width: auto;">
                                <button type="submit" class="btn btn-sm btn-success">Update</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php else: ?>
                <tr><td colspan="11" class="text-center">No scans found or an error occurred.</td></tr>
            <?php endif; ?>
        </tbody>
    </table>
    </div> <!-- Close table-responsive -->

  </div> <!-- Close container -->

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
