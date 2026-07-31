<?php //DOCTOR
// available_times.php
$availableTimes = [];
$appointmentDuration = 30 * 60;  // 30 minutes in seconds
$buffer = 15 * 60;  // 15 minutes buffer between appointments

foreach ($availability as $timeSlot) {
    $slotStart = strtotime($timeSlot['startTime']);
    $slotEnd = strtotime($timeSlot['endTime']);
    
    // Check for available slots between start and end time
    while ($slotStart + $appointmentDuration <= $slotEnd) {
        // Check if the slot conflicts with existing appointments
        $conflict = false;
        $appointmentQuery = "
            SELECT appointmentTime
            FROM Appointment 
            WHERE doctorID = ? AND appointmentDate = ? AND status = 'Scheduled'
        ";
        $stmt = $conn->prepare($appointmentQuery);
        $stmt->bind_param("is", $doctorID, $selectedDate);
        $stmt->execute();
        $appointments = $stmt->get_result();
        
        while ($appointment = $appointments->fetch_assoc()) {
            $apptStart = strtotime($appointment['appointmentTime']);
            $apptEnd = $apptStart + $appointmentDuration;
            
            // Check if the slot overlaps with existing appointments
            if (($slotStart < $apptEnd) && ($slotEnd > $apptStart)) {
                $conflict = true;
                break;
            }
        }

        // If no conflict, add the slot to available times
        if (!$conflict) {
            $availableTimes[] = date('H:i', $slotStart);
        }

        // Move to the next slot
        $slotStart += $appointmentDuration + $buffer;
    }
}
?>
