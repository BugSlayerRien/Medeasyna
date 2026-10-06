<?php
// Include database connection
include 'config.php';

// Initialize message variables
$message = '';
$messageClass = '';

if (isset($_GET['token'])) {
    $token = $_GET['token'];

    // Query to check if the token exists and is not expired
    $stmt = $conn->prepare("SELECT userID, reset_token, reset_token_expiry FROM User WHERE reset_token = ?");
    $stmt->bind_param("s", $token);
    $stmt->execute();
    $stmt->store_result();

    if ($stmt->num_rows > 0) {
        $stmt->bind_result($userID, $storedToken, $expiry);
        $stmt->fetch();

        // Check if the token has expired
        if (strtotime($expiry) > time()) {
            // Token is valid and not expired
            if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['newPassword'])) {
                // Process password reset
                $newPassword = $_POST['newPassword'];
                $valid = true;

                // Validate password requirements
                if (!preg_match('/[A-Z]/', $newPassword)) {
                    $message = "Password must contain at least one uppercase letter.";
                    $valid = false;
                } elseif (!preg_match('/[0-9]/', $newPassword)) {
                    $message = "Password must contain at least one number.";
                    $valid = false;
                } elseif (strlen($newPassword) < 12) {
                    $message = "Password must be at least 12 characters long.";
                    $valid = false;
                } elseif (!preg_match('/[^a-zA-Z0-9]/', $newPassword)) {
                    $message = "Password must contain at least one symbol.";
                    $valid = false;
                }

                if ($valid) {
                    // First, get the current password hash
                    $currentPassStmt = $conn->prepare("SELECT passwordHash FROM User WHERE userID = ?");
                    $currentPassStmt->bind_param("i", $userID);
                    $currentPassStmt->execute();
                    $currentPassStmt->bind_result($currentPasswordHash);
                    $currentPassStmt->fetch();
                    $currentPassStmt->close();
                    
                    // Check if new password is the same as the old one
                    if (password_verify($newPassword, $currentPasswordHash)) {
                        $message = "New password cannot be the same as your old password.";
                        $messageClass = 'alert-error';
                        $valid = false;
                    }
                    
                    if ($valid) {
                        $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
                        
                        // Update the user's password
                        $updateStmt = $conn->prepare("UPDATE User SET passwordHash = ?, reset_token = NULL, reset_token_expiry = NULL WHERE userID = ?");
                        $updateStmt->bind_param("si", $hashedPassword, $userID);
                
                        if ($updateStmt->execute()) {
                            $message = "Your password has been successfully reset! You can now <a href='signin.php'>sign in</a> with your new password.";
                            $messageClass = 'alert-success';
                            // Clear the password field
                            $_POST['newPassword'] = '';
                        } else {
                            $message = "There was an error resetting your password.";
                            $messageClass = 'alert-error';
                        }
                        $updateStmt->close();
                    }
                } else {
                    $messageClass = 'alert-error';
                }
            }
        } else {
            $message = "The token has expired.";
            $messageClass = 'alert-error';
        }
    } else {
        $message = "Invalid or expired token.";
        $messageClass = 'alert-error';
    }

    $stmt->close();
} else {
    $message = "No reset token provided.";
    $messageClass = 'alert-error';
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reset Password | MedEasyna</title>
    <link rel="stylesheet" href="../assets/css/signin.css">
    <base target="_self">
    <style>
        .show-password-container {
            display: flex;
            align-items: center;
            margin-top: 10px;
        }
        
        .show-password-container input[type="checkbox"] {
            margin-right: 8px;
        }
        
        .password-requirements {
            margin-top: 15px;
            padding: 10px;
            background-color: #f8f9fa;
            border-radius: 4px;
            border: 1px solid #ddd;
        }
        
        .password-requirements ul {
            list-style-type: none;
            padding-left: 20px;
        }
        
        .password-requirements li {
            margin-bottom: 5px;
            position: relative;
        }
        
        .password-requirements li:before {
            content: "•";
            color: #007BFF;
            position: absolute;
            left: -15px;
        }
        
        .alert-error {
            color: #dc3545;
            background-color: #f8d7da;
            padding: 10px;
            border-radius: 4px;
            margin-bottom: 15px;
            border: 1px solid #f5c6cb;
        }
        
        .alert-success {
            color: #155724;
            background-color: #d4edda;
            padding: 10px;
            border-radius: 4px;
            margin-bottom: 15px;
            border: 1px solid #c3e6cb;
        }
    </style>
</head>
<body>
    <!-- Navigation Bar -->
    <header class="navbar">
        <div class="navbar-brand">
            <h1>MedEasyna</h1>
        </div>
        <nav class="navbar-menu">
            <a href="../pages/index.php">Home</a>
            <a href="../pages/about.php">About Us</a>
            <a href="../pages/contact.php">Contact</a>
        </nav>
    </header>

    <!-- Main Content -->
    <main class="auth-container">
        <div class="auth-card">
            <div class="auth-header">
                <h2>Reset Password</h2>
                <p>Enter your new password below</p>
            </div>

            <?php if ($message): ?>
                <div class="<?php echo $messageClass; ?>">
                    <?php echo $message; ?>
                </div>
            <?php endif; ?>

            <?php if (!isset($messageClass) || $messageClass !== 'alert-success'): ?>
                <form method="POST" action="" class="auth-form" id="resetForm">
                    <input type="hidden" name="token" value="<?php echo htmlspecialchars($_GET['token']); ?>">

                    <div class="form-group">
                        <label for="newPassword">New Password</label>
                        <input type="password" id="newPassword" name="newPassword" 
                            value="<?php echo isset($_POST['newPassword']) ? htmlspecialchars($_POST['newPassword']) : ''; ?>" 
                            required>
                        
                        <div class="show-password-container">
                            <input type="checkbox" id="showPassword" onchange="togglePassword()">
                            <label for="showPassword">Show Password</label>
                        </div>
                        
                        <div class="password-requirements">
                            <p>Password must meet the following requirements:</p>
                            <ul>
                                <li id="length">At least 12 characters long</li>
                                <li id="uppercase">Contain at least one uppercase letter</li>
                                <li id="number">Contain at least one number</li>
                                <li id="symbol">Contain at least one symbol</li>
                                <li id="samePasswordWarning" style="display: none; color: #dc3545;">Cannot be the same as old password</li>
                            </ul>
                        </div>
                    </div>

                    <button type="submit" class="auth-btn" id="submitBtn" disabled>Reset Password</button>
                </form>
            <?php endif; ?>

            <div class="auth-footer">
                <p>Remembered your password? <a href="signin.php">Sign In</a></p>
            </div>
        </div>
    </main>

    <!-- Footer -->
    <footer class="app-footer">
        <div class="footer-content">
            <div class="footer-links">
                <a href="../privacy.php">Privacy Policy</a>
                <a href="../terms.php">Terms of Service</a>
                <a href="../contact.php">Contact Us</a>
                <a href="../faq.php">FAQ</a>
            </div>
            <p class="copyright">&copy; <?php echo date("Y"); ?> MedEasyna. All rights reserved.</p>
        </div>
    </footer>

    <script>
        // Password toggle functionality
        function togglePassword() {
            const passwordField = document.getElementById('newPassword');
            const showPassword = document.getElementById('showPassword');
            passwordField.type = showPassword.checked ? 'text' : 'password';
        }

        // Real-time password validation
        document.getElementById('newPassword').addEventListener('input', function() {
            const password = this.value;
            const lengthValid = password.length >= 12;
            const uppercaseValid = /[A-Z]/.test(password);
            const numberValid = /[0-9]/.test(password);
            const symbolValid = /[^a-zA-Z0-9]/.test(password);
            
            // Update validation indicators
            updateRequirement('length', lengthValid);
            updateRequirement('uppercase', uppercaseValid);
            updateRequirement('number', numberValid);
            updateRequirement('symbol', symbolValid);
            
            // Enable/disable submit button
            document.getElementById('submitBtn').disabled = !(lengthValid && uppercaseValid && numberValid && symbolValid);
        });

        function updateRequirement(id, isValid) {
            const element = document.getElementById(id);
            if (isValid) {
                element.style.color = "#28a745";
                element.style.fontWeight = "bold";
            } else {
                element.style.color = "";
                element.style.fontWeight = "";
            }
        }

        // Handle new tab detection
        if (window.opener && !window.opener.closed) {
            const returnBtn = document.createElement('button');
            returnBtn.textContent = '← Return to Original Window';
            returnBtn.style.margin = '10px 0';
            returnBtn.style.padding = '8px 15px';
            returnBtn.style.backgroundColor = '#f0f0f0';
            returnBtn.style.border = '1px solid #ddd';
            returnBtn.style.borderRadius = '4px';
            returnBtn.style.cursor = 'pointer';
            returnBtn.onclick = () => {
                window.opener.location.href = window.location.href;
                window.close();
            };
            document.querySelector('.auth-card').prepend(returnBtn);
        }
    </script>
</body>
</html>












<?php
require 'config.php';

$email = $_POST['email'] ?? '';
$otp = $_POST['otp'] ?? '';
$newPassword = $_POST['newPassword'] ?? '';

$stmt = $conn->prepare("SELECT userID FROM user WHERE email = ?");
$stmt->bind_param("s", $email);
$stmt->execute();
$userResult = $stmt->get_result();

if ($userResult->num_rows === 1) {
    $user = $userResult->fetch_assoc();
    $userID = $user['userID'];

    $otpStmt = $conn->prepare("SELECT otpCode, expiresAt FROM password_reset_otp WHERE userID = ? AND otpCode = ?");
    $otpStmt->bind_param("is", $userID, $otp);
    $otpStmt->execute();
    $otpResult = $otpStmt->get_result();

    if ($otpResult->num_rows === 1) {
        $otpRow = $otpResult->fetch_assoc();

        if (strtotime($otpRow['expiresAt']) >= time()) {
            // Valid OTP
            $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
            $update = $conn->prepare("UPDATE user SET passwordHash = ? WHERE userID = ?");
            $update->bind_param("si", $hashedPassword, $userID);
            $update->execute();

            // Remove OTP
            $conn->query("DELETE FROM password_reset_otp WHERE userID = $userID");

            echo "Password reset successfully.";
        } else {
            echo "OTP expired.";
        }
    } else {
        echo "Invalid OTP.";
    }
} else {
    echo "User not found.";
}
?>


<form method="POST" action="verifyOtp.php">
  <input type="email" name="email" required placeholder="Enter your email">
  <input type="text" name="otp" required placeholder="Enter OTP">
  <input type="password" name="newPassword" required placeholder="New Password">
  <button type="submit">Reset Password</button>
</form>
