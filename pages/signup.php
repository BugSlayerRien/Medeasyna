<?php
include 'config.php';
session_start();

if (isset($_POST['signup'])) {
    $firstName = trim($_POST['firstName']);
    $lastName = trim($_POST['lastName']);
    $email = trim($_POST['email']);
    $password = trim($_POST['password']);
    $confirmPassword = trim($_POST['confirmPassword']);
    $phoneNumber = trim($_POST['phoneNumber']);
    $dateOfBirth = trim($_POST['dateOfBirth']);
    $hashedPassword = password_hash($password, PASSWORD_DEFAULT);

    // Form validation
    if ($password !== $confirmPassword) {
        $error = "Passwords do not match.";
    } elseif (strlen($password) < 12 || !preg_match('/[A-Z]/', $password) || 
             !preg_match('/[a-z]/', $password) || !preg_match('/\d/', $password) ||
             !preg_match('/[^a-zA-Z0-9]/', $password)) {
        $error = "Password must be at least 12 characters long, contain one uppercase letter, one lowercase letter, one number, and one symbol.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Please enter a valid email address.";
    } elseif (!preg_match("/^09\d{9}$/", $phoneNumber)) {
        $error = "Phone number must be 11 digits and start with 09.";
    } elseif (empty($firstName) || empty($lastName) || empty($email) || 
             empty($password) || empty($phoneNumber) || empty($dateOfBirth)) {
        $error = "All fields are required.";
    } else {
        // Age validation (must be 18+)
        $today = new DateTime();
        $birthDate = new DateTime($dateOfBirth);
        $age = $today->diff($birthDate)->y;
        
        if ($age < 18) {
            $error = "You must be at least 18 years old to register.";
        } else {
            // Check if email or phone already exists
            $stmt = $conn->prepare("SELECT userID FROM User WHERE email = ? OR phoneNumber = ?");
            $stmt->bind_param("ss", $email, $phoneNumber);
            $stmt->execute();
            $stmt->store_result();
            
            if ($stmt->num_rows > 0) {
                $error = "Email or phone number already registered.";
            } else {
                // Insert user
                $stmt = $conn->prepare("INSERT INTO User (firstName, lastName, email, passwordHash, phoneNumber, dateOfBirth, role) VALUES (?, ?, ?, ?, ?, ?, 'Patient')");
                $stmt->bind_param("ssssss", $firstName, $lastName, $email, $hashedPassword, $phoneNumber, $dateOfBirth);
                
                if ($stmt->execute()) {
                    $_SESSION['signup_success'] = true;
                    header("Location: signup.php");
                    exit();
                } else {
                    $error = "Signup failed. Please try again.";
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
    <title>Doval Santos Diagnostic Center | Sign Up</title>
    <link rel="stylesheet" href="../assets/css/main.css">
    <link rel="stylesheet" href="../assets/css/signup.css">
    <style>
        .success-message {
            background-color: #4CAF50;
            color: white;
            padding: 15px;
            margin-bottom: 20px;
            border-radius: 4px;
            text-align: center;
        }
        .form-group {
            margin-bottom: 15px;
        }
        .form-group label {
            display: block;
            margin-bottom: 5px;
            font-weight: bold;
        }
        .error {
            color: #f44336;
            margin-bottom: 15px;
        }
    </style>
</head>
<body>
    
<div class="navbar">
    <h1>Doval Santos Diagnostic Center</h1>
    <div>
        <a href="../pages/index.php">Home</a>
        <a href="../pages/about.php">About</a>
        <a href="../pages/contact.php">Contact</a>
    </div>
</div>

    <main class="main-content">
        <button class="back-btn" onclick="window.location.href='index.php'"></button>
        
        <img src="../assets/img/dovalsantoslogo.png" alt="doval Logo" class="logo">

        <div class="login-container">
            <h2>Create Your Account</h2>
            
            <?php if (isset($_SESSION['signup_success'])): ?>
                <div class="success-message">
                    Account successfully created! You can now <a href="signin.php" style="color: white; text-decoration: underline;">sign in</a>.
                </div>
                <?php unset($_SESSION['signup_success']); ?>
            <?php endif; ?>
            
            <?php if (isset($error)): ?>
                <p class="error"><?php echo htmlspecialchars($error); ?></p>
            <?php endif; ?>
            
            <form method="POST" onsubmit="return validateForm()">
                <div class="form-group">
                    <input type="text" name="firstName" placeholder="First Name" required>
                </div>
                <div class="form-group">
                    <input type="text" name="lastName" placeholder="Last Name" required>
                </div>
                <div class="form-group">
                    <input type="email" name="email" placeholder="Email" required>
                </div>
                <div class="form-group">
                    <input type="password" name="password" placeholder="Password" required>
                </div>
                <div class="form-group">
                    <input type="password" name="confirmPassword" placeholder="Confirm Password" required>
                </div>
                
                <div class="password-requirements">
                    <p>Password must contain:</p>
                    <ul>
                        <li>At least 12 characters</li>
                        <li>One uppercase letter</li>
                        <li>One lowercase letter</li>
                        <li>One number</li>
                        <li>One symbol</li>
                    </ul>
                </div>
                
                <div class="form-group">
                    <input type="text" name="phoneNumber" placeholder="Phone Number (09XXXXXXXXX)" required pattern="^09\d{9}$">
                </div>
                
                <div class="form-group">
                    <label for="dateOfBirth">Date of Birth</label>
                    <input type="date" name="dateOfBirth" id="dateOfBirth" 
                           max="<?php echo date('Y-m-d', strtotime('-18 years')); ?>" 
                           min="<?php echo date('Y-m-d', strtotime('-100 years')); ?>" required>
                </div>
                
                <input type="hidden" name="role" value="Patient">
                
                <button type="submit" name="signup">Sign Up</button>
            </form>

            <p>Already have an account? <a href="signin.php">Sign In</a></p>
        </div>
    </main>

    <script>
        function validateForm() {
            // Existing validations
            const email = document.querySelector('input[name="email"]').value;
            const password = document.querySelector('input[name="password"]').value;
            const confirmPassword = document.querySelector('input[name="confirmPassword"]').value;
            const phoneNumber = document.querySelector('input[name="phoneNumber"]').value;
            const dateOfBirth = document.querySelector('input[name="dateOfBirth"]').value;
        
            // Email validation
            const emailPattern = /^[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,4}$/;
            if (!emailPattern.test(email)) {
                alert("Please enter a valid email address.");
                return false;
            }

            // Password validation
            const passwordPattern = /^(?=.*[A-Z])(?=.*[a-z])(?=.*\d)(?=.*[^a-zA-Z0-9]).{12,}$/;
            if (!passwordPattern.test(password)) {
                alert("Password must be at least 12 characters long and include at least one uppercase letter, one lowercase letter, one number, and one symbol.");
                return false;
            }

            if (password !== confirmPassword) {
                alert("Passwords do not match.");
                return false;
            }

            // Phone validation
            const phonePattern = /^09\d{9}$/;
            if (!phonePattern.test(phoneNumber)) {
                alert("Phone number must be 11 digits and start with 09.");
                return false;
            }

            // Age validation
            if (!dateOfBirth) {
                alert("Please enter your date of birth.");
                return false;
            }
            
            const birthDate = new Date(dateOfBirth);
            const today = new Date();
            let age = today.getFullYear() - birthDate.getFullYear();
            const monthDiff = today.getMonth() - birthDate.getMonth();
            
            if (monthDiff < 0 || (monthDiff === 0 && today.getDate() < birthDate.getDate())) {
                age--;
            }
            
            if (age < 18) {
                alert("You must be at least 18 years old to register.");
                return false;
            }

            return true;
        }
    </script>

<footer class="app-footer">
    <div class="footer-content">
        <p class="copyright">&copy; <?php echo date("Y"); ?> Doval Santos Diagnostic Center</p>
    </div>
</footer>

</body>
</html>