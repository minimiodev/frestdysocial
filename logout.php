<?php
/**
 * Logout Page - Frests Lite
 */
require_once __DIR__ . '/config.php';

// Clear user session variables
unset($_SESSION['user_id']);
unset($_SESSION['username']);
unset($_SESSION['avatar']);

// Redirect to login page
header("Location: login.php");
exit;

