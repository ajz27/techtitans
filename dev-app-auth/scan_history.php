<?php
// If you're getting scan history from the database:
session_start();
$scanHistory = [];

// Simulated example (replace with your DB query)
if (isset($_SESSION['user_id'])) {
    $scanHistory = [
        ["url" => "http://example.com", "result" => "Safe", "scanned_at" => "2025-07-01 12:00"],
        ["url" => "http://badwebsite.com", "result" => "Malicious", "scanned_at" => "2025-07-03 09:30"],
    ];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Scan History Example</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <!-- Bootstrap CDN -->
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">

<div class="container py-5">
  <h2 class="mb-4">Dashboard</h2>

  <!-- Scan History Toggle -->
  <div class="form-check form-switch mb-3">
    <input class="form-check-input" type="checkbox" id="scanHistoryCheckbox">
    <label class="form-check-label" for="scanHistoryCheckbox">Scan History</label>
  </div>

  <!-- Scan History Section (Initially Hidden) -->
  <div id="scanHistorySection" class="card shadow p-3" style="display: none;">
    <h5 class="card-title mb-3">Your Scan History</h5>
    <?php if (!empty($scanHistory)): ?>
      <table class="table table-striped">
        <thead>
          <tr>
            <th>URL</th>
            <th>Result</th>
            <th>Scanned At</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($scanHistory as $scan): ?>
            <tr>
              <td><?= htmlspecialchars($scan['url']) ?></td>
              <td><?= htmlspecialchars($scan['result']) ?></td>
              <td><?= htmlspecialchars($scan['scanned_at']) ?></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    <?php else: ?>
      <p class="text-muted">No scan history available.</p>
    <?php endif; ?>
  </div>
</div>

<!-- JavaScript to toggle visibility -->
<script>
  document.getElementById("scanHistoryCheckbox").addEventListener("change", function () {
    const section = document.getElementById("scanHistorySection");
    section.style.display = this.checked ? "block" : "none";
  });
</script>

</body>
</html>
