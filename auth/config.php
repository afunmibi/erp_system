<?php
// Database configuration
$db_host = 'localhost';
$db_name = 'erp_system';
$db_user = 'root';
$db_pass = '';

// Only start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Authentication functions
function isLoggedIn() {
    return isset($_SESSION['user_id']);
}

function requireAuth() {
    if (!isLoggedIn()) {
        header('Location: login.php');
        exit;
    }
}

function hasRole($role) {
    return isset($_SESSION['user_role']) && $_SESSION['user_role'] === $role;
}

// Get current user info
function getCurrentUser() {
    if (isLoggedIn()) {
        return [
            'id' => $_SESSION['user_id'],
            'username' => $_SESSION['user_username'] ?? '',
            'email' => $_SESSION['user_email'] ?? '',
            'role' => $_SESSION['user_role'] ?? ''
        ];
    }
    return null;
}
?>
