<?php
include 'config.php';
session_start();

if (!isset($_SESSION['userID']) || $_SESSION['role'] !== 'Admin') {
    header("Location: ../pages/index.php");
    exit();
}

if (isset($_GET['delete'])) {
    $userId = intval($_GET['delete']);

    $conn->begin_transaction();

    try {
        $conn->query("SET @current_admin_id = " . $_SESSION['userID']);
        // 1. Detect role from User table
        $stmt = $conn->prepare("SELECT role FROM User WHERE userID = ?");
        $stmt->bind_param("i", $userId);
        $stmt->execute();
        $result = $stmt->get_result();
        $userRole = $result->fetch_assoc()['role'] ?? null;
        $stmt->close();

        // 2. If Patient, delete walk-ins
        if ($userRole === 'Patient') {
            $stmt = $conn->prepare("DELETE FROM WalkInTracking WHERE patientID = ?");
            $stmt->bind_param("i", $userId);
            $stmt->execute();
            $stmt->close();
        }

        // 3. Delete from AppointmentService (linked to this user as patient or doctor)
        $conn->query("DELETE aps FROM AppointmentService aps 
                      JOIN Appointment a ON aps.appointmentID = a.appointmentID 
                      WHERE a.patientID = $userId OR a.doctorID = $userId");

        // 4. Delete Appointments
        $conn->query("DELETE FROM Appointment WHERE patientID = $userId OR doctorID = $userId");

        // 5. Delete Notifications
        $stmt = $conn->prepare("DELETE FROM Notification WHERE userID = ?");
        $stmt->bind_param("i", $userId);
        $stmt->execute();
        $stmt->close();

        // 6. Delete from role-specific table
        if ($userRole === 'Patient') {
            $stmt = $conn->prepare("DELETE FROM Patient WHERE patientID = ?");
        } elseif ($userRole === 'Doctor') {
            $stmt = $conn->prepare("DELETE FROM Doctor WHERE doctorID = ?");
        } elseif ($userRole === 'Employee') {
            $stmt = $conn->prepare("DELETE FROM Employee WHERE employeeID = ?");
        }

        if (isset($stmt)) {
            $stmt->bind_param("i", $userId);
            $stmt->execute();
            $stmt->close();
        }

        // 7. Finally, delete from User
        $stmt = $conn->prepare("DELETE FROM User WHERE userID = ?");
        $stmt->bind_param("i", $userId);
        $stmt->execute();
        $stmt->close();

        $conn->commit();
        header("Location: manage_accounts.php");
        exit();
    } catch (Exception $e) {
        $conn->rollback();
        die("Error deleting user: " . $e->getMessage());
    }
}


if (isset($_POST['editUser'])) {
    $userId = isset($_POST['userId']) ? intval($_POST['userId']) : 0;
    $firstName = trim($_POST['firstName']);
    $lastName = trim($_POST['lastName']);
    $email = trim($_POST['email']);
    $role = trim($_POST['role']);

    $conn->begin_transaction();

    try {
        if ($userId > 0) {
            $conn->query("SET @current_admin_id = " . $_SESSION['userID']);
            // 🔄 Update existing user
            $stmt = $conn->prepare("UPDATE User SET firstName = ?, lastName = ?, email = ?, role = ? WHERE userID = ?");
            $stmt->bind_param("ssssi", $firstName, $lastName, $email, $role, $userId);
            $stmt->execute();

            // Clear old roles
            $conn->query("DELETE FROM Doctor WHERE doctorID = $userId");
            $conn->query("DELETE FROM Patient WHERE patientID = $userId");
            $conn->query("DELETE FROM Employee WHERE employeeID = $userId");

        } else {
            // ➕ Insert new user
            $stmt = $conn->prepare("INSERT INTO User (firstName, lastName, email, role) VALUES (?, ?, ?, ?)");
            $stmt->bind_param("ssss", $firstName, $lastName, $email, $role);
            $stmt->execute();

            // Get the new userID
            $userId = $conn->insert_id;
        }

        // ✅ Insert into role-specific table
        if ($role === 'Doctor') {
            $stmt = $conn->prepare("INSERT INTO Doctor (doctorID, specialization) VALUES (?, ?)");
            $specialization = 'General';
            $stmt->bind_param("is", $userId, $specialization);
            $stmt->execute();
        } elseif ($role === 'Patient') {
            $stmt = $conn->prepare("INSERT INTO Patient (patientID) VALUES (?)");
            $stmt->bind_param("i", $userId);
            $stmt->execute();
        } elseif (in_array($role, ['Nurse', 'Receptionist'])) {
            $stmt = $conn->prepare("INSERT INTO Employee (employeeID, employeeRole) VALUES (?, ?)");
            $stmt->bind_param("is", $userId, $role);
            $stmt->execute();
        }

        $conn->commit();
        header("Location: manage_accounts.php");
        exit();

    } catch (Exception $e) {
        $conn->rollback();
        die("Error saving user: " . $e->getMessage());
    }
}



// Handle add new user
if (isset($_POST['addUser'])) {
    $firstName = trim($_POST['firstName']);
    $lastName = trim($_POST['lastName']);
    $email = trim($_POST['email']);
    $passwordRaw = isset($_POST['password']) && $_POST['password'] !== '' ? $_POST['password'] : 'defaultPassword123';
    $password = password_hash($passwordRaw, PASSWORD_BCRYPT);
    $phoneNumber = isset($_POST['phoneNumber']) ? trim($_POST['phoneNumber']) : null;
    $dateOfBirth = isset($_POST['dateOfBirth']) ? trim($_POST['dateOfBirth']) : null;
    $role = 'Patient'; // Default role for new user

    require_once 'config.php';
    $conn->begin_transaction();

    try {
        // Insert into User table
        $stmt = $conn->prepare("INSERT INTO User (firstName, lastName, email, passwordHash, phoneNumber, role, dateOfBirth) 
                                VALUES (?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("sssssss", $firstName, $lastName, $email, $password, $phoneNumber, $role, $dateOfBirth);
        $stmt->execute();
        $userId = $conn->insert_id;

        // Insert into Patient table
        $stmt = $conn->prepare("INSERT INTO Patient (patientID) VALUES (?)");
        $stmt->bind_param("i", $userId);
        $stmt->execute();

        $conn->commit();
        header("Location: manage_accounts.php");
        exit();
    } catch (Exception $e) {
        $conn->rollback();
        die("Error adding user: " . $e->getMessage());
    }
}


// Search and filter handling
$search = '';
$roleFilter = '';
if (isset($_GET['search'])) {
    $search = trim($_GET['search']);
}

if (isset($_GET['role'])) {
    $roleFilter = trim($_GET['role']);
}

// Build SQL query with filters
$sql = "SELECT u.userID, u.firstName, u.lastName, u.email, u.role, 
               e.employeeRole, u.createdAt
        FROM User u
        LEFT JOIN Employee e ON u.userID = e.employeeID";
$params = [];
$paramTypes = '';

if (!empty($search)) {
    $sql .= " WHERE (u.firstName LIKE ? OR u.lastName LIKE ? OR u.email LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $paramTypes .= "sss";
}

if (!empty($roleFilter)) {
    if (!empty($search)) {
        $sql .= " AND";
    } else {
        $sql .= " WHERE";
    }
    
    if ($roleFilter === 'Employee') {
        $sql .= " e.employeeRole IS NOT NULL";
    } else {
        $sql .= " u.role = ?";
        $params[] = $roleFilter;
        $paramTypes .= "s";
    }
}

$sql .= " ORDER BY u.userID DESC";

$stmt = $conn->prepare($sql);
if (!empty($params)) {
    $stmt->bind_param($paramTypes, ...$params);
}
$stmt->execute();
$result = $stmt->get_result();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Accounts | Admin Dashboard</title>
    <link rel="stylesheet" href="../assets/css/main.css">
    <link rel="stylesheet" href="../assets/css/manage_accounts.css">
</head>
<body>
    <!-- Navigation Bar -->
    <div class="navbar">
    <h1>Doval Santos Diagnostic Center</h1>
    <div>
    <a href="admin_dashboard.php">Dashboard</a>
            <a href="manage_accounts.php">Manage Accounts</a>
            <a href="view_audit_logs.php">Audit Logs</a>
            <a href="../pages/logout.php">Sign Out</a>
    </div>
</div>

    <!-- Main Content -->
    <main class="dashboard-container">
        <section class="search-section">
            <h2>Manage User Accounts</h2>
            <button class="btn add-btn" onclick="openAddModal()">Add New User</button>
            <form method="GET" action="manage_accounts.php" class="search-form">
                <div class="form-group">
                    <input type="text" name="search" placeholder="Search by name or email" value="<?php echo htmlspecialchars($search); ?>">
                </div>
                <div class="form-group">
                    <select name="role">
                        <option value="">All Roles</option>
                        <option value="Admin" <?= $roleFilter == "Admin" ? "selected" : "" ?>>Admin</option>
                        <option value="Doctor" <?= $roleFilter == "Doctor" ? "selected" : "" ?>>Doctor</option>
                        <option value="Employee" <?= $roleFilter == "Employee" ? "selected" : "" ?>>Employee (All)</option>
                        <option value="Patient" <?= $roleFilter == "Patient" ? "selected" : "" ?>>Patient</option>
                    </select>
                </div>
                <button type="submit" class="search-btn">Search</button>
            </form>
        </section>

        <section class="user-table-section">
            <table class="user-table">
                <thead>
                    <tr>
                        <th>UserID</th>
                        <th>Full Name</th>
                        <th>Email</th>
                        <th>Role</th>
                        <th>Employee Type</th>
                        <th>Created</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while ($row = $result->fetch_assoc()): ?>
                    <tr>
                        <td><?= htmlspecialchars($row['userID']) ?></td>
                        <td><?= htmlspecialchars($row['firstName'] . ' ' . $row['lastName']) ?></td>
                        <td><?= htmlspecialchars($row['email']) ?></td>
                        <td><?= htmlspecialchars($row['role']) ?></td>
                        <td><?= htmlspecialchars($row['employeeRole'] ?? 'N/A') ?></td>
                        <td><?= date('M d, Y', strtotime($row['createdAt'])) ?></td>
                        <td class="actions">
                            <button class="btn edit-btn" onclick="openEditModal(
                                <?= $row['userID'] ?>, 
                                '<?= addslashes($row['firstName']) ?>', 
                                '<?= addslashes($row['lastName']) ?>', 
                                '<?= addslashes($row['email']) ?>', 
                                '<?= addslashes($row['role']) ?>'
                            )">Edit</button>
                            <a href="manage_accounts.php?delete=<?= $row['userID'] ?>" class="btn delete-btn" onclick="return confirm('Are you sure you want to delete this user?')">Delete</a>
                        </td>
                    </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </section>
    </main>

    <!-- Edit User Modal -->
    <div id="editModal" class="modal">
        <div class="modal-content">
            <span class="close-btn" onclick="closeEditModal()">&times;</span>
            <h3>Edit User</h3>
            <form method="POST" action="manage_accounts.php" class="modal-form">
                <input type="hidden" name="userId" id="editUserId">
                <div class="form-group">
                    <label>First Name:</label>
                    <input type="text" name="firstName" id="editFirstName" required>
                </div>
                <div class="form-group">
                    <label>Last Name:</label>
                    <input type="text" name="lastName" id="editLastName" required>
                </div>
                <div class="form-group">
                    <label>Email:</label>
                    <input type="email" name="email" id="editEmail" required>
                </div>
                <div class="form-group">
                    <label>Role:</label>
                    <select name="role" id="editRole" required>
                        <option value="Admin">Admin</option>
                        <option value="Doctor">Doctor</option>
                        <option value="Nurse">Nurse</option>
                        <option value="Receptionist">Receptionist</option>
                        <option value="Patient">Patient</option>
                    </select>
                </div>
                <div class="form-actions">
                    <button type="submit" name="editUser" class="btn save-btn">Save Changes</button>
                    <button type="button" class="btn cancel-btn" onclick="closeEditModal()">Cancel</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Add User Modal -->
    <div id="addModal" class="modal"> 
    <div class="modal-content">
        <span class="close-btn" onclick="closeAddModal()">&times;</span>
        <h3>Add New User</h3>
        <form method="POST" action="manage_accounts.php" class="modal-form">
            <div class="form-group">
                <label>First Name:</label>
                <input type="text" name="firstName" required>
            </div>
            <div class="form-group">
                <label>Last Name:</label>
                <input type="text" name="lastName" required>
            </div>
            <div class="form-group">
                <label>Email:</label>
                <input type="email" name="email" required>
            </div>
            <div class="form-group">
                <label>Phone Number:</label>
                <input type="text" name="phoneNumber" placeholder="Optional">
            </div>
            <div class="form-group">
                <label>Date of Birth:</label>
                <input type="date" name="dateOfBirth" placeholder="Optional">
            </div>
            <div class="form-group">
                <label>Password:</label>
                <input type="password" name="password" placeholder="Leave blank to use default">
            </div>
            <div class="form-actions">
                <button type="submit" name="addUser" class="btn save-btn">Add User</button>
                <button type="button" class="btn cancel-btn" onclick="closeAddModal()">Cancel</button>
            </div>
        </form>
    </div>
</div>


    <script>
        // Edit Modal Functions
        function openEditModal(userId, firstName, lastName, email, role) {
            document.getElementById('editUserId').value = userId;
            document.getElementById('editFirstName').value = firstName;
            document.getElementById('editLastName').value = lastName;
            document.getElementById('editEmail').value = email;
            document.getElementById('editRole').value = role;
            document.getElementById('editModal').style.display = 'block';
        }

        function closeEditModal() {
            document.getElementById('editModal').style.display = 'none';
        }

        // Add Modal Functions
        function openAddModal() {
            document.getElementById('addModal').style.display = 'block';
        }

        function closeAddModal() {
            document.getElementById('addModal').style.display = 'none';
        }

        // Close modals when clicking outside
        window.onclick = function(event) {
            if (event.target.classList.contains('modal')) {
                closeEditModal();
                closeAddModal();
            }
        }
    </script>

<footer class="app-footer">
    <div class="footer-content">
        <p class="copyright">&copy; <?php echo date("Y"); ?> Doval Santos Diagnostic Center</p>
    </div>
</footer>

</body>
</html>