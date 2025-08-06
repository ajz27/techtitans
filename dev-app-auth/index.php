<?php
require_once('session_check.inc');
requireLogin();
$user = getSessionUser();
$username = htmlspecialchars($user['username']);

// Check user role
$isAdmin = ($user && $user['role_id'] == 1);
$isManager = ($user && $user['role_id'] == 2);
$isUser = ($user && $user['role_id'] == 3);
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
    
    .welcome-card {
      background: rgba(255, 255, 255, 0.95);
      padding: 2rem;
      border-radius: 1rem;
      box-shadow: 0 20px 40px rgba(0, 0, 0, 0.15);
      margin: 2rem auto;
      text-align: center;
      max-width: 600px;
    }

    .feature-card {
      background: rgba(255, 255, 255, 0.9);
      border-radius: 0.75rem;
      padding: 1.5rem;
      margin-bottom: 1rem;
      box-shadow: 0 8px 20px rgba(0, 0, 0, 0.1);
      transition: transform 0.2s ease;
    }

    .feature-card:hover {
      transform: translateY(-5px);
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
        <li class="nav-item"><a class="nav-link active" href="index.php">Home</a></li>
        <li class="nav-item"><a class="nav-link" href="profile.php">Profile</a></li>
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

<!-- Welcome Card -->
<div class="container">
  <div class="welcome-card">
    <h2 class="mb-4">Welcome back, <?= $username ?>! 🎉</h2>
    <p class="lead mb-4">Your comprehensive security scanning platform</p>
    
    <div class="row g-3">
      <div class="col-md-6">
        <div class="feature-card">
          <i class="bi bi-shield-check fs-2 text-primary mb-2"></i>
          <h5>URL Scanner</h5>
          <p class="text-muted mb-3">Scan URLs for malicious content and security threats</p>
          <a href="check-url.php" class="btn btn-primary btn-sm">Start Scanning</a>
        </div>
      </div>
      
      <div class="col-md-6">
        <div class="feature-card">
          <i class="bi bi-globe fs-2 text-success mb-2"></i>
          <h5>Domain Scanner</h5>
          <p class="text-muted mb-3">Check domain reputation and security status</p>
          <a href="check-domain.php" class="btn btn-success btn-sm">Check Domain</a>
        </div>
      </div>
      
      <div class="col-md-6">
        <div class="feature-card">
          <i class="bi bi-clock-history fs-2 text-info mb-2"></i>
          <h5>Scan History</h5>
          <p class="text-muted mb-3">View your previous scans and results</p>
          <a href="view_scan_history.php" class="btn btn-info btn-sm">View History</a>
        </div>
      </div>
      
      <div class="col-md-6">
        <div class="feature-card">
          <i class="bi bi-speedometer2 fs-2 text-warning mb-2"></i>
          <h5>Dashboard</h5>
          <p class="text-muted mb-3">Quick access to all scanning tools</p>
          <a href="dashboard.php" class="btn btn-warning btn-sm">Go to Dashboard</a>
        </div>
      </div>
      
      <?php if ($isManager || $isAdmin): ?>
      <div class="col-md-6">
        <div class="feature-card">
          <i class="bi bi-clipboard-data fs-2 text-success mb-2"></i>
          <h5>Manager Panel</h5>
          <p class="text-muted mb-3">Review and manage all system scans</p>
          <a href="manager/list_all_scans.php" class="btn btn-success btn-sm">📋 Manager Panel</a>
        </div>
      </div>
      <?php endif; ?>
      
      <?php if ($isAdmin): ?>
      <div class="col-md-6">
        <div class="feature-card">
          <i class="bi bi-people fs-2 text-danger mb-2"></i>
          <h5>Admin Panel</h5>
          <p class="text-muted mb-3">Manage users and system administration</p>
          <a href="admin/list_all_users.php" class="btn btn-danger btn-sm">👑 Admin Panel</a>
        </div>
      </div>
      <?php endif; ?>
    </div>
    
    <div class="mt-4 pt-4 border-top">
      <p class="text-muted mb-3">Account Management</p>
      <div class="d-flex gap-2 justify-content-center">
        <a href="profile.php" class="btn btn-outline-secondary"><i class="bi bi-person"></i> Profile</a>
        <a href="logout.php" class="btn btn-outline-danger"><i class="bi bi-box-arrow-right"></i> Logout</a>
      </div>
    </div>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

</body>
</html>
