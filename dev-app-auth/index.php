<?php
require_once('session_check.inc');
requireLogin();

// Include RabbitMQ library for role checking
require_once('path.inc');
require_once('get_host_info.inc');
require_once('rabbitMQLib.inc');

$user = getSessionUser();
$username = htmlspecialchars($user['username']);
$userId = $user['user_id'];

// Function to check user role
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
  <title>Welcome | Tech Titans</title>
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet" />
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons/font/bootstrap-icons.css" rel="stylesheet" />
  <style>
    body {
      background: linear-gradient(135deg, #f0eff2, #66a6ff);
      font-family: 'Segoe UI', sans-serif;
      min-height: 100vh;
      margin: 0;
      display: flex;
      flex-direction: column;
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
      background-color: rgba(255, 255, 255, 0.1);
      border-radius: 0.375rem;
    }
    .admin-link {
      color: #ff6b7a !important;
      font-weight: 600;
    }
    .admin-link:hover {
      color: #ff8a9a !important;
    }
    .manager-link {
      color: #ffc107 !important;
      font-weight: 600;
    }
    .manager-link:hover {
      color: #ffda6a !important;
    }
    .welcome-card {
      background: rgba(255, 255, 255, 0.95);
      padding: 2rem;
      border-radius: 1rem;
      box-shadow: 0 20px 40px rgba(0, 0, 0, 0.2);
      margin: auto;
      text-align: center;
      max-width: 520px;
    }
  </style>
</head>
<body>

<!-- Navbar -->
<nav class="navbar navbar-expand-lg sticky-top">
  <div class="container">
    <a class="navbar-brand fw-bold" href="#">Tech Titans</a>
    <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
      <span class="navbar-toggler-icon"></span>
    </button>
    <div class="collapse navbar-collapse justify-content-end" id="navbarNav">
      <ul class="navbar-nav">
        <li class="nav-item"><a class="nav-link active" href="index.php">Home</a></li>
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

<!-- Welcome Card -->
<div class="welcome-card">
  <h3 class="mb-3">Welcome back, <?= $username ?>!</h3>
  <?php if ($isAdmin): ?>
    <p class="text-danger mb-3"><i class="bi bi-shield-fill-check"></i> Administrator Access</p>
  <?php elseif ($isManager): ?>
    <p class="text-warning mb-3"><i class="bi bi-person-badge"></i> Manager Access</p>
  <?php endif; ?>
  <p class="mb-4">This is your main portal. From here, you can:</p>
  <div class="d-grid gap-2">
    <a href="dashboard.php" class="btn btn-outline-primary"><i class="bi bi-link-45deg"></i> Access the Scanner Tools</a>
    <a href="view_scan_history.php" class="btn btn-outline-info"><i class="bi bi-clock-history"></i> View Scan History</a>
    <a href="profile.php" class="btn btn-outline-secondary"><i class="bi bi-person"></i> View your Profile</a>
    <?php if ($isManager || $isAdmin): ?>
    <a href="manager/list_all_scans.php" class="btn btn-outline-warning"><i class="bi bi-list-check"></i> Manager Panel</a>
    <?php endif; ?>
    <?php if ($isAdmin): ?>
    <a href="admin/list_all_users.php" class="btn btn-outline-danger"><i class="bi bi-people"></i> Admin Panel</a>
    <?php endif; ?>
    <a href="logout.php" class="btn btn-outline-danger"><i class="bi bi-box-arrow-right"></i> Log Out</a>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
