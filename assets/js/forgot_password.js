// assets/js/forgot_password.js
document.addEventListener('DOMContentLoaded', function() {
    // Intercept all link clicks
    document.querySelectorAll('a').forEach(link => {
        link.addEventListener('click', function(e) {
            // Only handle internal links
            if (this.href && !this.hasAttribute('target') && 
                (this.href.startsWith(window.location.origin) || this.href.startsWith('/'))) {
                e.preventDefault();
                window.location.href = this.href;
            }
        });
    });
    
    // Add target="_self" to all internal links
    document.querySelectorAll('a[href^="/"], a[href^="' + window.location.origin + '"]').forEach(link => {
        link.setAttribute('target', '_self');
    });
});