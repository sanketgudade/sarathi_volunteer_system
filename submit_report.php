<?php
session_start();
require_once 'config/config.php';

if (!isset($_SESSION['volunteer_id'])) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Not logged in']);
    exit();
}

$conn = getDBConnection();
$volunteer_id = $_SESSION['volunteer_id'];
$task_id = intval($_POST['task_id']);
$report_text = mysqli_real_escape_string($conn, $_POST['report_text']);

if (!$task_id || empty($report_text)) {
    echo json_encode(['success' => false, 'message' => 'Task ID and report text required']);
    exit();
}

// Create upload directory
$upload_dir = 'uploads/proofs/' . date('Y/m/d/');
if (!file_exists($upload_dir)) {
    mkdir($upload_dir, 0777, true);
}

// Process uploaded files
$images = [];
$documents = [];
$videos = [];

// Maximum file sizes (in bytes)
$max_image_size = 2 * 1024 * 1024; // 2MB
$max_document_size = 5 * 1024 * 1024; // 5MB
$max_video_size = 10 * 1024 * 1024; // 10MB

// Allowed file types
$allowed_images = ['jpg', 'jpeg', 'png', 'gif'];
$allowed_documents = ['pdf', 'doc', 'docx'];
$allowed_videos = ['mp4', 'mov', 'avi'];

function processUpload($file, $type, $max_size, $allowed_exts, $upload_dir) {
    $uploaded_files = [];
    
    if (is_array($file['name'])) {
        for ($i = 0; $i < count($file['name']); $i++) {
            if ($file['error'][$i] === UPLOAD_ERR_OK) {
                $result = handleSingleFile($file, $i, $type, $max_size, $allowed_exts, $upload_dir);
                if ($result) $uploaded_files[] = $result;
            }
        }
    } else {
        if ($file['error'] === UPLOAD_ERR_OK) {
            $result = handleSingleFile($file, null, $type, $max_size, $allowed_exts, $upload_dir);
            if ($result) $uploaded_files[] = $result;
        }
    }
    
    return $uploaded_files;
}

function handleSingleFile($file, $index, $type, $max_size, $allowed_exts, $upload_dir) {
    $filename = $index !== null ? $file['name'][$index] : $file['name'];
    $tmp_name = $index !== null ? $file['tmp_name'][$index] : $file['tmp_name'];
    $size = $index !== null ? $file['size'][$index] : $file['size'];
    $error = $index !== null ? $file['error'][$index] : $file['error'];
    
    if ($error !== UPLOAD_ERR_OK) {
        return false;
    }
    
    if ($size > $max_size) {
        return false;
    }
    
    $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
    if (!in_array($ext, $allowed_exts)) {
        return false;
    }
    
    // Generate unique filename
    $new_filename = uniqid() . '_' . time() . '.' . $ext;
    $destination = $upload_dir . $new_filename;
    
    if (move_uploaded_file($tmp_name, $destination)) {
        return $destination;
    }
    
    return false;
}

// Process images
if (isset($_FILES['images'])) {
    $images = processUpload($_FILES['images'], 'image', $max_image_size, $allowed_images, $upload_dir);
}

// Process documents
if (isset($_FILES['documents'])) {
    $documents = processUpload($_FILES['documents'], 'document', $max_document_size, $allowed_documents, $upload_dir);
}

// Process videos
if (isset($_FILES['videos'])) {
    $videos = processUpload($_FILES['videos'], 'video', $max_video_size, $allowed_videos, $upload_dir);
}

// Convert arrays to JSON for database storage
$images_json = json_encode($images);
$documents_json = json_encode($documents);
$videos_json = json_encode($videos);

// Insert into database
$sql = "INSERT INTO task_proofs 
        (task_id, volunteer_id, report_text, images, documents, videos, submitted_at) 
        VALUES (?, ?, ?, ?, ?, ?, NOW())";
$stmt = mysqli_prepare($conn, $sql);
mysqli_stmt_bind_param($stmt, "iissss", $task_id, $volunteer_id, $report_text, $images_json, $documents_json, $videos_json);

if (mysqli_stmt_execute($stmt)) {
    // Update task status
    $update_sql = "UPDATE tasks SET status = 'under_review' WHERE id = ?";
    $update_stmt = mysqli_prepare($conn, $update_sql);
    mysqli_stmt_bind_param($update_stmt, "i", $task_id);
    mysqli_stmt_execute($update_stmt);
    
    echo json_encode([
        'success' => true, 
        'message' => 'Report submitted successfully!',
        'files_count' => count($images) + count($documents) + count($videos)
    ]);
} else {
    echo json_encode(['success' => false, 'message' => 'Database error: ' . mysqli_error($conn)]);
}

mysqli_close($conn);
?>