function togglePassword() {
    var passwordInput = document.getElementById('password');
    var eyeIcon = document.querySelector('.eye-icon svg');
    if (passwordInput.type === 'password') {
        passwordInput.type = 'text';
        eyeIcon.setAttribute('d', 'M12 4.5c-3.29 0-6 2.71-6 6 0 1.85 1.08 3.41 2.64 4.34-.02.1-.04.2-.04.31 0 2.1 1.69 3.8 3.77 3.93.18 1.25.72 2.27 1.46 3.08-1.2.58-2.6.89-4.07.89-6.63 0-12-5.37-12-12S5.37 0 12 0c2.7 0 5.15 1.03 7 2.73 1.8 1.42 3.16 3.38 4.03 5.49.06-.58.1-1.16.1-1.73 0-5.52-4.48-10-10-10z');
    } else {
        passwordInput.type = 'password';
        eyeIcon.setAttribute('d', 'M12 4.5c-3.29 0-6 2.71-6 6 0 1.85 1.08 3.41 2.64 4.34-.02.1-.04.2-.04.31 0 2.1 1.69 3.8 3.77 3.93.18 1.25.72 2.27 1.46 3.08-1.2.58-2.6.89-4.07.89-6.63 0-12-5.37-12-12S5.37 0 12 0c2.7 0 5.15 1.03 7 2.73 1.8 1.42 3.16 3.38 4.03 5.49.06-.58.1-1.16.1-1.73 0-5.52-4.48-10-10-10z');
    }
}
