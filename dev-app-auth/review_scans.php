<?php
// Show all errors for debugging
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();
require_once('path.inc');
require_once('get_host_info.inc');
require_once('rabbitMQLib.inc');

// Extract filters and limits from URL params
$limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 10;
$filter_status = $_GET['status'] ?? '';
$filter_type = $_GET['scan_type'] ?? '';

// Prepare RabbitMQ request
$request = [
    'type' => 'get_scans',
    'limit' => $limit,
    'status' => $filter_status,
    'scan_type' => $filter_type
];

try {
    $client = new rabbitMQClient("testRabbitMQ.ini", "testServer");
    $response = $client->send_request($request);

    // Safely decode and extract data
    $scans = [];

    if (isset($response['success']) && $response['success'] === true && isset($response['data']) && is_array($response['data'])) {
        $scans = $response['data'];
    } else {
        $error_message = isset($response['message']) ? $response['message'] : 'Unexpected response from server.';
        error_log("Scan fetch error: $error_message");
    }

} catch (Exception $e) {
    error_log("RabbitMQ error: " . $e->getMessage());
    $scans = [];
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Review Submitted Scans</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; }
        table { border-collapse: collapse; width: 100%; margin-top: 20px; }
        th, td { padding: 8px; border: 1px solid #ccc; text-align: left; }
        form { margin-bottom: 20px; }
    </style>
</head>
<body>
    <h2>Review Submitted Scans</h2>

    <form method="GET">
        <label>Status:</label>
        <select name="status">
            <option value="">All</option>
            <option value="flagged" <?= $filter_status === 'flagged' ? 'selected' : '' ?>>Flagged</option>
            <option value="approved" <?= $filter_status === 'approved' ? 'selected' : '' ?>>Approved</option>
            <option value="archived" <?= $filter_status === 'archived' ? 'selected' : '' ?>>Archived</option>
            <option value="pending" <?= $filter_status === 'pending' ? 'selected' : '' ?>>Pending</option>
        </select>

        <label>Scan Type:</label>
        <select name="scan_type">
            <option value="">All</option>
            <option value="url" <?= $filter_type === 'url' ? 'selected' : '' ?>>URL</option>
            <option value="ip" <?= $filter_type === 'ip' ? 'selected' : '' ?>>IP</option>
            <option value="domain" <?= $filter_type === 'domain' ? 'selected' : '' ?>>Domain</option>
        </select>

        <label>Show:</label>
        <select name="limit">
            <?php foreach ([10, 25, 50, 100] as $l): ?>
                <option value="<?= $l ?>" <?= $limit === $l ? 'selected' : '' ?>><?= $l ?></option>
            <?php endforeach; ?>
        </select>

        <button type="submit">Apply</button>
    </form>

    <table>
        <thead>
            <tr>
                <th>ID</th>
                <th>Submitted By</th>
                <th>Type</th>
                <th>Value</th>
                <th>Status</th>
                <th>Safety</th>
                <th>Risk Score</th>
                <th>Antivirus</th>
                <th>Notes</th>
                <th>Timestamp</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php if (!empty($scans)): ?>
                <?php foreach ($scans as $row): ?>
                    <tr>
                        <td><?= htmlspecialchars($row['id']) ?></td>
                        <td><?= htmlspecialchars($row['submitted_by']) ?></td>
                        <td><?= htmlspecialchars($row['scan_type']) ?></td>
                        <td><?= htmlspecialchars($row['scan_value']) ?></td>
                        <td><?= htmlspecialchars($row['status']) ?></td>
                        <td><?= htmlspecialchars($row['safety']) ?></td>
                        <td><?= htmlspecialchars($row['risk_score']) ?></td>
                        <td><?= htmlspecialchars($row['antivirus_results']) ?></td>
                        <td><?= htmlspecialchars($row['notes']) ?></td>
                        <td><?= htmlspecialchars($row['timestamp']) ?></td>
                        <td>
                            <form action="update_scan.php" method="POST" style="display:inline;">
                                <input type="hidden" name="scan_id" value="<?= htmlspecialchars($row['id']) ?>">
                                <select name="status">
                                    <option value="approved">Approve</option>
                                    <option value="flagged">Flag</option>
                                    <option value="archived">Archive</option>
                                </select>
                                <input type="text" name="notes" placeholder="Add note">
                                <button type="submit">Update</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php else: ?>
                <tr><td colspan="11">No scans found or an error occurred.</td></tr>
            <?php endif; ?>
        </tbody>
    </table>
</body>
</html>
