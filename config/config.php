<?php
// Start session if not already started
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Database configuration
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', 'rajkumar@123');
define('DB_NAME', 'sarathi_volunteer_db');

// File upload configuration
define('MAX_FILE_SIZE', 5 * 1024 * 1024); // 5MB
define('UPLOAD_BASE_PATH', __DIR__ . '/../assets/uploads/');
define('ALLOWED_TYPES', ['jpg', 'jpeg', 'png', 'pdf', 'gif']);

// Admin credentials
define('ADMIN_USERNAME', 'Admin@1234@');
define('NGO_SECRET_KEY', 'NGO@1234@');

// Site configuration
define('SITE_URL', 'http://localhost/sarathi_volunteer_system/');

// Create database connection
function getDBConnection() {
    $conn = mysqli_connect(DB_HOST, DB_USER, DB_PASS, DB_NAME);
    
    if (!$conn) {
        die("Database connection failed: " . mysqli_connect_error());
    }
    
    mysqli_set_charset($conn, "utf8mb4");
    return $conn;
}

// Security functions
function sanitizeInput($data) {
    if ($data === null) return '';
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data, ENT_QUOTES, 'UTF-8');
    return $data;
}

function generateInviteCode() {
    return strtoupper(bin2hex(random_bytes(6)));
}

function generateRandomPassword($length = 8) {
    $chars = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789!@#$%^&*';
    $password = '';
    for ($i = 0; $i < $length; $i++) {
        $password .= $chars[random_int(0, strlen($chars) - 1)];
    }
    return $password;
}

function uploadFile($file, $type = 'document') {
    // Define subdirectories
    $type_folders = [
        'passport' => 'passport/',
        'aadhaar' => 'aadhaar/',
        'certificate' => 'certificate/',
        'document' => 'documents/'
    ];
    
    // Get the subfolder for this file type
    $subfolder = $type_folders[$type] ?? 'documents/';
    
    // Create full upload path
    $upload_dir = __DIR__ . '/../assets/uploads/' . $subfolder;
    
    // Create directory if it doesn't exist
    if (!is_dir($upload_dir)) {
        mkdir($upload_dir, 0777, true);
    }
    
    // Generate unique filename
    $timestamp = time();
    $random = bin2hex(random_bytes(8));
    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    
    // Create filename WITH type prefix
    $filename = $type . '_' . $timestamp . '_' . $random . '.' . $ext;
    
    $target_file = $upload_dir . $filename;
    
    // Move uploaded file
    if (move_uploaded_file($file['tmp_name'], $target_file)) {
        // Return RELATIVE PATH with subfolder
        return [
            'success' => true, 
            'file_path' => 'assets/uploads/' . $subfolder . $filename,
            'file_name' => $filename
        ];
    }
    
    return ['error' => 'Upload failed'];
}


// Check if user is logged in
function isLoggedIn() {
    return isset($_SESSION['user_id']) || isset($_SESSION['admin_id']);
}

// Redirect function
function redirect($url) {
    header("Location: " . $url);
    exit();
}

// Log activity
function logActivity($conn, $admin_id, $action_type, $description) {
    $sql = "INSERT INTO activity_logs (admin_id, action_type, description, ip_address, user_agent) 
            VALUES (?, ?, ?, ?, ?)";
    $stmt = mysqli_prepare($conn, $sql);
    $ip_address = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
    $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown';
    mysqli_stmt_bind_param($stmt, "issss", $admin_id, $action_type, $description, $ip_address, $user_agent);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);
}

// Get file type from extension
function getFileType($filename) {
    $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
    if (in_array($ext, ['jpg', 'jpeg', 'png', 'gif'])) {
        return 'image';
    } elseif ($ext == 'pdf') {
        return 'pdf';
    }
    return 'unknown';
}

// Validate file before upload
function validateFile($file) {
    if (!isset($file['tmp_name']) || empty($file['tmp_name'])) {
        return ['error' => 'No file uploaded'];
    }
    
    if ($file['size'] > MAX_FILE_SIZE) {
        return ['error' => 'File size exceeds 5MB limit'];
    }
    
    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if (!in_array($ext, ALLOWED_TYPES)) {
        return ['error' => 'Invalid file type. Allowed: JPG, PNG, GIF, PDF'];
    }
    
    return ['success' => true];
}
?>