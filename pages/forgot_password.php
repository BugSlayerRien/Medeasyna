<?php
session_start();
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require '../vendor/autoload.php';
require 'config.php';

// Initialize variables
$message = '';
$messageClass = '';
$showOtpForm = false;
$email = '';

// Process form submissions
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    if (isset($_POST['email'])) {
        // STEP 1: Email submission
        $email = trim($_POST['email']);
        
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $message = "Invalid email format.";
            $messageClass = 'alert-error';
        } else {
            // Check if user exists
            $stmt = $conn->prepare("SELECT userID, email FROM User WHERE email = ?");
            $stmt->bind_param("s", $email);
            $stmt->execute();
            $stmt->store_result();

            if ($stmt->num_rows > 0) {
                $stmt->bind_result($userID, $dbEmail);
                $stmt->fetch();
                
                // Generate OTP
                $otp = rand(100000, 999999);
                $expiresAt = date('Y-m-d H:i:s', strtotime('+10 minutes'));

                // Clear old OTPs
                $conn->query("DELETE FROM password_reset_otp WHERE userID = $userID");

                // Store new OTP
                $insert = $conn->prepare("INSERT INTO password_reset_otp (userID, otpCode, expiresAt) VALUES (?, ?, ?)");
                $insert->bind_param("iss", $userID, $otp, $expiresAt);
                
                if ($insert->execute()) {
                    // Send OTP via email
                    $mail = new PHPMailer(true);
                    try {
                        // Server settings
                        $mail->isSMTP();
                        $mail->Host       = 'smtp.gmail.com';
                        $mail->SMTPAuth   = true;
                        $mail->Username   = 'gappirien@gmail.com';
                        $mail->Password   = 'mjiy azxq pjmn srho';
                        $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
                        $mail->Port       = 465;

                        // Recipients
                        $mail->setFrom('gappirien@gmail.com', 'MedEasyna');
                        $mail->addAddress($email);

                        // Content
                        $mail->isHTML(true);
                        $mail->Subject = 'Your Password Reset OTP';
                        $mail->Body = <<<HTML
<!DOCTYPE html>
<html>
<head>
    <style>
        body { font-family: Arial, sans-serif; }
        .otp-code { 
            font-size: 24px; 
            letter-spacing: 3px; 
            color: #007BFF;
            font-weight: bold;
        }
    </style>
</head>
<body>
    <p>Your OTP code for password reset is:</p>
    <div class="otp-code">$otp</div>
    <p>This code will expire in 10 minutes.</p>
    <p><small>If you didn't request this, please ignore this email.</small></p>
</body>
</html>
HTML;
                        $mail->AltBody = "Your OTP code is: $otp\n\nThis code expires in 10 minutes.";

                        $mail->send();
                        $_SESSION['otp_user'] = [
                            'email' => $email,
                            'userID' => $userID
                        ];
                        $message = 'OTP has been sent to your email.';
                        $messageClass = 'alert-success';
                        $showOtpForm = true;
                    } catch (Exception $e) {
                        $message = "Failed to send OTP. Error: {$mail->ErrorInfo}";
                        $messageClass = 'alert-error';
                    }
                } else {
                    $message = "Error generating OTP.";
                    $messageClass = 'alert-error';
                }
            } else {
                $message = "No user found with that email.";
                $messageClass = 'alert-error';
            }
        }
    } elseif (isset($_POST['otp']) && isset($_POST['newPassword']) && isset($_SESSION['otp_user'])) {
        // STEP 2: OTP and new password submission
        $otp = $_POST['otp'];
        $newPassword = $_POST['newPassword'];
        $userID = $_SESSION['otp_user']['userID'];
        
        // Validate OTP format
        if (!preg_match('/^\d{6}$/', $otp)) {
            $message = "Invalid OTP format.";
            $messageClass = 'alert-error';
            $showOtpForm = true;
        } else {
            // Check password strength
            $validPassword = strlen($newPassword) >= 12 &&
                preg_match('/[A-Z]/', $newPassword) &&
                preg_match('/[0-9]/', $newPassword) &&
                preg_match('/[\W_]/', $newPassword);
            
            if (!$validPassword) {
                $message = "Password must be at least 12 characters with 1 uppercase letter, 1 number, and 1 symbol.";
                $messageClass = 'alert-error';
                $showOtpForm = true;
            } else {
                // Verify OTP
                $stmt = $conn->prepare("SELECT otpCode, expiresAt FROM password_reset_otp WHERE userID = ?");
                $stmt->bind_param("i", $userID);
                $stmt->execute();
                $result = $stmt->get_result();
                
                if ($result->num_rows === 1) {
                    $otpData = $result->fetch_assoc();
                    
                    // Check if OTP matches and is not expired
                    if ($otpData['otpCode'] !== $otp) {
                        $message = "Incorrect OTP.";
                        $messageClass = 'alert-error';
                        $showOtpForm = true;
                    } elseif (strtotime($otpData['expiresAt']) < time()) {
                        $message = "OTP has expired. Please request a new one.";
                        $messageClass = 'alert-error';
                        unset($_SESSION['otp_user']);
                    } else {
                        // Check if new password is different from current one
                        $stmt = $conn->prepare("SELECT passwordHash FROM User WHERE userID = ?");
                        $stmt->bind_param("i", $userID);
                        $stmt->execute();
                        $result = $stmt->get_result();
                        $user = $result->fetch_assoc();
                        
                        if (password_verify($newPassword, $user['passwordHash'])) {
                            $message = "New password cannot be the same as your current password.";
                            $messageClass = 'alert-error';
                            $showOtpForm = true;
                        } else {
                            // Update password
                            $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
                            $update = $conn->prepare("UPDATE User SET passwordHash = ? WHERE userID = ?");
                            $update->bind_param("si", $hashedPassword, $userID);
                            
                            if ($update->execute()) {
                                // Clean up
                                $conn->query("DELETE FROM password_reset_otp WHERE userID = $userID");
                                unset($_SESSION['otp_user']);
                                
                                $message = "Password successfully reset! You can now <a href='signin.php'>login</a> with your new password.";
                                $messageClass = 'alert-success';
                            } else {
                                $message = "Error updating password.";
                                $messageClass = 'alert-error';
                                $showOtpForm = true;
                            }
                        }
                    }
                } else {
                    $message = "No OTP found. Please request a new one.";
                    $messageClass = 'alert-error';
                    unset($_SESSION['otp_user']);
                }
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="Window-Target" content="_top">
    <base target="_self">
    <title>Forgot Password | MedEasyna</title>
    <link rel="stylesheet" href="../assets/css/main.css">
    <link rel="stylesheet" href="../assets/css/forgot_password.css">
    <style>
        .alert-success {
            color: green;
            padding: 10px;
            margin: 10px 0;
            border: 1px solid green;
            border-radius: 4px;
            background-color: #e6ffe6;
        }
        .alert-error {
            color: red;
            padding: 10px;
            margin: 10px 0;
            border: 1px solid red;
            border-radius: 4px;
            background-color: #ffe6e6;
        }
        .otp-form {
            display: <?php echo $showOtpForm ? 'block' : 'none'; ?>;
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
                <h2>Forgot Password</h2>
                <p><?php echo $showOtpForm ? 'Enter the OTP sent to your email' : 'Enter your email to receive a password reset OTP'; ?></p>
            </div>

            <?php if ($message): ?>
                <div class="<?php echo $messageClass; ?>">
                    <?php echo $message; ?>
                </div>
            <?php endif; ?>

            <?php if (!$showOtpForm): ?>
                <!-- Step 1: Email Form -->
                <form method="POST" class="auth-form">
                    <input type="email" name="email" required placeholder="Enter your registered email" value="<?php echo htmlspecialchars($email); ?>">
                    <button type="submit">Send OTP</button>
                </form>
            <?php else: ?>
                <!-- Step 2: OTP Form -->
                <form method="POST" class="auth-form">
                    <input type="text" name="otp" pattern="\d{6}" required placeholder="6-digit OTP" maxlength="6">
                    <input type="password" name="newPassword" required minlength="12" 
                        pattern="^(?=.*[A-Z])(?=.*\d)(?=.*[\W_]).{12,}$"
                        title="At least 12 characters with 1 uppercase, 1 number, and 1 symbol"
                        placeholder="New Password">
                    <button type="submit">Reset Password</button>
                </form>
            <?php endif; ?>

            <div class="auth-footer">
                <p>Remember your password? <a href="signin.php">Sign In</a></p>
            </div>
        </div>
    </main>



    <script>
document.addEventListener('DOMContentLoaded', function() {
    const passwordInput = document.querySelector('input[name="newPassword"]');
    if (passwordInput) {
        // Create toggle button
        const toggleBtn = document.createElement('button');
        toggleBtn.type = 'button';
        toggleBtn.className = 'toggle-password';
        toggleBtn.innerHTML = '👁️';
        toggleBtn.setAttribute('aria-label', 'Show password');
        
        // Insert after password input
        const container = document.createElement('div');
        container.className = 'password-input-container';
        passwordInput.parentNode.insertBefore(container, passwordInput);
        container.appendChild(passwordInput);
        container.appendChild(toggleBtn);
        
        // Toggle functionality
        toggleBtn.addEventListener('click', function() {
            const type = passwordInput.getAttribute('type') === 'password' ? 'text' : 'password';
            passwordInput.setAttribute('type', type);
            this.innerHTML = type === 'password' ? '👁️' : '👁️‍🗨️';
            this.setAttribute('aria-label', type === 'password' ? 'Show password' : 'Hide password');
        });
    }
});
</script>

<footer class="app-footer">
    <div class="footer-content">
        <p class="copyright">&copy; <?php echo date("Y"); ?> MedEasyna</p>
    </div>
</footer>

</body>
</html>