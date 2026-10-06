<?php
session_start();
require 'config.php';

// Verify nurse session
if (!isset($_SESSION['userID']) || 
    !isset($_SESSION['role']) || 
    $_SESSION['role'] !== 'Employee' ||
    !isset($_SESSION['employeeRole']) || 
    $_SESSION['employeeRole'] !== 'Nurse') {
    
    header('Location: signin.php?error=access_denied');
    exit;
}

// Get current week's start (Monday) and end (Sunday) dates
$currentWeekStart = date('Y-m-d', strtotime('monday this week'));
$currentWeekEnd = date('Y-m-d', strtotime('sunday this week'));

// Fetch this week's appointments
$query = "
    SELECT 
        a.appointmentID, 
        a.appointmentDate, 
        a.appointmentTime, 
        up.firstName AS patientFirstName, 
        up.lastName AS patientLastName, 
        ud.firstName AS doctorFirstName, 
        ud.lastName AS doctorLastName, 
        a.status
    FROM 
        Appointment a
    JOIN 
        Patient p ON a.patientID = p.patientID
    JOIN 
        User up ON p.patientID = up.userID
    JOIN 
        Doctor d ON a.doctorID = d.doctorID
    JOIN 
        User ud ON d.doctorID = ud.userID
    WHERE 
        a.appointmentDate BETWEEN ? AND ?
        AND a.status IN ('Scheduled', 'Checked-in')
    ORDER BY 
        a.appointmentDate ASC,
        a.appointmentTime ASC;
";

$stmt = $conn->prepare($query);
$stmt->bind_param("ss", $currentWeekStart, $currentWeekEnd);
$stmt->execute();
$result = $stmt->get_result();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>This Week's Appointments | Nurse Dashboard</title>
    <link rel="stylesheet" href="../assets/css/main.css">
    <link rel="stylesheet" href="../assets/css/today_appointments.css">

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

    <main>
        <h2>This Week's Appointments</h2>
        <div class="week-range">
            Week of <?php echo date('F j', strtotime($currentWeekStart)) . ' to ' . date('F j, Y', strtotime($currentWeekEnd)); ?>
        </div>
        
        <?php if ($result && $result->num_rows > 0): ?>
            <?php
            $currentDate = '';
            while ($row = $result->fetch_assoc()): 
                $appointmentDate = $row['appointmentDate'];
                $isToday = ($appointmentDate == date('Y-m-d'));
                
                if ($appointmentDate != $currentDate):
                    $currentDate = $appointmentDate;
            ?>
                <div class="date-header <?php echo $isToday ? 'today' : ''; ?>">
                    <?php echo date('l, F j', strtotime($appointmentDate)); ?>
                    <?php if ($isToday) echo '<small>(Today)</small>'; ?>
                </div>
                <table>
                    <thead>
                        <tr>
                            <th>Patient Name</th>
                            <th>Doctor</th>
                            <th>Time</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
            <?php endif; ?>
                        <tr>
                            <td><?php echo htmlspecialchars($row['patientFirstName'] . ' ' . $row['patientLastName']); ?></td>
                            <td><?php echo htmlspecialchars($row['doctorFirstName'] . ' ' . $row['doctorLastName']); ?></td>
                            <td><?php echo date('g:i A', strtotime($row['appointmentTime'])); ?></td>
                            <td><?php echo htmlspecialchars($row['status']); ?></td>
                            <td class="actions">
                                <a href="update_vitals.php?appointmentID=<?php echo $row['appointmentID']; ?>" class="btn">Record Vitals</a>
                                <?php if ($row['status'] == 'Scheduled'): ?>
                                    <a href="mark_ready.php?appointmentID=<?php echo $row['appointmentID']; ?>" class="btn">Mark Ready</a>
                                <?php endif; ?>
                            </td>
                        </tr>
            <?php 
                // Check if next row is a different date or last row
                $nextRow = $result->fetch_assoc();
                if (($nextRow && $nextRow['appointmentDate'] != $currentDate) || !$nextRow):
                    // If we peeked at the next row, put it back
                    if ($nextRow) $result->data_seek($result->num_rows - 1);
            ?>
                    </tbody>
                </table>
            <?php 
                else:
                    // Put the row back if it's the same date
                    $result->data_seek($result->num_rows - 1);
                endif;
            endwhile; 
            ?>
        <?php else: ?>
            <p>No appointments scheduled for this week.</p>
        <?php endif; ?>

        
    </main>

    <footer class="app-footer">
    <div class="footer-content">
        <p class="copyright">&copy; <?php echo date("Y"); ?> MedEasyna</p>
    </div>
</footer>

</body>
</html>