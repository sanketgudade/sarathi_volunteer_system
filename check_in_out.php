<?php
session_start();
require_once 'config/config.php';
require_once 'includes/location_validator.php';

if (!isset($_SESSION['volunteer_id'])) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Not logged in']);
    exit();
}

$conn = getDBConnection();
$volunteer_id = $_SESSION['volunteer_id'];
$action = $_POST['action']; // 'check_in' or 'check_out'
$lat = floatval($_POST['latitude']);
$lng = floatval($_POST['longitude']);
$task_id = $_POST['task_id'] ?? null;

// Get volunteer's current task if not provided
if (!$task_id) {
    $task_sql = "SELECT id FROM tasks WHERE assigned_to = ? 
                 AND status IN ('assigned', 'in_progress') 
                 ORDER BY created_at DESC LIMIT 1";
    $task_stmt = mysqli_prepare($conn, $task_sql);
    mysqli_stmt_bind_param($task_stmt, "i", $volunteer_id);
    mysqli_stmt_execute($task_stmt);
    $task_result = mysqli_fetch_assoc(mysqli_stmt_get_result($task_stmt));
    $task_id = $task_result['id'] ?? null;
}

// Validate location if task exists
if ($task_id) {
    $validation = LocationValidator::validateTaskLocation($task_id, $lat, $lng, $conn);
    
    if (!$validation['valid']) {
        echo json_encode([
            'success' => false, 
            'message' => $validation['message'],
            'distance' => $validation['distance']
        ]);
        exit();
    }
}

// Get address from coordinates
$address = LocationValidator::getAddressFromCoordinates($lat, $lng);

// Get device info
$device_info = $_SERVER['HTTP_USER_AGENT'];
$ip_address = $_SERVER['REMOTE_ADDR'];

if ($action === 'check_in') {
    // Check if already checked in today
    $check_sql = "SELECT id FROM volunteer_attendance 
                  WHERE volunteer_id = ? 
                  AND DATE(check_in_time) = CURDATE() 
                  AND check_out_time IS NULL";
    $check_stmt = mysqli_prepare($conn, $check_sql);
    mysqli_stmt_bind_param($check_stmt, "i", $volunteer_id);
    mysqli_stmt_execute($check_stmt);
    mysqli_stmt_store_result($check_stmt);
    
    if (mysqli_stmt_num_rows($check_stmt) > 0) {
        echo json_encode(['success' => false, 'message' => 'Already checked in today']);
        exit();
    }
    
    // Insert check-in
    $sql = "INSERT INTO volunteer_attendance 
            (volunteer_id, task_id, check_in_time, check_in_lat, check_in_lng, location_name, device_info, ip_address) 
            VALUES (?, ?, NOW(), ?, ?, ?, ?, ?)";
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, "iiddsss", $volunteer_id, $task_id, $lat, $lng, $address, $device_info, $ip_address);
    
} elseif ($action === 'check_out') {
    // Find today's check-in
    $find_sql = "SELECT id FROM volunteer_attendance 
                 WHERE volunteer_id = ? 
                 AND DATE(check_in_time) = CURDATE() 
                 AND check_out_time IS NULL 
                 ORDER BY id DESC LIMIT 1";
    $find_stmt = mysqli_prepare($conn, $find_sql);
    mysqli_stmt_bind_param($find_stmt, "i", $volunteer_id);
    mysqli_stmt_execute($find_stmt);
    $attendance = mysqli_fetch_assoc(mysqli_stmt_get_result($find_stmt));
    
    if (!$attendance) {
        echo json_encode(['success' => false, 'message' => 'No check-in found for today']);
        exit();
    }
    
    // Update check-out
    $sql = "UPDATE volunteer_attendance 
            SET check_out_time = NOW(), 
                check_out_lat = ?, 
                check_out_lng = ?
            WHERE id = ?";
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, "ddi", $lat, $lng, $attendance['id']);
}

if (mysqli_stmt_execute($stmt)) {
    // Update volunteer's last location
    $update_sql = "UPDATE volunteers 
                   SET last_location_lat = ?, 
                       last_location_lng = ?, 
                       location_updated_at = NOW()
                   WHERE id = ?";
    $update_stmt = mysqli_prepare($conn, $update_sql);
    mysqli_stmt_bind_param($update_stmt, "ddi", $lat, $lng, $volunteer_id);
    mysqli_stmt_execute($update_stmt);
    
    echo json_encode([
        'success' => true, 
        'message' => ucfirst(str_replace('_', ' ', $action)) . ' successful!',
        'location' => $address,
        'time' => date('h:i A')
    ]);
} else {
    echo json_encode(['success' => false, 'message' => 'Database error: ' . mysqli_error($conn)]);
}

mysqli_close($conn);
?>