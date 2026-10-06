<?php
session_start();
include 'config.php';

if (!isset($_SESSION['userID']) || $_SESSION['role'] !== 'Doctor') {
    header("Location: index.php");
    exit();
}

$userID = $_SESSION['userID'];
$search = isset($_GET['search']) ? trim($_GET['search']) : '';

// Fetch patients who had appointments with this doctor
if (!empty($search)) {
    $sql = "
    SELECT DISTINCT 
        p.patientID, u.firstName, u.lastName, u.email, u.phoneNumber
    FROM Appointment a
    INNER JOIN Patient p ON a.patientID = p.patientID
    INNER JOIN User u ON p.patientID = u.userID
    WHERE a.doctorID = ?
    AND (u.firstName LIKE CONCAT('%', ?, '%') OR u.lastName LIKE CONCAT('%', ?, '%'))
    ";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("iss", $userID, $search, $search);
} else {
    $sql = "
    SELECT DISTINCT 
        p.patientID, u.firstName, u.lastName, u.email, u.phoneNumber
    FROM Appointment a
    INNER JOIN Patient p ON a.patientID = p.patientID
    INNER JOIN User u ON p.patientID = u.userID
    WHERE a.doctorID = ?
    ";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $userID);
}

$stmt->execute();
$result = $stmt->get_result();
?>

<!DOCTYPE html>
<html>
<head>
    <title>Patient Records</title>
    <link rel="stylesheet" href="../assets/css/main.css">
        <link rel="stylesheet" href="../assets/css/patient_records.css">
</head>
<body>

<div class="navbar">
    <h1>MedEasyna</h1>
    <div>
        <a href="../pages/doctor_dashboard.php">Dashboard</a>
        <a href="../pages/doctor_availability.php">Availability</a>
        <a href="../pages/patient_records.php">Patient Records</a>
        <a href="../pages/medical_reports.php">Medical Reports</a>
        <a href="../pages/doctor_appointments.php">Appointments</a>
        <a href="logout.php">Sign Out</a>
    </div>
</div>

<h2>Connected Patient Records</h2>

<form method="GET">
    <input type="text" name="search" placeholder="Search by name..." value="<?= htmlspecialchars($search) ?>">
    <button type="submit">Search</button>
</form>

<table>
    <thead>
        <tr>
            <th>Patient Name</th>
            <th>Email</th>
            <th>Phone</th>
            <th>Action</th>
        </tr>
    </thead>
    <tbody>
        <?php while ($row = $result->fetch_assoc()) : ?>
            <tr>
                <td><?= htmlspecialchars($row['firstName'] . ' ' . $row['lastName']) ?></td>
                <td><?= htmlspecialchars($row['email']) ?></td>
                <td><?= htmlspecialchars($row['phoneNumber']) ?></td>
                <td>
                    <form method="GET" action="medical_reports.php">
                        <input type="hidden" name="patientID" value="<?= $row['patientID'] ?>">
                        <button type="submit" class="btn">Create Report</button>
                    </form>
                </td>
            </tr>
        <?php endwhile; ?>
    </tbody>
</table>


<footer class="app-footer">
    <div class="footer-content">
        <p class="copyright">&copy; <?php echo date("Y"); ?> MedEasyna</p>
    </div>
</footer>

</body>
</html>
