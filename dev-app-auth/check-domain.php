<?php

session_start();
require_once('session_check.inc');
require_once('rabbitMQLib.inc');

$domain = "";
$scanResult = null;
$error = "";

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['domain'])) {
    $domain = filter_var($_POST['domain'], FILTER_SANITIZE_STRING);
    
    // Basic domain validation
    if (filter_var($domain, FILTER_VALIDATE_DOMAIN) || filter_var("http://" . $domain, FILTER_VALIDATE_URL)) {
        
        $client = new rabbitMQClient("apiRabbitMQ.ini", "apiRequest");
        
        // Get user info from session
        $user = getSessionUser();
        $userId = $user ? $user['user_id'] : null;
        
        // Create request message
        $request = array();
        $request['type'] = "domain_scan";
        $request['domain'] = $domain;
        if ($userId) {
            $request['user_id'] = $userId; // Include user ID for database saving
        }
        
        // Send request to API server
        $response = $client->send_request($request);
        
        if ($response) {
            $scanResult = $response;
        } else {
            $error = "Failed to get response from API server";
        }
    } else {
        $error = "Please enter a valid domain name";
    }
}

?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Domain Scan Results | Tech Titans</title>
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

    .badge-safe {
      background-color: #198754;
    }

    .badge-warning {
      background-color: #ffc107;
      color: #000;
    }

    .badge-danger {
      background-color: #dc3545;
    }

    .reputation-score {
      font-size: 1.5rem;
      font-weight: bold;
    }

    .reputation-positive {
      color: #198754;
    }

    .reputation-negative {
      color: #dc3545;
    }

    .reputation-neutral {
      color: #6c757d;
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
          <li class="nav-item"><a class="nav-link" href="view_scan_history.php">Scan History</a></li>
          <li class="nav-item"><a class="nav-link text-danger" href="logout.php">Logout</a></li>
        </ul>
      </div>
    </div>
  </nav>

  <!-- Main Content -->
  <div class="container py-5">
    <div class="row justify-content-center">
      <div class="col-lg-8">
        
        <!-- Domain Checker Form -->
        <div class="card mb-4">
          <h3 class="text-center mb-4">🌐 Domain Scanner</h3>
          <form action="check-domain.php" method="POST">
            <div class="mb-3">
              <label for="domain" class="form-label">Domain Name</label>
              <input type="text" class="form-control" id="domain" name="domain" value="<?php echo htmlspecialchars($domain); ?>" placeholder="example.com" required />
              <small class="form-text text-muted">Enter domain without http:// or https://</small>
            </div>
            <button type="submit" class="btn btn-primary w-100">Scan Domain</button>
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
            <h4 class="mb-4">Domain Analysis for: <span class="text-primary"><?php echo htmlspecialchars($domain); ?></span></h4>
            
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
                    <?php if (isset($scanResult->data->attributes->last_analysis_date)): ?>
                      <p><strong>Last Analysis:</strong> <?php echo date('Y-m-d H:i:s', $scanResult->data->attributes->last_analysis_date); ?></p>
                    <?php endif; ?>
                    
                    <?php if (isset($scanResult->data->attributes->creation_date)): ?>
                      <p><strong>Domain Created:</strong> <?php echo date('Y-m-d', $scanResult->data->attributes->creation_date); ?></p>
                    <?php endif; ?>
                    
                    <?php if (isset($scanResult->data->attributes->registrar)): ?>
                      <p><strong>Registrar:</strong> <?php echo htmlspecialchars($scanResult->data->attributes->registrar); ?></p>
                    <?php endif; ?>
                  </div>
                  <div class="col-md-6">
                    <?php 
                      $harmlessCount = 0;
                      $maliciousCount = 0;
                      $suspiciousCount = 0;
                      $undetectedCount = 0;
                      $totalCount = 0;
                      $reputation = 0;
                      
                      if (isset($scanResult->data->attributes->last_analysis_stats)) {
                        $stats = $scanResult->data->attributes->last_analysis_stats;
                        $harmlessCount = $stats->harmless ?? 0;
                        $maliciousCount = $stats->malicious ?? 0;
                        $suspiciousCount = $stats->suspicious ?? 0;
                        $undetectedCount = $stats->undetected ?? 0;
                        $totalCount = $harmlessCount + $maliciousCount + $suspiciousCount + $undetectedCount;
                      }
                      
                      if (isset($scanResult->data->attributes->reputation)) {
                        $reputation = $scanResult->data->attributes->reputation;
                      }
                      
                      // Determine status class based on detection ratio
                      $statusClass = "detection-safe";
                      $statusBadge = "badge-safe";
                      $statusText = "✅ Clean";
                      
                      if ($maliciousCount > 0 || $suspiciousCount > 0) {
                        if ($maliciousCount > 0) {
                          $statusClass = "detection-danger";
                          $statusBadge = "badge-danger";
                          $statusText = "❌ Malicious";
                        } else {
                          $statusClass = "detection-warning";
                          $statusBadge = "badge-warning";
                          $statusText = "⚠️ Suspicious";
                        }
                      }
                      
                      // Reputation class
                      $reputationClass = "reputation-neutral";
                      if ($reputation > 0) {
                        $reputationClass = "reputation-positive";
                      } elseif ($reputation < 0) {
                        $reputationClass = "reputation-negative";
                      }
                    ?>
                    <h5>
                      <span class="badge <?php echo $statusBadge; ?>"><?php echo $statusText; ?></span>
                    </h5>
                    
                    <?php if ($totalCount > 0): ?>
                      <p><strong>Malicious:</strong> <?php echo $maliciousCount; ?> / <?php echo $totalCount; ?> engines</p>
                      <p><strong>Suspicious:</strong> <?php echo $suspiciousCount; ?> / <?php echo $totalCount; ?> engines</p>
                    <?php endif; ?>
                    
                    <p><strong>Reputation Score:</strong> 
                      <span class="reputation-score <?php echo $reputationClass; ?>"><?php echo $reputation; ?></span>
                    </p>
                  </div>
                </div>
              </div>
              
              <!-- Engine Results Summary -->
              <?php if ($totalCount > 0): ?>
                <div class="stats-box">
                  <h5 class="mb-3">Detection Statistics</h5>
                  <div class="row text-center">
                    <div class="col-3">
                      <div class="text-success">
                        <h6><?php echo $harmlessCount; ?></h6>
                        <small>Harmless</small>
                      </div>
                    </div>
                    <div class="col-3">
                      <div class="text-danger">
                        <h6><?php echo $maliciousCount; ?></h6>
                        <small>Malicious</small>
                      </div>
                    </div>
                    <div class="col-3">
                      <div class="text-warning">
                        <h6><?php echo $suspiciousCount; ?></h6>
                        <small>Suspicious</small>
                      </div>
                    </div>
                    <div class="col-3">
                      <div class="text-muted">
                        <h6><?php echo $undetectedCount; ?></h6>
                        <small>Undetected</small>
                      </div>
                    </div>
                  </div>
                </div>
              <?php endif; ?>
              
              <!-- Detailed Engine Results -->
              <?php if (isset($scanResult->data->attributes->last_analysis_results)): ?>
                <h5 class="mb-3 mt-4">Detailed Engine Results</h5>
                <div class="table-responsive">
                  <table class="table table-striped table-hover">
                    <thead>
                      <tr>
                        <th>Security Engine</th>
                        <th>Category</th>
                        <th>Result</th>
                      </tr>
                    </thead>
                    <tbody>
                      <?php foreach ($scanResult->data->attributes->last_analysis_results as $engine => $result): ?>
                        <tr>
                          <td><?php echo htmlspecialchars($engine); ?></td>
                          <td>
                            <?php 
                              $categoryClass = "badge bg-secondary";
                              $categoryText = $result->category ?? 'unknown';
                              
                              switch($categoryText) {
                                case 'harmless':
                                  $categoryClass = "badge bg-success";
                                  break;
                                case 'malicious':
                                  $categoryClass = "badge bg-danger";
                                  break;
                                case 'suspicious':
                                  $categoryClass = "badge bg-warning text-dark";
                                  break;
                                case 'undetected':
                                  $categoryClass = "badge bg-secondary";
                                  break;
                              }
                            ?>
                            <span class="<?php echo $categoryClass; ?>"><?php echo ucfirst($categoryText); ?></span>
                          </td>
                          <td>
                            <?php if (isset($result->result) && $result->result): ?>
                              <span class="text-danger"><?php echo htmlspecialchars($result->result); ?></span>
                            <?php else: ?>
                              <span class="text-muted">Clean</span>
                            <?php endif; ?>
                          </td>
                        </tr>
                      <?php endforeach; ?>
                    </tbody>
                  </table>
                </div>
              <?php endif; ?>
              
              <!-- Additional Domain Info -->
              <?php if (isset($scanResult->data->attributes)): ?>
                <div class="mt-4">
                  <h5 class="mb-3">Additional Information</h5>
                  <div class="row">
                    <?php if (isset($scanResult->data->attributes->categories)): ?>
                      <div class="col-md-6">
                        <p><strong>Categories:</strong></p>
                        <?php foreach ($scanResult->data->attributes->categories as $vendor => $category): ?>
                          <span class="badge bg-info me-1"><?php echo htmlspecialchars($category); ?></span>
                        <?php endforeach; ?>
                      </div>
                    <?php endif; ?>
                    
                    <?php if (isset($scanResult->data->links->self)): ?>
                      <div class="col-md-6">
                        <p><strong>VirusTotal Report:</strong> 
                          <a href="<?php echo $scanResult->data->links->self; ?>" target="_blank" class="btn btn-outline-primary btn-sm">View Full Report</a>
                        </p>
                      </div>
                    <?php endif; ?>
                  </div>
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
