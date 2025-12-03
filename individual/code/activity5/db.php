<?php
$host = "localhost";
$user = "root"; 
$port = '3307';  
$pass = "";       
$dbname = "ashesi_attendance";

$conn = new mysqli($host, $user, $pass, $dbname,$port);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}
?>


