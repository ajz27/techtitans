<?php

session_start();
require_once('session_check.inc');
require_once('rabbitMQLib.inc');


$url = "";
$scanResult = null;
$error = "";


if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['url'])) {
    $url = filter_var($_POST['url'], FILTER_SANITIZE_URL);
    
    if (filter_var($url, FILTER_VALIDATE_URL)) {
        
        
        
        $client = new rabbitMQClient("apiRabbitMQ.ini", "apiRequest");
        
        // Create request message
        $request = array();
        $request['type'] = "virus_scan";
        $request['url'] = $url;
        
        // Include user_id if user is logged in for database association
        if (isset($_SESSION['user_id'])) {
            $request['user_id'] = $_SESSION['user_id'];
        }
        
        // Send request to API server
        $response = $client->send_request($request);
        
        
        
        if ($response) {
            $scanResult = $response;
        } else {
            $error = "Failed to get response from API server";
        }
    } else {
        $error = "Please enter a valid URL";
    }
}


?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>URL Scan Results | Tech Titans</title>
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
    }

    .result-card {
      border-left: 5px solid #007bff;
    }

    .error-card {
      border-left: 5px solid #dc3545;
    }

    .stats-box {
      background: rgba(255, 255, 255, 0.7);
      border-radius: 0.5rem;
      padding: 1rem;
      margin-bottom: 1rem;
      box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
    }

    .detection-safe {
      color: #198754;
    }

    .detection-warning {
      color: #ffc107;
    }

    .detection-danger {
      color: #dc3545;
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
          <li class="nav-item"><a class="nav-link active" href="dashboard.html">Dashboard</a></li>
          <li class="nav-item"><a class="nav-link" href="Scan_history.php">Scan History</a></li>
          <li class="nav-item"><a class="nav-link text-danger" href="logout.php">Logout</a></li>
        </ul>
      </div>
    </div>
  </nav>

  <!-- Main Content -->
  <div class="container py-5">
    <div class="row justify-content-center">
      <div class="col-lg-8">
        
        <!-- URL Checker Form -->
        <div class="card mb-4">
          <h3 class="text-center mb-4">🔎 URL Scanner</h3>
          <form action="check-url.php" method="POST">
            <div class="mb-3">
              <label for="url" class="form-label">Website URL</label>
              <input type="url" class="form-control" id="url" name="url" value="<?php echo htmlspecialchars($url); ?>" placeholder="https://example.com" required />
            </div>
            <button type="submit" class="btn btn-primary w-100">Scan URL</button>
          </form>
        </div>
        
        <?php if ($error): ?>
          <!-- Error Message -->
          <div class="card error-card">
            <h4 class="text-danger mb-3">Error</h4>
            <p><?php echo $error; ?></p>
          </div>
        <?php endif; ?>

        <?php if ($scanResult): ?>
          <!-- Scan Results -->
          <div class="card result-card">
            <h4 class="mb-4">Scan Results for: <span class="text-primary"><?php echo htmlspecialchars($url); ?></span></h4>
            
            <?php if (isset($scanResult->error)): ?>
              <div class="alert alert-danger">
                <strong>Error:</strong> <?php echo $scanResult->error; ?>
              </div>
            <?php else: ?>
              
              <!-- Summary -->
              <div class="stats-box">
                <h5 class="mb-3">Summary</h5>
                <div class="row">
                  <div class="col-md-6">
                    <p><strong>Scan Date:</strong> <?php echo isset($scanResult->scan_date) ? $scanResult->scan_date : 'N/A'; ?></p>
                    <p><strong>Scan ID:</strong> <?php echo isset($scanResult->scan_id) ? $scanResult->scan_id : 'N/A'; ?></p>
                  </div>
                  <div class="col-md-6">
                    <?php 
                      $safeEngines = 0;
                      $maliciousEngines = 0;
                      $totalEngines = 0;
                      
                      if (isset($scanResult->positives) && isset($scanResult->total)) {
                        $maliciousEngines = $scanResult->positives;
                        $totalEngines = $scanResult->total;
                        $safeEngines = $totalEngines - $maliciousEngines;
                      }
                      
                      // Determine status class based on detection ratio
                      $statusClass = "detection-safe";
                      if ($maliciousEngines > 0) {
                        $statusClass = $maliciousEngines > 3 ? "detection-danger" : "detection-warning";
                      }
                    ?>
                    <h5 class="<?php echo $statusClass; ?>">
                      <?php 
                        if ($maliciousEngines === 0) {
                          echo "✅ Safe";
                        } elseif ($maliciousEngines <= 3) {
                          echo "⚠️ Potentially Suspicious";
                        } else {
                          echo "❌ Potentially Malicious";
                        }
                      ?>
                    </h5>
                    <p><strong><?php echo $maliciousEngines; ?></strong> out of <strong><?php echo $totalEngines; ?></strong> security vendors flagged this URL</p>
                  </div>
                </div>
              </div>
              
              <!-- Detailed Results -->
              <?php if (isset($scanResult->scans) && is_object($scanResult->scans)): ?>
                <h5 class="mb-3 mt-4">Detailed Detection Results</h5>
                <div class="table-responsive">
                  <table class="table table-striped table-hover">
                    <thead>
                      <tr>
                        <th>Security Vendor</th>
                        <th>Result</th>
                      </tr>
                    </thead>
                    <tbody>
                      <?php foreach ($scanResult->scans as $vendor => $result): ?>
                        <tr>
                          <td><?php echo htmlspecialchars($vendor); ?></td>
                          <td>
                            <?php if ($result->detected): ?>
                              <span class="text-danger">❌ <?php echo htmlspecialchars($result->result); ?></span>
                            <?php else: ?>
                              <span class="text-success">✅ Clean</span>
                            <?php endif; ?>
                          </td>
                        </tr>
                      <?php endforeach; ?>
                    </tbody>
                  </table>
                </div>
              <?php endif; ?>
              
              <!-- Additional Info -->
              <?php if (isset($scanResult->permalink)): ?>
                <div class="mt-4">
                  <p><strong>For more details:</strong> <a href="<?php echo $scanResult->permalink; ?>" target="_blank">View full report on VirusTotal</a></p>
                </div>
              <?php endif; ?>
              
            <?php endif; ?>
          </div>
        <?php endif; ?>
        
        <div class="text-center mt-4">
          <a href="dashboard.html" class="btn btn-outline-secondary">Back to Dashboard</a>
        </div>
        
      </div>
    </div>
  </div>

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>

<?php
ob_end_flush();
?>
