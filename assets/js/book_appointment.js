
document.addEventListener('DOMContentLoaded', function() {
    // Display day of week when date is selected
    document.getElementById('appointmentDate').addEventListener('change', function() {
        const dateInput = this.value;
        if (dateInput) {
            const date = new Date(dateInput);
            const options = { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' };
            const dayOfWeek = date.toLocaleDateString('en-US', options);
            document.getElementById('dayOfWeek').textContent = dayOfWeek;
            
            // Update instructions based on day
            const weekday = date.toLocaleDateString('en-US', { weekday: 'long' });
            let instructions = '';
            
            if (weekday === 'Sunday') {
                instructions = 'Note: Most doctors are unavailable on Sundays.';
            } else if (weekday === 'Saturday') {
                instructions = 'Saturday appointments may have limited availability.';
            } else {
                instructions = 'Standard weekday availability.';
            }
            
            document.getElementById('dateInstructions').textContent = instructions;
            
            // Load available time slots
            fetchAvailableSlots();
        }
    });

    // Also fetch slots when doctor changes
    document.getElementById('doctorID').addEventListener('change', fetchAvailableSlots);
});

function fetchAvailableSlots() {
    const doctorID = document.getElementById("doctorID").value;
    const date = document.getElementById("appointmentDate").value;
    const container = document.getElementById("timeSlotContainer");
    
    if (!doctorID || !date) {
        container.innerHTML = '<div class="no-slots">Please select both doctor and date</div>';
        return;
    }
    
    // Show loading state
    container.innerHTML = '<div class="loading-slots">Loading available time slots...</div>';
    
    fetch(`../includes/fetch_timeslots.php?doctorID=${doctorID}&date=${date}`)
        .then(response => {
            if (!response.ok) throw new Error('Network error');
            return response.json();
        })
        .then(data => {
            container.innerHTML = '';
            
            if (data.error) {
                container.innerHTML = `<div class="error-slots">${data.error}</div>`;
                return;
            }
            
            if (data.slots && data.slots.length > 0) {
                data.slots.forEach(slot => {
                    const timeSlot = document.createElement("div");
                    timeSlot.className = "time-slot";
                    timeSlot.textContent = formatTime(slot);
                    timeSlot.dataset.time = slot;
                    
                    timeSlot.addEventListener('click', function() {
                        // Remove previous selection
                        document.querySelectorAll('.time-slot.selected').forEach(el => {
                            el.classList.remove('selected');
                        });
                        
                        // Select this slot
                        this.classList.add('selected');
                        document.getElementById("appointmentTime").value = slot;
                    });
                    
                    container.appendChild(timeSlot);
                });
            } else {
                container.innerHTML = '<div class="no-slots">No available time slots for this date</div>';
            }
        })
        .catch(error => {
            console.error('Error:', error);
            container.innerHTML = '<div class="error-slots">Error loading time slots. Please try again.</div>';
        });
}

function formatTime(timeString) {
    const [hours, minutes] = timeString.split(':');
    const hour = parseInt(hours);
    const ampm = hour >= 12 ? 'PM' : 'AM';
    const displayHour = hour % 12 || 12;
    return `${displayHour}:${minutes} ${ampm}`;
}
