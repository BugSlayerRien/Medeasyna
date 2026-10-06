<?php
session_start();
include 'config.php';

// Handle walk-in actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        $walkInID = (int)$_POST['walkInID'];
        $action = $_POST['action'];
        $userID = $_SESSION['userID'];

        try {
            $conn->begin_transaction();

            if ($action === 'delete') {
                $stmt = $conn->prepare("UPDATE walkInTracking SET deletedAt = NOW() WHERE walkInID = ?");
                $stmt->bind_param("i", $walkInID);
                $stmt->execute();
                $actionDesc = "Deleted walk-in ID $walkInID";
            } 
            elseif ($action === 'consultation') {
                $stmt = $conn->prepare("UPDATE walkInTracking SET status = 'In Consultation' WHERE walkInID = ?");
                $stmt->bind_param("i", $walkInID);
                $stmt->execute();
                $actionDesc = "Marked walk-in ID $walkInID as In Consultation";
            }
            elseif ($action === 'remove') {
                $stmt = $conn->prepare("UPDATE walkInTracking SET status = 'Removed', updatedAt = NOW() WHERE walkInID = ?");
                $stmt->bind_param("i", $walkInID);
                $stmt->execute();
                $actionDesc = "Removed walk-in ID $walkInID from queue";
            }

            $conn->query("INSERT INTO audit_Log (userID, action, timestamp) VALUES ($userID, '$actionDesc', NOW())");
            $conn->commit();
            $_SESSION['success'] = "Action completed successfully";
        } catch (Exception $e) {
            $conn->rollback();
            $_SESSION['error'] = "Error processing action: " . $e->getMessage();
        }

        header("Location: walk_in.php");
        exit();
    }

    // Walk-in registration
    $firstName = $_POST['firstName'];
    $lastName = $_POST['lastName'];
    $phoneNumber = $_POST['phoneNumber'];
    $doctorID = $_POST['doctorID'];
    $email = $_POST['email'];
    $dateOfBirth = !empty($_POST['dateOfBirth']) ? $_POST['dateOfBirth'] : null;

    try {
        $conn->begin_transaction();

        // Check if email already exists
        $check = $conn->prepare("SELECT userID FROM user WHERE email = ?");
        $check->bind_param("s", $email);
        $check->execute();
        $result = $check->get_result();
        
        if ($result->num_rows > 0) {
            throw new Exception("This email is already registered");
        }

        // Generate temporary credentials
        $tempPassword = bin2hex(random_bytes(4)); // 8-character temp password
        $passwordHash = password_hash($tempPassword, PASSWORD_DEFAULT);

        // Create user with credentials
        $stmt = $conn->prepare("INSERT INTO user 
            (firstName, lastName, phoneNumber, email, passwordHash, role, dateOfBirth, createdAt) 
            VALUES (?, ?, ?, ?, ?, 'Patient', ?, NOW())");
        $stmt->bind_param("ssssss", $firstName, $lastName, $phoneNumber, $email, $passwordHash, $dateOfBirth);
        $stmt->execute();
        $userID = $conn->insert_id;

        // Register in patient table
        $stmt = $conn->prepare("INSERT INTO patient (patientID, createdAt) VALUES (?, NOW())");
        $stmt->bind_param("i", $userID);
        $stmt->execute();

        // Insert into walk-in tracking
        $status = "Waiting";
        $insert = $conn->prepare("
            INSERT INTO walkInTracking 
            (patientID, doctorID, arrivalTime, status, createdAt) 
            VALUES (?, ?, NOW(), ?, NOW())
        ");
        $insert->bind_param("iis", $userID, $doctorID, $status);
        $insert->execute();

        $conn->commit();
        
        // Store credentials to display
        $_SESSION['temp_credentials'] = [
            'name' => "$firstName $lastName",
            'email' => $email,
            'password' => $tempPassword,
            'time' => time()
        ];
        
        $_SESSION['success'] = "Walk-in registered successfully!";
        header("Location: walk_in.php");
        exit();
        
    } catch (Exception $e) {
        $conn->rollback();
        $_SESSION['error'] = "Error registering walk-in: " . $e->getMessage();
        header("Location: walk_in.php");
        exit();
    }
}

// Get doctors for dropdown
$doctors = $conn->query("
    SELECT d.doctorID, u.firstName, u.lastName 
    FROM doctor d 
    JOIN user u ON d.doctorID = u.userID
");

// Get current walk-ins
$walkIns = $conn->query("
    SELECT w.walkInID, w.patientID, u.firstName, u.lastName, w.status, w.arrivalTime, 
           w.doctorID, CONCAT(du.firstName, ' ', du.lastName) as doctorName
    FROM walkInTracking w
    JOIN user u ON w.patientID = u.userID
    JOIN doctor d ON w.doctorID = d.doctorID
    JOIN user du ON d.doctorID = du.userID
    WHERE DATE(w.createdAt) = CURDATE() AND w.deletedAt IS NULL
    ORDER BY 
        CASE w.status
            WHEN 'In Consultation' THEN 1
            WHEN 'Waiting' THEN 2
            ELSE 3
        END,
        w.arrivalTime ASC
");
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Walk-In Registration & Tracking</title>
    <link rel="stylesheet" href="../assets/css/main.css">
    <link rel="stylesheet" href="../assets/css/walk_in.css">
</head>
<body>
    <div class="navbar">
        <h1>MedEasyna</h1>
        <div>
            <a href="receptionist_dashboard.php">Dashboard</a>
            <a href="../pages/schedule_appointment.php">Schedule Appointment</a>
            <a href="../pages/walk_in.php">Walk-Ins</a>
            <a href="../pages/logout.php">Sign Out</a>
        </div>
    </div>

    <div class="dashboard-content">
        <?php if (isset($_SESSION['success'])): ?>
            <div class="success-message"><?= $_SESSION['success']; unset($_SESSION['success']); ?></div>
        <?php endif; ?>
        
        <?php if (isset($_SESSION['error'])): ?>
            <div class="error-message"><?= $_SESSION['error']; unset($_SESSION['error']); ?></div>
        <?php endif; ?>

        <?php if (isset($_SESSION['temp_credentials']) && time() - $_SESSION['temp_credentials']['time'] < 10): ?>
            <div class="credentials-box">
                <h3>Patient Login Credentials</h3>
                <p>Patient: <strong><?= htmlspecialchars($_SESSION['temp_credentials']['name']) ?></strong></p>
                <p>Login Email: <strong><?= htmlspecialchars($_SESSION['temp_credentials']['email']) ?></strong></p>
                <p>Temporary Password: <strong><?= htmlspecialchars($_SESSION['temp_credentials']['password']) ?></strong></p>
                <p class="note">These credentials will be shown only once. Patient can login at <a href="../login.php">our portal</a>.</p>
                <button onclick="printCredentials()">Print Credentials</button>
            </div>
            <?php unset($_SESSION['temp_credentials']); ?>
        <?php endif; ?>

        <header class="dashboard-header">
            <h1>Walk-In Registration</h1>
        </header>

        <section class="form-section">
            <form method="POST">
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px; margin-bottom: 15px;">
                    <div>
                        <input type="text" name="firstName" placeholder="First Name" required>
                    </div>
                    <div>
                        <input type="text" name="lastName" placeholder="Last Name" required>
                    </div>
                    <div>
                        <input type="email" name="email" placeholder="Email" required>
                    </div>
                    <div>
                        <input type="text" name="phoneNumber" placeholder="Phone Number" required>
                    </div>
                    <div>
                        <input type="date" name="dateOfBirth" placeholder="Date of Birth (optional)">
                    </div>
                </div>

                <div style="margin-bottom: 15px;">
                    <label for="doctorID" style="display: block; margin-bottom: 5px;">Choose Doctor:</label>
                    <select name="doctorID" required>
                        <option value="">--Select Doctor--</option>
                        <?php while ($doc = $doctors->fetch_assoc()): ?>
                            <option value="<?= $doc['doctorID'] ?>">
                                <?= htmlspecialchars($doc['firstName'] . ' ' . $doc['lastName']) ?>
                            </option>
                        <?php endwhile; ?>
                    </select>
                </div>

                <button type="submit">Register Walk-In</button>
            </form>
        </section>

        <section class="walkin-tracking">
            <h2>Today's Walk-In Queue</h2>
            <table>
                <thead>
                    <tr>
                        <th>Patient</th>
                        <th>Doctor</th>
                        <th>Status</th>
                        <th>Arrival Time</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while ($row = $walkIns->fetch_assoc()): ?>
                        <tr>
                            <td><?= htmlspecialchars($row['firstName'] . ' ' . $row['lastName']) ?></td>
                            <td><?= htmlspecialchars($row['doctorName']) ?></td>
                            <td class="status-<?= strtolower(str_replace(' ', '-', $row['status'])) ?>">
                                <?= $row['status'] ?>
                            </td>
                            <td><?= date("h:i A", strtotime($row['arrivalTime'])) ?></td>
                            <td class="action-buttons">
                                <?php if ($row['status'] === 'Waiting'): ?>
                                    <form method="post" style="display: inline;">
                                        <input type="hidden" name="walkInID" value="<?= $row['walkInID'] ?>">
                                        <input type="hidden" name="action" value="consultation">
                                        <button type="submit" class="btn btn-consultation">Start Consultation</button>
                                    </form>
                                <?php endif; ?>
                                
                                <form method="post" style="display: inline;">
                                    <input type="hidden" name="walkInID" value="<?= $row['walkInID'] ?>">
                                    <input type="hidden" name="action" value="remove">
                                    <button type="submit" class="btn btn-remove">Remove</button>
                                </form>
                                
                                <form method="post" style="display: inline;" onsubmit="return confirm('Are you sure you want to permanently delete this walk-in?')">
                                    <input type="hidden" name="walkInID" value="<?= $row['walkInID'] ?>">
                                    <input type="hidden" name="action" value="delete">
                                    <button type="submit" class="btn btn-delete">Delete</button>
                                </form>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </section>
    </div>

    <footer class="app-footer">
        <div class="footer-content">
            <p class="copyright">&copy; <?php echo date("Y"); ?> MedEasyna</p>
        </div>
    </footer>

    <script>
    function printCredentials() {
        const printContent = document.querySelector('.credentials-box').innerHTML;
        const originalContent = document.body.innerHTML;
        
        document.body.innerHTML = `
            <div style="padding:20px;font-family:Century Gothic">
                <h1 style="color:#0d47a1;border-bottom:2px solid #0d47a1;padding-bottom:10px">
                    Patient Credentials - MedEasyna
                </h1>
                ${printContent}
                <p style="margin-top:30px;font-size:12px;text-align:center">
                    Generated on ${new Date().toLocaleString()}<br>
                    This document contains sensitive information and should be handled securely
                </p>
            </div>
        `;
        
        window.print();
        document.body.innerHTML = originalContent;
    }
    </script>
</body>
</html>