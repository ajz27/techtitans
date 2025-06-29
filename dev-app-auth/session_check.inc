<?php
// Session management utility functions

function isLoggedIn() {
    if (session_status() == PHP_SESSION_NONE) {
        session_start();
    }
    return isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true;
}

function requireLogin() {
    if (!isLoggedIn()) {
        header("Location: login.html");
        exit();
    }
}

function getSessionUser() {
    if (session_status() == PHP_SESSION_NONE) {
        session_start();
    }
    
    if (isLoggedIn()) {
        return array(
            'user_id' => $_SESSION['user_id'],
            'username' => $_SESSION['username'],
            'email' => $_SESSION['email']
        );
    }
    return null;
}

function destroySession() {
    if (session_status() == PHP_SESSION_NONE) {
        session_start();
    }
    
    session_unset();
    session_destroy();
    
    // Clear any session cookies
    if (ini_get("session.use_cookies")) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000,
            $params["path"], $params["domain"],
            $params["secure"], $params["httponly"]
        );
    }
}
?>