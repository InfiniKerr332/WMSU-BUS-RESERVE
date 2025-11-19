<?php
// Save as: api/check_availability.php

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/database.php';
require_once __DIR__ . '/../includes/functions.php';

header('Content-Type: application/json');

// Start session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Validate parameters
if (!isset($_GET['date']) || !isset($_GET['bus_id'])) {
    echo json_encode([
        'error' => 'Missing parameters',
        'available' => false,
        'message' => 'Date and bus_id required'
    ]);
    exit;
}

$departure_date = clean_input($_GET['date']);
$bus_id = (int)clean_input($_GET['bus_id']);
$return_date = isset($_GET['return_date']) && !empty($_GET['return_date']) ? clean_input($_GET['return_date']) : $departure_date;
$current_user_id = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : null;

try {
    $db = new Database();
    $conn = $db->connect();
    
    // Check if bus exists and is available
    $stmt = $conn->prepare("SELECT id, bus_name, plate_no, status FROM buses WHERE id = :bus_id AND (deleted = 0 OR deleted IS NULL)");
    $stmt->bindParam(':bus_id', $bus_id, PDO::PARAM_INT);
    $stmt->execute();
    $bus = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$bus) {
        echo json_encode([
            'available' => false,
            'error' => 'Bus not found',
            'message' => 'Bus does not exist'
        ]);
        exit;
    }
    
    if ($bus['status'] === 'unavailable') {
        echo json_encode([
            'available' => false,
            'bus_id' => $bus_id,
            'bus_name' => $bus['bus_name'],
            'message' => 'Bus disabled by administrator'
        ]);
        exit;
    }
    
    // Check for overlapping reservations
    // Two date ranges overlap if: (Start1 <= End2) AND (End1 >= Start2)
    $sql = "
        SELECT 
            id,
            reservation_date,
            COALESCE(return_date, reservation_date) as effective_return,
            user_id
        FROM reservations 
        WHERE bus_id = :bus_id 
        AND status IN ('pending', 'approved')
        AND (
            -- Check overlap: requested range overlaps with existing range
            (reservation_date <= :return_date)
            AND
            (COALESCE(return_date, reservation_date) >= :departure_date)
        )
    ";
    
    $stmt = $conn->prepare($sql);
    $stmt->bindParam(':bus_id', $bus_id, PDO::PARAM_INT);
    $stmt->bindParam(':departure_date', $departure_date, PDO::PARAM_STR);
    $stmt->bindParam(':return_date', $return_date, PDO::PARAM_STR);
    $stmt->execute();
    $conflicts = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $is_available = (count($conflicts) === 0);
    
    // Build message
    if ($is_available) {
        $depart = new DateTime($departure_date);
        $return = new DateTime($return_date);
        $days = $depart->diff($return)->days + 1;
        
        if ($departure_date === $return_date) {
            $message = "✅ Available for same-day trip on {$departure_date}";
        } else {
            $message = "✅ Available for your {$days}-day trip ({$departure_date} to {$return_date})";
        }
    } else {
        // Check if user already has booking
        $own_booking = false;
        if ($current_user_id) {
            foreach ($conflicts as $c) {
                if ($c['user_id'] == $current_user_id) {
                    $own_booking = true;
                    break;
                }
            }
        }
        
        // Build conflict list
        $conflict_dates = [];
        foreach ($conflicts as $c) {
            if ($c['reservation_date'] === $c['effective_return']) {
                $conflict_dates[] = $c['reservation_date'];
            } else {
                $conflict_dates[] = $c['reservation_date'] . ' to ' . $c['effective_return'];
            }
        }
        
        if ($own_booking) {
            $message = "❌ You already have a reservation: " . implode(', ', $conflict_dates);
        } else {
            $message = "❌ Already booked by another user: " . implode(', ', $conflict_dates);
        }
    }
    
    echo json_encode([
        'available' => $is_available,
        'bus_id' => $bus_id,
        'bus_name' => $bus['bus_name'],
        'date' => $departure_date,
        'return_date' => $return_date,
        'conflict_count' => count($conflicts),
        'message' => $message
    ]);
    
} catch (Exception $e) {
    error_log("check_availability.php error: " . $e->getMessage());
    echo json_encode([
        'available' => false,
        'error' => 'System error',
        'message' => 'Error checking availability'
    ]);
}
?>