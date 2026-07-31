<?php
session_start();
require 'config.php';

// Verify doctor is logged in
if (!isset($_SESSION['userID']) || $_SESSION['role'] !== 'Doctor') {
    header("Location: ../pages/index.php");
    exit();
}

$doctorID = $_SESSION['userID'];

// Handle form submission for new report
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_report'])) {
    $patientID = intval($_POST['patient_id']);
    $diagnosis = trim($_POST['diagnosis']);
    $treatment = trim($_POST['treatment']);
    $prescription = trim($_POST['prescription']);
    $notes = trim($_POST['notes']);
    $appointmentID = isset($_POST['appointment_id']) ? intval($_POST['appointment_id']) : null;

    // Insert new medical report
    $stmt = $conn->prepare("
        INSERT INTO MedicalReports 
        (patientID, doctorID, appointmentID, diagnosis, treatment, prescription, notes, reportDate) 
        VALUES (?, ?, ?, ?, ?, ?, ?, NOW())
    ");
    $stmt->bind_param("iiissss", $patientID, $doctorID, $appointmentID, $diagnosis, $treatment, $prescription, $notes);
    
    if ($stmt->execute()) {
        $_SESSION['success_message'] = "Medical report created successfully!";
    } else {
        $_SESSION['error_message'] = "Error creating medical report: " . $conn->error;
    }
    header("Location: medical_reports.php");
    exit();
}

// Handle report deletion
if (isset($_GET['delete_report'])) {
    $reportID = intval($_GET['delete_report']);
    $stmt = $conn->prepare("DELETE FROM MedicalReports WHERE reportID = ? AND doctorID = ?");
    $stmt->bind_param("ii", $reportID, $doctorID);
    
    if ($stmt->execute()) {
        $_SESSION['success_message'] = "Report deleted successfully!";
    } else {
        $_SESSION['error_message'] = "Error deleting report: " . $conn->error;
    }
    header("Location: medical_reports.php");
    exit();
}

