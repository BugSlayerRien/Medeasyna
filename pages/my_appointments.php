<?php //PATIENT
session_start();
require 'config.php';

// Check if the user is logged in and is a patient
if (!isset($_SESSION['userID']) || $_SESSION['role'] !== 'Patient') {
    header("Location: signin.php?error=access_denied");
    exit();
}

// Ensure patientID is set in session
if (!isset($_SESSION['patientID'])) {
    $userID = $_SESSION['userID'];

    $stmt = $conn->prepare("SELECT patientID FROM Patient WHERE patientID = ?");
    $stmt->bind_param("i", $userID);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($row = $result->fetch_assoc()) {
        $_SESSION['patientID'] = $row['patientID'];
    } else {
        die("Patient record not found.");
    }
}

$patientID = $_SESSION['patientID'];

// Handle cancel appointment with reason
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['cancel_reason'])) {
    $appointmentID = intval($_POST['appointmentID']);
    $reason = trim($_POST['cancel_reason']);
    
    if (empty($reason)) {
        header("Location: my_appointments.php?error=Please+provide+a+cancellation+reason");
        exit();
    }
    
    // Update appointment status
    $stmt = $conn->prepare("UPDATE Appointment SET status = 'Cancelled', updatedAt = NOW(), cancellationReason = ? WHERE appointmentID = ? AND patientID = ?");
    $stmt->bind_param("sii", $reason, $appointmentID, $patientID);
    
    if ($stmt->execute()) {
        // Log to audit log
        $action = "Patient cancelled appointment #$appointmentID. Reason: $reason";
        $audit = $conn->prepare("INSERT INTO audit_log (userID, action, timestamp) VALUES (?, ?, NOW())");
        $audit->bind_param("is", $_SESSION['userID'], $action);
        $audit->execute();
        
        header("Location: my_appointments.php?message=Appointment+cancelled+successfully");
        exit();
    } else {
        header("Location: my_appointments.php?error=Failed+to+cancel+appointment");
        exit();
    }
}

// Handle remove appointment
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['remove_appointment'])) {
    $appointmentID = intval($_POST['appointmentID']);
    
    // Soft delete the appointment (mark as deleted)
    $stmt = $conn->prepare("UPDATE Appointment SET deletedAt = NOW() WHERE appointmentID = ? AND patientID = ?");
    $stmt->bind_param("ii", $appointmentID, $patientID);
    
    if ($stmt->execute()) {
        // Log to audit log
        $action = "Patient removed appointment #$appointmentID from view";
        $audit = $conn->prepare("INSERT INTO audit_log (userID, action, timestamp) VALUES (?, ?, NOW())");
        $audit->bind_param("is", $_SESSION['userID'], $action);
        $audit->execute();
        
        header("Location: my_appointments.php?message=Appointment+removed+successfully");
        exit();
    } else {
        header("Location: my_appointments.php?error=Failed+to+remove+appointment");
        exit();
    }
}

// Fetch all appointments for this patient
$sql = "
    SELECT 
        a.appointmentID,
        a.appointmentDate,
        a.appointmentTime,
        a.status,
        a.reasonForVisit,
        a.cancellationReason,
        d.specialization,
        u.firstName AS doctorFirstName,
        u.lastName AS doctorLastName
    FROM Appointment a
    JOIN Doctor d ON a.doctorID = d.doctorID
    JOIN User u ON d.doctorID = u.userID
    WHERE a.patientID = ? AND a.deletedAt IS NULL
    ORDER BY a.appointmentDate DESC, a.appointmentTime DESC
";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $patientID);
$stmt->execute();
$result = $stmt->get_result();
?>

