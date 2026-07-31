<?php 
require 'config.php';

// Handle AJAX patient search request
if (isset($_GET['action']) && $_GET['action'] == 'searchPatients') {
    $searchTerm = isset($_GET['q']) ? $conn->real_escape_string($_GET['q']) : '';
    
    $stmt = $conn->prepare("
        SELECT userID as id, CONCAT(firstName, ' ', lastName) as text 
        FROM user 
        WHERE role = 'Patient' 
        AND (firstName LIKE CONCAT('%', ?, '%') OR lastName LIKE CONCAT('%', ?, '%'))
        LIMIT 10
    ");
    $stmt->bind_param("ss", $searchTerm, $searchTerm);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $patients = [];
    while ($row = $result->fetch_assoc()) {
        $patients[] = $row;
    }
    
    header('Content-Type: application/json');
    echo json_encode(['results' => $patients]);
    exit;
}

// Fetch doctors with their weekly availability
$doctorQuery = $conn->query("
    SELECT d.doctorID, u.firstName, u.lastName, d.specialization,
           GROUP_CONCAT(DISTINCT CONCAT(a.weekday, '|', a.startTime, '|', a.endTime) ORDER BY a.weekday) as availability
    FROM doctor d 
    JOIN user u ON d.doctorID = u.userID
    LEFT JOIN doctorweeklyavailability a ON d.doctorID = a.doctorID AND a.isEnabled = 1
    GROUP BY d.doctorID
");
$doctors = [];
while ($row = $doctorQuery->fetch_assoc()) {
    $doctors[] = $row;
}

// Handle AJAX availability check
if (isset($_GET['getAvailability']) && isset($_GET['doctorID']) && isset($_GET['date'])) {
    header('Content-Type: application/json');
    
    try {
        $doctorID = (int)$_GET['doctorID'];
        $date = $conn->real_escape_string($_GET['date']);
        $weekday = date('N', strtotime($date)); // 1=Monday to 7=Sunday
        
        // Validate inputs
        if ($doctorID <= 0 || !strtotime($date)) {
            throw new Exception("Invalid parameters");
        }

        // Get doctor's weekly availability for this weekday
        $weeklyStmt = $conn->prepare("
            SELECT startTime, endTime 
            FROM doctorweeklyavailability 
            WHERE doctorID = ? AND weekday = ? AND isEnabled = 1
            LIMIT 1
        ");
        
        if (!$weeklyStmt) {
            throw new Exception("Database error: " . $conn->error);
        }
        
        $weeklyStmt->bind_param("ii", $doctorID, $weekday);
        
        if (!$weeklyStmt->execute()) {
            throw new Exception("Database error: " . $weeklyStmt->error);
        }
        
        $weeklyResult = $weeklyStmt->get_result();
        $availability = [];
        
        if ($weeklyResult->num_rows > 0) {
            $row = $weeklyResult->fetch_assoc();
            $start = strtotime($row['startTime']);
            $end = strtotime($row['endTime']);
            
            // Get booked appointments
            $bookedStmt = $conn->prepare("
                SELECT appointmentTime 
                FROM appointment 
                WHERE doctorID = ? AND appointmentDate = ? AND status = 'Scheduled'
            ");
            $bookedStmt->bind_param("is", $doctorID, $date);
            $bookedStmt->execute();
            $bookedResult = $bookedStmt->get_result();
            
            $bookedTimes = [];
            while ($appt = $bookedResult->fetch_assoc()) {
                $bookedTimes[] = $appt['appointmentTime'];
            }
            
            // Generate 30-minute slots
            $current = $start;
            while ($current < $end) {
                $time = date('H:i:s', $current);
                if (!in_array($time, $bookedTimes)) {
                    $availability[] = $time;
                }
                $current = strtotime('+30 minutes', $current);
            }
        } else {
            // Doctor has no availability for this weekday
            echo json_encode([]);
            exit;
        }
        
        echo json_encode($availability);
        
    } catch (Exception $e) {
        echo json_encode(['error' => $e->getMessage()]);
    }
    
    exit;
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $patientID = $_POST['patientID'];
    $doctorID = $_POST['doctorID'];
    $appointmentDate = $_POST['appointmentDate'];
    $appointmentTime = $_POST['appointmentTime'];
    $appointmentType = $_POST['appointmentType'];
    $notes = !empty($_POST['notes']) ? trim($_POST['notes']) : null;

    if (!$patientID || !$doctorID || !$appointmentDate || !$appointmentTime || !$appointmentType) {
        $error = "All required fields must be filled.";
    } else {
        // Verify availability again before booking
        $weekday = date('N', strtotime($appointmentDate));
        
        $weeklyStmt = $conn->prepare("
            SELECT startTime, endTime 
            FROM doctorweeklyavailability 
            WHERE doctorID = ? AND weekday = ? AND isEnabled = 1
            LIMIT 1
        ");
        $weeklyStmt->bind_param("ii", $doctorID, $weekday);
        $weeklyStmt->execute();
        $weeklyResult = $weeklyStmt->get_result();
        
        $isAvailable = false;
        
        if ($weeklyResult->num_rows > 0) {
            $row = $weeklyResult->fetch_assoc();
            $start = $row['startTime'];
            $end = $row['endTime'];
            
            if ($appointmentTime < $start || $appointmentTime >= $end) {
                $error = "Selected time is outside of doctor's availability.";
            } else {
                // Check for double booking
                $check = $conn->prepare("
                    SELECT appointmentID 
                    FROM appointment 
                    WHERE doctorID = ? AND appointmentDate = ? AND appointmentTime = ? AND status = 'Scheduled'
                ");
                $check->bind_param("iss", $doctorID, $appointmentDate, $appointmentTime);
                $check->execute();
                $check->store_result();
                
                if ($check->num_rows > 0) {
                    $error = "This time slot is already booked.";
                } else {
                    $isAvailable = true;
                }
            }
        } else {
            $error = "Doctor is not available on this weekday.";
        }
        
        if ($isAvailable) {
            $insert = $conn->prepare("
                INSERT INTO appointment 
                (patientID, doctorID, appointmentDate, appointmentTime, reasonForVisit, status, createdAt, updatedAt) 
                VALUES (?, ?, ?, ?, ?, 'Scheduled', NOW(), NOW())
            ");
            $insert->bind_param("iisss", $patientID, $doctorID, $appointmentDate, $appointmentTime, $notes);
            if ($insert->execute()) {
                $success = true;
                $_POST = array();
            } else {
                $error = "Database error: " . $conn->error;
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Schedule Appointment</title>
    <link rel="stylesheet" href="../assets/css/main.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/select2/4.0.13/css/select2.min.css">
    <style>
        .booking-form {
            max-width: 800px;
            margin: 2rem auto;
            background: white;
            padding: 2rem;
            border-radius: 8px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
        }
        
        .form-group {
            margin-bottom: 1.5rem;
        }
        
        label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 600;
            color: #2c3e50;
        }
        
        input, select, textarea {
            width: 100%;
            padding: 0.8rem;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-family: "Century Gothic", sans-serif;
            transition: border 0.3s ease;
        }
        
        input:focus, select:focus, textarea:focus {
            border-color: #3498db;
            outline: none;
            box-shadow: 0 0 0 2px rgba(52,152,219,0.2);
        }
        
        button[type="submit"] {
            background: #3498db;
            color: white;
            border: none;
            padding: 1rem 1.5rem;
            border-radius: 4px;
            cursor: pointer;
            font-family: "Century Gothic", sans-serif;
            font-weight: bold;
            width: 100%;
            transition: all 0.3s ease;
        }
        
        button[type="submit"]:hover {
            background: #2980b9;
            transform: translateY(-2px);
        }
        
        .error {
            color: #e74c3c;
            background: #fdecea;
            padding: 1rem;
            border-radius: 4px;
            margin-bottom: 1.5rem;
            border-left: 4px solid #e74c3c;
        }
        
        .success {
            color: #27ae60;
            background: #e8f8f0;
            padding: 1rem;
            border-radius: 4px;
            margin-bottom: 1.5rem;
            border-left: 4px solid #27ae60;
        }
        
        .select2-container {
            width: 100% !important;
        }
        
        .time-selection-help {
            margin-top: 0.5rem;
        }
        
        .availability-badge {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            margin-top: 0.5rem;
            padding: 0.5rem;
            background: #f8f9fa;
            border-radius: 4px;
            font-size: 0.9rem;
        }
        
        .neutral-dot, .loading-dot, .available-dot, .unavailable-dot {
            display: inline-block;
            width: 10px;
            height: 10px;
            border-radius: 50%;
        }
        
        .neutral-dot { background: #6c757d; }
        .loading-dot { background: #ffc107; }
        .available-dot { background: #28a745; }
        .unavailable-dot { background: #dc3545; }
        
        #dayOfWeek {
            margin-top: 0.5rem;
            font-size: 0.9rem;
            color: #6c757d;
        }
        
        .select2-result-patient {
            padding: 5px;
        }
        
        .patient-name {
            font-weight: bold;
        }
    </style>
</head>
<body>
    <div class="navbar">
        <h1>Doval Santos Diagnostic Center</h1>
        <div>
            <a href="receptionist_dashboard.php">Dashboard</a>
            <a href="../pages/schedule_appointment.php">Schedule Appointment</a>
            <a href="../pages/walk_in.php">Walk-Ins</a>
            <a href="../pages/logout.php">Sign Out</a>
        </div>
    </div>

    <main class="main-content">
        <section class="booking-form">
            <form method="POST" id="appointmentForm">
                <?php if (!empty($error)): ?>
                    <div class="error"><?= htmlspecialchars($error) ?></div>
                <?php elseif (!empty($success)): ?>
                    <div class="success">Appointment scheduled successfully!</div>
                <?php endif; ?>

                <div class="form-group">
                    <label for="patientSearch">Patient:</label>
                    <select id="patientSearch" name="patientID" class="patient-search" required>
                        <?php if (!empty($_POST['patientID'])): ?>
                            <?php 
                                $patientStmt = $conn->prepare("SELECT userID, firstName, lastName FROM user WHERE userID = ?");
                                $patientStmt->bind_param("i", $_POST['patientID']);
                                $patientStmt->execute();
                                $patientResult = $patientStmt->get_result();
                                $selectedPatient = $patientResult->fetch_assoc();
                            ?>
                            <option value="<?= $selectedPatient['userID'] ?>" selected>
                                <?= htmlspecialchars($selectedPatient['firstName'] . ' ' . $selectedPatient['lastName']) ?>
                            </option>
                        <?php endif; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label for="doctorID">Doctor:</label>
                    <select name="doctorID" id="doctorID" required>
                        <option value="">Select Doctor</option>
                        <?php foreach ($doctors as $doc): 
                            $availability = [];
                            if (!empty($doc['availability'])) {
                                $slots = explode(',', $doc['availability']);
                                foreach ($slots as $slot) {
                                    list($day, $start, $end) = explode('|', $slot);
                                    $availability[$day] = [
                                        'start' => date('g:i A', strtotime($start)),
                                        'end' => date('g:i A', strtotime($end))
                                    ];
                                }
                            }
                            $workingDays = '';
                            if (!empty($doc['availability'])) {
                                $slots = explode(',', $doc['availability']);
                                $uniqueDays = [];
                                
                                foreach ($slots as $slot) {
                                    if (empty($slot)) continue;
                                    list($day, $start, $end) = explode('|', $slot);
                                    
                                    // Only add each weekday once
                                    if (!isset($uniqueDays[$day])) {
                                        $dayName = date('l', strtotime("Sunday +{$day} days"));
                                        $uniqueDays[$day] = $dayName . ' ' . date('g:i A', strtotime($start)) . '-' . date('g:i A', strtotime($end));
                                    }
                                }
                                
                                $workingDays = implode(', ', $uniqueDays);
                            }
                        ?>
                            <option value="<?= $doc['doctorID'] ?>" 
                                data-working-days="<?= htmlspecialchars($workingDays) ?>"
                                <?= ($_POST['doctorID'] ?? '') == $doc['doctorID'] ? 'selected' : '' ?>>
                                Dr. <?= htmlspecialchars($doc['firstName']) ?> <?= htmlspecialchars($doc['lastName']) ?> 
                                (<?= htmlspecialchars($doc['specialization']) ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label for="appointmentDate">Date:</label>
                    <input type="date" name="appointmentDate" id="appointmentDate" 
                           min="<?= date('Y-m-d') ?>" 
                           value="<?= htmlspecialchars($_POST['appointmentDate'] ?? '') ?>" required>
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
                    <label for="appointmentType">Appointment Type:</label>
                    <select id="appointmentType" name="appointmentType" required>
                        <option value="">Select Type</option>
                        <option value="Consultation" <?= ($_POST['appointmentType'] ?? '') == 'Consultation' ? 'selected' : '' ?>>Consultation</option>
                        <option value="Checkup" <?= ($_POST['appointmentType'] ?? '') == 'Checkup' ? 'selected' : '' ?>>Checkup</option>
                        <option value="Follow-up" <?= ($_POST['appointmentType'] ?? '') == 'Follow-up' ? 'selected' : '' ?>>Follow-up</option>
                    </select>
                </div>

                <div class="form-group">
                    <label for="notes">Notes:</label>
                    <textarea id="notes" name="notes" placeholder="Enter any extra details..."><?= htmlspecialchars($_POST['notes'] ?? '') ?></textarea>
                </div>

                <button type="submit" class="btn-primary">Schedule Appointment</button>
            </form>
        </section>
    </main>

    <footer class="app-footer">
        <div class="footer-content">
            <p class="copyright">&copy; <?php echo date("Y"); ?> Doval Santos Diagnostic Center</p>
        </div>
    </footer>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/select2/4.0.13/js/select2.min.js"></script>
    <script>
        $(document).ready(function() {
            // Initialize patient search with Select2
            $('.patient-search').select2({
                placeholder: "Search for patient...",
                minimumInputLength: 2,
                ajax: {
                    url: 'schedule_appointment.php?action=searchPatients',
                    dataType: 'json',
                    delay: 250,
                    data: function(params) {
                        return {
                            q: params.term
                        };
                    },
                    processResults: function(data) {
                        return {
                            results: data.results
                        };
                    },
                    cache: true
                },
                templateResult: formatPatient,
                templateSelection: formatPatientSelection
            });
            
            function formatPatient(patient) {
                if (patient.loading) {
                    return patient.text;
                }
                
                var $container = $(
                    "<div class='select2-result-patient'>" +
                    "<div class='patient-name'>" + patient.text + "</div>" +
                    "</div>"
                );
                
                return $container;
            }
            
            function formatPatientSelection(patient) {
                return patient.text;
            }

            // Update day of week display
            $('#appointmentDate').change(function() {
                if (this.value) {
                    const date = new Date(this.value);
                    $('#dayOfWeek').text(date.toLocaleDateString('en-US', { 
                        weekday: 'long', 
                        year: 'numeric', 
                        month: 'long', 
                        day: 'numeric' 
                    }));
                    updateTimes();
                } else {
                    $('#dayOfWeek').text('');
                }
            });

            // When doctor changes
            $('#doctorID').change(function() {
                updateTimes();
                updateDoctorAvailabilityInfo();
            });

            // Show doctor's typical availability
            function updateDoctorAvailabilityInfo() {
                const doctorSelect = $('#doctorID');
                const selectedOption = doctorSelect.find('option:selected');
                
                if (selectedOption.val()) {
                    const workingDays = selectedOption.data('working-days');
                    
                    $('#timeHelpText').html(`
                        <span class="neutral-dot"></span>
                        <span>Typical availability: ${workingDays || 'Not specified'}</span>
                    `);
                }
            }

            async function updateTimes() {
                const doctorID = $('#doctorID').val();
                const date = $('#appointmentDate').val();
                const timeSelect = $('#appointmentTime');
                const helpText = $('#timeHelpText');
                
                if (!doctorID || !date) {
                    timeSelect.html('<option value="">First select doctor and date</option>');
                    helpText.html('<span class="neutral-dot"></span><span>Select doctor and date</span>');
                    timeSelect.prop('disabled', true);
                    return;
                }
                
                // Show loading state
                timeSelect.html('<option value="">Loading...</option>').prop('disabled', true);
                helpText.html('<span class="loading-dot"></span><span>Checking availability...</span>');
                
                try {
                    const response = await fetch(`schedule_appointment.php?getAvailability=1&doctorID=${doctorID}&date=${date}`);
                    
                    // First check if response is JSON
                    const contentType = response.headers.get('content-type');
                    if (!contentType || !contentType.includes('application/json')) {
                        throw new Error('Invalid response from server');
                    }
                    
                    const data = await response.json();
                    
                    if (!response.ok || data.error) {
                        throw new Error(data.error || 'Failed to check availability');
                    }
                    
                    timeSelect.empty().append('<option value="">Select time</option>');
                    
                    if (Array.isArray(data) && data.length > 0) {
                        data.forEach(time => {
                            const [h, m, s] = time.split(':');
                            const hour = parseInt(h) % 12 || 12;
                            const ampm = parseInt(h) >= 12 ? 'PM' : 'AM';
                            timeSelect.append(`<option value="${time}">${hour}:${m} ${ampm}</option>`);
                        });
                        helpText.html(`<span class="available-dot"></span><span>${data.length} slots available</span>`);
                        timeSelect.prop('disabled', false);
                    } else {
                        timeSelect.html('<option value="">No availability</option>');
                        helpText.html('<span class="unavailable-dot"></span><span>No slots available</span>');
                    }
                } catch (error) {
                    console.error('Availability check failed:', error);
                    timeSelect.html('<option value="">Error loading times</option>');
                    helpText.html(`<span class="unavailable-dot"></span><span>Error: ${error.message}</span>`);
                }
            }
            
            // Initialize doctor availability info
            updateDoctorAvailabilityInfo();
            
            // Initialize form if there was an error
            <?php if (!empty($_POST['appointmentTime'])): ?>
                const doctorID = $('#doctorID').val();
                const date = $('#appointmentDate').val();
                
                if (doctorID && date) {
                    updateTimes();
                    
                    // Wait for slots to load then select the previously chosen time
                    setTimeout(function() {
                        $('#appointmentTime').val("<?= $_POST['appointmentTime'] ?>");
                    }, 500);
                }
            <?php endif; ?>
        });
    </script>
</body>
</html>