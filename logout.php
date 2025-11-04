<?php
/*
 * LernovaAI - Logout Script
 * Destroys the user session and redirects to login.
 */

session_start(); // Resume the session
session_unset(); // Unset all session variables
session_destroy(); // Destroy the session data

// Start a new session to store the logout message
session_start();
$_SESSION['logout_message'] = 'You have been successfully logged out.';

// Redirect to the login page
header("Location: login.php");
exit;
?>