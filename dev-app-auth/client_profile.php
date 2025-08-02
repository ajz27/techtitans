<?php
session_start();
ini_set('display_errors', 1);
error_reporting(E_ALL);

require_once('path.inc');
require_once('get_host_info.inc');
require_once('rabbitMQLib.inc');

$username = $_SESSION['username'] ?? null;
if (!$username) {
    die("Please login to access your profile.");
}

$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 10;
$offset = ($page - 1) * $limit;

// Prepare request for profile + scans
$request = [
    'type' => 'get_user_profile',
    'username' => $username,
    'limit' => $limit,
    'offset' => $offset
];

try {
    $client = new rabbitMQClient("testRabbitMQ.ini", "testServer");
    $response = $client->send_request($request);

    $user = $response['user'] ?? [];
    $scans = $response['scans'] ?? [];
    $weeklyCount = $response['weekly_count'] ?? 0;
    $total_records = $response['total'] ?? 0;
    $max_pages = min(10, ceil($total_records / $limit));

} catch (Exception $e) {
    error_log("Profile error: " . $e->getMessage());
    $user = [];
    $scans = [];
    $weeklyCount = 0;
    $max_pages = 1;
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>My Profile</title>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet" />
</head>
<body class="bg-light">
<div class="container my-5">
    <h2 class="mb-4">Client Profile</h2>

    <!-- Profile Section -->
    <div class="card mb-5">
        <div class="card-header bg-primary text-white">Profile Info</div>
        <div class="card-body">
            <form method="POST" action="update_profile.php">
                <div class="row mb-3">
                    <div class="col-md-6">
                        <label>Name</label>
                        <input type="text" name="name" class="form-control" value="<?= htmlspecialchars($user['name'] ?? '') ?>">
                    </div>
                    <div class="col-md-6">
                        <label>Email</label>
                        <input type="email" name="email" class="form-control" value="<?= htmlspecialchars($user['email'] ?? '') ?>">
                    </div>
                </div>
                <div class="mb-3">
                    <label>Bio</label>
                    <textarea name="bio" class="form-control" rows="2"><?= htmlspecialchars($user['bio'] ?? '') ?></textarea>
                </div>
                <div class="mb-3">
                    <label>Password (leave blank to keep unchanged)</label>
                    <input type="password" name="password" class="form-control">
                </div>
                <p><strong>Role:</strong> <?= htmlspecialchars($user['role'] ?? 'Client') ?></p>
                <button type="submit" class="btn btn-success">Update Profile</button>
            </form>
        </div>
    </div>

    <!-- Scan Summary -->
    <div class="alert alert-info mb-4">
        🔍 <strong><?= $weeklyCount ?></strong> scans submitted this week.
    </div>

    <!-- Scan History -->
    <div class="card">
        <div class="card-header bg-secondary text-white">Scan History</div>
        <div class="card-body">
            <form class="row mb-3" method="GET">
                <input type="hidden" name="page" value="1" />
                <div class="col-md-2">
                    <label>Records per page</label>
                    <select class="form-select" name="limit" onchange="this.form.submit()">
                        <?php foreach ([10, 25, 50, 100] as $l): ?>
                            <option value="<?= $l ?>" <?= $limit === $l ? 'selected' : '' ?>><?= $l ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </form>

            <div class="table-responsive">
                <table class="table table-bordered table-hover bg-white">
                    <thead class="table-dark">
                        <tr>
                            <th>Type</th>
                            <th>Value</th>
                            <th>Status</th>
                            <th>Safety</th>
                            <th>Risk Score</th>
                            <th>Timestamp</th>
                            <th>View</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($scans)): ?>
                            <?php foreach ($scans as $scan): ?>
                                <tr>
                                    <td><?= htmlspecialchars($scan['scan_type']) ?></td>
                                    <td><?= htmlspecialchars($scan['scan_value']) ?></td>
                                    <td>
                                        <?php
                                            $status = htmlspecialchars($scan['status']);
                                            $badge = match($status) {
                                                'pending' => 'warning',
                                                'malicious' => 'danger',
                                                'safe' => 'success',
                                                default => 'secondary'
                                            };
                                            echo "<span class='badge bg-$badge'>" . ucfirst($status) . "</span>";
                                        ?>
                                    </td>
                                    <td><?= htmlspecialchars($scan['safety']) ?></td>
                                    <td><?= htmlspecialchars($scan['risk_score']) ?></td>
                                    <td><?= htmlspecialchars($scan['timestamp']) ?></td>
                                    <td>
                                        <a href="scan_details.php?id=<?= urlencode($scan['id']) ?>" class="btn btn-sm btn-outline-primary">View</a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr><td colspan="7" class="text-center text-muted">No scans found.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <!-- Pagination -->
            <nav>
                <ul class="pagination justify-content-center">
                    <?php for ($i = 1; $i <= $max_pages; $i++): ?>
                        <li class="page-item <?= $i === $page ? 'active' : '' ?>">
                            <a class="page-link" href="?<?= http_build_query(array_merge($_GET, ['page' => $i])) ?>">
                                <?= $i ?>
                            </a>
                        </li>
                    <?php endfor; ?>
                </ul>
            </nav>
        </div>
    </div>
</div>
</body>
</html>
