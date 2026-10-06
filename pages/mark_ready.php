<?php
session_start();
require 'config.php';

// Ensure only logged-in nurse can access
if (!isset($_SESSION['userID']) || 
    $_SESSION['role'] !== 'Employee' || 
    $_SESSION['employeeRole'] !== 'Nurse') {
    
    header('Location: signin.php?error=access_denied');
    exit;
}

// Get current week start and end dates
$monday = date('Y-m-d', strtotime('monday this week'));
$sunday = date('Y-m-d', strtotime('sunday this week'));

// Fetch this week's scheduled appointments
$stmt = $conn->prepare("
    SELECT 
        a.appointmentID,
        a.appointmentDate,
        a.appointmentTime,
        a.status,
        up.firstName AS patientFirstName,
        up.lastName AS patientLastName,
        ud.firstName AS doctorFirstName,
        ud.lastName AS doctorLastName
    FROM Appointment a
    JOIN Patient p ON a.patientID = p.patientID
    JOIN User up ON p.patientID = up.userID
    JOIN Doctor d ON a.doctorID = d.doctorID
    JOIN User ud ON d.doctorID = ud.userID
    WHERE a.appointmentDate BETWEEN ? AND ?
    AND a.status IN ('Scheduled', 'Checked-in')
    ORDER BY a.appointmentDate ASC, a.appointmentTime ASC
");
$stmt->bind_param("ss", $monday, $sunday);
$stmt->execute();
$result = $stmt->get_result();

// Handle mark ready form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['appointmentID'])) {
    $appointmentID = intval($_POST['appointmentID']);

    // Confirm appointment is valid and still scheduled
    $check = $conn->prepare("
        SELECT appointmentID FROM Appointment 
        WHERE appointmentID = ? 
        AND appointmentDate BETWEEN ? AND ?
        AND status = 'Scheduled'
    ");
    $check->bind_param("iss", $appointmentID, $monday, $sunday);
    $check->execute();
    $checkResult = $check->get_result();

    if ($checkResult && $checkResult->num_rows > 0) {
        $update = $conn->prepare("
            UPDATE Appointment 
            SET status = 'Checked-in', updatedAt = NOW() 
            WHERE appointmentID = ?
        ");
        $update->bind_param("i", $appointmentID);
        if ($update->execute()) {
            $_SESSION['success_message'] = "Patient successfully marked as ready!";
            header("Location: mark_ready.php");
            exit();
        } else {
            $_SESSION['error_message'] = "Failed to update appointment.";
        }
    } else {
        $_SESSION['error_message'] = "Invalid or already updated appointment.";
    }
    header("Location: mark_ready.php");
    exit();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Mark Patients as Ready | Nurse</title>
    <link rel="stylesheet" href="../assets/css/main.css">
    <link rel="stylesheet" href="../assets/css/mark_ready.css">
    <style>
        /* Notification styles */
        .notification {
            position: fixed;
            top: 20px;
            right: 20px;
            padding: 15px 25px;
            border-radius: 5px;
            color: white;
            font-weight: bold;
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
            z-index: 1000;
            animation: slideIn 0.5s, fadeOut 0.5s 2.5s forwards;
        }
        
        .success-notification {
            background-color: #27ae60;
        }
        
        .error-notification {
            background-color: #e74c3c;
        }
        
        @keyframes slideIn {
            from { transform: translateX(100%); opacity: 0; }
            to { transform: translateX(0); opacity: 1; }
        }
        
        @keyframes fadeOut {
            from { opacity: 1; }
            to { opacity: 0; }
        }
        
        /* Rest of your existing styles */
        .week-nav {
            display: flex;
            justify-content: space-between;
            margin-bottom: 20px;
            align-items: center;
        }
        .week-range {
            font-weight: bold;
            color: #2c3e50;
        }
        .day-section {
            margin-bottom: 30px;
        }
        .day-header {
            background-color: #3498db;
            color: white;
            padding: 10px 15px;
            border-radius: 5px 5px 0 0;
            margin-bottom: 0;
        }
        .status-scheduled {
            color: #e67e22;
            font-weight: bold;
        }
        .status-checked-in {
            color: #27ae60;
            font-weight: bold;
        }
        .no-appointments {
            padding: 15px;
            background-color: #f8f9fa;
            border: 1px solid #e0e0e0;
            border-radius: 0 0 5px 5px;
            text-align: center;
        }
        .ready-btn {
            background-color: #27ae60;
            color: white;
            border: none;
            padding: 5px 10px;
            border-radius: 3px;
            cursor: pointer;
            transition: background-color 0.2s;
        }
        .ready-btn:hover {
            background-color: #219653;
        }
        .already-ready {
            color: #27ae60;
            font-weight: bold;
        }
    </style>
</head>
<body>

<!-- Notification Container -->
<?php if (isset($_SESSION['success_message'])): ?>
    <div class="notification success-notification" id="successNotification">
        <?php echo htmlspecialchars($_SESSION['success_message']); ?>
    </div>
    <?php unset($_SESSION['success_message']); ?>
<?php elseif (isset($_SESSION['error_message'])): ?>
    <div class="notification error-notification" id="errorNotification">
        <?php echo htmlspecialchars($_SESSION['error_message']); ?>
    </div>
    <?php unset($_SESSION['error_message']); ?>
<?php endif; ?>

<div class="navbar">
    <h1>MedEasyna</h1>
    <div>
        <a href="nurse_dashboard.php">Dashboard</a>
        <a href="today_appointments.php">Today's Appointments</a>
        <a href="update_vitals.php">Record Vitals</a>
        <a href="mark_ready.php">Mark Patient as Ready</a>
        <a href="../pages/logout.php">Sign Out</a>
    </div>
</div>

<main>
    <div class="week-nav">
        <h2>This Week's Appointments</h2>
        <div class="week-range">
            <?php echo date('F j', strtotime($monday)) . ' - ' . date('F j, Y', strtotime($sunday)); ?>
        </div>
    </div>

    <?php if ($result && $result->num_rows > 0): 
        // Group appointments by day
        $appointmentsByDay = [];
        while ($row = $result->fetch_assoc()) {
            $day = $row['appointmentDate'];
            if (!isset($appointmentsByDay[$day])) {
                $appointmentsByDay[$day] = [];
            }
            $appointmentsByDay[$day][] = $row;
        }
        
        foreach ($appointmentsByDay as $date => $appointments): 
            $dayName = date('l', strtotime($date));
            $formattedDate = date('F j, Y', strtotime($date));
        ?>
            <div class="day-section">
                <h3 class="day-header"><?php echo "$dayName, $formattedDate"; ?></h3>
                <table>
                    <thead>
                        <tr>
                            <th>Time</th>
                            <th>Patient</th>
                            <th>Doctor</th>
                            <th>Status</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($appointments as $row): ?>
                            <tr>
                                <td><?php echo date('g:i A', strtotime($row['appointmentTime'])); ?></td>
                                <td><?php echo htmlspecialchars($row['patientFirstName'] . ' ' . $row['patientLastName']); ?></td>
                                <td><?php echo htmlspecialchars($row['doctorFirstName'] . ' ' . $row['doctorLastName']); ?></td>
                                <td class="status-<?php echo strtolower(str_replace(' ', '-', $row['status'])); ?>">
                                    <?php echo htmlspecialchars($row['status']); ?>
                                </td>
                                <td>
                                    <?php if ($row['status'] === 'Scheduled'): ?>
                                        <form method="POST" action="mark_ready.php">
                                            <input type="hidden" name="appointmentID" value="<?php echo $row['appointmentID']; ?>">
                                            <button type="submit" class="ready-btn">Mark as Ready</button>
                                        </form>
                                    <?php else: ?>
                                        <span class="already-ready">Ready</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endforeach; ?>
    <?php else: ?>
        <div class="no-appointments">
            <p>No appointments scheduled for this week.</p>
        </div>
    <?php endif; ?>
</main>

<footer class="app-footer">
    <div class="footer-content">
        <p class="copyright">&copy; <?php echo date("Y"); ?> MedEasyna</p>
    </div>
</footer>

<script>
// Auto-hide notifications after delay
document.addEventListener('DOMContentLoaded', function() {
    const successNotification = document.getElementById('successNotification');
    const errorNotification = document.getElementById('errorNotification');
    
    if (successNotification) {
        setTimeout(() => {
            successNotification.style.display = 'none';
        }, 3000);
    }
    
    if (errorNotification) {
        setTimeout(() => {
            errorNotification.style.display = 'none';
        }, 3000);
    }
});
</script>

</body>
</html>