document.addEventListener('DOMContentLoaded', function() {
    // Load recent activity based on role
    const role = document.querySelector('.user-role-badge').classList.contains('receptionist') ? 'receptionist' : 'nurse';
    
    if (role === 'receptionist') {
        loadRecentWalkins();
    } else {
        loadRecentPatients();
    }
    
    // Add any other role-specific initialization here
});

function loadRecentWalkins() {
    // This would typically be an AJAX call to your backend
    const recentWalkins = [
        { name: 'John Doe', time: '10:30 AM', status: 'Waiting' },
        { name: 'Jane Smith', time: '11:15 AM', status: 'In Progress' },
        { name: 'Robert Johnson', time: '09:45 AM', status: 'Completed' }
    ];
    
    const container = document.getElementById('recentWalkins');
    container.innerHTML = recentWalkins.map(walkin => `
        <div class="activity-item">
            <div class="activity-details">
                <span class="patient-name">${walkin.name}</span>
                <span class="activity-time">${walkin.time}</span>
            </div>
            <span class="status-badge ${walkin.status.toLowerCase().replace(' ', '-')}">
                ${walkin.status}
            </span>
        </div>
    `).join('');
}

function loadRecentPatients() {
    // This would typically be an AJAX call to your backend
    const recentPatients = [
        { name: 'Michael Brown', lastVisit: 'Vitals check', time: 'Today, 10:00 AM' },
        { name: 'Sarah Wilson', lastVisit: 'Follow-up', time: 'Today, 09:30 AM' },
        { name: 'David Lee', lastVisit: 'Initial consultation', time: 'Yesterday' }
    ];
    
    const container = document.getElementById('recentPatients');
    container.innerHTML = recentPatients.map(patient => `
        <div class="activity-item">
            <div class="activity-details">
                <span class="patient-name">${patient.name}</span>
                <span class="activity-description">${patient.lastVisit}</span>
            </div>
            <span class="activity-time">${patient.time}</span>
        </div>
    `).join('');
}