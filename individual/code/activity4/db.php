<?php
$host = "localhost";
$user = "root";     // default XAMPP MySQL user
$pass = "";         // default XAMPP password is empty
$dbname = "ashesi_attendance";

$conn = new mysqli($host, $user, $pass, $dbname);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}
?>
