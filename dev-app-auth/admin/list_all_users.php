<?php
// Start session and suppress deprecated warnings
session_start();
error_reporting(E_ALL & ~E_DEPRECATED);
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);

// Include required files
require_once('../path.inc');
require_once('../get_host_info.inc');
require_once('../rabbitMQLib.inc');

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.html");
    exit();
}

// Get user information from session
$userId = $_SESSION['user_id'];
$username = $_SESSION['username'] ?? 'User';

// Function to check if user is admin
function isUserAdmin($userId) {
    $client = new rabbitMQClient("../testRabbitMQ.ini", "testServer");
    
    $request = array(
        'type' => 'get_user_role',
        'user_id' => $userId
    );
    
    $response = $client->send_request($request);
    
    // Convert stdClass to array if needed
    if (is_object($response)) {
        $response = json_decode(json_encode($response), true);
    }
    
    if (isset($response['success']) && $response['success'] && isset($response['role'])) {
        return $response['role']['role_id'] == 1; // Admin role_id is 1
    }
    
    return false;
}

// Check if current user is admin
if (!isUserAdmin($userId)) {
    // Redirect non-admin users
    header("Location: ../dashboard.php");
    exit();
}

// Handle role update request
$updateMessage = '';
$updateStatus = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_role') {
    if (isset($_POST['target_user_id']) && isset($_POST['new_role_id'])) {
        $targetUserId = intval($_POST['target_user_id']);
        $newRoleId = intval($_POST['new_role_id']);
        
        // Prevent admin from changing their own role
        if ($targetUserId === $userId) {
            $updateMessage = 'You cannot change your own role.';
            $updateStatus = 'error';
        } else {
            $result = updateUserRole($targetUserId, $newRoleId, $userId);
            if (isset($result['success']) && $result['success']) {
                $updateMessage = 'User role updated successfully.';
                $updateStatus = 'success';
            } else {
                $updateMessage = isset($result['message']) ? $result['message'] : 'Failed to update user role.';
                $updateStatus = 'error';
            }
        }
    }
}

// Handle user deletion request
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete_user') {
    if (isset($_POST['target_user_id'])) {
        $targetUserId = intval($_POST['target_user_id']);
        
        // Prevent admin from deleting themselves
        if ($targetUserId === $userId) {
            $updateMessage = 'You cannot delete your own account.';
            $updateStatus = 'error';
        } else {
            $result = deleteUser($targetUserId, $userId);
            if (isset($result['success']) && $result['success']) {
                $updateMessage = $result['message'];
                $updateStatus = 'success';
            } else {
                $updateMessage = isset($result['message']) ? $result['message'] : 'Failed to delete user.';
                $updateStatus = 'error';
            }
        }
    }
}

// Function to get all users
function getAllUsers() {
    $client = new rabbitMQClient("../testRabbitMQ.ini", "testServer");
    
    $request = array(
        'type' => 'get_all_users'
    );
    
    $response = $client->send_request($request);
    
    // Convert stdClass to array if needed
    if (is_object($response)) {
        $response = json_decode(json_encode($response), true);
    }
    
    if (isset($response['success']) && $response['success'] && isset($response['users'])) {
        return $response['users'];
    }
    
    return [];
}

// Function to get all available roles
function getAllRoles() {
    $client = new rabbitMQClient("../testRabbitMQ.ini", "testServer");
    
    $request = array(
        'type' => 'get_all_roles'
    );
    
    $response = $client->send_request($request);
    
    // Convert stdClass to array if needed
    if (is_object($response)) {
        $response = json_decode(json_encode($response), true);
    }
    
    if (isset($response['success']) && $response['success'] && isset($response['roles'])) {
        return $response['roles'];
    }
    
    return [];
}

// Function to update user role
function updateUserRole($targetUserId, $newRoleId, $adminUserId) {
    $client = new rabbitMQClient("../testRabbitMQ.ini", "testServer");
    
    $request = array(
        'type' => 'update_user_role',
        'user_id' => $targetUserId,
        'new_role_id' => $newRoleId,
        'admin_user_id' => $adminUserId
    );
    
    $response = $client->send_request($request);
    
    // Convert stdClass to array if needed
    if (is_object($response)) {
        $response = json_decode(json_encode($response), true);
    }
    
    return $response;
}

