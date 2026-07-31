<?php //DOCTOR 
// save_schedule.php
session_start();
include 'config.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $doctorID = $_SESSION['userID'];  // Assuming the doctorID is stored in session

    // Example: saving availability for each day of the week
    $daysOfWeek = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'];

    foreach ($daysOfWeek as $day) {
        // Retrieve the date and times from the form
        $date = $_POST[$day . '_date'];  // E.g., 'monday_date'
        $startTime = $_POST[$day . '_start'];  // E.g., 'monday_start'
        $endTime = $_POST[$day . '_end'];  // E.g., 'monday_end'

        // If both start and end time are provided, save to the database
        if ($date && $startTime && $endTime) {
            $sql = "INSERT INTO Schedule (doctorID, availableDate, startTime, endTime) 
                    VALUES (?, ?, ?, ?)";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("isss", $doctorID, $date, $startTime, $endTime);
            $stmt->execute();
        }
    }

    header("Location: availability_page.php?success=true");
}
?>
