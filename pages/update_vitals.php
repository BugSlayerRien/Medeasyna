<?php
session_start();
require 'config.php';

// Verify Nurse Role
if (!isset($_SESSION['userID']) || 
    $_SESSION['role'] !== 'Employee' ||
    $_SESSION['employeeRole'] !== 'Nurse') {
    header('Location: signin.php?error=access_denied');
    exit;
}

// Initialize variables
$appointment = null;
$vitalsData = null;
$errors = [];
$successMessage = '';

// Get current week's dates
$currentWeekStart = date('Y-m-d', strtotime('monday this week'));
$currentWeekEnd = date('Y-m-d', strtotime('sunday this week'));

// Validate and process appointment ID if provided
if (isset($_GET['appointmentID']) && $appointmentID = intval($_GET['appointmentID'])) {
    // Check if appointment exists for this week
    $stmt = $conn->prepare("
        SELECT a.*, u.firstName, u.lastName 
        FROM Appointment a
        JOIN Patient p ON a.patientID = p.patientID
        JOIN User u ON p.patientID = u.userID
        WHERE a.appointmentID = ?
        AND a.appointmentDate BETWEEN ? AND ?
        AND a.status = 'Scheduled'
    ");
    $stmt->bind_param("iss", $appointmentID, $currentWeekStart, $currentWeekEnd);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $appointment = $result->fetch_assoc();
        
        // Check for existing vitals
        $checkVitals = $conn->prepare("SELECT * FROM PatientVitals WHERE appointmentID = ?");
        $checkVitals->bind_param("i", $appointmentID);
        $checkVitals->execute();
        $vitalsResult = $checkVitals->get_result();
        if ($vitalsResult->num_rows > 0) {
            $vitalsData = $vitalsResult->fetch_assoc();
        }
    } else {
        $_SESSION['error'] = "No matching scheduled appointment found for this week (".date('M j', strtotime($currentWeekStart))." - ".date('M j', strtotime($currentWeekEnd)).").";
    }
} else {
    $_SESSION['error'] = "No appointment ID provided.";
}

