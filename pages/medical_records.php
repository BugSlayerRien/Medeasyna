<?php
session_start();
require 'config.php'; // Database connection

if (!isset($_SESSION['userID']) || $_SESSION['role'] !== 'Patient') {
    header("Location: ../pages/index.php");
    exit();
}

$patientId = $_SESSION['userID'];

// Fetch medical reports with doctor information
$stmt = $conn->prepare("
    SELECT 
        mr.reportID,
        mr.diagnosis,
        mr.treatment,
        mr.prescription,
        mr.notes,
        mr.reportDate,
        u.firstName AS doctorFirstName,
        u.lastName AS doctorLastName,
        a.appointmentDate,
        a.appointmentTime
    FROM MedicalReports mr
    JOIN User u ON mr.doctorID = u.userID
    LEFT JOIN Appointment a ON mr.appointmentID = a.appointmentID
    WHERE mr.patientID = ?
    ORDER BY mr.reportDate DESC
");
$stmt->bind_param("i", $patientId);
$stmt->execute();
$result = $stmt->get_result();

$reports = [];
while ($row = $result->fetch_assoc()) {
    $reports[] = $row;
}
$stmt->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Medical Records</title>
    <link rel="stylesheet" href="../assets/css/main.css">
    <link rel="stylesheet" href="../assets/css/medical_records.css">

</head>
<body>

<div class="navbar">
    <h1>Doval Santos Diagnostic Center</h1>
    <div>
        <a href="patient_dashboard.php">Dashboard</a>
        <a href="book_appointment.php">Schedule Appointment</a>
        <a href="my_appointments.php">My Appointments</a>
        <a href="medical_records.php" class="active">Medical Records</a>
        <a href="logout.php">Sign Out</a>
    </div>
</div>

<main>
    <h2>Medical Records</h2>

    <?php if (empty($reports)): ?>
        <div class="no-records">
            <p>No medical reports found.</p>
        </div>
    <?php else: ?>
        <table>
            <thead>
                <tr>
                    <th>Date</th>
                    <th>Doctor</th>
                    <th>Diagnosis</th>
                    <th>Appointment</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($reports as $report): ?>
                    <tr onclick="toggleDetails('details-<?= $report['reportID'] ?>')" style="cursor: pointer;">
                        <td><?= htmlspecialchars(date('M j, Y', strtotime($report['reportDate']))) ?></td>
                        <td>Dr. <?= htmlspecialchars($report['doctorFirstName'] . ' ' . $report['doctorLastName']) ?></td>
                        <td><?= htmlspecialchars($report['diagnosis']) ?></td>
                        <td>
                            <?php if ($report['appointmentDate']): ?>
                                <?= date('M j, Y', strtotime($report['appointmentDate'])) ?>
                                at <?= date('g:i A', strtotime($report['appointmentTime'])) ?>
                            <?php else: ?>
                                Not linked to appointment
                            <?php endif; ?>
                        </td>
                    </tr>
                    <tr id="details-<?= $report['reportID'] ?>" style="display: none;">
                        <td colspan="4">
                            <div class="report-details">
                                <h3>Report Details</h3>
                                <div class="detail-row">
                                    <span class="detail-label">Diagnosis:</span>
                                    <p><?= htmlspecialchars($report['diagnosis']) ?></p>
                                </div>
                                <div class="detail-row">
                                    <span class="detail-label">Treatment:</span>
                                    <p><?= htmlspecialchars($report['treatment']) ?></p>
                                </div>
                                <div class="detail-row">
                                    <span class="detail-label">Prescription:</span>
                                    <p><?= htmlspecialchars($report['prescription']) ?></p>
                                </div>
                                <div class="detail-row">
                                    <span class="detail-label">Notes:</span>
                                    <p><?= htmlspecialchars($report['notes']) ?></p>
                                </div>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</main>


<script>
    function toggleDetails(id) {
        const element = document.getElementById(id);
        if (element.style.display === 'none') {
            element.style.display = 'table-row';
        } else {
            element.style.display = 'none';
        }
    }
</script>

<footer class="app-footer">
    <div class="footer-content">
        <p class="copyright">&copy; <?php echo date("Y"); ?> Doval Santos Diagnostic Center</p>
    </div>
</footer>

</body>
</html>