<?php
session_start();
require 'config.php';

$step = 1;
$message = '';
$email = '';
$showOtpForm = false;

// 🟡 STEP 1: Handle email input
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submitEmail'])) {
    $email = trim($_POST['email'] ?? '');

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $message = "❌ Invalid email format.";
    } else {
        // Check if user exists
        $stmt = $conn->prepare("SELECT userID FROM user WHERE email = ?");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows !== 1) {
            $message = "❌ No account found with that email.";
        } else {
            $user = $result->fetch_assoc();
            $userID = $user['userID'];

            // Generate OTP
            $otp = rand(100000, 999999);
            $expiresAt = date('Y-m-d H:i:s', strtotime('+10 minutes'));

            // Clear old OTPs
            $conn->query("DELETE FROM password_reset_otp WHERE userID = $userID");

            // Store new OTP
            $insert = $conn->prepare("INSERT INTO password_reset_otp (userID, otpCode, expiresAt) VALUES (?, ?, ?)");
            $insert->bind_param("iss", $userID, $otp, $expiresAt);
            $insert->execute();

            // Send email (optional)
            // mail($email, "Your OTP Code", "Your OTP is $otp");

            $_SESSION['otp_user'] = [
                'email' => $email,
                'userID' => $userID,
            ];

            $message = "✅ OTP sent to $email";
            $showOtpForm = true;
        }
    }
}

// 🟢 STEP 2: Handle OTP + new password
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submitOtp'])) {
    $otp = $_POST['otp'] ?? '';
    $newPassword = $_POST['newPassword'] ?? '';

    // Regex validation for strict password rules
    $validPassword = strlen($newPassword) >= 12 &&
        preg_match('/[A-Z]/', $newPassword) &&
        preg_match('/[0-9]/', $newPassword) &&
        preg_match('/[\W_]/', $newPassword); // symbol or special char

    if (!preg_match('/^\d{6}$/', $otp)) {
        $message = "❌ Invalid OTP format.";
        $showOtpForm = true;
    } elseif (!$validPassword) {
        $message = "❌ Password must be at least 12 characters and include 1 uppercase letter, 1 number, and 1 symbol.";
        $showOtpForm = true;
    } elseif (!isset($_SESSION['otp_user'])) {
        $message = "❌ Session expired. Please try again.";
    } else {
        $userID = $_SESSION['otp_user']['userID'];

        $stmt = $conn->prepare("SELECT otpCode, expiresAt FROM password_reset_otp WHERE userID = ?");
        $stmt->bind_param("i", $userID);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows === 1) {
            $otpData = $result->fetch_assoc();
            if ($otpData['otpCode'] !== $otp) {
                $message = "❌ Incorrect OTP.";
                $showOtpForm = true;
            } elseif (strtotime($otpData['expiresAt']) < time()) {
                $message = "❌ OTP expired.";
                $showOtpForm = true;
            } else {
                // Update password
                $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
                $update = $conn->prepare("UPDATE user SET passwordHash = ? WHERE userID = ?");
                $update->bind_param("si", $hashedPassword, $userID);
                $update->execute();

                // Clean up
                $conn->query("DELETE FROM password_reset_otp WHERE userID = $userID");
                unset($_SESSION['otp_user']);

                $message = "✅ Password successfully reset!";
            }
        } else {
            $message = "❌ No OTP found. Please try again.";
        }
    }
}
?>

<!-- 🔵 HTML FORM -->
<!DOCTYPE html>
<html>
<head>
    <title>Reset Password</title>
</head>
<body>
    <h2>Reset Password</h2>

    <?php if (!empty($message)) : ?>
        <p style="color:<?= strpos($message, '✅') === 0 ? 'green' : 'red' ?>"><?= htmlspecialchars($message) ?></p>
    <?php endif; ?>

    <?php if (!$showOtpForm && !isset($_SESSION['otp_user'])) : ?>
        <!-- Step 1: Enter Email -->
        <form method="POST">
            <label>Email Address:</label><br>
            <input type="email" name="email" required><br><br>
            <button type="submit" name="submitEmail">Send OTP</button>
        </form>

    <?php else : ?>
        <!-- Step 2: Enter OTP and New Password -->
        <form method="POST">
            <label>Enter OTP:</label><br>
            <input type="text" name="otp" pattern="\d{6}" required placeholder="6-digit code"><br><br>

            <label>New Password:</label><br>
            <input type="password" name="newPassword" required minlength="12"
                pattern="^(?=.*[A-Z])(?=.*\d)(?=.*[\W_]).{12,}$"
                title="At least 12 characters, with 1 uppercase letter, 1 number, and 1 symbol."><br><br>

            <button type="submit" name="submitOtp">Reset Password</button>
        </form>
    <?php endif; ?>

    <footer class="app-footer">
    <div class="footer-content">
        <p class="copyright">&copy; <?php echo date("Y"); ?> Doval Santos Diagnostic Center</p>
    </div>
</footer>

</body>
</html>
