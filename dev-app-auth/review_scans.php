<?php

require_once('db.php');
$conn = getDBConnection();

// Get filter & pagination
$limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 10;
$filter_status = $_GET['status'] ?? '';
$filter_type = $_GET['scan_type'] ?? '';

$query = "SELECT * FROM Scans WHERE 1=1";
$params = [];

if (!empty($filter_status)) {
    $query .= " AND status = ?";
    $params[] = $filter_status;
}
if (!empty($filter_type)) {
    $query .= " AND scan_type = ?";
    $params[] = $filter_type;
}

$query .= " ORDER BY timestamp DESC LIMIT ?";
$params[] = $limit;

$stmt = $conn->prepare($query);
$types = str_repeat("s", count($params) - 1) . "i"; // s for strings, i for limit
$stmt->bind_param($types, ...$params);
$stmt->execute();
$result = $stmt->get_result();
?>

<!DOCTYPE html>
<html>
<head>
    <title>Review Submitted Scans</title>
    <style>
        table { border-collapse: collapse; width: 100%; }
        th, td { padding: 8px; border: 1px solid #ccc; text-align: left; }
    </style>
</head>
<body>
    <h2>Review Submitted Scans</h2>

    <form method="GET">
        <label>Status:</label>
        <select name="status">
            <option value="">All</option>
            <option value="flagged" <?= $filter_status == 'flagged' ? 'selected' : '' ?>>Flagged</option>
            <option value="approved" <?= $filter_status == 'approved' ? 'selected' : '' ?>>Approved</option>
            <option value="archived" <?= $filter_status == 'archived' ? 'selected' : '' ?>>Archived</option>
            <option value="pending" <?= $filter_status == 'pending' ? 'selected' : '' ?>>Pending</option>
        </select>

        <label>Scan Type:</label>
        <select name="scan_type">
            <option value="">All</option>
            <option value="url" <?= $filter_type == 'url' ? 'selected' : '' ?>>URL</option>
            <option value="ip" <?= $filter_type == 'ip' ? 'selected' : '' ?>>IP</option>
            <option value="domain" <?= $filter_type == 'domain' ? 'selected' : '' ?>>Domain</option>
        </select>

        <label>Show:</label>
        <select name="limit">
            <?php foreach ([10, 25, 50, 100] as $l): ?>
                <option value="<?= $l ?>" <?= $limit == $l ? 'selected' : '' ?>><?= $l ?></option>
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
            <?php while ($row = $result->fetch_assoc()): ?>
                <tr>
                    <td><?= $row['id'] ?></td>
                    <td><?= htmlspecialchars($row['submitted_by']) ?></td>
                    <td><?= $row['scan_type'] ?></td>
                    <td><?= htmlspecialchars($row['scan_value']) ?></td>
                    <td><?= $row['status'] ?></td>
                    <td><?= $row['safety'] ?></td>
                    <td><?= $row['risk_score'] ?></td>
                    <td><?= htmlspecialchars($row['antivirus_results']) ?></td>
                    <td><?= htmlspecialchars($row['notes']) ?></td>
                    <td><?= $row['timestamp'] ?></td>
                    <td>
                        <form action="update_scan.php" method="POST" style="display:inline;">
                            <input type="hidden" name="scan_id" value="<?= $row['id'] ?>">
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
            <?php endwhile; ?>
        </tbody>
    </table>

</body>
</html>

<?php
$stmt->close();
$conn->close();
?>
