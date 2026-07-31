<?php //DOCTOR
include 'config.php';

$doctorID = $_GET['doctorID'] ?? null;

$response = ['dates' => [], 'timesByDate' => []];

if ($doctorID) {
    $sql = "SELECT DISTINCT availableDate, startTime, endTime 
            FROM DoctorAvailability 
            WHERE doctorID = ? AND availableDate >= CURDATE()
            ORDER BY availableDate ASC";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $doctorID);
    $stmt->execute();
    $result = $stmt->get_result();

    while ($row = $result->fetch_assoc()) {
        $date = $row['availableDate'];
        $response['dates'][] = $date;
        
        // Generate 30-min time slots
        $start = strtotime($row['startTime']);
        $end = strtotime($row['endTime']);
        $availableSlots = [];

        while ($start < $end) {
            $availableSlots[] = date('H:i', $start);
            $start = strtotime('+30 minutes', $start);
        }

        // Fetch booked times for this date
        $bookedStmt = $conn->prepare("SELECT appointmentTime 
                                      FROM Appointment 
                                      WHERE doctorID = ? AND appointmentDate = ? AND status != 'Cancelled'");
        $bookedStmt->bind_param("is", $doctorID, $date);
        $bookedStmt->execute();
        $bookedResult = $bookedStmt->get_result();

        $bookedTimes = [];
        while ($b = $bookedResult->fetch_assoc()) {
            $bookedTimes[] = $b['appointmentTime'];
        }

        // Exclude booked times
        $freeSlots = array_diff($availableSlots, $bookedTimes);
        $response['timesByDate'][$date] = array_values($freeSlots); // reset indexes
    }
}

echo json_encode($response);
?>
