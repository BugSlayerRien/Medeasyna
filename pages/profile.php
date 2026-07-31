<?php
session_start();
require_once 'config.php'; // Make sure this is your database connection

// Check if user is logged in
if (!isset($_SESSION['userID']) || $_SESSION['role'] !== 'Patient') {
    header("Location: index.php");
    exit();
}

$userID = $_SESSION['userID'];

// Handle profile update
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $firstName = $_POST['firstName'];
    $lastName = $_POST['lastName'];
    $phoneNumber = $_POST['phoneNumber'];

    // Handle profile picture upload
    if (isset($_FILES['profilePicture']) && $_FILES['profilePicture']['error'] == 0) {
        $imgName = time() . '_' . basename($_FILES['profilePicture']['name']);
        $targetDir = "../uploads/";
        $targetFile = $targetDir . $imgName;

        move_uploaded_file($_FILES['profilePicture']['tmp_name'], $targetFile);

        // Update picture
        $stmt = $conn->prepare("UPDATE User SET firstName=?, lastName=?, phoneNumber=?, profilePicture=? WHERE userID=?");
        $stmt->bind_param("ssssi", $firstName, $lastName, $phoneNumber, $imgName, $userID);
    } else {
        // No new picture
        $stmt = $conn->prepare("UPDATE User SET firstName=?, lastName=?, phoneNumber=? WHERE userID=?");
        $stmt->bind_param("sssi", $firstName, $lastName, $phoneNumber, $userID);
    }
    $stmt->execute();
    $stmt->close();

    $_SESSION['firstName'] = $firstName; // Update session too

    header("Location: profile.php?success=1");
    exit();
}

// Fetch user details
$stmt = $conn->prepare("SELECT firstName, lastName, email, phoneNumber, profilePicture FROM User WHERE userID=?");
$stmt->bind_param("i", $userID);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();
$stmt->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>My Profile</title>
    <link rel="stylesheet" href="../assets/css/patientprofile.css">
</head>
<body>

<div class="navbar">
    <h1>MedEasyna</h1>
    <div>
        <a href="patient_dashboard.php">Dashboard</a>
        <a href="appointments.php">My Appointments</a>
        <a href="profile.php" class="active">Profile</a>
        <a href="logout.php">Logout</a>
    </div>
</div>

<div class="dashboard-content">
    <h2>My Profile</h2>

    <?php if (isset($_GET['success'])): ?>
        <div class="success-msg">Profile updated successfully!</div>
    <?php endif; ?>

    <form action="profile.php" method="POST" enctype="multipart/form-data" class="profile-form">
    <div class="profile-picture">
        <?php if (!empty($user['profilePicture'])): ?>
            <img src="../uploads/<?php echo htmlspecialchars($user['profilePicture']); ?>" alt="Profile Picture">
        <?php else: ?>
            <img src="../assets/images/default-profile.png" alt="Default Picture">
        <?php endif; ?>
    </div>

    <div class="form-group">
        <label for="profilePicture">Change Profile Picture:</label>
        <input type="file" name="profilePicture" id="profilePicture" accept="image/*">
    </div>

    <div class="form-group">
        <label for="firstName">First Name:</label>
        <input type="text" name="firstName" id="firstName" value="<?php echo htmlspecialchars($user['firstName']); ?>" required>
    </div>

    <div class="form-group">
        <label for="lastName">Last Name:</label>
        <input type="text" name="lastName" id="lastName" value="<?php echo htmlspecialchars($user['lastName']); ?>" required>
    </div>

    <div class="form-group">
        <label for="email">Email:</label>
        <input type="email" id="email" value="<?php echo htmlspecialchars($user['email']); ?>" disabled>
    </div>

    <div class="form-group">
        <label for="phoneNumber">Phone Number:</label>
        <input type="text" name="phoneNumber" id="phoneNumber" value="<?php echo htmlspecialchars($user['phoneNumber']); ?>">
    </div>

    <div class="form-group">
        <button type="submit" class="btn-update">Update Profile</button>
    </div>
</form>

</body>
</html>
