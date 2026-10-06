
<?php
include 'config.php';
session_start();

if (!isset($_SESSION['userID']) || $_SESSION['role'] !== 'Patient') {
    header("Location: index.php"); 
    exit();
}

$patientID = $_SESSION['userID'];
$error = '';
$success = '';

// Fetch active services
$services = $conn->query("SELECT * FROM Service WHERE deletedAt IS NULL")->fetch_all(MYSQLI_ASSOC);

// Fetch available doctors
$doctors = $conn->query("
    SELECT u.userID, u.firstName, u.lastName, d.specialization,
    d.working_days, d.start_time, d.end_time, d.appointment_duration
    FROM User u
    JOIN Doctor d ON u.userID = d.doctorID
    WHERE u.role = 'Doctor'
    ORDER BY u.lastName, u.firstName
")->fetch_all(MYSQLI_ASSOC);

// Fetch patient's scheduled appointments
$appointments = $conn->query("
    SELECT a.appointmentID, a.appointmentDate, a.appointmentTime, a.status,
           CONCAT(u.firstName, ' ', u.lastName) AS doctorName, d.specialization,
           GROUP_CONCAT(s.serviceName SEPARATOR ', ') AS services
    FROM Appointment a
    JOIN User u ON a.doctorID = u.userID
    JOIN Doctor d ON u.userID = d.doctorID
    LEFT JOIN AppointmentService aps ON a.appointmentID = aps.appointmentID
    LEFT JOIN Service s ON aps.serviceID = s.serviceID
    WHERE a.patientID = $patientID
    AND a.status = 'Scheduled'
    AND a.deletedAt IS NULL
    GROUP BY a.appointmentID
    ORDER BY a.appointmentDate, a.appointmentTime
")->fetch_all(MYSQLI_ASSOC);

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['bookAppointment'])) {
    // Sanitize and validate inputs
    $doctorID = intval($_POST['doctorID']);
    $appointmentDate = $conn->real_escape_string($_POST['appointmentDate']);
    $appointmentTime = $conn->real_escape_string($_POST['appointmentTime']);
    $reasonForVisit = $conn->real_escape_string($_POST['reasonForVisit'] ?? '');
    $selectedServices = isset($_POST['services']) ? array_map('intval', $_POST['services']) : [];

    // Validate required fields
    $validationErrors = [];
    
    if (empty($doctorID)) $validationErrors[] = "Please select a doctor";
    if (empty($appointmentDate)) $validationErrors[] = "Please select a date";
    if (empty($appointmentTime)) $validationErrors[] = "Please select a time";
    
    // Check if time is during lunch break (12PM-1PM)
    $timeHour = date('H', strtotime($appointmentTime));
    if ($timeHour == 12) {
        $validationErrors[] = "Cannot book appointments during lunch break (12PM-1PM)";
    }
    
    // Check appointment limit (only count Scheduled appointments)
    $appointmentCount = $conn->query("
        SELECT COUNT(*) 
        FROM Appointment 
        WHERE patientID = $patientID
        AND status = 'Scheduled'
        AND deletedAt IS NULL
    ")->fetch_row()[0];
    
    if ($appointmentCount >= 3) {
        $validationErrors[] = "You can only have 3 scheduled appointments at a time";
    }

    // Validate services count
    if (count($selectedServices) > 5) {
        $validationErrors[] = "You can select maximum 5 services per appointment";
    }

    if (empty($validationErrors)) {
        // Start transaction
        $conn->begin_transaction();

        try {
            // Insert appointment
            $insertAppt = $conn->query("
                INSERT INTO Appointment (
                    patientID, doctorID, appointmentDate, appointmentTime, 
                    status, reasonForVisit, createdAt, updatedAt
                ) VALUES (
                    $patientID, 
                    $doctorID, 
                    '$appointmentDate', 
                    '$appointmentTime',
                    'Scheduled', 
                    " . ($reasonForVisit ? "'$reasonForVisit'" : "NULL") . ", 
                    NOW(), 
                    NOW()
                )
            ");

            if (!$insertAppt) {
                throw new Exception("Failed to create appointment: " . $conn->error);
            }

            $appointmentID = $conn->insert_id;

            // Insert services if any
            if (!empty($selectedServices)) {
                foreach ($selectedServices as $serviceID) {
                    $serviceID = intval($serviceID);
                    if (!$conn->query("
                        INSERT INTO AppointmentService (
                            appointmentID, serviceID
                        ) VALUES (
                            $appointmentID, 
                            $serviceID
                        )
                    ")) {
                        throw new Exception("Failed to add service: " . $conn->error);
                    }
                }
            }

            // Commit transaction
            $conn->commit();
            $success = "Appointment scheduled successfully!";
            // Refresh the page to show updated appointments
            header("Location: book_appointment.php");
            exit();

        } catch (Exception $e) {
            $conn->rollback();
            $error = "Booking failed: " . $e->getMessage();
        }
    } else {
        $error = implode("<br>", $validationErrors);
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Schedule Appointment</title>
    <link rel="stylesheet" href="../assets/css/main.css">
    <link rel="stylesheet" href="../assets/css/book_appointment.css">
    <style>
        .status-scheduled { color: #2ecc71; font-weight: bold; }
        .status-cancelled { color: #e74c3c; }
        .status-completed { color: #3498db; }
        
        .service-option {
            display: inline-block;
            margin: 5px;
            padding: 8px 12px;
            background: #f8f9fa;
            border-radius: 4px;
            cursor: pointer;
        }
        
        .service-option:hover {
            background: #e9ecef;
        }
        
        .services-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
            gap: 10px;
        }
        
        .time-selection-help {
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .availability-badge {
            display: flex;
            align-items: center;
            gap: 5px;
            font-size: 0.9em;
            padding: 5px 10px;
            border-radius: 4px;
            background: #f8f9fa;
        }
        
        .neutral-dot, .available-dot, .unavailable-dot, .loading-dot {
            display: inline-block;
            width: 10px;
            height: 10px;
            border-radius: 50%;
        }
        
        .neutral-dot { background: #95a5a6; }
        .available-dot { background: #2ecc71; }
        .unavailable-dot { background: #e74c3c; }
        .loading-dot { background: #f39c12; }
        
        .cancel-form {
            margin-top: 10px;
            padding: 10px;
            background: #f8f9fa;
            border-radius: 4px;
        }
        
        .cancel-form textarea {
            width: 100%;
            margin-bottom: 10px;
        }
    </style>
</head>
<body>

<div class="navbar">
    <h1>MedEasyna</h1>
    <div>
        <a href="patient_dashboard.php">Dashboard</a>
        <a href="book_appointment.php">Schedule Appointment</a>
        <a href="my_appointments.php">My Appointments</a>
        <a href="medical_records.php">Medical Records</a>
        <a href="logout.php">Sign Out</a>
    </div>
</div>

<main class="container">
    <h1>Schedule Appointment</h1>

    <div class="booking-guide">
        <h3>How to Schedule Your Appointment</h3> <br>
        <div class="guide-step">
            <span class="step-number">1</span>
            <div class="step-content">
                <strong>Choose Your Doctor</strong>
                <p>Select from our available specialists</p>
            </div>
        </div>
        
        <div class="guide-step">
            <span class="step-number">2</span>
            <div class="step-content">
                <strong>Select a Date</strong>
                <p>Pick any weekday (Monday-Friday)</p>
            </div>
        </div>
        
        <div class="guide-step">
            <span class="step-number">3</span>
            <div class="step-content">
                <strong>Choose a Time</strong>
                <p>Available in 30-minute slots between:</p>
                <ul class="time-slots-guide">
                    <li>Morning: 8:00 AM - 12:00 PM</li>
                    <li>Afternoon: 1:00 PM - 5:00 PM</li>
                </ul>
            </div>
        </div>
        
        <div class="guide-step">
            <span class="step-number">4</span>
            <div class="step-content">
                <strong>Select Services (Optional)</strong>
                <p>Choose up to 5 services per visit</p>
                <p class="note">Prices shown for each service</p>
            </div>
        </div>
        
        <div class="important-notes">
            <h4>Important Reminders:</h4>
            <ul>
                <li>❌ You can only have 3 active appointments at a time</li>
                <li>⏱️ Appointments must be at least 30 minutes apart</li>
                <li>✅ Available time slots will show in green</li>
                <li>❌ Scheduled slots will be automatically removed</li>
            </ul>
        </div>
    </div>

    <?php if ($error): ?>
        <div class="alert error"><?= $error ?></div>
    <?php endif; ?>
    
    <?php if ($success): ?>
        <div class="alert success"><?= $success ?></div>
    <?php endif; ?>

    <section class="booking-form">
        <form method="POST" id="appointmentForm">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'] ?? '') ?>">
            
            <div class="form-group">
                <label for="doctorID">Doctor:</label>
                <select name="doctorID" id="doctorID" required>
                    <option value="">Select Doctor</option>
                    <?php foreach ($doctors as $doc): ?>
                        <option value="<?= $doc['userID'] ?>" 
                            data-working-days="<?= htmlspecialchars($doc['working_days']) ?>"
                            data-start-time="<?= htmlspecialchars($doc['start_time']) ?>"
                            data-end-time="<?= htmlspecialchars($doc['end_time']) ?>"
                            <?= @$_POST['doctorID'] == $doc['userID'] ? 'selected' : '' ?>>
                            Dr. <?= $doc['firstName'] ?> <?= $doc['lastName'] ?> (<?= $doc['specialization'] ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-group">
                <label for="appointmentDate">Date:</label>
                <input type="date" name="appointmentDate" id="appointmentDate" 
                       min="<?= date('Y-m-d') ?>" 
                       value="<?= @$_POST['appointmentDate'] ?>" required>
                <div id="dayOfWeek"></div>
            </div>

            <div class="form-group">
                <label for="appointmentTime">Available Appointment Time:</label>
                <div class="time-selection-help">
                    <select name="appointmentTime" id="appointmentTime" required disabled>
                        <option value="">First select doctor and date</option>
                    </select>
                    <div class="availability-badge" id="timeHelpText">
                        <span class="neutral-dot"></span>
                        <span>Select doctor and date to see available times</span>
                    </div>
                </div>
            </div>

            <div class="form-group">
                <label>Services (max 5):</label>
                <div class="services-grid">
                    <?php foreach ($services as $service): ?>
                        <label class="service-option">
                            <input type="checkbox" name="services[]" 
                                   value="<?= $service['serviceID'] ?>"
                                   class="service-checkbox"
                                   <?= isset($_POST['services']) && in_array($service['serviceID'], $_POST['services']) ? 'checked' : '' ?>>
                            <?= $service['serviceName'] ?> 
                            (₱<?= number_format($service['price'], 2) ?>)
                        </label>
                    <?php endforeach; ?>
                </div>
            </div>

            <div class="form-group">
                <label for="reasonForVisit">Reason for Visit:</label>
                <textarea name="reasonForVisit" id="reasonForVisit" rows="3"><?= @$_POST['reasonForVisit'] ?></textarea>
            </div>

            <button type="submit" name="bookAppointment" class="btn-primary">Schedule Appointment</button>
        </form>
    </section> 

    <section class="appointments-list">
        <h2>Your Scheduled Appointments</h2>
        <?php if (empty($appointments)): ?>
            <p>No scheduled appointments</p>
        <?php else: ?>
            <div class="table-responsive">
                <table>
                    <thead>
                        <tr>
                            <th>Doctor</th>
                            <th>Date</th>
                            <th>Time</th>
                            <th>Services</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($appointments as $apt): ?>
                            <tr>
                                <td>Dr. <?= htmlspecialchars($apt['doctorName']) ?> (<?= htmlspecialchars($apt['specialization']) ?>)</td>
                                <td><?= date('M j, Y', strtotime($apt['appointmentDate'])) ?></td>
                                <td><?= date('g:i A', strtotime($apt['appointmentTime'])) ?></td>
                                <td><?= $apt['services'] ? htmlspecialchars($apt['services']) : 'None' ?></td>
                                <td class="status-<?= strtolower($apt['status']) ?>">
                                    <?= htmlspecialchars($apt['status']) ?>
                                </td>
                                <td>
                                    <button onclick="showCancelForm(<?= $apt['appointmentID'] ?>)" class="btn-cancel">Cancel</button>
                                    <div id="cancel-form-<?= $apt['appointmentID'] ?>" class="cancel-form" style="display:none">
                                        <form method="POST" action="cancel_appointments.php">
                                            <input type="hidden" name="appointmentID" value="<?= $apt['appointmentID'] ?>">
                                            <textarea name="reasonForCancellation" placeholder="Reason for cancellation" required rows="3"></textarea>
                                            <div class="form-actions">
                                                <button type="submit" name="cancelAppointment" class="btn-confirm">Confirm Cancellation</button>
                                                <button type="button" onclick="hideCancelForm(<?= $apt['appointmentID'] ?>)" class="btn-back">Back</button>
                                            </div>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </section>
</main>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Update day of week display
    const dateInput = document.getElementById('appointmentDate');
    dateInput.addEventListener('change', function() {
        if (this.value) {
            const date = new Date(this.value);
            document.getElementById('dayOfWeek').textContent = date.toLocaleDateString('en-US', { 
                weekday: 'long', 
                year: 'numeric', 
                month: 'long', 
                day: 'numeric' 
            });
            updateTimes();
        }
    });

    // Service selection limit
    document.querySelectorAll('.service-checkbox').forEach(cb => {
        cb.addEventListener('change', function() {
            const checked = document.querySelectorAll('.service-checkbox:checked');
            if (checked.length > 5) {
                this.checked = false;
                alert('Maximum 5 services allowed');
            }
        });
    });

    // When doctor changes
    document.getElementById('doctorID').addEventListener('change', updateTimes);
    
    async function updateTimes() {
        const doctorID = document.getElementById('doctorID').value;
        const date = document.getElementById('appointmentDate').value;
        const timeSelect = document.getElementById('appointmentTime');
        const helpText = document.getElementById('timeHelpText');
        
        if (!doctorID || !date) {
            timeSelect.innerHTML = '<option value="">First select doctor and date</option>';
            helpText.innerHTML = `
                <span class="neutral-dot"></span>
                <span>Select doctor and date to see available times</span>
            `;
            timeSelect.disabled = true;
            return;
        }
        
        // Show loading state
        timeSelect.innerHTML = '<option value="">Loading available times...</option>';
        timeSelect.disabled = true;
        helpText.innerHTML = `
            <span class="loading-dot"></span>
            <span>Checking availability...</span>
        `;
        
        try {
            // Fetch available times from server
            const response = await fetch(`get_available_times.php?doctorID=${doctorID}&date=${date}`);
            if (!response.ok) throw new Error('Network response was not ok');
            
            let times = await response.json();
            
            // Filter out lunch times (12:00-13:00) client-side as additional protection
            times = times.filter(time => {
                const [hours] = time.split(':');
                return !(parseInt(hours) >= 12 && parseInt(hours) < 13);
            });
            
            // Populate dropdown
            timeSelect.innerHTML = '<option value="">Select a time slot</option>';
            
            if (times.length > 0) {
                times.forEach(time => {
                    const [hours, minutes] = time.split(':');
                    const hour = parseInt(hours);
                    const ampm = hour >= 12 ? 'PM' : 'AM';
                    const displayHour = hour % 12 || 12;
                    const displayTime = `${displayHour}:${minutes} ${ampm}`;
                    
                    timeSelect.innerHTML += `<option value="${time}">${displayTime}</option>`;
                });
                
                helpText.innerHTML = `
                    <span class="available-dot"></span>
                    <span>${times.length} available time slots (8AM-12PM, 1PM-5PM)</span>
                `;
                timeSelect.disabled = false;
            } else {
                timeSelect.innerHTML = '<option value="">No available times</option>';
                helpText.innerHTML = `
                    <span class="unavailable-dot"></span>
                    <span>No availability - try another date</span>
                `;
            }
        } catch (error) {
            console.error('Error:', error);
            timeSelect.innerHTML = '<option value="">Error loading times</option>';
            helpText.innerHTML = `
                <span class="unavailable-dot"></span>
                <span>Could not check availability</span>
            `;
        }
    }
});

function showCancelForm(appointmentID) {
    document.getElementById(`cancel-form-${appointmentID}`).style.display = 'block';
}

function hideCancelForm(appointmentID) {
    document.getElementById(`cancel-form-${appointmentID}`).style.display = 'none';
}
</script>

<footer class="app-footer">
    <div class="footer-content">
        <p class="copyright">&copy; <?php echo date("Y"); ?> MedEasyna</p>
    </div>
</footer>

</body>
</html>