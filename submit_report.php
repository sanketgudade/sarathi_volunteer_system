<?php
session_start();
require_once 'config/config.php';

if (!isset($_SESSION['volunteer_id'])) {
    echo json_encode(['success' => false, 'message' => 'Not logged in']);
    exit();
}

$conn = getDBConnection();
$volunteer_id = $_SESSION['volunteer_id'];
$task_id = intval($_POST['task_id'] ?? 0);
$report_text = mysqli_real_escape_string($conn, $_POST['report_text'] ?? '');

if (!$task_id || empty($report_text)) {
    echo json_encode(['success' => false, 'message' => 'Missing required fields']);
    exit();
}

// Check if volunteer has permission for this task
$task_check = "SELECT id FROM tasks WHERE id = ? AND assigned_to = ?";
$stmt = mysqli_prepare($conn, $task_check);
mysqli_stmt_bind_param($stmt, "ii", $task_id, $volunteer_id);
mysqli_stmt_execute($stmt);
$task_exists = mysqli_stmt_get_result($stmt)->num_rows > 0;

if (!$task_exists) {
    echo json_encode(['success' => false, 'message' => 'Task not assigned to you']);
    exit();
}

// Handle file uploads
$upload_dir = 'uploads/reports/';
if (!file_exists($upload_dir)) {
    mkdir($upload_dir, 0777, true);
}

$images = [];
$documents = [];
$videos = [];

function handleFileUpload($file, $type, $max_size, $allowed_extensions, &$array) {
    global $upload_dir;
    
    if (!isset($file['name'])) return;
    
    for ($i = 0; $i < count($file['name']); $i++) {
        if ($file['error'][$i] !== UPLOAD_ERR_OK) continue;
        
        if ($file['size'][$i] > $max_size) {
            echo json_encode(['success' => false, 'message' => "File too large: {$file['name'][$i]}"]);
            exit();
        }
        
        $extension = strtolower(pathinfo($file['name'][$i], PATHINFO_EXTENSION));
        if (!in_array($extension, $allowed_extensions)) {
            echo json_encode(['success' => false, 'message' => "Invalid file type: {$file['name'][$i]}"]);
            exit();
        }
        
        $filename = uniqid() . '_' . preg_replace('/[^a-zA-Z0-9.]/', '_', $file['name'][$i]);
        $destination = $upload_dir . $filename;
        
        if (move_uploaded_file($file['tmp_name'][$i], $destination)) {
            $array[] = $filename;
        }
    }
}

// Handle image uploads (max 5, 2MB each)
if (isset($_FILES['images'])) {
    handleFileUpload($_FILES['images'], 'image', 2 * 1024 * 1024, 
                    ['jpg', 'jpeg', 'png', 'gif'], $images);
}

// Handle document uploads (max 3, 5MB each)
if (isset($_FILES['documents'])) {
    handleFileUpload($_FILES['documents'], 'document', 5 * 1024 * 1024,
                    ['pdf', 'doc', 'docx', 'txt'], $documents);
}

// Handle video uploads (max 2, 10MB each)
if (isset($_FILES['videos'])) {
    handleFileUpload($_FILES['videos'], 'video', 10 * 1024 * 1024,
                    ['mp4', 'mov', 'avi', 'mkv'], $videos);
}

// Save report to database
$images_json = json_encode($images);
$documents_json = json_encode($documents);
$videos_json = json_encode($videos);

$sql = "INSERT INTO daily_reports 
        (volunteer_id, task_id, report_text, images, documents, videos, submitted_at) 
        VALUES (?, ?, ?, ?, ?, ?, NOW())";
$stmt = mysqli_prepare($conn, $sql);
mysqli_stmt_bind_param($stmt, "iissss", $volunteer_id, $task_id, $report_text, 
                      $images_json, $documents_json, $videos_json);

if (mysqli_stmt_execute($stmt)) {
    // Add points for submitting report
    $points_sql = "UPDATE volunteers SET total_points = total_points + 5 WHERE id = ?";
    $points_stmt = mysqli_prepare($conn, $points_sql);
    mysqli_stmt_bind_param($points_stmt, "i", $volunteer_id);
    mysqli_stmt_execute($points_stmt);
    
    echo json_encode(['success' => true, 'message' => 'Report submitted successfully']);
} else {
    echo json_encode(['success' => false, 'message' => 'Database error']);
}

mysqli_close($conn);
?>