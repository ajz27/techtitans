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

function getAllUsers() {
    $conn = getDBConnection();
    
    if (!$conn) {
        return false;
    }
    
    $stmt = $conn->prepare("
        SELECT u.id, u.username, u.email, u.created, u.modified, u.role_id,
               r.name as role_name, r.description as role_description
        FROM Users u
        LEFT JOIN Roles r ON u.role_id = r.id
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
    
    $stmt = $conn->prepare("
        SELECT u.role_id, r.name as role_name 
        FROM Users u 
        LEFT JOIN Roles r ON u.role_id = r.id 
        WHERE u.id = ?
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

        case 'get_user_url_scans':
            if (!isset($request['user_id'])) {
                return array("success" => false, "message" => "missing user_id");
            }
            $limit = $request['limit'] ?? 50;
            $scans = getUserUrlScans($request['user_id'], $limit);
            return array("success" => true, "scans" => $scans);

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
    }

}

// start
$server = new rabbitMQServer("testRabbitMQ.ini", "testServer");

echo "database rabbitmq server started\n";
$server->process_requests('request_processor');
echo "database rabbitmq server stopped\n";
exit();
?>
