<?php
// Enable full error reporting at the VERY TOP
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();
include 'config.php';

// Verify database connection
if (!isset($conn) || $conn->connect_error) {
    die("Database connection failed: " . (isset($conn) ? $conn->connect_error : "Connection not established"));
}

// Verify doctor is logged in
if (!isset($_SESSION['userID']) || $_SESSION['role'] !== 'Doctor') {
    header("Location: index.php");
    exit();
}

$doctorID = $_SESSION['userID'];

// Function to log actions to audit_log
function logAction($conn, $doctorID, $appointmentID, $action, $details = null) {
    try {
        $actionType = '';
        switch ($action) {
            case 'complete': $actionType = 'completed appointment'; break;
            case 'cancel': $actionType = 'cancelled appointment'; break;
            case 'remove': $actionType = 'removed appointment'; break;
            case 'delete': $actionType = 'deleted appointment'; break;
        }
        
        $stmt = $conn->prepare("INSERT INTO audit_log (userID, action, entityType, entityID, details, timestamp) 
                               VALUES (?, ?, 'appointment', ?, ?, NOW())");
        if (!$stmt) {
            throw new Exception("Prepare failed: " . $conn->error);
        }
        
        $stmt->bind_param("isis", $doctorID, $actionType, $appointmentID, $details);
        if (!$stmt->execute()) {
            throw new Exception("Execute failed: " . $stmt->error);
        }
        
        return true;
    } catch (Exception $e) {
        error_log("Audit log error: " . $e->getMessage());
        return false;
    }
}

// Handle appointment actions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    try {
        if (!isset($_POST['appointmentID'])) {
            throw new Exception("Appointment ID not provided");
        }
        
        $appointmentID = (int)$_POST['appointmentID'];
        $details = null;
        
        switch ($_POST['action']) {
            case 'complete':
                $status = 'Completed';
                $details = 'Appointment marked as completed';
                break;
                
            case 'cancel':
                if (empty($_POST['cancellation_reason'])) {
                    $_SESSION['error'] = "Please provide a cancellation reason";
                    header("Location: doctor_appointments.php");
                    exit();
                }
                $status = 'Cancelled';
                $details = 'Cancellation reason: ' . $_POST['cancellation_reason'];
                break;
                
            case 'remove':
                $status = 'Removed';
                $details = 'Appointment removed from schedule';
                break;
                
            case 'delete':
                // First delete related records in appointmentservice
                $deleteServiceSql = "DELETE FROM appointmentservice WHERE appointmentID = ?";
                $stmt = $conn->prepare($deleteServiceSql);
                if (!$stmt) {
                    throw new Exception("Prepare failed: " . $conn->error);
                }
                $stmt->bind_param("i", $appointmentID);
                if (!$stmt->execute()) {
                    throw new Exception("Execute failed: " . $stmt->error);
                }
                $stmt->close();
                
                // Then delete the appointment
                $deleteAppointmentSql = "DELETE FROM Appointment WHERE appointmentID = ? AND doctorID = ?";
                $stmt = $conn->prepare($deleteAppointmentSql);
                if (!$stmt) {
                    throw new Exception("Prepare failed: " . $conn->error);
                }
                $stmt->bind_param("ii", $appointmentID, $doctorID);
                if (!$stmt->execute()) {
                    throw new Exception("Execute failed: " . $stmt->error);
                }
                $stmt->close();
                
                // Log the deletion
                logAction($conn, $doctorID, $appointmentID, 'delete', 'Appointment permanently deleted');
                
                $_SESSION['success'] = "Appointment deleted successfully.";
                header("Location: doctor_appointments.php");
                exit();
                
            default:
                $status = null;
        }
        
        if ($status) {
            $updateSql = "UPDATE Appointment SET status = ?";
            // Add cancellation reason if cancelling
            if ($_POST['action'] === 'cancel') {
                $updateSql .= ", cancellationReason = ?";
            }
            $updateSql .= " WHERE appointmentID = ? AND doctorID = ?";
            
            $stmt = $conn->prepare($updateSql);
            if (!$stmt) {
                throw new Exception("Prepare failed: " . $conn->error);
            }
            
            if ($_POST['action'] === 'cancel') {
                $stmt->bind_param("ssii", $status, $_POST['cancellation_reason'], $appointmentID, $doctorID);
            } else {
                $stmt->bind_param("sii", $status, $appointmentID, $doctorID);
            }
            
            if (!$stmt->execute()) {
                throw new Exception("Execute failed: " . $stmt->error);
            }
            $stmt->close();
            
            // Log the action
            logAction($conn, $doctorID, $appointmentID, $_POST['action'], $details);
            
            $_SESSION['success'] = "Appointment " . strtolower($status) . " successfully.";
            header("Location: doctor_appointments.php");
            exit();
        }
    } catch (Exception $e) {
        $_SESSION['error'] = "Error: " . $e->getMessage();
        header("Location: doctor_appointments.php");
        exit();
    }
}

