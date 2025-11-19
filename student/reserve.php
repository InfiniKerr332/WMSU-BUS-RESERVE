<?php
require_once '../includes/config.php';
require_once '../includes/database.php';
require_once '../includes/functions.php';
require_once '../includes/session.php';

if (!is_logged_in()) {
    redirect(SITE_URL . 'login.php');
}

if (is_admin()) {
    redirect(SITE_URL . 'admin/dashboard.php');
}

$user = get_logged_user();

if ($user['role'] != 'teacher' && $user['role'] != 'employee') {
    redirect(SITE_URL . 'student/dashboard.php?error=only_teachers');
}

$errors = [];
$success = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $purpose = isset($_POST['purpose']) ? clean_input($_POST['purpose']) : '';
    $destination = isset($_POST['destination']) ? clean_input($_POST['destination']) : '';
    $reservation_date = isset($_POST['reservation_date']) ? clean_input($_POST['reservation_date']) : '';
    $reservation_time = isset($_POST['reservation_time']) ? clean_input($_POST['reservation_time']) : '';
    $return_date = isset($_POST['return_date']) ? clean_input($_POST['return_date']) : null;
    $return_time = isset($_POST['return_time']) ? clean_input($_POST['return_time']) : null;
    $passenger_count = isset($_POST['passenger_count']) ? clean_input($_POST['passenger_count']) : '';
    $bus_id = isset($_POST['bus_id']) ? clean_input($_POST['bus_id']) : '';
    
    if (empty($purpose)) {
        $errors[] = 'Purpose is required';
    }
    
    if (empty($destination)) {
        $errors[] = 'Destination is required';
    }
    
    if (empty($reservation_date)) {
        $errors[] = 'Departure date is required';
    } else {
        $now = new DateTime();
        $now->setTimezone(new DateTimeZone('Asia/Manila'));
        
        $reservation_datetime = new DateTime($reservation_date . ' ' . ($reservation_time ?: '00:00:00'));
        $reservation_datetime->setTimezone(new DateTimeZone('Asia/Manila'));
        
        $diff_seconds = $reservation_datetime->getTimestamp() - $now->getTimestamp();
        $diff_hours = $diff_seconds / 3600;
        
        if ($reservation_datetime <= $now) {
            $errors[] = 'Cannot reserve for past dates';
        } else if ($diff_hours < 72) {
            $hours_remaining = 72 - $diff_hours;
            $minimum_datetime = clone $now;
            $minimum_datetime->modify('+72 hours');
            
            $errors[] = sprintf(
                'Reservations must be made at least 72 hours (3 days) in advance. Your reservation is only %.1f hours away. You need %.1f more hours. Earliest available: %s',
                $diff_hours,
                $hours_remaining,
                $minimum_datetime->format('F d, Y g:i A')
            );
        }
        
        if (is_sunday($reservation_date)) {
            $errors[] = 'Reservations on Sundays are not allowed';
        }
    }
    
    if (empty($reservation_time)) {
        $errors[] = 'Departure time is required';
    }
    
    if (empty($return_date)) {
        $errors[] = 'Return date is required';
    }
    
    if (empty($return_time)) {
        $errors[] = 'Return time is required';
    }
    
    if (!empty($return_date) && !empty($reservation_date)) {
        $depart_timestamp = strtotime($reservation_date);
        $return_timestamp = strtotime($return_date);
        
        if ($return_timestamp < $depart_timestamp) {
            $errors[] = 'Return date cannot be before departure date';
        }
        
        $departure_dt = new DateTime($reservation_date);
        $return_dt = new DateTime($return_date);
        $interval = $departure_dt->diff($return_dt);
        $daysDiff = $interval->days;
        
        if ($daysDiff > 7) {
            $errors[] = 'Maximum reservation duration is 7 days. Your trip is ' . $daysDiff . ' days long.';
        }
        
        if (is_sunday($return_date)) {
            $errors[] = 'Return date cannot be on Sunday';
        }
        
        if ($return_date == $reservation_date && !empty($return_time) && !empty($reservation_time)) {
            $depart_time_ts = strtotime($reservation_date . ' ' . $reservation_time);
            $return_time_ts = strtotime($return_date . ' ' . $return_time);
            
            if ($return_time_ts <= $depart_time_ts) {
                $errors[] = 'Return time must be after departure time on same-day trips';
            }
        }
    }
    
    if (empty($passenger_count) || $passenger_count < 1) {
        $errors[] = 'Valid passenger count is required';
    }
    
    if (empty($bus_id)) {
        $errors[] = 'Please select a bus';
    }
    
    $db = new Database();
    $conn = $db->connect();
    
    if (!empty($bus_id) && !empty($passenger_count)) {
        $stmt = $conn->prepare("SELECT capacity, bus_name FROM buses WHERE id = :bus_id");
        $stmt->bindParam(':bus_id', $bus_id, PDO::PARAM_INT);
        $stmt->execute();
        $selected_bus = $stmt->fetch();
        
        if ($selected_bus) {
            if ($passenger_count > $selected_bus['capacity']) {
                $errors[] = "Passenger count ({$passenger_count}) exceeds the capacity of {$selected_bus['bus_name']} ({$selected_bus['capacity']} passengers)";
            }
        } else {
            $errors[] = 'Selected bus not found';
        }
    }
    
       if (empty($errors) && !empty($bus_id) && !empty($reservation_date)) {
        $effective_return = $return_date ?: $reservation_date;
        
        // Use unique placeholder names for each occurrence to avoid PDO HY093 error
        $check_sql = "
            SELECT COUNT(*) as conflict_count
            FROM reservations 
            WHERE bus_id = :bus_id 
            AND status IN ('pending', 'approved')
            AND (
                (reservation_date BETWEEN :start_date1 AND :end_date1)
                OR
                (COALESCE(return_date, reservation_date) BETWEEN :start_date2 AND :end_date2)
                OR
                (reservation_date <= :start_date3 AND COALESCE(return_date, reservation_date) >= :end_date3)
            )
        ";
        
        $check_stmt = $conn->prepare($check_sql);
        $check_stmt->bindParam(':bus_id', $bus_id, PDO::PARAM_INT);
        
        // Bind each unique placeholder
        $check_stmt->bindParam(':start_date1', $reservation_date, PDO::PARAM_STR);
        $check_stmt->bindParam(':end_date1', $effective_return, PDO::PARAM_STR);
        $check_stmt->bindParam(':start_date2', $reservation_date, PDO::PARAM_STR);
        $check_stmt->bindParam(':end_date2', $effective_return, PDO::PARAM_STR);
        $check_stmt->bindParam(':start_date3', $reservation_date, PDO::PARAM_STR);
        $check_stmt->bindParam(':end_date3', $effective_return, PDO::PARAM_STR);
        
        $check_stmt->execute();
        $conflict_check = $check_stmt->fetch();
        
        if ($conflict_check && $conflict_check['conflict_count'] > 0) {
            $errors[] = 'Sorry, this bus has just been booked by another user on your selected dates. Please refresh the page and choose another bus or different dates.';
        }
    }

    
    if (empty($errors)) {
        try {
            $user_id = (int)$_SESSION['user_id'];
            $bus_id_int = (int)$bus_id;
            $passenger_count_int = (int)$passenger_count;
            
            $return_date_value = (!empty($return_date) && $return_date !== '') ? $return_date : null;
            $return_time_value = (!empty($return_time) && $return_time !== '') ? $return_time : null;
            
            // Fixed: include placeholders for driver_id, status, created_at and bind them
            $sql = "INSERT INTO reservations 
                    (user_id, bus_id, driver_id, purpose, destination, reservation_date, reservation_time, 
                     return_date, return_time, passenger_count, status, created_at) 
                    VALUES 
                    (:user_id, :bus_id, :driver_id, :purpose, :destination, :reservation_date, :reservation_time, 
                     :return_date, :return_time, :passenger_count, :status, :created_at)";
            
            $stmt = $conn->prepare($sql);
            
            $stmt->bindValue(':user_id', $user_id, PDO::PARAM_INT);
            $stmt->bindValue(':bus_id', $bus_id_int, PDO::PARAM_INT);
            
            // driver will be assigned by admin later; insert NULL for now
            $stmt->bindValue(':driver_id', null, PDO::PARAM_NULL);
            
            $stmt->bindValue(':purpose', $purpose, PDO::PARAM_STR);
            $stmt->bindValue(':destination', $destination, PDO::PARAM_STR);
            $stmt->bindValue(':reservation_date', $reservation_date, PDO::PARAM_STR);
            $stmt->bindValue(':reservation_time', $reservation_time, PDO::PARAM_STR);
            
            if ($return_date_value === null) {
                $stmt->bindValue(':return_date', null, PDO::PARAM_NULL);
            } else {
                $stmt->bindValue(':return_date', $return_date_value, PDO::PARAM_STR);
            }
            
            if ($return_time_value === null) {
                $stmt->bindValue(':return_time', null, PDO::PARAM_NULL);
            } else {
                $stmt->bindValue(':return_time', $return_time_value, PDO::PARAM_STR);
            }
            
            $stmt->bindValue(':passenger_count', $passenger_count_int, PDO::PARAM_INT);
            
            // status and created_at placeholders
            $stmt->bindValue(':status', 'pending', PDO::PARAM_STR);
            $stmt->bindValue(':created_at', date('Y-m-d H:i:s'), PDO::PARAM_STR);
            
            if ($stmt->execute()) {
                $success = 'Reservation submitted successfully! Waiting for admin approval and driver assignment.';
                
                $stmt = $conn->prepare("SELECT * FROM buses WHERE id = :bus_id");
                $stmt->bindParam(':bus_id', $bus_id_int, PDO::PARAM_INT);
                $stmt->execute();
                $bus = $stmt->fetch();
                
                $return_info = '';
                if ($return_date_value && $return_time_value) {
                    $return_info = "<p><strong>Return:</strong> " . format_date($return_date_value) . " at " . format_time($return_time_value) . "</p>";
                }
                
                $email_message = "
                    <h3>New Bus Reservation Request</h3>
                    <p><strong>From:</strong> " . htmlspecialchars($user['name']) . "</p>
                    <p><strong>Email:</strong> " . htmlspecialchars($user['email']) . "</p>
                    <p><strong>Contact:</strong> " . htmlspecialchars($user['contact_no']) . "</p>
                    <p><strong>Department:</strong> " . htmlspecialchars($user['department']) . "</p>
                    <hr>
                    <p><strong>Departure:</strong> " . format_date($reservation_date) . " at " . format_time($reservation_time) . "</p>
                    {$return_info}
                    <p><strong>Destination:</strong> " . htmlspecialchars($destination) . "</p>
                    <p><strong>Purpose:</strong> " . htmlspecialchars($purpose) . "</p>
                    <p><strong>Passengers:</strong> " . $passenger_count_int . "</p>
                    <p><strong>Bus Requested:</strong> " . htmlspecialchars($bus['bus_name']) . " (" . htmlspecialchars($bus['plate_no']) . ")</p>
                    <hr>
                    <p><strong>Action Required:</strong> Please assign a driver and approve/reject this reservation.</p>
                    <p><a href='" . SITE_URL . "admin/reservations.php'>View Reservation</a></p>
                ";
                
                send_email(ADMIN_EMAIL, 'New Bus Reservation Request - Driver Assignment Needed', $email_message);
            } else {
                $errors[] = 'Failed to submit reservation. Please try again.';
            }
        } catch (PDOException $e) {
            $errors[] = 'Database error: ' . $e->getMessage();
            error_log("Reservation insert error: " . $e->getMessage());
        }
    }
}

