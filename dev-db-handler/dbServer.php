<?php
require_once('path.inc');
require_once('get_host_info.inc');
require_once('rabbitMQLib.inc');

// database credentials, specific to adriel right now but change accordingly
$DB_HOST = 'localhost';
$DB_USER = 'root';
$DB_PASS = 'test';
$DB_NAME = 'userDatabase';

function getDBConnection()
{
    global $DB_HOST, $DB_USER, $DB_PASS, $DB_NAME;
    $conn = new mysqli($DB_HOST, $DB_USER, $DB_PASS, $DB_NAME);
    if ($conn->connect_error) {
        error_log("database connection failed: " . $conn->connect_error);
        return false;
    }
    return $conn;
}

function register($username, $email, $password)
{
    echo "attempting to register user: $username with email: $email\n";

    $conn = getDBConnection();
    if (!$conn) {
        echo "database connection failed\n";
        return array("success" => false, "message" => "database connection failed");
    }

    // check if email already exists
    $stmt = $conn->prepare("SELECT id FROM Users WHERE email = ?");
    if (!$stmt) {
        echo "prepare statement failed\n";
        $conn->close();
        return array("success" => false, "message" => "database query error");
    }

    $stmt->bind_param("s", $email);
    $stmt->execute();
    $stmt->store_result();

    if ($stmt->num_rows > 0) {
        echo "email already exists: $email\n";
        $stmt->close();
        $conn->close();
        return array("success" => false, "message" => "email already exists");
    }
    $stmt->close();

    // check if username already exists
    $stmt = $conn->prepare("SELECT id FROM Users WHERE username = ?");
    if (!$stmt) {
        echo "prepare statement failed\n";
        $conn->close();
        return array("success" => false, "message" => "database query error");
    }

    $stmt->bind_param("s", $username);
    $stmt->execute();
    $stmt->store_result();

    if ($stmt->num_rows > 0) {
        echo "username already exists: $username\n";
        $stmt->close();
        $conn->close();
        return array("success" => false, "message" => "username already exists");
    }
    $stmt->close();

    // hash the password
    $hashedPassword = password_hash($password, PASSWORD_BCRYPT);
    echo "password hashed successfully\n";

    // insert new user
    $stmt = $conn->prepare("INSERT INTO Users (username, email, password) VALUES (?, ?, ?)");
    if (!$stmt) {
        echo "insert prepare failed\n";
        $conn->close();
        return array("success" => false, "message" => "database prepare error");
    }

    $stmt->bind_param("sss", $username, $email, $hashedPassword);

    if ($stmt->execute()) {
        $userId = $stmt->insert_id;
        echo "user registered successfully with id: $userId\n";
        $stmt->close();
        $conn->close();

        return array(
            "success" => true,
            "message" => "user registered successfully",
            "user_id" => $userId,
            "username" => $username,
            "email" => $email
        );
    } else {
        $error = $stmt->error;
        echo "insert failed: $error\n";
        $stmt->close();
        $conn->close();
        return array("success" => false, "message" => "registration failed: " . $error);
    }
}

function login($username, $password)
{
    $conn = getDBConnection();
    if (!$conn) {
        return array("success" => false, "message" => "database connection failed");
    }

    $stmt = $conn->prepare("SELECT id, username, email, password FROM Users WHERE username = ? OR email = ?");
    if (!$stmt) {
        $conn->close();
        return array("success" => false, "message" => "database query error");
    }

    $stmt->bind_param("ss", $username, $username);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 1) {
        $row = $result->fetch_assoc();

        if (password_verify($password, $row['password'])) {
            $stmt->close();
            $conn->close();
            return array(
                "success" => true,
                "message" => "login successful",
                "user_id" => $row['id'],
                "username" => $row['username'],
                "email" => $row['email']
            );
        }
    }

    $stmt->close();
    $conn->close();
    return array("success" => false, "message" => "invalid credentials");
}