// Fetch appointments with error handling
try {
    $sql = "SELECT 
                a.appointmentID,
                DATE_FORMAT(a.appointmentDate, '%M %e, %Y') as formattedDate,
                DATE_FORMAT(a.appointmentTime, '%h:%i %p') as formattedTime,
                a.reasonForVisit,
                a.status,
                a.cancellationReason,
                u.firstName,
                u.lastName
            FROM Appointment a
            JOIN User u ON a.patientID = u.userID
            WHERE a.doctorID = ?
            ORDER BY a.appointmentDate DESC, a.appointmentTime DESC";
    
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        throw new Exception("Prepare failed: " . $conn->error);
    }
    
    if (!$stmt->bind_param("i", $doctorID)) {
        throw new Exception("Bind failed: " . $stmt->error);
    }
    
    if (!$stmt->execute()) {
        throw new Exception("Execute failed: " . $stmt->error);
    }
    
    $result = $stmt->get_result();
    if (!$result) {
        throw new Exception("Get result failed: " . $stmt->error);
    }
    
    $appointments = $result->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    
} catch (Exception $e) {
    die("Database error: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Doctor Appointments | Doval Santos Diagnostic Center</title>
    <link rel="stylesheet" href="../assets/css/main.css">
    <link rel="stylesheet" href="../assets/css/doctor_appointments.css">
    <style>
        /* Error/Success Messages */
        .alert {
            padding: 15px;
            margin: 15px 0;
            border-radius: 4px;
        }
        .alert-success {
            background-color: #dff0d8;
            color: #3c763d;
            border: 1px solid #d6e9c6;
        }
        .alert-error {
            background-color: #f2dede;
            color: #a94442;
            border: 1px solid #ebccd1;
        }
        
        /* Status Indicators */
        .status-scheduled { color: #337ab7; font-weight: bold; }
        .status-completed { color: #5cb85c; font-weight: bold; }
        .status-cancelled { color: #d9534f; }
        .status-removed { color: #f0ad4e; }
        
        /* Action Buttons */
        .action-button {
            padding: 5px 10px;
            margin: 2px;
            border: none;
            border-radius: 3px;
            cursor: pointer;
            color: white;
        }
        .complete-btn { background-color: #5cb85c; }
        .cancel-btn { background-color: #d9534f; }
        .remove-btn { background-color: #f0ad4e; }
        .delete-btn { background-color: #333; }
        
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
            background-color: #fefefe;
            margin: 10% auto;
            padding: 20px;
            border: 1px solid #888;
            width: 50%;
            max-width: 500px;
            border-radius: 5px;
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
        }
        .close {
            color: #aaa;
            float: right;
            font-size: 28px;
            font-weight: bold;
            cursor: pointer;
        }
        .close:hover { color: #333; }
        
        /* Form Elements */
        .form-group {
            margin-bottom: 15px;
        }
        textarea {
            width: 100%;
            padding: 8px;
            border: 1px solid #ddd;
            border-radius: 4px;
            resize: vertical;
            min-height: 100px;
        }
        
        /* Table Styles */
        table {
            width: 100%;
            border-collapse: collapse;
            margin: 20px 0;
        }
        th, td {
            padding: 12px 15px;
            text-align: left;
            border-bottom: 1px solid #ddd;
        }
        th {
            background-color: #f8f9fa;
            font-weight: bold;
        }
        tr:hover {
            background-color: #f5f5f5;
        }
    </style>
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
    <h2>Appointments</h2>
    
    <?php if (isset($_SESSION['success'])): ?>
        <div class="alert alert-success">
            <?= htmlspecialchars($_SESSION['success']); unset($_SESSION['success']); ?>
        </div>
    <?php endif; ?>
    
    <?php if (isset($_SESSION['error'])): ?>
        <div class="alert alert-error">
            <?= htmlspecialchars($_SESSION['error']); unset($_SESSION['error']); ?>
        </div>
    <?php endif; ?>
    
    <?php if (empty($appointments)): ?>
        <p>No appointments found.</p>
    <?php else: ?>
        <table>
            <thead>
                <tr>
                    <th>Patient</th>
                    <th>Date</th>
                    <th>Time</th>
                    <th>Reason</th>
                    <th>Status</th>
                    <th>Actions</th>
                    <th>Delete</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($appointments as $appt): ?>
                    <tr>
                        <td><?= htmlspecialchars($appt['firstName'] . ' ' . $appt['lastName']) ?></td>
                        <td><?= htmlspecialchars($appt['formattedDate']) ?></td>
                        <td><?= htmlspecialchars($appt['formattedTime']) ?></td>
                        <td><?= htmlspecialchars($appt['reasonForVisit']) ?></td>
                        <td class="status-<?= strtolower($appt['status']) ?>">
                            <?= htmlspecialchars($appt['status']) ?>
                            <?php if ($appt['status'] === 'Cancelled' && !empty($appt['cancellationReason'])): ?>
                                <br><small><?= htmlspecialchars($appt['cancellationReason']) ?></small>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ($appt['status'] === 'Scheduled'): ?>
                                <form method="post" class="action-form">
                                    <input type="hidden" name="appointmentID" value="<?= $appt['appointmentID'] ?>">
                                    <button type="submit" name="action" value="complete" class="action-button complete-btn">Complete</button>
                                    <button type="button" onclick="showCancelModal(<?= $appt['appointmentID'] ?>)" class="action-button cancel-btn">Cancel</button>
                                    <button type="submit" name="action" value="remove" class="action-button remove-btn">Remove</button>
                                </form>
                            <?php else: ?>
                                <span class="no-actions">No actions available</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <form method="post" class="action-form" onsubmit="return confirm('Are you sure you want to permanently delete this appointment?');">
                                <input type="hidden" name="appointmentID" value="<?= $appt['appointmentID'] ?>">
                                <button type="submit" name="action" value="delete" class="action-button delete-btn">Delete</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</main>

<!-- Cancel Appointment Modal -->
<div id="cancelModal" class="modal">
    <div class="modal-content">
        <span class="close">&times;</span>
        <h3>Cancel Appointment</h3>
        <form id="cancelForm" method="post">
            <input type="hidden" name="appointmentID" id="modalAppointmentID">
            <input type="hidden" name="action" value="cancel">
            <div class="form-group">
                <label for="cancellation_reason">Reason for Cancellation (required):</label>
                <textarea name="cancellation_reason" id="cancellation_reason" rows="4" required></textarea>
            </div>
            <button type="submit" class="action-button cancel-btn">Confirm Cancellation</button>
        </form>
    </div>
</div>

<script>
// Modal handling for cancellation
const modal = document.getElementById("cancelModal");
const span = document.getElementsByClassName("close")[0];

function showCancelModal(appointmentID) {
    document.getElementById("modalAppointmentID").value = appointmentID;
    modal.style.display = "block";
}

// When user clicks on (x), close modal
span.onclick = function() {
    modal.style.display = "none";
}

// When user clicks anywhere outside modal, close it
window.onclick = function(event) {
    if (event.target == modal) {
        modal.style.display = "none";
    }
}
</script>

<footer class="app-footer">
    <div class="footer-content">
        <p class="copyright">&copy; <?php echo date("Y"); ?> Doval Santos Diagnostic Center. All rights reserved.</p>
    </div>
</footer>

</body>
</html>