$db = new Database();
$conn = $db->connect();

$stmt = $conn->prepare("SELECT * FROM buses WHERE (deleted = 0 OR deleted IS NULL) AND status = 'available' ORDER BY bus_name");
$stmt->execute();
$all_buses = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>New Reservation - <?php echo SITE_NAME; ?></title>
    <link rel="stylesheet" href="../css/main.css">
    <style>
        .info-box {
            background: #e3f2fd;
            border-left: 4px solid #2196f3;
            padding: 15px;
            margin-bottom: 20px;
            border-radius: 5px;
        }
        
        .info-box ul {
            margin: 10px 0 0 20px;
            color: #1565c0;
        }
        
        .info-box ul li {
            margin: 5px 0;
        }
        
        .bus-selector {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
            gap: 15px;
            margin-bottom: 20px;
        }
        
        .bus-card {
            border: 2px solid #ddd;
            border-radius: 8px;
            padding: 15px;
            cursor: pointer;
            transition: all 0.3s ease;
            background: white;
            position: relative;
        }
        
        .bus-card:hover:not(.step-disabled):not(.unavailable) {
            border-color: var(--wmsu-maroon);
            box-shadow: 0 4px 12px rgba(128, 0, 0, 0.15);
            transform: translateY(-2px);
        }
        
        .bus-card.selected {
            border-color: var(--wmsu-maroon);
            background: #fff5f5;
            box-shadow: 0 4px 12px rgba(128, 0, 0, 0.2);
        }
        
        .bus-card.unavailable {
            opacity: 0.6;
            cursor: not-allowed;
            background: #f5f5f5;
        }
        
        .bus-card.checking {
            opacity: 0.7;
        }
        
        .bus-card.step-disabled {
            opacity: 0.4;
            cursor: not-allowed;
            pointer-events: none;
        }
        
        .bus-name {
            font-weight: 600;
            color: var(--wmsu-maroon);
            font-size: 16px;
            text-align: center;
            margin-bottom: 5px;
        }
        
        .bus-plate {
            font-size: 14px;
            color: #666;
            text-align: center;
            margin-bottom: 5px;
        }
        
        .bus-capacity {
            font-size: 13px;
            color: #888;
            text-align: center;
            margin-bottom: 10px;
        }
        
        .bus-status {
            text-align: center;
            padding: 5px;
            border-radius: 15px;
            font-size: 12px;
            font-weight: 600;
        }
        
        .bus-status.available {
            background: #d4edda;
            color: #155724;
        }
        
        .bus-status.unavailable {
            background: #f8d7da;
            color: #721c24;
        }
        
        .bus-status.checking {
            background: #fff3cd;
            color: #856404;
        }
        
        .availability-info {
            margin-top: 10px;
            padding: 10px;
            background: #f9f9f9;
            border-radius: 5px;
            font-size: 11px;
            text-align: center;
        }
        
        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
        }
        
        .form-section {
            background: #f9f9f9;
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 20px;
        }
        
        .form-section h3 {
            color: var(--wmsu-maroon);
            margin-bottom: 15px;
            font-size: 18px;
        }
        
        .step-indicator {
            background: var(--wmsu-maroon);
            color: white;
            width: 30px;
            height: 30px;
            border-radius: 50%;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-weight: 700;
            margin-right: 10px;
        }
        
        @media (max-width: 768px) {
            .form-row {
                grid-template-columns: 1fr;
            }
            
            .bus-selector {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <header>
        <div class="header-content">
            <div class="logo-section">
                <img src="../images/wmsu.png" alt="WMSU Logo" onerror="this.style.display='none'">
                <h1><?php echo SITE_NAME; ?></h1>
            </div>
            <div class="user-info">
                <span class="user-name">Welcome, <?php echo htmlspecialchars($user['name']); ?>!</span>
                <a href="../logout.php" class="logout-btn">Logout</a>
            </div>
        </div>
    </header>

    <nav class="nav">
        <ul>
            <li><a href="dashboard.php">Dashboard</a></li>
            <li><a href="reserve.php" class="active">New Reservation</a></li>
            <li><a href="my_reservations.php">My Reservations</a></li>
            <li><a href="profile.php">Profile</a></li>
        </ul>
    </nav>

    <div class="container">
        <div class="card">
            <div class="card-header">
                <h2>New Bus Reservation</h2>
            </div>
            
            <div class="info-box">
                <strong>Important Reservation Guidelines:</strong>
                <ul>
                    <li>Reservations must be made at least 3 days (72 hours) in advance</li>
                    <li>Maximum trip duration: 7 days</li>
                    <li>Pickup location is always WMSU Campus, Normal Road, Baliwasan</li>
                    <li>Return date and time are required</li>
                    <li>No reservations on Sundays (both departure and return)</li>
                    <li>Driver will be assigned by admin</li>
                </ul>
            </div>
            
            <?php if ($success): ?>
                <div class="alert alert-success">
                    <?php echo $success; ?>
                    <span class="alert-close">&times;</span>
                </div>
                <div style="text-align: center; margin: 20px 0;">
                    <a href="my_reservations.php" class="btn btn-primary">View My Reservations</a>
                    <a href="reserve.php" class="btn btn-secondary">Make Another Reservation</a>
                </div>
            <?php endif; ?>
            
            <?php if (!empty($errors)): ?>
                <div class="alert alert-error">
                    <?php foreach ($errors as $error): ?>
                        <p><?php echo $error; ?></p>
                    <?php endforeach; ?>
                    <span class="alert-close">&times;</span>
                </div>
            <?php endif; ?>
            
            <?php if (!$success): ?>
            <form method="POST" action="" id="reservationForm">
                
                <div class="form-section">
                    <h3><span class="step-indicator">1</span>Select Date & Time</h3>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="reservation_date">Departure Date <span class="required">*</span></label>
                            <input type="date" id="reservation_date" name="reservation_date" class="form-control" 
                                   value="<?php echo isset($_POST['reservation_date']) ? htmlspecialchars($_POST['reservation_date']) : ''; ?>">
                            <small style="color: #666;">At least 3 days from now</small>
                        </div>
                        
                        <div class="form-group">
                            <label for="reservation_time">Departure Time <span class="required">*</span></label>
                            <input type="time" id="reservation_time" name="reservation_time" class="form-control" 
                                   value="<?php echo isset($_POST['reservation_time']) ? htmlspecialchars($_POST['reservation_time']) : ''; ?>">
                            <small style="color: #666;">Depart from WMSU</small>
                        </div>
                    </div>
                    
                    <hr style="margin: 20px 0;">
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="return_date">Return Date <span class="required">*</span></label>
                            <input type="date" id="return_date" name="return_date" class="form-control" 
                                   value="<?php echo isset($_POST['return_date']) ? htmlspecialchars($_POST['return_date']) : ''; ?>" required>
                            <small style="color: #666;">Max 7 days from departure</small>
                        </div>
                        
                        <div class="form-group">
                            <label for="return_time">Return Time <span class="required">*</span></label>
                            <input type="time" id="return_time" name="return_time" class="form-control" 
                                   value="<?php echo isset($_POST['return_time']) ? htmlspecialchars($_POST['return_time']) : ''; ?>" required>
                            <small style="color: #666;">Pick-up from destination</small>
                        </div>
                    </div>
                </div>
                
                <div class="form-section" id="busSelectionSection">
                    <h3><span class="step-indicator">2</span>Select an Available Bus</h3>
                    <p style="color: #666; margin-bottom: 15px; font-weight: 600;" id="busHint">Please complete ALL date and time fields above first</p>
                    
                    <div class="bus-selector">
                        <?php foreach ($all_buses as $bus): ?>
                        <div class="bus-card step-disabled" 
                             data-bus-id="<?php echo $bus['id']; ?>"
                             data-bus-name="<?php echo htmlspecialchars($bus['bus_name']); ?>"
                             data-bus-capacity="<?php echo $bus['capacity']; ?>"
                             onclick="selectBus(this, <?php echo $bus['id']; ?>, '<?php echo addslashes($bus['bus_name']); ?>', <?php echo $bus['capacity']; ?>)">
                            <div class="bus-name"><?php echo htmlspecialchars($bus['bus_name']); ?></div>
                            <div class="bus-plate"><?php echo htmlspecialchars($bus['plate_no']); ?></div>
                            <div class="bus-capacity">Capacity: <?php echo $bus['capacity']; ?> passengers</div>
                            <div class="bus-status" id="status-<?php echo $bus['id']; ?>">Select dates first</div>
                            <div class="availability-info" id="info-<?php echo $bus['id']; ?>">Waiting...</div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    
                    <input type="hidden" name="bus_id" id="selected_bus_id" value="">
                </div>
                
                <div class="form-section">
                    <h3><span class="step-indicator">3</span>Trip Information</h3>
                    
                    <div class="form-group">
                        <label for="purpose">Purpose <span class="required">*</span></label>
                        <textarea id="purpose" name="purpose" class="form-control" rows="3" 
                                  placeholder="Educational field trip, Official business meeting, etc."><?php echo isset($_POST['purpose']) ? htmlspecialchars($_POST['purpose']) : ''; ?></textarea>
                    </div>
                    
                    <div class="form-group">
                        <label for="destination">Destination <span class="required">*</span></label>
                        <input type="text" id="destination" name="destination" class="form-control" 
                               value="<?php echo isset($_POST['destination']) ? htmlspecialchars($_POST['destination']) : ''; ?>" 
                               placeholder="Zamboanga City Museum, Fort Pilar, etc.">
                        <small style="color: #666;">From WMSU to your destination and back</small>
                    </div>
                    
                    <div class="form-group">
                        <label for="passenger_count">Passengers <span class="required">*</span></label>
                        <input type="number" id="passenger_count" name="passenger_count" class="form-control" 
                               min="1" max="45" value="<?php echo isset($_POST['passenger_count']) ? htmlspecialchars($_POST['passenger_count']) : '1'; ?>">
                        <small style="color: #666;" id="capacity-hint">Select a bus to see capacity</small>
                    </div>
                </div>
                
                <button type="submit" class="btn btn-primary btn-block">Submit Reservation</button>
            </form>
            <?php endif; ?>
        </div>
    </div>

    <footer>
        <p>&copy; <?php echo date('Y'); ?> Western Mindanao State University. All rights reserved.</p>
    </footer>

    <script src="../js/main.js"></script>
    <script>
        let selectedBusId = null;
        let selectedBusName = '';
        let selectedBusCapacity = 0;
        let allFieldsFilled = false;
        
        const minDate = new Date();
        minDate.setDate(minDate.getDate() + 3);
        document.getElementById('reservation_date').setAttribute('min', minDate.toISOString().split('T')[0]);
        
        function checkAllFieldsFilled() {
            const dDate = document.getElementById('reservation_date').value;
            const dTime = document.getElementById('reservation_time').value;
            const rDate = document.getElementById('return_date').value;
            const rTime = document.getElementById('return_time').value;
            
            if (dDate && dTime && rDate && rTime) {
                allFieldsFilled = true;
                document.getElementById('busHint').textContent = 'Choose an available bus:';
                document.getElementById('busHint').style.color = '#28a745';
                
                document.querySelectorAll('.bus-card').forEach(card => {
                    card.classList.remove('step-disabled');
                });
                
                checkBusAvailability();
            } else {
                allFieldsFilled = false;
                document.getElementById('busHint').textContent = 'Please complete ALL date and time fields above first';
                document.getElementById('busHint').style.color = '#dc3545';
                
                document.querySelectorAll('.bus-card').forEach(card => {
                    card.classList.add('step-disabled');
                    card.classList.remove('selected');
                });
                
                selectedBusId = null;
                selectedBusName = '';
                selectedBusCapacity = 0;
                document.getElementById('selected_bus_id').value = '';
            }
        }
        
        document.getElementById('reservation_date').addEventListener('change', function() {
            if (!this.value) return;
            
            const sel = new Date(this.value);
            if (sel.getDay() === 0) {
                alert('Sundays not allowed');
                this.value = '';
                return;
            }
            
            document.getElementById('return_date').setAttribute('min', this.value);
            
            const maxReturn = new Date(sel);
            maxReturn.setDate(maxReturn.getDate() + 7);
            document.getElementById('return_date').setAttribute('max', maxReturn.toISOString().split('T')[0]);
            
            checkAllFieldsFilled();
        });
        
        document.getElementById('reservation_time').addEventListener('change', checkAllFieldsFilled);
        
        document.getElementById('return_date').addEventListener('change', function() {
            const dDate = document.getElementById('reservation_date').value;
            const rDate = this.value;
            
            if (!dDate) {
                alert('Select departure date first');
                this.value = '';
                return;
            }
            
            if (!rDate) return;
            
            const departTimestamp = new Date(dDate).getTime();
            const returnTimestamp = new Date(rDate).getTime();
            
            if (returnTimestamp < departTimestamp) {
                alert('Return date cannot be BEFORE departure date!');
                this.value = '';
                return;
            }
            
            const daysD = Math.ceil((returnTimestamp - departTimestamp) / (1000 * 60 * 60 * 24));
            if (daysD > 7) {
                alert('Maximum 7 days! Your trip: ' + daysD + ' days');
                this.value = '';
                return;
            }
            
            if (new Date(rDate).getDay() === 0) {
                alert('Return on Sunday not allowed');
                this.value = '';
                return;
            }
            
            checkAllFieldsFilled();
        });
        
        document.getElementById('return_time').addEventListener('change', function() {
            const dDate = document.getElementById('reservation_date').value;
            const dTime = document.getElementById('reservation_time').value;
            const rDate = document.getElementById('return_date').value;
            const rTime = this.value;
            
            if (dDate === rDate && dTime && rTime) {
                const dTimestamp = new Date(dDate + ' ' + dTime).getTime();
                const rTimestamp = new Date(rDate + ' ' + rTime).getTime();
                
                if (rTimestamp <= dTimestamp) {
                    alert('Return time must be AFTER departure time on same-day trips');
                    this.value = '';
                    return;
                }
            }
            
            checkAllFieldsFilled();
        });
        
        function checkBusAvailability() {
            const dDate = document.getElementById('reservation_date').value;
            const rDate = document.getElementById('return_date').value;
            
            if (!dDate || !rDate) return;
            
            document.querySelectorAll('.bus-card').forEach(card => {
                const busId = card.getAttribute('data-bus-id');
                card.classList.add('checking');
                document.getElementById('status-' + busId).textContent = 'Checking...';
                document.getElementById('status-' + busId).className = 'bus-status checking';
                
                fetch(`../api/check_availability.php?date=${encodeURIComponent(dDate)}&return_date=${encodeURIComponent(rDate)}&bus_id=${busId}`)
                    .then(res => res.json())
                    .then(data => {
                        card.classList.remove('checking');
                        const statusEl = document.getElementById('status-' + busId);
                        const infoEl = document.getElementById('info-' + busId);
                        
                        if (data.available) {
                            card.classList.remove('unavailable');
                            statusEl.textContent = 'Available';
                            statusEl.className = 'bus-status available';
                            infoEl.textContent = data.message || 'Available for your trip';
                            infoEl.style.color = 'green';
                        } else {
                            card.classList.add('unavailable');
                            statusEl.textContent = 'Not Available';
                            statusEl.className = 'bus-status unavailable';
                            infoEl.textContent = data.message || 'Already booked';
                            infoEl.style.color = 'red';
                        }
                    })
                    .catch(err => {
                        console.error('Error:', err);
                        card.classList.remove('checking');
                    });
            });
        }
        
        function selectBus(card, busId, busName, capacity) {
            if (!allFieldsFilled) {
                alert('Please fill in ALL date and time fields first');
                return;
            }
            
            if (card.classList.contains('unavailable')) {
                alert('This bus is not available for your selected dates');
                return;
            }
            
            if (card.classList.contains('step-disabled')) {
                return;
            }
            
            document.querySelectorAll('.bus-card').forEach(c => c.classList.remove('selected'));
            
            card.classList.add('selected');
            selectedBusId = busId;
            selectedBusName = busName;
            selectedBusCapacity = capacity;
            document.getElementById('selected_bus_id').value = busId;
            
            const passengerInput = document.getElementById('passenger_count');
            passengerInput.setAttribute('max', capacity);
            
            const hint = document.getElementById('capacity-hint');
            hint.textContent = `Maximum: ${capacity} passengers for ${busName}`;
            hint.style.color = '#28a745';
            hint.style.fontWeight = '600';
            
            if (parseInt(passengerInput.value) > capacity) {
                passengerInput.value = capacity;
                alert(`Adjusted to ${capacity} passengers (max for ${busName})`);
            }
        }
        
        document.getElementById('passenger_count').addEventListener('input', function() {
            if (selectedBusCapacity > 0) {
                const count = parseInt(this.value);
                if (count > selectedBusCapacity) {
                    this.value = selectedBusCapacity;
                    alert(`Maximum ${selectedBusCapacity} passengers for ${selectedBusName}`);
                }
            }
        });
        
        document.getElementById('reservationForm').addEventListener('submit', function(e) {
            if (!selectedBusId) {
                e.preventDefault();
                alert('Please select a bus');
                return false;
            }
            
            const dDate = document.getElementById('reservation_date').value;
            const dTime = document.getElementById('reservation_time').value;
            const rDate = document.getElementById('return_date').value;
            const rTime = document.getElementById('return_time').value;
            const purpose = document.getElementById('purpose').value;
            const destination = document.getElementById('destination').value;
            const passengers = document.getElementById('passenger_count').value;
            
            if (!dDate || !dTime || !rDate || !rTime || !purpose || !destination || !passengers) {
                e.preventDefault();
                alert('Please fill in all required fields');
                return false;
            }
            
            if (parseInt(passengers) > selectedBusCapacity) {
                e.preventDefault();
                alert(`Passenger count (${passengers}) exceeds bus capacity (${selectedBusCapacity})`);
                return false;
            }
            
            const dTimestamp = new Date(dDate).getTime();
            const rTimestamp = new Date(rDate).getTime();
            const days = Math.ceil((rTimestamp - dTimestamp) / (1000 * 60 * 60 * 24));
            
            const msg = `Confirm Reservation\n\n` +
                       `Bus: ${selectedBusName}\n` +
                       `Passengers: ${passengers}\n` +
                       `Departure: ${dDate} at ${dTime}\n` +
                       `Return: ${rDate} at ${rTime}\n` +
                       `Duration: ${days} day(s)\n` +
                       `Destination: ${destination}\n\n` +
                       `Proceed?`;
            
            return confirm(msg);
        });
    </script>
</body>
</html>
