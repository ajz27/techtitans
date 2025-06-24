<?php
session_start();
if (!isset($_SESSION['username'])) {
    header("Location: login.html");
    exit();
}
$username = htmlspecialchars($_SESSION['username']);
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
      background-color: rgba(255, 255, 255, 0.9);
      box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
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
    .nav-link.active {
      font-weight: bold;
    }
  </style>
</head>
<body>

<!-- Navbar -->
<nav class="navbar navbar-expand-lg sticky-top">
  <div class="container">
    <a class="navbar-brand fw-bold" href="#">Tech Titans</a>
    <div class="collapse navbar-collapse justify-content-end">
      <ul class="navbar-nav">
        <li class="nav-item"><a class="nav-link" href="index.php">Home</a></li>
        <li class="nav-item"><a class="nav-link" href="profile.php">Profile</a></li>
        <li class="nav-item"><a class="nav-link" href="dashboard.html">Dashboard</a></li>
        <li class="nav-item"><a class="nav-link text-danger" href="logout.php">Logout</a></li>
      </ul>
    </div>
  </div>
</nav>

<!-- Welcome Card -->
<div class="welcome-card">
  <h3 class="mb-3">Welcome back, <?= $username ?>!</h3>
  <p class="mb-4">This is your main portal. From here, you can:</p>
  <div class="d-grid gap-2">
    <a href="#" class="btn btn-outline-primary"><i class="bi bi-link-45deg"></i> Access the URL Checker Tool</a>
    <a href="profile.php" class="btn btn-outline-secondary"><i class="bi bi-person"></i> View your Profile</a>
    <a href="logout.php" class="btn btn-outline-danger"><i class="bi bi-box-arrow-right"></i> Log Out</a>
  </div>
</div>

</body>
</html>
