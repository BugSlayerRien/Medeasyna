<?php
session_start();

if (!isset($_SESSION['userID']) || $_SESSION['role'] !== 'Doctor') {
    header("Location: index.php");
    exit();
}

include 'config.php';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Ensure patientID is being sent in the POST request
    if (!isset($_POST['patientID']) || empty($_POST['patientID'])) {
        die("Patient ID is required.");
    }

    // Get values from POST
    $doctorID = $_SESSION['userID'];  // Get the doctor's ID from the session
    $patientID = $_POST['patientID'];  // Patient ID from the form
    $diagnosis = $_POST['diagnosis'];
    $treatment = $_POST['treatment'];

    // Save the medical record to the database
    $sql = "INSERT INTO MedicalRecords (doctorID, patientID, diagnosis, treatment) VALUES (?, ?, ?, ?)";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("iiss", $doctorID, $patientID, $diagnosis, $treatment);
    $stmt->execute();

    // Redirect after saving
    header("Location: view_patient.php?patientID=" . $patientID); 
    exit();
}
?>
