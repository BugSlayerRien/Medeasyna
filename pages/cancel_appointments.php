<?php //PATIENT
session_start();
require 'config.php';

// Access Control: Only logged-in patients can cancel their own appointments
if (!isset($_SESSION['userID']) || $_SESSION['role'] !== 'Patient') {
    header("Location: index.php");
    exit;
}

// Check if appointment ID is passed via POST
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['appointmentID'])) {
    $appointmentID = intval($_POST['appointmentID']);
    $patientID = $_SESSION['patientID'];

    // 1. Validate if appointment exists and belongs to this patient
    $checkSql = "SELECT * FROM Appointment WHERE appointmentID = ? AND patientID = ? AND status = 'Scheduled'";
    $stmt = $conn->prepare($checkSql);
    $stmt->bind_param("ii", $appointmentID, $patientID);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 1) {
        // 2. Proceed with cancellation
        $cancelSql = "UPDATE Appointment 
        SET status = 'Canceled', 
        updatedAt = NOW(), 
        cancellationDate = NOW(), 
        cancellationReason = ? 
        WHERE appointmentID = ?";
        $cancelReason = "Patient request";
        $cancelStmt = $conn->prepare($cancelSql);
        $cancelStmt->bind_param("si", $cancelReason, $appointmentID);
        $cancelStmt->execute();

        // 3. Log the cancellation in the Audit Log
        $logSql = "INSERT INTO audit_log (userID, action, createdAt) 
        VALUES (?, ?, NOW())";
        $logMsg = "Patient canceled appointment ID: $appointmentID";
        $logStmt = $conn->prepare($logSql);
        $logStmt->bind_param("is", $_SESSION['userID'], $logMsg);
        $logStmt->execute();

        // Optional: Send cancellation notification to doctor/admin via email or notification system

        header("Location: my_appointments.php?status=success&message=Appointment+Canceled");
        exit;
    } else {
        // Appointment not found or doesn't belong to user
        header("Location: my_appointments.php?status=error&message=Invalid+Appointment");
        exit;
    }
} else {
    // Invalid request
    header("Location: my_appointments.php?status=error&message=Invalid+Request");
    exit;
}
?>