function saveUrlScan($userId, $scanData) {
    $conn = getDBConnection();
    
    try {
        $scanResult = $scanData['scan_result'];
        
        $scannedUrl = $scanData['scanned_url'];
        $scanId = $scanResult['scan_id'];
        $permalink = $scanResult['permalink'];
        $scanTimestamp = $scanData['scan_timestamp'];
        $scanDate = $scanResult['scan_date'];
        $totalEngines = $scanResult['total'];
        $positiveDetections = $scanResult['positives'];
        $responseCode = $scanResult['response_code'];
        $verboseMsg = $scanResult['verbose_msg'];
        
        $stmt = $conn->prepare("
            INSERT INTO url_scans (
                user_id, scanned_url, scan_id, permalink, scan_timestamp, 
                scan_date, total_engines, positive_detections, response_code, verbose_msg
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        
        $stmt->bind_param(
            "isssssiiis",
            $userId, $scannedUrl, $scanId, $permalink, $scanTimestamp,
            $scanDate, $totalEngines, $positiveDetections, $responseCode, $verboseMsg
        );
        
        $stmt->execute();
        $insertedId = $conn->insert_id;
        $stmt->close();
        $conn->close();
        
        return $insertedId;
        
    } catch (Exception $e) {
        $conn->close();
        throw new Exception("Failed to save URL scan: " . $e->getMessage());
    }
}

function saveDomainScan($userId, $scanData) {
    $conn = getDBConnection();
    
    if (!$conn) {
        throw new Exception("Database connection failed");
    }
    
    try {
        $stmt = $conn->prepare("
            INSERT INTO domain_scans (
                user_id, scanned_domain, scan_timestamp, total_engines, positive_detections,
                harmless_count, malicious_count, suspicious_count, undetected_count,
                reputation_score, vt_permalink, scan_status, raw_response
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        
        $stmt->bind_param(
            "issiiiiiiiiss",
            $userId,
            $scanData['scanned_domain'],
            $scanData['scan_timestamp'],
            $scanData['total_engines'],
            $scanData['positive_detections'],
            $scanData['harmless_count'],
            $scanData['malicious_count'],
            $scanData['suspicious_count'],
            $scanData['undetected_count'],
            $scanData['reputation_score'],
            $scanData['vt_permalink'],
            $scanData['scan_status'],
            $scanData['raw_response']
        );
        
        $stmt->execute();
        $insertedId = $conn->insert_id;
        $stmt->close();
        $conn->close();
        
        return $insertedId;
        
    } catch (Exception $e) {
        if ($conn) {
            $conn->close();
        }
        throw new Exception("Failed to save domain scan: " . $e->getMessage());
    }
}

function getUserUrlScans($userId, $limit = 50) {
    $conn = getDBConnection();
    
    $stmt = $conn->prepare("
        SELECT * FROM url_scans 
        WHERE user_id = ? 
        ORDER BY scan_timestamp DESC 
        LIMIT ?
    ");
    $stmt->bind_param("ii", $userId, $limit);
    $stmt->execute();
    $result = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    $conn->close();
    
    return $result;
}

function getUserDomainScans($userId, $limit = 50) {
    $conn = getDBConnection();
    
    if (!$conn) {
        return array();
    }
    
    $stmt = $conn->prepare("
        SELECT * FROM domain_scans 
        WHERE user_id = ? 
        ORDER BY scan_timestamp DESC 
        LIMIT ?
    ");
    $stmt->bind_param("ii", $userId, $limit);
    $stmt->execute();
    $result = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    $conn->close();
    
    return $result;
}

function getAllUrlScans($limit = 100, $offset = 0, $reviewStatus = 'all') {
    $conn = getDBConnection();
    
    if (!$conn) {
        return false;
    }
    
    // Build the query with optional WHERE clause for review_status filtering
    $whereClause = "";
    $bindTypes = "ii";
    $bindValues = [$limit, $offset];
    
    if ($reviewStatus !== 'all') {
        $whereClause = "WHERE us.review_status = ?";
        $bindTypes = "sii";
        array_unshift($bindValues, $reviewStatus);
    }
    
    $query = "
        SELECT us.*, u.username, u.email, 
               reviewer.username as reviewer_username
        FROM url_scans us
        LEFT JOIN Users u ON us.user_id = u.id
        LEFT JOIN Users reviewer ON us.reviewed_by = reviewer.id
        {$whereClause}
        ORDER BY us.scan_timestamp DESC 
        LIMIT ? OFFSET ?
    ";
    
    $stmt = $conn->prepare($query);
    
    if (!$stmt) {
        $conn->close();
        return false;
    }
    
    $stmt->bind_param($bindTypes, ...$bindValues);
    $stmt->execute();
    $result = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    $conn->close();
    
    return $result;
}

function getAllDomainScans($limit = 100, $offset = 0) {
    $conn = getDBConnection();
    
    if (!$conn) {
        return false;
    }
    
    $stmt = $conn->prepare("
        SELECT ds.*, u.username, u.email
        FROM domain_scans ds
        LEFT JOIN Users u ON ds.user_id = u.id
        ORDER BY ds.scan_timestamp DESC 
        LIMIT ? OFFSET ?
    ");
    
    if (!$stmt) {
        $conn->close();
        return false;
    }
    
    $stmt->bind_param("ii", $limit, $offset);
    $stmt->execute();
    $result = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    $conn->close();
    
    return $result;
}

function getAllUsers() {
    $conn = getDBConnection();
    
    if (!$conn) {
        return false;
    }
    
    // Updated query to use UserRoles table since Users table doesn't have role_id
    $stmt = $conn->prepare("
        SELECT u.id, u.username, u.email, u.created, u.modified, 
               ur.role_id, r.name as role_name, r.description as role_description
        FROM Users u
        LEFT JOIN UserRoles ur ON u.id = ur.user_id AND ur.is_active = 1
        LEFT JOIN Roles r ON ur.role_id = r.id AND r.is_active = 1
        ORDER BY u.created DESC
    ");
    
    if (!$stmt) {
        $conn->close();
        return false;
    }
    
    $stmt->execute();
    $result = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    $conn->close();
    
    return $result;
}

function getUserRole($userId) {
    $conn = getDBConnection();
    
    if (!$conn) {
        return false;
    }
    
    // Query the UserRoles table since Users table doesn't have role_id column
    $stmt = $conn->prepare("
        SELECT ur.role_id, r.name as role_name 
        FROM UserRoles ur
        LEFT JOIN Roles r ON ur.role_id = r.id 
        WHERE ur.user_id = ? AND ur.is_active = 1 AND r.is_active = 1
    ");
    
    if (!$stmt) {
        $conn->close();
        return false;
    }
    
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $result = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    $conn->close();
    
    return $result;
}

function getAllRoles() {
    $conn = getDBConnection();
    
    if (!$conn) {
        return false;
    }
    
    $stmt = $conn->prepare("SELECT id, name, description FROM Roles WHERE is_active = 1 ORDER BY id");
    
    if (!$stmt) {
        $conn->close();
        return false;
    }
    
    $stmt->execute();
    $result = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    $conn->close();
    
    return $result;
}

function updateUserRole($userId, $newRoleId, $adminUserId) {
    $conn = getDBConnection();
    
    if (!$conn) {
        return array("success" => false, "message" => "database connection failed");
    }
    
    // Check if the admin user has admin privileges (role_id = 1)
    $adminCheck = $conn->prepare("
        SELECT ur.role_id 
        FROM UserRoles ur 
        WHERE ur.user_id = ? AND ur.role_id = 1 AND ur.is_active = 1
    ");
    
    if (!$adminCheck) {
        $conn->close();
        return array("success" => false, "message" => "database query error");
    }
    
    $adminCheck->bind_param("i", $adminUserId);
    $adminCheck->execute();
    $adminResult = $adminCheck->get_result();
    
    if ($adminResult->num_rows === 0) {
        $adminCheck->close();
        $conn->close();
        return array("success" => false, "message" => "insufficient privileges");
    }
    $adminCheck->close();
    
    // Check if the target user exists
    $userCheck = $conn->prepare("SELECT id FROM Users WHERE id = ?");
    if (!$userCheck) {
        $conn->close();
        return array("success" => false, "message" => "database query error");
    }
    
    $userCheck->bind_param("i", $userId);
    $userCheck->execute();
    $userResult = $userCheck->get_result();
    
    if ($userResult->num_rows === 0) {
        $userCheck->close();
        $conn->close();
        return array("success" => false, "message" => "user not found");
    }
    $userCheck->close();
    
    // Check if the role exists
    $roleCheck = $conn->prepare("SELECT id FROM Roles WHERE id = ? AND is_active = 1");
    if (!$roleCheck) {
        $conn->close();
        return array("success" => false, "message" => "database query error");
    }
    
    $roleCheck->bind_param("i", $newRoleId);
    $roleCheck->execute();
    $roleResult = $roleCheck->get_result();
    
    if ($roleResult->num_rows === 0) {
        $roleCheck->close();
        $conn->close();
        return array("success" => false, "message" => "invalid role");
    }
    $roleCheck->close();
    
    // Begin transaction
    $conn->begin_transaction();
    
    try {
        // Deactivate current role assignments for this user
        $deactivateStmt = $conn->prepare("UPDATE UserRoles SET is_active = 0 WHERE user_id = ?");
        $deactivateStmt->bind_param("i", $userId);
        $deactivateStmt->execute();
        $deactivateStmt->close();
        
        // Check if this user-role combination already exists
        $existingStmt = $conn->prepare("SELECT id FROM UserRoles WHERE user_id = ? AND role_id = ?");
        $existingStmt->bind_param("ii", $userId, $newRoleId);
        $existingStmt->execute();
        $existingResult = $existingStmt->get_result();
        
        if ($existingResult->num_rows > 0) {
            // Reactivate existing record
            $existingStmt->close();
            $reactivateStmt = $conn->prepare("UPDATE UserRoles SET is_active = 1 WHERE user_id = ? AND role_id = ?");
            $reactivateStmt->bind_param("ii", $userId, $newRoleId);
            $reactivateStmt->execute();
            $reactivateStmt->close();
        } else {
            // Create new role assignment
            $existingStmt->close();
            $insertStmt = $conn->prepare("INSERT INTO UserRoles (user_id, role_id, is_active) VALUES (?, ?, 1)");
            $insertStmt->bind_param("ii", $userId, $newRoleId);
            $insertStmt->execute();
            $insertStmt->close();
        }
        
        $conn->commit();
        return array("success" => true, "message" => "user role updated successfully");
        
    } catch (Exception $e) {
        $conn->rollback();
        $conn->close();
        return array("success" => false, "message" => "failed to update user role: " . $e->getMessage());
    }
    
    $conn->close();
}

function deleteUser($userId, $adminUserId) {
    $conn = getDBConnection();
    
    if (!$conn) {
        return array("success" => false, "message" => "database connection failed");
    }
    
    // Check if the admin user has admin privileges (role_id = 1)
    $adminCheck = $conn->prepare("
        SELECT ur.role_id 
        FROM UserRoles ur 
        WHERE ur.user_id = ? AND ur.role_id = 1 AND ur.is_active = 1
    ");
    
    if (!$adminCheck) {
        $conn->close();
        return array("success" => false, "message" => "database query error");
    }
    
    $adminCheck->bind_param("i", $adminUserId);
    $adminCheck->execute();
    $adminResult = $adminCheck->get_result();
    
    if ($adminResult->num_rows === 0) {
        $adminCheck->close();
        $conn->close();
        return array("success" => false, "message" => "insufficient privileges - admin access required");
    }
    $adminCheck->close();
    
    // Prevent admin from deleting themselves
    if ($userId === $adminUserId) {
        $conn->close();
        return array("success" => false, "message" => "cannot delete your own account");
    }
    
    // Check if the target user exists
    $userCheck = $conn->prepare("SELECT id, username, email FROM Users WHERE id = ?");
    if (!$userCheck) {
        $conn->close();
        return array("success" => false, "message" => "database query error");
    }
    
    $userCheck->bind_param("i", $userId);
    $userCheck->execute();
    $userResult = $userCheck->get_result();
    
    if ($userResult->num_rows === 0) {
        $userCheck->close();
        $conn->close();
        return array("success" => false, "message" => "user not found");
    }
    
    $userData = $userResult->fetch_assoc();
    $userCheck->close();
    
    // Begin transaction
    $conn->begin_transaction();
    
    try {
        // First, delete all UserRoles entries for this user
        $deleteRolesStmt = $conn->prepare("DELETE FROM UserRoles WHERE user_id = ?");
        if (!$deleteRolesStmt) {
            throw new Exception("Failed to prepare UserRoles deletion query");
        }
        $deleteRolesStmt->bind_param("i", $userId);
        $deleteRolesStmt->execute();
        $deleteRolesStmt->close();
        
        // Delete any URL scans for this user (if url_scans table exists)
        $deleteScansStmt = $conn->prepare("DELETE FROM url_scans WHERE user_id = ?");
        if ($deleteScansStmt) {
            $deleteScansStmt->bind_param("i", $userId);
            $deleteScansStmt->execute();
            $deleteScansStmt->close();
        }
        
        // Finally, delete the user from Users table
        $deleteUserStmt = $conn->prepare("DELETE FROM Users WHERE id = ?");
        if (!$deleteUserStmt) {
            throw new Exception("Failed to prepare Users deletion query");
        }
        $deleteUserStmt->bind_param("i", $userId);
        $deleteUserStmt->execute();
        $deleteUserStmt->close();
        
        $conn->commit();
        echo "User deleted successfully: ID={$userId}, username={$userData['username']}, email={$userData['email']}\n";
        
        return array(
            "success" => true, 
            "message" => "User '{$userData['username']}' has been successfully deleted from the system",
            "deleted_user" => array(
                "id" => $userId,
                "username" => $userData['username'],
                "email" => $userData['email']
            )
        );
        
    } catch (Exception $e) {
        $conn->rollback();
        $conn->close();
        echo "Failed to delete user: " . $e->getMessage() . "\n";
        return array("success" => false, "message" => "failed to delete user: " . $e->getMessage());
    }
    
    $conn->close();
}

function updateScanReview($scanId, $reviewStatus, $reviewNotes, $reviewerId) {
    $conn = getDBConnection();
    
    if (!$conn) {
        return array("success" => false, "message" => "database connection failed");
    }
    
    // Check if the reviewer has manager or admin privileges
    $roleCheck = $conn->prepare("
        SELECT ur.role_id 
        FROM UserRoles ur 
        WHERE ur.user_id = ? AND ur.role_id IN (1, 2) AND ur.is_active = 1
    ");
    
    if (!$roleCheck) {
        $conn->close();
        return array("success" => false, "message" => "database query error");
    }
    
    $roleCheck->bind_param("i", $reviewerId);
    $roleCheck->execute();
    $roleResult = $roleCheck->get_result();
    
    if ($roleResult->num_rows === 0) {
        $roleCheck->close();
        $conn->close();
        return array("success" => false, "message" => "insufficient privileges - admin or manager access required");
    }
    $roleCheck->close();
    
    // Validate review status
    $allowedStatuses = ['pending', 'approved', 'flagged', 'archived'];
    if (!in_array($reviewStatus, $allowedStatuses)) {
        $conn->close();
        return array("success" => false, "message" => "invalid review status");
    }
    
    // Check if scan exists
    $scanCheck = $conn->prepare("SELECT id FROM url_scans WHERE id = ?");
    if (!$scanCheck) {
        $conn->close();
        return array("success" => false, "message" => "database query error");
    }
    
    $scanCheck->bind_param("i", $scanId);
    $scanCheck->execute();
    $scanResult = $scanCheck->get_result();
    
    if ($scanResult->num_rows === 0) {
        $scanCheck->close();
        $conn->close();
        return array("success" => false, "message" => "scan not found");
    }
    $scanCheck->close();
    
    // Update the scan review fields
    $updateStmt = $conn->prepare("
        UPDATE url_scans 
        SET review_status = ?, review_notes = ?, reviewed_by = ?, reviewed_at = NOW()
        WHERE id = ?
    ");
    
    if (!$updateStmt) {
        $conn->close();
        return array("success" => false, "message" => "failed to prepare update statement: " . $conn->error);
    }
    
    $updateStmt->bind_param("ssii", $reviewStatus, $reviewNotes, $reviewerId, $scanId);
    
    if ($updateStmt->execute()) {
        $updateStmt->close();
        $conn->close();
        return array("success" => true, "message" => "scan review updated successfully");
    } else {
        $error = $updateStmt->error;
        $updateStmt->close();
        $conn->close();
        return array("success" => false, "message" => "failed to update scan review: " . $error);
    }
}

function updateUserProfile($userId, $newUsername, $newEmail) {
    $conn = getDBConnection();
    
    if (!$conn) {
        return array("success" => false, "message" => "database connection failed");
    }
    
    // Check if user exists
    $userCheck = $conn->prepare("SELECT id, username, email FROM Users WHERE id = ?");
    if (!$userCheck) {
        $conn->close();
        return array("success" => false, "message" => "database query error");
    }
    
    $userCheck->bind_param("i", $userId);
    $userCheck->execute();
    $userResult = $userCheck->get_result();
    
    if ($userResult->num_rows === 0) {
        $userCheck->close();
        $conn->close();
        return array("success" => false, "message" => "user not found");
    }
    
    $currentUser = $userResult->fetch_assoc();
    $userCheck->close();
    
    // Check if new email already exists (if it's different from current email)
    if ($newEmail !== $currentUser['email']) {
        $emailCheck = $conn->prepare("SELECT id FROM Users WHERE email = ? AND id != ?");
        if (!$emailCheck) {
            $conn->close();
            return array("success" => false, "message" => "database query error");
        }
        
        $emailCheck->bind_param("si", $newEmail, $userId);
        $emailCheck->execute();
        $emailResult = $emailCheck->get_result();
        
        if ($emailResult->num_rows > 0) {
            $emailCheck->close();
            $conn->close();
            return array("success" => false, "message" => "email already exists");
        }
        $emailCheck->close();
    }
    
    // Check if new username already exists (if it's different from current username)
    if ($newUsername !== $currentUser['username']) {
        $usernameCheck = $conn->prepare("SELECT id FROM Users WHERE username = ? AND id != ?");
        if (!$usernameCheck) {
            $conn->close();
            return array("success" => false, "message" => "database query error");
        }
        
        $usernameCheck->bind_param("si", $newUsername, $userId);
        $usernameCheck->execute();
        $usernameResult = $usernameCheck->get_result();
        
        if ($usernameResult->num_rows > 0) {
            $usernameCheck->close();
            $conn->close();
            return array("success" => false, "message" => "username already exists");
        }
        $usernameCheck->close();
    }
    
    // Update user profile
    $updateStmt = $conn->prepare("UPDATE Users SET username = ?, email = ?, modified = CURRENT_TIMESTAMP WHERE id = ?");
    if (!$updateStmt) {
        $conn->close();
        return array("success" => false, "message" => "database prepare error");
    }
    
    $updateStmt->bind_param("ssi", $newUsername, $newEmail, $userId);
    
    if ($updateStmt->execute()) {
        $updateStmt->close();
        $conn->close();
        return array(
            "success" => true, 
            "message" => "profile updated successfully",
            "user" => array(
                "user_id" => $userId,
                "username" => $newUsername,
                "email" => $newEmail
            )
        );
    } else {
        $error = $updateStmt->error;
        $updateStmt->close();
        $conn->close();
        return array("success" => false, "message" => "update failed: " . $error);
    }
}

function getUserUrlScansWithPagination($userId, $limit = 10, $offset = 0) {
    $conn = getDBConnection();
    
    if (!$conn) {
        return array("success" => false, "message" => "database connection failed");
    }
    
    // Get total count
    $countStmt = $conn->prepare("SELECT COUNT(*) as total FROM url_scans WHERE user_id = ?");
    if (!$countStmt) {
        $conn->close();
        return array("success" => false, "message" => "database query error");
    }
    
    $countStmt->bind_param("i", $userId);
    $countStmt->execute();
    $countResult = $countStmt->get_result();
    $totalScans = $countResult->fetch_assoc()['total'];
    $countStmt->close();
    
    // Get scans with pagination
    $stmt = $conn->prepare("
        SELECT * FROM url_scans 
        WHERE user_id = ? 
        ORDER BY scan_timestamp DESC 
        LIMIT ? OFFSET ?
    ");
    
    if (!$stmt) {
        $conn->close();
        return array("success" => false, "message" => "database query error");
    }
    
    $stmt->bind_param("iii", $userId, $limit, $offset);
    $stmt->execute();
    $result = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    $conn->close();
    
    return array(
        "success" => true,
        "scans" => $result,
        "total" => $totalScans,
        "limit" => $limit,
        "offset" => $offset
    );
}

function request_processor($request)
{
    echo "received request: " . json_encode($request) . "\n";

    if (!isset($request['type'])) {
        return array("success" => false, "message" => "request type not specified");
    }

    switch ($request['type']) {
        case 'register':
            if (!isset($request['username']) || !isset($request['email']) || !isset($request['password'])) {
                return array("success" => false, "message" => "missing required fields");
            }
            return register($request['username'], $request['email'], $request['password']);

        case 'login':
            if (!isset($request['username']) || !isset($request['password'])) {
                return array("success" => false, "message" => "missing username or password");
            }
            return login($request['username'], $request['password']);

        // --- NEW SCAN HANDLERS ---
        case 'submit_scan':
            if (!isset($request['user_id']) || !isset($request['scan_type']) || !isset($request['input_value'])) {
                return array("success" => false, "message" => "missing user_id, scan_type, or input_value");
            }
            return [
                "success" => true,
                "scan_id" => submitScan($request['user_id'], $request['scan_type'], $request['input_value'])
            ];

        case 'view_scan_result':
            if (!isset($request['scan_id'])) {
                return array("success" => false, "message" => "missing scan_id");
            }
            return [
                "success" => true,
                "result" => viewScanResult($request['scan_id'])
            ];


        case 'get_scan_history':
            if (!isset($request['user_id'])) {
                return array("success" => false, "message" => "missing user_id");
            }
            $limit = $request['limit'] ?? 10;
            $offset = $request['offset'] ?? 0;
            return [
                "success" => true,
                "history" => getScanHistory($request['user_id'], $limit, $offset)
            ];


        case 'manager_review_scans':
            $limit = $request['limit'] ?? 10;
            $offset = $request['offset'] ?? 0;
            return [
                "success" => true,
                "scans" => getClientScansForReview($limit, $offset)
            ];


        case 'flag_scan':
            if (!isset($request['scan_id']) || !isset($request['user_id']) || !isset($request['reason'])) {
                return array("success" => false, "message" => "missing scan_id, user_id, or reason");
            }
            $result = flagScan($request['scan_id'], $request['user_id'], $request['reason']);
            return ['success' => $result];

        case 'get_flagged_scans':
            return [
                "success" => true,
                "flags" => getFlaggedScans()
            ];


        case 'admin_get_all_scans':
            $limit = $request['limit'] ?? 10;
            $offset = $request['offset'] ?? 0;
            return [
                "success" => true,
                "scans" => getAllScans($limit, $offset)
            ];

        case 'admin_check_duplicates':
            if (!isset($request['input_value'])) {
                return array("success" => false, "message" => "missing input_value");
            }
            return [
                "success" => true,
                "duplicates" => findDuplicateSubmissions($request['input_value'])
            ];

        case 'save_url_scan':
            if (!isset($request['user_id']) || !isset($request['scan_data'])) {
                return array("success" => false, "message" => "missing required parameters");
            }
            try {
                $scanId = saveUrlScan($request['user_id'], $request['scan_data']);
                return array("success" => true, "scan_id" => $scanId);
            } catch (Exception $e) {
                return array("success" => false, "message" => $e->getMessage());
            }

        case 'save_domain_scan':
            if (!isset($request['user_id']) || !isset($request['scan_data'])) {
                return array("success" => false, "message" => "missing required parameters");
            }
            try {
                $scanId = saveDomainScan($request['user_id'], $request['scan_data']);
                return array("success" => true, "scan_id" => $scanId);
            } catch (Exception $e) {
                return array("success" => false, "message" => $e->getMessage());
            }

        case 'get_user_url_scans':
            if (!isset($request['user_id'])) {
                return array("success" => false, "message" => "missing user_id");
            }
            $limit = $request['limit'] ?? 50;
            $scans = getUserUrlScans($request['user_id'], $limit);
            return array("success" => true, "scans" => $scans);

        case 'get_user_domain_scans':
            if (!isset($request['user_id'])) {
                return array("success" => false, "message" => "missing user_id");
            }
            $limit = $request['limit'] ?? 50;
            $scans = getUserDomainScans($request['user_id'], $limit);
            return array("success" => true, "scans" => $scans);

        case 'get_all_url_scans':
            $limit = $request['limit'] ?? 100;
            $offset = $request['offset'] ?? 0;
            $reviewStatus = $request['review_status'] ?? 'all';
            $scans = getAllUrlScans($limit, $offset, $reviewStatus);
            if ($scans !== false) {
                return array("success" => true, "scans" => $scans);
            } else {
                return array("success" => false, "message" => "failed to retrieve scans");
            }

        case 'get_all_domain_scans':
            $limit = $request['limit'] ?? 100;
            $offset = $request['offset'] ?? 0;
            $scans = getAllDomainScans($limit, $offset);
            if ($scans !== false) {
                return array("success" => true, "scans" => $scans);
            } else {
                return array("success" => false, "message" => "failed to retrieve domain scans");
            }

        case 'get_all_users':
            $users = getAllUsers();
            if ($users !== false) {
                return array("success" => true, "users" => $users);
            } else {
                return array("success" => false, "message" => "failed to retrieve users");
            }

        case 'get_user_role':
            if (!isset($request['user_id'])) {
                return array("success" => false, "message" => "missing user_id");
            }
            $role = getUserRole($request['user_id']);
            if ($role !== false) {
                return array("success" => true, "role" => $role);
            } else {
                return array("success" => false, "message" => "failed to retrieve user role");
            }

        case 'get_all_roles':
            $roles = getAllRoles();
            if ($roles !== false) {
                return array("success" => true, "roles" => $roles);
            } else {
                return array("success" => false, "message" => "failed to retrieve roles");
            }

        case 'update_user_profile':
            if (!isset($request['user_id']) || !isset($request['username']) || !isset($request['email'])) {
                return array("success" => false, "message" => "missing user_id, username, or email");
            }
            return updateUserProfile($request['user_id'], $request['username'], $request['email']);

        case 'get_user_url_scans_paginated':
            if (!isset($request['user_id'])) {
                return array("success" => false, "message" => "missing user_id");
            }
            $limit = isset($request['limit']) ? (int)$request['limit'] : 10;
            $offset = isset($request['offset']) ? (int)$request['offset'] : 0;
            return getUserUrlScansWithPagination($request['user_id'], $limit, $offset);

        case 'update_user_role':
            if (!isset($request['user_id']) || !isset($request['new_role_id']) || !isset($request['admin_user_id'])) {
                return array("success" => false, "message" => "missing user_id, new_role_id, or admin_user_id");
            }
            return updateUserRole($request['user_id'], $request['new_role_id'], $request['admin_user_id']);

        case 'delete_user':
            if (!isset($request['user_id']) || !isset($request['admin_user_id'])) {
                return array("success" => false, "message" => "missing user_id or admin_user_id");
            }
            return deleteUser($request['user_id'], $request['admin_user_id']);

        case 'update_scan_review':
            if (!isset($request['scan_id']) || !isset($request['review_status']) || !isset($request['reviewer_id'])) {
                return array("success" => false, "message" => "missing scan_id, review_status, or reviewer_id");
            }
            $reviewNotes = $request['review_notes'] ?? '';
            return updateScanReview($request['scan_id'], $request['review_status'], $reviewNotes, $request['reviewer_id']);

        default:
            return array("success" => false, "message" => "unknown request type: " . $request['type']);
    }

}

// start
$server = new rabbitMQServer("testRabbitMQ.ini", "testServer");

echo "database rabbitmq server started\n";
$server->process_requests('request_processor');
echo "database rabbitmq server stopped\n";
exit();
?>
