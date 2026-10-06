<?php //NURSE
session_start();

if (!isset($_SESSION['userID']) || $_SESSION['role'] !== 'Doctor') {
    header("Location: index.php"); 
    exit();
}

include 'config.php'; 
$userID = $_SESSION['userID'];
$sql = "SELECT firstName, lastName FROM User WHERE userID = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $userID);
$stmt->execute();
$stmt->store_result();
$stmt->bind_result($firstName, $lastName);

if ($stmt->num_rows > 0) {
    $stmt->fetch();
    $fullName = $firstName . ' ' . $lastName;
} else {
    $fullName = "Doctor"; 
}

$sql = "SELECT notificationID, message, sentDate 
        FROM Notification 
        WHERE userID = ? AND deletedAt IS NULL 
        ORDER BY sentDate DESC";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $_SESSION['userID']); // doctor ID
$stmt->execute();
$result = $stmt->get_result();

$stmt->close();
?>










<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Doctor Dashboard | MedEasyna</title>
    <link rel="stylesheet" href="../assets/css/main.css">
    <link rel="stylesheet" href="../assets/css/doctor_dashboard.css">

</head>
<body>

    <div class="navbar">
    <h1>MedEasyna</h1>
    <div>
        <a href="../pages/doctor_dashboard.php">Dashboard</a>
        <a href="../pages/doctor_availability.php">Availability</a>
        <a href="../pages/patient_records.php">Patient Records</a>
        <a href="../pages/medical_reports.php">Medical Reports</a>
        <a href="../pages/doctor_appointments.php">Appointments</a>
        <a href="logout.php">Sign Out</a>
    </div>
</div>


    <!-- Main Content -->
    <main class="dashboard-container">
        <section class="welcome-section">
            <h2>Welcome, Dr. <?php echo $fullName; ?>!</h2>
            <p class="subtitle">Here you can manage your appointments and patient information.</p>
        </section>

        <!-- Quick Access Cards -->
        <section class="quick-access">
    <div class="stats-grid">
        <article class="stat-card" onclick="window.location.href='doctor_availability.php'">
            <h3>Service Availability</h3>
            <div class="card-content">
                <p>Set your weekly schedule and manage appointment availability</p>
            </div>
        </article>
        
        <article class="stat-card" onclick="window.location.href='patient_records.php'">
            <h3>Patient Records</h3>
            <div class="card-content">
                <p>Access and manage patient medical histories and records</p>
            </div>
        </article>
        
        <article class="stat-card" onclick="window.location.href='medical_reports.php'">
            <h3>Medical Reports</h3>
            <div class="card-content">
                <p>Generate and review diagnostic reports and test results</p>
            </div>
        </article>

        <article class="stat-card" onclick="window.location.href='doctor_appointments.php'">
            <h3>Appointments</h3>
            <div class="card-content">
                <p>View and manage your upcoming patient appointments</p>
            </div>
        </article>
    </div>
</section>

        <!-- Notifications Section -->
        <section class="notifications-panel">
    <div class="panel-header">
        <h3>
            <span class="notification-icon">🔔</span>
            Notifications
            <button id="toggleNotifications" class="toggle-btn">▼</button>
        </h3>
    </div>
    
    <div id="notificationContainer" class="notification-list collapsed">
        <?php
        // Get all notifications
        $sql = "SELECT notificationID, message, sentDate FROM Notification WHERE userID = ? ORDER BY sentDate DESC";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $_SESSION['userID']);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0): ?>
            <?php while ($row = $result->fetch_assoc()): ?>
                <div class="notification-item" data-id="<?php echo $row['notificationID']; ?>">
                    <div class="notification-content">
                        <p><?php echo htmlspecialchars($row['message']); ?></p>
                        <small class="notification-time"><?php echo date('M j, Y g:i A', strtotime($row['sentDate'])); ?></small>
                    </div>
                    <form method="post" action="delete_notification.php" class="delete-form">
                        <input type="hidden" name="notificationID" value="<?php echo $row['notificationID']; ?>">
                        <button type="submit" class="delete-btn" aria-label="Delete notification">✕</button>
                    </form>
                </div>
            <?php endwhile; ?>
        <?php else: ?>
            <div class="empty-state">
                <p>No new notifications</p>
            </div>
        <?php endif; ?>
    </div>
</section>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const toggleBtn = document.getElementById('toggleNotifications');
    const notificationContainer = document.getElementById('notificationContainer');
    
    toggleBtn.addEventListener('click', function() {
        notificationContainer.classList.toggle('collapsed');
        notificationContainer.classList.toggle('expanded');
        toggleBtn.textContent = notificationContainer.classList.contains('collapsed') ? '▼' : '▲';
    });
    
    // Prevent form submission from triggering the toggle
    document.querySelectorAll('.delete-form').forEach(form => {
        form.addEventListener('click', function(e) {
            e.stopPropagation();
        });
    });
});
</script>

        <section class="walkin-panel">
    <div class="panel-header">
        <h3>
            <span class="walkin-icon">🚶‍♂️</span>
            Today's Walk-ins
        </h3>
    </div>

    <div class="walkin-list">
        <?php
        $today = date("Y-m-d");
        $walkinSql = "SELECT W.walkInID, W.status, W.arrivalTime, U.firstName, U.lastName 
                      FROM WalkInTracking W
                      JOIN Patient P ON W.patientID = P.patientID
                      JOIN User U ON P.patientID = U.userID
                      WHERE DATE(W.arrivalTime) = ? 
                      AND W.status IN ('Waiting', 'In Consultation')
                      ORDER BY W.arrivalTime ASC";
        $walkinStmt = $conn->prepare($walkinSql);
        $walkinStmt->bind_param("s", $today);
        $walkinStmt->execute();
        $walkinResult = $walkinStmt->get_result();

        if ($walkinResult->num_rows > 0):
            while ($row = $walkinResult->fetch_assoc()):
        ?>

        
        <div class="walkin-item">
            <div class="walkin-info">
                <strong><?php echo htmlspecialchars($row['firstName'] . ' ' . $row['lastName']); ?></strong>
                <p>Status: <span class="status-tag"><?php echo $row['status']; ?></span></p>
                <p><small>Arrived at: <?php echo date("g:i A", strtotime($row['arrivalTime'])); ?></small></p>
            </div>
        </div>
        <?php
            endwhile;
        else:
        ?>
        <div class="empty-state">
            <p>No walk-ins at the moment.</p>
        </div>
        <?php endif; ?>
    </div>
</section>
    </main>

    <!-- Footer -->
    <footer class="app-footer">
    <div class="footer-content">
        <p class="copyright">&copy; <?php echo date("Y"); ?> MedEasyna</p>
    </div>
</footer>

    <script src="../assets/js/doctor_dashboard.js"></script>
</body>
</html>