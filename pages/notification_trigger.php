<?php
// This script should be triggered after the doctor's schedule is updated.

include 'config.php';

// Fetch all appointments for the doctor
$doctorID = $_SESSION['userID']; // Assuming the doctor is logged in
$sql = "SELECT appointmentID, patientID, appointmentDate, appointmentTime 
        FROM Appointment 
        WHERE doctorID = ? AND appointmentDate = ? AND appointmentTime = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("iss", $doctorID, $appointmentDate, $appointmentTime);
$stmt->execute();
$result = $stmt->get_result();

// Notify patients of schedule change
while ($row = $result->fetch_assoc()) {
    $patientID = $row['patientID'];
    $appointmentID = $row['appointmentID'];

    // Send notification
    $message = "Your appointment on " . $appointmentDate . " at " . $appointmentTime . " has been rescheduled due to a change in the doctor's availability.";
    $stmt2 = $conn->prepare("INSERT INTO Notification (userID, message, sentDate) VALUES (?, ?, NOW())");
    $stmt2->bind_param("is", $patientID, $message);
    $stmt2->execute();

    // Optionally, update appointment status to "Rescheduled" or something else
    $stmt2 = $conn->prepare("UPDATE Appointment SET status = 'Rescheduled' WHERE appointmentID = ?");
    $stmt2->bind_param("i", $appointmentID);
    $stmt2->execute();
}

$stmt->close();
?>
