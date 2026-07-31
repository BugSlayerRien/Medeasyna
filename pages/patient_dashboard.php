<?php
session_start();
if (!isset($_SESSION['userID']) || $_SESSION['role'] !== 'Patient') {
    header("Location: index.php"); 
    exit();
}
require_once 'config.php';

$firstName = $_SESSION["firstName"];
$patientID = $_SESSION['userID'];

// Fetch upcoming appointments
$upcomingAppointments = [];
$appointmentSql = "SELECT a.appointmentID, a.appointmentDate, a.appointmentTime, 
                          u.firstName AS docFirstName, u.lastName AS docLastName, d.specialization
                   FROM Appointment a
                   JOIN User u ON a.doctorID = u.userID
                   JOIN Doctor d ON a.doctorID = d.doctorID
                   WHERE a.patientID = ? 
                   AND a.appointmentDate >= CURDATE()
                   AND a.status = 'Scheduled'
                   ORDER BY a.appointmentDate ASC, a.appointmentTime ASC
                   LIMIT 3";
$stmt = $conn->prepare($appointmentSql);
$stmt->bind_param("i", $patientID);
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    $upcomingAppointments[] = $row;
}
$stmt->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Patient Dashboard</title>
    <link rel="stylesheet" href="../assets/css/main.css">

</head>
<body>

<div class="navbar">
    <h1>Doval Santos Diagnostic Center</h1>
    <div>
        <a href="patient_dashboard.php">Dashboard</a>
        <a href="book_appointment.php">Schedule Appointment</a>
        <a href="my_appointments.php">My Appointments</a>
        <a href="medical_records.php">Medical Records</a>
        <a href="logout.php">Sign Out</a>
    </div>
</div>

<!-- Main Dashboard Content -->
<div class="dashboard-content">
    <h2>Welcome, <?php echo htmlspecialchars($firstName); ?></h2>
    
    <!-- My Appointments Box -->
    <section class="dashboard-section my-appointments-box">
        <h3>My Appointments</h3>
        <p>View and manage all your scheduled appointments in one place.</p>
        <a href="my_appointments.php" class="button primary">View All Appointments</a>
    </section>
    
    <!-- Upcoming Appointments -->
    <section class="dashboard-section">
        <h3>Upcoming Appointments</h3>
        
        <?php if (count($upcomingAppointments) > 0): ?>
            <?php foreach ($upcomingAppointments as $appt): ?>
                <div class="appointment-card">
                    <div><strong>Dr. <?php echo htmlspecialchars($appt['docFirstName'] . ' ' . $appt['docLastName']); ?></strong></div>
                    <div class="appointment-meta">
                        <span class="appointment-date">
                            <?php echo htmlspecialchars(date('M j, Y', strtotime($appt['appointmentDate']))); ?>
                        </span>
                        <span class="appointment-time">
                            <?php echo htmlspecialchars(date('g:i A', strtotime($appt['appointmentTime']))); ?>
                        </span>
                    </div>
                    <div>
                        <a href="my_appointments.php?id=<?php echo $appt['appointmentID']; ?>" class="button">Details</a>
                        <a href="cancel_appointments.php?id=<?php echo $appt['appointmentID']; ?>" class="button cancel">Cancel</a>
                    </div>
                </div>
            <?php endforeach; ?>
            <div class="view-all">
                <a href="book_appointment.php" class="button primary">Schedule New</a>
                <a href="my_appointments.php" class="button">View All</a>
            </div>
        <?php else: ?>
            <div class="no-appointments">
                <p>No upcoming appointments</p>
                <a href="book_appointment.php" class="button primary">Schedule Now</a>
            </div>
        <?php endif; ?>
    </section>

    <!-- Medical Records -->
    <section class="dashboard-section">
    <h3>Notifications</h3>
    <?php
    // Get the most recent notification
    $sql = "SELECT notificationID, message, sentDate FROM Notification WHERE userID = ? ORDER BY sentDate DESC LIMIT 1";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $_SESSION['userID']);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        $row = $result->fetch_assoc();
        echo '<div class="notification-preview" onclick="toggleNotifications()">';
        echo '<div class="notification-content">';
        echo '<p>' . htmlspecialchars($row['message']) . '</p>';
        echo '<small>' . htmlspecialchars($row['sentDate']) . '</small>';
        echo '</div>';
        echo '<span class="notification-toggle">▼</span>';
        echo '</div>';
        
        // Hidden container for all notifications
        echo '<div class="all-notifications" id="allNotifications">';
        
        // Get all notifications
        $allSql = "SELECT notificationID, message, sentDate FROM Notification WHERE userID = ? ORDER BY sentDate DESC";
        $allStmt = $conn->prepare($allSql);
        $allStmt->bind_param("i", $_SESSION['userID']);
        $allStmt->execute();
        $allResult = $allStmt->get_result();

        if ($allResult->num_rows > 0) {
            while ($notification = $allResult->fetch_assoc()) {
                echo '<div class="notification-item">';
                echo '<div class="notification-content">';
                echo '<p>' . htmlspecialchars($notification['message']) . '</p>';
                echo '<small>' . htmlspecialchars($notification['sentDate']) . '</small>';
                echo '</div>';
                echo '<form method="post" action="delete_notification.php" class="delete-notification-form">';
                echo '<input type="hidden" name="notificationID" value="' . $notification['notificationID'] . '">';
                echo '<button type="submit" class="delete-notification-btn" title="Delete notification">';
                echo '✕';
                echo '</button>';
                echo '</form>';
                echo '</div>';
            }
        } else {
            echo '<p>No notifications found</p>';
        }
        
        echo '</div>'; // Close all-notifications div
    } else {
        echo '<p>No new notifications</p>';
    }
    ?>
</section>

<script>
function toggleNotifications() {
    const allNotifications = document.getElementById('allNotifications');
    const toggleIcon = document.querySelector('.notification-toggle');
    
    if (allNotifications.style.display === 'block') {
        allNotifications.style.display = 'none';
        toggleIcon.textContent = '▼';
    } else {
        allNotifications.style.display = 'block';
        toggleIcon.textContent = '▲';
    }
}
</script>
</div>

<footer class="app-footer">
    <div class="footer-content">
        <p class="copyright">&copy; <?php echo date("Y"); ?> Doval Santos Diagnostic Center</p>
    </div>
</footer>

</body>
</html>