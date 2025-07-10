<?php
require_once('session_check.inc');
requireLogin();
$user = getSessionUser();
$username = htmlspecialchars($user['username']);
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
  background: linear-gradient(to right, #00c9ff, #92fe9d);
  font-family: 'Segoe UI', sans-serif;
  height: 100vh;
  margin: 0;
  display: flex;
  flex-direction: column;
}

.navbar {
  background-color: rgba(255, 255, 255, 0.95);
  box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
  padding-top: 0.5rem;
  padding-bottom: 0.5rem;
  z-index: 999;
}


.navbar-toggler {
  padding: 0.25rem 0.5rem;
  font-size: 1rem;
}


.navbar-collapse {
  background-color: white;
  padding: 1rem;
  border-radius: 0.5rem;
  box-shadow: 0 8px 16px rgba(0,0,0,0.1);
}

.navbar-nav .nav-link {
  padding-right: 1rem;
  padding-left: 1rem;
  transition: color 0.2s ease;
}

.nav-link.active {
  font-weight: bold;
}

.welcome-card {
  background: rgba(255, 255, 255, 0.95);
  padding: 2rem;
  border-radius: 1rem;
  box-shadow: 0 20px 40px rgba(0, 0, 0, 0.2);
  margin: auto;
  text-align: center;
  max-width: 480px;
}

@media (max-width: 991.98px) {
  .navbar-collapse {
    position: absolute;
    top: 60px; /* below navbar */
    right: 1rem;
    left: auto;
    width: 220px;
    border-radius: 1rem;
    box-shadow: 0 8px 24px rgba(0,0,0,0.15);
    z-index: 999;
  }

  .navbar-nav {
    flex-direction: column;
    text-align: left;
  }

  .navbar-nav .nav-item {
    margin: 0.5rem 0;
  }
}


.transition {
  transition: all 0.3s ease-in-out;
}

  </style>
</head>
<body>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

<!-- Navbar -->
<nav class="navbar navbar-expand-lg navbar-light bg-white shadow-sm sticky-top">
  <div class="container">
    <a class="navbar-brand fw-bold" href="#">Tech Titans</a>
    <!-- Hamburger button -->
    <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav"
      aria-controls="navbarNav" aria-expanded="false" aria-label="Toggle navigation">
      <span class="navbar-toggler-icon"></span>
    </button>

    <!-- Collapsible links -->
    <div class="collapse navbar-collapse justify-content-end" id="navbarNav">
      <ul class="navbar-nav">
        <li class="nav-item">
          <a class="nav-link active" href="index.php">Home</a>
        </li>
        <li class="nav-item">
          <a class="nav-link" href="profile.php">Profile</a>
        </li>
        <li class="nav-item">
          <a class="nav-link" href="dashboard.html">Dashboard</a>
        </li>
        <li class="nav-item">
          <a class="nav-link text-danger" href="logout.php">Logout</a>
        </li>
      </ul>
    </div>
  </div>
</nav>


<!-- Welcome Card -->
<div class="welcome-card">
<h3 class="mb-3">Welcome<?= $username ? ', <strong>' . $username . '</strong>!' : '!' ?></h3>
  <p class="mb-4">This is your main portal. From here, you can:</p>
  <div class="d-grid gap-2">
    <a href="dashboard.html" class="btn btn-outline-primary"><i class="bi bi-link-45deg"></i> Access the URL Checker Tool</a>
    <a href="profile.php" class="btn btn-outline-secondary"><i class="bi bi-person"></i> View your Profile</a>
    <a href="logout.php" class="btn btn-outline-danger"><i class="bi bi-box-arrow-right"></i> Log Out</a>
  </div>
</div>

</body>
</html>
