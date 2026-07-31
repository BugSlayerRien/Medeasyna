<?php
require 'config.php';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Schedule Appointment</title>
    <link rel="stylesheet" href="../assets/css/schedule_appointment.css">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
</head>
<body>
<header class="navbar">
    <h1>MedEasyna</h1>
    <nav>
        <a href="receptionist_dashboard.php">Dashboard</a>
        <a href="walk_in.php">Walk-In</a>
        <a href="cancel_appointments.php">Cancel</a>
        <a href="logout.php">Logout</a>
    </nav>
</header>

<main class="form-container">
    <h2>Schedule New Appointment</h2>

    <form action="submit_appointment.php" method="POST">
        <!-- Patient ID -->
        <label for="patientID">Patient ID</label>
        <input type="text" id="patientID" name="patientID" required placeholder="Enter or search patient ID">
        <a href="search_patient.php" class="search-link">Search Patient</a>

        <!-- Doctor Selection -->
        <label for="doctorID">Select Doctor</label>
        <select id="doctorID" name="doctorID" required>
            <option value="">-- Choose Doctor --</option>
            <?php
                $query = "SELECT doctorID, firstName, lastName, specialization FROM doctor JOIN user ON doctorID = userID";
                $result = $conn->query($query);
                while ($row = $result->fetch_assoc()) {
                    $name = htmlspecialchars("Dr. {$row['firstName']} {$row['lastName']} ({$row['specialization']})");
                    echo "<option value='{$row['doctorID']}'>$name</option>";
                }
            ?>
        </select>

        <!-- Appointment Date -->
        <label for="appointmentDate">Appointment Date</label>
        <input type="date" id="appointmentDate" name="appointmentDate" required disabled>

        <!-- Appointment Time -->
        <label for="appointmentTime">Appointment Time</label>
        <select id="appointmentTime" name="appointmentTime" required disabled>
            <option value="">-- Select Time --</option>
        </select>

        <!-- Appointment Type -->
        <label for="appointmentType">Appointment Type</label>
        <select id="appointmentType" name="appointmentType" required>
            <option value="">-- Select Type --</option>
            <option value="Consultation">Consultation</option>
            <option value="Checkup">Checkup</option>
            <option value="Follow-up">Follow-up</option>
        </select>

        <!-- Reason / Notes -->
        <label for="reasonForVisit">Additional Notes (Optional)</label>
        <textarea id="reasonForVisit" name="reasonForVisit" rows="4" placeholder="Include any relevant information..."></textarea>

        <button type="submit">Book Appointment</button>
    </form>
</main>

<footer class="dashboard-footer">
    <p>MedEasyna Clinic Management System &copy; 2025</p>
</footer>

<script>
$(document).ready(function () {
    $('#doctorID').change(function () {
        const doctorID = $(this).val();
        if (!doctorID) return;

        // Enable date field
        $('#appointmentDate').prop('disabled', false).val('');
        $('#appointmentTime').html('<option value="">-- Select Time --</option>').prop('disabled', true);

        // Remove previous event to avoid stacking
        $('#appointmentDate').off('change');

        $('#appointmentDate').on('change', function () {
            const date = $(this).val();
            if (!date) return;

            $.ajax({
                url: 'get_available_times.php',
                method: 'POST',
                data: { doctorID, date },
                success: function (data) {
                    const times = JSON.parse(data);
                    const $timeSelect = $('#appointmentTime');
                    $timeSelect.html('<option value="">-- Select Time --</option>');
                    times.forEach(function (time) {
                        $timeSelect.append(`<option value="${time}">${time}</option>`);
                    });
                    $timeSelect.prop('disabled', false);
                }
            });
        });
    });
});
</script>
</body>
</html>
