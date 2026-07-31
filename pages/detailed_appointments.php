<?php
session_start();
require 'config.php';

if (!isset($_SESSION['userID']) || $_SESSION['role'] !== 'Patient') {
    header("Location: signin.php");
    exit();
}

if (!isset($_GET['id'])) {
    header("Location: my_appointments.php?message=Missing+appointment+ID");
    exit();
}

$appointmentID = intval($_GET['id']);
$patientID = $_SESSION['patientID'];

// Fetch appointment info
$sql = "
    SELECT a.*, 
           u.firstName AS doctorFirstName, 
           u.lastName AS doctorLastName, 
           d.specialization
    FROM Appointment a
    JOIN Doctor d ON a.doctorID = d.doctorID
    JOIN User u ON u.userID = d.doctorID
    WHERE a.appointmentID = ? AND a.patientID = ?
";
$stmt = $conn->prepare($sql);
$stmt->bind_param("ii", $appointmentID, $patientID);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows !== 1) {
    header("Location: my_appointments.php?message=Appointment+not+found");
    exit();
}

$appointment = $result->fetch_assoc();

// Fetch linked services
$services = [];
$serviceSql = "
    SELECT s.serviceName, s.price, aps.quantity
    FROM AppointmentService aps
    JOIN Service s ON aps.serviceID = s.serviceID
    WHERE aps.appointmentID = ?
";
$svcStmt = $conn->prepare($serviceSql);
$svcStmt->bind_param("i", $appointmentID);
$svcStmt->execute();
$svcResult = $svcStmt->get_result();
while ($row = $svcResult->fetch_assoc()) {
    $services[] = $row;
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Appointment Details</title>
    <link rel="stylesheet" href="../assets/css/detailed_appointments.css">
</head>
<body>
<h2>Appointment Details</h2>

<p><strong>Date:</strong> <?= $appointment['appointmentDate'] ?></p>
<p><strong>Time:</strong> <?= $appointment['appointmentTime'] ?></p>
<p><strong>Status:</strong> <?= $appointment['status'] ?></p>
<p><strong>Doctor:</strong> Dr. <?= $appointment['doctorFirstName'] . " " . $appointment['doctorLastName'] ?></p>
<p><strong>Specialization:</strong> <?= $appointment['specialization'] ?></p>
<p><strong>Reason for Visit:</strong> <?= $appointment['reasonForVisit'] ?></p>

<?php if (!empty($services)): ?>
    <h4>Included Services</h4>
    <ul>
        <?php foreach ($services as $svc): ?>
            <li><?= $svc['serviceName'] ?> (₱<?= $svc['price'] ?> x <?= $svc['quantity'] ?>)</li>
        <?php endforeach; ?>
    </ul>
<?php else: ?>
    <p>No services linked to this appointment.</p>
<?php endif; ?>

<?php if ($appointment['status'] === 'Canceled' && isset($appointment['cancellationReason'])): ?>
    <p><strong>Cancellation Reason:</strong> <?= $appointment['cancellationReason'] ?></p>
<?php endif; ?>

<a href="my_appointments.php">← Back to Appointments</a>

<footer class="app-footer">
    <div class="footer-content">
        <p class="copyright">&copy; <?php echo date("Y"); ?> Doval Santos Diagnostic Center</p>
    </div>
</footer>

</body>
</html>
