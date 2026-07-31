<?php
include 'config.php';
session_start();

// Verify doctor is logged in
if (!isset($_SESSION['userID']) || $_SESSION['role'] !== 'Doctor') {
    header("Location: ../index.php");
    exit();
}

$doctorID = $_SESSION['userID'];
$error = '';
$success = '';

// Handle delete request
if (isset($_GET['delete'])) {
    $availabilityID = intval($_GET['delete']);
    $stmt = $conn->prepare("DELETE FROM DoctorWeeklyAvailability WHERE availabilityID = ? AND doctorID = ?");
    $stmt->bind_param("ii", $availabilityID, $doctorID);
    $stmt->execute();
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $days = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];
    $validationErrors = [];
    
    // Clear existing availability for this doctor
    $conn->query("DELETE FROM DoctorWeeklyAvailability WHERE doctorID = $doctorID");

    foreach ($days as $day) {
        $lower = strtolower($day);
        $enabled = isset($_POST[$lower . '_enabled']) ? 1 : 0;
        $start = $_POST[$lower . '_start'] ?? null;
        $end = $_POST[$lower . '_end'] ?? null;

        if ($enabled) {
            // Validate time inputs
            if (empty($start) || empty($end)) {
                $validationErrors[] = "$day: Both start and end times are required";
                continue;
            }

            // Convert to DateTime objects for comparison
            $startTime = DateTime::createFromFormat('H:i', $start);
            $endTime = DateTime::createFromFormat('H:i', $end);
            
            // Validate time ranges
            $minTime = DateTime::createFromFormat('H:i', '08:00');
            $maxTime = DateTime::createFromFormat('H:i', '17:00');
            
            if ($startTime < $minTime || $startTime > $maxTime) {
                $validationErrors[] = "$day: Start time must be between 8:00 AM and 5:00 PM";
            }
            
            if ($endTime < $minTime || $endTime > $maxTime) {
                $validationErrors[] = "$day: End time must be between 8:00 AM and 5:00 PM";
            }
            
            if ($startTime >= $endTime) {
                $validationErrors[] = "$day: End time must be after start time";
            }
            
            // Check for lunch break violation
            $lunchStart = DateTime::createFromFormat('H:i', '12:00');
            $lunchEnd = DateTime::createFromFormat('H:i', '13:00');
            
            if (($startTime >= $lunchStart && $startTime < $lunchEnd) || 
                ($endTime > $lunchStart && $endTime <= $lunchEnd)) {
                $validationErrors[] = "$day: Cannot set availability during lunch break (12:00 PM - 1:00 PM)";
            }

            if (empty($validationErrors)) {
                $stmt = $conn->prepare("INSERT INTO DoctorWeeklyAvailability (doctorID, weekday, startTime, endTime, isEnabled) VALUES (?, ?, ?, ?, ?)");
                $stmt->bind_param("isssi", $doctorID, $day, $start, $end, $enabled);
                $stmt->execute();
            }
        }
    }

    if (empty($validationErrors)) {
        $success = "Availability updated successfully!";
    } else {
        $error = implode("<br>", $validationErrors);
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Doctor Availability | Doval Santos Diagnostic Center</title>
    <link rel="stylesheet" href="../assets/css/main.css">
    <link rel="stylesheet" href="../assets/css/doctor_availability.css">
    <style>
        .error { color: red; margin: 10px 0; }
        .success { color: green; margin: 10px 0; }
        fieldset { margin-bottom: 15px; padding: 10px; border: 1px solid #ddd; }
        legend { font-weight: bold; }
        table { width: 100%; border-collapse: collapse; margin-top: 20px; }
        th, td { padding: 8px; text-align: left; border-bottom: 1px solid #ddd; }
        th { background-color: #f2f2f2; }
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
    <h1>Doctor Availability Management</h1>
    
    <?php if ($error): ?>
        <div class="error"><?= $error ?></div>
    <?php endif; ?>
    
    <?php if ($success): ?>
        <div class="success"><?= $success ?></div>
    <?php endif; ?>

    <h3>Set Weekly Availability</h3>
    <form method="POST" id="availabilityForm">
        <?php
        $days = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];
        
        foreach ($days as $day) {
            $lower = strtolower($day);
            echo "
            <fieldset>
                <legend>$day</legend>
                <label>
                    <input type='checkbox' name='{$lower}_enabled' checked> Enable
                </label><br>
                <div class='time-inputs'>
                    <label>Start Time: 
                        <input type='time' name='{$lower}_start' min='08:00' max='17:00' required>
                        <small>(8:00 AM - 11:30 AM or 1:00 PM - 5:00 PM)</small>
                    </label><br>
                    <label>End Time: 
                        <input type='time' name='{$lower}_end' min='08:00' max='17:00' required>
                        <small>(Must be after start time)</small>
                    </label>
                </div>
            </fieldset>
            ";
        }
        ?>
        <input type="submit" value="Save Availability">
    </form>

    <hr>
    <h3>Current Weekly Availability</h3>
    <table>
        <tr>
            <th>Day</th>
            <th>Start Time</th>
            <th>End Time</th>
            <th>Enabled</th>
            <th>Action</th>
        </tr>
        <?php
        $result = $conn->prepare("SELECT availabilityID, weekday, startTime, endTime, isEnabled FROM DoctorWeeklyAvailability WHERE doctorID = ? ORDER BY FIELD(weekday, 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday')");
        $result->bind_param("i", $doctorID);
        $result->execute();
        $res = $result->get_result();

        while ($row = $res->fetch_assoc()) {
            echo "<tr>
                <td>{$row['weekday']}</td>
                <td>{$row['startTime']}</td>
                <td>{$row['endTime']}</td>
                <td>" . ($row['isEnabled'] ? 'Yes' : 'No') . "</td>
                <td><a href='doctor_availability.php?delete={$row['availabilityID']}' onclick=\"return confirm('Delete this availability?');\">Delete</a></td>
            </tr>";
        }
        ?>
    </table>
</main>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Initialize time inputs disabled state
    document.querySelectorAll("input[type=checkbox]").forEach(checkbox => {
        const fieldset = checkbox.closest("fieldset");
        const timeInputs = fieldset.querySelectorAll("input[type=time]");
        
        checkbox.addEventListener("change", function() {
            timeInputs.forEach(input => {
                input.disabled = !this.checked;
                input.required = this.checked;
            });
        });
        
        // Trigger change event to set initial state
        checkbox.dispatchEvent(new Event("change"));
    });

    // Additional client-side validation
    document.getElementById('availabilityForm').addEventListener('submit', function(e) {
        let isValid = true;
        const errorMessages = [];
        
        document.querySelectorAll('fieldset').forEach(fieldset => {
            const checkbox = fieldset.querySelector('input[type=checkbox]');
            if (checkbox.checked) {
                const day = fieldset.querySelector('legend').textContent;
                const start = fieldset.querySelector('input[name$="_start"]').value;
                const end = fieldset.querySelector('input[name$="_end"]').value;
                
                if (!start || !end) {
                    errorMessages.push(`${day}: Both start and end times are required`);
                    isValid = false;
                    return;
                }
                
                const startHour = parseInt(start.split(':')[0]);
                const endHour = parseInt(end.split(':')[0]);
                
                // Check for lunch break
                if ((startHour >= 12 && startHour < 13) || (endHour > 12 && endHour <= 13)) {
                    errorMessages.push(`${day}: Cannot set availability during lunch break (12:00 PM - 1:00 PM)`);
                    isValid = false;
                }
                
                // Check time range
                if (startHour < 8 || startHour > 17 || endHour < 8 || endHour > 17) {
                    errorMessages.push(`${day}: Availability must be between 8:00 AM and 5:00 PM`);
                    isValid = false;
                }
                
                // Check start before end
                if (start >= end) {
                    errorMessages.push(`${day}: End time must be after start time`);
                    isValid = false;
                }
            }
        });
        
        if (!isValid) {
            e.preventDefault();
            alert("Please fix the following errors:\n\n" + errorMessages.join("\n"));
        }
    });
});
</script>

<footer class="app-footer">
    <div class="footer-content">
        <p class="copyright">&copy; <?php echo date("Y"); ?> Doval Santos Diagnostic Center. All rights reserved.</p>
    </div>
</footer>

</body>
</html>