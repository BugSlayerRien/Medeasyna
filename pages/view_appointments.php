<?php
session_start();
require 'config.php';

if (!isset($_SESSION['patientID']) || !isset($_GET['id'])) {
    header('Location: my_appointment.php?message=Access+Denied');
    exit;
}

$appointmentID = intval($_GET['id']);
$patientID = $_SESSION['patientID'];

// Fetch appointment detail
$sql = "
    SELECT 
        a.*,
        d.specialization,
        u.firstName AS doctorFirstName,
        u.lastName AS doctorLastName
    FROM Appointment a
    JOIN Doctor d ON a.doctorID = d.doctorID
    JOIN User u ON d.doctorID = u.userID
    WHERE a.appointmentID = ? AND a.patientID = ?
";
$stmt = $conn->prepare($sql);
$stmt->bind_param("ii", $appointmentID, $patientID);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    header("Location: my_appointments.php?message=Appointment+Not+Found");
    exit;
}

$appointment = $result->fetch_assoc();

// Fetch services associated
$servicesSql = "
    SELECT s.serviceName, s.price, aps.quantity
    FROM AppointmentService aps
    JOIN Service s ON aps.serviceID = s.serviceID
    WHERE aps.appointmentID = ?
";
$svcStmt = $conn->prepare($servicesSql);
$svcStmt->bind_param("i", $appointmentID);
$svcStmt->execute();
$services = $svcStmt->get_result();
?>

<!DOCTYPE html>
<html>
<head>
    <title>Appointment Details</title>
</head>
<body>
<h2>Appointment Details</h2>

<p><strong>Date:</strong> <?= $appointment['appointmentDate'] ?></p>
<p><strong>Time:</strong> <?= $appointment['appointmentTime'] ?></p>
<p><strong>Status:</strong> <?= $appointment['status'] ?></p>
<p><strong>Doctor:</strong> Dr. <?= $appointment['doctorFirstName'] . ' ' . $appointment['doctorLastName'] ?> (<?= $appointment['specialization'] ?>)</p>
<p><strong>Reason:</strong> <?= $appointment['reasonForVisit'] ?></p>

<h3>Services</h3>
<ul>
    <?php while ($svc = $services->fetch_assoc()): ?>
        <li><?= $svc['serviceName'] ?> (x<?= $svc['quantity'] ?>) - ₱<?= number_format($svc['price'], 2) ?></li>
    <?php endwhile; ?>
</ul>

<?php if ($appointment['status'] === 'Scheduled'): ?>
    <form action="cancel_appointment.php" method="POST">
        <input type="hidden" name="appointmentID" value="<?= $appointment['appointmentID'] ?>">
        <button type="submit" onclick="return confirm('Are you sure you want to cancel this appointment?')">Cancel Appointment</button>
    </form>
<?php endif; ?>

<p><a href="my_appointments.php">← Back to Appointments</a></p>


<footer class="app-footer">
    <div class="footer-content">
        <p class="copyright">&copy; <?php echo date("Y"); ?> MedEasyna</p>
    </div>
</footer>

</body>
</html>
