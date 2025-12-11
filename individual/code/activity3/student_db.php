<?php

// Start session for login
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

define('DB_HOST', 'localhost');
define('DB_PORT', '3307'); // Change to 3306 if needed
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'attendanceAshesi');


function getDBConnection() {
    $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME, DB_PORT);
    
    if ($conn->connect_error) {
        die("Connection failed: " . $conn->connect_error);
    }
    
    return $conn;
}

function sanitize_input($data) {
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data, ENT_QUOTES, 'UTF-8');
    return $data;
}

function is_logged_in() {
    return isset($_SESSION['student_id']) && !empty($_SESSION['student_id']);
}

function require_login() {
    if (!is_logged_in()) {
        header("Location: login.php");
        exit();
    }
}
?>