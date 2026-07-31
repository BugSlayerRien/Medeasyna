<?php
session_start();
require 'config.php';

// Verify nurse session
if (!isset($_SESSION['userID']) || 
    !isset($_SESSION['role']) || 
    $_SESSION['role'] !== 'Employee' ||
    !isset($_SESSION['employeeRole']) || 
    $_SESSION['employeeRole'] !== 'Nurse') {
    
    header('Location: signin.php?error=access_denied');
    exit;
}
$firstName = $_SESSION['firstName'] ?? 'Nurse';
$role = 'Nurse';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($role); ?> Dashboard | Clinic System</title>
    <link rel="stylesheet" href="../assets/css/main.css">
    <link rel="stylesheet" href="../assets/css/nurse_dashboard.css">
</head>
<body>

    <div class="navbar">
    <h1>Doval Santos Diagnostic Center</h1>
    <div>
    <a href="nurse_dashboard.php">Dashboard</a>
            <a href="today_appointments.php">This Week's Appointments</a>
            <a href="update_vitals.php">Record Vitals</a>
            <a href="mark_ready.php">Mark Patient as Ready</a>
            <a href="../pages/logout.php">Sign Out</a>
    </div>
</div>

    <!-- Main Content -->
    <main class="dashboard-container">
        <section class="welcome-section">
            <h2>Welcome, Nurse <?php echo htmlspecialchars($firstName); ?> <span class="role-badge nurse"></span></h2>
            <p class="subtitle">Efficient scheduling through vitals and readiness tracking</p>
        </section>

        <section class="quick-actions">
            <div class="card-grid">
                <article class="action-card" onclick="location.href='today_appointments.php'">
                    <h3>This Week's Appointments</h3>
                    <p>Prepare for incoming patients and view schedules</p>
                </article>

                <article class="action-card" onclick="location.href='update_vitals.php'">
    <h3>Record Vitals</h3>
    <p>Enter pre-consult patient data (BP, temp, etc.)</p>
</article>


                <article class="action-card" onclick="location.href='mark_ready.php'">
                    <h3>Mark Patient as Ready</h3>
                    <p>Flag patients as ready for doctor consultation</p>
                </article>
            </div>
        </section>


        <div class="walkin-list">
                <?php
                $today = date("Y-m-d");
                $walkinSql = "SELECT W.walkInID, W.status, W.arrivalTime, U.firstName, U.lastName 
                              FROM WalkInTracking W
                              JOIN Patient P ON W.patientID = P.patientID
                              JOIN User U ON P.patientID = U.userID
                              WHERE DATE(W.arrivalTime) = ? 
                              AND W.status IN ('Waiting', 'In Consultation')
                              ORDER BY W.arrivalTime ASC";
                $walkinStmt = $conn->prepare($walkinSql);
                $walkinStmt->bind_param("s", $today);
                $walkinStmt->execute();
                $walkinResult = $walkinStmt->get_result();

                if ($walkinResult->num_rows > 0):
                    while ($row = $walkinResult->fetch_assoc()):
                ?>
                <div class="walkin-item">
                    <div class="walkin-info">
                        <strong><?php echo htmlspecialchars($row['firstName'] . ' ' . $row['lastName']); ?></strong>
                        <p>Status: <span class="status-tag"><?php echo $row['status']; ?></span></p>
                        <p><small>Arrived at: <?php echo date("g:i A", strtotime($row['arrivalTime'])); ?></small></p>
                    </div>
                    <div class="walkin-actions">
                        <form method="POST" action="mark_ready.php">
                            <input type="hidden" name="walkInID" value="<?php echo $row['walkInID']; ?>" />
                            <button type="submit" class="mark-ready-btn">Mark as Ready</button>
                        </form>
                    </div>
                </div>
                <?php
                    endwhile;
                else:
                ?>
                <div class="empty-state">
                    <p>No walk-ins at the moment.</p>
                </div>
                <?php endif; ?>
            </div>
        </section>
    </main>

    <!-- Footer -->
    <footer class="app-footer">
    <div class="footer-content">
        <p class="copyright">&copy; <?php echo date("Y"); ?> Doval Santos Diagnostic Center</p>
    </div>
</footer>

    <script src="../assets/js/employee_dashboard.js"></script>
</body>
</html>
