function goBack() {
    window.history.back();
}
function togglePassword(fieldId) {
    const passwordField = document.getElementById(fieldId);
    const toggleIcon = passwordField.nextElementSibling; // Get the eye icon
    
    if (passwordField.type === 'password') {
        passwordField.type = 'text';
        toggleIcon.textContent = '👁️';
    } else {
        passwordField.type = 'password';
        toggleIcon.textContent = '👁️';
    }
// Check if passwords match
function validatePasswords() {
    const password = document.getElementById("password").value;
    const confirm = document.getElementById("confirmPassword").value;
    const matchMsg = document.getElementById("passwordMatch");

    if (password && confirm && password !== confirm) {
        matchMsg.style.display = "block";
    } else {
        matchMsg.style.display = "none";
        document.getElementById("confirmPassword").addEventListener("input", validatePasswords);
document.getElementById("password").addEventListener("input", validatePasswords);
document.getElementById("phoneNumber").addEventListener("input", validatePhone);

    }
}

// Check phone number format
function validatePhone() {
    const phone = document.getElementById("phoneNumber").value;
    const phoneMsg = document.getElementById("phoneValidation");
    const valid = /^09\d{9}$/.test(phone);

    phoneMsg.style.display = valid || phone === "" ? "none" : "block";
}
}
