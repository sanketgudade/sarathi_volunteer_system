<?php
session_start();
require_once 'config/config.php';

if (!isset($_SESSION['volunteer_id'])) {
    echo json_encode(['success' => false, 'message' => 'Not logged in']);
    exit();
}

$conn = getDBConnection();
$volunteer_id = $_SESSION['volunteer_id'];
$action = $_POST['action'] ?? '';
$latitude = floatval($_POST['latitude'] ?? 0);
$longitude = floatval($_POST['longitude'] ?? 0);
$task_id = intval($_POST['task_id'] ?? 0);

// Get today's task for the volunteer
if (!$task_id) {
    $task_sql = "SELECT id, title, location_name, latitude, longitude, geofence_radius 
                 FROM tasks 
                 WHERE assigned_to = ? 
                 AND DATE(deadline) >= CURDATE() 
                 AND status IN ('assigned', 'in_progress')
                 ORDER BY deadline ASC 
                 LIMIT 1";
    $stmt = mysqli_prepare($conn, $task_sql);
    mysqli_stmt_bind_param($stmt, "i", $volunteer_id);
    mysqli_stmt_execute($stmt);
    $task_result = mysqli_stmt_get_result($stmt);
    $task = mysqli_fetch_assoc($task_result);
    $task_id = $task['id'] ?? 0;
} else {
    $task_sql = "SELECT latitude, longitude, geofence_radius, location_name 
                 FROM tasks WHERE id = ?";
    $stmt = mysqli_prepare($conn, $task_sql);
    mysqli_stmt_bind_param($stmt, "i", $task_id);
    mysqli_stmt_execute($stmt);
    $task_result = mysqli_stmt_get_result($stmt);
    $task = mysqli_fetch_assoc($task_result);
}

if (!$task) {
    echo json_encode(['success' => false, 'message' => 'No task assigned']);
    exit();
}

// Calculate distance using Haversine formula
function calculateDistance($lat1, $lon1, $lat2, $lon2) {
    $earthRadius = 6371000; // meters
    
    $lat1 = deg2rad($lat1);
    $lon1 = deg2rad($lon1);
    $lat2 = deg2rad($lat2);
    $lon2 = deg2rad($lon2);
    
    $latDelta = $lat2 - $lat1;
    $lonDelta = $lon2 - $lon1;
    
    $angle = 2 * asin(sqrt(pow(sin($latDelta / 2), 2) + 
        cos($lat1) * cos($lat2) * pow(sin($lonDelta / 2), 2)));
    
    return $angle * $earthRadius;
}

$distance = calculateDistance($latitude, $longitude, 
                            $task['latitude'], $task['longitude']);

$geofence_radius = $task['geofence_radius'] ?? 100;

// Check if within allowed radius
if ($distance > $geofence_radius) {
    echo json_encode([
        'success' => false, 
        'message' => "You must be within {$geofence_radius}m of task location",
        'distance' => round($distance, 2)
    ]);
    exit();
}

// Check existing attendance for today
$attendance_sql = "SELECT * FROM volunteer_attendance 
                   WHERE volunteer_id = ? 
                   AND DATE(check_in_time) = CURDATE() 
                   ORDER BY id DESC LIMIT 1";
$stmt = mysqli_prepare($conn, $attendance_sql);
mysqli_stmt_bind_param($stmt, "i", $volunteer_id);
mysqli_stmt_execute($stmt);
$attendance = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));

if ($action === 'check_in') {
    if ($attendance) {
        echo json_encode(['success' => false, 'message' => 'Already checked in today']);
        exit();
    }
    
    // Record check-in
    $insert_sql = "INSERT INTO volunteer_attendance 
                   (volunteer_id, task_id, check_in_time, check_in_lat, check_in_lng, 
                    location_name, distance_from_task, status) 
                   VALUES (?, ?, NOW(), ?, ?, ?, ?, 'present')";
    $stmt = mysqli_prepare($conn, $insert_sql);
    mysqli_stmt_bind_param($stmt, "iiddsd", $volunteer_id, $task_id, $latitude, $longitude, 
                          $task['location_name'], $distance);
    
    if (mysqli_stmt_execute($stmt)) {
        // Update volunteer's last location
        $update_sql = "UPDATE volunteers SET last_location_update = NOW() WHERE id = ?";
        $update_stmt = mysqli_prepare($conn, $update_sql);
        mysqli_stmt_bind_param($update_stmt, "i", $volunteer_id);
        mysqli_stmt_execute($update_stmt);
        
        echo json_encode(['success' => true, 'message' => 'Checked in successfully']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Database error']);
    }
    
} elseif ($action === 'check_out') {
    if (!$attendance || $attendance['check_out_time']) {
        echo json_encode(['success' => false, 'message' => 'Not checked in or already checked out']);
        exit();
    }
    
    // Record check-out
    $update_sql = "UPDATE volunteer_attendance 
                   SET check_out_time = NOW(), 
                       check_out_lat = ?, 
                       check_out_lng = ?,
                       distance_from_task = ?
                   WHERE id = ?";
    $stmt = mysqli_prepare($conn, $update_sql);
    mysqli_stmt_bind_param($stmt, "dddi", $latitude, $longitude, $distance, $attendance['id']);
    
    if (mysqli_stmt_execute($stmt)) {
        // Update task status to completed
        $task_sql = "UPDATE tasks SET status = 'completed' WHERE id = ?";
        $task_stmt = mysqli_prepare($conn, $task_sql);
        mysqli_stmt_bind_param($task_stmt, "i", $task_id);
        mysqli_stmt_execute($task_stmt);
        
        // Add points for task completion
        $points_sql = "UPDATE volunteers 
                      SET total_points = total_points + 10, 
                          tasks_completed = tasks_completed + 1 
                      WHERE id = ?";
        $points_stmt = mysqli_prepare($conn, $points_sql);
        mysqli_stmt_bind_param($points_stmt, "i", $volunteer_id);
        mysqli_stmt_execute($points_stmt);
        
        echo json_encode(['success' => true, 'message' => 'Checked out successfully']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Database error']);
    }
}

mysqli_close($conn);
?>