<?php
$host = "localhost";
$user = "aisha.chihuri"; 
$port = '3306';  
$pass = "RGMugabe2027";       
$dbname = "webtech_2025A_aisha_chihuri";

$conn = new mysqli($host, $user, $pass, $dbname,$port);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}
?>


