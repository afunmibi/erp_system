<?php
require_once 'includes/database.php';

if (isset($_SESSION['user_id'])) {
    logActivity('Logout', 'auth', 'User logged out');
}

session_destroy();
header('Location: login.php');
exit;
?>
