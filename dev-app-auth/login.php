<?php
session_start();
include 'db.php';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $username = trim($_POST['username']);
    $password = trim($_POST['password']);

    // Prepare and execute query
    $stmt = $conn->prepare("SELECT id, username, password FROM users WHERE username = ? OR email = ?");
    $stmt->bind_param("ss", $username, $username);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 1) {
        $row = $result->fetch_assoc();

        if (password_verify($password, $row['password'])) {
            // Login successful
            $_SESSION['user_id'] = $row['id'];
            $_SESSION['username'] = $row['username'];

            // Set localStorage via JavaScript and redirect
            echo "<script>
                localStorage.setItem('loggedIn', 'true');
                window.location.href = 'index.php';
            </script>";
            exit;
        } else {
            header("Location: login.html?error=Invalid password");
            exit;
        }
    } else {
        header("Location: login.html?error=User not found");
        exit;
    }
}
?>
