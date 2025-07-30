<?php
// Show errors for debugging
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();
require_once('path.inc');
require_once('get_host_info.inc');
require_once('rabbitMQLib.inc');

$username = $_SESSION['username'] ?? null;
if (!$username) {
    die("Unauthorized access. Please log in.");
}

// Pagination controls
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 10;
$offset = ($page - 1) * $limit;

// Filters
$filter_status = $_GET['status'] ?? '';
$filter_type = $_GET['scan_type'] ?? '';

// Prepare RabbitMQ request
$request = [
    'type' => 'get_user_scans',
    'username' => $username,
    'limit' => $limit,
    'offset' => $offset,
    'status' => $filter_status,
    'scan_type' => $filter_type
];

try {
    $client = new rabbitMQClient("testRabbitMQ.ini", "testServer");
    $response = $client->send_request($request);
    $scans = $response['data'] ?? [];
    $total_records = $response['total'] ?? 0;
    $max_pages = min(10, ceil($total_records / $limit));
} catch (Exception $e) {
    error_log("RabbitMQ Error: " . $e->getMessage());
    $scans = [];
    $max_pages = 1;
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Scan History</title>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet" />
</head>
<body class="bg-light">
    <div class="container my-5">
        <h2 class="mb-4">My Scan History</h2>

        <form class="row g-3 mb-4" method="GET">
            <input type="hidden" name="page" value="1" />
            <div class="col-md-3">
                <label for="status" class="form-label">Status</label>
                <select class="form-select" name="status" id="status">
                    <option value="">All</option>
                    <option value="approved" <?= $filter_status === 'approved' ? 'selected' : '' ?>>Approved</option>
                    <option value="flagged" <?= $filter_status === 'flagged' ? 'selected' : '' ?>>Flagged</option>
                    <option value="archived" <?= $filter_status === 'archived' ? 'selected' : '' ?>>Archived</option>
                    <option value="pending" <?= $filter_status === 'pending' ? 'selected' : '' ?>>Pending</option>
                </select>
            </div>

            <div class="col-md-3">
                <label for="scan_type" class="form-label">Scan Type</label>
                <select class="form-select" name="scan_type" id="scan_type">
                    <option value="">All</option>
                    <option value="url" <?= $filter_type === 'url' ? 'selected' : '' ?>>URL</option>
                    <option value="ip" <?= $filter_type === 'ip' ? 'selected' : '' ?>>IP</option>
                    <option value="domain" <?= $filter_type === 'domain' ? 'selected' : '' ?>>Domain</option>
                </select>
            </div>

            <div class="col-md-2">
                <label for="limit" class="form-label">Show</label>
                <select class="form-select" name="limit" id="limit">
                    <?php foreach ([10, 25, 50, 100] as $l): ?>
                        <option value="<?= $l ?>" <?= $limit === $l ? 'selected' : '' ?>><?= $l ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="col-md-2 d-flex align-items-end">
                <button type="submit" class="btn btn-primary w-100">Apply</button>
            </div>
        </form>

        <div class="table-responsive">
            <table class="table table-bordered table-hover bg-white">
                <thead class="table-dark">
                    <tr>
                        <th>ID</th>
                        <th>Type</th>
                        <th>Value</th>
                        <th>Status</th>
                        <th>Safety</th>
                        <th>Risk Score</th>
                        <th>Timestamp</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!empty($scans)): ?>
                        <?php foreach ($scans as $scan): ?>
                            <tr>
                                <td><?= htmlspecialchars($scan['id']) ?></td>
                                <td><?= htmlspecialchars($scan['scan_type']) ?></td>
                                <td><?= htmlspecialchars($scan['scan_value']) ?></td>
                                <td><?= htmlspecialchars($scan['status']) ?></td>
                                <td><?= htmlspecialchars($scan['safety']) ?></td>
                                <td><?= htmlspecialchars($scan['risk_score']) ?></td>
                                <td><?= htmlspecialchars($scan['timestamp']) ?></td>
                                <td>
                                    <a href="scan_details.php?id=<?= urlencode($scan['id']) ?>" class="btn btn-sm btn-outline-primary">View</a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="8" class="text-center text-muted">No scans found.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <!-- Pagination Controls -->
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
</body>
</html>