// Function to delete user
function deleteUser($targetUserId, $adminUserId) {
    $client = new rabbitMQClient("../testRabbitMQ.ini", "testServer");
    
    $request = array(
        'type' => 'delete_user',
        'user_id' => $targetUserId,
        'admin_user_id' => $adminUserId
    );
    
    $response = $client->send_request($request);
    
    // Convert stdClass to array if needed
    if (is_object($response)) {
        $response = json_decode(json_encode($response), true);
    }
    
    return $response;
}

// Get all users and roles
$users = getAllUsers();
$roles = getAllRoles();

// Function to format date
function formatDate($dateString) {
    if (empty($dateString)) return "N/A";
    $date = new DateTime($dateString);
    return $date->format('Y-m-d H:i:s');
}

// Function to get role badge class
function getRoleBadgeClass($roleId) {
    switch ($roleId) {
        case 1: return 'badge-admin';
        case 2: return 'badge-manager';
        case 3: return 'badge-user';
        default: return 'badge-user';
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>All Users - Admin Panel | TechTitans Security</title>
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

        .nav-link.active {
            color: #ffd700 !important;
            font-weight: bold;
        }

        .manager-link {
            background: linear-gradient(45deg, #28a745, #20c997);
            border-radius: 0.375rem;
            padding: 0.375rem 0.75rem !important;
            margin: 0 0.25rem;
        }

        .admin-link {
            background: linear-gradient(45deg, #dc3545, #fd7e14);
            border-radius: 0.375rem;
            padding: 0.375rem 0.75rem !important;
            margin: 0 0.25rem;
        }

        .card {
            border-radius: 1rem;
            box-shadow: 0 12px 32px rgba(0, 0, 0, 0.2);
            padding: 2rem;
            margin-bottom: 2rem;
            background: rgba(255, 255, 255, 0.9);
        }

        .welcome-card {
            background: rgba(255, 255, 255, 0.9);
            border-left: 5px solid #dc3545;
        }

        .stats-card {
            background: rgba(255, 255, 255, 0.8);
            border-radius: 0.5rem;
            padding: 1rem;
            margin-bottom: 1rem;
        }

        .table-card {
            background: rgba(255, 255, 255, 0.9);
        }

        .table {
            background: white;
            border-radius: 0.5rem;
            overflow: hidden;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        }

        .table thead th {
            background-color: #343a40;
            color: white;
            border: none;
            font-weight: 600;
            padding: 15px;
        }

        .table tbody td {
            padding: 12px 15px;
            vertical-align: middle;
        }

        .table tbody tr:hover {
            background-color: #f8f9fa;
        }

        .badge-admin {
            background-color: #dc3545;
            color: white;
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 0.85em;
            font-weight: 500;
        }

        .badge-manager {
            background-color: #fd7e14;
            color: white;
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 0.85em;
            font-weight: 500;
        }

        .badge-user {
            background-color: #6c757d;
            color: white;
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 0.85em;
            font-weight: 500;
        }

        .user-id {
            font-weight: bold;
            color: #495057;
        }

        .username {
            font-weight: 600;
            color: #212529;
        }

        .email {
            color: #6c757d;
            font-size: 0.9em;
        }

        .date-text {
            font-size: 0.85em;
            color: #6c757d;
        }

        .no-users {
            text-align: center;
            padding: 40px;
            color: #6c757d;
        }

        .btn-primary {
            font-weight: 500;
            transition: background-color 0.3s ease;
        }

        .btn-primary:hover {
            background-color: #0056b3;
        }

        .stat-item {
            text-align: center;
        }

        .stat-number {
            font-size: 2rem;
            font-weight: bold;
            color: #dc3545;
        }

        .stat-label {
            color: #6c757d;
            font-size: 0.9em;
        }

        .role-select {
            border: 1px solid #ced4da;
            border-radius: 0.375rem;
            padding: 0.375rem 0.75rem;
            font-size: 0.875rem;
            background-color: white;
        }

        .role-select:focus {
            border-color: #007bff;
            box-shadow: 0 0 6px rgba(0, 123, 255, 0.3);
            outline: 0;
        }

        .alert {
            border-radius: 0.5rem;
            padding: 0.75rem 1rem;
            margin-bottom: 1rem;
        }

        .alert-success {
            color: #155724;
            background-color: #d4edda;
            border-color: #c3e6cb;
        }

        .alert-danger {
            color: #721c24;
            background-color: #f8d7da;
            border-color: #f5c6cb;
        }

        .role-management-cell {
            min-width: 200px;
        }

        .admin-section {
            background: linear-gradient(135deg, rgba(220, 53, 69, 0.1), rgba(255, 107, 122, 0.1));
            border: 2px solid rgba(220, 53, 69, 0.3);
            border-radius: 0.5rem;
            padding: 1rem;
            margin-top: 1rem;
        }

        .btn-delete {
            background-color: #dc3545;
            color: white;
            border: none;
            padding: 0.25rem 0.5rem;
            border-radius: 0.25rem;
            font-size: 0.75em;
            margin-left: 0.5rem;
            transition: background-color 0.3s ease;
        }

        .btn-delete:hover {
            background-color: #c82333;
            color: white;
        }

        .action-buttons {
            display: flex;
            gap: 0.5rem;
            align-items: center;
            flex-wrap: wrap;
        }

        .delete-form {
            display: inline-block;
        }
    </style>
</head>
<body>
    <!-- Navbar -->
    <nav class="navbar navbar-expand-lg">
        <div class="container">
            <a class="navbar-brand" href="#">TechTitans Security - Admin Panel</a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ms-auto">
                    <li class="nav-item"><a class="nav-link" href="../index.php">Home</a></li>
                    <li class="nav-item"><a class="nav-link" href="../profile.php">Profile</a></li>
                    <li class="nav-item"><a class="nav-link" href="../check-url.php">URL Scan</a></li>
                    <li class="nav-item"><a class="nav-link" href="../check-domain.php">Domain Scan</a></li>
                    <li class="nav-item"><a class="nav-link" href="../view_scan_history.php">Scan History</a></li>
                    <li class="nav-item"><a class="nav-link manager-link" href="../manager/list_all_scans.php">📋 Manager Panel</a></li>
                    <li class="nav-item"><a class="nav-link admin-link active" href="list_all_users.php">👑 Admin Panel</a></li>
                    <li class="nav-item"><a class="nav-link text-danger" href="../logout.php">Logout</a></li>
                </ul>
            </div>
        </div>
    </nav>

    <!-- Main Content -->
    <div class="container mt-4">
        <!-- Welcome Card -->
        <div class="card welcome-card">
            <h1 class="mb-2">👥 User Management</h1>
            <p class="mb-0">Welcome, <?php echo htmlspecialchars($username); ?>! Manage all registered users from this admin panel.</p>
        </div>

        <!-- Update Message -->
        <?php if (!empty($updateMessage)): ?>
            <div class="alert alert-<?php echo $updateStatus === 'success' ? 'success' : 'danger'; ?>" role="alert">
                <?php echo htmlspecialchars($updateMessage); ?>
            </div>
        <?php endif; ?>

        <!-- Statistics -->
        <?php if (!empty($users)): ?>
            <?php
            $totalUsers = count($users);
            $adminCount = count(array_filter($users, function($user) { return $user['role_id'] == 1; }));
            $managerCount = count(array_filter($users, function($user) { return $user['role_id'] == 2; }));
            $regularUsers = count(array_filter($users, function($user) { return $user['role_id'] == 3; }));
            $unassignedUsers = count(array_filter($users, function($user) { return empty($user['role_id']); }));
            ?>
            <div class="card stats-card">
                <div class="row">
                    <div class="col-md-2 stat-item">
                        <div class="stat-number"><?php echo $totalUsers; ?></div>
                        <div class="stat-label">Total Users</div>
                    </div>
                    <div class="col-md-2 stat-item">
                        <div class="stat-number"><?php echo $adminCount; ?></div>
                        <div class="stat-label">Admins</div>
                    </div>
                    <div class="col-md-2 stat-item">
                        <div class="stat-number"><?php echo $managerCount; ?></div>
                        <div class="stat-label">Managers</div>
                    </div>
                    <div class="col-md-2 stat-item">
                        <div class="stat-number"><?php echo $regularUsers; ?></div>
                        <div class="stat-label">Regular Users</div>
                    </div>
                    <div class="col-md-2 stat-item">
                        <div class="stat-number"><?php echo $unassignedUsers; ?></div>
                        <div class="stat-label">Unassigned</div>
                    </div>
                </div>
            </div>
        <?php endif; ?>

        <?php if (empty($users)): ?>
            <div class="card">
                <div class="no-users">
                    <h4>No Users Found</h4>
                    <p>There are no registered users in the system.</p>
                </div>
            </div>
        <?php else: ?>
            <!-- Users Table -->
            <div class="card table-card">
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Username</th>
                                <th>Email</th>
                                <th>Role</th>
                                <th>Registered</th>
                                <th>Last Modified</th>
                                <th>Manage Role</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($users as $user): ?>
                                <tr>
                                    <td>
                                        <span class="user-id"><?php echo htmlspecialchars($user['id']); ?></span>
                                    </td>
                                    <td>
                                        <span class="username">
                                            <?php 
                                            // Display username if available, otherwise use email prefix
                                            if (!empty($user['username'])) {
                                                echo htmlspecialchars($user['username']);
                                            } else {
                                                echo htmlspecialchars(explode('@', $user['email'])[0]);
                                            }
                                            ?>
                                        </span>
                                    </td>
                                    <td>
                                        <span class="email"><?php echo htmlspecialchars($user['email']); ?></span>
                                    </td>
                                    <td>
                                        <span class="<?php echo getRoleBadgeClass($user['role_id']); ?>">
                                            <?php 
                                            if (!empty($user['role_name'])) {
                                                echo htmlspecialchars(ucfirst($user['role_name']));
                                            } else {
                                                echo 'No Role Assigned';
                                            }
                                            ?>
                                        </span>
                                    </td>
                                    <td>
                                        <span class="date-text"><?php echo formatDate($user['created']); ?></span>
                                    </td>
                                    <td>
                                        <span class="date-text"><?php echo formatDate($user['modified']); ?></span>
                                    </td>
                                    <td class="role-management-cell">
                                        <?php if ($user['id'] == $userId): ?>
                                            <small class="text-muted">Your Account</small>
                                        <?php else: ?>
                                            <form method="POST" style="display: inline-block;" class="role-update-form">
                                                <input type="hidden" name="action" value="update_role">
                                                <input type="hidden" name="target_user_id" value="<?php echo $user['id']; ?>">
                                                <select name="new_role_id" class="role-select">
                                                    <option value="">Select Role</option>
                                                    <?php foreach ($roles as $role): ?>
                                                        <option value="<?php echo $role['id']; ?>" 
                                                            <?php echo (isset($user['role_id']) && $user['role_id'] == $role['id']) ? 'selected' : ''; ?>>
                                                            <?php echo htmlspecialchars(ucfirst($role['name'])); ?>
                                                        </option>
                                                    <?php endforeach; ?>
                                                </select>
                                            </form>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($user['id'] == $userId): ?>
                                            <small class="text-muted">Your Account</small>
                                        <?php else: ?>
                                            <div class="action-buttons">
                                                <form method="POST" class="delete-form" onsubmit="return confirmDelete('<?php echo htmlspecialchars($user['username'] ?: explode('@', $user['email'])[0]); ?>')">
                                                    <input type="hidden" name="action" value="delete_user">
                                                    <input type="hidden" name="target_user_id" value="<?php echo $user['id']; ?>">
                                                    <button type="submit" class="btn-delete">Delete User</button>
                                                </form>
                                            </div>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        <?php endif; ?>

        <!-- Action Buttons -->
        <div class="card">
            <div class="d-flex flex-wrap gap-2">
                <a href="../dashboard.php" class="btn btn-outline-secondary">Back to Dashboard</a>
                <a href="../view_scan_history.php" class="btn btn-outline-primary">View Scan History</a>
                <a href="../profile.php" class="btn btn-primary">User Profile</a>
            </div>
        </div>
    </div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <!-- Role Management JavaScript -->
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Add confirmation for role changes
            const roleSelects = document.querySelectorAll('.role-select');
            
            roleSelects.forEach(function(select) {
                const originalValue = select.value;
                
                select.addEventListener('change', function() {
                    if (this.value && this.value !== originalValue) {
                        const userName = this.closest('tr').querySelector('.username').textContent.trim();
                        const newRoleName = this.options[this.selectedIndex].text;
                        
                        if (confirm(`Are you sure you want to change ${userName}'s role to ${newRoleName}?`)) {
                            // Submit the form
                            this.closest('form').submit();
                        } else {
                            // Reset to original value
                            this.value = originalValue;
                        }
                    }
                });
            });
        });

        // Function to confirm user deletion
        function confirmDelete(username) {
            return confirm(`⚠️ WARNING: Are you sure you want to permanently delete user "${username}"?\n\nThis action will:\n• Delete the user account\n• Remove all user roles\n• Delete all associated data\n\nThis action CANNOT be undone!`);
        }
    </script>
</body>
</html>
