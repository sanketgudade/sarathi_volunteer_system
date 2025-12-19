<?php
require_once 'config/config.php';

header('Content-Type: application/json');

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit();
}

$conn = getDBConnection();

// Get form data
$user_name = mysqli_real_escape_string($conn, $_POST['user_name']);
$user_email = mysqli_real_escape_string($conn, $_POST['user_email']);
$user_phone = mysqli_real_escape_string($conn, $_POST['user_phone']);
$problem_title = mysqli_real_escape_string($conn, $_POST['problem_title']);
$problem_type = mysqli_real_escape_string($conn, $_POST['problem_type']);
$problem_description = mysqli_real_escape_string($conn, $_POST['problem_description']);
$urgency_level = mysqli_real_escape_string($conn, $_POST['urgency_level']);
$area_location = mysqli_real_escape_string($conn, $_POST['area_location']);
$latitude = isset($_POST['latitude']) ? floatval($_POST['latitude']) : null;
$longitude = isset($_POST['longitude']) ? floatval($_POST['longitude']) : null;
$google_map_link = isset($_POST['google_map_link']) ? mysqli_real_escape_string($conn, $_POST['google_map_link']) : null;

// Validate required fields
if (empty($user_name) || empty($user_email) || empty($user_phone) || 
    empty($problem_title) || empty($problem_description) || empty($area_location)) {
    echo json_encode(['success' => false, 'message' => 'All required fields must be filled']);
    exit();
}

// Validate email
if (!filter_var($user_email, FILTER_VALIDATE_EMAIL)) {
    echo json_encode(['success' => false, 'message' => 'Invalid email address']);
    exit();
}

// Validate phone number (basic validation for Indian numbers)
if (!preg_match('/^[0-9]{10}$/', $user_phone)) {
    echo json_encode(['success' => false, 'message' => 'Invalid phone number. Please enter 10 digits']);
    exit();
}

// Handle image uploads
$image_paths = [];

if (isset($_FILES['images']) && count($_FILES['images']['name']) > 0) {
    $upload_dir = 'uploads/problems/';
    
    // Create upload directory if it doesn't exist
    if (!file_exists($upload_dir)) {
        mkdir($upload_dir, 0777, true);
    }
    
    for ($i = 0; $i < count($_FILES['images']['name']); $i++) {
        if ($_FILES['images']['error'][$i] === UPLOAD_ERR_OK) {
            $file_name = time() . '_' . uniqid() . '_' . basename($_FILES['images']['name'][$i]);
            $target_file = $upload_dir . $file_name;
            
            // Check file size (5MB max)
            if ($_FILES['images']['size'][$i] > 5 * 1024 * 1024) {
                continue; // Skip files that are too large
            }
            
            // Check file type
            $allowed_types = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif'];
            $file_type = mime_content_type($_FILES['images']['tmp_name'][$i]);
            
            if (in_array($file_type, $allowed_types)) {
                if (move_uploaded_file($_FILES['images']['tmp_name'][$i], $target_file)) {
                    $image_paths[] = $target_file;
                }
            }
        }
    }
}

// Convert image paths to JSON
$images_json = !empty($image_paths) ? json_encode($image_paths) : null;

// Insert problem into database
$sql = "INSERT INTO problems (
    user_name, user_email, user_phone, problem_title, problem_type, 
    problem_description, urgency_level, area_location, latitude, longitude, 
    google_map_link, images, status
) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending')";

$stmt = mysqli_prepare($conn, $sql);
mysqli_stmt_bind_param($stmt, "ssssssssddss", 
    $user_name, $user_email, $user_phone, $problem_title, $problem_type,
    $problem_description, $urgency_level, $area_location, $latitude, $longitude,
    $google_map_link, $images_json
);

if (mysqli_stmt_execute($stmt)) {
    $problem_id = mysqli_insert_id($conn);
    
    // Log admin activity
    if (isset($_SESSION['admin_id'])) {
        logAdminActivity($conn, $_SESSION['admin_id'], 'problem_reported', 
            "New problem reported: {$problem_title} by {$user_name}");
    }
    
    echo json_encode([
        'success' => true, 
        'message' => 'Problem reported successfully! Reference ID: PR-' . str_pad($problem_id, 6, '0', STR_PAD_LEFT),
        'problem_id' => $problem_id
    ]);
} else {
    error_log("Database error: " . mysqli_error($conn));
    echo json_encode([
        'success' => false, 
        'message' => 'Database error: ' . mysqli_error($conn)
    ]);
}

mysqli_stmt_close($stmt);
mysqli_close($conn);

function logAdminActivity($conn, $admin_id, $action_type, $description) {
    $ip_address = $_SERVER['REMOTE_ADDR'];
    $user_agent = $_SERVER['HTTP_USER_AGENT'];
    
    $sql = "INSERT INTO activity_logs 
            (admin_id, action_type, description, ip_address, user_agent, created_at) 
            VALUES (?, ?, ?, ?, ?, NOW())";
    
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, "issss", $admin_id, $action_type, $description, $ip_address, $user_agent);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);
}
?>