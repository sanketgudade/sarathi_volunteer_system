<?php
session_start();
require_once 'config/config.php';

if (!isset($_SESSION['admin_id'])) {
    echo json_encode(['success' => false, 'message' => 'Not authorized']);
    exit();
}

$conn = getDBConnection();

if (!isset($_GET['id'])) {
    echo json_encode(['success' => false, 'message' => 'No ID provided']);
    exit();
}

$leave_id = intval($_GET['id']);

$sql = "SELECT lr.*, v.name as volunteer_name, v.email as volunteer_email, 
               v.phone as volunteer_phone, a.username as processed_by_name
        FROM leave_requests lr
        JOIN volunteers v ON lr.volunteer_id = v.id
        LEFT JOIN admins a ON lr.processed_by = a.id
        WHERE lr.id = ?";

$stmt = mysqli_prepare($conn, $sql);
mysqli_stmt_bind_param($stmt, "i", $leave_id);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$leave = mysqli_fetch_assoc($stmt);

if ($leave) {
    echo json_encode(['success' => true, 'leave' => $leave]);
} else {
    echo json_encode(['success' => false, 'message' => 'Leave request not found']);
}

mysqli_stmt_close($stmt);
mysqli_close($conn);
?>