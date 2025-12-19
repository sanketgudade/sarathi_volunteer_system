<?php
session_start();
require_once 'config/config.php';

if (!isset($_SESSION['volunteer_id'])) {
    echo json_encode(['success' => false, 'message' => 'Not logged in']);
    exit();
}

$conn = getDBConnection();
$volunteer_id = $_SESSION['volunteer_id'];
$latitude = floatval($_POST['latitude'] ?? 0);
$longitude = floatval($_POST['longitude'] ?? 0);
$accuracy = floatval($_POST['accuracy'] ?? 0);
$battery = intval($_POST['battery'] ?? 100);

// Update volunteer's location in volunteers table (YOUR STRUCTURE)
$update_sql = "UPDATE volunteers SET 
                last_location_lat = ?, 
                last_location_lng = ?,
                location_updated_at = NOW()
                WHERE id = ?";
$update_stmt = mysqli_prepare($conn, $update_sql);
mysqli_stmt_bind_param($update_stmt, "ddi", $latitude, $longitude, $volunteer_id);

if (mysqli_stmt_execute($update_stmt)) {
    // Also store in volunteer_locations table for tracking history
    $history_sql = "INSERT INTO volunteer_locations (volunteer_id, latitude, longitude, accuracy, battery_level) 
                    VALUES (?, ?, ?, ?, ?)";
    $history_stmt = mysqli_prepare($conn, $history_sql);
    mysqli_stmt_bind_param($history_stmt, "idddi", $volunteer_id, $latitude, $longitude, $accuracy, $battery);
    mysqli_stmt_execute($history_stmt);
    
    echo json_encode(['success' => true, 'timestamp' => date('Y-m-d H:i:s')]);
} else {
    echo json_encode(['success' => false, 'message' => 'Database error']);
}

mysqli_close($conn);
?>