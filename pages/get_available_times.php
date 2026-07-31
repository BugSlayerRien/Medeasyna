<?php
include 'config.php';
header('Content-Type: application/json');

$doctorID = $_GET['doctorID'] ?? null;
$date = $_GET['date'] ?? null;

if (!$doctorID || !$date) {
    echo json_encode([]);
    exit;
}

$dayOfWeek = date('l', strtotime($date));
$slots = [];

// Get doctor's availability for this day
$availability = $conn->prepare("
    SELECT startTime, endTime FROM DoctorWeeklyAvailability 
    WHERE doctorID = ? AND weekday = ? AND isEnabled = 1
");
$availability->bind_param("is", $doctorID, $dayOfWeek);
$availability->execute();
$result = $availability->get_result();

if ($result->num_rows > 0) {
    $row = $result->fetch_assoc();
    $start = new DateTime($row['startTime']);
    $end = new DateTime($row['endTime']);
    
    // Generate 30-minute slots
    $interval = new DateInterval('PT30M');
    $period = new DatePeriod($start, $interval, $end);
    
    foreach ($period as $time) {
        $timeStr = $time->format('H:i:s');
        
        // Check if slot is already booked
        $booked = $conn->prepare("
            SELECT * FROM Appointment 
            WHERE doctorID = ? 
            AND appointmentDate = ? 
            AND appointmentTime = ?
            AND status IN ('Scheduled', 'Checked-in')
        ");
        $booked->bind_param("iss", $doctorID, $date, $timeStr);
        $booked->execute();
        
        if ($booked->get_result()->num_rows === 0) {
            $slots[] = $time->format('H:i');
        }
    }
}

echo json_encode($slots);