<!DOCTYPE html>
<html>
<head>
    <title>My Appointments</title>
    <link rel="stylesheet" href="../assets/css/main.css">
    <link rel="stylesheet" href="../assets/css/my_appointments.css">
    <style>
        .modal {
            display: none;
            position: fixed;
            z-index: 100;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0,0,0,0.5);
        }
        
        .modal-content {
            background-color: white;
            margin: 15% auto;
            padding: 20px;
            border-radius: 8px;
            width: 80%;
            max-width: 500px;
            animation: modalFadeIn 0.3s;
        }
        
        @keyframes modalFadeIn {
            from { opacity: 0; transform: translateY(-50px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        .modal-actions {
            display: flex;
            justify-content: flex-end;
            gap: 10px;
            margin-top: 20px;
        }
        
        .cancel-reason {
            width: 100%;
            padding: 10px;
            margin-top: 10px;
            border: 1px solid #ddd;
            border-radius: 4px;
            resize: vertical;
            min-height: 100px;
        }
    </style>
</head>
<body>

<div class="navbar">
    <h1>MedEasyna</h1>
    <div>
        <a href="patient_dashboard.php">Dashboard</a>
        <a href="book_appointment.php">Schedule Appointment</a>
        <a href="my_appointments.php">My Appointments</a>
        <a href="medical_records.php">Medical Records</a>
        <a href="logout.php">Sign Out</a>
    </div>
</div>

<h2>My Appointments</h2>

<?php if (isset($_GET['message'])): ?>
    <div class="alert success"><?php echo htmlspecialchars($_GET['message']); ?></div>
<?php elseif (isset($_GET['error'])): ?>
    <div class="alert error"><?php echo htmlspecialchars($_GET['error']); ?></div>
<?php endif; ?>

<table>
    <thead>
    <tr>
        <th>Date</th>
        <th>Time</th>
        <th>Doctor</th>
        <th>Specialization</th>
        <th>Status</th>
        <th>Reason</th>
        <th>Actions</th>
    </tr>
    </thead>
    <tbody>
    <?php if ($result->num_rows > 0): ?>
        <?php while ($row = $result->fetch_assoc()): ?>
            <tr>
                <td><?= htmlspecialchars($row['appointmentDate']) ?></td>
                <td><?= htmlspecialchars($row['appointmentTime']) ?></td>
                <td>Dr. <?= htmlspecialchars($row['doctorFirstName'] . ' ' . $row['doctorLastName']) ?></td>
                <td><?= htmlspecialchars($row['specialization']) ?></td>
                <td class="status-<?= strtolower($row['status']) ?>">
                    <?= htmlspecialchars($row['status']) ?>
                    <?php if ($row['status'] === 'Cancelled' && !empty($row['cancellation_reason'])): ?>
                        <div class="cancel-reason">Reason: <?= htmlspecialchars($row['cancellation_reason']) ?></div>
                    <?php endif; ?>
                </td>
                <td><?= htmlspecialchars($row['reasonForVisit']) ?></td>
                <td class="actions">
                    <a class="action-btn view" href="detailed_appointments.php?id=<?= $row['appointmentID'] ?>">View</a>
                    
                    <?php if ($row['status'] === 'Scheduled'): ?>
                        <button class="action-btn cancel" onclick="showCancelModal(<?= $row['appointmentID'] ?>)">Cancel</button>
                    <?php elseif (in_array($row['status'], ['Cancelled', 'Completed'])): ?>
                        <form action="my_appointments.php" method="POST" class="action-form">
                            <input type="hidden" name="appointmentID" value="<?= $row['appointmentID'] ?>">
                            <input type="hidden" name="remove_appointment" value="1">
                            <button type="submit" class="action-btn remove" onclick="return confirm('Remove this appointment from your list?')">Remove</button>
                        </form>
                    <?php endif; ?>
                </td>
            </tr>
        <?php endwhile; ?>
    <?php else: ?>
        <tr><td colspan="7" class="no-appointments">You have no appointments.</td></tr>
    <?php endif; ?>
    </tbody>
</table>

<!-- Cancel Appointment Modal -->
<div id="cancelModal" class="modal">
    <div class="modal-content">
        <h3>Cancel Appointment</h3>
        <p>Please provide a reason for cancellation:</p>
        <form action="my_appointments.php" method="POST">
            <input type="hidden" name="appointmentID" id="modalAppointmentID">
            <textarea name="cancel_reason" class="cancel-reason" required placeholder="Enter your reason for cancellation..."></textarea>
            <div class="modal-actions">
                <button type="button" class="action-btn" onclick="document.getElementById('cancelModal').style.display='none'">Close</button>
                <button type="submit" class="action-btn cancel">Confirm Cancellation</button>
            </div>
        </form>
    </div>
</div>

<footer class="app-footer">
    <div class="footer-content">
        <p class="copyright">&copy; <?php echo date("Y"); ?> MedEasyna</p>
    </div>
</footer>

<script>
    function showCancelModal(appointmentID) {
        document.getElementById('modalAppointmentID').value = appointmentID;
        document.getElementById('cancelModal').style.display = 'block';
    }
    
    // Close modal when clicking outside
    window.onclick = function(event) {
        if (event.target == document.getElementById('cancelModal')) {
            document.getElementById('cancelModal').style.display = 'none';
        }
    }
</script>

</body>
</html>