<?php
// Include config and session start
include 'config.php';
session_start();

// Check if the user is an admin
if ($_SESSION['role'] !== 'Admin') {
    header('Location: admin_dashboard.php');
    exit();
}

// Get the userID from the query string
if (isset($_GET['userID'])) {
    $userID = $_GET['userID'];

    // Fetch the user's current details
    $sql = "SELECT * FROM User WHERE userID = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('i', $userID);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 0) {
        echo "User not found!";
        exit();
    }

    $user = $result->fetch_assoc();

    // Handle form submission to update user details
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $firstName = $_POST['firstName'];
        $lastName = $_POST['lastName'];
        $email = $_POST['email'];
        $role = $_POST['role'];

        // Update user details
        $updateQuery = "UPDATE User SET firstName = ?, lastName = ?, email = ?, role = ? WHERE userID = ?";
        $stmt = $conn->prepare($updateQuery);
        $stmt->bind_param('ssssi', $firstName, $lastName, $email, $role, $userID);
        $stmt->execute();

        header('Location: manage_accounts.php');
        exit();
    }
} else {
    echo "No user selected!";
    exit();
}

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit User | Admin Dashboard</title>
    <link rel="stylesheet" href="../assets/css/edit_user.css">
</head>
<body>
    <header class="navbar">
        <div class="navbar-brand">
            <h1>Doval Santos Diagnostic Center</h1>
        </div>
        <nav class="navbar-menu">
            <a href="admin_dashboard.php">Dashboard</a>
            <a href="manage_accounts.php">Manage Accounts</a>
            <a href="view_audit_logs.php">Audit Logs</a>
            <a href="../pages/logout.php">Logout</a>
        </nav>
    </header>

    <main class="dashboard-container">
        <section class="edit-user-form">
            <h2>Edit User Details</h2>
            <form action="edit_user.php?userID=<?php echo $userID; ?>" method="POST">
                <label for="firstName">First Name:</label>
                <input type="text" id="firstName" name="firstName" value="<?php echo htmlspecialchars($user['firstName']); ?>" required>

                <label for="lastName">Last Name:</label>
                <input type="text" id="lastName" name="lastName" value="<?php echo htmlspecialchars($user['lastName']); ?>" required>

                <label for="email">Email:</label>
                <input type="email" id="email" name="email" value="<?php echo htmlspecialchars($user['email']); ?>" required>

                <label for="role">Role:</label>
                <select id="role" name="role" required>
                    <option value="Admin" <?php echo $user['role'] === 'Admin' ? 'selected' : ''; ?>>Admin</option>
                    <option value="Doctor" <?php echo $user['role'] === 'Doctor' ? 'selected' : ''; ?>>Doctor</option>
                    <option value="Employee" <?php echo $user['role'] === 'Employee' ? 'selected' : ''; ?>>Employee</option>
                </select>

                <button type="submit">Save Changes</button>
            </form>
        </section>
    </main>

    <footer class="app-footer">
        <div class="footer-content">
            <p>&copy; <?php echo date("Y"); ?> Doval Santos Diagnostic Center. All rights reserved.</p>
        </div>
    </footer>
    
    <footer class="app-footer">
    <div class="footer-content">
        <p class="copyright">&copy; <?php echo date("Y"); ?> Doval Santos Diagnostic Center</p>
    </div>
</footer>

</body>
</html>
