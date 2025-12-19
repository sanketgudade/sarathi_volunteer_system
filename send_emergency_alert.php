<?php
session_start();
require_once 'config/config.php';

if (!isset($_SESSION['volunteer_id'])) {
    echo json_encode(['success' => false, 'message' => 'Not logged in']);
    exit();
}

$conn = getDBConnection();
$volunteer_id = $_SESSION['volunteer_id'];
$data = json_decode(file_get_contents('php://input'), true);

$latitude = $data['latitude'] ?? null;
$longitude = $data['longitude'] ?? null;
$volunteer_name = $data['volunteer_name'] ?? 'Volunteer';

// Create emergency alert
$sql = "INSERT INTO alerts (volunteer_id, title, description, severity, status, location_name, latitude, longitude) 
        VALUES (?, 'EMERGENCY ALERT', ?, 'critical', 'active', ?, ?, ?)";
        
$description = "Emergency alert from {$volunteer_name}. Immediate assistance required.";
$location = $latitude && $longitude ? "Volunteer's location" : "Location unknown";

$stmt = mysqli_prepare($conn, $sql);
mysqli_stmt_bind_param($stmt, "isssdd", $volunteer_id, $description, $location, $latitude, $longitude);

if (mysqli_stmt_execute($stmt)) {
    // Send notification to all admins
    $notif_sql = "INSERT INTO notifications (title, message, type, target, created_by, status) 
                  VALUES ('EMERGENCY ALERT', '{$volunteer_name} needs immediate assistance!', 
                         'urgent', 'all_admins', ?, 'sent')";
    mysqli_query($conn, $notif_sql);
    
    echo json_encode(['success' => true, 'message' => 'Emergency alert sent']);
} else {
    echo json_encode(['success' => false, 'message' => 'Database error']);
}

mysqli_close($conn);
?>