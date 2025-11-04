<?php
/*
 * LernovaAI - Database Connection
 * This file connects the application to the 'lernovaai_db'.
 */

// Only create connection if it doesn't already exist or is closed/invalid
$need_new_connection = true;
if (isset($conn) && $conn instanceof mysqli) {
    // Check if connection is valid by trying to ping it
    try {
        // Check if connection appears to be open by checking thread_id
        @$thread_id = $conn->thread_id;
        if ($thread_id !== null) {
            // Connection appears open, try to ping
            $need_new_connection = !@$conn->ping();
        } else {
            // Connection is closed, need new one
            $need_new_connection = true;
        }
    } catch (Exception $e) {
        // If any exception occurs, create a new connection
        $need_new_connection = true;
    } catch (Error $e) {
        // Catch PHP 7+ Error objects (like "mysqli object is already closed")
        $need_new_connection = true;
    } catch (Throwable $e) {
        // Catch any other throwable errors
        $need_new_connection = true;
    }
}

if ($need_new_connection) {
    // Database credentials
    $servername = "localhost"; // This is the default for XAMPP
    $username = "root";        // This is the default for XAMPP
    $password = "";            // This is the default for XAMPP
    $dbname = "lernovaai_db";  // The database we just created

    // Create connection using MySQLi (MySQL Improved)
    $conn = new mysqli($servername, $username, $password, $dbname);

    // Check connection
    if ($conn->connect_error) {
        // If connection fails, stop the script and show the error.
        die("Connection failed: " . $conn->connect_error);
    }

    // Set the character set to utf8mb4 for full UTF-8 support (good for all languages)
    $conn->set_charset("utf8mb4");
}

?>