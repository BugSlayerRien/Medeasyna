<?php
date_default_timezone_set('Asia/Manila');
$host = "localhost";
$db_user = "root";
$db_pass = "";
$db_name = "medeasyna";
$conn = new mysqli($host, $db_user, $db_pass, $db_name);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}
$conn->query("SET time_zone = '+08:00'"); 
?>
