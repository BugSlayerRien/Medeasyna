<?php
session_start();
include 'config.php';

header('Content-Type: application/json');

$doctorID = filter_input(INPUT_GET, 'doctor', FILTER_VALIDATE_INT);
$date = filter_input(INPUT_GET, 'date', FILTER_SANITIZE_STRING);

if (!$doctorID || !$date) {
    echo json_encode(['error' => 'Invalid request']);
    exit;
}

// Get doctor's schedule
$scheduleSql = "SELECT working_days, start_time, end_time, appointment_duration 
FROM Doctor WHERE userID = ?";
$stmt = $conn->prepare($scheduleSql);
$stmt->bind_param("i", $doctorID);
$stmt->execute();
$doctor = $stmt->get_result()->fetch_assoc();

// Check if date is valid
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) || strtotime($date) < strtotime(date('Y-m-d'))) {
    echo json_encode(['error' => 'Invalid date']);
    exit;
}

// Check if doctor works on this day
$dayOfWeek = date('N', strtotime($date));
$workingDays = explode(',', $doctor['working_days']);
if (!in_array($dayOfWeek, $workingDays)) {
    echo json_encode(['error' => 'Doctor not available on selected day']);
    exit;
}

// Calculate all possible time slots
$start = strtotime($doctor['start_time']);
$end = strtotime($doctor['end_time']);
$duration = $doctor['appointment_duration'] * 60; // in seconds
$buffer = 15 * 60; // 15 minute buffer between appointments

$availableTimes = [];
$current = $start;

// Get existing appointments for this doctor/date
$appointments = [];
$apptSql = "SELECT appointmentTime, ADDTIME(appointmentTime, SEC_TO_TIME(?)) as endTime 
           FROM Appointment 
           WHERE doctorID = ? AND appointmentDate = ? AND status = 'Scheduled'";
$stmt = $conn->prepare($apptSql);
$stmt->bind_param("iis", $duration, $doctorID, $date);
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    $appointments[] = [
        'start' => strtotime($row['appointmentTime']),
        'end' => strtotime($row['endTime'])
    ];
}

// Generate available time slots
while ($current + $duration <= $end) {
    $slotStart = $current;
    $slotEnd = $current + $duration;
    
    $isAvailable = true;
    
    // Check against existing appointments
    foreach ($appointments as $appt) {
        if (($slotStart >= $appt['start'] && $slotStart < $appt['end']) ||
            ($slotEnd > $appt['start'] && $slotEnd <= $appt['end']) ||
            ($slotStart <= $appt['start'] && $slotEnd >= $appt['end'])) {
            $isAvailable = false;
            break;
        }
    }
    
    if ($isAvailable) {
        $availableTimes[] = date('H:i', $current);
    }
    
    $current += $duration + $buffer;
}

echo json_encode(['availableTimes' => $availableTimes]);
?>