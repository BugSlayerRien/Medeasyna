<!-- select_time_slot.php -->
<form action="book_appointment.php" method="POST">
    <label for="appointment_time">Select Time</label>
    <select name="appointment_time">
        <?php foreach ($availableTimes as $time): ?>
            <option value="<?php echo $time; ?>"><?php echo $time; ?></option>
        <?php endforeach; ?>
    </select>
    
    <input type="submit" value="Book Appointment">
</form>