// Fetch doctor's patients
$patients = [];
$stmt = $conn->prepare("
    SELECT DISTINCT p.patientID, u.firstName, u.lastName 
    FROM Appointment a
    JOIN Patient p ON a.patientID = p.patientID
    JOIN User u ON p.patientID = u.userID
    WHERE a.doctorID = ?
    ORDER BY u.lastName, u.firstName
");
$stmt->bind_param("i", $doctorID);
$stmt->execute();
$result = $stmt->get_result();
$patients = $result->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Fetch patient's appointments (when selected)
$appointments = [];
$selectedPatientID = isset($_GET['patient_id']) ? intval($_GET['patient_id']) : null;

if ($selectedPatientID) {
    $stmt = $conn->prepare("
        SELECT appointmentID, appointmentDate, appointmentTime, status
        FROM Appointment
        WHERE patientID = ? AND doctorID = ?
        ORDER BY appointmentDate DESC
    ");
    $stmt->bind_param("ii", $selectedPatientID, $doctorID);
    $stmt->execute();
    $result = $stmt->get_result();
    $appointments = $result->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
}

// Fetch reports for selected patient
$reports = [];
if ($selectedPatientID) {
    $stmt = $conn->prepare("
        SELECT r.*, a.appointmentDate, a.appointmentTime
        FROM MedicalReports r
        LEFT JOIN Appointment a ON r.appointmentID = a.appointmentID
        WHERE r.patientID = ? AND r.doctorID = ?
        ORDER BY r.reportDate DESC
    ");
    $stmt->bind_param("ii", $selectedPatientID, $doctorID);
    $stmt->execute();
    $result = $stmt->get_result();
    $reports = $result->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
}

// Check for messages
$successMessage = $_SESSION['success_message'] ?? '';
$errorMessage = $_SESSION['error_message'] ?? '';
unset($_SESSION['success_message'], $_SESSION['error_message']);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Medical Reports | Doctor Dashboard</title>
    <link rel="stylesheet" href="../assets/css/main.css">
    <link rel="stylesheet" href="../assets/css/medical_reports.css">

</head>
<body>
    
<div class="navbar">
    <h1>Doval Santos Diagnostic Center</h1>
    <div>
        <a href="../pages/doctor_dashboard.php">Dashboard</a>
        <a href="../pages/doctor_availability.php">Availability</a>
        <a href="../pages/patient_records.php">Patient Records</a>
        <a href="../pages/medical_reports.php">Medical Reports</a>
        <a href="../pages/doctor_appointments.php">Appointments</a>
        <a href="logout.php">Sign Out</a>
    </div>
</div>

    <main class="container">
        <h2>Medical Reports</h2>
        
        <?php if ($successMessage): ?>
            <div class="alert alert-success">
                <?php echo htmlspecialchars($successMessage); ?>
            </div>
        <?php endif; ?>
        
        <?php if ($errorMessage): ?>
            <div class="alert alert-error">
                <?php echo htmlspecialchars($errorMessage); ?>
            </div>
        <?php endif; ?>

        <div class="card">
            <h3>Select Patient</h3>
            <form method="GET" action="medical_reports.php">
                <div class="form-group">
                    <label for="patient_id">Patient:</label>
                    <select name="patient_id" id="patient_id" required>
                        <option value="">-- Select Patient --</option>
                        <?php foreach ($patients as $patient): ?>
                            <option value="<?= $patient['patientID'] ?>" <?= $selectedPatientID == $patient['patientID'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($patient['firstName'] . ' ' . $patient['lastName']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <button type="submit">Create New Report</button>
            </form>
        </div>

        <?php if ($selectedPatientID): ?>
            <div class="card">
                <h3>Create New Report</h3>
                <form method="POST" action="medical_reports.php">
                    <input type="hidden" name="patient_id" value="<?= $selectedPatientID ?>">
                    
                    <div class="form-group">
                        <label for="appointment_id">Associated Appointment (optional):</label>
                        <select name="appointment_id" id="appointment_id">
                            <option value="">-- Not linked to appointment --</option>
                            <?php foreach ($appointments as $appt): ?>
                                <option value="<?= $appt['appointmentID'] ?>">
                                    <?= date('M j, Y', strtotime($appt['appointmentDate'])) ?> - 
                                    <?= date('g:i A', strtotime($appt['appointmentTime'])) ?>
                                    (<?= $appt['status'] ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="diagnosis">Diagnosis:</label>
                        <input type="text" name="diagnosis" id="diagnosis" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="treatment">Treatment:</label>
                        <textarea name="treatment" id="treatment" required></textarea>
                    </div>
                    
                    <div class="form-group">
                        <label for="prescription">Prescription:</label>
                        <textarea name="prescription" id="prescription"></textarea>
                    </div>
                    
                    <div class="form-group">
                        <label for="notes">Notes:</label>
                        <textarea name="notes" id="notes"></textarea>
                    </div>
                    
                    <button type="submit" name="create_report">Create Report</button>
                </form>
            </div>

            <div class="card">
                <h3>Patient Reports</h3>
                
                <?php if (empty($reports)): ?>
                    <div class="no-records">
                        <p>No medical reports found for this patient.</p>
                    </div>
                <?php else: ?>
                    <table>
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Appointment</th>
                                <th>Diagnosis</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($reports as $report): ?>
                                <tr>
                                    <td><?= date('M j, Y', strtotime($report['reportDate'])) ?></td>
                                    <td>
                                        <?php if ($report['appointmentDate']): ?>
                                            <?= date('M j, Y', strtotime($report['appointmentDate'])) ?>
                                            at <?= date('g:i A', strtotime($report['appointmentTime'])) ?>
                                        <?php else: ?>
                                            N/A
                                        <?php endif; ?>
                                    </td>
                                    <td><?= htmlspecialchars($report['diagnosis']) ?></td>
                                    <td>
                                       
                                        <a href="medical_reports.php?delete_report=<?= $report['reportID'] ?>" class="action-btn delete-btn" 
                                           onclick="return confirm('Are you sure you want to delete this report?')">Delete</a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </main>

</body>
<footer class="app-footer">
    <div class="footer-content">
        <p class="copyright">&copy; <?php echo date("Y"); ?> Doval Santos Diagnostic Center</p>
    </div>
</footer>
</html>