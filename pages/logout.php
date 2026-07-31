<?php
session_start();

require 'config.php'; 

$stmt = $conn->prepare("INSERT INTO audit_log (userID, tableChanged, columnChanged, action) VALUES (?, 'session', 'status', 'LOGOUT')");
$stmt->bind_param('i', $_SESSION['userID']);
$stmt->execute();


session_unset();
session_destroy();
header("Location: index.php");
exit();
?>
