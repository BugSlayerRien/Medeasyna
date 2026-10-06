<?php
session_start();
require 'config.php'; 

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = $_POST['email'] ?? '';
    $password = $_POST['password'] ?? '';

    // Fetch user details by email
    $stmt = $conn->prepare("SELECT userID, passwordHash, role, firstName FROM user WHERE email = ?");
    $stmt->bind_param('s', $email);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($user = $result->fetch_assoc()) {
        // Verify password
        if (password_verify($password, $user['passwordHash'])) {
            // Set session variables
            $_SESSION['userID'] = $user['userID'];
            $_SESSION['role'] = $user['role'];
            $_SESSION['firstName'] = $user['firstName'];
        
            // Log the login action
            $logStmt = $conn->prepare("INSERT INTO audit_log (userID, tableChanged, columnChanged, action) VALUES (?, 'session', 'status', 'LOGIN')");
            $logStmt->bind_param('i', $_SESSION['userID']);
            $logStmt->execute();
        
            // Redirect based on role
            switch ($user['role']) {
                case 'Admin':
                    header("Location: ../pages/admin_dashboard.php");
                    break;
                case 'Doctor':
                    header("Location: ../pages/doctor_dashboard.php");
                    break;
                case 'Patient':
                    header("Location: ../pages/patient_dashboard.php");
                    break;
                case 'Employee':
                    // Handle Employee-specific roles (Receptionist, Nurse, etc.)
                    $stmt = $conn->prepare("SELECT employeeRole FROM Employee WHERE employeeID = ?");
                    $stmt->bind_param('i', $_SESSION['userID']);
                    $stmt->execute();
                    $stmt->bind_result($employeeRole);
                    if ($stmt->fetch()) {
                        $_SESSION['employeeRole'] = $employeeRole;
                    } else {
                        // Fallback if not found (shouldn't happen for valid employees)
                        $_SESSION['employeeRole'] = 'Receptionist';
                    }
                    $stmt->close();
        
                    // Redirect based on employeeRole
                    if ($_SESSION['employeeRole'] === 'Receptionist') {
                        header('Location: ../pages/receptionist_dashboard.php');
                    } elseif ($_SESSION['employeeRole'] === 'Nurse') {
                        header('Location: ../pages/nurse_dashboard.php');
                    }
                    break;
                default:
                    // Unknown role, logout
                    session_destroy();
                    header("Location: ../pages/signin.php?error=invalidrole");
            }
            exit();
        } else {
            $error = "Incorrect email or password.";
        }
    } else {
        $error = "Incorrect email or password.";
    }
}
?>



<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sign In | MedEasyna</title>
    <link rel="stylesheet" href="../assets/css/main.css">
    <link rel="stylesheet" href="../assets/css/signin.css">
</head>
<body>
    <!-- Navigation Bar -->
    <div class="navbar">
    <h1>MedEasyna</h1>
    <div>
        <a href="../pages/index.php">Home</a>
        <a href="../pages/about.php">About</a>
        <a href="../pages/contact.php">Contact</a>
    </div>
</div>
    
    <div class="logo-container">
                    <img src="../assets/img/medeasyna-logo.png" alt="MedEasyna Logo" class="logo">
                    </div>
                    
    <main class="auth-container">
        <div class="auth-card">
            <div class="auth-header">
                <h2>Sign In</h2>
                <p>Access your account to manage appointments and records</p>
            </div>
            <?php if (isset($error)): ?>
                <div class="alert-error">
                    <?php echo htmlspecialchars($error); ?>
                </div>

            <?php endif; ?>
            

            <form method="POST" class="auth-form">
                <div class="form-group">
                    <label for="email">Email Address</label>
                    <input type="email" id="email" name="email" required>

                    <label for="password">Password</label>
                    <input type="password" id="password" name="password" required>
                    <div class="password-options">
                        <label class="show-password">
                            <input type="checkbox" id="showPassword"> Show Password
                        </label>
                        <a href="forgot_password.php" class="forgot-password">Forgot Password?</a>
                    </div>
                </div>

                <button type="submit" class="auth-btn">Sign In</button>
            </form>

            <div class="auth-footer">
                <p>Don't have an account? <a href="signup.php">Sign Up!</a></p>
            </div>
        </div>
    </main>


    <script>
        // Toggle password visibility
        document.getElementById('showPassword').addEventListener('change', function() {
            const passwordField = document.getElementById('password');
            passwordField.type = this.checked ? 'text' : 'password';
        });
    </script>

<footer class="app-footer">
    <div class="footer-content">
        <p class="copyright">&copy; <?php echo date("Y"); ?> MedEasyna</p>
    </div>
</footer>

</body>
</html>