document.addEventListener('DOMContentLoaded', function() {
    // Force all links to open in the same window
    document.querySelectorAll('a').forEach(link => {
        link.setAttribute('target', '_self');
    });

    // Handle form submissions normally
    document.querySelectorAll('form').forEach(form => {
        form.addEventListener('submit', function(e) {
            // No need to prevent default; let it submit normally
// Add this to your existing password validation code
document.getElementById('newPassword').addEventListener('input', function() {
    // ... existing validation code ...
    
    // Add this to your existing validation logic
    // Note: This is just client-side feedback - server-side validation is still needed
    const samePasswordWarning = document.getElementById('samePasswordWarning');
    if (samePasswordWarning) {
        samePasswordWarning.style.display = 'none';
    }
});
            if (window.opener && !window.opener.closed) {
                // Show a "Return to App" button
                const returnBtn = document.createElement('a');
                returnBtn.href = '#';
                returnBtn.textContent = '← Return to Application';
                returnBtn.style.display = 'block';
                returnBtn.style.margin = '10px 0';
                returnBtn.style.color = '#007BFF';
                returnBtn.onclick = (e) => {
                    e.preventDefault();
                    window.opener.location.href = window.location.href;
                    window.close();
                };
                document.querySelector('.auth-card').prepend(returnBtn);
                
            }
        });
    });
});