<?php
session_start();
require_once 'config/config.php';

if (!isset($_SESSION['admin_id'])) {
    echo json_encode(['success' => false, 'message' => 'Not authorized']);
    exit();
}

if (!isset($_POST['leave_id'])) {
    echo json_encode(['success' => false, 'message' => 'No ID provided']);
    exit();
}

$conn = getDBConnection();
$leave_id = intval($_POST['leave_id']);

$sql = "DELETE FROM leave_requests WHERE id = ?";
$stmt = mysqli_prepare($conn, $sql);
mysqli_stmt_bind_param($stmt, "i", $leave_id);

if (mysqli_stmt_execute($stmt)) {
    echo json_encode(['success' => true, 'message' => 'Leave request deleted']);
} else {
    echo json_encode(['success' => false, 'message' => 'Error deleting leave request']);
}

mysqli_stmt_close($stmt);
mysqli_close($conn);
?>