<?php
require_once 'config/config.php';

if (!isset($_SESSION['admin_id'])) {
    echo json_encode([]);
    exit();
}

$conn = getDBConnection();

// Get latest locations from last 5 minutes
$sql = "SELECT v.id, vl.latitude, vl.longitude 
        FROM volunteers v
        JOIN volunteer_locations vl ON v.id = vl.volunteer_id
        WHERE vl.timestamp >= DATE_SUB(NOW(), INTERVAL 5 MINUTE)
        AND vl.id = (SELECT MAX(id) FROM volunteer_locations WHERE volunteer_id = v.id)
        ORDER BY vl.timestamp DESC";
        
$result = mysqli_query($conn, $sql);
$locations = [];

while($row = mysqli_fetch_assoc($result)) {
    $locations[] = $row;
}

echo json_encode($locations);
mysqli_close($conn);
?>