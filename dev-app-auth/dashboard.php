<?php
session_start();
error_reporting(E_ALL & ~E_DEPRECATED);

// Include required files
require_once('path.inc');
require_once('get_host_info.inc');
require_once('rabbitMQLib.inc');

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.html");
    exit();
}

$userId = $_SESSION['user_id'];
$username = $_SESSION['username'] ?? 'User';

// Function to check if user is admin or manager
function getUserRole($userId) {
    $client = new rabbitMQClient("testRabbitMQ.ini", "testServer");
    
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
        return $response['role']['role_id'];
    }
    
    return null;
}

$userRole = getUserRole($userId);
$isAdmin = ($userRole == 1);
$isManager = ($userRole == 2);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>URL Checker Dashboard</title>
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

    .nav-link.admin-link {
      color: #ff6b7a !important;
      font-weight: 600;
    }

    .nav-link.admin-link:hover {
      color: #ff8a9a !important;
    }

    .nav-link.manager-link {
      color: #ffc107 !important;
      font-weight: 600;
    }

    .nav-link.manager-link:hover {
      color: #ffda6a !important;
    }

    .nav-link.active {
      background-color: rgba(255, 255, 255, 0.1);
      border-radius: 0.375rem;
    }

    .card {
      border-radius: 1rem;
      box-shadow: 0 12px 32px rgba(0, 0, 0, 0.2);
      padding: 2rem;
    }

    .form-control:focus {
      border-color: #007bff;
      box-shadow: 0 0 6px rgba(0, 123, 255, 0.3);
    }

    .btn-primary {
      font-weight: 500;
      transition: background-color 0.3s ease;
    }

    .btn-primary:hover {
      background-color: #0056b3;
    }

    .admin-section {
      background: linear-gradient(135deg, rgba(220, 53, 69, 0.1), rgba(255, 107, 122, 0.1));
      border: 2px solid rgba(220, 53, 69, 0.3);
      border-radius: 0.5rem;
      padding: 1rem;
      margin-top: 1rem;
    }

    .admin-section h5 {
      color: #dc3545;
      margin-bottom: 0.5rem;
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
          <li class="nav-item"><a class="nav-link" href="profile.php">Profile</a></li>
          <li class="nav-item"><a class="nav-link active" href="dashboard.php">Dashboard</a></li>
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

  <!-- URL Checker Form -->
  <div class="container d-flex justify-content-center align-items-center" style="min-height: 80vh;">
    <div class="card w-100" style="max-width: 500px;">
      <h3 class="text-center mb-4">🔎 URL Checker Tool</h3>
      <p class="text-center mb-4">Welcome, <?php echo htmlspecialchars($username); ?>! Scan any URL against VirusTotal to check for security threats</p>
      <form action="check-url.php" method="POST">
        <div class="mb-3">
          <label for="url" class="form-label">Website URL</label>
          <input type="url" class="form-control" id="url" name="url" placeholder="https://example.com" required />
        </div>
        <button type="submit" class="btn btn-primary w-100 mb-3">Scan URL</button>
      </form>
      <div class="text-center mt-4">
        <a href="view_scan_history.php" class="btn btn-outline-primary">View Scan History</a>
      </div>
      
      <?php if ($isAdmin): ?>
      <div class="admin-section">
        <h5>👑 Admin Functions</h5>
        <p class="small mb-2">You have administrator privileges</p>
        <a href="admin/list_all_users.php" class="btn btn-outline-danger btn-sm">Manage All Users</a>
      </div>
      <?php endif; ?>
    </div>
  </div>

</body>
</html>
