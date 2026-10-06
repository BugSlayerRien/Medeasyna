<?php
session_start();
include('config.php'); 
$firstName = $_SESSION['firstName']; 

// Handle appointment deletion/cancellation
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['deleteAppointment'])) {
        $appointmentID = intval($_POST['deleteAppointment']);
        $action = $_POST['action'] ?? 'delete'; // delete, cancel, or remove
        
        // First check if the appointment exists
        $checkSql = "SELECT * FROM Appointment WHERE appointmentID = ? AND deletedAt IS NULL";
        $stmt = $conn->prepare($checkSql);
        $stmt->bind_param("i", $appointmentID);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            $userID = $_SESSION['userID'];
            $appointment = $result->fetch_assoc();
            
            if ($action === 'cancel') {
                // Mark as cancelled
                $updateSql = "UPDATE Appointment SET status = 'Cancelled', updatedAt = NOW() WHERE appointmentID = ?";
                $desc = "Cancelled appointmentID $appointmentID";
            } else {
                // Soft delete the appointment
                $updateSql = "UPDATE Appointment SET deletedAt = NOW() WHERE appointmentID = ?";
                $desc = "Deleted appointmentID $appointmentID";
            }
            
            $stmt = $conn->prepare($updateSql);
            $stmt->bind_param("i", $appointmentID);
            $stmt->execute();
            
            // Log to audit log
            $conn->query("INSERT INTO audit_Log (userID, action, timestamp) VALUES ($userID, '$desc', NOW())");
            
            $_SESSION['success'] = "Appointment ".($action === 'cancel' ? 'cancelled' : 'deleted')." successfully.";
        } else {
            $_SESSION['error'] = "Appointment not found or already deleted.";
        }
        
        // Refresh the page
        header("Location: receptionist_dashboard.php");
        exit();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Receptionist Dashboard</title>
    <link rel="stylesheet" href="../assets/css/main.css">
    <link rel="stylesheet" href="../assets/css/receptionist_dashboard.css">
    <style>
        /* Modal Styles */
        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0,0,0,0.5);
        }
        
        .modal-content {
            background-color: #fff;
            margin: 15% auto;
            padding: 20px;
            border-radius: 8px;
            width: 400px;
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
        }
        
        .modal-actions {
            display: flex;
            justify-content: flex-end;
            margin-top: 20px;
            gap: 10px;
        }
        
        .modal-btn {
            padding: 8px 16px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
        }
        
        .modal-btn-cancel {
            background-color: #f44336;
            color: white;
        }
        
        .modal-btn-delete {
            background-color: #ff9800;
            color: white;
        }
        
        .modal-btn-close {
            background-color: #e0e0e0;
        }
        
        /* Status Badges */
        .status-scheduled { color: #2196F3; font-weight: bold; }
        .status-completed { color: #4CAF50; font-weight: bold; }
        .status-cancelled { color: #f44336; font-weight: bold; }
        .status-no-show { color: #9C27B0; font-weight: bold; }
    </style>
</head>
<body>

    <div class="navbar">
        <h1>MedEasyna</h1>
        <div>
            <a href="receptionist_dashboard.php">Dashboard</a>
            <a href="../pages/schedule_appointment.php">Schedule Appointment</a>
            <a href="../pages/walk_in.php">Walk-Ins</a>
            <a href="../pages/logout.php">Sign Out</a>
        </div>
    </div>

    <!-- Dashboard Content -->
    <div class="dashboard-content">
        <h2>Welcome, <?php echo htmlspecialchars($firstName); ?>!</h2>
        
        <!-- Display success/error messages -->
        <?php if (isset($_SESSION['success'])): ?>
            <div class="alert-success">
                <?= $_SESSION['success']; unset($_SESSION['success']); ?>
            </div>
        <?php endif; ?>
        
        <?php if (isset($_SESSION['error'])): ?>
            <div class="alert-error">
                <?= $_SESSION['error']; unset($_SESSION['error']); ?>
            </div>
        <?php endif; ?>

        <!-- Quick Actions Section -->
        <section class="quick-actions">
            <div class="card-grid">
                <article class="action-card" onclick="location.href='schedule_appointment.php'">
                    <h3>Schedule Appointment</h3>
                    <p>Book new appointments for patients.</p>
                </article>

                <article class="action-card" onclick="location.href='walk_in.php'">
                    <h3>Walk-In Registration</h3>
                    <p>Register walk-in patients without appointments.</p>
                </article>
            </div>
        </section>

        <!-- Patient Alerts Section -->
        <section class="patient-alerts">
            <h3>Patient Alerts</h3>
            <div id="patientAlerts" class="alerts-container">
                <?php
                    // Step 1: Auto-cancel missed appointments
                    $updateSql = "UPDATE Appointment 
                                SET status = 'Cancelled' 
                                WHERE status = 'Scheduled' 
                                AND STR_TO_DATE(CONCAT(appointmentDate, ' ', appointmentTime), '%Y-%m-%d %H:%i:%s') < NOW()
                                AND deletedAt IS NULL";
                    $conn->query($updateSql);

                    // Step 2: Fetch overdue appointments
                    $sql = "SELECT a.appointmentID, a.patientID, pu.firstName, pu.lastName 
                            FROM Appointment a
                            JOIN Patient p ON a.patientID = p.patientID
                            JOIN User pu ON p.patientID = pu.userID
                            WHERE a.status = 'Cancelled' 
                            AND STR_TO_DATE(CONCAT(a.appointmentDate, ' ', a.appointmentTime), '%Y-%m-%d %H:%i:%s') < NOW()
                            AND a.deletedAt IS NULL";
                    $result = $conn->query($sql);
                    
                    if ($result && $result->num_rows > 0) {
                        while ($row = $result->fetch_assoc()) {
                            echo "<div class='alert-item'>";
                            echo "No-show alert: " . htmlspecialchars($row['firstName'] . ' ' . $row['lastName']) . " missed their appointment.";
                            echo "<form method='post' style='display:inline; margin-left: 10px;'>";
                            echo "<input type='hidden' name='deleteAlert' value='" . $row['appointmentID'] . "'>";
                            echo "<button type='submit' onclick='return confirm(\"Are you sure you want to remove this alert?\");'>Dismiss</button>";
                            echo "</form>";
                            echo "</div>";
                        }
                    } else {
                        echo "<div class='alert-item'>No active alerts.</div>";
                    }
                ?>
            </div>
        </section>

        <!-- Upcoming Appointments Table -->
        <h2 class="section-title">Appointment Management</h2>
        <div class="table-container">
            <table class="table">
                <thead>
                    <tr>
                        <th>Patient</th>
                        <th>Doctor</th>
                        <th>Date</th>
                        <th>Time</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $sql = "SELECT 
                                a.appointmentID,
                                a.appointmentDate,
                                a.appointmentTime,
                                a.status,
                                CONCAT(pu.firstName, ' ', pu.lastName) AS patientName,
                                CONCAT(du.firstName, ' ', du.lastName) AS doctorName
                            FROM Appointment a
                            JOIN Patient p ON a.patientID = p.patientID
                            JOIN User pu ON p.patientID = pu.userID
                            JOIN Doctor d ON a.doctorID = d.doctorID
                            JOIN User du ON d.doctorID = du.userID
                            WHERE a.deletedAt IS NULL
                            ORDER BY a.appointmentDate, a.appointmentTime";

                    $result = $conn->query($sql);
                    
                    if ($result && $result->num_rows > 0) {
                        while ($appointment = $result->fetch_assoc()): ?>
                            <tr>
                                <td><?= htmlspecialchars($appointment['patientName']) ?></td>
                                <td><?= htmlspecialchars($appointment['doctorName']) ?></td>
                                <td><?= htmlspecialchars($appointment['appointmentDate']) ?></td>
                                <td><?= htmlspecialchars($appointment['appointmentTime']) ?></td>
                                <td class="status-<?= strtolower($appointment['status']) ?>">
                                    <?= htmlspecialchars($appointment['status']) ?>
                                </td>
                                <td class="action-buttons">
                                    <button class="btn-action btn-cancel" 
                                            onclick="showActionModal(<?= $appointment['appointmentID'] ?>, 'cancel')">
                                        Cancel
                                    </button>
                                    <button class="btn-action btn-delete" 
                                            onclick="showActionModal(<?= $appointment['appointmentID'] ?>, 'delete')">
                                        Delete
                                    </button>
                                </td>
                            </tr>
                        <?php endwhile;
                    } else {
                        echo "<tr><td colspan='6'>No appointments found</td></tr>";
                    }
                    ?>
                </tbody>
            </table>
        </div>

        <!-- Action Modal -->
        <div id="actionModal" class="modal">
            <div class="modal-content">
                <h3 id="modalTitle">Confirm Action</h3>
                <p id="modalMessage">Are you sure you want to perform this action?</p>
                <div class="modal-actions">
                    <form id="actionForm" method="post" style="display: inline;">
                        <input type="hidden" name="deleteAppointment" id="modalAppointmentID">
                        <input type="hidden" name="action" id="modalAction">
                        <button type="button" class="modal-btn modal-btn-close" onclick="closeModal()">Close</button>
                        <button type="submit" class="modal-btn" id="modalSubmitBtn">Confirm</button>
                    </form>
                </div>
            </div>
        </div>

    </div>

    <footer class="app-footer">
        <div class="footer-content">
            <p class="copyright">&copy; <?php echo date("Y"); ?> MedEasyna</p>
        </div>
    </footer>

    <script>
        // Modal functions
        function showActionModal(appointmentID, action) {
            const modal = document.getElementById('actionModal');
            const modalTitle = document.getElementById('modalTitle');
            const modalMessage = document.getElementById('modalMessage');
            const modalAppointmentID = document.getElementById('modalAppointmentID');
            const modalAction = document.getElementById('modalAction');
            const modalSubmitBtn = document.getElementById('modalSubmitBtn');
            
            modalAppointmentID.value = appointmentID;
            modalAction.value = action;
            
            if (action === 'cancel') {
                modalTitle.textContent = 'Cancel Appointment';
                modalMessage.textContent = 'Are you sure you want to cancel this appointment? This will mark it as cancelled but keep it in the system.';
                modalSubmitBtn.className = 'modal-btn modal-btn-cancel';
                modalSubmitBtn.textContent = 'Cancel Appointment';
            } else {
                modalTitle.textContent = 'Delete Appointment';
                modalMessage.textContent = 'Are you sure you want to permanently delete this appointment? This action cannot be undone.';
                modalSubmitBtn.className = 'modal-btn modal-btn-delete';
                modalSubmitBtn.textContent = 'Delete Permanently';
            }
            
            modal.style.display = 'block';
        }
        
        function closeModal() {
            document.getElementById('actionModal').style.display = 'none';
        }
        
        // Close modal when clicking outside
        window.onclick = function(event) {
            const modal = document.getElementById('actionModal');
            if (event.target === modal) {
                closeModal();
            }
        }
    </script>
</body>
</html>