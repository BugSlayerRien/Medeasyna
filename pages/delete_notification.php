<?php
session_start();
include 'config.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['notificationID'])) {
    $notificationID = (int)$_POST['notificationID'];
    $userID = $_SESSION['userID'];

    try {
        // Verify the notification belongs to the user before deleting
        $stmt = $conn->prepare("DELETE FROM Notification WHERE notificationID = ? AND userID = ?");
        $stmt->bind_param("ii", $notificationID, $userID);
        $stmt->execute();

        if ($stmt->affected_rows > 0) {
            $_SESSION['success'] = "Notification deleted successfully";
        } else {
            $_SESSION['error'] = "Notification not found or you don't have permission";
        }
    } catch (Exception $e) {
        $_SESSION['error'] = "Error deleting notification: " . $e->getMessage();
    }
}

header("Location: " . ($_SERVER['HTTP_REFERER'] ?? 'dashboard.php'));
exit();
?>