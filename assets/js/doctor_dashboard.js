function toggleNotifications() {
    const notificationList = document.getElementById('all-notifications');
    if (notificationList.style.display === 'block') {
        notificationList.style.display = 'none';
    } else {
        notificationList.style.display = 'block';
    }
}

// Close notifications when clicking outside
document.addEventListener('click', function(event) {
    const notifications = document.getElementById('notifications');
    const notificationList = document.getElementById('all-notifications');
    if (!notifications.contains(event.target) && notificationList.style.display === 'block') {
        notificationList.style.display = 'none';
    }
function toggleNotifications() {
    var list = document.getElementById('notificationList');
    if (list.style.display === "none") {
        list.style.display = "block";
    } else {
        list.style.display = "none";
    }
}

document.addEventListener('DOMContentLoaded', function() {
    // Toggle notifications
    const toggleBtn = document.getElementById('toggleNotifications');
    const notificationContainer = document.getElementById('notificationContainer');
    
    toggleBtn.addEventListener('click', function() {
        notificationContainer.classList.toggle('expanded');
        this.textContent = notificationContainer.classList.contains('expanded') 
            ? 'Hide Notifications' 
            : 'Show All';
    });

    // Delete notification
    document.querySelectorAll('.delete-btn').forEach(button => {
        button.addEventListener('click', function() {
            const notification = this.closest('.notification-item');
            const notificationID = notification.dataset.id;
            
            if (confirm('Are you sure you want to delete this notification?')) {
                fetch('delete_notification.php', {
                    method: 'POST',
                    headers: { 
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: `notificationID=${notificationID}`
                })
                .then(response => {
                    if (response.ok) {
                        notification.style.opacity = '0';
                        setTimeout(() => notification.remove(), 300);
                    } else {
                        alert('Failed to delete notification');
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('An error occurred while deleting');
                });
            }
        });
    });
});
});

