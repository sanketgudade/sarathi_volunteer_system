<?php
session_start();
require_once 'config/config.php';

if (!isset($_SESSION['volunteer_id'])) {
    echo json_encode(['success' => false, 'message' => 'Not logged in']);
    exit();
}

$conn = getDBConnection();
$volunteer_id = $_SESSION['volunteer_id'];

// Check for pending leave requests
$check_sql = "SELECT id FROM leave_requests WHERE volunteer_id = ? AND status = 'pending'";
$check_stmt = mysqli_prepare($conn, $check_sql);
mysqli_stmt_bind_param($check_stmt, "i", $volunteer_id);
mysqli_stmt_execute($check_stmt);
mysqli_stmt_store_result($check_stmt);

if (mysqli_stmt_num_rows($check_stmt) > 0) {
    echo json_encode(['success' => false, 'message' => 'You already have a pending leave request']);
    mysqli_stmt_close($check_stmt);
    mysqli_close($conn);
    exit();
}
mysqli_stmt_close($check_stmt);

// Insert new leave request
$insert_sql = "INSERT INTO leave_requests 
               (volunteer_id, leave_type, start_date, end_date, total_days, reason, 
                contact_number, emergency_contact, status, created_at) 
               VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'pending', NOW())";

$insert_stmt = mysqli_prepare($conn, $insert_sql);

// Calculate total days
$start_date = $_POST['start_date'];
$end_date = $_POST['end_date'];
$total_days = ceil((strtotime($end_date) - strtotime($start_date)) / (60 * 60 * 24)) + 1;

mysqli_stmt_bind_param($insert_stmt, "isssisss", 
    $volunteer_id,
    $_POST['leave_type'],
    $start_date,
    $end_date,
    $total_days,
    $_POST['reason'],
    $_POST['contact_number'],
    $_POST['emergency_contact']
);

if (mysqli_stmt_execute($insert_stmt)) {
    echo json_encode(['success' => true, 'message' => 'Leave request submitted successfully']);
} else {
    echo json_encode(['success' => false, 'message' => 'Error submitting leave request']);
}

mysqli_stmt_close($insert_stmt);
mysqli_close($conn);
?>