// Handle form submission if we have a valid appointment
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $appointment) {
    $temperature = floatval($_POST['temperature']);
    $bloodPressure = trim($_POST['bloodPressure']);
    $heartRate = intval($_POST['heartRate']);
    $weight = floatval($_POST['weight']);
    $notes = trim($_POST['notes']);
    $recordedBy = $_SESSION['userID'];

    // Validation
    if ($temperature < 35 || $temperature > 42) {
        $errors[] = "Temperature must be between 35°C and 42°C.";
    }

    if (!preg_match("/^\d{2,3}\/\d{2,3}$/", $bloodPressure)) {
        $errors[] = "Invalid blood pressure format. Use 120/80.";
    }

    if ($heartRate < 40 || $heartRate > 180) {
        $errors[] = "Heart rate must be between 40 and 180 bpm.";
    }

    if ($weight < 2 || $weight > 300) {
        $errors[] = "Weight must be between 2 and 300 kg.";
    }

    if (empty($errors)) {
        if ($vitalsData) {
            // Update
            $update = $conn->prepare("
                UPDATE PatientVitals 
                SET temperature = ?, bloodPressure = ?, heartRate = ?, weight = ?, notes = ?, recordedBy = ?, updatedAt = NOW()
                WHERE appointmentID = ?
            ");
            $update->bind_param("dsdssii", $temperature, $bloodPressure, $heartRate, $weight, $notes, $recordedBy, $appointmentID);
            $update->execute();
            $successMessage = "Vitals updated successfully.";
        } else {
            // Insert
            $insert = $conn->prepare("
                INSERT INTO PatientVitals 
                (appointmentID, temperature, bloodPressure, heartRate, weight, notes, recordedBy, createdAt, updatedAt)
                VALUES (?, ?, ?, ?, ?, ?, ?, NOW(), NOW())
            ");
            $insert->bind_param("idsdssi", $appointmentID, $temperature, $bloodPressure, $heartRate, $weight, $notes, $recordedBy);
            $insert->execute();
            $successMessage = "Vitals recorded successfully.";
        }

        // Refresh vitals view
        $checkVitals->execute();
        $vitalsData = $checkVitals->get_result()->fetch_assoc();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Record Vitals</title>
    <link rel="stylesheet" href="../assets/css/main.css">
    <link rel="stylesheet" href="../assets/css/update_vitals.css">

</head>
<body>

<div class="navbar">
    <h1>MedEasyna</h1>
    <div>
    <a href="nurse_dashboard.php">Dashboard</a>
            <a href="today_appointments.php">This Week's Appointments</a>
            <a href="update_vitals.php">Record Vitals</a>
            <a href="mark_ready.php">Mark Patient as Ready</a>
            <a href="../pages/logout.php">Sign Out</a>
    </div>
</div>

<div class="container">
    <h2>Record Patient Vitals</h2>
    
    <?php if (!isset($appointment) || $appointment === null): ?>
        <div class="no-appointments">
            <p>There are no appointments to record vitals for.</p>
            <p>Please select a valid appointment.</p>
        </div>
    <?php else: ?>
        <div class="week-info">
            Current Week: <?= date('M j', strtotime($currentWeekStart)) ?> - <?= date('M j', strtotime($currentWeekEnd)) ?>
        </div>
        
        <div class="patient-info">
            <h3><?= htmlspecialchars($appointment['firstName'] . ' ' . $appointment['lastName']) ?></h3>
            <p>Appointment Date: <?= date('D, M j', strtotime($appointment['appointmentDate'])) ?></p>
            <p>Appointment Time: <?= date('h:i A', strtotime($appointment['appointmentTime'])) ?></p>
        </div>

        <?php if (!empty($errors)): ?>
            <div class="error-box">
                <?php foreach ($errors as $error): ?>
                    <p><?= htmlspecialchars($error) ?></p>
                <?php endforeach; ?>
            </div>
        <?php elseif ($successMessage): ?>
            <div class="success-box">
                <p><?= htmlspecialchars($successMessage) ?></p>
            </div>
        <?php endif; ?>

        <form method="POST">
            <label>Temperature (°C):
                <input type="number" name="temperature" step="0.1" min="35" max="42" required 
                       value="<?= htmlspecialchars($vitalsData['temperature'] ?? '') ?>">
            </label>
            
            <label>Blood Pressure (e.g., 120/80):
                <input type="text" name="bloodPressure" pattern="\d{2,3}\/\d{2,3}" required 
                       value="<?= htmlspecialchars($vitalsData['bloodPressure'] ?? '') ?>">
                <small>Format: systolic/diastolic (e.g., 120/80)</small>
            </label>
            
            <label>Heart Rate (bpm):
                <input type="number" name="heartRate" min="40" max="180" required 
                       value="<?= htmlspecialchars($vitalsData['heartRate'] ?? '') ?>">
            </label>
            
            <label>Weight (kg):
                <input type="number" name="weight" step="0.1" min="2" max="300" required 
                       value="<?= htmlspecialchars($vitalsData['weight'] ?? '') ?>">
            </label>
            
            <label>Notes:
                <textarea name="notes" rows="4"><?= htmlspecialchars($vitalsData['notes'] ?? '') ?></textarea>
            </label>
            
            <button type="submit">Save Vitals</button>
        </form>

        <?php if ($vitalsData): ?>
            <div class="view-box">
                <h4>Existing Vitals</h4>
                <ul class="vitals-list">
                    <li><strong>Temperature:</strong> <?= htmlspecialchars($vitalsData['temperature']) ?> °C</li>
                    <li><strong>Blood Pressure:</strong> <?= htmlspecialchars($vitalsData['bloodPressure']) ?></li>
                    <li><strong>Heart Rate:</strong> <?= htmlspecialchars($vitalsData['heartRate']) ?> bpm</li>
                    <li><strong>Weight:</strong> <?= htmlspecialchars($vitalsData['weight']) ?> kg</li>
                    <li><strong>Notes:</strong> <?= htmlspecialchars($vitalsData['notes']) ?></li>
                    <li><strong>Last Updated:</strong> <?= date('M j, Y g:i A', strtotime($vitalsData['updatedAt'])) ?></li>
                </ul>
            </div>
        <?php endif; ?>
    <?php endif; ?>

    <a href="today_appointments.php" class="back-link">← Back to Appointments</a>
</div>

<footer class="app-footer">
    <div class="footer-content">
        <p class="copyright">&copy; <?php echo date("Y"); ?> MedEasyna</p>
    </div>
</footer>

</body>
</html>