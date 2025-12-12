<?php

include 'db.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

define('DB_HOST', 'localhost');
define('DB_USER', 'aisha.chihuri');
define('DB_PORT', '3306');
define('DB_PASS', 'RGMugabe2027');
define('DB_NAME', '"webtech_2025A_aisha_chihuri');

function getDBConnection() {
    $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME,DB_PORT );
    
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
    return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
}


function require_login() {
    if (!is_logged_in()) {
        header("Location: login.php");
        exit();
    }
}

function redirect_to_dashboard() {
    if (isset($_SESSION['role'])) {
        if ($_SESSION['role'] === 'faculty') {
            header("Location: faculty_dashboard.php");
        } else {
            header("Location: student_dashboard.php");
        }
        exit();
    }
}
function get_initials($name) {
    $words = explode(' ', $name);
    $initials = '';
    foreach ($words as $word) {
        $initials .= strtoupper(substr($word, 0, 1));
    }
    return substr($initials, 0, 2);
}
?>